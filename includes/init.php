<?php
// Khởi tạo session và các biến người dùng, KHÔNG xuất HTML
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';

$db = get_db();
$isLoggedIn = is_logged_in();
$displayName = 'Guest';
if ($isLoggedIn) {
    try {
        $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([current_user_id()]);
        $displayName = $stmt->fetchColumn() ?: 'User';
    } catch (Exception $e) { /* ignore */ }
}
// Cấu hình base path cho JS dùng AJAX
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '') $basePath = '/shoe-store';
// KHÔNG xuất bất kỳ ký tự nào ở đây!
