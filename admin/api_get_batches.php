<?php
/**
 * API endpoint để lấy danh sách lô hàng theo size
 * File: admin/api_get_batches.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

ob_clean();
header('Content-Type: application/json');

$size_id = (int)($_GET['size_id'] ?? 0);

if ($size_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid size ID.']);
    exit;
}

try {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT id, batch_code, quantity_remaining 
        FROM product_batch 
        WHERE productsize_id = ? AND quantity_remaining > 0
        ORDER BY import_date ASC
    ");
    $stmt->execute([$size_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(['success' => true, 'batches' => $batches]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;