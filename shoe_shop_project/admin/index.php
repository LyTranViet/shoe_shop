<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

$db = get_db();

$page = $_GET['page'] ?? (is_admin() ? 'dashboard' : 'orders');

// --- Xử lý các file handler trước khi check quyền ---
if (in_array($page, ['handle_stock_in', 'handle_stock_out'])) {
    include __DIR__ . '/' . $page . '.php';
    exit; 
}

// --- Xử lý POST/DELETE cho các trang trước khi xuất header ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ORDERS ---
    if ($page === 'orders' && isset($_POST['update_status'])) {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $status_id = (int)($_POST['status_id'] ?? 0);
        if ($order_id > 0 && $status_id > 0) {
            try {
                $stmt = $db->prepare("UPDATE orders SET status_id = ? WHERE id = ?");
                $stmt->execute([$status_id, $order_id]);
                flash_set('success', 'Cập nhật trạng thái đơn hàng thành công!');
                header('Location: index.php?page=orders&action=view&id=' . $order_id);
                exit;
            } catch (PDOException $e) {
                flash_set('error', 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage());
            }
        }
    }
    // --- PRODUCTS ---
    if ($page === 'products') {
        $id = (int)($_POST['id'] ?? 0);
        $form_action = $_POST['form_action'] ?? 'add';

        // The full processing logic is complex, involving file uploads and transactions.
        // We will let products.php handle the logic but prevent it from sending headers.
        // This is a temporary measure. The full logic should be moved here for a cleaner architecture.
        // For now, we just need to prevent the header() call in products.php.
        // The actual fix will be removing the header() call from products.php.
    }
    // --- SUPPLIERS ---
    if ($page === 'suppliers') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            flash_set('error', "Tên nhà cung cấp không được để trống.");
        } else {
            try {
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $address = trim($_POST['address'] ?? '');
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE supplier SET supplierName = ?, Sdt = ?, Address = ?, Email = ? WHERE supplier_id = ?");
                    $stmt->execute([$name, $phone, $address, $email, $id]);
                    flash_set('success', "Cập nhật nhà cung cấp thành công!");
                } else {
                    $stmt = $db->prepare("INSERT INTO supplier (supplierName, Sdt, Address, Email) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $phone, $address, $email]);
                    flash_set('success', "Thêm nhà cung cấp thành công!");
                }
                header("Location: index.php?page=suppliers");
                exit;
            } catch (PDOException $e) {
                flash_set('error', "Lỗi cơ sở dữ liệu: " . $e->getMessage());
            }
        }
    }
    // --- BANNERS ---
    if ($page === 'banners') {
        // Banner handling is complex due to file uploads.
        // The logic will be kept in banners.php for now, but the header() call will be removed.
        // This prevents the "headers already sent" error.        
        if (isset($_POST['csrf_token'])) {
            if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                flash_set('error', "Lỗi xác thực (CSRF token không hợp lệ). Vui lòng thử lại.");
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $link = trim($_POST['link'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $current_image = $_POST['current_image'] ?? '';
                $image_url = $current_image;

                if (empty($title)) {
                    flash_set('error', "Tiêu đề không được để trống.");
                } else {
                    // Handle file upload without validation
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'banners' . DIRECTORY_SEPARATOR;
                        $upload_dir_url = 'assets/images/banners/';
                        if (!is_dir($upload_dir_path)) {
                            mkdir($upload_dir_path, 0777, true);
                        }
                        $filename = uniqid() . '-' . basename($_FILES['image']['name']);
                        $target_file = $upload_dir_path . $filename;

                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            // Delete old image if a new one is uploaded
                            if (!empty($current_image)) {
                                $old_image_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $current_image);
                                if (file_exists($old_image_path)) {
                                    @unlink($old_image_path);
                                }
                            }
                            $image_url = $upload_dir_url . $filename;
                        }
                    }

                    try {
                        if ($id > 0) { // Update
                            $stmt = $db->prepare("UPDATE banners SET title = ?, image_url = ?, link = ?, is_active = ? WHERE id = ?");
                            $stmt->execute([$title, $image_url, $link, $is_active, $id]);
                            flash_set('success', "Cập nhật banner thành công!");
                        } else { // Insert
                            $stmt = $db->prepare("INSERT INTO banners (title, image_url, link, is_active) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$title, $image_url, $link, $is_active]);
                            flash_set('success', "Thêm banner mới thành công!");
                        }
                        header("Location: index.php?page=banners");
                        exit;
                    } catch (PDOException $e) {
                        flash_set('error', "Lỗi cơ sở dữ liệu: " . $e->getMessage());
                    }
                }
            }
        }
    }
    // --- CATEGORIES ---
    if ($page === 'categories') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($name)) {
            flash_set('error', 'Tên danh mục không được để trống.');
        } else {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
                    $stmt->execute([$name, $description, $id]);
                    flash_set('success', "Cập nhật danh mục thành công!");
                } else {
                    $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    flash_set('success', "Thêm danh mục thành công!");
                }
                header("Location: index.php?page=categories");
                exit;
            } catch (PDOException $e) {
                flash_set('error', "Lỗi cơ sở dữ liệu: " . $e->getMessage());
            }
        }
    }
    // --- BRANDS ---
    if ($page === 'brands') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (empty($name)) {
            flash_set('error', "Tên thương hiệu không được để trống.");
        } else {
            try {
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE brands SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    flash_set('success', "Cập nhật thương hiệu thành công!");
                } else {
                    $stmt = $db->prepare("INSERT INTO brands (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    flash_set('success', "Thêm thương hiệu thành công!");
                }
                header("Location: index.php?page=brands");
                exit;
            } catch (PDOException $e) {
                flash_set('error', "Lỗi cơ sở dữ liệu: " . $e->getMessage());
            }
        }
    }
    // --- COUPONS ---
    if ($page === 'coupons') {
        $id = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $discount_percent = (int)($_POST['discount_percent'] ?? 0);
        $valid_from = $_POST['valid_from'] ?? '';
        $valid_to = $_POST['valid_to'] ?? '';
        $usage_limit = (int)($_POST['usage_limit'] ?? 0);
        if (empty($code) || $discount_percent <= 0 || empty($valid_from) || empty($valid_to)) {
            flash_set('error', 'Vui lòng điền đầy đủ các trường bắt buộc.');
        } else {
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
                flash_set('error', "Lỗi cơ sở dữ liệu: " . $e->getMessage());
            }
        }
    }
}

