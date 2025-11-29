<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

error_log('PayPal Process - Start');

if (!is_logged_in()) {
    die(json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']));
}

$db = get_db();
$userId = current_user_id();

// --- Đảm bảo cột paypal_order_id tồn tại ---
try {
    $colStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'orders' 
          AND COLUMN_NAME = 'paypal_order_id'
    ");
    $colStmt->execute();
    if ((int)$colStmt->fetchColumn() === 0) {
        $db->exec("ALTER TABLE `orders` ADD COLUMN `paypal_order_id` VARCHAR(255) NULL");
        error_log('process_paypal.php: Added orders.paypal_order_id column');
    }
} catch (Exception $ex) {
    error_log('process_paypal.php: Could not ensure paypal_order_id column: ' . $ex->getMessage());
}

// --- Helper lấy giỏ hàng ---
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
        $items = $itSt->fetchAll(PDO::FETCH_ASSOC);
        $total = 0;
        foreach ($items as $it) $total += ((float)$it['price']) * ((int)$it['quantity']);
        return [$items, $total, $cartId];
    }
    $items = [];
    $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $row = $p->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $items[] = ['cart_item_id' => $k, 'product_id' => $it['product_id'], 'size' => $it['size'] ?? null, 'quantity' => $it['quantity'], 'price' => $row['price'], 'name' => $row['name']];
        $total += ((float)$row['price']) * ((int)$it['quantity']);
    }
    return [$items, $total, null];
}

try {
    $db->beginTransaction();

    // === LẤY DỮ LIỆU TỪ FORM ===
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $shipping_fee = (float)($_POST['shipping_fee'] ?? 0);
    $shipping_carrier = $_POST['carrier'] ?? ($_POST['shipping_carrier'] ?? 'GHN');
    $paypal_order_id = $_POST['paypal_order_id'] ?? '';

    // === LẤY GIỎ HÀNG ===
    list($items, $subtotal, $cartId) = get_cart_items_and_total($db);
    if (empty($items)) {
        throw new Exception('Giỏ hàng trống');
    }

    // === TÍNH GIẢM GIÁ SẢN PHẨM ===
    $couponCode = trim($_POST['coupon_code'] ?? '');
    $discount = 0;
    $couponId = null;

    if ($couponCode) {
        $st = $db->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
        $st->execute([$couponCode]);
        $coupon = $st->fetch(PDO::FETCH_ASSOC);
        if ($coupon) {
            $now = date('Y-m-d H:i:s');
            if (($coupon['valid_from'] && $now < $coupon['valid_from']) || ($coupon['valid_to'] && $now > $coupon['valid_to'])) {
                $coupon = null;
            } else {
                $discount = round($subtotal * $coupon['discount_percent'] / 100, 2);
                $couponId = $coupon['id'];
            }
        }
    }

    // === TÍNH GIẢM PHÍ SHIP ===
    $shipping_coupon_code = trim($_POST['validated_shipping_coupon_code'] ?? '');
    $original_shipping_fee = (float)($_POST['original_shipping_fee'] ?? $shipping_fee);
    $shipping_discount = 0;
    $final_shipping_fee = $shipping_fee;

    if ($shipping_coupon_code) {
        $st = $db->prepare('SELECT * FROM shipping_coupons WHERE UPPER(code) = UPPER(?) AND active = 1 AND (expire_date IS NULL OR expire_date >= CURDATE()) LIMIT 1');
        $st->execute([$shipping_coupon_code]);
        $sc = $st->fetch(PDO::FETCH_ASSOC);
        if ($sc) {
            $val = (float)$sc['VALUE'];
            if (strtolower($sc['TYPE']) === 'percent') {
                $shipping_discount = round($original_shipping_fee * $val / 100, 2);
            } else {
                $shipping_discount = $val;
            }
            $shipping_discount = min($shipping_discount, $original_shipping_fee);
            $final_shipping_fee = $original_shipping_fee - $shipping_discount;
        }
    }

    // === TỔNG CUỐI CÙNG (ĐÚNG NHƯ PAYPAL HIỆN) ===
    $total_amount = $subtotal + $final_shipping_fee - $discount;

    // === TẠO ĐƠN HÀNG VỚI ĐẦY ĐỦ GIẢM GIÁ ===
    $ins = $db->prepare('INSERT INTO orders (
        user_id, total_amount, shipping_address, phone,
        status_id, payment_method, shipping_fee, shipping_carrier,
        paypal_order_id, paid_at,
        discount_amount, coupon_id, coupon_code,
        shipping_discount_amount, shipping_coupon_code
    ) VALUES (?,?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?)');

    $ins->execute([
        $userId,
        $total_amount,
        $address,
        $phone,
        1,
        'PAYPAL',
        $final_shipping_fee,
        $shipping_carrier,
        $paypal_order_id,
        $discount,
        $couponId,
        $couponCode,
        $shipping_discount,
        $shipping_coupon_code
    ]);

    $orderId = $db->lastInsertId();

    // === THÊM CHI TIẾT ĐƠN HÀNG ===
    $itemStmt = $db->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
    foreach ($items as $item) {
        $itemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['size'] ?? null,
            $item['quantity'],
            $item['price']
        ]);
    }

    // === XÓA GIỎ HÀNG ===
    if ($cartId) {
        $db->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
    }
    unset($_SESSION['cart']);

    // === TỰ ĐỘNG TẠO PHIẾU XUẤT KHO ===
    $exportCode = 'PX-ORD' . $orderId;
    $exportNote = 'Tự động tạo cho đơn hàng PayPal #' . $orderId;
    $exportStmt = $db->prepare("INSERT INTO export_receipt (receipt_code, export_type, status, employee_id, total_amount, note, order_id) VALUES (?, 'Bán hàng', 'Đang xử lý', ?, ?, ?, ?)");
    $exportStmt->execute([$exportCode, $userId, $total_amount, $exportNote, $orderId]);
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

            $db->prepare("INSERT INTO export_receipt_detail (export_id, batch_id, productsize_id, quantity, price) VALUES (?, ?, ?, ?, ?)")
                ->execute([$export_id, $batch['id'], $productsize_id, $deduct_from_this_batch, (float)$item['price']]);
            $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining - ? WHERE id = ?")
                ->execute([$deduct_from_this_batch, $batch['id']]);
            $db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ?")
                ->execute([$deduct_from_this_batch, $productsize_id]);
            $quantity_left_to_deduct -= $deduct_from_this_batch;
        }
    }

    // === XÓA MÃ GIẢM GIÁ SAU KHI ĐẶT HÀNG ===


    $_SESSION['order_success'] = $orderId;
    $db->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Đặt hàng thành công'
    ]);
} catch (Exception $e) {
    $db->rollBack();
    error_log('PayPal Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi xử electors: ' . $e->getMessage()
    ]);
}
