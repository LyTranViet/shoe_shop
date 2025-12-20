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

<div class="order-history-page">
    <h2>Lịch sử đơn hàng</h2>
</div>

<?php if (empty($orders)): ?>
<p>You have not placed any orders yet.</p>
<?php else: ?>
<div class="order-history-list">
    <?php foreach ($orders as $order): ?>
    <div class="order-card">
        <div class="order-card-header">
            <div>
                <strong>Order #<?php echo $order['id']; ?></strong>
                | <?php echo date('d M Y', strtotime($order['created_at'])); ?>
            </div>
            <div><span
                    class="status <?php echo strtolower(str_replace(' ', '-', $order['status_name'])); ?>"><?php echo htmlspecialchars($order['status_name']); ?></span>
            </div>
        </div>
        <div class="order-card-body">
            <?php foreach ($items_by_order[$order['id']] ?? [] as $item): ?>
            <div class="order-item-row">
                <img src="<?php echo htmlspecialchars($item['product_image'] ?? 'assets/images/product-placeholder.png'); ?>"
                    alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                <div class="order-item-details">
                    <a
                        href="product.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['product_name']); ?></a>
                    <span>Qty: <?php echo $item['quantity']; ?></span>
                </div>
                <div class="order-item-price"><?php echo number_format($item['price'] * $item['quantity'], 0); ?>₫</div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="order-card-footer">
            <div>
                <strong>Total: <?php echo number_format($order['total_amount'], 0); ?>₫</strong>
            </div>
            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn small">View Details</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.order-history-page h2 {
    text-align: center;
    margin-bottom: 30px;
    color: var(--text-dark);
    font-weight: 700;
}

.order-history-list {
    max-width: 900px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.order-card {
    background: var(--bg-white);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s;
}

.order-card:hover {
    box-shadow: var(--shadow-md);
}

.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: var(--bg-light);
    border-bottom: 1px solid var(--border);
    border-radius: 12px 12px 0 0;
}

.order-card-header strong {
    color: var(--text-dark);
}

.txid {
    margin-left: 8px;
    font-size: 0.9em;
    color: var(--text-muted);
}

.status {
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85em;
    text-transform: uppercase;
}

.status.pending,
.status.cho-xu-ly {
    background-color: rgba(255, 193, 7, 0.15);
    color: #b58500;
}

.status.processing,
.status.dang-giao {
    background-color: rgba(23, 162, 184, 0.15);
    color: var(--info);
}

.status.shipped,
.status.delivered,
.status.da-giao-hang,
.status.hoan-thanh {
    background-color: rgba(40, 167, 69, 0.15);
    color: var(--success);
}

.status.cancelled,
.status.da-huy {
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger);
}

.order-card-body {
    padding: 15px 20px;
}

.order-item-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-light);
}

.order-item-row:last-child {
    border-bottom: none;
}

.order-item-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
}

.order-item-details {
    flex-grow: 1;
}

.order-item-details a {
    color: var(--text-dark);
    font-weight: 600;
    text-decoration: none;
}

.order-item-details a:hover {
    color: var(--primary);
}

.order-item-details span {
    color: var(--text-muted);
    font-size: 0.9em;
}

.order-item-price {
    font-weight: 600;
    color: var(--text-body);
}

.order-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: var(--bg-light);
    border-top: 1px solid var(--border);
    border-radius: 0 0 12px 12px;
}

.order-card-footer strong {
    font-size: 1.1rem;
    color: var(--primary);
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>