// --- Xử lý POST/DELETE cho trang users.php trước khi xuất header ---
if ($page === 'users') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = (int)($_POST['role_id'] ?? 0);

        if (empty($name) || empty($email) || $role_id <= 0) {
            flash_set('error', 'Vui lòng nhập đầy đủ Họ tên, Email và chọn Vai trò.');
        } elseif ($id === 0 && empty($password)) {
            flash_set('error', 'Mật khẩu là bắt buộc khi thêm mới.');
        } else {
            $db->beginTransaction();
            try {
                if ($id > 0) {
                    $params = [$name, $email];
                    $sql = "UPDATE users SET name=?, email=?";
                    if (!empty($password)) {
                        $sql .= ", password=?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $sql .= " WHERE id=?";
                    $params[] = $id;
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $stmt_role = $db->prepare("UPDATE user_roles SET role_id = ? WHERE user_id = ?");
                    $stmt_role->execute([$role_id, $id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $id = $db->lastInsertId();
                    $stmt_role = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt_role->execute([$id, $role_id]);
                }
                $db->commit();
                header('Location: index.php?page=users');
                exit;
            } catch (PDOException $e) {
                $db->rollBack();
                flash_set('error', "Lỗi cơ sở dữ liệu: " . $e->getMessage());
            }
        }
    }
}

// --- Xử lý DELETE cho các trang ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = (int)$_GET['id'];
    if ($id_to_delete > 0) {
        try {
            if ($page === 'categories') {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id_to_delete]);
                header("Location: index.php?page=categories&msg=" . urlencode("Đã xóa danh mục thành công!"));
                exit;
            }
            if ($page === 'brands') {
                $stmt = $db->prepare("DELETE FROM brands WHERE id = ?");
                $stmt->execute([$id_to_delete]);
                header("Location: index.php?page=brands&msg=" . urlencode("Đã xóa thương hiệu thành công!"));
                exit;
            }
            if ($page === 'coupons') {
                $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$id_to_delete]);
                header('Location: index.php?page=coupons');
                exit;
            }
            if ($page === 'users') {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id_to_delete]);
                header('Location: index.php?page=users');
                exit;
            }
            if ($page === 'products') {
                // Note: This is a simple delete. For a real-world app, you'd also delete associated images and sizes.
                $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id_to_delete]);
                header('Location: index.php?page=products&msg=' . urlencode("Đã xóa sản phẩm thành công!"));
                exit;
            }
        } catch (PDOException $e) {
            flash_set('error', "Không thể xóa mục này vì có dữ liệu liên quan.");
        }
    }
}

