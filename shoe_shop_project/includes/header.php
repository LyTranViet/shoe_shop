<?php

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/init.php'; // Nạp init.php trước để có BASE_URL và các hàm khác

// --- Access Control ---
// Chỉ SuperAdmin mới có thể truy cập cả trang người dùng và trang quản trị.
// Admin và Staff sẽ bị chuyển hướng về trang quản trị nếu cố gắng truy cập trang người dùng.
if (is_logged_in() && (is_admin() || is_staff()) && !is_superadmin()) {
    // Chuyển hướng về trang quản trị
    header('Location: ' . rtrim(BASE_URL, '/') . '/admin/');
    exit;
}

// Define BASE_URL if it's not already defined (e.g., by init.php)
// Đã được định nghĩa trong init.php, không cần định nghĩa lại.

// Database connection
$db = get_db();
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
        }
    } catch (PDOException $e) {
        // If DB query fails, fallback to a default role for logged-in user
        $_SESSION['user_role'] = 'user';
    }
} else {
    $_SESSION['user_role'] = 'guest';
}

$basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH), '/');
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
    <!-- FIX jQuery: Load từ CDN ổn định, version 3.6.0 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <!-- Nếu CDN fail, fallback local (tải jquery-3.6.0.min.js về thư mục js/) -->
    <script>window.jQuery || document.write('<script src="js/jquery-3.6.0.min.js"><\/script>')</script>
    <script>window.siteBasePath = '<?php echo $basePath; ?>';</script>
    <script src="assets/js/chat.js" defer></script>
    <script src="assets/js/site.js" defer></script>

    <style>
        :root {
            /* === PRIMARY COLORS === */
            --primary: #38bdf8;
            --primary-dark: #0c8ad8;
            --primary-light: #bae6fd;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;

            /* === NEUTRAL COLORS === */
            --bg-white: #ffffff;
            --bg-light: #f8f9fa;
            --bg-gray: #f1f5f9;
            --text-dark: #1a202c;
            --text-body: #4a5568;
            --text-muted: #6c757d;
            --border: #e2e8f0;
            --border-dark: #cbd5e1;

            /* === SEMANTIC COLORS === */
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;

            /* === SHADOWS === */
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-gray);
            margin: 0;
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* 🔹 Top Bar */
        .top-bar {
            background: var(--text-dark);
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
            background: var(--bg-white);
            box-shadow: var(--shadow-sm);
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
            color: var(--text-dark);
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .brand span { color: var(--accent); }
        .brand .logo { font-size: 2rem; }

        /* Search Bar */
        .search-form {
            display: flex;
            border: 1px solid var(--border);
            border-radius: 50px;
            overflow: hidden;
            background: var(--bg-light);
            position: relative; /* Cần thiết cho hộp kết quả */
            transition: box-shadow 0.3s;
        }
        .search-form:hover { box-shadow: var(--shadow-sm); }
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
        .search-form button:hover { background: var(--primary-dark); }

        /* Nav Actions */
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .nav-actions a {
            text-decoration: none;
            color: var(--text-dark);
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
            color: var(--text-dark);
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
            background: var(--bg-white);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            min-width: 200px;
            animation: fadeIn 0.3s ease;
        }
        .dropdown-menu a, .dropdown-menu a:visited {
            padding: 0.8rem 1rem;
            text-decoration: none;
            color: var(--text-dark);
            transition: background 0.2s;
        }
        .dropdown-menu a:hover { background: var(--bg-light); }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- AJAX Search Results --- */
        .search-results-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-white);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 12px 12px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none; /* Ẩn mặc định */
        }
        .search-result-item {
            display: flex;
            align-items: center;
            padding: 10px;
            gap: 15px;
            text-decoration: none !important;
            color: var(--text-body);
            border-bottom: 1px solid var(--bg-gray);
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item:hover, .search-result-item:focus {
            background-color: #f8f9fa;
        }
        .search-result-item img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }
        .search-result-info {
            flex-grow: 1;
        }
        .search-result-name {
            font-weight: 600;
            margin: 0;
            color: var(--text-dark);
        }
        .search-result-category {
            color: var(--text-muted);
            font-size: 0.85em;
            margin-top: 2px;
        }
        .search-result-price {
            color: var(--primary);
            font-size: 0.95em;
            margin-top: 4px;
        }
        .search-results-box .loading,
        .search-results-box .no-results {
            padding: 20px;
            text-align: center;
            color: #888;
        }

        /* Custom button for consistent login/register style */
        .btn-gradient {
            background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%);
            color: #fff !important; /* Use !important to override Bootstrap specificity */
            border: none;
        }

        /* === Pagination Styles === */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 2.5rem;
        }
        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 10px;
            background: var(--bg-white);
            color: var(--text-dark);
            border: 1px solid var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .pagination a:hover { background: var(--primary-light); border-color: var(--primary); }
        .pagination a.current { background: var(--primary); color: var(--bg-white); font-weight: 700; border-color: var(--primary); }
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
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <div class="logo">👟</div>
                <div>Púp<span>Bờ Si</span></div>
            </a>

            <form class="search-form" action="<?php echo BASE_URL; ?>category.php" method="GET">
                <input type="text" name="q" id="ajax-search-input" placeholder="Tìm kiếm sản phẩm, thương hiệu..." required autocomplete="off">
                <button type="submit">🔍</button>
                <div class="search-results-box" id="search-results-container">
                    <!-- Kết quả AJAX sẽ được chèn vào đây -->
                </div>
            </form>

            <div class="nav-actions">
                <a href="<?php echo BASE_URL; ?>category.php">🏷️ Danh mục</a>
                <a href="<?php echo BASE_URL; ?>about.php">ℹ️ Giới thiệu</a>
                <a href="<?php echo BASE_URL; ?>contact.php">📞 Liên hệ</a>
                <a href="<?php echo BASE_URL; ?>cart.php">🛒 Giỏ hàng <span class="badge"><?php echo cart_count(); ?></span></a>
                <?php if ($isLoggedIn): ?>  
                <div class="user-menu">
                    <button class="user-btn"><?php echo htmlspecialchars($displayName); ?> ⬇️</button>
                    <div class="dropdown-menu">
                        <a href="<?php echo BASE_URL; ?>profile.php">👤 Hồ sơ</a>
                        <a href="<?php echo BASE_URL; ?>order_history.php">📦 Đơn hàng</a>
                        <a href="<?php echo BASE_URL; ?>wishlist.php">❤️ Yêu thích</a>
                        <?php if (is_superadmin()): ?>
                            <a href="<?php echo BASE_URL; ?>admin/index.php" style="color:var(--primary);font-weight:600;">
                                ⚙️ Quản trị
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>logout.php" style="color:var(--danger);font-weight:600;">🚪 Đăng xuất</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-gradient rounded-pill px-3 fw-semibold">Đăng nhập</a>
                <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-gradient rounded-pill px-3 fw-semibold">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main class="container-fluid" style="flex-grow: 1;">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('ajax-search-input');
    const resultsContainer = document.getElementById('search-results-container');
    let searchTimeout;

    if (!searchInput || !resultsContainer) return;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout); // Hủy bỏ yêu cầu trước đó

        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }

        resultsContainer.style.display = 'block';
        resultsContainer.innerHTML = '<div class="loading">Đang tìm kiếm...</div>';

        searchTimeout = setTimeout(() => {
            fetch(`api_search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    resultsContainer.innerHTML = ''; // Xóa kết quả cũ
                    if (data.length > 0) {
                        data.forEach(item => {
                            const resultItem = document.createElement('a');
                            resultItem.href = item.url;
                            resultItem.className = 'search-result-item';

                            const priceFormatted = new Intl.NumberFormat('vi-VN').format(item.price) + '₫';

                            resultItem.innerHTML = `
                                <img src="${item.image_url}" alt="${item.name}">
                                <div class="search-result-info">
                                    <div class="search-result-name">${item.name}</div>
                                    <div class="search-result-category">${item.category_name || ''}</div>
                                    <div class="search-result-price">${priceFormatted}</div>
                                </div>
                            `;
                            resultsContainer.appendChild(resultItem);
                        });
                    } else {
                        resultsContainer.innerHTML = '<div class="no-results">Không tìm thấy kết quả nào.</div>';
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    resultsContainer.innerHTML = '<div class="no-results">Lỗi khi tìm kiếm.</div>';
                });
        }, 300); // Chờ 300ms sau khi người dùng ngừng gõ
    });

    // Ẩn kết quả khi click ra ngoài
    document.addEventListener('click', function(event) {
        if (!searchInput.contains(event.target) && !resultsContainer.contains(event.target)) {
            resultsContainer.style.display = 'none';
        }
    });

    // Hiển thị lại kết quả khi focus vào input
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length > 1 && resultsContainer.innerHTML.trim() !== '') {
            resultsContainer.style.display = 'block';
        }
    });
});
</script>