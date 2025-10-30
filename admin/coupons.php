<?php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$coupon = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = (int)($_POST['id'] ?? 0);
	$code = trim($_POST['code'] ?? '');
	$discount_percent = (int)($_POST['discount_percent'] ?? 0);
	$valid_from = $_POST['valid_from'] ?? '';
	$valid_to = $_POST['valid_to'] ?? '';
	$usage_limit = (int)($_POST['usage_limit'] ?? 0);

	if (empty($code) || $discount_percent <= 0 || empty($valid_from) || empty($valid_to)) {
		$errors[] = 'Please fill all required fields.';
	}

	if (empty($errors)) {
		try {
			if ($id > 0) {
				$stmt = $db->prepare("UPDATE coupons SET code=?, discount_percent=?, valid_from=?, valid_to=?, usage_limit=? WHERE id=?");
				$stmt->execute([$code, $discount_percent, $valid_from, $valid_to, $usage_limit, $id]);
			} else {
				$stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, valid_from, valid_to, usage_limit) VALUES (?, ?, ?, ?, ?)");
				$stmt->execute([$code, $discount_percent, $valid_from, $valid_to, $usage_limit]);
			}
			header('Location: index.php?page=coupons');
			exit;
		} catch (PDOException $e) {
			$errors[] = "Database error: " . $e->getMessage();
		}
	}
}

if ($action === 'delete' && $id > 0) {
	try {
		$stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
		$stmt->execute([$id]);
		header('Location: index.php?page=coupons');
		exit;
	} catch (PDOException $e) {
		header('Location: index.php?page=coupons&error=deletefailed');
		exit;
	}
}

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
			<?php
			$query_params = !empty($search_query) ? '&q=' . urlencode($search_query) : '';
			for ($i = 1; $i <= $totalPages; $i++): ?>
				<a href="index.php?page=coupons&p=<?php echo $i; ?><?php echo $query_params; ?>"
				   class="page-btn <?php echo ($i == $currentPage) ? 'active' : ''; ?>"><?php echo $i; ?></a>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</div>
<?php endif; ?>