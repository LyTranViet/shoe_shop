<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// Kiểm tra AJAX
function is_ajax() {
    // Kiểm tra header cũ (jQuery) hoặc header mới (fetch API)
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (!empty($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false);
}

// Trả JSON
function json_res($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

// === TÍNH TỔNG GIỎ HÀNG (chỉ tính subtotal raw, giảm giá áp ở JS) ===
function calculate_cart_total($db = null, $userId = null) {
    if (!$db) $db = get_db();
    $subtotal = 0;
    $items = [];

    if ($userId) {
        $stmt = $db->prepare('SELECT ci.price, ci.quantity FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?');
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();
    } else {
        foreach ($_SESSION['cart'] ?? [] as $it) {
            $p = $db->prepare('SELECT price FROM products WHERE id = ?');
            $p->execute([(int)$it['product_id']]);
            $price = (float)($p->fetchColumn() ?: 0);
            $items[] = ['price' => $price, 'quantity' => (int)$it['quantity']];
        }
    }

    foreach ($items as $it) $subtotal += $it['price'] * $it['quantity'];

    return round($subtotal);
}

// === XỬ LÝ POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    // ADD TO CART
    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $size = $_POST['size'] ?? null;

        // Không lưu session nữa, trả coupon từ POST để JS lưu localStorage
        $coupon_code = trim($_POST['coupon_code'] ?? '');
        $shipping_coupon = trim($_POST['validated_shipping_coupon_code'] ?? '');
        $shipping_discount = (float)($_POST['shipping_discount_amount'] ?? 0);

        if (is_logged_in()) {
            $db = get_db();
            $uid = current_user_id();
            try {
                $st = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
                $st->execute([$uid]);
                $cartId = $st->fetchColumn();
                if (!$cartId) {
                    $ins = $db->prepare('INSERT INTO carts (user_id) VALUES (?)');
                    $ins->execute([$uid]);
                    $cartId = $db->lastInsertId();
                }

                $ci = $db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL))');
                $ci->execute([$cartId, $pid, $size, $size]);
                $row = $ci->fetch();
                if ($row) {
                    $newQty = $row['quantity'] + $qty;
                    $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                    $up->execute([$newQty, $row['id']]);
                } else {
                    $pstmt = $db->prepare('SELECT price FROM products WHERE id = ?');
                    $pstmt->execute([$pid]);
                    $price = $pstmt->fetchColumn() ?: 0;
                    $ins = $db->prepare('INSERT INTO cart_items (cart_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
                    $ins->execute([$cartId, $pid, $size, $qty, $price]);
                }

                $count = $db->query("SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = $cartId")->fetchColumn();
                $total = calculate_cart_total($db, $uid);

                if (is_ajax()) json_res(['success' => true, 'count' => $count, 'total' => $total, 'coupon_code' => $coupon_code, 'shipping_coupon' => $shipping_coupon, 'shipping_discount' => $shipping_discount]);
                header('Location: cart.php'); exit;
            } catch (Exception $e) { }
        }

        // Session fallback
        $key = $pid . '|' . ($size ?? '');
        if (!isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key] = ['product_id' => $pid, 'quantity' => $qty, 'size' => $size];
        } else {
            $_SESSION['cart'][$key]['quantity'] += $qty;
        }

        $count = array_sum(array_column($_SESSION['cart'], 'quantity'));
        $total = calculate_cart_total();

        if (is_ajax()) json_res(['success' => true, 'count' => $count, 'total' => $total, 'coupon_code' => $coupon_code, 'shipping_coupon' => $shipping_coupon, 'shipping_discount' => $shipping_discount]);
        header('Location: cart.php'); exit;
    }

    // UPDATE
    if ($action === 'update') {
        $newQty = max(1, (int)($_POST['quantity'] ?? 1));
        if (is_logged_in()) {
            $db = get_db();
            $id = (int)($_POST['cart_item_id'] ?? 0);
            $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $up->execute([$newQty, $id]);
            $total = calculate_cart_total($db, current_user_id());
            $stmt = $db->prepare('SELECT price FROM cart_items WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $subtotal = $row ? $row['price'] * $newQty : 0;

            // Không áp giảm ở PHP nữa, trả raw subtotal
            $subtotal = round($subtotal);

            json_res(['success' => true, 'item_subtotal' => $subtotal, 'total' => $total]);
        } else {
            $key = $_POST['session_key'] ?? null;
            if ($key && isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] = $newQty;
            }
            $total = calculate_cart_total();
            $db = get_db();
            $item = $_SESSION['cart'][$key];
            $p = $db->prepare('SELECT price FROM products WHERE id = ?');
            $p->execute([(int)$item['product_id']]);
            $price = $p->fetchColumn() ?: 0;
            $subtotal = $price * $newQty;

            // Không áp giảm ở PHP nữa
            $subtotal = round($subtotal);

            json_res(['success' => true, 'item_subtotal' => $subtotal, 'total' => $total]);
        }
    }

    // REMOVE
    if ($action === 'remove') {
        if (is_logged_in()) {
            $db = get_db();
            $id = (int)($_POST['cart_item_id'] ?? 0);
            $db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$id]);
            $total = calculate_cart_total($db, current_user_id());
            $count = $db->query("SELECT COALESCE(SUM(quantity),0) FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = " . current_user_id())->fetchColumn();
            json_res(['success' => true, 'count' => $count, 'total' => $total]);
        } else {
            $key = $_POST['session_key'] ?? null;
            if ($key) unset($_SESSION['cart'][$key]);
            $total = calculate_cart_total();
            $count = array_sum(array_column($_SESSION['cart'], 'quantity'));
            json_res(['success' => true, 'count' => $count, 'total' => $total]);
        }
    }

    json_res(['success' => false, 'error' => 'Unknown action']);
}

