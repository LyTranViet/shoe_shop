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
    'TmnCode'   => '1NDITK1W',
    'HashSecret' => '3W1G21VMGLI30U99923AM5JF1A9RE7K1',
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

// === LẤY ĐỊA CHỈ TỪ PROFILE ===
$stmt = $db->prepare('
    SELECT a.*, c.ghn_province_id, c.ghn_district_id, c.ghn_ward_code 
    FROM addresses a 
    LEFT JOIN address_codes c ON a.id = c.address_id 
    WHERE a.user_id = ? 
    ORDER BY a.is_default DESC, a.created_at DESC
');
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

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
    $address_id = (int)$_POST['saved_address_id'] ?? 0;
    $phone = trim($_POST['phone'] ?? '');
    $payment = $_POST['payment_method'] ?? 'COD';
    $shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;
    $shipping_carrier = $_POST['shipping_carrier'] ?? 'GHN';
    $couponCode = trim($_POST['coupon_code'] ?? ''); // Lấy mã từ input người dùng nhập

    // === LẤY ĐỊA CHỈ ĐÃ CHỌN ===
    $addrStmt = $db->prepare('SELECT a.*, c.ghn_district_id, c.ghn_ward_code FROM addresses a LEFT JOIN address_codes c ON a.id = c.address_id WHERE a.id = ? AND a.user_id = ?');
    $addrStmt->execute([$address_id, $userId]);
    $selectedAddr = $addrStmt->fetch();

    if (!$selectedAddr || !$address_id) {
        flash_set('error', 'Vui lòng chọn địa chỉ giao hàng hợp lệ.');
        header('Location: checkout.php');
        exit;
    }

    $address = trim($selectedAddr['address'] . ', ' . $selectedAddr['ward'] . ', ' . $selectedAddr['district'] . ', ' . $selectedAddr['city']);
    $phone = $selectedAddr['phone'];

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

    // Xác thực coupon và tính toán giảm giá
    $coupon = validate_coupon($db, $couponCode);
    $discount = 0;
    if ($coupon) {
        $discount = ((int)$coupon['discount_percent']) * $subtotal / 100.0;
        $couponCode = $coupon['code']; // Đảm bảo mã đúng được lưu
    }

    // ----- Áp dụng mã giảm phí vận chuyển (sửa nhanh) -----
    $shipping_coupon_code = trim($_POST['validated_shipping_coupon_code'] ?? '');
    $shipping_discount = 0.0;
    $shipping_message = '';
    $shipping_success = false;

    // Prefer original_shipping_fee if client provided it (fee before coupon applied)
    $orig_shipping_fee = isset($_POST['original_shipping_fee']) ? (float)$_POST['original_shipping_fee'] : (isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0);
    $final_shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;

    if ($shipping_coupon_code !== '') {
        $stmt = $db->prepare(
            "SELECT * 
             FROM shipping_coupons
             WHERE UPPER(CODE) = UPPER(?)
               AND active = 1
               AND (expire_date IS NULL OR expire_date >= CURDATE())
             LIMIT 1"
        );
        $stmt->execute([$shipping_coupon_code]);
        $ship_coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ship_coupon) {
            $ship_type = strtolower($ship_coupon['TYPE']);
            $ship_value = (float)$ship_coupon['VALUE'];

            if ($ship_type === 'percent') {
                // percent of original fee
                $shipping_discount = ($orig_shipping_fee * $ship_value) / 100.0;
            } else {
                $shipping_discount = $ship_value;
            }

            // Do not exceed original fee and ensure non-negative
            $shipping_discount = max(0.0, min($shipping_discount, $orig_shipping_fee));
            // final shipping after discount
            $final_shipping_fee = max(0.0, $orig_shipping_fee - $shipping_discount);
        }
    }

    // Total must include final shipping fee (after shipping coupon) and product discount
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

            $ins = $db->prepare('INSERT INTO orders (user_id, total_amount, shipping_address, phone, status_id, coupon_id, payment_method, shipping_fee, shipping_carrier, discount_amount, coupon_code, shipping_discount_amount, shipping_coupon_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'); // Thêm coupon_code
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
    '); // Thêm coupon_code
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
            $coupon ? $coupon['code'] : null, // Lưu mã coupon nếu hợp lệ
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
                $db->prepare("INSERT INTO export_receipt_detail (export_id, batch_id, productsize_id, quantity, price) VALUES (?, ?, ?, ?, ?)")->execute([$export_id, $batch['id'], $productsize_id, $deduct_from_this_batch, (float)$item['price']]);

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

            <div class="form-group">
                <label for="saved_address">Chọn địa chỉ giao hàng <span class="text-danger">*</span></label>
                <select id="saved_address" name="saved_address_id" class="form-control" required>
                    <option value="">-- Chọn địa chỉ --</option>
                    <?php
                    $hasDefault = false;
                    foreach ($addresses as $addr):
                        $fullAddr = trim("{$addr['address']}, {$addr['ward']}, {$addr['district']}, {$addr['city']}");
                        $isDefault = ($addr['is_default'] == 1);
                        if ($isDefault) $hasDefault = true;
                    ?>
                        <option value="<?= $addr['id'] ?>" <?= $isDefault ? 'selected' : '' ?>
                            data-phone="<?= htmlspecialchars($addr['phone']) ?>"
                            data-district-id="<?= $addr['ghn_district_id'] ?>"
                            data-ward-code="<?= $addr['ghn_ward_code'] ?>"
                            data-province="<?= htmlspecialchars($addr['city']) ?>"
                            data-district="<?= htmlspecialchars($addr['district']) ?>"
                            data-ward="<?= htmlspecialchars($addr['ward']) ?>">
                            <?= htmlspecialchars($fullAddr) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ẩn các input cũ -->
            <input type="hidden" name="address" id="address" value="">
            <input type="hidden" name="phone" id="phone" value="">
            <input type="hidden" name="ghn_district_id" id="hidden_district_id" value="">
            <input type="hidden" name="ghn_ward_code" id="hidden_ward_code" value="">
            <!-- Original fee (before shipping coupon) - used server-side to compute discount reliably -->
            <input type="hidden" name="original_shipping_fee" id="original-shipping-fee-input" value="0">
            <input type="hidden" name="shipping_fee" id="shipping-fee-input" value="0">
            <input type="hidden" name="shipping_carrier" id="shipping-carrier-input" value="GHN">
            <div class="form-group">
                <label>Chọn dịch vụ giao hàng</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input carrier-select" type="radio" name="carrier" id="carrierGHN"
                        value="GHN" checked>
                    <label class="form-check-label" for="carrierGHN">Giao Hàng Nhanh (GHN)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input carrier-select" type="radio" name="carrier" id="carrierGHTK"
                        value="GHTK">
                    <label class="form-check-label" for="carrierGHTK">Giao hàng tiết kiệm (GHTK)</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input carrier-select" type="radio" name="carrier" id="carrierShoeShopShip"
                        value="ShoeShopShip">
                    <label class="form-check-label" for="carrierShoeShopShip">ShoeShopShip</label>
                </div>

                <!-- Hidden inputs để gửi form -->
            </div>
            <!-- SỐ ĐIỆN THOẠI - ĐỂ NGOÀI FORM ĐỂ TRÁNH BỊ RESET -->
            <div class="form-group">
                <label for="phone">Số điện thoại <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="phone-display" readonly>
                <input type="hidden" name="phone" id="phone" required>
            </div>

            <div class="form-group coupon-group">
                <label for="coupon_code">Mã giảm giá</label>
                <div class="input-with-button">
                    <input type="text" id="coupon_code" name="coupon_code" placeholder="Nhập mã giảm giá"
                        value="<?php echo htmlspecialchars($sessionCoupon); ?>">
                    <button type="button" id="applyCoupon" class="btn small">Áp dụng
                    </button>
                </div>
                <div class="coupon-result"></div>
            </div>
            <!-- Mã giảm phí vận chuyển -->
            <div class="form-group coupon-group">
                <label for="shipping_coupon_code">Mã giảm phí vận chuyển</label>
                <div class="input-with-button">
                    <input type="text" id="shipping_coupon_code" name="shipping_coupon_code"
                        placeholder="Nhập mã vận chuyển">
                    <button type="button" id="applyShippingCoupon" class="btn small">Áp dụng</button>
                </div>
                <div id="shippingCouponMessage" class="coupon-result"></div>
                <input type="hidden" name="validated_shipping_coupon_code" id="validated_shipping_coupon_code" value="">
            </div>

            <!-- New Payment Buttons -->
            <form id="checkout-form" method="post" action="process_checkout.php">
                <input type="hidden" name="coupon_code" id="form_coupon_code" value="">
                <input type="hidden" name="shipping_coupon_code" id="form_shipping_coupon_code" value="">

                <!-- ... phần chọn địa chỉ, sản phẩm, v.v. ... -->

                <div class="form-actions payment-buttons">
                    <button class="btn btn-cod" type="submit" name="payment_method" value="COD">Đặt hàng COD</button>
                    <button class="btn btn-vnpay" type="submit" name="payment_method" value="VNPAY">
                        <img src="assets/images/vnpay_logo.png" alt="VNPay Logo" class="vnpay-logo">
                        <span class="vnpay-text">
                            <span class="vnpay-vn">VN</span><span class="vnpay-pay">PAY</span>
                        </span>
                    </button>
                    <div id="paypal-button-container"></div>

                    <!-- 3 input cho PayPal (để lại, không xóa) -->
                    <input type="hidden" name="coupon_code" id="hidden_product_coupon" value="">
                    <input type="hidden" name="validated_shipping_coupon_code" id="hidden_shipping_coupon" value="">
                    <input type="hidden" name="original_shipping_fee" id="hidden_original_shipping_fee" value="">
                </div>
            </form>
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

