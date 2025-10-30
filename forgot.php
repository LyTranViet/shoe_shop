<?php
require_once __DIR__ . '/includes/init.php';
$db = get_db();
$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Địa chỉ email không hợp lệ.');
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // User exists, generate a token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // Token expires in 1 hour

            try {
                // Store token in the database
                $ins_stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $ins_stmt->execute([$email, $token, $expires]);

                // Simulate sending email
                $reset_link = BASE_URL . 'reset_password.php?token=' . $token;
                $email_body = "Để đặt lại mật khẩu, vui lòng nhấp vào liên kết sau: <a href='$reset_link'>$reset_link</a>";
                
                // For demonstration, we'll store the link in a flash message.
                flash_set('email_simulation', $email_body);

            } catch (PDOException $e) {
                // Handle DB error
            }
        }
        // Always show a generic success message to prevent user enumeration
        flash_set('success', 'Nếu email của bạn tồn tại trong hệ thống, bạn sẽ nhận được một liên kết để đặt lại mật khẩu.');
        $show_form = false;
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
.email-simulation {
    background-color: #e9f5ff;
    border: 1px solid #bde0fe;
    padding: 15px;
    border-radius: 8px;
    margin-top: 1rem;
    word-wrap: break-word;
}
</style>

<div class="form-auth">
  <div class="auth-card">
    <div class="auth-head">
      <h2>Quên mật khẩu</h2>
    </div>
    <?php if ($m = flash_get('error')): ?><div class="alert error"><?= htmlspecialchars($m) ?></div><?php endif; ?>
    <?php if ($m = flash_get('success')): ?><div class="alert success"><?= htmlspecialchars($m) ?></div><?php endif; ?>

    <?php if ($show_form): ?>
    <p>Nhập địa chỉ email của bạn. Chúng tôi sẽ gửi cho bạn một liên kết để đặt lại mật khẩu.</p>
    <form method="post">
      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" class="input" required placeholder="you@example.com">
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Gửi liên kết</button>
        <a href="login.php" class="btn secondary small">Quay lại đăng nhập</a>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($m = flash_get('email_simulation')): ?>
        <div class="email-simulation"><strong>Mô phỏng email:</strong><br><?= $m ?></div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>