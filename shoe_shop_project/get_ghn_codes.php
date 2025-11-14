<?php
// get_ghn_codes.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$address_id = $_GET['address_id'] ?? null;
if (!$address_id) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT ghn_district_id, ghn_ward_code FROM address_codes WHERE address_id = ?");
$stmt->execute([$address_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'success' => true,
        'ghn_district_id' => $row['ghn_district_id'],
        'ghn_ward_code'   => str_pad($row['ghn_ward_code'], 5, '0', STR_PAD_LEFT)
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>