<script>
    // === CÂN NẶNG MẶC ĐỊNH: 1KG = 1000g ===
    function getCartWeight() {
        return 1000;
    }
    // === CHỈ THÊM PHẦN JS MỚI CHO VẬN CHUYỂN ===
    $(document).on('change', '#saved_address', function() {
        const $option = $(this).find('option:selected');

        // CẬP NHẬT ĐỊA CHỈ
        let fullAddress = $option.text().replace(/ \(Mặc định\)$/, '');
        fullAddress = fullAddress.replace(/^>\s*\d+,\s*/, '');
        $('#address').val(fullAddress);

        // CẬP NHẬT SỐ ĐIỆN THOẠI
        const phone = $option.data('phone') || '';
        $('#phone-display').val(phone);
        $('#phone').val(phone);

        // CẬP NHẬT BIẾN TOÀN CỤC
        selectedDistrictId = $option.data('district-id') || null;
        selectedWardCode = $option.data('ward-code') || null;

        // GỌI TÍNH LẠI PHÍ
        calculateShippingFee();
    });
    // TỰ ĐỘNG CHẠY KHI LOAD TRANG
    $(document).ready(function() {
        if ($('#saved_address').val()) {
            $('#saved_address').trigger('change');
        }
    });


    $(document).on("change", "input[name='carrier']", calculateShippingFee);
    $(document).ready(function() {
        if ($('#saved_address').val()) $('#saved_address').trigger('change');
        // TỰ ĐỘNG ÁP DỤNG MÃ GIẢM GIÁ SẢN PHẨM
        const savedProductCoupon = localStorage.getItem('product_coupon_data');
        if (savedProductCoupon) {
            const coupon = JSON.parse(savedProductCoupon);
            const code = localStorage.getItem('product_coupon_code') || '';

            $('#coupon_code').val(code);
            $('#validated_coupon_code').val(coupon.code || '');

            // Cập nhật giao diện
            $('.coupon-result').text(`Áp dụng thành công! Giảm ${coupon.discount_percent}%`).addClass('success')
                .removeClass('error');

            updateCheckoutSummary(coupon.discount_percent);
        }
    });
    $(document).ready(function() {
        const savedProductCoupon = localStorage.getItem('product_coupon_data');
        if (savedProductCoupon) {
            const coupon = JSON.parse(savedProductCoupon);
            $('#coupon_code').val(localStorage.getItem('product_coupon_code') || '');
            $('#validated_coupon_code').val(coupon.code);
            updateCheckoutSummary(coupon.discount_percent);
        }

        // GỌI TÍNH PHÍ KHI ĐỔI ĐỊA CHỈ
        $(document).on('change', '#saved_address', function() {
            calculateShippingFee();
        });
    });
    // === CẬP NHẬT TỔNG TIỀN (CHỈ CẬP NHẬT GIAO DIỆN) ===
    window.updateSummaryTotal = function(shippingFeeVND = 0, originalFee = 0, discountInfo = '') {
        const subtotal = parseFloat($('#summary-subtotal').data('value')) || 0;
        let discount = 0;
        const savedCoupon = localStorage.getItem('product_coupon_data');
        if (savedCoupon) {
            const coupon = JSON.parse(savedCoupon);
            discount = (subtotal * (coupon.discount_percent || 0)) / 100;
        }

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
    // === TÍNH PHÍ VẬN CHUYỂN (CHỈ TÍNH, KHÔNG GỌI updateSummaryTotal) ===
    window.calculateShippingFeet = function() {
        if (!selectedDistrictId || !selectedWardCode) {
            updateSummaryTotal(0);
            $('#shipping-fee-text').html('<span style="color:red">Vui lòng chọn địa chỉ đầy đủ</span>');
            return;
        }

        $('#shipping-fee-text').html('Đang tính...');
        const selectedCarrier = $('input[name="carrier"]:checked').val() || 'GHN';
        $('#shipping-carrier-input').val(selectedCarrier);
        serviceTypeId = 2;
        $.ajax({
            url: "CalculateFee.php",
            method: "POST",
            dataType: "json",
            data: {
                districtId: selectedDistrictId,
                wardCode: selectedWardCode,
                serviceTypeId: serviceTypeId,
                carrier: selectedCarrier
            },
            success: function(response) {
                if (response && response.error === false) {
                    let feeVND = Number(response.fee);
                    if (isNaN(feeVND) || feeVND <= 0) {
                        updateSummaryTotal(0);
                        $('#shipping-fee-text').html('<span style="color:red">Không lấy được phí</span>');
                        return;
                    }

                    let finalFeeVND = feeVND;
                    let discountText = '';

                    // ÁP DỤNG MÃ GIẢM PHÍ
                    const couponData = localStorage.getItem('shipping_coupon_data');
                    if (couponData) {
                        const coupon = JSON.parse(couponData);
                        if (coupon.type === 'percent') {
                            const discount = (feeVND * coupon.value) / 100;
                            finalFeeVND = Math.max(0, feeVND - discount);
                            discountText = `Giảm ${coupon.value}% phí vận chuyển`;
                        } else if (coupon.type === 'fixed') {
                            finalFeeVND = Math.max(0, feeVND - coupon.value);
                            discountText = `Giảm ${coupon.value.toLocaleString()}₫ phí vận chuyển`;
                        }
                    }

                    updateSummaryTotal(finalFeeVND, feeVND, discountText);
                    $('#shipping-fee-detail').text(selectedCarrier + ' - tính theo địa chỉ đã chọn');
                } else {
                    updateSummaryTotal(0);
                    $('#shipping-fee-text').html('<span style="color:red">Lỗi tính phí</span>');
                }
            },
            error: function() {
                updateSummaryTotal(0);
                $('#shipping-fee-text').html('<span style="color:red">Lỗi mạng</span>');
            }
        });
    }; // 👉 Khi người dùng đổi hãng vận chuyển, gọi lại hàm tính phí
</script>
<script>
    // === TỰ ĐỘNG ĐIỀN MÃ GIẢM PHÍ VẬN CHUYỂN - CHỜ DOM SẴN SÀNG ===
    const applySavedShippingCoupon = async () => {
        const savedCode = localStorage.getItem('shipping_coupon_code');
        if (!savedCode) return;

        // Chờ input xuất hiện (tối đa 5s)
        let attempts = 0;
        const maxAttempts = 50; // 5 giây

        const tryFill = setInterval(() => {
            const codeInput = document.getElementById('shipping_coupon_code');
            const validatedInput = document.getElementById('validated_shipping_coupon_code');
            const msgEl = document.getElementById('shippingCouponMessage');

            if (codeInput && validatedInput && msgEl || attempts >= maxAttempts) {
                clearInterval(tryFill);

                if (attempts >= maxAttempts) {
                    console.warn('Không tìm thấy input mã giảm phí vận chuyển');
                    return;
                }

                // Điền vào form
                codeInput.value = savedCode;
                validatedInput.value = savedCode;

                // GỌI API ĐỂ XÁC NHẬN LẠI
                const formData = new FormData();
                formData.append('code', savedCode);

                fetch('validate_shipping_coupon.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            msgEl.textContent = data.message;
                            msgEl.className = 'text-success';
                            localStorage.setItem('shipping_coupon_data', JSON.stringify(data.coupon));
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(err => {
                        msgEl.textContent = 'Mã đã hết hạn';
                        msgEl.className = 'text-danger';
                        localStorage.removeItem('shipping_coupon_code');
                        localStorage.removeItem('shipping_coupon_data');
                        validatedInput.value = '';
                    })
                    .finally(() => {
                        // BẮT BUỘC GỌI TÍNH LẠI PHÍ
                        if (typeof calculateShippingFee === 'function') {
                            calculateShippingFee();
                        }
                    });
            }
            attempts++;
        }, 100); // Kiểm tra mỗi 100ms
    };

    // CHẠY SAU KHI TOÀN BỘ TRANG LOAD XONG
    window.addEventListener('load', applySavedShippingCoupon);
</script>
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
    // === CÂN NẶNG MẶC ĐỊNH ===
    function getCartWeight() {
        return 1000;
    }

    // === BIẾN TOÀN CỤC ===
    let selectedProvinceName = "";
    let selectedDistrictName = "";
    let selectedWardName = "";
    let selectedDistrictId = null;
    let selectedWardCode = null;
    let serviceTypeId = 2;
    const GHN_TOKEN = "658b57db-acf1-11f0-93b8-b675d1187f91";

    // === CẬP NHẬT PHÍ VẬN CHUYỂN (CHỈ GÁN, KHÔNG TÍNH LẠI TỔNG) ===
    function updateShippingFee(fee, text = '', carrier = 'GHN') {
        const display = document.getElementById('shipping-fee-text');
        const input = document.getElementById('shipping-fee-input');
        const carrierInput = document.getElementById('shipping-carrier-input');
        const originalFeeEl = document.getElementById('original-shipping-fee');
        const discountInfoEl = document.getElementById('shipping-discount-info');

        if (fee === null) {
            if (display) display.textContent = text || 'Đang tính...';
            if (display) display.style.color = '#666';
            return;
        }

        const formatted = new Intl.NumberFormat('vi-VN').format(fee) + '₫';
        // Save original fee (before applying any shipping coupon)
        const origInput = document.getElementById('original-shipping-fee-input');
        if (origInput) origInput.value = fee;
        input.value = fee;
        carrierInput.value = carrier;

        // === TÍNH GIẢM PHÍ VẬN CHUYỂN ===
        let shippingDiscount = 0;
        const savedCoupon = localStorage.getItem('shipping_coupon_data');
        if (savedCoupon) {
            const coupon = JSON.parse(savedCoupon);
            if (coupon.type === 'percent') {
                shippingDiscount = (fee * coupon.value) / 100;
            } else {
                shippingDiscount = Math.min(fee, coupon.value);
            }
        }

        const finalFee = Math.max(0, fee - shippingDiscount);
        input.value = finalFee; // GHI ĐÈ INPUT CUỐI CÙNG

        // === HIỂN THỊ ===
        if (carrier === 'GHTK') {
            document.getElementById('ghtk-fee-text').textContent = formatted;
            document.getElementById('ghtk-fee-row').style.display = 'table-row';
            document.getElementById('shipping-fee-row').style.display = 'none';
        } else {
            document.getElementById('shipping-fee-row').style.display = 'table-row';
            document.getElementById('ghtk-fee-row').style.display = 'none';

            if (shippingDiscount > 0) {
                originalFeeEl.textContent = new Intl.NumberFormat('vi-VN').format(fee) + '₫';
                originalFeeEl.style.display = 'inline';
                display.textContent = new Intl.NumberFormat('vi-VN').format(finalFee) + '₫';
                discountInfoEl.textContent = `Giảm ${shippingDiscount.toLocaleString()}₫ phí vận chuyển`;
                discountInfoEl.style.display = 'block';
            } else {
                display.textContent = formatted;
                originalFeeEl.style.display = 'none';
                discountInfoEl.style.display = 'none';
            }
        }

        // === CHỈ CẬP NHẬT TỔNG SAU KHI PHÍ ĐÃ XONG ===
        updateCheckoutSummary();
    }

    // === CẬP NHẬT TỔNG TIỀN (TỰ TÍNH, KHÔNG GỌI HÀM KHÁC) ===
    function updateCheckoutSummary() {
        const subtotal = parseFloat(document.getElementById('summary-subtotal')?.dataset.value) || 0;
        const shippingFee = parseFloat(document.getElementById('shipping-fee-input')?.value) || 0;

        // Giảm giá sản phẩm
        let productDiscount = 0;
        const savedProductCoupon = localStorage.getItem('product_coupon_data');
        if (savedProductCoupon) {
            const coupon = JSON.parse(savedProductCoupon);
            productDiscount = (subtotal * (coupon.discount_percent || 0)) / 100;
        }

        const total = subtotal - productDiscount + shippingFee;
        const totalEl = document.getElementById('summary-total');
        if (totalEl) {
            totalEl.textContent = new Intl.NumberFormat('vi-VN').format(total) + '₫';
        }

        // Cập nhật dòng giảm giá sản phẩm
        const discountRow = document.getElementById('summary-discount-row');
        const discountAmountEl = document.getElementById('summary-discount-amount');
        if (productDiscount > 0 && discountRow && discountAmountEl) {
            discountRow.style.display = 'table-row';
            discountAmountEl.textContent = `- ${new Intl.NumberFormat('vi-VN').format(productDiscount)}₫`;
        } else if (discountRow) {
            discountRow.style.display = 'none';
        }
    }

    // === TÍNH PHÍ VẬN CHUYỂN (CHUẨN VỚI TYPE = "shipping") ===
    async function calculateShippingFee() {
        console.log("🚀 Bắt đầu calculateShippingFee()");

        const addressSelect = document.getElementById('saved_address');
        const carrierChecked = document.querySelector('input[name="carrier"]:checked');

        if (!addressSelect || !addressSelect.value || !carrierChecked) {
            console.warn("⚠️ Thiếu địa chỉ hoặc hãng vận chuyển");
            updateShippingFee(0, 'Vui lòng chọn địa chỉ và hãng vận chuyển');
            return;
        }

        const opt = addressSelect.options[addressSelect.selectedIndex];
        const districtId = opt.dataset.districtId;
        const wardCode = opt.dataset.wardCode;
        const province = opt.dataset.province;
        const district = opt.dataset.district;
        const ward = opt.dataset.ward;
        const carrier = carrierChecked.value;
        const weight = getCartWeight();

        console.log("📦 Dữ liệu gửi đi:", {
            districtId,
            wardCode,
            carrier,
            weight
        });

        if (!districtId || !wardCode) {
            updateShippingFee(0, 'Địa chỉ chưa hỗ trợ', carrier);
            console.warn("🚫 Địa chỉ chưa có districtId hoặc wardCode");
            return;
        }

        let url = '',
            isPost = false;
        const data = new FormData();

        if (carrier === 'GHTK') {
            url = 'ghtk_fee.php?' + new URLSearchParams({
                pick_province: 'Hà Nội',
                pick_district: 'Quận Ba Đình',
                province,
                district,
                ward,
                weight,
                value: 200000
            });
        } else {
            url = 'CalculateFee.php';
            isPost = true;
            data.append('districtId', districtId);
            data.append('wardCode', wardCode);
            data.append('carrier', carrier);
            data.append('weight', weight);
            if (carrier === 'GHN') data.append('serviceTypeId', 2);
        }

        console.log("🌐 Gửi request tới:", url);
        updateShippingFee(null, 'Đang tính...', carrier);

        try {
            const res = await fetch(url, isPost ? {
                method: 'POST',
                body: data
            } : {
                method: 'GET'
            });
            const json = await res.json();
            console.log("📨 Phản hồi phí vận chuyển:", json);

            let shippingFee = json.fee || 0;

            // === ÁP DỤNG MÃ GIẢM PHÍ VẬN CHUYỂN (type = "shipping") ===
            try {
                const stored = localStorage.getItem('shipping_coupon');
                console.log("📦 Dữ liệu localStorage.shipping_coupon:", stored);

                if (stored) {
                    const coupon = JSON.parse(stored);
                    console.log("✅ Đã đọc mã giảm phí vận chuyển:", coupon);

                    const type = coupon.type;
                    const value = parseFloat(coupon.value) || 0;

                    if (type === 'shipping') {
                        // Giảm phần trăm phí vận chuyển
                        const discount = (shippingFee * value) / 100;
                        shippingFee = Math.max(0, shippingFee - discount);
                        console.log(`💸 Mã ${coupon.code}: Giảm ${value}% (${discount.toLocaleString()}₫)`);
                    } else {
                        console.warn("⚠️ Kiểu giảm không xác định:", type);
                    }

                    console.log(`📉 Phí sau giảm: ${shippingFee.toLocaleString()}₫`);
                } else {
                    console.log('❌ Không tìm thấy mã giảm phí vận chuyển trong localStorage.');
                }
            } catch (e) {
                console.error('💥 Lỗi khi xử lý mã giảm phí vận chuyển:', e);
            }

            updateShippingFee(shippingFee, '', carrier);
        } catch (err) {
            console.error('💥 Lỗi fetch:', err);
            updateShippingFee(0, 'Lỗi mạng', carrier);
        }

        updateCheckoutSummary();
        console.log("✅ Kết thúc calculateShippingFee()");
    }

    // === GỌI LẠI KHI THAY ĐỔI ===
    document.getElementById('saved_address')?.addEventListener('change', calculateShippingFee);
    document.querySelectorAll('input[name="carrier"]').forEach(r => r.addEventListener('change', calculateShippingFee));

    // === NÚT ÁP DỤNG MÃ GIẢM PHÍ VẬN CHUYỂN ===
    document.getElementById('applyShippingCoupon')?.addEventListener('click', async () => {
        const codeInput = document.getElementById('shipping_coupon_code');
        const validatedInput = document.getElementById('validated_shipping_coupon_code');
        const msgEl = document.getElementById('shippingCouponMessage');

        if (!codeInput || !validatedInput || !msgEl) return;

        const code = codeInput.value.trim().toUpperCase();
        if (!code) {
            msgEl.textContent = 'Vui lòng nhập mã';
            msgEl.className = 'text-danger';
            return;
        }

        msgEl.textContent = 'Đang kiểm tra...';
        msgEl.className = 'text-info';

        const formData = new FormData();
        formData.append('code', code);

        try {
            const res = await fetch('validate_shipping_coupon.php', {
                method: 'POST',
                body: formData
            });
            if (!res.ok) throw new Error('Lỗi mạng');

            const data = await res.json();
            console.log('validate_shipping_coupon response:', data); // <--- debug

            if (data.success) {
                const couponObj = {
                    code: data.coupon?.code,
                    type: data.coupon?.type,
                    value: data.coupon?.value,
                    message: data.message
                };



                localStorage.setItem('shipping_coupon', JSON.stringify(couponObj));
                validatedInput.value = code;
                msgEl.textContent = data.message;
                msgEl.className = 'text-success';

                if (typeof calculateShippingFee === 'function') {
                    calculateShippingFee();
                }
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            msgEl.textContent = err.message || 'Lỗi hệ thống';
            msgEl.className = 'text-danger';
            localStorage.removeItem('shipping_coupon');
            validatedInput.value = '';

            if (typeof calculateShippingFee === 'function') {
                calculateShippingFee();
            }
        }

    });

    // === TỰ ĐỘNG ÁP DỤNG MÃ KHI LOAD TRANG ===
    document.addEventListener('DOMContentLoaded', () => {
        const savedCode = localStorage.getItem('shipping_coupon_code');
        const savedData = localStorage.getItem('shipping_coupon_data');

        if (!savedCode || !savedData) return;

        const input = document.getElementById('shipping_coupon_code');
        const validatedInput = document.getElementById('validated_shipping_coupon_code');
        const msgEl = document.getElementById('shippingCouponMessage');

        if (input) input.value = savedCode;
        if (validatedInput) validatedInput.value = savedCode;

        // Gọi lại API để xác thực
        const formData = new FormData();
        formData.append('code', savedCode);

        fetch('validate_shipping_coupon.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    localStorage.setItem('shipping_coupon_data', JSON.stringify(data.coupon));
                    alert('Đã lưu mã vào localStorage: ' + localStorage.getItem('shipping_coupon_code'));
                    if (msgEl) {
                        msgEl.textContent = data.message;
                        msgEl.className = 'text-success';
                    }
                    if (typeof calculateShippingFee === 'function') calculateShippingFee();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(err => {
                console.warn('Mã giảm phí cũ không hợp lệ:', err.message);
                if (msgEl) {
                    msgEl.textContent = 'Mã giảm phí vận chuyển không hợp lệ. Vui lòng nhập lại.';
                    msgEl.className = 'text-danger';
                }
                localStorage.removeItem('shipping_coupon_code');
                localStorage.removeItem('shipping_coupon_data');
                if (validatedInput) validatedInput.value = '';
                if (typeof calculateShippingFee === 'function') {
                    calculateShippingFee();
                }
            });
    });

    // === TỰ ĐỘNG ÁP DỤNG MÃ GIẢM GIÁ SẢN PHẨM ===
    $(document).ready(function() {
        const saved = localStorage.getItem('product_coupon_data');
        if (saved) {
            const coupon = JSON.parse(saved);
            $('#coupon_code').val(localStorage.getItem('product_coupon_code') || '');
            $('.coupon-result').text(`Áp dụng thành công! Giảm ${coupon.discount_percent}%`).addClass('success');
            updateCheckoutSummary();
        }
    });

    // === ĐIỀN SỐ ĐIỆN THOẠI ===
    setTimeout(() => {
        if (typeof $ === 'undefined') return;
        const fill = () => {
            const phone = $('#saved_address option:selected').data('phone') || '';
            $('#phone-display').val(phone);
            $('#phone').val(phone);
        };
        $(document).off('change.phone').on('change.phone', '#saved_address', fill);
        if ($('#saved_address').val()) fill();
    }, 600);

    // === KHỞI TẠO: Đọc lại mã giảm phí vận chuyển từ localStorage ===
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const storedShippingCoupon = localStorage.getItem('shipping_coupon');
            if (storedShippingCoupon) {
                const coupon = JSON.parse(storedShippingCoupon);
                const input = document.getElementById('shipping_coupon_code');
                const validatedInput = document.getElementById('validated_shipping_coupon_code');
                const msgEl = document.getElementById('shippingCouponMessage');

                if (input && validatedInput && msgEl) {
                    input.value = coupon.code || '';
                    validatedInput.value = coupon.code || '';
                    msgEl.textContent = coupon.message || 'Đã áp dụng mã giảm phí vận chuyển';
                    msgEl.className = 'text-success';
                }

                if (typeof calculateShippingFee === 'function') {
                    calculateShippingFee();
                }
            }
        } catch (e) {
            console.warn('Lỗi đọc shipping_coupon từ localStorage', e);
        }
    });
</script>
<script>
    // === TỰ ĐỘNG ÁP DỤNG MÃ GIẢM GIÁ SẢN PHẨM + GỌI VALIDATE ===
    $(document).ready(function() {
        const savedProductCode = localStorage.getItem('product_coupon_code');
        const savedProductData = localStorage.getItem('product_coupon_data');

        if (savedProductCode && savedProductData) {
            const coupon = JSON.parse(savedProductData);
            const input = document.getElementById('coupon_code');
            const resultDiv = document.querySelector('.checkout-layout .coupon-result');

            if (input) input.value = savedProductCode;

            // GỌI LẠI VALIDATE ĐỂ ĐẢM BẢO HỢP LỆ
            const formData = new FormData();
            formData.append('code', savedProductCode);

            fetch('validate_coupon.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Cập nhật giao diện
                        if (resultDiv) {
                            resultDiv.textContent = `Áp dụng thành công! Giảm ${data.coupon.discount_percent}%`;
                            resultDiv.className = 'coupon-result success';
                        }
                        // Cập nhật localStorage (đảm bảo đồng bộ)
                        localStorage.setItem('product_coupon_data', JSON.stringify(data.coupon));
                        updateCheckoutSummary();
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(err => {
                    console.warn('Mã giảm giá cũ không hợp lệ:', err.message);
                    if (resultDiv) {
                        resultDiv.textContent = 'Mã giảm giá không hợp lệ. Vui lòng nhập lại.';
                        resultDiv.className = 'coupon-result error';
                    }
                    localStorage.removeItem('product_coupon_code');
                    localStorage.removeItem('product_coupon_data');
                    updateCheckoutSummary();
                });
        }
    });

    // === XỬ LÝ NÚT "APPLY" MÃ GIẢM GIÁ ===
    document.getElementById('applyCoupon')?.addEventListener('click', async function() {
        const codeInput = document.getElementById('coupon_code');
        const resultDiv = document.querySelector('.checkout-layout .coupon-result');
        if (!codeInput || !resultDiv) return;

        const code = codeInput.value.trim().toUpperCase();
        if (!code) {
            resultDiv.textContent = 'Vui lòng nhập mã giảm giá.';
            resultDiv.className = 'coupon-result error';
            return;
        }

        resultDiv.textContent = 'Đang kiểm tra...';
        resultDiv.className = 'coupon-result';

        try {
            const formData = new FormData();
            formData.append('code', code);
            const res = await fetch('validate_coupon.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                resultDiv.textContent = `Áp dụng thành công! Giảm ${data.coupon.discount_percent}%`;
                resultDiv.className = 'coupon-result success';

                localStorage.setItem('product_coupon_code', code);
                document.getElementById('form_coupon_code').value = code;
                document.getElementById('hidden_product_coupon').value = code;
                localStorage.setItem('product_coupon_data', JSON.stringify(data.coupon));

                updateCheckoutSummary();
            } else {
                throw new Error(data.message || 'Mã không hợp lệ');
            }
        } catch (err) {
            resultDiv.textContent = err.message;
            resultDiv.className = 'coupon-result error';
            localStorage.removeItem('product_coupon_code');
            localStorage.removeItem('product_coupon_data');
            updateCheckoutSummary();
        }
    });

    // === TỰ ĐỘNG ÁP DỤNG MÃ GIẢM PHÍ VẬN CHUYỂN TỪ localStorage ===
    document.addEventListener('DOMContentLoaded', async () => {
        const savedCode = localStorage.getItem('shipping_coupon_code');
        const savedData = localStorage.getItem('shipping_coupon_data');

        const codeInput = document.getElementById('shipping_coupon_code');
        const validatedInput = document.getElementById('validated_shipping_coupon_code');
        const msgEl = document.getElementById('shippingCouponMessage');

        if (!savedCode || !codeInput || !validatedInput || !msgEl) return;

        // Điền vào form
        codeInput.value = savedCode;
        validatedInput.value = savedCode;

        // Gọi API để xác nhận lại
        const formData = new FormData();
        formData.append('code', savedCode);

        try {
            const res = await fetch('validate_shipping_coupon.php', {
                method: 'POST',
                body: formData
            });
            if (!res.ok) throw new Error('Lỗi mạng');

            const data = await res.json();

            if (data.success) {
                localStorage.setItem('shipping_coupon_data', JSON.stringify(data.coupon));
                msgEl.textContent = data.message;
                msgEl.className = 'text-success';

                // BẮT BUỘC GỌI TÍNH LẠI PHÍ
                if (typeof calculateShippingFee === 'function') {
                    calculateShippingFee();
                }
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            console.warn('Mã giảm phí vận chuyển cũ không hợp lệ:', err.message);
            msgEl.textContent = 'Mã đã hết hạn. Vui lòng nhập lại.';
            msgEl.className = 'text-danger';

            localStorage.removeItem('shipping_coupon_code');
            localStorage.removeItem('shipping_coupon_data');
            validatedInput.value = '';

            if (typeof calculateShippingFee === 'function') {
                calculateShippingFee();
            }
        }
    });

    // === XÓA localStorage KHI TRANG CÓ order_success (F5 VẪN XÓA) ===
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('order_success')) {
            // XÓA NGAY LẬP TỨC
            localStorage.removeItem('product_coupon_code');
            localStorage.removeItem('product_coupon_data');
            localStorage.removeItem('shipping_coupon_code');
            localStorage.removeItem('shipping_coupon_data');
            console.log('[OK] Xóa mã giảm giá do có order_success');

            // Tự động ẩn modal (nếu có)
            setTimeout(() => {
                const modal = document.querySelector('#orderSuccessModal');
                if (modal && typeof bootstrap !== 'undefined') {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                }
            }, 3000);
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const hasShippingCoupon = !!localStorage.getItem('shipping_coupon') ||
                !!localStorage.getItem('shipping_coupon_code');

            console.log('🔎 Có mã giảm phí vận chuyển trong localStorage không?', hasShippingCoupon);

            if (hasShippingCoupon) {
                const data =
                    JSON.parse(localStorage.getItem('shipping_coupon') || localStorage.getItem(
                        'shipping_coupon_data'));
                console.log('📦 Dữ liệu mã giảm phí vận chuyển:', data);
            } else {
                console.log('⚠️ Không tìm thấy mã giảm phí vận chuyển trong localStorage.');
            }
        } catch (e) {
            console.error('🚫 Lỗi truy cập localStorage:', e);
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        try {
            localStorage.setItem('test_local', 'ok');
            const val = localStorage.getItem('test_local');
            console.log('✅ localStorage test:', val);
        } catch (e) {
            console.error('🚫 localStorage bị chặn:', e);
        }
    });
    // === KHỞI TẠO LẠI MÃ GIẢM PHÍ VẬN CHUYỂN KHI LOAD TRANG ===
    document.addEventListener('DOMContentLoaded', () => {
        const savedShippingCode = localStorage.getItem('shipping_coupon_code');
        const savedShippingData = localStorage.getItem('shipping_coupon_data');
        const codeInput = document.getElementById('shipping_coupon_code');
        const validatedInput = document.getElementById('validated_shipping_coupon_code');
        const msgEl = document.getElementById('shippingCouponMessage');

        if (savedShippingCode && savedShippingData && codeInput && msgEl) {
            codeInput.value = savedShippingCode;
            if (validatedInput) validatedInput.value = savedShippingCode;
            msgEl.textContent = 'Áp dụng lại mã: ' + savedShippingCode;
            msgEl.className = 'text-success';

            // Gọi lại tính phí sau khi load
            if (typeof calculateShippingFee === 'function') {
                calculateShippingFee();
            }
        }
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

    /* CSS để input và button nằm chung 1 hàng (giống product.php) */
    .input-with-button {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }

    .input-with-button input[type="text"] {
        flex: 1;
        min-width: 0;
    }

    .input-with-button .btn {
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* Responsive cho màn hình nhỏ */
    @media (max-width: 576px) {
        .input-with-button {
            flex-direction: column;
        }

        .input-with-button .btn {
            width: 100%;
        }
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
                const formData = new FormData();
                formData.append('address', document.getElementById('address').value.trim());
                formData.append('phone', document.getElementById('phone').value.trim());
                formData.append('shipping_fee', document.getElementById('shipping-fee-input').value);
                formData.append('carrier', document.querySelector('input[name="carrier"]:checked')
                    .value);
                formData.append('payment_method', 'PAYPAL');
                formData.append('paypal_order_id', details.id);

                // === GỬI COUPON ===
                formData.append('coupon_code', document.getElementById('hidden_product_coupon')
                    ?.value || '');
                formData.append('validated_shipping_coupon_code', document.getElementById(
                    'hidden_shipping_coupon')?.value || '');
                formData.append('original_shipping_fee', document.getElementById(
                    'hidden_original_shipping_fee')?.value || '0');

                return fetch('process_paypal.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // === KIỂM TRA HTTP STATUS TRƯỚC KHI PARSE JSON ===
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(
                                    `Server error: ${response.status} - ${text.substring(0, 200)}`
                                );
                            });
                        }
                        return response.json();
                    })
                    .then(result => {
                        if (result.success) {
                            // XÓA localStorage TRƯỚC KHI CHUYỂN TRANG
                            localStorage.removeItem('product_coupon_code');
                            localStorage.removeItem('product_coupon_data');
                            localStorage.removeItem('shipping_coupon_code');
                            localStorage.removeItem('shipping_coupon_data');

                            window.location.href = 'checkout.php?order_success=' + result.order_id;
                        } else {
                            throw new Error(result.message || 'Thanh toán thất bại');
                        }
                    })
                    .catch(error => {
                        console.error('PayPal Process Error:', error);
                        alert('Lỗi thanh toán: ' + error.message);
                    });
            });
        },

        onError: function(err) {
            console.error('PayPal Error:', err);
            alert('Có lỗi với PayPal, vui lòng thử lại sau');
        }
    }).render('#paypal-button-container');
