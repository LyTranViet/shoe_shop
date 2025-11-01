<?php
// ghtk_fee.php — proxy trung gian gọi GHTK API

header('Content-Type: application/json; charset=utf-8');

// ✅ Kiểm tra tham số bắt buộc
$required = ['pick_province', 'pick_district', 'province', 'district', 'weight'];
foreach ($required as $param) {
    if (empty($_GET[$param])) {
        echo json_encode(['success' => false, 'message' => "Thiếu tham số: $param"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ✅ Token thật từ dashboard.ghtk.vn
$token = '2YPCGIKgF8y9CorhgyTC7ctrIuNZlmp4H2qq7AY';

// ✅ API URL GHTK
$url = 'https://services.giaohangtietkiem.vn/services/shipment/fee';

// ✅ Dữ liệu gửi đi
$data = [
    'pick_province'  => $_GET['pick_province'],
    'pick_district'  => $_GET['pick_district'],
    'pick_ward'      => $_GET['pick_ward'] ?? '',
    'pick_address'   => $_GET['pick_address'] ?? '',
    'province'       => $_GET['province'],
    'district'       => $_GET['district'],
    'ward'           => $_GET['ward'] ?? '',
    'address'        => $_GET['address'] ?? '',
    'weight'         => (int)($_GET['weight'] ?? 1000),
    'value'          => 200000, // Giá trị đơn hàng (VNĐ)
    'transport'      => 'road',
    'deliver_option' => 'none'
];

// ✅ Gọi API bằng JSON body
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Token: ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => 'Lỗi CURL: ' . curl_error($ch)], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}
curl_close($ch);

// ✅ Chuẩn hóa phản hồi để JS dễ đọc
$result = json_decode($response, true);

if (isset($result['fee']['fee'])) {
    echo json_encode([
        'success' => true,
        'fee' => $result['fee']['fee'],
        'delivery' => $result['fee']['delivery_type'] ?? '1-2 ngày',
        'raw' => $result
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'Không lấy được phí GHTK',
        'raw' => $result
    ], JSON_UNESCAPED_UNICODE);
}
?>
