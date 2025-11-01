<?php
/**
 * API endpoint để lấy danh sách size của sản phẩm
 * File: admin/api_get_sizes.php
 */

// Include các file cần thiết
require_once __DIR__ . '/../includes/functions.php';

// Chỉ cho phép Admin và Staff truy cập
require_admin_or_staff();

// Set header JSON và tắt output buffer
ob_clean(); // Xóa mọi output trước đó
header('Content-Type: application/json');

$product_id = (int)($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

try {
    $db = get_db();
    $stmt = $db->prepare("SELECT id, size, stock FROM product_sizes WHERE product_id = ? ORDER BY size");
    $stmt->execute([$product_id]);
    $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(['success' => true, 'sizes' => $sizes]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;