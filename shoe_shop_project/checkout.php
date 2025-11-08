<?php
ob_start();
// Checkout: create orders based on cart (DB-backed for logged-in users, session for guests)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

require_once __DIR__ . '/includes/init.php'; // Nạp init.php để có BASE_URL và các hàm
// Lấy order_success, ưu tiên GET
$serverOrderSuccess = $_GET['order_success'] ?? ($_SESSION['order_success'] ?? null);

// Log để kiểm tra
error_log("CHECKOUT: order_success = " . var_export($serverOrderSuccess, true));

// Xóa session nếu có, tránh lặp modal khi F5
if (isset($_SESSION['order_success'])) {
    unset($_SESSION['order_success']);
}


// Thêm cấu hình VNPay chung (chèn bắt buộc để các khối VNPay dùng được)
$vnp_config = [
    'TmnCode'   => 'VO2O0KDK',
    'HashSecret' => 'UPP5TZKX60XTIKA60DRSV6HHE847PKS5',
    'BaseUrl'   => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
    'Version'   => '2.1.0',
    'Command'   => 'pay',
    'CurrCode'  => 'VND',
    'Locale'    => 'vn',
];
// tiện khai báo các biến thường dùng
$vnp_TmnCode = $vnp_config['TmnCode'];
$vnp_HashSecret = $vnp_config['HashSecret'];
$vnp_Url = $vnp_config['BaseUrl'];
$vnp_ReturnUrl = 'http://localhost/shoe_shop/shoe_shop_project/vnpay_return.php'; // Hardcode full URL

// require login for checkout
if (!is_logged_in()) {
    $_SESSION['return_to'] = BASE_URL . 'checkout.php'; // Lưu lại trang checkout để quay lại sau khi đăng nhập
    flash_set('info', 'Vui lòng đăng nhập để tiến hành thanh toán.');
    header('Location: ' . BASE_URL . 'login.php'); 
    exit;
}

$db = get_db();
$userId = current_user_id();

// helper to compute cart items and totals
function get_cart_items_and_total($db)
{
    if (is_logged_in()) {
        $uid = current_user_id();
        $st = $db->prepare('SELECT c.id AS cart_id FROM carts c WHERE c.user_id = ? LIMIT 1');
        $st->execute([$uid]);
        $cartId = $st->fetchColumn();
        if (!$cartId) return [[], 0, 0];
        $itSt = $db->prepare('SELECT ci.id AS cart_item_id, ci.product_id, ci.size, ci.quantity, ci.price, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $itSt->execute([$cartId]);
        $items = $itSt->fetchAll();
        $total = 0;
        foreach ($items as $it) $total += ((float)$it['price']) * ((int)$it['quantity']);
        return [$items, $total, $cartId];
    }
    // guests (shouldn't reach because we require login), fallback to session
    $items = [];
    $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $row = $p->fetch();
        if (!$row) continue;
        $items[] = ['cart_item_id' => $k, 'product_id' => $it['product_id'], 'size' => $it['size'] ?? null, 'quantity' => $it['quantity'], 'price' => $row['price'], 'name' => $row['name']];
        $total += ((float)$row['price']) * ((int)$it['quantity']);
    }
    return [$items, $total, null];
}