require_once __DIR__ . '/../includes/header_admin.php';

// --- Phân quyền ---
$is_superadmin = is_superadmin();
$is_admin = is_admin();
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.admin-topbar .search form');
    const searchInput = searchForm ? searchForm.querySelector('input[name="q"]') : null;

    if (searchForm && searchInput) {
        // Từ điển các từ khóa và trang tương ứng
        const adminPageKeywords = {
            'dashboard': ['dashboard', 'tổng quan', 'báo cáo'],
            'orders': ['đơn hàng', 'đơn', 'order'],
            'products': ['sản phẩm', 'sp', 'hàng hóa', 'product'],
            'coupons': ['mã giảm giá', 'coupon', 'khuyến mãi', 'voucher'],
            'categories': ['danh mục', 'loại sản phẩm', 'category'],
            'brands': ['thương hiệu', 'nhãn hàng', 'brand'],
            'customers': ['khách hàng', 'khách', 'customer'],
            'users': ['nhân viên', 'người dùng', 'user', 'tài khoản'],
            'suppliers': ['nhà cung cấp', 'ncc', 'supplier'],
            'stock_in': ['phiếu nhập', 'nhập kho', 'nhập hàng', 'stock in'],
            'stock_out': ['phiếu xuất', 'xuất kho', 'xuất hàng', 'stock out'],
            'inventory': ['tồn kho', 'kho', 'inventory'],
            'banners': ['banner', 'quảng cáo'],
            'contacts': ['liên hệ', 'phản hồi', 'contact']
        };

        searchForm.addEventListener('submit', function(event) {
            const query = searchInput.value.trim().toLowerCase();

            for (const pageName in adminPageKeywords) {
                if (adminPageKeywords[pageName].some(keyword => query.includes(keyword))) {
                    event.preventDefault(); // Ngăn form submit theo cách thông thường
                    window.location.href = `index.php?page=${pageName}`; // Chuyển hướng đến trang tương ứng
                    return;
                }
            }
            // Nếu không có từ khóa nào khớp, để form submit bình thường
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.querySelector('.admin-tools .search-form'); // Assuming this is the search form
    const searchInput = searchForm ? searchForm.querySelector('input[name="q"]') : null;

    if (searchForm && searchInput) {
        const adminPageKeywords = {
            'dashboard': ['dashboard', 'tổng quan'],
            'orders': ['đơn hàng', 'đơn', 'order'],
            'products': ['sản phẩm', 'sp', 'hàng hóa', 'product'],
            'coupons': ['mã giảm giá', 'coupon', 'khuyến mãi', 'voucher'],
            'categories': ['danh mục', 'loại sản phẩm', 'category'],
            'brands': ['thương hiệu', 'nhãn hàng', 'brand'],
            'customers': ['khách hàng', 'khách', 'customer'],
            'users': ['nhân viên', 'người dùng', 'user'],
            'suppliers': ['nhà cung cấp', 'ncc', 'supplier'],
            'stock_in': ['phiếu nhập', 'nhập kho', 'nhập hàng', 'stock in'],
            'stock_out': ['phiếu xuất', 'xuất kho', 'xuất hàng', 'stock out'],
            'inventory': ['tồn kho', 'kho', 'inventory'],
            'banners': ['banner', 'quảng cáo'],
            'contacts': ['liên hệ', 'phản hồi', 'contact']
        };

        searchForm.addEventListener('submit', function(event) {
            const query = searchInput.value.trim().toLowerCase();

            for (const pageName in adminPageKeywords) {
                if (adminPageKeywords[pageName].some(keyword => query.includes(keyword))) {
                    event.preventDefault(); // Prevent default form submission
                    window.location.href = `index.php?page=${pageName}`;
                    return;
                }
            }
            // If no specific page keyword is found, let the form submit normally
        });
    }
});
</script>
