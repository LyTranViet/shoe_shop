<?php
// Admin header — safe to include from admin pages
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';

$db = null;
if (function_exists('get_db')) {
	try { $db = get_db(); } catch (Exception $e) { /* ignore */ }
}

$displayName = 'Khách';
$roleDisplay = 'Khách';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');

if (is_logged_in() && $db) {
	try {
		$st = $db->prepare('SELECT name FROM users WHERE id = ?');
		$st->execute([current_user_id() ?? 0]);
		$user = $st->fetch();
		if ($user && !empty($user['name'])) $displayName = $user['name'];
	} catch (Exception $e) { /* ignore */ }

	if (function_exists('get_user_roles')) {
		$userRoles = get_user_roles();
		if (!empty($userRoles)) $roleDisplay = implode(', ', array_map('ucfirst', $userRoles));
	}
}

// Notifications count
$notifCount = 0;
if ($db) {
	try {
		$q = $db->prepare('SELECT COUNT(*) FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0');
		$q->execute([current_user_id()]);
		$notifCount = (int)$q->fetchColumn();
	} catch (Exception $e) { /* ignore */ }
}
?>
<!doctype html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Admin - Púp Bờ Si Shoes</title>
	<link rel="stylesheet" href="../assets/css/site.css">
	<link rel="stylesheet" href="../assets/css/admin.css">
	<link rel='stylesheet' href='https://cdn-uicons.flaticon.com/2.4.2/uicons-regular-rounded/css/uicons-regular-rounded.css'>
</head>
<body>

<div class="admin-topbar">
	<div class="topbar-inner">

		<!-- Logo -->
		<a href="index.php" class="brand">
			<div class="logo">👟</div>
			<div>Púp<span>Bờ Si Admin</span></div>
		</a>

		<!-- Search -->
		<div class="search">
			<?php
				$current_page = $_GET['page'] ?? 'dashboard';
				$search_placeholder = 'Tìm kiếm trong ' . ucfirst($current_page) . '...';
				$search_query = htmlspecialchars($_GET['q'] ?? '');
			?>
			<form action="index.php" method="get">
				<input type="hidden" name="page" value="<?php echo htmlspecialchars($current_page); ?>">
				<input type="search" name="q" placeholder="<?php echo $search_placeholder; ?>" value="<?php echo $search_query; ?>">
				<button type="submit"><i class="fi fi-rr-search"></i></button>
			</form>
		</div>

		<!-- User / Actions -->
		<div class="topbar-right">
			<?php if (is_superadmin()): ?>
				<a href="<?php echo $basePath; ?>/index.php" class="btn home-btn"><i class="fi fi-rr-home"></i> Trang chủ</a>
			<?php endif; ?>
			<div class="user-menu">
				<button class="user-name-btn">
					<i class="fi fi-rr-user"></i> <?php echo htmlspecialchars($displayName); ?> <i class="fi fi-rr-angle-small-down"></i>
				</button>
				<div class="user-dropdown">
					<div class="user-info">
						<strong><?php echo htmlspecialchars($displayName); ?></strong>
						<div class="muted"><?php echo htmlspecialchars($roleDisplay); ?></div>
					</div>
					<div class="user-actions">
						<a href="<?php echo $basePath; ?>/logout.php?redirect=home" class="logout-btn"><i class="fi fi-rr-sign-out-alt"></i> Đăng xuất</a>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<main class="admin-container">
