<?php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$coupon = null;
$errors = [];

if ($action === 'edit' && $id > 0) {
	$stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
	$stmt->execute([$id]);
	$coupon = $stmt->fetch();
}
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="admin-container">
	<div class="form-container">
		<div class="form-header">
			<h3><?php echo $action === 'edit' ? '<i class="fi fi-rr-pencil"></i> Chỉnh sửa Coupon' : '<i class="fi fi-rr-plus"></i> Thêm Coupon mới'; ?></h3>
			<a href="index.php?page=coupons" class="btn-back"><i class="fi fi-rr-arrow-left"></i> Quay lại</a>
		</div>
		<div>
			<?php if ($msg = flash_get('error')): ?>
				<div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
			<?php endif; ?>
			<?php if (!empty($errors)): ?>
				<div class="alert alert-error">
					<?php foreach ($errors as $error) echo "<p>$error</p>"; ?>
				</div>
			<?php endif; ?>

			<form method="POST" action="index.php?page=coupons">
				<input type="hidden" name="id" value="<?php echo $coupon['id'] ?? 0; ?>">

				<div class="form-group">
					<label for="code">Mã Coupon</label>
					<input type="text" id="code" name="code" value="<?php echo htmlspecialchars($coupon['code'] ?? ''); ?>" required>
				</div>

				<div class="form-group">
					<label for="discount_percent">Giảm giá (%)</label>
					<input type="number" id="discount_percent" name="discount_percent" value="<?php echo htmlspecialchars($coupon['discount_percent'] ?? ''); ?>" required>
				</div>

				<div class="form-group">
					<label for="valid_from">Hiệu lực từ</label>
					<input type="datetime-local" id="valid_from" name="valid_from"
						value="<?php echo !empty($coupon['valid_from']) ? date('Y-m-d\TH:i', strtotime($coupon['valid_from'])) : ''; ?>" required>
				</div>

				<div class="form-group">
					<label for="valid_to">Hiệu lực đến</label>
					<input type="datetime-local" id="valid_to" name="valid_to"
						value="<?php echo !empty($coupon['valid_to']) ? date('Y-m-d\TH:i', strtotime($coupon['valid_to'])) : ''; ?>" required>
				</div>

				<div class="form-group">
					<label for="usage_limit">Giới hạn sử dụng</label>
					<input type="number" id="usage_limit" name="usage_limit"
						value="<?php echo htmlspecialchars($coupon['usage_limit'] ?? '100'); ?>" required>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn-submit">
						<?php echo $action === 'edit' ? '<i class="fi fi-rr-disk"></i> Cập nhật' : '<i class="fi fi-rr-check"></i> Lưu'; ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php else:
	// === List View ===
	$search_query = trim($_GET['q'] ?? '');
	$itemsPerPage = 10;
	$currentPage = (int)($_GET['p'] ?? 1);

	$where_clause = '';
	$params = [];
	if (!empty($search_query)) {
		$where_clause = "WHERE code LIKE :search_code";
		$params[':search_code'] = "%$search_query%";
	}

	$countStmt = $db->prepare("SELECT COUNT(*) FROM coupons $where_clause");
	$countStmt->execute($params);
	$totalItems = $countStmt->fetchColumn();
	$totalPages = ceil($totalItems / $itemsPerPage);
	$offset = ($currentPage - 1) * $itemsPerPage;

	$params[':limit'] = $itemsPerPage;
	$params[':offset'] = $offset;

	$stmt = $db->prepare("SELECT * FROM coupons $where_clause ORDER BY id DESC LIMIT :limit OFFSET :offset");
	foreach ($params as $key => &$value)
		$stmt->bindParam($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
	$stmt->execute();
	$coupons = $stmt->fetchAll();
?>
<div class="admin-container">
	<header class="admin-header">
		<h2><i class="fi fi-rr-ticket"></i> Quản lý Coupon</h2>
		<div class="admin-tools">
			<form method="get" class="search-form">
				<input type="hidden" name="page" value="coupons">
				<input type="text" name="q" placeholder="Tìm theo mã..." value="<?= htmlspecialchars($search_query) ?>">
				<button type="submit">Tìm</button>
			</form>
			<a href="index.php?page=coupons&action=add" class="add-btn"><i class="fi fi-rr-plus"></i> Thêm Coupon</a>
		</div>
	</header>

	<div class="table-wrapper">
		<?php if ($msg = flash_get('error')): ?>
			<div class="alert alert-error" style="margin: 10px;"><?= htmlspecialchars($msg) ?></div>
		<?php endif; ?>
		<table class="admin-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Mã</th>
					<th>Giảm giá</th>
					<th>Từ ngày</th>
					<th>Đến ngày</th>
					<th>Giới hạn</th>
					<th>Thao tác</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($coupons)): ?>
					<tr><td colspan="7" class="empty">Không có coupon nào.</td></tr>
				<?php else: foreach ($coupons as $coupon): ?>
				<tr>
					<td>#<?php echo (int)$coupon['id']; ?></td>
					<td><strong><?php echo htmlspecialchars($coupon['code']); ?></strong></td>
					<td><?php echo (int)$coupon['discount_percent']; ?>%</td>
					<td><?php echo date('d/m/Y', strtotime($coupon['valid_from'])); ?></td>
					<td><?php echo date('d/m/Y', strtotime($coupon['valid_to'])); ?></td>
					<td><?php echo (int)$coupon['usage_limit']; ?></td>
					<td>
						<div class="actions">
							<a href="index.php?page=coupons&action=edit&id=<?php echo $coupon['id']; ?>" class="btn edit" title="Sửa"><i class="fi fi-rr-pencil"></i></a>
							<a href="index.php?page=coupons&action=delete&id=<?php echo $coupon['id']; ?>" class="btn delete" title="Xóa" onclick="return confirm('Bạn chắc chắn muốn xóa coupon này?');"><i class="fi fi-rr-trash"></i></a>
						</div>
					</td>
				</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ($totalPages > 1): ?>
		<div class="pagination">
			<?php if ($currentPage > 1): ?>
				<a href="index.php?page=coupons&p=1&q=<?= urlencode($search_query) ?>">« Đầu</a>
				<a href="index.php?page=coupons&p=<?= $currentPage - 1 ?>&q=<?= urlencode($search_query) ?>">‹ Trước</a>
			<?php endif; ?>
			<?php
			$window = 5;
			$half = floor($window / 2);
			$start = $currentPage - $half;
			$end = $currentPage + $half;
			if ($start < 1) {
				$start = 1;
				$end = min($window, $totalPages);
			}
			if ($end > $totalPages) {
				$end = $totalPages;
				$start = max(1, $end - $window + 1);
			}
			for ($i = $start; $i <= $end; $i++): ?>
				<a href="index.php?page=coupons&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="<?= $i == $currentPage ? 'current' : '' ?>"><?= $i ?></a>
			<?php endfor; ?>
			<?php if ($currentPage < $totalPages): ?>
				<a href="index.php?page=coupons&p=<?= $currentPage + 1 ?>&q=<?= urlencode($search_query) ?>">Tiếp ›</a>
				<a href="index.php?page=coupons&p=<?= $totalPages ?>&q=<?= urlencode($search_query) ?>">Cuối »</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
<?php endif; ?>