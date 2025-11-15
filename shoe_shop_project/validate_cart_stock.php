<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$db = get_db();
$errors = [];
$cart_items = [];

if (is_logged_in()) {
    $uid = current_user_id();
    $stmt = $db->prepare('
        SELECT ci.id, ci.product_id, ci.size, ci.quantity, p.name 
        FROM cart_items ci 
        JOIN carts c ON c.id = ci.cart_id 
        JOIN products p ON p.id = ci.product_id
        WHERE c.user_id = ?
    ');
    $stmt->execute([$uid]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    foreach ($_SESSION['cart'] ?? [] as $key => $item) {
        $stmt = $db->prepare('SELECT name FROM products WHERE id = ?');
        $stmt->execute([$item['product_id']]);
        $name = $stmt->fetchColumn();
        if ($name) {
            $cart_items[] = [
                'session_key' => $key, // Sử dụng session_key để định danh
                'product_id' => $item['product_id'],
                'size' => $item['size'],
                'quantity' => $item['quantity'],
                'name' => $name
            ];
        }
    }
}

foreach ($cart_items as $item) {
    $stmt = $db->prepare('SELECT stock FROM product_sizes WHERE product_id = ? AND size = ?');
    $stmt->execute([$item['product_id'], $item['size']]);
    $stock = $stmt->fetchColumn();

    if ($stock === false || (int)$item['quantity'] > (int)$stock) {
        $errors[] = [
            'id' => $item['id'] ?? null,
            'session_key' => $item['session_key'] ?? null,
            'name' => $item['name'],
            'size' => $item['size'],
            'available_stock' => (int)$stock
        ];
    }
}

if (empty($errors)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'errors' => $errors]);
}

exit;
?>