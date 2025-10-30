<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$results = [];

if (strlen($q) > 1) { // Chỉ tìm kiếm khi có hơn 1 ký tự
    try {
        $db = get_db();
        // Tìm kiếm toàn diện hơn
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.price, pi.url as image_url
            FROM products p
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
            WHERE p.name LIKE ? OR p.description LIKE ?
            ORDER BY p.name
            LIMIT 7
        ");
        $like = "%$q%";
        $stmt->execute([$like, $like]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Thêm URL sản phẩm và ảnh mặc định
        foreach ($results as &$row) {
            $row['url'] = 'product.php?id=' . $row['id'];
            $row['image_url'] = $row['image_url'] ?? 'assets/images/product-placeholder.png';
        }

    } catch (Exception $e) {
        // Bỏ qua lỗi và trả về mảng rỗng
    }
}

echo json_encode($results);