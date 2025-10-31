<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

$db = get_db();

// --- Router Logic ---
$page = $_GET['page'] ?? (is_admin() ? 'dashboard' : 'orders');

// --- Xử lý các file handler trước khi check quyền ---
if (in_array($page, ['handle_stock_in', 'handle_stock_out'])) {
    include __DIR__ . '/' . $page . '.php';
    exit; 
}

require_once __DIR__ . '/../includes/header_admin.php';

// --- Phân quyền ---
$is_superadmin = is_superadmin();
$is_admin = is_admin() && !$is_superadmin;
$is_staff = is_staff();

$superadmin_pages = ['dashboard', 'orders', 'products', 'coupons', 'categories', 'brands', 'customers', 'users', 'suppliers', 'stock_in', 'stock_out', 'inventory', 'banners', 'contacts', 'send_single_mailjet'];
$admin_pages      = ['dashboard', 'orders', 'products', 'coupons', 'categories', 'brands', 'customers', 'users', 'suppliers', 'stock_in', 'stock_out', 'inventory', 'banners', 'contacts', 'send_single_mailjet'];
$staff_pages      = ['orders', 'customers', 'products'];

if ($is_superadmin) {
    $allowed_pages = $superadmin_pages;
} elseif ($is_admin) {
    $allowed_pages = $admin_pages;
} else {
    $allowed_pages = $staff_pages;
}

// --- Kiểm tra quyền truy cập ---
if (!in_array($page, $allowed_pages, true)) {
    flash_set('error', 'Bạn không có quyền truy cập trang này.');
    header('Location: index.php?page=' . ($is_staff ? 'orders' : 'dashboard'));
    exit;
}
?>

<div class="admin-layout">
    <aside class="admin-nav">
        <nav>
            <!-- Tổng quan -->
            <div class="nav-section">
                <h4 class="nav-section-title"><i class="fi fi-rr-chart-pie-alt"></i> Tổng quan</h4>
                <?php if ($is_superadmin || $is_admin): ?>
                    <a href="index.php?page=dashboard" class="<?= ($page === 'dashboard') ? 'active' : '' ?>">
                        <i class="fi fi-rr-dashboard"></i> Dashboard
                    </a>
                <?php endif; ?>
            </div>

            <!-- Đơn hàng -->
            <div class="nav-section">
                <h4 class="nav-section-title"><i class="fi fi-rr-shopping-cart"></i> Quản lý Đơn hàng</h4>
                <a href="index.php?page=orders" class="<?= ($page === 'orders') ? 'active' : '' ?>">
                    <i class="fi fi-rr-receipt"></i> Đơn hàng
                </a>
            </div>

            <!-- Sản phẩm -->
            <div class="nav-section">
                <h4 class="nav-section-title"><i class="fi fi-rr-box"></i> Quản lý Sản phẩm</h4>
                <?php if ($is_superadmin || $is_admin || $is_staff): ?>
                    <a href="index.php?page=products" class="<?= ($page === 'products') ? 'active' : '' ?>">
                        <i class="fi fi-rr-boxes"></i> Sản phẩm
                    </a>
                <?php endif; ?>
                <?php if ($is_superadmin || $is_admin): ?>
                    <a href="index.php?page=categories" class="<?= ($page === 'categories') ? 'active' : '' ?>">
                        <i class="fi fi-rr-folder-open"></i> Danh mục
                    </a>
                    <a href="index.php?page=brands" class="<?= ($page === 'brands') ? 'active' : '' ?>">
                        <i class="fi fi-rr-tags"></i> Thương hiệu
                    </a>
                    <a href="index.php?page=banners" class="<?= ($page === 'banners') ? 'active' : '' ?>">
                        <i class="fi fi-rr-picture"></i> Banner
                    </a>
                    <a href="index.php?page=coupons" class="<?= ($page === 'coupons') ? 'active' : '' ?>">
                        <i class="fi fi-rr-ticket"></i> Mã giảm giá
                    </a>
                <?php endif; ?>
            </div>

            <!-- Người dùng -->
            <div class="nav-section">
                <h4 class="nav-section-title"><i class="fi fi-rr-users"></i> Quản lý Người dùng</h4>
                <?php if ($is_superadmin || $is_admin || $is_staff): ?>
                    <a href="index.php?page=customers" class="<?= ($page === 'customers') ? 'active' : '' ?>">
                        <i class="fi fi-rr-user-check"></i> Khách hàng
                    </a>
                <?php endif; ?>
                <?php if ($is_superadmin || $is_admin): ?>
                    <a href="index.php?page=users" class="<?= ($page === 'users') ? 'active' : '' ?>">
                        <i class="fi fi-rr-users-alt"></i> Nhân viên
                    </a>
                <?php endif; ?>
            </div>

            <!-- Liên hệ -->
            <?php if ($is_superadmin || $is_admin): ?>
                <div class="nav-section">
                    <h4 class="nav-section-title"><i class="fi fi-rr-envelope"></i> Liên hệ</h4>
                    <a href="index.php?page=contacts" class="<?= ($page === 'contacts') ? 'active' : '' ?>">
                        <i class="fi fi-rr-inbox"></i> Liên hệ khách hàng
                    </a>
                     <a href="index.php?page=send_single_mailjet" class="<?= ($page === 'send_single_mailjet') ? 'active' : '' ?>">
                        <i class="fi fi-rr-inbox"></i> Gửi Mmail Khuyến Mãi
                    </a>
                </div>
            <?php endif; ?>
            <!-- Kho & Nhà cung cấp -->
            <?php if ($is_superadmin || $is_admin): ?>
                <div class="nav-section">
                    <h4 class="nav-section-title"><i class="fi fi-rr-warehouse"></i> Quản lý Kho & NCC</h4>
                    <a href="index.php?page=suppliers" class="<?= ($page === 'suppliers') ? 'active' : '' ?>">
                        <i class="fi fi-rr-supplier"></i> Nhà cung cấp
                    </a>

                    <div class="nav-item-with-submenu">
                        <a href="#" class="submenu-toggle <?= in_array($page, ['stock_in', 'stock_out', 'inventory']) ? 'active open' : '' ?>">
                            <span><i class="fi fi-rr-dolly-flatbed"></i> Kho trữ</span>
                            <span class="arrow">›</span>
                        </a>
                        <div class="submenu" style="<?= in_array($page, ['stock_in', 'stock_out', 'inventory']) ? 'display: block;' : '' ?>">
                            <a href="index.php?page=stock_in" class="<?= ($page === 'stock_in') ? 'active' : '' ?>">
                                <i class="fi fi-rr-document"></i> Phiếu nhập
                            </a>
                            <a href="index.php?page=stock_out" class="<?= ($page === 'stock_out') ? 'active' : '' ?>">
                                <i class="fi fi-rr-arrow-up-from-square"></i> Phiếu xuất
                            </a>
                            <a href="index.php?page=inventory" class="<?= ($page === 'inventory') ? 'active' : '' ?>">
                                <i class="fi fi-rr-inventory"></i> Tồn kho
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </aside>

    <section class="admin-main">
        <?php
        $page_file = __DIR__ . '/' . $page . '.php';
        if (file_exists($page_file)) {
            include $page_file;
        } else {
            echo "<h2>Page not found</h2><p>The requested page '{$page}' could not be found.</p>";
        }
        ?>
    </section>
</div>

<script src="../assets/js/site.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            this.classList.toggle('open');
            const submenu = this.nextElementSibling;
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
    });
});
</script>
