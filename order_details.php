<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// require login
if (!is_logged_in()) {
    flash_set('info', 'Please login to view your order.');
    header('Location: login.php');
    exit;
}

$db = get_db();
$userId = current_user_id();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: order_history.php');
    exit;
}

// Fetch order details, ensuring it belongs to the current user
$stmt = $db->prepare("
    SELECT o.*, os.name as status_name
    FROM orders o
    JOIN order_status os ON o.status_id = os.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    flash_set('error', 'Order not found.');
    header('Location: order_history.php');
    exit;
}

// Fetch order items
$items_stmt = $db->prepare("
    SELECT oi.*, p.name as product_name, 
           (SELECT url FROM product_images WHERE product_id = oi.product_id AND is_main = 1 LIMIT 1) as product_image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt->execute([$orderId]);
$order_items = $items_stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="order-details-page">
    <div class="order-details-header">
        <h2>Order Details #<?php echo $order['id']; ?></h2>
        <a href="order_history.php" class="btn secondary">‹ Back to History</a>
    </div>

    <div class="order-details-grid">
        <div class="panel">
            <h3>Order Information</h3>
            <div class="info-grid">
                <strong>Order Date:</strong><span><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
                <strong>Status:</strong><span><span class="status <?php echo strtolower(str_replace(' ', '-', $order['status_name'])); ?>"><?php echo htmlspecialchars($order['status_name']); ?></span></span>
                <strong>Payment Method:</strong><span><?php echo htmlspecialchars($order['payment_method']); ?></span>
                <strong>Total Amount:</strong><span><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></span>
            </div>
        </div>
        <div class="panel">
            <h3>Shipping Address</h3>
            <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
        </div>
    </div>

    <div class="panel order-items-panel">
        <h3>Items in this Order</h3>
        <div class="order-items-list">
            <?php foreach ($order_items as $item): ?>
                <div class="order-item-row">
                    <img src="<?php echo htmlspecialchars($item['product_image'] ?? 'assets/images/product-placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                    <div class="order-item-details">
                        <a href="product.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['product_name']); ?></a>
                        <span>Qty: <?php echo $item['quantity']; ?> | Size: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="order-item-price">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>