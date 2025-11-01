<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$code = trim($_POST['code'] ?? '');
// Phí vận chuyển hiện tại (tiền VND) được gửi từ frontend
// Tên biến trong AJAX frontend của bạn là 'fee', nên dùng $_POST['fee']
$currentFee = isset($_POST['fee']) ? (float)$_POST['fee'] : 0.0; 

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá vận chuyển.']);
    exit;
}

if ($currentFee <= 0) {
    // Nếu phí vận chuyển hiện tại là 0, không cần áp dụng giảm giá
    echo json_encode(['success' => false, 'message' => 'Phí vận chuyển hiện tại là 0₫, không cần áp dụng.']);
    exit;
}

try {
    $db = get_db();
    
    // Tìm coupon, chỉ chấp nhận loại 'shipping'
    $st = $db->prepare('SELECT * FROM coupons WHERE code = ? AND discount_type = ? LIMIT 1');
    $st->execute([$code, 'shipping']); // Chỉ lấy coupon có discount_type là 'shipping'
    $coupon = $st->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Mã giảm phí vận chuyển không hợp lệ hoặc không áp dụng cho vận chuyển.']);
        exit;
    }

    // 1. Kiểm tra thời hạn
    $now = date('Y-m-d H:i:s');
    if (($coupon['valid_from'] && $now < $coupon['valid_from']) || ($coupon['valid_to'] && $now > $coupon['valid_to'])) {
        echo json_encode(['success' => false, 'message' => 'Coupon đã hết hạn hoặc chưa có hiệu lực.']);
        exit;
    }

    // 2. Lô-gic tính toán giảm giá (Dựa trên discount_percent)
    $percent = (int)$coupon['discount_percent'];
    $calculatedDiscount = $currentFee * ($percent / 100.0);
    
    // Giảm giá không được vượt quá phí vận chuyển hiện tại
    $discountAmount = min($currentFee, $calculatedDiscount); 
    $discountInfo = "Giảm $percent% phí vận chuyển";

    if ($discountAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã này không áp dụng giảm giá cho phí vận chuyển của bạn.']);
        exit;
    }

    $newFee = $currentFee - $discountAmount;
    
    // 3. Trả về kết quả thành công
    echo json_encode([
        'success' => true, 
        'discount_amount' => round($discountAmount), // Số tiền giảm (VND)
        'new_fee' => round($newFee),               // Phí mới sau khi giảm
        'discount_info' => $discountInfo,          // Thông tin hiển thị
        'message' => "Áp dụng mã thành công: $discountInfo"
    ]);

} catch (Exception $e) {
    error_log("Shipping discount error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống khi áp dụng coupon.']);
}
?>