// apply coupon if valid
function validate_coupon($db, $code)
{
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
    $shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;
    $shipping_carrier = $_POST['shipping_carrier'] ?? 'GHN';
    $couponCode = trim($_POST['validated_coupon_code'] ?? ''); // Chỉ sử dụng mã đã được xác thực từ JS

    if ($address === '') {
        flash_set('error', 'Please enter shipping address');
        header('Location: checkout.php');
        exit;
    }

    list($items, $subtotal, $cartId) = get_cart_items_and_total($db);
    if (empty($items)) {
        flash_set('error', 'Your cart is empty');
        header('Location: cart.php');
        exit;
    }

    // validate stock for each item
    foreach ($items as $it) {
        $size = $it['size'] ?? null;
        if ($size) {
            $sSt = $db->prepare('SELECT stock FROM product_sizes WHERE product_id = ? AND size = ? LIMIT 1');
            $sSt->execute([$it['product_id'], $size]);
            $stock = $sSt->fetchColumn();
            if ($stock === false) {
                flash_set('error', 'Size not available for product: ' . $it['name']);
                header('Location: cart.php');
                exit;
            }
            if ((int)$stock < (int)$it['quantity']) {
                flash_set('error', 'Not enough stock for ' . $it['name'] . ' size ' . $size);
                header('Location: cart.php');
                exit;
            }
        }
    }

    $coupon = validate_coupon($db, $couponCode);
    $discount = 0;
    if ($coupon) {
        $discount = ((int)$coupon['discount_percent']) * $subtotal / 100.0;
    }

    // ----- Áp dụng mã giảm phí vận chuyển -----
    $shipping_coupon_code = trim($_POST['shipping_coupon_code'] ?? '');
    $shipping_discount = 0;
    $shipping_message = '';
    $shipping_success = false;

    // NHẬN PHÍ CUỐI TỪ FORM (JS đã giảm rồi)
    $final_shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;
    $shipping_discount = 0; // mặc định

    // Chỉ validate mã giảm để lưu discount_amount (không dùng để tính lại phí)
    $shipping_coupon_code = trim($_POST['shipping_coupon_code'] ?? '');
    if ($shipping_coupon_code !== '') {
        $stmt = $db->prepare("
        SELECT * 
        FROM shipping_coupons
        WHERE UPPER(CODE) = UPPER(?)
          AND active = 1
          AND (expire_date IS NULL OR expire_date >= CURDATE())
        LIMIT 1
    ");
        $stmt->execute([$shipping_coupon_code]);
        $ship_coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ship_coupon) {
            $ship_type = strtolower($ship_coupon['TYPE']);
            $ship_value = (float)$ship_coupon['VALUE'];

            // Tính discount để lưu vào DB (không thay đổi $final_shipping_fee)
            if ($ship_type === 'percent') {
                $shipping_discount = ($final_shipping_fee * 100 / (100 - $ship_value)) - $final_shipping_fee;
            } else {
                $shipping_discount = $ship_value;
            }

            // Đảm bảo discount không âm
            $shipping_discount = max(0, $shipping_discount);
        }
    }


    // ----- TÍNH TỔNG CUỐI CÙNG -----
    $total_Amount = $subtotal + $final_shipping_fee - $discount;

    // Debug
    error_log("Tổng đơn: $subtotal - $discount + $final_shipping_fee = $total_Amount");

    // VNPay handling is implemented in the main create-order section below (avoid duplicate blocks)

    // Nếu user chọn VNPay => tạo order (tạm trạng thái chờ thanh toán), commit rồi redirect sang VNPay
    if (strtoupper($payment) === 'VNPAY') {
        // Tạo đơn hàng trước
        try {
            $db->beginTransaction();
            $statusId = 1; // chờ thanh toán
            $couponId = $coupon ? $coupon['id'] : null;

            $ins = $db->prepare('INSERT INTO orders (user_id, total_amount, shipping_address, phone, status_id, coupon_id, payment_method, shipping_fee, shipping_carrier, discount_amount, coupon_code, shipping_discount_amount, shipping_coupon_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([$userId, $total_Amount, $address, $phone, $statusId, $couponId, 'VNPAY', $final_shipping_fee, $shipping_carrier, $discount, $couponCode, $shipping_discount, $shipping_coupon_code]);

            $orderId = $db->lastInsertId();

            // Insert order items
            foreach ($items as $it) {
                $oi = $db->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
                $oi->execute([$orderId, $it['product_id'], $it['size'] ?? null, (int)$it['quantity'], (float)$it['price']]);
            }

            $db->commit();

            // Chuẩn bị dữ liệu cho VNPay
            $vnp_TxnRef = $orderId; // Mã đơn hàng
            $vnp_Amount = (int)round($total_Amount * 100); // Số tiền * 100 (VNPay yêu cầu)
            $vnp_OrderInfo = 'Thanh toan don hang #' . $orderId;
            $vnp_OrderType = 'billpayment'; // Loại hóa đơn
            $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
            $vnp_CreateDate = date('YmdHis');

            $inputData = array(
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => $vnp_TmnCode,
                "vnp_Amount" => $vnp_Amount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => $vnp_CreateDate,
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => $vnp_IpAddr,
                "vnp_Locale" => "vn",
                "vnp_OrderInfo" => $vnp_OrderInfo,
                "vnp_OrderType" => $vnp_OrderType,
                "vnp_ReturnUrl" => $vnp_ReturnUrl,
                "vnp_TxnRef" => $vnp_TxnRef
            );

            ksort($inputData);
            $query = "";
            $i = 0;
            $hashdata = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashdata .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }

            $vnp_Url = $vnp_config['BaseUrl'] . "?" . $query;
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;

            header('Location: ' . $vnp_Url);
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            error_log("VNPay error: " . $e->getMessage());
            flash_set('error', 'Lỗi khi tạo thanh toán');
            header('Location: checkout.php');
            exit();
        }
    }

    // Nếu không phải VNPay thì xử lý như cũ
    // create order in transaction
    try {
        // COMMON: insert order row (status 'Chờ xử lý' / awaiting)
        $db->beginTransaction();
        $statusId = 1; // 'Chờ xử lý' (chưa thanh toán)
        $couponId = $coupon ? $coupon['id'] : null;

        $ins = $db->prepare('
        INSERT INTO orders 
(user_id, total_amount, shipping_address, phone, status_id, coupon_id, payment_method, 
 shipping_fee, shipping_carrier, discount_amount, coupon_code, 
 shipping_discount_amount, shipping_coupon_code)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');
        $ins->execute([
            $userId,
            $total_Amount,
            $address,
            $phone,
            $statusId,
            $couponId,
            $payment,
            $final_shipping_fee,
            $shipping_carrier,
            $discount,
            $couponCode,
            $shipping_discount,
            $shipping_coupon_code
        ]);

        $orderId = $db->lastInsertId();

        // insert order_items
        foreach ($items as $it) {
            $pId = $it['product_id'];
            $qty = (int)$it['quantity'];
            $price = (float)$it['price'];
            $size = $it['size'] ?? null;
            $oi = $db->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
            $oi->execute([$orderId, $pId, $size, $qty, $price]);
        }

        // If payment is VNPay: (duplicate block removed — VNPay is handled earlier)
        if (strtoupper($payment) === 'VNPAY') {
            // VNPay already initialized earlier and user should have been redirected.
            // Stop further processing here to avoid duplicate redirects/operations.
            exit;
        }

        // ELSE (non-VNPAY): proceed with original flow (create export, deduct stock, clear cart)
        // --- TỰ ĐỘNG TẠO PHIẾU XUẤT KHO ---
        $exportCode = 'PX-ORD' . $orderId;
        $exportNote = 'Tự động tạo cho đơn hàng #' . $orderId;
        $exportStmt = $db->prepare("INSERT INTO export_receipt (receipt_code, export_type, status, employee_id, total_amount, note, order_id) VALUES (?, 'Bán hàng', 'Đang xử lý', ?, ?, ?, ?)");
        $exportStmt->execute([$exportCode, $userId, $total_Amount, $exportNote, $orderId]);
        $export_id = $db->lastInsertId();

        foreach ($items as $item) {
            $quantity_to_export = (int)$item['quantity'];
            $psStmt = $db->prepare("SELECT id FROM product_sizes WHERE product_id = ? AND size = ?");
            $psStmt->execute([$item['product_id'], $item['size']]);
            $productsize_id = $psStmt->fetchColumn();
            if (!$productsize_id) continue;

            $batchesStmt = $db->prepare("SELECT id, quantity_remaining FROM product_batch WHERE productsize_id = ? AND quantity_remaining > 0 ORDER BY import_date ASC");
            $batchesStmt->execute([$productsize_id]);

            $quantity_left_to_deduct = $quantity_to_export;
            while ($quantity_left_to_deduct > 0 && ($batch = $batchesStmt->fetch())) {
                $deduct_from_this_batch = min($quantity_left_to_deduct, (int)$batch['quantity_remaining']);

                // fixed: prepare + execute must be separate calls (or chained correctly)
                $db->prepare("INSERT INTO export_receipt_detail (export_id, batch_id, productsize_id, quantity, price) VALUES (?, ?, ?, ?, ?)")->execute([$export_id, $batch['id'], $productsize_id, $deduct_from_this_batch, $item['price']]);

                $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining - ? WHERE id = ?")
                    ->execute([$deduct_from_this_batch, $batch['id']]);
                $db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ?")
                    ->execute([$deduct_from_this_batch, $productsize_id]);

                $quantity_left_to_deduct -= $deduct_from_this_batch;
            }
        }

        // clear cart
        if ($cartId) {
            $del = $db->prepare('DELETE FROM cart_items WHERE cart_id = ?');
            $del->execute([$cartId]);
        }

        $db->commit();
        unset($_SESSION['cart']);
        flash_set('success', 'Đặt hàng thành công. Mã đơn hàng của bạn là: ' . $orderId);
        header('Location: checkout.php?order_success=' . $orderId);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("LỖI TẠO ĐƠN HÀNG: " . $e->getMessage() . " | STACK: " . $e->getTraceAsString());
        die('<pre>Lỗi hệ thống. Vui lòng thử lại sau. Mã lỗi: ' . $e->getMessage() . '</pre>');
    }
}

// Render checkout page with summary
require_once __DIR__ . '/includes/header.php';
list($items, $subtotal, $cartId) = get_cart_items_and_total($db);
// Lấy coupon từ session (nếu có) để tự động điền
$sessionCoupon = $_SESSION['coupon_code'] ?? '';

$userPhone = '';
try {
    $ust = $db->prepare('SELECT phone FROM users WHERE id = ? LIMIT 1');
    $ust->execute([$userId]);
    $userPhone = $ust->fetchColumn() ?: '';
} catch (Exception $e) {
}

// Lấy order_success từ query string HOẶC session (session là fallback)
$serverOrderSuccess = null;
if (isset($_GET['order_success']) && $_GET['order_success'] !== '') {
    $serverOrderSuccess = $_GET['order_success'];
} elseif (isset($_SESSION['order_success'])) {
    $serverOrderSuccess = $_SESSION['order_success'];
    unset($_SESSION['order_success']); // tránh hiển thị lại sau refresh
}
?>
<h2>Checkout</h2>
<?php if ($m = flash_get('error')): ?><p style="color:red"><?php echo htmlspecialchars($m); ?></p><?php endif; ?>
<?php if ($m = flash_get('success')): ?><p style="color:green"><?php echo htmlspecialchars($m); ?></p><?php endif; ?>

<div class="checkout-layout">
    <section>
        <form method="post">
            <h3>Shipping information</h3>

            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="province">Tỉnh / Thành phố</label>
                        <select class="form-control" id="province" name="province">
                            <option value="">-- Chọn tỉnh/thành --</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label for="district">Quận / Huyện</label>
                        <select class="form-control" id="district" name="district" disabled>
                            <option value="">-- Chọn quận/huyện --</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label for="ward">Phường / Xã</label>
                        <select class="form-control" id="ward" name="ward" disabled>
                            <option value="">-- Chọn phường/xã --</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Chọn dịch vụ giao hàng</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input carrier-select" type="radio" name="carrier" id="carrierGHN"
                        value="GHN" checked>
                    <label class="form-check-label" for="carrierGHN">Giao Hàng Nhanh (GHN)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input carrier-select" type="radio" name="carrier" id="carrierLalamove"
                        value="GHTK">
                    <label class="form-check-label" for="carrierLalamove">Giao hàng tiết kiệm(GHTK)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input carrier-select" type="radio" name="carrier" id="carrierShoeShopShip"
                        value="ShoeShopShip">
                    <label class="form-check-label" for="carrierShoeShopShip">ShoeShopShip</label>
                </div>

                <input type="hidden" id="shipping-fee-input" name="shipping_fee" value="">
                <input type="hidden" id="shipping-carrier-input" name="shipping_carrier" value="GHN">

            </div>
            <div class="form-group">
                <label for="address">Địa chỉ chi tiết (VD: Số nhà, Tên đường)</label>
                <input type="text" class="form-control" id="address" name="address" required>
            </div>

            <div class="form-group">
                <label for="phone">Số điện thoại</label>
                <input type="text" class="form-control" id="phone" name="phone"
                    value="<?php echo htmlspecialchars($userPhone); ?>" required>
            </div>

            <div class="form-group coupon-group">
                <label for="coupon_code">Mã giảm giá</label>
                <input type="text" id="coupon_code" name="coupon_code"
                    value="<?php echo htmlspecialchars($sessionCoupon); ?>">
                <button type="button" id="validate-checkout-coupon-btn" class="btn small"
                    style="margin-top: 4px;">Apply</button>
                <div class="coupon-result"></div>
            </div>
            <!-- Mã giảm phí vận chuyển -->
            <div class="form-group mt-3">
                <label for="shipping_coupon_code" class="font-semibold">Mã giảm phí vận chuyển</label>
                <div class="input-group">
                    <input type="text" id="shipping_coupon_code" name="shipping_coupon_code" class="form-control"
                        placeholder="Nhập mã vận chuyển, ví dụ: SHIP80">
                    <button type="button" id="applyShippingCoupon" class="btn btn-outline-primary">Áp dụng</button>
                </div>
                <small id="shippingCouponMessage" class="text-success"></small>
            </div>

            <!-- New Payment Buttons -->
            <div class="form-actions payment-buttons">
                <button class="btn btn-cod" type="submit" name="payment_method" value="COD">Đặt hàng COD</button>
                <button class="btn btn-vnpay" type="submit" name="payment_method" value="VNPAY">
                    <img src="assets/images/vnpay_logo.png" alt="VNPay Logo" class="vnpay-logo">
                    <span class="vnpay-text">
                        <span class="vnpay-vn">VN</span><span class="vnpay-pay">PAY</span>
                    </span>
                </button>
                <!-- PayPal Button Container is now inside the flex container -->
                <div id="paypal-button-container"></div>
            </div>
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
                            <img src="<?php echo htmlspecialchars($summary_images[$it['product_id']] ?? 'assets/images/product-placeholder.png'); ?>"
                                alt="<?php echo htmlspecialchars($it['name']); ?>">
                            <span class="summary-item-quantity"><?php echo (int)$it['quantity']; ?></span>
                        </div>
                        <div class="summary-item-info">
                            <span class="summary-item-name"><?php echo htmlspecialchars($it['name']); ?></span>
                            <span
                                class="summary-item-size"><?php if (!empty($it['size'])) echo 'Size: ' . htmlspecialchars($it['size']); ?></span>
                        </div>
                        <div class="summary-item-price"><?php echo number_format($it['price'] * $it['quantity'], 0); ?>₫</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="summary-totals-table mt-3">
                <table class="table w-100">
                    <tbody>
                        <tr>
                            <td>Tạm tính</td>
                            <td id="summary-subtotal" data-value="<?= $subtotal ?>" class="text-end">
                                <?= number_format($subtotal, 0) ?>₫
                            </td>
                        </tr>

                        <tr id="summary-discount-row" style="display:none;">
                            <td id="summary-discount-label">Giảm giá</td>
                            <td class="text-right">
                                <span id="summary-discount-amount" data-vnd="0">0₫</span>
                            </td>
                        </tr>

                        <tr id="summary-subtotal-after-discount-row" style="display:none;">
                            <td>Giá sau giảm</td>
                            <td id="summary-subtotal-after-discount" class="text-end">0₫</td>
                        </tr>

                        <!-- Phí vận chuyển -->
                        <tr id="shipping-fee-row" style="display:none;">
                            <td>Phí vận chuyển</td>
                            <td class="text-end">
                                <span id="original-shipping-fee"
                                    style="text-decoration:line-through; color:#999; display:none;"></span>
                                <strong id="shipping-fee-text">—</strong>
                                <div class="small text-success" id="shipping-discount-info" style="display:none;"></div>
                                <div class="small text-muted" id="shipping-fee-detail"></div>
                            </td>
                        </tr>

                        <!-- Phí GHTK (nếu có) -->
                        <tr id="ghtk-fee-row" style="display:none;">
                            <td>Phí GHTK</td>
                            <td class="text-end">
                                <strong id="ghtk-fee-text">—</strong>
                                <div class="small text-muted" id="ghtk-fee-detail"></div>
                            </td>
                        </tr>
                    </tbody>

                    <tfoot>
                        <tr class="total-row border-top">
                            <th>Tổng cộng</th>
                            <th id="summary-total" class="text-end">
                                <?= number_format($subtotal, 0) ?>₫
                            </th>
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
        white-space: nowrap;
        /* Prevent text wrapping */
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

    .summary-totals-table td,
    .summary-totals-table th {
        padding: 12px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .summary-totals-table tr:last-child td,
    .summary-totals-table tr:last-child th {
        border-bottom: none;
    }

    .summary-totals-table td:last-child,
    .summary-totals-table th:last-child {
        text-align: right;
        font-weight: bold;
    }

    .summary-totals-table .total-row th {
        font-size: 20px;
    }

    /* --- New Payment Button Styles --- */
    .payment-buttons {
        display: flex;
        flex-direction: column;
        gap: 10px;
        /* Space between buttons */
        /* Removed align-items: center to make buttons left-aligned and full width */
    }

    .payment-buttons .btn {
        width: 100%;
        /* Ensure all payment options take full width */
        padding: 12px;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: background-color 0.2s ease-in-out;
    }

    #paypal-button-container {
        width: 100%;
        /* Ensure PayPal button container also takes full width */
    }

    .btn-cod {
        background-color: #6c757d;
        /* Bootstrap secondary grey */
        color: white;
        border: none;
    }

    .btn-cod:hover {
        background-color: #5a6268;
        color: white;
    }

    .btn-vnpay {
        background-color: #ffffff;
        border: 1px solid #dee2e6;
    }

    .btn-vnpay:hover {
        background-color: #f8f9fa;
    }

    .btn-vnpay .vnpay-logo {
        height: 24px;
        /* Adjust as needed */
        width: auto;
    }

    .btn-vnpay .vnpay-text {
        display: flex;
        align-items: center;
        gap: 2px;
        /* Small gap between VN and PAY */
    }

    .btn-vnpay .vnpay-vn {
        color: #E50019;
        /* VNPAY Red */
        font-weight: bold;
    }

    .btn-vnpay .vnpay-pay {
        color: #005baa;
        /* VNPAY Blue */
        font-weight: bold;
    }
</style>
<script src="https://code.jquery.com/jquery-3.4.0.min.js"></script>
<script
    src="https://www.paypal.com/sdk/js?client-id=Ab4kmqecM_NRnL8i9rrLZtklHlFaspC7IGKFeW7JDFMWoIA8oWF2V326kFxtVYUyE14ap-chRZu1U77P&currency=USD">
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        $('.carrier-select').on('change', function() {
            calculateShippingFee();
        });
        // === BIẾN TOÀN CỤC ===
        window.shippingDiscountPercent = 0;
        window.shippingDiscountAmount = 0;
        let selectedProvinceName = "";
        let selectedDistrictName = "";
        let selectedWardName = "";
        let selectedDistrictId = 0;
        let selectedWardCode = "";
        let serviceTypeId = 2;
        const GHN_TOKEN = "658b57db-acf1-11f0-93b8-b675d1187f91";

        // === HÀM CẬP NHẬT TỔNG TIỀN (CHỈ KHAI BÁO 1 LẦN) ===
        window.updateSummaryTotal = function(shippingFeeVND = 0, originalFee = 0, discountInfo = '') {
            const subtotal = parseFloat($('#summary-subtotal').data('value')) || 0;
            const discount = parseFloat($('#summary-discount-amount').data('vnd')) || 0;
            const total = subtotal - discount + shippingFeeVND;

            if (originalFee > 0 && shippingFeeVND < originalFee && discountInfo) {
                $('#original-shipping-fee').text(originalFee.toLocaleString('vi-VN') + ' ₫').show();
                $('#shipping-fee-text').text(shippingFeeVND.toLocaleString('vi-VN') + ' ₫');
                $('#shipping-discount-info').text(discountInfo).show();
            } else {
                $('#original-shipping-fee').hide();
                $('#shipping-fee-text').text(shippingFeeVND.toLocaleString('vi-VN') + ' ₫');
                $('#shipping-discount-info').hide();
            }

            $('#summary-total').text(total.toLocaleString('vi-VN') + ' ₫');
            $('#shipping-fee-input').val(shippingFeeVND);
            $('#shipping-fee-row').show();
        };

        // === HÀM TÍNH PHÍ VẬN CHUYỂN ===
        window.calculateShippingFee = function() {
            if (!selectedDistrictId || !selectedWardCode) {
                $('#shipping-fee-text').html('Vui lòng chọn đủ địa chỉ');
                $('#shipping-fee-input').val(0);
                updateSummaryTotal(0);
                return;
            }

            $('#shipping-fee-text').html('Đang tính...');

            // 🧩 Lấy carrier được chọn
            const selectedCarrier = $('input[name="carrier"]:checked').val() || 'GHN';
            $('#shipping-carrier-input').val(selectedCarrier);

            $.ajax({
                url: "CalculateFee.php",
                method: "POST",
                dataType: "json",
                data: {
                    districtId: selectedDistrictId,
                    wardCode: selectedWardCode,
                    serviceTypeId: serviceTypeId,
                    carrier: selectedCarrier // 👉 TRUYỀN VÀO BACKEND
                },
                success: function(response) {
                    if (response && response.error === false) {
                        const feeVND = Number(response.fee);
                        if (isNaN(feeVND) || feeVND <= 0) {
                            $('#shipping-fee-text').html(
                                '<span style="color:red">Không lấy được phí</span>');
                            $('#shipping-fee-input').val(0);
                            updateSummaryTotal(0);
                            return;
                        }

                        let finalFeeVND = feeVND;
                        let discountText = '';

                        // 🧾 Áp dụng mã giảm phí nếu có
                        const couponData = localStorage.getItem('shipping_coupon_data');
                        if (couponData) {
                            const coupon = JSON.parse(couponData);
                            if (coupon.type === 'percent') {
                                const discount = (feeVND * coupon.value) / 100;
                                finalFeeVND = Math.max(0, feeVND - discount);
                                discountText = `Giảm ${coupon.value}% phí vận chuyển`;
                            } else if (coupon.type === 'fixed') {
                                finalFeeVND = Math.max(0, feeVND - coupon.value);
                                discountText =
                                    `Giảm ${coupon.value.toLocaleString('vi-VN')}₫ phí vận chuyển`;
                            }
                        }

                        // 🖋️ Cập nhật giao diện
                        $('#shipping-fee-text').html('<strong>' + finalFeeVND.toLocaleString(
                            'vi-VN') + ' ₫</strong>');
                        $('#shipping-fee-detail').text(selectedCarrier +
                            ' - tính theo địa chỉ đã chọn');
                        $('#shipping-fee-input').val(finalFeeVND);
                        updateSummaryTotal(finalFeeVND, feeVND, discountText);
                    } else {
                        $('#shipping-fee-text').html('<span style="color:red">Lỗi tính phí</span>');
                        $('#shipping-fee-input').val(0);
                        updateSummaryTotal(0);
                    }
                },
                error: function() {
                    $('#shipping-fee-text').html('<span style="color:red">Lỗi mạng</span>');
                    $('#shipping-fee-input').val(0);
                    updateSummaryTotal(0);
                }
            });
        };
        // 👉 Khi người dùng đổi hãng vận chuyển, gọi lại hàm tính phí
        $(document).ready(function() {
            $('.carrier-select').on('change', function() {
                calculateShippingFee();
            });
        });



        // === HÀM LẤY GÓI DỊCH VỤ ===
        window.getAvailableServices = function(toDistrictId) {
            const fromDistrictId = 6084;
            $.ajax({
                url: "https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/available-services",
                method: "POST",
                headers: {
                    "Token": GHN_TOKEN
                },
                contentType: "application/json",
                data: JSON.stringify({
                    "shop_id": 179319,
                    "from_district": fromDistrictId,
                    "to_district": toDistrictId
                }),
                success: function(response) {
                    if (response.data && response.data.length > 0) {
                        const defaultService = response.data.find(s => s.service_type_id === 2);
                        serviceTypeId = defaultService ? defaultService.service_type_id : response
                            .data[0].service_type_id;
                    } else {
                        serviceTypeId = 2;
                    }
                },
                error: function() {
                    serviceTypeId = 2;
                },
                complete: function() {
                    calculateShippingFee();
                }
            });
        };

        // === COUPON SẢN PHẨM ===
        const validateCouponBtn = document.getElementById('validate-checkout-coupon-btn');
        if (validateCouponBtn) {
            validateCouponBtn.addEventListener('click', () => handlePasteAndValidateCheckout(true));
            const couponInput = document.getElementById('coupon_code');
            if (couponInput && couponInput.value) {
                setTimeout(() => handlePasteAndValidateCheckout(false), 100);
            }
        }

        async function handlePasteAndValidateCheckout(fromClick = true) {
            const couponInput = document.getElementById('coupon_code');
            const resultDiv = document.querySelector('.checkout-layout .coupon-result');
            resultDiv.textContent = '';
            resultDiv.className = 'coupon-result';

            let validatedCouponInput = document.getElementById('validated_coupon_code');
            if (!validatedCouponInput) {
                validatedCouponInput = document.createElement('input');
                validatedCouponInput.type = 'hidden';
                validatedCouponInput.id = 'validated_coupon_code';
                validatedCouponInput.name = 'validated_coupon_code';
                couponInput.form.appendChild(validatedCouponInput);
            }

            let code = couponInput.value.trim().toUpperCase();

            if (!code) {
                resultDiv.textContent = 'Vui lòng nhập mã giảm giá.';
                resultDiv.className = 'coupon-result error';
                updateCheckoutSummary(0);
                return;
            }

            try {
                const formData = new FormData();
                formData.append('code', code);
                const response = await fetch('validate_coupon.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    resultDiv.textContent = `Success: ${data.message}`;
                    resultDiv.className = 'coupon-result success';
                    validatedCouponInput.value = code;
                    updateCheckoutSummary(data.discount_percent);
                } else {
                    throw new Error(data.message || 'Mã không hợp lệ.');
                }
            } catch (err) {
                resultDiv.textContent = `Error: ${err.message}`;
                resultDiv.className = 'coupon-result error';
                validatedCouponInput.value = '';
                updateCheckoutSummary(0);
            }
        }

        // HÀM CẬP NHẬT TỔNG TIỀN (ĐÃ SỬA – KHÔNG GỌI updateSummaryTotal)
        function updateCheckoutSummary(discountPercent) {
            const subtotalEl = document.getElementById('summary-subtotal');
            const discountRowEl = document.getElementById('summary-discount-row');
            const discountAmountEl = document.getElementById('summary-discount-amount');
            const discountLabelEl = document.getElementById('summary-discount-label');
            const subtotalAfterDiscountRowEl = document.getElementById('summary-subtotal-after-discount-row');
            const subtotalAfterDiscountEl = document.getElementById('summary-subtotal-after-discount');
            const totalEl = document.getElementById('summary-total');

            if (!subtotalEl || !totalEl) return;

            const subtotal = parseFloat(subtotalEl.dataset.value) || 0;
            const discountAmount = (subtotal * discountPercent) / 100;
            const formatVND = (v) => Math.round(v).toLocaleString('vi-VN') + '₫';

            // HIỂN THỊ GIẢM GIÁ
            if (discountPercent > 0) {
                discountLabelEl.textContent = `Giảm giá (${discountPercent}%)`;
                discountAmountEl.textContent = `- ${formatVND(discountAmount)}`;
                discountAmountEl.dataset.vnd = discountAmount;
                subtotalAfterDiscountEl.textContent = formatVND(subtotal - discountAmount);
                discountRowEl.style.display = 'table-row';
                subtotalAfterDiscountRowEl.style.display = 'table-row';
                subtotalEl.style.textDecoration = 'line-through';
            }
            // ẨN + XÓA GIẢM GIÁ
            else {
                discountRowEl.style.display = 'none';
                subtotalAfterDiscountRowEl.style.display = 'none';
                subtotalEl.style.textDecoration = 'none';
                discountAmountEl.dataset.vnd = 0;
            }

            // CẬP NHẬT TỔNG (TỰ TÍNH, KHÔNG GỌI HÀM KHÁC)
            const shippingFee = parseFloat(document.getElementById('shipping-fee-input')?.value || '0');
            const total = subtotal - discountAmount + shippingFee;
            totalEl.textContent = formatVND(total);
        }

        // === JQUERY READY ===
        $(document).ready(function() {
            const sessionCoupon = <?= json_encode($sessionCoupon) ?>;
            if (sessionCoupon) {
                $('#coupon_code').val(sessionCoupon);
                $('#validated_coupon_code').val(sessionCoupon);
                handlePasteAndValidateCheckout(false);
            }
            // TỰ ĐỘNG ÁP DỤNG MÃ VẬN CHUYỂN
            const savedCouponCode = localStorage.getItem('shipping_coupon_code');
            const savedCouponData = localStorage.getItem('shipping_coupon_data');
            if (savedCouponCode && savedCouponData) {
                const coupon = JSON.parse(savedCouponData);
                document.getElementById('shipping_coupon_code').value = savedCouponCode;
                if (coupon.type === 'percent') {
                    window.shippingDiscountPercent = coupon.value;
                } else {
                    window.shippingDiscountAmount = coupon.value; // cố định
                }
            }

            // Load tỉnh
            $.ajax({
                url: "https://online-gateway.ghn.vn/shiip/public-api/master-data/province",
                method: "GET",
                headers: {
                    "Token": GHN_TOKEN
                },
                success: function(response) {
                    //$('#province').append('<option value="">-- Chọn tỉnh/thành --</option>');
                    $.each(response.data, function(index, item) {
                        $('#province').append('<option value="' + item.ProvinceID +
                            '">' + item.ProvinceName + '</option>');
                    });
                }
            });

            // Load quận
            $('#province').on('change', function() {
                const provinceId = parseInt($(this).val());
                selectedProvinceName = $("#province option:selected").text();
                $('#district').prop('disabled', false).html(
                    '<option value="">Đang tải...</option>');
                $('#ward').prop('disabled', true).html(
                    '<option value="">-- Chọn phường/xã --</option>');

                if (!provinceId) return;

                $.ajax({
                    url: "https://online-gateway.ghn.vn/shiip/public-api/master-data/district",
                    method: "POST",
                    headers: {
                        "Token": GHN_TOKEN
                    },
                    data: JSON.stringify({
                        province_id: provinceId
                    }),
                    contentType: "application/json",
                    success: function(response) {
                        $('#district').html(
                            '<option value="">-- Chọn quận/huyện --</option>');
                        $.each(response.data, function(index, item) {
                            $('#district').append('<option value="' + item
                                .DistrictID + '">' + item.DistrictName +
                                '</option>');
                        });
                    }
                });
            });

            // Load phường + tính phí
            $('#district').on('change', function() {
                selectedDistrictId = parseInt($(this).val());
                selectedDistrictName = $("#district option:selected").text();
                $('#ward').prop('disabled', false).html('<option value="">Đang tải...</option>');
                selectedWardCode = "";

                if (!selectedDistrictId) {
                    calculateShippingFee();
                    return;
                }

                getAvailableServices(selectedDistrictId);

                $.ajax({
                    url: "https://online-gateway.ghn.vn/shiip/public-api/master-data/ward",
                    method: "POST",
                    headers: {
                        "Token": GHN_TOKEN
                    },
                    data: JSON.stringify({
                        district_id: selectedDistrictId
                    }),
                    contentType: "application/json",
                    success: function(response) {
                        $('#ward').html(
                            '<option value="">-- Chọn phường/xã --</option>');
                        $.each(response.data, function(index, item) {
                            $('#ward').append('<option value="' + item
                                .WardCode + '">' + item.WardName +
                                '</option>');
                        });
                    }
                });
            });

            $('#ward').on('change', function() {
                selectedWardCode = $(this).val();
                selectedWardName = $("#ward option:selected").text();
                const detail = $('#address').val().split(',')[0].trim() || "Địa chỉ chi tiết";
                $('#address').val(
                    `${detail}, ${selectedWardName}, ${selectedDistrictName}, ${selectedProvinceName}`
                );
                calculateShippingFee();
            });

            async function handlePasteAndValidateCheckout(fromClick = true) {
                const couponInput = document.getElementById('coupon_code');
                const resultDiv = document.querySelector('.checkout-layout .coupon-result');
                resultDiv.textContent = '';
                resultDiv.className = 'coupon-result';

                let validatedCouponInput = document.getElementById('validated_coupon_code');
                if (!validatedCouponInput) {
                    validatedCouponInput = document.createElement('input');
                    validatedCouponInput.type = 'hidden';
                    validatedCouponInput.id = 'validated_coupon_code';
                    validatedCouponInput.name = 'validated_coupon_code';
                    couponInput.form.appendChild(validatedCouponInput);
                }

                let code = couponInput.value.trim().toUpperCase();

                if (!code) {
                    resultDiv.textContent = 'Vui lòng nhập mã giảm giá.';
                    resultDiv.className = 'coupon-result error';
                    validatedCouponInput.value = '';
                    updateCheckoutSummary(0);
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('code', code);
                    const response = await fetch('validate_coupon.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    // CHỈ CHẤP NHẬN KHI success: true + có coupon
                    if (data.success && data.coupon && data.coupon.code && data.coupon
                        .discount_percent > 0) {
                        resultDiv.textContent =
                            `Áp dụng thành công! Giảm ${data.coupon.discount_percent}%`;
                        resultDiv.className = 'coupon-result success';
                        validatedCouponInput.value = data.coupon.code; // Lưu mã chính xác từ DB
                        updateCheckoutSummary(data.coupon.discount_percent);
                    } else {
                        throw new Error(data.message || 'Mã không tồn tại hoặc không hợp lệ.');
                    }
                } catch (err) {
                    resultDiv.textContent = err.message;
                    resultDiv.className = 'coupon-result error';
                    validatedCouponInput.value = '';
                    updateCheckoutSummary(0);
                }
            }

            // GHTK & CARRIER
            $(document).on("change", ".carrier-select", function() {
                const carrier = $("input[name='carrier']:checked").val();
                $('#shipping-carrier-input').val(carrier);
                if (carrier === "GHTK") {
                    fetchGHTKFeeAndRender();
                } else {
                    calculateShippingFee();
                }
            });

            $("#province, #district, #ward, #address").on("change keyup", function() {
                const carrier = $("input[name='carrier']:checked").val();
                if (carrier === "GHTK") {
                    fetchGHTKFeeAndRender();
                } else {
                    calculateShippingFee();
                }
            });

            // Khởi tạo
            updateSummaryTotal(0);
        });
    });

    function fetchGHTKFeeAndRender() {
        const province = $("#province option:selected").text().trim();
        const district = $("#district option:selected").text().trim();
        const ward = $("#ward option:selected").text().trim();
        const address = $("#address").val().trim();

        if (!province || !district || !ward || !address) {
            $("#ghtk-fee-text").text("Chưa đủ thông tin");
            $("#ghtk-fee-detail").text("");
            $("#shipping-fee-input").val("");
            return;
        }

        $("#ghtk-fee-text").text("Đang tính...");
        $("#ghtk-fee-detail").text("");

        $.ajax({
            url: GHTK_PROXY,
            method: "GET",
            dataType: "json",
            data: {
                pick_province: PICK_PROVINCE,
                pick_district: PICK_DISTRICT,
                pick_ward: PICK_WARD,
                pick_address: PICK_ADDRESS,
                province: province,
                district: district,
                ward: ward,
                address: address,
                weight: 1000
            },
            success: function(res) {
                console.log("Phản hồi GHTK:", res);

                if (res && (res.success === true || res.code === 200)) {
                    let feeVND = null;

                    if (res.fee && typeof res.fee === 'object' && res.fee.fee !== undefined) {
                        feeVND = Number(res.fee.fee);
                    } else if (typeof res.fee === 'number') {
                        feeVND = Number(res.fee);
                    }

                    if (!feeVND || isNaN(feeVND)) {
                        $("#ghtk-fee-text").text("Không lấy được phí");
                        $("#shipping-fee-input").val("");
                        return;
                    }

                    const feeUSD = feeVND / 25000;

                    $("#ghtk-fee-text").html('<strong>' + feeVND.toLocaleString('vi-VN') + ' ₫</strong>');
                    $("#ghtk-fee-detail").text(
                        '(' + feeUSD.toFixed(2) + ' USD) - Dự kiến: ' + (res.delivery || res.fee
                            ?.delivery || '--')
                    );

                    updateSummaryTotal(feeVND);

                    $("#shipping-fee-input").val(feeVND);
                    $("#shipping-carrier-input").val("GHTK");

                    $("#shipping-fee-detail").text("GHTK - tính theo địa chỉ đã chọn");
                } else {
                    $("#ghtk-fee-text").text("Không lấy được phí");
                    $("#shipping-fee-input").val("");
                }


            },
            error: function(xhr, status, err) {
                console.error("Lỗi proxy GHTK:", err, xhr.responseText);
                $("#ghtk-fee-text").text("Lỗi API GHTK");
                $("#shipping-fee-input").val("");
            }
        });
    }


    $(document).on("change", ".carrier-select", function() {
        const selectedCarrier = $("input[name='carrier']:checked").val();

        if (selectedCarrier === "GHTK") {
            $("#ghtk-fee-container").show();
            fetchGHTKFeeAndRender();
            $("#shipping-carrier-input").val("GHTK");
        } else if (selectedCarrier === "ShoeShopShip" || selectedCarrier === "GHN") {
            $("#ghtk-fee-container").hide();
            $("#ghtk-fee-text").text("—");
            $("#ghtk-fee-detail").text("");
            $("#shipping-carrier-input").val(selectedCarrier);
            calculateShippingFee();
        }
    });


    $("#province, #district, #ward, #address").on("change keyup", function() {
        const selectedCarrier = $("input[name='carrier']:checked").val();
        if (selectedCarrier === "GHTK") {
            fetchGHTKFeeAndRender();
        } else if (selectedCarrier === "ShoeShopShip" || selectedCarrier === "GHN") {
            calculateShippingFee();
        }
    });

    document.getElementById("applyShippingCoupon").addEventListener("click", function() {
        const code = document.getElementById("shipping_coupon_code").value.trim().toUpperCase();
        const msgEl = document.getElementById("shippingCouponMessage");

        if (!code) {
            msgEl.textContent = "Vui lòng nhập mã vận chuyển.";
            msgEl.className = "text-danger";
            return;
        }

        msgEl.textContent = "Đang kiểm tra...";
        msgEl.className = "text-info";

        fetch("validate_shipping_coupon.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "code=" + encodeURIComponent(code)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    msgEl.textContent = data.message;
                    msgEl.className = "text-success";

                    // Lưu mã + thông tin giảm vào localStorage
                    localStorage.setItem("shipping_coupon_code", code);
                    localStorage.setItem("shipping_coupon_data", JSON.stringify(data.coupon));

                    // Cập nhật % hoặc số tiền giảm để JS tính lại phí
                    if (data.coupon.type === 'percent') {
                        window.shippingDiscountPercent = data.coupon.value;
                    } else {
                        window.shippingDiscountAmount = data.coupon.value; // cố định
                    }

                    // Tính lại phí vận chuyển ngay lập tức
                    calculateShippingFee();
                } else {
                    msgEl.textContent = data.message || "Mã không hợp lệ.";
                    msgEl.className = "text-danger";

                    // Xóa dữ liệu cũ
                    localStorage.removeItem("shipping_coupon_code");
                    localStorage.removeItem("shipping_coupon_data");
                    window.shippingDiscountPercent = 0;
                    window.shippingDiscountAmount = 0;

                    calculateShippingFee();
                }
            })
            .catch(err => {
                console.error(err);
                msgEl.textContent = "Lỗi kết nối. Vui lòng thử lại.";
                msgEl.className = "text-danger";
            });
    });
