<?php
header('Content-Type: application/json');
$token = '658b57db-acf1-11f0-93b8-b675d1187f91'; // Replace with your GHN token

$province_id = $_GET['province_id'] ?? '';

if (empty($province_id)) {
    echo json_encode([]);
    exit;
}

$url = 'https://online-gateway.ghn.vn/shiip/public-api/master-data/district?province_id=' . urlencode($province_id);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Token: ' . $token
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
echo json_encode($data['data'] ?? []);
?>