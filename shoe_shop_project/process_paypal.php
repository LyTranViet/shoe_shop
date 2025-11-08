<?php
header('Content-Type: application/json'); // Thêm header này để đảm bảo JSON response
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// Log để debug
error_log('PayPal Process - Start');

if (!is_logged_in()) {
    die(json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']));
}

$db = get_db();
$userId = current_user_id();

// --- ADD: ensure paypal_order_id column exists to avoid SQL errors ---
try {
    $colStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'orders' 
          AND COLUMN_NAME = 'paypal_order_id'
    ");
    $colStmt->execute();
    if ((int)$colStmt->fetchColumn() === 0) {
        // safe: add column (nullable)
        $db->exec("ALTER TABLE `orders` ADD COLUMN `paypal_order_id` VARCHAR(255) NULL");
        error_log('process_paypal.php: Added orders.paypal_order_id column');
    }
} catch (Exception $ex) {
    // nếu ALTER thất bại thì log nhưng không dừng toàn bộ xử lý - lỗi sẽ được trả về JSON sau
    error_log('process_paypal.php: Could not ensure paypal_order_id column: ' . $ex->getMessage());
}
// --- end add ---

// --- Bổ sung: helper get_cart_items_and_total (bản sao từ checkout.php) ---
function get_cart_items_and_total($db)
{
    if (is_logged_in()) {
        $uid = current_user_id();
        $st = $db->prepare('SELECT c.id AS cart_id FROM carts c WHERE c.user_id = ? LIMIT 1');
        $st->execute([$uid]);
        $cartId = $st->fetchColumn();
        if (!$cartId) return [[], 0, 0];
        $itSt = $db->prepare('SELECT ci.id AS cart_item_id, ci.product_id, ci.size, ci.quantity, ci.price, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $itSt->execute([$cartId]);
        $items = $itSt->fetchAll(PDO::FETCH_ASSOC);
        $total = 0;
        foreach ($items as $it) $total += ((float)$it['price']) * ((int)$it['quantity']);
        return [$items, $total, $cartId];
    }
    // guests fallback (shouldn't happen if checkout requires login)
    $items = [];
    $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $row = $p->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $items[] = ['cart_item_id' => $k, 'product_id' => $it['product_id'], 'size' => $it['size'] ?? null, 'quantity' => $it['quantity'], 'price' => $row['price'], 'name' => $row['name']];
        $total += ((float)$row['price']) * ((int)$it['quantity']);
    }
    return [$items, $total, null];
}
// --- end helper ---

try {
    $db->beginTransaction();

    // Debug POST data
    error_log('POST data: ' . print_r($_POST, true));

    // Lấy thông tin shipping từ form
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 0.0;
    $shipping_carrier = $_POST['carrier'] ?? ($_POST['shipping_carrier'] ?? 'GHN');
    $paypal_order_id = $_POST['paypal_order_id'] ?? '';

    // Lấy giỏ hàng
    list($items, $subtotal, $cartId) = get_cart_items_and_total($db);
    if (empty($items)) {
        throw new Exception('Giỏ hàng trống');
    }

    // Tính tổng tiền
    $total_amount = $subtotal + $shipping_fee;

    // Tạo đơn hàng
    $ins = $db->prepare('INSERT INTO orders (
        user_id, total_amount, shipping_address, phone,
        status_id, payment_method, shipping_fee,
        shipping_carrier, paypal_order_id, paid_at
    ) VALUES (?,?,?,?,?,?,?,?,?,NOW())');

    $ins->execute([
        $userId,
        $total_amount,
        $address,
        $phone,
        1,
        'PAYPAL',
        $shipping_fee,
        $shipping_carrier,
        $paypal_order_id
    ]);

    $orderId = $db->lastInsertId();

    // Thêm chi tiết đơn hàng
    $itemStmt = $db->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
    foreach ($items as $item) {
        $itemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['size'] ?? null,
            $item['quantity'],
            $item['price']
        ]);
    }

    // Xóa giỏ hàng
    if ($cartId) {
        $db->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
    }
    unset($_SESSION['cart']);

    $_SESSION['order_success'] = $orderId;
    $_SESSION['success'] = 'Đặt hàng thành công!';

    $db->commit();

    // Response JSON
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Đặt hàng thành công'
    ]);
} catch (Exception $e) {
    $db->rollBack();
    error_log('PayPal Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi xử lý đơn hàng: ' . $e->getMessage()
    ]);
}