</script>

<!-- MODAL: ĐẶT HÀNG THÀNH CÔNG -->
<div class="modal fade" id="orderSuccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <h5 class="modal-title text-success">
                    <i class="fas fa-check-circle"></i> Đặt hàng thành công!
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="padding: 1.5rem 2rem;">
                <p class="mb-2">Cảm ơn bạn đã mua sắm tại <strong>ShoeShop</strong>!</p>
                <p class="text-muted mb-3">Mã đơn hàng của bạn là:</p>
                <h3 class="text-primary mb-4" id="modalOrderId">#000000</h3>
                <div class="d-grid gap-2 d-md-flex justify-content-center">
                    <a href="index.php" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-shopping-bag"></i> Tiếp tục mua sắm
                    </a>
                    <a href="#" id="viewOrderDetailBtn" class="btn btn-primary px-4">
                        <i class="fas fa-eye"></i> Xem chi tiết đơn hàng
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT HIỂN THỊ MODAL SAU KHI ĐẶT HÀNG -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lấy success message từ PHP
        const successMsg = <?php echo json_encode(flash_get('success')); ?>;
        const orderSuccess = <?php echo json_encode($_GET['order_success'] ?? null); ?>;

        // Nếu có order_success param hoặc success message, hiển thị modal
        if (orderSuccess || successMsg) {
            const orderId = orderSuccess;

            // Cập nhật nội dung modal
            const modalOrderEl = document.getElementById('modalOrderId');
            if (modalOrderEl && orderId) {
                modalOrderEl.textContent = '#' + String(orderId).padStart(6, '0');
            }

            // Cập nhật link xem chi tiết
            const viewBtn = document.getElementById('viewOrderDetailBtn');
            if (viewBtn && orderId) {
                viewBtn.href = 'order_details.php?id=' + encodeURIComponent(orderId);
            }

            // Hiển thị modal
            const modalEl = document.getElementById('orderSuccessModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }

            // Clear URL params
            try {
                history.replaceState({}, document.title, window.location.pathname);
            } catch (e) {}
        }
    });
