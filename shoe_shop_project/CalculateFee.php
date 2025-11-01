<?php
header('Content-Type: application/json; charset=utf-8');

// ✅ Nhận dữ liệu gửi từ AJAX
$districtId     = $_POST['districtId'] ?? null;
$wardCode       = $_POST['wardCode'] ?? null;
$serviceTypeId  = $_POST['serviceTypeId'] ?? null;
$carrier        = $_POST['carrier'] ?? 'GHN'; // <-- Lấy hãng vận chuyển được chọn

// 🧩 Kiểm tra dữ liệu đầu vào
if (!$districtId || !$wardCode) {
    echo json_encode(['error' => true, 'message' => 'Thiếu thông tin địa chỉ']);
    exit;
}

// 🧮 Hàm tính phí mẫu cho từng hãng (bạn có thể thay bằng gọi API thật)
function getGHNFee($districtId, $wardCode, $serviceTypeId) {
    // Gọi API GHN thật ở đây nếu bạn có
    // Ví dụ tạm thời:
    return ['error' => false, 'fee' => 25000];
}

function getGHTKFee($districtId, $wardCode) {
    // Gọi API GHTK thật ở đây nếu có
    // Ví dụ tạm:
    return ['error' => false, 'fee' => 30000];
}

function getShoeShopShipFee($districtId, $wardCode) {
    // Ship nội bộ (phí cố định)
    return ['error' => false, 'fee' => 15000];
}

// ⚙️ Xử lý theo nhà vận chuyển
$response = ['error' => true, 'fee' => 0];

switch ($carrier) {
    case 'GHN':
        $response = getGHNFee($districtId, $wardCode, $serviceTypeId);
        break;
    case 'GHTK':
        $response = getGHTKFee($districtId, $wardCode);
        break;
    case 'ShoeShopShip':
        $response = getShoeShopShipFee($districtId, $wardCode);
        break;
    default:
        $response = ['error' => true, 'message' => 'Nhà vận chuyển không hợp lệ'];
        break;
}

// 🚀 Trả kết quả JSON về cho frontend
echo json_encode($response);
exit;
?>
