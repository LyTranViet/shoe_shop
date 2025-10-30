<?php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$supplier = null;
$errors = [];

// --- CREATE / UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id = (int)($_POST['id'] ?? 0);
	$name = trim($_POST['name'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$address = trim($_POST['address'] ?? '');

	if ($name === '') $errors[] = "Tên nhà cung cấp không được để trống.";
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Định dạng email không hợp lệ.";
    }

	if (empty($errors)) {
		try {
			if ($id > 0) {
				$stmt = $db->prepare("UPDATE supplier SET supplierName = ?, Sdt = ?, Address = ?, Email = ? WHERE supplier_id = ?");
				$stmt->execute([$name, $phone, $address, $email, $id]);
				$msg = "Cập nhật nhà cung cấp thành công!";
			} else {
				$stmt = $db->prepare("INSERT INTO supplier (supplierName, Sdt, Address, Email) VALUES (?, ?, ?, ?)");
				$stmt->execute([$name, $phone, $address, $email]);
				$msg = "Thêm nhà cung cấp thành công!";
			}
			header("Location: index.php?page=suppliers&msg=" . urlencode($msg));
			exit;
		} catch (PDOException $e) {
			$errors[] = "❌ Lỗi cơ sở dữ liệu: " . $e->getMessage();
		}
	}
}

// --- DELETE ---
if ($action === 'delete' && $id > 0) {
	try {
		$stmt = $db->prepare("DELETE FROM supplier WHERE supplier_id = ?");
		$stmt->execute([$id]);
		header("Location: index.php?page=suppliers&msg=" . urlencode("Đã xóa nhà cung cấp!"));
		exit;
	} catch (PDOException $e) {
		// Check for foreign key constraint violation
        if ($e->getCode() == '23000') {
             $errors[] = "Không thể xóa nhà cung cấp này vì đang có dữ liệu liên quan (ví dụ: phiếu nhập hàng).";
        } else {
            $errors[] = "Lỗi cơ sở dữ liệu khi xóa: " . $e->getMessage();
        }
	}
}

// --- FETCH FOR EDIT ---
if ($action === 'edit' && $id > 0) {
	$stmt = $db->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
	$stmt->execute([$id]);
	$supplier = $stmt->fetch();
}
?>
<div class="admin-container">
<?php if ($action === 'add' || $action === 'edit'): ?>
	<div class="form-container">
		<div class="form-header">
			<h3><?= $action === 'edit' ? '<i class="fi fi-rr-pencil"></i> Chỉnh sửa Nhà cung cấp' : '<i class="fi fi-rr-plus"></i> Thêm Nhà cung cấp mới' ?></h3>
			<a href="index.php?page=suppliers" class="btn-back"><i class="fi fi-rr-arrow-left"></i> Quay lại</a>
		</div>
		<div>
			<?php if (!empty($errors)): ?>
				<div class="alert alert-error"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
			<?php endif; ?>

			<form method="POST" action="index.php?page=suppliers">
				<input type="hidden" name="id" value="<?= $supplier['supplier_id'] ?? 0 ?>">
				<div class="form-group">
					<label>Tên Nhà cung cấp</label>
					<input type="text" name="name" value="<?= htmlspecialchars($supplier['supplierName'] ?? '') ?>" required>
				</div>
                <div class="form-group">
					<label>Số điện thoại</label>
					<input type="text" name="phone" value="<?= htmlspecialchars($supplier['Sdt'] ?? '') ?>">
				</div>
                <div class="form-group">
					<label>Email</label>
					<input type="email" name="email" value="<?= htmlspecialchars($supplier['Email'] ?? '') ?>">
				</div>
				<div class="form-group">
					<label>Địa chỉ</label>
					<textarea name="address" rows="3"><?= htmlspecialchars($supplier['Address'] ?? '') ?></textarea>
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
		$where_clause = "WHERE supplierName LIKE :q OR Email LIKE :q OR Sdt LIKE :q";
		$params[':q'] = "%$search_query%";
	}

	$countStmt = $db->prepare("SELECT COUNT(*) FROM supplier $where_clause");
	$countStmt->execute($params);
	$totalItems = $countStmt->fetchColumn();
	$totalPages = ceil($totalItems / $itemsPerPage);
	$offset = ($currentPage - 1) * $itemsPerPage;
	
    $params[':limit'] = $itemsPerPage;
	$params[':offset'] = $offset;

	$stmt = $db->prepare("SELECT * FROM supplier $where_clause ORDER BY supplier_id DESC LIMIT :limit OFFSET :offset");
	foreach ($params as $k => &$v)
		$stmt->bindParam($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
	$stmt->execute();
	$suppliers = $stmt->fetchAll();
?>
	<header class="admin-header">
        <h2><i class="fi fi-rr-supplier"></i> Quản lý Nhà cung cấp</h2>
        <div class="admin-tools">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="suppliers">
                <input type="text" name="q" placeholder="Tìm theo tên, email, sđt..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit">Tìm</button>
            </form>
            <a href="index.php?page=suppliers&action=add" class="add-btn"><i class="fi fi-rr-plus"></i> Thêm NCC</a>
        </div>
    </header>

	<div class="table-wrapper">
			<?php if (isset($_GET['msg'])): ?>
				<div class="alert alert-success" style="margin: 10px;"><?= htmlspecialchars($_GET['msg']); ?></div>
			<?php endif; ?>
            <?php if (!empty($errors)): ?>
				<div class="alert alert-error" style="margin: 10px;"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
			<?php endif; ?>

			<table class="admin-table">
				<thead>
					<tr>
						<th>ID</th>
						<th>Tên NCC</th>
						<th>SĐT</th>
						<th>Email</th>
                        <th>Địa chỉ</th>
						<th>Thao tác</th>
					</tr>
				</thead>
				<tbody>
				<?php if (empty($suppliers)): ?>
					<tr><td colspan="6" class="empty">Không có nhà cung cấp nào.</td></tr>
				<?php else: foreach ($suppliers as $s): ?>
					<tr>
						<td><?= $s['supplier_id'] ?></td>
						<td><?= htmlspecialchars($s['supplierName']) ?></td>
						<td><?= htmlspecialchars($s['Sdt'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['Email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($s['Address'] ?? '—') ?></td>
						<td>
							<div class="actions">
								<a href="index.php?page=suppliers&action=edit&id=<?= $s['supplier_id'] ?>" class="btn edit" title="Sửa"><i class="fi fi-rr-pencil"></i></a>
								<a href="index.php?page=suppliers&action=delete&id=<?= $s['supplier_id'] ?>" class="btn delete" title="Xóa" onclick="return confirm('Bạn chắc chắn muốn xóa nhà cung cấp này?');"><i class="fi fi-rr-trash"></i></a>
							</div>
						</td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php if ($totalPages > 1): ?>
				<div class="pagination">
					<?php for ($i = 1; $i <= $totalPages; $i++): ?>
						<a href="index.php?page=suppliers&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="page-btn <?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>