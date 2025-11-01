<?php
header('Content-Type: application/json; charset=utf-8');

// DÙNG functions.php (đã có get_db())
require_once __DIR__ . '/includes/functions.php';

try {
    $db = get_db();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối CSDL']);
    exit;
}

$code = strtoupper(trim($_POST['code'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã']);
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT CODE, TYPE, VALUE 
        FROM shipping_coupons 
        WHERE UPPER(CODE) = ? 
          AND active = 1 
          AND (expire_date IS NULL OR expire_date >= CURDATE())
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã không hợp lệ hoặc hết hạn']);
        exit;
    }

    $type = strtolower($coupon['TYPE']);
    $value = (float)$coupon['VALUE'];

    $message = $type === 'percent' 
        ? "Áp dụng thành công! Giảm {$value}% phí vận chuyển"
        : "Áp dụng thành công! Giảm " . number_format($value) . "₫ phí vận chuyển";

    echo json_encode([
        'success' => true,
        'message' => $message,
        'coupon' => [
            'code' => $coupon['CODE'],
            'type' => $type,
            'value' => $value
        ]
    ]);

} catch (Throwable $e) {
    error_log("Lỗi validate_shipping_coupon: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}