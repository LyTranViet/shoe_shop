<?php
// --- ADMIN: Manage Brands (modernized design) ---
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$brand = null;
$errors = [];

// --- SAVE (CREATE / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = (int)($_POST['id'] ?? 0);
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');

	if ($name === '') $errors[] = "⚠️ Brand name cannot be empty.";

	if (empty($errors)) {
		try {
			if ($id > 0) {
				$stmt = $db->prepare("UPDATE brands SET name = ?, description = ? WHERE id = ?");
				$stmt->execute([$name, $description, $id]);
				$msg = "✅ Brand updated successfully!";
			} else {
				$stmt = $db->prepare("INSERT INTO brands (name, description) VALUES (?, ?)");
				$stmt->execute([$name, $description]);
				$msg = "✅ Brand added successfully!";
			}
			header("Location: index.php?page=brands&msg=" . urlencode($msg));
			exit;
		} catch (PDOException $e) {
			$errors[] = "❌ Database error: " . $e->getMessage();
		}
	}
}

// --- DELETE ---
if ($action === 'delete' && $id > 0) {
	try {
		$stmt = $db->prepare("DELETE FROM brands WHERE id = ?");
		$stmt->execute([$id]); 
		header("Location: index.php?page=brands&msg=" . urlencode("Brand deleted successfully!"));
		exit;
	} catch (PDOException $e) {
		$errors[] = "❌ Cannot delete brand (might be in use).";
	}
}

// --- FETCH FOR EDIT ---
if ($action === 'edit' && $id > 0) {
	$stmt = $db->prepare("SELECT * FROM brands WHERE id = ?");
	$stmt->execute([$id]);
	$brand = $stmt->fetch();
}?>
<div class="admin-container">
<?php if ($action === 'add' || $action === 'edit'): ?>
	<div class="form-container">
		<div class="form-header">
			<h3><?= $action === 'edit' ? '<i class="fi fi-rr-pencil"></i> Chỉnh sửa thương hiệu' : '<i class="fi fi-rr-plus"></i> Thêm thương hiệu mới' ?></h3>
			<a href="index.php?page=brands" class="btn-back"><i class="fi fi-rr-arrow-left"></i> Quay lại</a>
		</div>
		<div>
			<?php if (!empty($errors)): ?>
				<div class="alert alert-error"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
			<?php endif; ?>

			<form method="POST" action="index.php?page=brands">
				<input type="hidden" name="id" value="<?= $brand['id'] ?? 0 ?>">
				<div class="form-group">
					<label>Tên thương hiệu</label>
					<input type="text" name="name" value="<?= htmlspecialchars($brand['name'] ?? '') ?>" required>
				</div>
				<div class="form-group">
					<label>Mô tả</label>
					<textarea name="description" rows="4"><?= htmlspecialchars($brand['description'] ?? '') ?></textarea>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn-submit"><?= $action === 'edit' ? '<i class="fi fi-rr-disk"></i> Cập nhật' : '<i class="fi fi-rr-check"></i> Thêm mới' ?></button>
				</div>
			</form>
		</div>
	</div>

<?php else: 
	// --- LIST VIEW ---
	$search_query = trim($_GET['q'] ?? '');
	$itemsPerPage = 10;
	$currentPage = max(1, (int)($_GET['p'] ?? 1));
	$where_clause = '';
	$params = [];

	if ($search_query !== '') {
		$where_clause = "WHERE name LIKE :search_name";
		$params[':search_name'] = "%$search_query%";
	}

	$countStmt = $db->prepare("SELECT COUNT(*) FROM brands $where_clause");
	$countStmt->execute($params);
	$totalItems = $countStmt->fetchColumn();
	$totalPages = ceil($totalItems / $itemsPerPage);
	$offset = ($currentPage - 1) * $itemsPerPage;
	$params[':limit'] = $itemsPerPage;
	$params[':offset'] = $offset;

	$stmt = $db->prepare("SELECT * FROM brands $where_clause ORDER BY id DESC LIMIT :limit OFFSET :offset");
	foreach ($params as $k => &$v)
		$stmt->bindParam($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
	$stmt->execute();
	$brands = $stmt->fetchAll();
?>
	<header class="admin-header">
        <h2><i class="fi fi-rr-tags"></i> Quản lý thương hiệu</h2>
        <div class="admin-tools">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="brands">
                <input type="text" name="q" placeholder="Tìm theo tên..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit">Tìm</button>
            </form>
            <a href="index.php?page=brands&action=add" class="add-btn"><i class="fi fi-rr-plus"></i> Thêm thương hiệu</a>
        </div>
    </header>

	<div class="table-wrapper">
			<?php if (isset($_GET['msg'])): ?>
				<div class="alert alert-success"><?= htmlspecialchars($_GET['msg']); ?></div>
			<?php endif; ?>

			<table class="admin-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Tên</th>
						<th>Mô tả</th>
						<th>Thao tác</th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($brands)): ?>
					<tr><td colspan="4" class="empty">Không có thương hiệu nào.</td></tr>
				<?php else: foreach ($brands as $b): ?>
					<tr>
						<td><?= $b['id'] ?></td>
						<td><?= htmlspecialchars($b['name']) ?></td>
						<td><?= htmlspecialchars($b['description']) ?></td>
						<td>
							<div class="actions">
								<a href="index.php?page=brands&action=edit&id=<?= $b['id'] ?>" class="btn edit"><i class="fi fi-rr-pencil"></i></a>
								<a href="index.php?page=brands&action=delete&id=<?= $b['id'] ?>" class="btn delete" onclick="return confirm('Bạn chắc chắn muốn xóa thương hiệu này?');"><i class="fi fi-rr-trash"></i></a>
							</div>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php if ($totalPages > 1): ?>
				<div class="pagination">
					<?php for ($i = 1; $i <= $totalPages; $i++): ?>
						<a href="index.php?page=brands&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="page-btn <?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>
