<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// require login
if (!is_logged_in()) {
    flash_set('info', 'Please login to view your order history.');
    header('Location: login.php');
    exit;
}

$db = get_db();
$userId = current_user_id();

// Fetch orders for the current user
$orders = [];
try {
    $stmt = $db->prepare("
        SELECT o.*, os.name as status_name
        FROM orders o
        JOIN order_status os ON o.status_id = os.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    // For each order, fetch its items and their images
    if (!empty($orders)) {
        $order_ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

        $items_stmt = $db->prepare("
            SELECT oi.*, p.name as product_name, 
                   (SELECT url FROM product_images WHERE product_id = oi.product_id AND is_main = 1 LIMIT 1) as product_image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id IN ($placeholders)
        ");
        $items_stmt->execute($order_ids);
        $all_items = $items_stmt->fetchAll();

        // Group items by order_id
        $items_by_order = [];
        foreach ($all_items as $item) {
            $items_by_order[$item['order_id']][] = $item;
        }
    }
} catch (Exception $e) {
    // Handle DB error
    flash_set('error', 'Could not retrieve order history.');
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>My Order History</h2>

<?php if (empty($orders)): ?>
    <p>You have not placed any orders yet.</p>
<?php else: ?>
    <div class="order-history-list">
        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-card-header">
                    <div><strong>Order #<?php echo $order['id']; ?></strong> | <?php echo date('d M Y', strtotime($order['created_at'])); ?></div>
                    <div><span class="status <?php echo strtolower(str_replace(' ', '-', $order['status_name'])); ?>"><?php echo htmlspecialchars($order['status_name']); ?></span></div>
                </div>
                <div class="order-card-body">
                    <?php foreach ($items_by_order[$order['id']] ?? [] as $item): ?>
                        <div class="order-item-row">
                            <img src="<?php echo htmlspecialchars($item['product_image'] ?? 'assets/images/product-placeholder.png'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                            <div class="order-item-details">
                                <a href="product.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['product_name']); ?></a>
                                <span>Qty: <?php echo $item['quantity']; ?></span>
                            </div>
                            <div class="order-item-price">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="order-card-footer">
                    <div>
                        <strong>Total: $<?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn small">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>