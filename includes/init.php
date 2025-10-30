<?php
// Khởi tạo session và các biến người dùng, KHÔNG xuất HTML
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';
define('BASE_URL', '/shoe_shop/'); // Định nghĩa BASE_URL tại đây

$db = get_db();
$isLoggedIn = is_logged_in();
$displayName = 'Guest';
$userRole = 'guest';

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
        if ($user) { $displayName = $user['name'] ?? 'User'; $userRole = strtolower($user['role_name'] ?? 'user'); }
        $_SESSION['user_role'] = $userRole;
    } catch (Exception $e) { $_SESSION['user_role'] = 'user'; /* fallback */ }
} else {
    $_SESSION['user_role'] = 'guest';
}
// Cấu hình base path cho JS dùng AJAX
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '') $basePath = '/shoe-store';
// KHÔNG xuất bất kỳ ký tự nào ở đây!
