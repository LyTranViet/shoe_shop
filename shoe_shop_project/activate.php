<?php
require_once __DIR__ . '/includes/init.php';
$db = get_db();

$token = $_GET['token'] ?? '';

if (empty($token)) {
    flash_set('error', 'Token kích hoạt không hợp lệ.');
    header('Location: login.php');
    exit;
}

try {
    // Tìm token trong CSDL
    $stmt = $db->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $request = $stmt->fetch();

    // Bỏ kiểm tra thời gian hết hạn, chỉ cần token tồn tại là được.
    // Dòng cũ: if (!$request || strtotime($request['expires_at']) <= time()) {
    if (!$request) {
        flash_set('error', 'Token kích hoạt không hợp lệ hoặc đã hết hạn.');
        header('Location: login.php');
        exit;
    }

    // Lấy ID người dùng từ email
    $user_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $user_stmt->execute([$request['email']]);
    $user = $user_stmt->fetch();

    if (!$user) {
        flash_set('error', 'Không tìm thấy tài khoản để kích hoạt.');
        header('Location: login.php');
        exit;
    }
    $userId = $user['id'];

    // Bắt đầu transaction
    $db->beginTransaction();

    // 1. Kích hoạt tài khoản
    $update_stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE email = ?");
    $update_stmt->execute([$request['email']]);

    // 2. Xóa token đã sử dụng
    $delete_stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
    $delete_stmt->execute([$request['email']]);

    // 3. Đăng nhập cho người dùng
    $_SESSION['user_id'] = $userId;

    // 4. Hợp nhất giỏ hàng từ session vào DB (giống logic của login.php)
    if (!empty($_SESSION['cart'])) {
        $st_cart = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
        $st_cart->execute([$userId]);
        $cartId = $st_cart->fetchColumn();
        if (!$cartId) {
            $ins_cart = $db->prepare('INSERT INTO carts (user_id) VALUES (?)');
            $ins_cart->execute([$userId]);
            $cartId = $db->lastInsertId();
        }
        foreach ($_SESSION['cart'] as $item) {
            add_or_update_cart_item($db, $cartId, (int)$item['product_id'], (int)$item['quantity'], $item['size'] ?? null);
        }
        unset($_SESSION['cart']);
    }

    $db->commit();

    flash_set('success', 'Tài khoản đã được kích hoạt. Chào mừng bạn!');
    header('Location: index.php'); // Chuyển hướng về trang chủ
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    flash_set('error', 'Đã xảy ra lỗi trong quá trình kích hoạt. Vui lòng thử lại.');
    header('Location: login.php');
    exit;
}