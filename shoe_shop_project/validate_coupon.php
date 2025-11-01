<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$code = trim($_POST['code'] ?? '');

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã coupon.']);
    exit;
}

try {
    $db = get_db();
    $st = $db->prepare('SELECT * FROM coupons WHERE code = ? LIMIT 1');
    $st->execute([$code]);
    $coupon = $st->fetch();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không hợp lệ.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    if (($coupon['valid_from'] && $now < $coupon['valid_from']) || ($coupon['valid_to'] && $now > $coupon['valid_to'])) {
        echo json_encode(['success' => false, 'message' => 'Coupon không hợp lệ tại thời điểm này.']);
        exit;
    }

    echo json_encode(['success' => true, 'discount_percent' => (int)$coupon['discount_percent'], 'message' => 'Áp dụng mã thành công!']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}