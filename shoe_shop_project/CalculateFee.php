<?php
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

$GHN_TOKEN = "658b57db-acf1-11f0-93b8-b675d1187f91";

// Lấy dữ liệu POST
$district_id = $_POST['districtId'] ?? null;
$ward_code   = $_POST['wardCode'] ?? null;

if (!$district_id || !$ward_code) {
    echo json_encode(["error" => true, "message" => "Missing params"]);
    exit;
}

// Dữ liệu cố định của người gửi (quận 7 - Tân Hưng)
$from_district = 6084; // Quận 7
$from_ward = "550307"; // phường Tân Hưng

// Body gửi lên GHN
$data = [
    "shop_id" => 179319, // có thể bỏ nếu không cần
    "from_district_id" => $from_district,
    "from_ward_code"   => $from_ward,
    "service_type_id"  => 2, // chuẩn COD
    "to_district_id"   => intval($district_id),
    "to_ward_code"     => $ward_code,
    "weight"           => 500, // gram
    "length"           => 20,
    "width"            => 15,
    "height"           => 10
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Token: " . $GHN_TOKEN
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($curl);
curl_close($curl);

$res = json_decode($response, true);
$fee = $res['data']['total'] ?? 0;

echo json_encode([
    "error" => false,
    "fee"   => $fee
]);
