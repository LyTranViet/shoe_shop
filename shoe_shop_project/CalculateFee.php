<?php
header('Content-Type: application/json; charset=utf-8');

// Lấy thông tin hãng vận chuyển được gửi lên
$carrier = $_POST['carrier'] ?? ''; 
// Bỏ qua việc lấy và kiểm tra các thông tin khác (districtId, wardCode, weight)

// --- LOGIC: Phân biệt phí dựa trên Hãng vận chuyển, không cần địa chỉ ---

if ($carrier === 'ShoeShopShip') {
    // Nếu chọn ShoeShopShip, trả về 45000 đ
    echo json_encode(['success' => true, 'fee' => 45000]);
} else {
    // Mặc định cho GHN hoặc bất kỳ hãng nào khác (bao gồm cả trường hợp chưa chọn hãng), trả về 35000 đ
    // (Đây là logic thay thế cho trường hợp 'GHN' và mặc định)
    echo json_encode(['success' => true, 'fee' => 35000]);
}

?>