// === LOAD GIỎ HÀNG ===
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$items = [];

if (is_logged_in()) {
    $uid = current_user_id();
    $st = $db->prepare('SELECT c.id FROM carts c WHERE c.user_id = ?');
    $st->execute([$uid]);
    $cartId = $st->fetchColumn();
    if ($cartId) {
        $stmt = $db->prepare('SELECT ci.id, ci.product_id, ci.size, ci.quantity, ci.price, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll();
    }
} else {
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $row = $p->fetch();
        if ($row) {
            $items[] = [
                'session_key' => $k,
                'product_id' => $it['product_id'],
                'size' => $it['size'] ?? null,
                'quantity' => $it['quantity'],
                'price' => $row['price'],
                'name' => $row['name']
            ];
        }
    }
}

// Tính tổng raw (không giảm)
$final_total = calculate_cart_total($db, is_logged_in() ? current_user_id() : null);

// Load ảnh
$imagesByProduct = [];
if (!empty($items)) {
    $ids = array_column($items, 'product_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT product_id, url FROM product_images WHERE product_id IN ($placeholders) AND is_main = 1");
    $stmt->execute($ids);
    $imagesByProduct = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<h2>Giỏ hàng của bạn</h2>

<?php if (empty($items)): ?>
    <p>Giỏ hàng trống.</p>
<?php else: ?>
    <div class="cart-layout">
        <div class="cart-items-list">
            <div class="cart-header">
                <div class="cart-header-product">Sản phẩm</div>
                <div class="cart-header-quantity">Số lượng</div>
                <div class="cart-header-coupon">Mã giảm giá</div>
                <div class="cart-header-subtotal">Thành tiền</div>
                <div class="cart-header-remove"></div>
            </div>
            <?php foreach ($items as $it):
                $attr = is_logged_in() ? 'data-cart-item-id="' . $it['id'] . '"' : 'data-session-key="' . htmlspecialchars($it['session_key']) . '"';
                $img = $imagesByProduct[$it['product_id']] ?? 'assets/images/product-placeholder.png';
                $item_price = $it['price'];
                $item_subtotal = $item_price * $it['quantity'];
                $item_subtotal = round($item_subtotal);
                
                // Lấy mã giảm giá từ localStorage (chỉ áp dụng nếu có)
                $applied_coupon_code = '';
                $coupon_display = '<em style="color:#666; font-size:0.9em;">—</em>';
            ?>
                    <div class="cart-item" <?php echo $attr; ?> data-product-id="<?php echo $it['product_id']; ?>">
            <div class="cart-item-product">
                <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="cart-item-image">
                <div class="cart-item-info">
                    <a href="product.php?id=<?php echo $it['product_id']; ?>" class="cart-item-name"><?php echo htmlspecialchars($it['name']); ?></a>
                    <?php if (!empty($it['size'])): ?><p class="cart-item-meta">Size: <?php echo htmlspecialchars($it['size']); ?></p><?php endif; ?>
                    <p class="cart-item-meta cart-item-price-mobile">Giá: <?php echo number_format($item_price, 0); ?>đ</p>
                </div>
            </div>
                    <!-- Vùng hiển thị lỗi tồn kho -->
                    <div class="cart-item-quantity">
                        <form class="ajax-cart-update" method="post">
                            <input type="hidden" name="action" value="update">
                            <?php if (is_logged_in()): ?>
                                <input type="hidden" name="cart_item_id" value="<?php echo $it['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="session_key" value="<?php echo htmlspecialchars($it['session_key']); ?>">
                            <?php endif; ?>
                            <div class="quantity-input">
                                <button type="button" class="qty-btn minus">−</button>
                                <input type="number" name="quantity" value="<?php echo $it['quantity']; ?>" min="1">
                                <button type="button" class="qty-btn plus">+</button>
                            </div>
                        </form>
                    </div>
                    <!-- CỘT MỚI: MÃ GIẢM GIÁ -->
                    <div class="cart-item-coupon">
                        <span class="coupon-code-display"><?php echo $coupon_display; ?></span>
                    </div>
                    <div class="cart-item-subtotal">
                        <span><?php echo number_format($item_subtotal, 0); ?></span>đ
                    </div>
                    <div class="cart-item-remove">
                        <form class="ajax-cart-remove" method="post">
                            <input type="hidden" name="action" value="remove">
                            <?php if (is_logged_in()): ?>
                                <input type="hidden" name="cart_item_id" value="<?php echo $it['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="session_key" value="<?php echo htmlspecialchars($it['session_key']); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn-remove-icon" title="Xóa sản phẩm">Xóa</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h3>Tóm tắt đơn hàng</h3>
           <!-- Chỉ hiển thị mã giảm phí vận chuyển -->
    <div id="shipping-coupon-alert"></div>
            <div class="summary-row">
                <span>Tạm tính</span>
                <strong id="cart-total"><?php echo number_format($final_total, 0); ?>đ</strong>
            </div>
            <p>Phí vận chuyển sẽ được tính tại bước thanh toán.</p>
            <button id="proceed-to-checkout-btn" class="btn checkout-btn">Tiến hành thanh toán</button>
        </aside>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Lấy elements với ID nhất quán
    const checkoutBtn = document.getElementById('proceed-to-checkout-btn'); // Đảm bảo khớp với HTML
    const cartTotalEl = document.getElementById('cart-total');
    const shippingAlertContainer = document.getElementById('shipping-coupon-alert');

    // Hàm format số
    function formatVND(amount) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(amount)) + 'đ';
    }

    // Áp dụng mã giảm giá sản phẩm (cho từng dòng)
    function applyProductCouponToItems() {
        const productCode = localStorage.getItem('product_coupon_code');
        const productData = localStorage.getItem('product_coupon_data');
        let discountPercent = 0;

        if (productData) {
            const coupon = JSON.parse(productData);
            discountPercent = parseFloat(coupon.discount_percent) || 0;
        }

        // Cập nhật từng dòng sản phẩm
        document.querySelectorAll('.cart-item').forEach(item => {
            const couponDisplay = item.querySelector('.coupon-code-display');
            const subtotalEl = item.querySelector('.cart-item-subtotal span');

            if (productCode && discountPercent > 0) {
                couponDisplay.innerHTML = `<strong style="color:green;">${productCode}</strong>`;
                let rawSubtotal = parseFloat(subtotalEl.dataset.raw || subtotalEl.textContent.replace(/[^\d]/g, ''));
                subtotalEl.dataset.raw = rawSubtotal; // Lưu giá gốc
                let discounted = rawSubtotal * (1 - discountPercent / 100);
                subtotalEl.textContent = Math.round(discounted);
            } else {
                couponDisplay.innerHTML = '<em style="color:#666; font-size:0.9em;">—</em>';
                // Khôi phục giá gốc nếu không có mã
                if (subtotalEl.dataset.raw) {
                    subtotalEl.textContent = Math.round(parseFloat(subtotalEl.dataset.raw));
                }
            }
        });

        // Cập nhật tổng
        if (discountPercent > 0 && cartTotalEl) {
            let total = parseFloat(cartTotalEl.dataset.raw || cartTotalEl.textContent.replace(/[^\d]/g, ''));
            cartTotalEl.dataset.raw = total;
            total = total * (1 - discountPercent / 100);
            cartTotalEl.textContent = formatVND(total);
        } else if (cartTotalEl && cartTotalEl.dataset.raw) {
            cartTotalEl.textContent = formatVND(parseFloat(cartTotalEl.dataset.raw));
        }
    }

    // Hiển thị mã giảm phí vận chuyển
    function applyShippingCouponAlert() {
        if (!shippingAlertContainer) return;
        const shippingCode = localStorage.getItem('shipping_coupon_code');
        const shippingData = localStorage.getItem('shipping_coupon_data');

        if (shippingCode && shippingData) {
            const coupon = JSON.parse(shippingData);
            const discount = coupon.value || 0;
            shippingAlertContainer.innerHTML = `
                <div class="alert alert-info" style="margin: 10px 0; padding: 8px; font-size: 0.9em;">
                    Đang áp dụng mã giảm phí vận chuyển: <strong>${shippingCode}</strong> 
                    (Giảm ${formatVND(discount)})
                </div>`;
        } else {
            shippingAlertContainer.innerHTML = '';
        }
    }

    // Gọi khi load
    applyProductCouponToItems();
    applyShippingCouponAlert();

    // Cập nhật tổng (raw → áp giảm JS)
    function updateTotal(rawTotal) {
        if (!cartTotalEl) return;
        cartTotalEl.dataset.raw = rawTotal;
        applyProductCouponToItems(); // Áp lại giảm giá
    }

    // Xóa alert khi giỏ trống
    function clearAlertsIfEmpty() {
        // Logic này sẽ được xử lý trong hàm remove()
    }

    // Xóa tất cả thông báo lỗi tồn kho
    function clearAllStockErrors() {
        document.querySelectorAll('.cart-item').forEach(item => {
            item.classList.remove('error-stock');
            const errorDiv = item.querySelector('.cart-item-stock-error');
            if (errorDiv) errorDiv.style.display = 'none';
        });
    }

    // Sử dụng event delegation để quản lý tất cả các sự kiện trong giỏ hàng
    document.querySelector('.cart-items-list')?.addEventListener('click', function(event) {
        const target = event.target;
        const cartItem = target.closest('.cart-item');
        if (!cartItem) return;

        const input = cartItem.querySelector('input[name="quantity"]');
        let currentQty = parseInt(input.value);

        if (target.classList.contains('qty-btn.plus')) {
            update(cartItem, currentQty + 1);
        } else if (target.classList.contains('qty-btn.minus')) {
            update(cartItem, Math.max(1, currentQty - 1));
        } else if (target.classList.contains('btn-remove-icon')) {
            event.preventDefault();
            remove(cartItem);
        }
    });

    // Xử lý khi người dùng tự gõ số lượng
    document.querySelectorAll('.cart-item input[name="quantity"]').forEach(input => {
        let updateTimeout;
        function handleManualInput() {
            clearTimeout(updateTimeout);
            const newQty = parseInt(input.value);
            if (newQty >= 1) {
                updateTimeout = setTimeout(() => update(input.closest('.cart-item'), newQty), 500);
            }
        }
        input.addEventListener('input', handleManualInput);
    });

    function update(cartItem, newQty) {
        const input = cartItem.querySelector('input[name="quantity"]');
        const subtotalEl = cartItem.querySelector('.cart-item-subtotal span');
        input.value = newQty;

        const form = cartItem.querySelector('.ajax-cart-update');
        const data = new FormData(form);
        data.set('quantity', newQty);

        fetch('cart.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    subtotalEl.textContent = new Intl.NumberFormat('vi-VN').format(res.item_subtotal);
                    subtotalEl.dataset.raw = res.item_subtotal; // Lưu raw
                    updateTotal(res.total);
                }
            });
    }

    function remove(cartItem) {
        const form = cartItem.querySelector('.ajax-cart-remove');
        const data = new FormData(form);
        fetch('cart.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    // Nếu giỏ hàng sẽ trở nên trống sau khi xóa, tải lại trang
                    if (document.querySelectorAll('.cart-item').length <= 1) {
                        window.location.reload();
                    } else {
                        cartItem.remove();
                        updateTotal(res.total);
                    }
                }
            });
    }

    // XỬ LÝ NÚT "TIẾN HÀNH THANH TOÁN" – THÊM KIỂM TRA NULL
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearAllStockErrors(); // Xóa lỗi cũ trước khi kiểm tra
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Đang kiểm tra...';

            fetch('validate_cart_stock.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Nếu không có lỗi, chuyển đến trang checkout
                        window.location.href = 'checkout.php';
                    } else {
                        // Nếu có lỗi, hiển thị thông báo
                        let alertMessage = 'Một số sản phẩm trong giỏ hàng không đủ số lượng. Vui lòng kiểm tra lại.';
                        if (data.errors && data.errors.length > 0) {
                            const firstError = data.errors[0];
                            alertMessage = `Sản phẩm "${firstError.name}" (Size: ${firstError.size}) chỉ còn ${firstError.available_stock} sản phẩm. Vui lòng cập nhật lại số lượng.`;
                        }
                        alert(alertMessage);

                        data.errors.forEach(error => {
                            const itemId = error.id ? `[data-cart-item-id="${error.id}"]` : `[data-session-key="${error.session_key}"]`;
                            const problematicItem = document.querySelector(`.cart-item${itemId}`);
                            if (problematicItem) {
                                problematicItem.classList.add('error-stock');
                                const errorDiv = problematicItem.querySelector('.cart-item-stock-error');
                                if (errorDiv) {
                                    // Dòng này đã được xóa theo yêu cầu để không hiển thị text lỗi bên dưới sản phẩm
                                }
                            }
                        });
                    }
                })
                .catch(err => {
                    console.error('Validation Error:', err);
                    alert('Đã xảy ra lỗi khi kiểm tra giỏ hàng. Vui lòng thử lại.');
                })
                .finally(() => {
                    // Bật lại nút sau khi xử lý xong
                    checkoutBtn.disabled = false;
                    checkoutBtn.textContent = 'Tiến hành thanh toán';
                });
        });
    } else {
        console.warn('checkoutBtn not found – Kiểm tra ID #proceed-to-checkout-btn trong HTML');
    }

    // Xóa alert nếu giỏ trống lúc load
    clearAlertsIfEmpty();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<style>
