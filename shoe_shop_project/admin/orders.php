<?php
// This file is included by admin/index.php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Logic cập nhật trạng thái đã được chuyển lên index.php
// Tuy nhiên, chúng ta vẫn cần xử lý flash messages ở đây
if ($msg = flash_get('success')) {
    echo "<div class='alert alert-success'>$msg</div>";
}
if ($msg = flash_get('error')) {
    echo "<div class='alert alert-error'>$msg</div>";
}

if ($action === 'view' && $id > 0) {
    // ... code hiện tại của bạn để xem chi tiết ...
}


if ($action === 'view' && $id > 0) {
	$stmt = $db->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone, os.name as status_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_status os ON o.status_id = os.id
        WHERE o.id = ?
    ");
	$stmt->execute([$id]);
	$order = $stmt->fetch();

	if (!$order) {
		echo "<h2>Order not found</h2>";
	} else {
		$items_stmt = $db->prepare("
            SELECT oi.*, p.name as product_name, p.code as product_code
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
		$items_stmt->execute([$id]);
		$order_items = $items_stmt->fetchAll();

		$statuses = $db->query("SELECT * FROM order_status")->fetchAll();
		include __DIR__ . '/order_view.php';
	}
} else {
	$search_query = trim($_GET['q'] ?? '');
	$itemsPerPage = 10;
	$currentPage = (int)($_GET['p'] ?? 1);

	$where_clause = '';
	$params = [];
	if (!empty($search_query)) {
		$where_clause = "WHERE o.id = :search_id OR u.name LIKE :search_name";
		$params[':search_id'] = $search_query;
		$params[':search_name'] = "%$search_query%";
	}

	$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_clause");
	$countStmt->execute($params);
	$totalItems = $countStmt->fetchColumn();
	$totalPages = ceil($totalItems / $itemsPerPage);
	$offset = ($currentPage - 1) * $itemsPerPage;

	$params[':limit'] = $itemsPerPage;
	$params[':offset'] = $offset;

	$stmt = $db->prepare("
		SELECT o.*, u.name as customer_name, os.name as status_name 
		FROM orders o 
		LEFT JOIN users u ON o.user_id = u.id 
		LEFT JOIN order_status os ON o.status_id = os.id 
		$where_clause 
		ORDER BY o.created_at DESC 
		LIMIT :limit OFFSET :offset
	");
	foreach ($params as $key => &$value)
		$stmt->bindParam($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
	$stmt->execute();
	$orders = $stmt->fetchAll();
?>
<div class="admin-container">
	<header class="admin-header">
		<h2><i class="fi fi-rr-shopping-cart"></i> Quản lý đơn hàng</h2>
		<div class="admin-tools">
			<form method="get" class="search-form" action="index.php">
				<input type="hidden" name="page" value="orders">
				<input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>" placeholder="Tìm theo ID hoặc tên khách...">
				<button type="submit">Tìm</button>
			</form>
		</div>
	</header>

	<div class="table-wrapper">
		<table class="admin-table">
			<thead>
			<tr>
				<th>ID</th>
				<th>Khách hàng</th>
				<th>Tổng tiền</th>
				<th>Ngày đặt</th>
				<th>Trạng thái</th>
				<th>Thao tác</th>
			</tr>
			</thead>
			<tbody>
			<?php if (empty($orders)): ?>
				<tr><td colspan="6" class="empty">Không có đơn hàng nào.</td></tr>
			<?php else: foreach ($orders as $order): ?>
				<tr>
					<td>#<?= (int)$order['id']; ?></td>
					<td><?= htmlspecialchars($order['customer_name'] ?? 'Khách vãng lai'); ?></td>
					<td><span class="price"><?= number_format($order['total_amount'], 0); ?>₫</span></td>
					<td><?= date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
					<td>
						<span class="status <?= strtolower(str_replace(' ', '-', $order['status_name'] ?? '')); ?>">
							<?= htmlspecialchars($order['status_name'] ?? 'N/A'); ?>
						</span>
					</td>
					<td>
						<div class="actions">
							<a href="index.php?page=orders&action=view&id=<?= $order['id']; ?>" class="btn view">
								<i class="fi fi-rr-eye"></i>
							</a>
						</div>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ($totalPages > 1): ?>
		<nav class="pagination">
			<?php
			$query_params = !empty($search_query) ? '&q=' . urlencode($search_query) : '';
			?>
			<?php if ($currentPage > 1): ?>
				<a href="index.php?page=orders&p=<?= $currentPage - 1 . $query_params; ?>">‹ Trước</a>
			<?php endif; ?>
			<?php
			for ($i = 1; $i <= $totalPages; $i++): ?>
				<a href="index.php?page=orders&p=<?= $i . $query_params; ?>" class="<?= ($i == $currentPage) ? 'current' : ''; ?>"><?= $i; ?></a>
			<?php endfor; ?>
			<?php if ($currentPage < $totalPages): ?>
				<a href="index.php?page=orders&p=<?= $currentPage + 1 . $query_params; ?>">Tiếp ›</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
<?php } ?>
