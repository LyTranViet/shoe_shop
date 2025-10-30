<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

$db = get_db();

// --- Router Logic ---
$page = $_GET['page'] ?? (is_admin() ? 'dashboard' : 'orders');

// **QUAN TRỌNG: Xử lý handler/API files TRƯỚC khi check permission**
if (in_array($page, ['handle_stock_in', 'handle_stock_out'])) {
    include __DIR__ . '/' . $page . '.php';
    exit; 
}
require_once __DIR__ . '/../includes/header_admin.php';
 
// --- Define page permissions based on new roles ---
$is_superadmin = is_superadmin();
$is_admin = is_admin() && !$is_superadmin; // Ensure is_admin is exclusive
$is_staff = is_staff();
 
// --- Page Permissions based on Roles ---
$superadmin_pages = ['dashboard', 'orders', 'products', 'coupons', 'categories', 'brands', 'customers', 'users', 'suppliers', 'stock_in', 'stock_out', 'inventory', 'banners'];
$admin_pages      = ['dashboard', 'orders', 'products', 'coupons', 'categories', 'brands', 'customers', 'users', 'suppliers', 'stock_in', 'stock_out', 'inventory'];
$staff_pages      = ['orders', 'customers', 'products']; // Staff can only view these pages
 
if ($is_superadmin) {
    $allowed_pages = $superadmin_pages;
} elseif ($is_admin) {
    $allowed_pages = $admin_pages;
} else { // is_staff
    $allowed_pages = $staff_pages;
}
 
// If a user tries to access a page they don't have permission for, redirect them.
if (!in_array($page, $allowed_pages, true)) {
    flash_set('error', 'Bạn không có quyền truy cập trang này.');
    header('Location: index.php?page=' . ($is_staff ? 'orders' : 'dashboard'));
    exit;
}
?>
<div class="admin-layout">
	<aside class="admin-nav">
		<nav>
			<div class="nav-section">
				<h4 class="nav-section-title"><i class="fi fi-rr-chart-pie-alt"></i> Tổng quan</h4>
				<?php if ($is_superadmin || $is_admin): ?>
					<a href="index.php?page=dashboard" class="<?php echo ($page === 'dashboard') ? 'active' : ''; ?>"><i class="fi fi-rr-dashboard"></i> Dashboard</a>
				<?php endif; ?>
			</div>

			<div class="nav-section">
				<h4 class="nav-section-title"><i class="fi fi-rr-shopping-cart"></i> Quản lý Đơn hàng</h4>
				<a href="index.php?page=orders" class="<?php echo ($page === 'orders') ? 'active' : ''; ?>"><i class="fi fi-rr-receipt"></i> Đơn hàng</a>
			</div>

			<div class="nav-section">
				<h4 class="nav-section-title"><i class="fi fi-rr-box"></i> Quản lý Sản phẩm</h4>
				<?php if ($is_superadmin || $is_admin || $is_staff): ?>
					<a href="index.php?page=products" class="<?php echo ($page === 'products') ? 'active' : ''; ?>"><i class="fi fi-rr-boxes"></i> Sản phẩm</a>
				<?php endif; ?>
				<?php if ($is_superadmin || $is_admin): ?>
					<a href="index.php?page=categories" class="<?php echo ($page === 'categories') ? 'active' : ''; ?>"><i class="fi fi-rr-folder-open"></i> Danh mục</a>
					<a href="index.php?page=brands" class="<?php echo ($page === 'brands') ? 'active' : ''; ?>"><i class="fi fi-rr-tags"></i> Thương hiệu</a>
					<a href="index.php?page=banners" class="<?php echo ($page === 'banners') ? 'active' : ''; ?>"><i class="fi fi-rr-picture"></i> Banner</a>
					<a href="index.php?page=coupons" class="<?php echo ($page === 'coupons') ? 'active' : ''; ?>"><i class="fi fi-rr-ticket"></i> Mã giảm giá</a>
				<?php endif; ?>
			</div>

			<div class="nav-section">
				<h4 class="nav-section-title"><i class="fi fi-rr-users"></i> Quản lý Người dùng</h4>
				<?php if ($is_superadmin || $is_admin || $is_staff): ?>
					<a href="index.php?page=customers" class="<?php echo ($page === 'customers') ? 'active' : ''; ?>"><i class="fi fi-rr-user-check"></i> Khách hàng</a>
				<?php endif; ?>
				<?php if ($is_superadmin || $is_admin): ?>
					<a href="index.php?page=users" class="<?php echo ($page === 'users') ? 'active' : ''; ?>"><i class="fi fi-rr-users-alt"></i> Nhân viên</a>
				<?php endif; ?>
			</div>

			<?php if ($is_superadmin || $is_admin): ?>
			<div class="nav-section">
				<h4 class="nav-section-title"><i class="fi fi-rr-warehouse"></i> Quản lý Kho & NCC</h4>
				<a href="index.php?page=suppliers" class="<?= ($page === 'suppliers') ? 'active' : '' ?>"><i class="fi fi-rr-supplier"></i> Nhà cung cấp</a>
				<div class="nav-item-with-submenu">
					<a href="#" class="submenu-toggle <?= in_array($page, ['stock_in', 'stock_out', 'inventory']) ? 'active open' : '' ?>">
						<span><i class="fi fi-rr-dolly-flatbed"></i> Kho trữ</span>
						<span class="arrow">›</span>
					</a>
					<div class="submenu" style="<?= in_array($page, ['stock_in', 'stock_out', 'inventory']) ? 'display: block;' : '' ?>">
						<a href="index.php?page=stock_in" class="<?= ($page === 'stock_in') ? 'active' : '' ?>"><i class="fi fi-rr-document"></i> Phiếu nhập</a>
						<a href="index.php?page=stock_out" class="<?= ($page === 'stock_out') ? 'active' : '' ?>"><i class="fi fi-rr-arrow-up-from-square"></i> Phiếu xuất</a>
						<a href="index.php?page=inventory" class="<?= ($page === 'inventory') ? 'active' : '' ?>"><i class="fi fi-rr-inventory"></i> Tồn kho</a>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</nav>
	</aside>

	<section class="admin-main">
	<?php
		// Include the content file based on the page parameter
		$page_file = __DIR__ . '/' . $page . '.php';
		if (file_exists($page_file)) {
				include $page_file;
		} else {
				echo "<h2>Page not found</h2><p>The requested page '{$page}' could not be found.</p>";
		}
	?>
	</section>
</div>

<!-- Include site-wide javascript -->
<script src="../assets/js/site.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault(); // Ngăn thẻ <a> chuyển trang
            // Toggle 'open' class on the button
            this.classList.toggle('open');
            // Find the next element sibling (the submenu) and toggle its display
            const submenu = this.nextElementSibling;
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
    });
});
</script>
