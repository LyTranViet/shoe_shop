<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';

// Database connection
$db = get_db();

// Default values
$isLoggedIn = is_logged_in();
$displayName = 'Guest';
$userRole = 'guest';

// Get user info if logged in
if ($isLoggedIn) {
    try {
        $stmt = $db->prepare("
            SELECT u.name, r.name AS role_name
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([current_user_id()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $displayName = $user['name'] ?? 'User';
            $userRole = strtolower($user['role_name'] ?? 'user');
            $_SESSION['user_role'] = $userRole;
        } else {
            $_SESSION['user_role'] = 'user';
        }
    } catch (PDOException $e) {
        $_SESSION['user_role'] = 'user';
    }
} else {
    $_SESSION['user_role'] = 'guest';
}

// Base path
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '') $basePath = '/shoe_shop-main/shoe_shop-main';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Púp Bờ Si - Premium Shoes Store</title>
    <meta name="description" content="Khám phá giày cao cấp tại Púp Bờ Si - xu hướng mới nhất cho nam, nữ và trẻ em.">

    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/2.4.2/uicons-regular-rounded/css/uicons-regular-rounded.css'>
    <link rel="stylesheet" href="assets/css/site.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    
    <script>window.siteBasePath = '<?php echo $basePath; ?>';</script>
    <script src="assets/js/chat.js" defer></script>

    <style>
        :root {
            --primary: #007bff;
            --dark: #111;
            --light: #f8f9fa;
            --gray: #6c757d;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            margin: 0;
            color: var(--dark);
        }

        /* 🔹 Top Bar */
        .top-bar {
            background: var(--dark);
            color: #eee;
            font-size: 0.9rem;
            padding: 0.3rem 0;
        }
        .top-bar a {
            color: #ddd;
            text-decoration: none;
            margin-right: 1rem;
        }
        .top-bar a:hover { color: white; }

        /* 🔹 Header */
        header {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .header-main {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1rem;
        }

        /* Logo */
        .brand {
            text-decoration: none;
            color: var(--dark);
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .brand span { color: var(--primary); }
        .brand .logo { font-size: 2rem; }

        /* Search Bar */
        .search-form {
            display: flex;
            border: 1px solid #ddd;
            border-radius: 50px;
            overflow: hidden;
            background: #fafafa;
            transition: box-shadow 0.3s;
        }
        .search-form:hover { box-shadow: 0 0 6px rgba(0,0,0,0.1); }
        .search-form input {
            border: none;
            flex: 1;
            padding: 0.6rem 1rem;
            font-size: 1rem;
            outline: none;
            background: transparent;
        }
        .search-form button {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.6rem 1.5rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .search-form button:hover { background: #0056b3; }

        /* Nav Actions */
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .nav-actions a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: 0.3s;
        }
        .nav-actions a:hover { color: var(--primary); }

        /* Badge */
        .badge {
            background: var(--primary);
            color: white;
            border-radius: 10px;
            padding: 0.15rem 0.6rem;
            font-size: 0.8rem;
        }

        /* Dropdown user */
        .user-menu {
            position: relative;
        }
        .user-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        .user-menu:hover .dropdown-menu {
            display: flex;
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 120%;
            right: 0;
            flex-direction: column;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            min-width: 200px;
            animation: fadeIn 0.3s ease;
        }
        .dropdown-menu a {
            padding: 0.8rem 1rem;
            text-decoration: none;
            color: var(--dark);
            transition: background 0.2s;
        }
        .dropdown-menu a:hover { background: var(--light); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <!-- 🔹 Top Bar -->
    <div class="top-bar text-center text-md-start">
        <div class="container d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <a href="tel:+1234567890">📞 123-456-7890</a>
                <a href="mailto:support@pupbosi.com">✉️ support@pupbosi.com</a>
            </div>
            <div>
                <a href="#">FB</a>
                <a href="#">IG</a>
                <a href="#">TW</a>
            </div>
        </div>
    </div>

    <!-- 🔹 Header -->
    <header>
        <div class="header-main">
            <a href="index.php" class="brand">
                <div class="logo">👟</div>
                <div>Púp<span>Bờ Si</span></div>
            </a>

            <form class="search-form" action="search.php" method="GET">
                <input type="text" name="q" placeholder="Tìm kiếm sản phẩm, thương hiệu..." required>
                <button type="submit">🔍</button>
            </form>

            <div class="nav-actions">
                <a href="category.php">🏷️ Danh mục</a>
                <a href="wishlist.php">❤️ Yêu thích</a>
                <a href="cart.php">🛒 Giỏ hàng <span class="badge"><?php echo cart_count(); ?></span></a>

                <?php if ($isLoggedIn): ?>
                <div class="user-menu">
                    <button class="user-btn"><?php echo htmlspecialchars($displayName); ?> ⬇️</button>
                    <div class="dropdown-menu">
                        <a href="profile.php">👤 Hồ sơ</a>
                        <a href="order_history.php">📦 Đơn hàng</a>
                        <a href="wishlist.php">❤️ Yêu thích</a>
                        <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'supperadmin' || $_SESSION['user_role'] === 'staff'): ?>
                            <a href="/shoe_shop-main/shoe_shop-main/admin/index.php" style="color:#0d6efd;font-weight:600;">
                                ⚙️ Quản trị
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" style="color:#dc3545;font-weight:600;">🚪 Đăng xuất</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="login.php" class="btn btn-primary rounded-pill px-3 fw-semibold">Đăng nhập</a>
                <a href="register.php" class="btn btn-outline-primary rounded-pill px-3 fw-semibold">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
</body>
</html>
<?php ob_end_flush(); ?>
