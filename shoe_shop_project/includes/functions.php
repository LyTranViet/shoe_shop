<?php
// ensure session is started when functions are used directly (admin pages may include this before header.php)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db/db.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function cart_count(): int
{
    // If user is logged in, prefer DB-backed cart count (carts/cart_items)
    if (is_logged_in()) {
        try {
            $db = get_db();
            $uid = current_user_id();
            $st = $db->prepare('SELECT COALESCE(SUM(ci.quantity),0) FROM carts c JOIN cart_items ci ON c.id = ci.cart_id WHERE c.user_id = ?');
            $st->execute([$uid]);
            $count = (int)$st->fetchColumn();
            return $count;
        } catch (Exception $e) {
            // fall back to session below
        }
    }

    if (!isset($_SESSION['cart'])) return 0;
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $count += (int)($item['quantity'] ?? 1);
    }
    return $count;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) return null;
    $m = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $m;
}

// Get all roles for the current user
function get_user_roles(): array
{
    if (!is_logged_in()) return [];
    // Use a static variable to cache the roles for the duration of the request
    static $roles = null;
    if ($roles !== null) {
        return $roles;
    }

    try {
        $db = get_db();
        $st = $db->prepare('SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?');
        $st->execute([current_user_id() ?? 0]);
        $roles = $st->fetchAll(PDO::FETCH_COLUMN);
        return $roles;
    } catch (Exception $e) {
        return [];
    }
}

// Check if the current user has a specific role (case-insensitive)
function user_has_role(string $roleName): bool
{
    $userRoles = get_user_roles();
    $lowerUserRoles = array_map('strtolower', $userRoles);
    return in_array(strtolower($roleName), $lowerUserRoles, true);
}

// Check if current user has superadmin role
function is_superadmin(): bool
{
    return user_has_role('SupperAdmin'); // Corrected to 'SupperAdmin' based on SQL dump
}
// Check if current user has admin role (role name 'Admin')
function is_admin(): bool
{
    return user_has_role('Admin');
}

// Check if current user has staff role
function is_staff(): bool
{
    return user_has_role('Staff');
}

function require_admin(): void
{
    if (!is_admin()) {
        flash_set('error','You must be an admin to access that page');
        header('Location: /index.php'); exit;
    }
}

function require_admin_or_staff(): void
{
    if (!is_superadmin() && !is_admin() && !is_staff()) {
        flash_set('error', 'You do not have permission to access that page.');
        header('Location: /index.php'); exit;
    }
}

/**
 * Thêm hoặc cập nhật một sản phẩm trong giỏ hàng của người dùng đã đăng nhập.
 *
 * @param PDO $db Đối tượng kết nối PDO.
 * @param int $cartId ID của giỏ hàng.
 * @param int $productId ID của sản phẩm.
 * @param int $quantity Số lượng.
 * @param string|null $size Kích thước sản phẩm.
 */
function add_or_update_cart_item(PDO $db, int $cartId, int $productId, int $quantity, ?string $size): void
{
    try {
        $ci = $db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL)) LIMIT 1');
        $ci->execute([$cartId, $productId, $size, $size]);
        $row = $ci->fetch();
        if ($row) {
            $newQty = (int)$row['quantity'] + $quantity;
            $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $up->execute([$newQty, $row['id']]);
        } else {
            $pstmt = $db->prepare('SELECT price FROM products WHERE id = ?');
            $pstmt->execute([$productId]);
            $price = $pstmt->fetchColumn() ?: 0;
            $ins = $db->prepare('INSERT INTO cart_items (cart_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
            $ins->execute([$cartId, $productId, $size, $quantity, $price]);
        }
    } catch (PDOException $e) {
        // Có thể ghi log lỗi ở đây nếu cần
    }
}
 