</script>
<script>
    // === TỰ ĐỘNG ĐIỀN SỐ ĐIỆN THOẠI - CHẠY SAU TẤT CẢ JS ===
    setTimeout(() => {
        if (typeof $ === 'undefined') return;

        const fillPhone = () => {
            const phone = $('#saved_address option:selected').data('phone') || '';
            $('#phone-display').val(phone);
            $('#phone').val(phone); // Gửi form
            console.log('[OK] Phone filled:', phone);
        };

        $(document).off('change.phone').on('change.phone', '#saved_address', fillPhone);


    }, 600);

    document.addEventListener('DOMContentLoaded', () => {
        const code = localStorage.getItem('shipping_coupon_code');
        const input = document.getElementById('shipping_coupon_code');
        const validated = document.getElementById('validated_shipping_coupon_code');

        if (code && input && validated) {
            input.value = code;
            validated.value = code;
            // Gọi calculateShippingFee() nếu có
            if (typeof calculateShippingFee === 'function') calculateShippingFee();
        }
    });
</script>
<script>
    // === TỰ ĐỘNG ĐIỀN COUPON CHO PAYPAL (product + shipping) ===
    document.addEventListener('DOMContentLoaded', () => {
        // Product coupon
        const productCode = localStorage.getItem('product_coupon_code') || '';
        if (productCode && document.getElementById('hidden_product_coupon')) {
            document.getElementById('hidden_product_coupon').value = productCode;
        }

        // Shipping coupon
        const shippingCode = localStorage.getItem('shipping_coupon_code') || '';
        if (shippingCode && document.getElementById('hidden_shipping_coupon')) {
            document.getElementById('hidden_shipping_coupon').value = shippingCode;
        }

        // Original shipping fee
        const origFeeElement = document.querySelector('#original-shipping-fee') || document.querySelector(
            '#shipping-fee-input');
        if (origFeeElement && document.getElementById('hidden_original_shipping_fee')) {
            const fee = origFeeElement.value || origFeeElement.textContent.replace(/[^\d]/g, '');
            document.getElementById('hidden_original_shipping_fee').value = fee;
        }
    });
</script>
<script>
    // === TỰ ĐỘNG ĐIỀN ĐỊA CHỈ ĐẦY ĐỦ KHI CHỌN SAVED ADDRESS ===
    $(document).ready(function() {
        const fillAddressAndPhone = () => {
            const selected = $('#saved_address option:selected');
            if (!selected.val()) return;

            const parts = [
                selected.data('address'),
                selected.data('ward'),
                selected.data('district'),
                selected.data('city')
            ].filter(Boolean).join(', ');

            const phone = selected.data('phone') || '';

            $('#address').val(parts); // ← Quan trọng: PayPal cần
            $('#phone').val(phone); // ← Gửi form
            $('#phone-display').val(phone); // ← Hiển thị

            console.log('[OK] Address filled:', parts);
        };

        // Khi thay đổi địa chỉ
        $(document).off('change.address').on('change.address', '#saved_address', fillAddressAndPhone);

        // Tự động điền khi load trang (nếu có default)
        if ($('#saved_address').val()) {
            fillAddressAndPhone();
        }
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>