</script>




<!-- CSS CHO MODAL -->
<style>
    #orderSuccessModal .modal-content {
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    #orderSuccessModal .modal-title i {
        margin-right: 8px;
    }

    #orderSuccessModal .btn {
        min-width: 180px;
        border-radius: 8px;
        font-weight: 500;
    }

    /* === 3 NÚT THANH TOÁN ĐỀU NHAU – CHỈNH CẢ 3 TRONG 1 NƠI === */
    .form-actions.payment-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 30px;
    }

    .btn-cod,
    .btn-vnpay,
    #paypal-button-container {
        align-items: center;
        justify-items: center;
        width: 750px !important;
        height: 55px !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12) !important;
    }

    #paypal-button-container {
        padding: 0 !important;
        margin: 0 !important;
        line-height: 0 !important;
    }

    /* === KẾT THÚC === */
</style>
<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- PayPal Script -->
<script>
    paypal.Buttons({
        style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'paypal',
            height: 55
            // Match height 55px
        },

        createOrder: function(data, actions) {
            // Validate form trước
            const address = document.getElementById('address').value.trim();
            const phone = document.getElementById('phone').value.trim();

            if (!address || !phone) {
                alert('Vui lòng điền đầy đủ địa chỉ và số điện thoại');
                return null;
            }

            // Lấy tổng tiền từ summary-total và chuyển USD
            const totalText = document.getElementById('summary-total').textContent;
            const totalVND = parseFloat(totalText.replace(/[^\d]/g, ''));
            const totalUSD = (totalVND / 26310).toFixed(2);

            return actions.order.create({
                purchase_units: [{
                    amount: {
                        currency_code: 'USD',
                        value: totalUSD
                    }
                }]
            });
        },

        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Lấy form data
                const formData = new FormData();

                // Thêm dữ liệu form
                formData.append('address', document.getElementById('address').value.trim());
                formData.append('phone', document.getElementById('phone').value.trim());
                formData.append('shipping_fee', document.getElementById('shipping-fee-input').value);
                formData.append('carrier', document.querySelector('input[name="carrier"]:checked')
                    .value);
                formData.append('payment_method', 'PAYPAL');
                formData.append('paypal_order_id', details.id);

                // Gửi request xử lý đơn hàng
                fetch('process_paypal.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            window.location.href = 'checkout.php?order_success=' + result.order_id;
                        } else {
                            throw new Error(result.message || 'Có lỗi xảy ra');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Lỗi: ' + error.message);
                    });
            });
        },

        onError: function(err) {
            console.error('PayPal Error:', err);
            alert('Có lỗi với PayPal, vui lòng thử lại sau');
        }
    }).render('#paypal-button-container');
</script>