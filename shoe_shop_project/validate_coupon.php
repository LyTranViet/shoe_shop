<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$code = trim($_POST['code'] ?? '');

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã coupon.']);
    exit;
}

try {
    $db = get_db();
    $st = $db->prepare('SELECT * FROM coupons WHERE UPPER(code) = UPPER(?) LIMIT 1');
    $st->execute([$code]);
    $coupon = $st->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm giá không hợp lệ.']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    if (($coupon['valid_from'] && $now < $coupon['valid_from']) || ($coupon['valid_to'] && $now > $coupon['valid_to'])) {
        echo json_encode(['success' => false, 'message' => 'Coupon không hợp lệ tại thời điểm này.']);
        exit;
    }

    // FIX: Sử dụng floatval để lấy số từ cột discount_percent một cách an toàn.
    // Điều này sẽ xử lý đúng cả trường hợp giá trị là số (30), chuỗi số ('30'), 
    // hoặc chuỗi có ký tự ('30%').
    $discount_percent = floatval($coupon['discount_percent']);

    echo json_encode([
        'success' => true,
        'message' => "Áp dụng thành công! Giảm {$discount_percent}%",
        'coupon' => [  // <-- THÊM ĐỐI TƯỢNG COUPON
            'code' => $coupon['code'],
            'discount_percent' => $discount_percent
        ]
    ]);

} catch (Exception $e) {
    error_log("Lỗi validate_coupon: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
?>