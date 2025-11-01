<?php
if (!isset($db)) {
    header('Location: index.php');
    exit;
}

// Start session if not already started to handle CSRF tokens
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$banner = null;
$errors = [];

const UPLOAD_DIR_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'banners' . DIRECTORY_SEPARATOR;
const UPLOAD_DIR_URL = 'assets/images/banners/';


// --- DELETE ---
if ($action === 'delete' && $id > 0) {
    // CSRF check from GET request
    if (!isset($_GET['csrf_token']) || !hash_equals($csrf_token, $_GET['csrf_token'])) {
        $errors[] = "Lỗi xác thực (CSRF token không hợp lệ).";
    } else {

    
	try {
        // First, get the image URL to delete the file
        $stmt = $db->prepare("SELECT image_url FROM banners WHERE id = ?");
        $stmt->execute([$id]);
        $image_to_delete = $stmt->fetchColumn();

		$stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
		$stmt->execute([$id]);

        // Delete the image file from server
        delete_banner_image($image_to_delete);

		header("Location: index.php?page=banners&msg=" . urlencode("Đã xóa banner!"));
		exit;
	} catch (PDOException $e) {
		$errors[] = "Lỗi khi xóa: " . $e->getMessage();
	}
    }
}

// --- FETCH FOR EDIT ---
if ($action === 'edit' && $id > 0) {
	$stmt = $db->prepare("SELECT * FROM banners WHERE id = ?");
	$stmt->execute([$id]);
	$banner = $stmt->fetch();
}

/**
 * Deletes a banner image file from the server.
 * @param string|null $image_url The relative URL of the image from the project root.
 */
function delete_banner_image(?string $image_url): void {
    if (!empty($image_url)) {
        $file_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image_url);
        if (file_exists($file_path)) @unlink($file_path);
    }
}
?>
<div class="admin-container">
<?php if ($action === 'add' || $action === 'edit'): ?>
	<div class="form-container">
		<div class="form-header">
			<h3><?= $action === 'edit' ? '<i class="fi fi-rr-pencil"></i> Chỉnh sửa Banner' : '<i class="fi fi-rr-plus"></i> Thêm Banner mới' ?></h3>
			<a href="index.php?page=banners" class="btn-back"><i class="fi fi-rr-arrow-left"></i> Quay lại</a>
		</div>
		<div>
			<?php if ($msg = flash_get('error')): ?>
				<div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
			<?php endif; ?>
			<?php if (!empty($errors)): ?>
				<div class="alert alert-error"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
			<?php endif; ?>

			<form method="POST" action="index.php?page=banners" enctype="multipart/form-data">
				<input type="hidden" name="id" value="<?= $banner['id'] ?? 0 ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="current_image" value="<?= htmlspecialchars($banner['image_url'] ?? '') ?>">

				<div class="form-group">
					<label>Tiêu đề</label>
					<input type="text" name="title" value="<?= htmlspecialchars($banner['title'] ?? '') ?>" required>
				</div>

                <div class="form-group">
                    <label>Ảnh Banner</label>
                    <?php if ($action === 'edit' && !empty($banner['image_url'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="../<?= htmlspecialchars($banner['image_url']) ?>" alt="Current Banner" style="max-width: 200px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <small><?= $action === 'edit' ? 'Để trống nếu không muốn thay đổi ảnh.' : 'Ảnh không được quá 2MB.' ?></small>
                </div>

                <div class="form-group">
					<label>Đường dẫn (Link)</label>
					<input type="url" name="link" value="<?= htmlspecialchars($banner['link'] ?? '') ?>" placeholder="https://example.com/product/123">
				</div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" <?= (isset($banner['is_active']) && $banner['is_active'] == 1) || $action === 'add' ? 'checked' : '' ?>>
                        Kích hoạt (hiển thị banner trên trang chủ)
                    </label>
                </div>

				<div class="form-actions">
					<button type="submit" class="btn-submit"><?= $action === 'edit' ? '<i class="fi fi-rr-disk"></i> Cập nhật' : '<i class="fi fi-rr-check"></i> Thêm mới' ?></button>
				</div>
			</form>
		</div>
	</div>

<?php else: 
	// --- LIST VIEW ---
	$stmt = $db->query("SELECT * FROM banners ORDER BY id DESC");
	$banners = $stmt->fetchAll();
?>
	<header class="admin-header">
        <h2><i class="fi fi-rr-picture"></i> Quản lý Banner</h2>
        <div class="admin-tools">
            <a href="index.php?page=banners&action=add" class="add-btn"><i class="fi fi-rr-plus"></i> Thêm Banner</a>
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
                    <th>Ảnh</th>
                    <th>Tiêu đề</th>
                    <th>Link</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($banners)): ?>
                <tr><td colspan="6" class="empty">Chưa có banner nào.</td></tr>
            <?php else: foreach ($banners as $b): ?>
                <tr>
                    <td><?= $b['id'] ?></td>
                    <td><img src="../<?= htmlspecialchars($b['image_url']) ?>" alt="<?= htmlspecialchars($b['title']) ?>" class="thumb"></td>
                    <td><?= htmlspecialchars($b['title']) ?></td>
                    <td>
                        <?php if (!empty($b['link'])): ?>
                            <a href="<?= htmlspecialchars($b['link']) ?>" target="_blank" title="<?= htmlspecialchars($b['link']) ?>">Link</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status <?= $b['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $b['is_active'] ? 'Đang hoạt động' : 'Không hoạt động' ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="index.php?page=banners&action=edit&id=<?= $b['id'] ?>" class="btn edit" title="Sửa"><i class="fi fi-rr-pencil"></i></a>
                            <a href="index.php?page=banners&action=delete&id=<?= $b['id'] ?>&csrf_token=<?= $csrf_token ?>" class="btn delete" title="Xóa" onclick="return confirm('Bạn chắc chắn muốn xóa banner này?');"><i class="fi fi-rr-trash"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.status.active { background-color: #dcfce7; color: #166534; }
.status.inactive { background-color: #f1f5f9; color: #475569; }
</style>