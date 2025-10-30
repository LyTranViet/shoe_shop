<?php
require_once __DIR__ . '/includes/init.php';
$db = get_db();

$token = $_GET['token'] ?? '';
$is_token_valid = false;
$email = null;

if (empty($token)) {
    flash_set('error', 'Token không hợp lệ hoặc đã hết hạn.');
} else {
    $stmt = $db->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();

    if ($reset_request && strtotime($reset_request['expires_at']) > time()) {
        $is_token_valid = true;
        $email = $reset_request['email'];
    } else {
        flash_set('error', 'Token không hợp lệ hoặc đã hết hạn.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_token_valid) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($password) || strlen($password) < 6) {
        flash_set('error', 'Mật khẩu phải có ít nhất 6 ký tự.');
    } elseif ($password !== $password_confirm) {
        flash_set('error', 'Mật khẩu xác nhận không khớp.');
    } else {
        try {
            $db->beginTransaction();

            // Update user's password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->execute([$hashed_password, $email]);

            // Delete the token to prevent reuse
            $delete_stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
            $delete_stmt->execute([$email]);

            $db->commit();

            flash_set('success', 'Mật khẩu của bạn đã được đặt lại thành công. Vui lòng đăng nhập.');
            header('Location: login.php');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            flash_set('error', 'Đã xảy ra lỗi. Vui lòng thử lại.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.form-auth {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
.auth-card {
  width: 100%;
  max-width: 450px;
  background: #fff;
  border-radius: 18px;
  padding: 38px 32px;
  box-shadow: 0 8px 32px rgba(15,23,42,0.13);
  border: 1px solid #e3e8ee;
}
.auth-head h2 {
  margin: 0 0 1rem 0;
  font-size: 1.8rem;
  font-weight: 800;
}
</style>

<div class="form-auth">
  <div class="auth-card">
    <div class="auth-head">
      <h2>Đặt lại mật khẩu</h2>
    </div>
    <?php if ($m = flash_get('error')): ?><div class="alert error"><?= htmlspecialchars($m) ?></div><?php endif; ?>

    <?php if ($is_token_valid): ?>
    <p>Nhập mật khẩu mới cho tài khoản: <strong><?= htmlspecialchars($email) ?></strong></p>
    <form method="post">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="form-row">
        <label for="password">Mật khẩu mới</label>
        <input id="password" name="password" type="password" class="input" required>
      </div>
      <div class="form-row">
        <label for="password_confirm">Xác nhận mật khẩu mới</label>
        <input id="password_confirm" name="password_confirm" type="password" class="input" required>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Đặt lại mật khẩu</button>
      </div>
    </form>
    <?php else: ?>
        <a href="forgot.php" class="btn">Yêu cầu liên kết mới</a>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>