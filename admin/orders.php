<?php
// This file is included by admin/index.php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
	$order_id = (int)($_POST['order_id'] ?? 0);
	$status_id = (int)($_POST['status_id'] ?? 0);

    if ($order_id > 0 && $status_id > 0) {
		try {
            $db->beginTransaction();

            // Cập nhật trạng thái đơn hàng
            $stmt = $db->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
            $stmt->execute([$status_id, $order_id]);

            // Lấy thông tin phiếu xuất liên quan
            $exportStmt = $db->prepare("SELECT id FROM export_receipt WHERE order_id = ? LIMIT 1");
            $exportStmt->execute([$order_id]);
            $export_receipt_id = $exportStmt->fetchColumn();

            if ($export_receipt_id) {
                // Nếu trạng thái đơn hàng là "Đang giao" (ID=2), cập nhật phiếu xuất thành "Đã xuất kho"
                if ($status_id === 2) {
                    $updateExportStmt = $db->prepare("UPDATE export_receipt SET status = 'Đã xuất kho' WHERE id = ? AND status = 'Đang xử lý'");
                    $updateExportStmt->execute([$export_receipt_id]);
                }
                // Nếu trạng thái đơn hàng là "Hoàn hàng" (ID=5 - giả sử), cập nhật phiếu xuất thành "Hoàn kho" và hoàn lại tồn kho
                elseif ($status_id === 5) { // Giả sử 'Hoàn hàng' có status_id = 5
                    // Cập nhật phiếu xuất thành "Hoàn kho"
                    $updateExportStmt = $db->prepare("UPDATE export_receipt SET status = 'Hoàn kho' WHERE id = ?");
                    $updateExportStmt->execute([$export_receipt_id]); // Chỉ cập nhật nếu đơn hàng đã được giao

                    // Hoàn lại tồn kho
                    $detailsStmt = $db->prepare("SELECT id, batch_id, productsize_id, quantity, price FROM export_receipt_detail WHERE export_id = ?");
                    $detailsStmt->execute([$export_receipt_id]);
                    $details = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($details as $detail) {
                        $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining + ? WHERE id = ?")->execute([$detail['quantity'], $detail['batch_id']]);
                        $db->prepare("UPDATE product_sizes SET stock = stock + ? WHERE id = ?")->execute([$detail['quantity'], $detail['productsize_id']]);
                    }

                    // Tự động tạo phiếu nhập hàng hoàn
                    $returnReceiptCode = 'PN-HOAN-' . $order_id;
                    $returnNote = 'Tự động tạo do hoàn hàng từ đơn hàng #' . $order_id;
                    $returnStmt = $db->prepare("INSERT INTO import_receipt (receipt_code, supplier_id, employee_id, total_amount, note) VALUES (?, NULL, ?, 0, ?)");
                    $returnStmt->execute([$returnReceiptCode, current_user_id(), $returnNote]);
                    $return_import_id = $db->lastInsertId();

                    // Tạo chi tiết phiếu nhập hoàn
                    foreach ($details as $detail) {
                        // Lấy batch_code từ batch_id
                        $batchCodeStmt = $db->prepare("SELECT batch_code FROM product_batch WHERE id = ?");
                        $batchCodeStmt->execute([$detail['batch_id']]);
                        $batch_code = $batchCodeStmt->fetchColumn();

                        $db->prepare("INSERT INTO import_receipt_detail (import_id, productsize_id, batch_id, batch_code, quantity, price) VALUES (?, ?, ?, ?, ?, ?)")
                           ->execute([$return_import_id, $detail['productsize_id'], $detail['batch_id'], $batch_code, $detail['quantity'], $detail['price']]);
                    }
                }
            }

            $db->commit();
			header('Location: index.php?page=orders&action=view&id=' . $order_id . '&status_updated=1');
			exit;
		} catch (PDOException $e) {
			$errors[] = "Database error: " . $e->getMessage();
		}
	} else {
		$errors[] = "Invalid data provided for status update.";
	}
}

if ($action === 'view' && $id > 0):
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

else:
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
		<div class="pagination">
			<?php
			$query_params = !empty($search_query) ? '&q=' . urlencode($search_query) : '';
			for ($i = 1; $i <= $totalPages; $i++): ?>
				<a href="index.php?page=orders&p=<?= $i . $query_params; ?>" class="page-btn <?= ($i == $currentPage) ? 'active' : ''; ?>"><?= $i; ?></a>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</div>
<?php endif; ?>
