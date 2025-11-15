<?php
header('Content-Type: application/json');
$token = '658b57db-acf1-11f0-93b8-b675d1187f91'; // Replace with your GHN token

$district_id = $_GET['district_id'] ?? '';

if (empty($district_id)) {
    echo json_encode([]);
    exit;
}

$url = 'https://online-gateway.ghn.vn/shiip/public-api/master-data/ward';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Token: ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['district_id' => (int)$district_id]));

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
echo json_encode($data['data'] ?? []);
?>