.cart-item.error-stock {
    border: 2px solid red;
    border-radius: 8px;
    background-color: #fff5f5;
}
</style>

<style>
    /* Ẩn các nút mũi tên (spinners) khỏi ô nhập số lượng */
    .quantity-input input[type=number]::-webkit-inner-spin-button,
    .quantity-input input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .quantity-input input[type=number] {
        -moz-appearance: textfield; /* Dành cho Firefox */
    }
</style>
<style>
/* === CẤU TRÚC GRID CHO GIỎ HÀNG === */
.cart-header,
.cart-item {
    display: grid;
    grid-template-columns: 2.5fr 1fr 0.8fr 1fr 0.5fr;
    gap: 12px;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.cart-header {
    font-weight: 600;
    color: #555;
    font-size: 0.95rem;
    padding-bottom: 8px;
    border-bottom: 2px solid #ddd;
}

/* === CHI TIẾT CÁC CỘT === */

/* 1. Sản phẩm */
.cart-item-product {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.95rem;
}
.cart-item-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
}
.cart-item-info {
    flex: 1;
    min-width: 0;
}
.cart-item-name {
    display: block;
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cart-item-meta {
    font-size: 0.85rem;
    color: #777;
    margin: 2px 0;
}

/* 2. Số lượng */
.cart-item-quantity {
    display: flex;
    justify-content: center;
}
.quantity-input {
    display: flex;
    align-items: center;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    width: fit-content;
}
.quantity-input button {
    width: 32px;
    height: 32px;
    border: none;
    background: #f8f8f8;
    font-size: 1.1rem;
    cursor: pointer;
    transition: 0.2s;
}
.quantity-input button:hover {
    background: #eee;
}
.quantity-input input {
    width: 40px;
    height: 32px;
    text-align: center;
    border: none;
    border-left: 1px solid #ddd;
    border-right: 1px solid #ddd;
    font-size: 0.95rem;
}

/* 3. Mã giảm giá */
.cart-item-coupon {
    text-align: center;
    font-size: 0.9rem;
    font-weight: 600;
}
.cart-item-coupon .coupon-code-display {
    color: #16a34a;
}
.cart-item-coupon em {
    color: #999;
    font-style: italic;
}

/* 4. Thành tiền */
.cart-item-subtotal {
    text-align: right;
    font-weight: 600;
    color: #d32f2f;
    font-size: 1rem;
    white-space: nowrap;
}

/* 5. Xóa */
.cart-item-remove {
    text-align: center;
}
.btn-remove-icon {
    background: none;
    border: none;
    font-size: 1.3rem;
    color: #999;
    cursor: pointer;
    padding: 4px;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
}
.btn-remove-icon:hover {
    background: #ffebee;
    color: #d32f2f;
}

/* === RESPONSIVE === */
@media (max-width: 992px) {
    .cart-header,
    .cart-item {
        grid-template-columns: 2fr 1fr 0.8fr 1fr 0.5fr;
        gap: 10px;
    }
    .cart-item-image { width: 50px; height: 50px; }
}

@media (max-width: 768px) {
    .cart-header,
    .cart-item {
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 16px;
        background: #fafafa;
        border-radius: 8px;
        margin-bottom: 12px;
        border: 1px solid #eee;
    }

    /* Ẩn header trên mobile */
    .cart-header { display: none; }

    /* Sắp xếp lại thứ tự */
    .cart-item-product { order: 1; }
    .cart-item-quantity { order: 2; justify-content: flex-start; }
    .cart-item-coupon { order: 3; text-align: left; }
    .cart-item-subtotal { order: 4; text-align: left; font-size: 1.1rem; }
    .cart-item-remove { order: 5; text-align: left; }

    /* Hiển thị nhãn trên mobile */
    .cart-item-quantity::before { content: "Số lượng: "; font-weight: 600; margin-right: 8px; color: #555; }
    .cart-item-coupon::before { content: "Mã giảm: "; font-weight: 600; margin-right: 8px; color: #555; }
    .cart-item-subtotal::before { content: "Thành tiền: "; font-weight: 600; margin-right: 8px; color: #00eb14ff; }
}
</style>