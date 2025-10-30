<?php
// Checkout: create orders based on cart (DB-backed for logged-in users, session for guests)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// require login for checkout
if (!is_logged_in()) {
    flash_set('info','Please login to checkout');
    header('Location: /login.php'); exit;
}

$db = get_db();
$userId = current_user_id();

const SHIPPING_FEE = 30.00; // Phí vận chuyển cố định

// helper to compute cart items and totals
function get_cart_items_and_total($db) {
    if (is_logged_in()) {
        $uid = current_user_id();
        $st = $db->prepare('SELECT c.id AS cart_id FROM carts c WHERE c.user_id = ? LIMIT 1');
        $st->execute([$uid]);
        $cartId = $st->fetchColumn();
        if (!$cartId) return [[],0,0];
        $itSt = $db->prepare('SELECT ci.id AS cart_item_id, ci.product_id, ci.size, ci.quantity, ci.price, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $itSt->execute([$cartId]);
        $items = $itSt->fetchAll();
        $total = 0; foreach ($items as $it) $total += ((float)$it['price'])*((int)$it['quantity']);
        return [$items,$total,$cartId];
    }
    // guests (shouldn't reach because we require login), fallback to session
    $items = [];$total=0;
    foreach ($_SESSION['cart'] ?? [] as $k=>$it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?'); $p->execute([(int)$it['product_id']]); $row = $p->fetch();
        if (!$row) continue;
        $items[] = ['cart_item_id'=>$k,'product_id'=>$it['product_id'],'size'=>$it['size'] ?? null,'quantity'=>$it['quantity'],'price'=>$row['price'],'name'=>$row['name']];
        $total += ((float)$row['price'])*((int)$it['quantity']);
    }
    return [$items,$total,null];
}

// apply coupon if valid
function validate_coupon($db, $code) {
    if (!$code) return null;
    $st = $db->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $st->execute([$code]);
    $c = $st->fetch();
    if (!$c) return null;
    $now = date('Y-m-d H:i:s');
    if (($c['valid_from'] && $now < $c['valid_from']) || ($c['valid_to'] && $now > $c['valid_to'])) return null;
    // Note: usage_limit not enforced here (would require tracking); assume valid
    return $c;
}

// POST: place order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $payment = $_POST['payment_method'] ?? 'COD';
    $couponCode = trim($_POST['validated_coupon_code'] ?? ''); // Chỉ sử dụng mã đã được xác thực từ JS

    if ($address === '') { flash_set('error','Please enter shipping address'); header('Location: checkout.php'); exit; }

    list($items,$subtotal,$cartId) = get_cart_items_and_total($db);
    if (empty($items)) { flash_set('error','Your cart is empty'); header('Location: cart.php'); exit; }

    // validate stock for each item
    foreach ($items as $it) {
        $size = $it['size'] ?? null;
        if ($size) {
            $sSt = $db->prepare('SELECT stock FROM product_sizes WHERE product_id = ? AND size = ? LIMIT 1');
            $sSt->execute([$it['product_id'],$size]);
            $stock = $sSt->fetchColumn();
            if ($stock === false) { flash_set('error','Size not available for product: ' . $it['name']); header('Location: cart.php'); exit; }
            if ((int)$stock < (int)$it['quantity']) { flash_set('error','Not enough stock for ' . $it['name'] . ' size ' . $size); header('Location: cart.php'); exit; }
        }
    }

    $coupon = validate_coupon($db, $couponCode);
    $discount = 0;
    if ($coupon) { $discount = ((int)$coupon['discount_percent']) * $subtotal / 100.0; }
    $totalAmount = $subtotal - $discount + SHIPPING_FEE;

    // create order in transaction
    try {
        $db->beginTransaction();
        $statusId = 1; // 'Chờ xử lý'
        $couponId = $coupon ? $coupon['id'] : null;
        $ins = $db->prepare('INSERT INTO orders (user_id, total_amount, shipping_address, status_id, coupon_id, payment_method) VALUES (?,?,?,?,?,?)');
        $ins->execute([$userId, $totalAmount, $address, $statusId, $couponId, $payment]);
        $orderId = $db->lastInsertId();
        
        // insert order_items
        foreach ($items as $it) {
            $pId = $it['product_id']; $qty = (int)$it['quantity']; $price = (float)$it['price']; $size = $it['size'] ?? null;
            $oi = $db->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
            $oi->execute([$orderId, $pId, $size, $qty, $price]);
        }

        // --- TỰ ĐỘNG TẠO PHIẾU XUẤT KHO VÀ TRỪ TỒN KHO (TẠM GIỮ) ---
        $exportCode = 'PX-ORD' . $orderId;
        $exportNote = 'Tự động tạo cho đơn hàng #' . $orderId;
        $exportStmt = $db->prepare("INSERT INTO export_receipt (receipt_code, export_type, status, employee_id, total_amount, note, order_id) VALUES (?, 'Bán hàng', 'Đang xử lý', ?, ?, ?, ?)");
        $exportStmt->execute([$exportCode, $userId, $totalAmount, $exportNote, $orderId]);
        $export_id = $db->lastInsertId();

        foreach ($items as $item) {
            $quantity_to_export = (int)$item['quantity'];
            
            // Lấy productsize_id từ product_id và size
            $psStmt = $db->prepare("SELECT id FROM product_sizes WHERE product_id = ? AND size = ?");
            $psStmt->execute([$item['product_id'], $item['size']]);
            $productsize_id = $psStmt->fetchColumn();

            if (!$productsize_id) continue; // Bỏ qua nếu không tìm thấy size

            // Tìm các lô hàng (batch) theo FIFO để trừ kho
            $batchesStmt = $db->prepare("SELECT id, quantity_remaining FROM product_batch WHERE productsize_id = ? AND quantity_remaining > 0 ORDER BY import_date ASC");
            $batchesStmt->execute([$productsize_id]);

            $quantity_left_to_deduct = $quantity_to_export;
            while ($quantity_left_to_deduct > 0 && ($batch = $batchesStmt->fetch())) {
                $deduct_from_this_batch = min($quantity_left_to_deduct, (int)$batch['quantity_remaining']);

                // Tạo chi tiết phiếu xuất
                $db->prepare("INSERT INTO export_receipt_detail (export_id, batch_id, productsize_id, quantity, price) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$export_id, $batch['id'], $productsize_id, $deduct_from_this_batch, $item['price']]);

                // Trừ tồn kho (tạm giữ)
                $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining - ? WHERE id = ?")->execute([$deduct_from_this_batch, $batch['id']]);
                $db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ?")->execute([$deduct_from_this_batch, $productsize_id]);

                $quantity_left_to_deduct -= $deduct_from_this_batch;
            }
        }

        // clear cart
        if ($cartId) {
            $del = $db->prepare('DELETE FROM cart_items WHERE cart_id = ?'); $del->execute([$cartId]);
        }

        $db->commit();
        // clear session cart as well
        unset($_SESSION['cart']);
        flash_set('success','Đặt hàng thành công. Mã đơn hàng của bạn là: ' . $orderId);
        header('Location: index.php'); exit;
    } catch (Exception $e) {
        $db->rollBack();
        flash_set('error','Could not place order: ' . $e->getMessage());
        header('Location: cart.php'); exit;
    }
}

// Render checkout page with summary
require_once __DIR__ . '/includes/header.php';
list($items,$subtotal,$cartId) = get_cart_items_and_total($db);
// Lấy coupon từ session (nếu có) để tự động điền
$sessionCoupon = $_SESSION['coupon_code'] ?? '';

$userPhone = '';
try { $ust = $db->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1'); $ust->execute([$userId]); $userPhone = $ust->fetchColumn() ?: ''; } catch (Exception $e) { }
?>
<h2>Checkout</h2>
<?php if ($m=flash_get('error')): ?><p style="color:red"><?php echo htmlspecialchars($m); ?></p><?php endif; ?>
<?php if ($m=flash_get('success')): ?><p style="color:green"><?php echo htmlspecialchars($m); ?></p><?php endif; ?>

<div class="checkout-layout">
    <section>
        <form method="post">
            <h3>Shipping information</h3>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" required rows="4"></textarea>
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($userPhone); ?>">
            </div>
            <div class="form-group">
                <label for="payment_method">Payment method</label>
                <select id="payment_method" name="payment_method"><option value="COD">Cash on delivery</option><option value="CARD">Card (offline)</option></select>
            </div>
            <div class="form-group coupon-group">
                <label for="coupon_code">Mã giảm giá (tùy chọn)</label>
                <div class="input-with-button">
                    <input type="text" id="coupon_code" name="coupon_code" value="<?php echo htmlspecialchars($sessionCoupon); ?>" placeholder="Dán mã vào đây" data-auto-validate="<?php echo !empty($sessionCoupon) ? 'true' : 'false'; ?>">
                    <button type="button" id="paste-and-validate-checkout-btn" class="btn small">Dán & Kiểm tra</button>
                </div>
                <div class="coupon-result"></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit" id="place-order-btn">Place order</button></div>
        </form>
    </section>
    <aside>
        <h3>Order summary</h3>
        <?php if (empty($items)): ?><p>Your cart is empty.</p><?php else: ?>
            <div class="checkout-summary-items">
                <?php
                // Load images for summary
                $summary_pids = array_column($items, 'product_id');
                $summary_images = [];
                if (!empty($summary_pids)) {
                    $placeholders = implode(',', array_fill(0, count($summary_pids), '?'));
                    $img_st = $db->prepare("SELECT product_id, url FROM product_images WHERE product_id IN ($placeholders) AND is_main = 1");
                    $img_st->execute($summary_pids);
                    $summary_images = $img_st->fetchAll(PDO::FETCH_KEY_PAIR);
                }
                ?>
                <?php foreach ($items as $it): ?>
                    <div class="summary-item">
                        <div class="summary-item-image">
                            <img src="<?php echo htmlspecialchars($summary_images[$it['product_id']] ?? 'assets/images/product-placeholder.png'); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>">
                            <span class="summary-item-quantity"><?php echo (int)$it['quantity']; ?></span>
                        </div>
                        <div class="summary-item-info">
                            <span class="summary-item-name"><?php echo htmlspecialchars($it['name']); ?></span>
                            <span class="summary-item-size"><?php if (!empty($it['size'])) echo 'Size: ' . htmlspecialchars($it['size']); ?></span>
                        </div>
                        <div class="summary-item-price">$<?php echo number_format($it['price'] * $it['quantity'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-totals-table">
                <table>
                    <tbody>
                        <tr>
                            <td>Tạm tính</td>
                            <td id="summary-subtotal" data-value="<?= $subtotal ?>"><?= number_format($subtotal, 0) ?>₫</td>
                        </tr>
                        <tr id="summary-discount-row" style="display: none;">
                            <td id="summary-discount-label">Giảm giá</td>
                            <td id="summary-discount-amount" style="color: #28a745;">-0₫</td>
                        </tr>
                        <tr id="summary-subtotal-after-discount-row" style="display: none;">
                            <td>Giá sau giảm</td>
                            <td id="summary-subtotal-after-discount">0₫</td>
                        </tr>
                        <tr>
                            <td>Phí vận chuyển</td>
                            <td id="summary-shipping-fee" data-value="<?= SHIPPING_FEE ?>"><?= number_format(SHIPPING_FEE, 0) ?>₫</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <th>Tổng cộng</th>
                            <th id="summary-total"><?= number_format($subtotal + SHIPPING_FEE, 0) ?>₫</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
?>
<style>
/* --- Coupon Input Group Styles --- */
.input-with-button {
    display: flex;
    margin-top: 5px;
}
.input-with-button input {
    flex-grow: 1;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
}
.input-with-button button {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    white-space: nowrap; /* Prevent text wrapping */
}

/* Coupon result message */
.coupon-result {
    margin-top: 8px;
    font-weight: 500;
    font-size: 0.9em;
}
.coupon-result.success {
    color: #28a745;
}
.coupon-result.error {
    color: #dc3545;
}

.summary-totals-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: 16px;
}
.summary-totals-table td, .summary-totals-table th {
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
}
.summary-totals-table tr:last-child td, .summary-totals-table tr:last-child th {
    border-bottom: none;
}
.summary-totals-table td:last-child, .summary-totals-table th:last-child {
    text-align: right;
    font-weight: bold;
}
.summary-totals-table .total-row th {
    font-size: 20px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Coupon Validation on Checkout Page ---
    const checkoutValidateBtn = document.getElementById('paste-and-validate-checkout-btn');
    if (checkoutValidateBtn) {
        checkoutValidateBtn.addEventListener('click', handlePasteAndValidateCheckout);

        // Tự động xác thực coupon nếu có sẵn khi tải trang
        const couponInput = document.getElementById('coupon_code');
        if (couponInput && couponInput.dataset.autoValidate === 'true' && couponInput.value) {
            // Sử dụng một chút delay để đảm bảo trang đã tải xong
            setTimeout(() => handlePasteAndValidateCheckout(false), 100);
        }
    }

    async function handlePasteAndValidateCheckout(fromClick = true) {
        const couponInput = document.getElementById('coupon_code');
        const resultDiv = document.querySelector('.checkout-layout .coupon-result');
        resultDiv.textContent = '';
        resultDiv.className = 'coupon-result';

        // Tìm hoặc tạo trường input ẩn để lưu mã đã xác thực
        let validatedCouponInput = document.getElementById('validated_coupon_code');
        if (!validatedCouponInput) {
            validatedCouponInput = document.createElement('input');
            validatedCouponInput.type = 'hidden';
            validatedCouponInput.id = 'validated_coupon_code';
            validatedCouponInput.name = 'validated_coupon_code'; // Tên này sẽ được gửi lên server
            couponInput.form.appendChild(validatedCouponInput);
        }

        let code = couponInput.value.trim();

        // Nếu người dùng nhấn nút, cố gắng dán từ clipboard
        if (fromClick) {
            try {
                const clipboardText = await navigator.clipboard.readText();
                if (clipboardText) {
                    couponInput.value = clipboardText;
                    code = clipboardText;
                }
            } catch (err) {
                console.warn('Không thể đọc clipboard:', err);
            }
        }

        if (!code) {
            resultDiv.textContent = 'Vui lòng nhập hoặc dán mã giảm giá.';
            resultDiv.className = 'coupon-result error';
            updateCheckoutSummary(0); // Reset discount
            return;
        }

        try {
            const formData = new FormData();
            formData.append('code', code);

            const response = await fetch('validate_coupon.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                resultDiv.textContent = `✓ ${data.message}`;
                resultDiv.className = 'coupon-result success';
                validatedCouponInput.value = code; // Lưu mã hợp lệ vào trường ẩn
                updateCheckoutSummary(data.discount_percent); // Gọi hàm cập nhật giao diện
            } else {
                throw new Error(data.message || 'Mã không hợp lệ.');
            }
        } catch (err) {
            resultDiv.textContent = `✗ ${err.message}`;
            resultDiv.className = 'coupon-result error';
            validatedCouponInput.value = ''; // Xóa mã không hợp lệ
            updateCheckoutSummary(0); // Reset discount on error
        }
    }

    // Hàm cập nhật giao diện Order Summary
    function updateCheckoutSummary(discountPercent) {
        const subtotalEl = document.getElementById('summary-subtotal');
        const discountRowEl = document.getElementById('summary-discount-row');
        const discountAmountEl = document.getElementById('summary-discount-amount');
        const discountLabelEl = document.getElementById('summary-discount-label');
        const subtotalAfterDiscountRowEl = document.getElementById('summary-subtotal-after-discount-row');
        const subtotalAfterDiscountEl = document.getElementById('summary-subtotal-after-discount');
        const totalEl = document.getElementById('summary-total');
        const shippingFeeEl = document.getElementById('summary-shipping-fee');
        if (!subtotalEl || !discountRowEl || !discountAmountEl || !discountLabelEl || !totalEl || !shippingFeeEl || !subtotalAfterDiscountRowEl || !subtotalAfterDiscountEl) return;

        const subtotal = parseFloat(subtotalEl.dataset.value || '0');
        const shippingFee = parseFloat(shippingFeeEl.dataset.value || '0');
        const discountAmount = (subtotal * discountPercent) / 100;
        const subtotalAfterDiscount = subtotal - discountAmount;
        const total = subtotalAfterDiscount + shippingFee;

        const formatPrice = (value) => new Intl.NumberFormat('vi-VN').format(value) + '₫';

        if (discountPercent > 0) {
            discountLabelEl.textContent = `Giảm giá (${discountPercent}%)`;
            discountAmountEl.textContent = `- ${formatPrice(discountAmount)}`;
            subtotalAfterDiscountEl.textContent = formatPrice(subtotalAfterDiscount); // This line is correct
            discountRowEl.style.display = 'table-row'; // Use 'table-row' for tables
            subtotalAfterDiscountRowEl.style.display = 'table-row'; // Use 'table-row' for tables
            subtotalEl.style.textDecoration = 'line-through';
        } else {
            discountRowEl.style.display = 'none'; // 'none' is correct for hiding
            subtotalAfterDiscountRowEl.style.display = 'none'; // 'none' is correct for hiding
            subtotalEl.style.textDecoration = 'none';
        }
        totalEl.textContent = formatPrice(total);
    }
});
</script>
