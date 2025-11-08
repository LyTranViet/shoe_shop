<?php
header('Content-Type: application/json');
session_start();
require_once 'includes/functions.php';
if (!is_logged_in()) die(json_encode(['success' => false, 'msg' => 'Login required']));

$db = get_db();
$userId = current_user_id();

// === COPY 99% LOGIC TỪ checkout.php ===
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$shipping_fee = (float)($_POST['shipping_fee'] ?? 0);
$shipping_carrier = $_POST['shipping_carrier'] ?? 'GHN';
$couponCode = trim($_POST['validated_coupon_code'] ?? '');
$shipping_coupon_code = trim($_POST['shipping_coupon_code'] ?? '');

if ($address === '') die(json_encode(['success' => false, 'msg' => 'Nhập địa chỉ']));

list($items, $subtotal, $cartId) = get_cart_items_and_total($db);
if (empty($items)) die(json_encode(['success' => false, 'msg' => 'Giỏ hàng rỗng']));

// Validate stock + coupon + shipping discount (giống checkout.php)
foreach ($items as $it) {
    if ($it['size']) {
        $st = $db->prepare('SELECT stock FROM product_sizes WHERE product_id=? AND size=?');
        $st->execute([$it['product_id'], $it['size']]);
        if ($st->fetchColumn() < $it['quantity']) die(json_encode(['success' => false, 'msg' => 'Hết hàng']));
    }
}
$coupon = validate_coupon($db, $couponCode);
$discount = $coupon ? $coupon['discount_percent'] * $subtotal / 100 : 0;

$shipping_discount = 0;
if ($shipping_coupon_code) {
    $sc = $db->prepare("SELECT * FROM shipping_coupons WHERE UPPER(code)=UPPER(?) AND active=1 LIMIT 1");
    $sc->execute([$shipping_coupon_code]);
    $ship = $sc->fetch();
    if ($ship) {
        $val = (float)$ship['VALUE'];
        $shipping_discount = ($ship['TYPE'] == 'percent') ? $shipping_fee * $val / 100 : $val;
        $shipping_discount = min($shipping_discount, $shipping_fee);
    }
}

$total = $subtotal + $shipping_fee - $discount;

// === TẠO ORDER PENDING ===
try {
    $db->beginTransaction();
    $ins = $db->prepare("INSERT INTO orders 
        (user_id,total_amount,shipping_address,status_id,payment_method,
         shipping_fee,shipping_carrier,discount_amount,coupon_code,
         shipping_discount_amount,shipping_coupon_code)
        VALUES (?,?,?,1,'VNPay',?,?,?,?,?,?)");
    $ins->execute([
        $userId,
        $total,
        $address,
        $shipping_fee,
        $shipping_carrier,
        $discount,
        $couponCode,
        $shipping_discount,
        $shipping_coupon_code
    ]);
    $orderId = $db->lastInsertId();

    foreach ($items as $it) {
        $db->prepare("INSERT INTO order_items (order_id,product_id,size,quantity,price)
                      VALUES (?,?,?,?,?)")
            ->execute([$orderId, $it['product_id'], $it['size'], $it['quantity'], $it['price']]);
    }

    // Tạo phiếu xuất kho tự động (copy từ checkout.php)
    $exportCode = 'PX-ORD' . $orderId;
    $db->prepare("INSERT INTO export_receipt (receipt_code,export_type,status,employee_id,total_amount,note,order_id)
                  VALUES (?,'Bán hàng','Đang xử lý',?,?,?,?)")
        ->execute([$exportCode, $userId, $total, "Tự động cho đơn #$orderId", $orderId]);
    $export_id = $db->lastInsertId();

    foreach ($items as $item) {
        $ps = $db->prepare("SELECT id FROM product_sizes WHERE product_id=? AND size=?");
        $ps->execute([$item['product_id'], $item['size']]);
        $psid = $ps->fetchColumn();
        if (!$psid) continue;
        $batches = $db->prepare("SELECT id,quantity_remaining FROM product_batch 
                                 WHERE productsize_id=? AND quantity_remaining>0 
                                 ORDER BY import_date ASC");
        $batches->execute([$psid]);
        $left = $item['quantity'];
        while ($left > 0 && $b = $batches->fetch()) {
            $take = min($left, $b['quantity_remaining']);
            $db->prepare("INSERT INTO export_receipt_detail 
                          (export_id,productsize_id,batch_id,quantity,export_price) 
                          VALUES (?,?,?,?,?)")
                ->execute([$export_id, $psid, $b['id'], $take, $item['price']]);
            $db->prepare("UPDATE product_batch SET quantity_remaining=quantity_remaining-? WHERE id=?")
                ->execute([$take, $b['id']]);
            $db->prepare("UPDATE product_sizes SET stock=stock-? WHERE id=?")
                ->execute([$take, $psid]);
            $left -= $take;
        }
    }

    // Xóa giỏ hàng
    if ($cartId) $db->prepare("DELETE FROM cart_items WHERE cart_id=?")->execute([$cartId]);

    $db->commit();
    echo json_encode(['success' => true, 'order_id' => $orderId, 'total_amount' => $total]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
}
