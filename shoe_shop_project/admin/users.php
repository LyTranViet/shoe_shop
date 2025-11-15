<?php
// This file is included by admin/index.php
if (!isset($db)) {
    header('Location: index.php');
    exit;
}

// Xác định action & id
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$user = null;
$errors = $errors ?? []; // Initialize if not set from POST handling

// --- LOAD FORM DATA ---
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT u.*, ur.role_id FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id WHERE u.id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
}

// --- Fetch roles ---
if (is_superadmin()) {
    // SuperAdmin can manage all admin roles
    $admin_roles = $db->query("SELECT id, name FROM roles WHERE id IN (3,4,5)")->fetchAll();
} else {
    // Admin can only manage Staff
    $admin_roles = $db->query("SELECT id, name FROM roles WHERE id = 3")->fetchAll(); // Role ID 3 = Staff
}

if ($action === 'add' || $action === 'edit'):
?>
<div class="admin-container">
    <div class="form-container">
		<div class="form-header">
			<h3><?php echo $action === 'edit' ? '<i class="fi fi-rr-pencil"></i> Chỉnh sửa người dùng' : '<i class="fi fi-rr-user-add"></i> Thêm người dùng mới'; ?></h3>
			<a href="index.php?page=users" class="btn-back"><i class="fi fi-rr-arrow-left"></i> Quay lại</a>
		</div>
		<div>
			<?php 
				// Hiển thị lỗi từ flash session nếu có
				if ($msg = flash_get('error')) {
					echo "<div class='alert alert-error' style='margin-bottom: 15px;'><p>$msg</p></div>";
				}
			?>
			<?php if (!empty($errors)): ?>
			<div class="alert alert-error" style="margin-bottom: 15px;">
				<?php foreach ($errors as $error) echo "<p>$error</p>"; ?>
			</div>
			<?php endif; ?>

			<form method="POST" action="index.php?page=users">
				<input type="hidden" name="id" value="<?php echo $user['id'] ?? 0; ?>">
				<div class="form-group">
					<label>Họ tên</label>
					<input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
				</div>
				<div class="form-group">
					<label>Email</label>
					<input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
				</div>
				<div class="form-group">
					<label>Mật khẩu <?php echo $action === 'edit' ? '(bỏ trống nếu không đổi)' : ''; ?></label>
					<input type="password" name="password" placeholder="********">
				</div>
				<div class="form-group">
					<label>Vai trò</label>
					<select name="role_id" required>
						<option value="">-- Chọn vai trò --</option>
						<?php foreach ($admin_roles as $role): ?>
							<option value="<?php echo $role['id']; ?>" <?php echo isset($user['role_id']) && $user['role_id'] == $role['id'] ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($role['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn-submit"><?php echo $action === 'edit' ? '<i class="fi fi-rr-disk"></i> Cập nhật' : '<i class="fi fi-rr-check"></i> Lưu'; ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php else:
// --- LIST USERS ---
$search_query = trim($_GET['q'] ?? '');
$itemsPerPage = 10;
$currentPage = (int)($_GET['p'] ?? 1);
if (is_superadmin()) {
    // SuperAdmin sees all admin/staff users
    $where_clause = "WHERE ur.role_id IN (3,4,5)";
} else {
    // Admin only sees Staff users
    $where_clause = "WHERE ur.role_id = 3"; // Role ID 3 = Staff
}
$params = [];

if (!empty($search_query)) {
    $where_clause .= " AND (u.name LIKE :q OR u.email LIKE :q)";
    $params[':q'] = "%$search_query%";
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id $where_clause");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

$params[':limit'] = $itemsPerPage;
$params[':offset'] = $offset;

$stmt = $db->prepare("
    SELECT u.*, r.name as role_name 
    FROM users u 
    LEFT JOIN user_roles ur ON u.id = ur.user_id 
    LEFT JOIN roles r ON ur.role_id = r.id 
    $where_clause 
    ORDER BY u.id DESC 
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => &$value)
    $stmt->bindParam($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->execute();
$users = $stmt->fetchAll();
?>
<div class="admin-container">
	<header class="admin-header">
		<h2><i class="fi fi-rr-users-alt"></i> Quản lý tài khoản Admin</h2>
		<div class="admin-tools">
			<form method="GET" class="search-form" action="">
				<input type="hidden" name="page" value="users">
				<input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Tìm theo tên hoặc email...">
				<button type="submit">Tìm</button>
			</form>
			<a href="index.php?page=users&action=add" class="add-btn"><i class="fi fi-rr-plus"></i> Thêm mới</a>
		</div>
	</header>

	<div class="table-wrapper">
		<table class="admin-table">
			<thead>
				<tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Thao tác</th></tr>
			</thead>
			<tbody>
				<?php if (empty($users)): ?>
					<tr><td colspan="5" class="empty">Không có người dùng nào.</td></tr>
				<?php else: ?>
					<?php foreach ($users as $u): ?>
					<tr>
						<td><?php echo (int)$u['id']; ?></td>
						<td><?php echo htmlspecialchars($u['name']); ?></td>
						<td><?php echo htmlspecialchars($u['email']); ?></td>
						<td><?php echo htmlspecialchars($u['role_name'] ?? '—'); ?></td>
						<td>
							<div class="actions">
								<?php
									// SuperAdmin can edit anyone. Admin can only edit Staff.
									$canEdit = is_superadmin() || (is_admin() && $u['role_name'] === 'Staff');
								?>
								<?php if ($canEdit): ?>
									<a href="index.php?page=users&action=edit&id=<?php echo $u['id']; ?>" class="btn edit" title="Sửa"><i class="fi fi-rr-pencil"></i></a>
									<a href="index.php?page=users&action=delete&id=<?php echo $u['id']; ?>" class="btn delete" title="Xóa" onclick="return confirm('Xác nhận xoá người dùng này?');"><i class="fi fi-rr-trash"></i></a>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ($totalPages > 1): ?>
	<div class="pagination">
		<?php if ($currentPage > 1): ?>
			<a href="index.php?page=users&p=1&q=<?= urlencode($search_query) ?>">« Đầu</a>
			<a href="index.php?page=users&p=<?= $currentPage - 1 ?>&q=<?= urlencode($search_query) ?>">‹ Trước</a>
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
			<a href="index.php?page=users&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="<?= $i == $currentPage ? 'current' : '' ?>"><?= $i ?></a>
		<?php endfor; ?>
		<?php if ($currentPage < $totalPages): ?>
			<a href="index.php?page=users&p=<?= $currentPage + 1 ?>&q=<?= urlencode($search_query) ?>">Tiếp ›</a>
			<a href="index.php?page=users&p=<?= $totalPages ?>&q=<?= urlencode($search_query) ?>">Cuối »</a>
		<?php endif; ?>
	</div>
	<?php endif; ?>
<?php endif; ?>
