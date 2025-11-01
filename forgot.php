<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/vendor/autoload.php'; // Nạp PHPMailer và các thư viện khác

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = get_db();

// --- Cấu hình PHPMailer với SMTP của Gmail ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'lyviettran01@gmail.com'); // Email Gmail của bạn
define('SMTP_PASSWORD', 'akfh swgt kosz tpgc'); // THAY THẾ BẰNG MẬT KHẨU ỨNG DỤNG 16 KÝ TỰ CỦA BẠN
define('SMTP_PORT', 587); // Hoặc 465 nếu bạn dùng SMTPS
define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS); // Hoặc PHPMailer::ENCRYPTION_SMTPS
define('SENDER_NAME', 'Shoe Shop');

// Quản lý các bước bằng session
$step = $_SESSION['reset_step'] ?? 'enter_email';

/**
 * Hàm gửi mã OTP qua Mailjet
 */
function send_otp_email($email, $otp) {
    $mail = new PHPMailer(true);
    try {
        // Cấu hình Server
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Người gửi và người nhận
        $mail->setFrom(SMTP_USERNAME, SENDER_NAME);
        $mail->addAddress($email);

        // Nội dung
        $mail->isHTML(true);
        $mail->Subject = "Mã OTP đặt lại mật khẩu của bạn";
        $mail->Body    = "
                <div style='font-family:Arial,sans-serif;font-size:16px;'>
                    <h3>Yêu cầu đặt lại mật khẩu</h3>
                    <p>Mã OTP của bạn là: <b style='font-size:20px; color:#ff6600;'>$otp</b></p>
                    <p>Mã này sẽ hết hạn sau 2 phút. Vui lòng không chia sẻ mã này với bất kỳ ai.</p>
                    <hr><small style='color:#888;'>Shoe Shop</small>
                </div>";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        // Trả về thông báo lỗi chi tiết từ PHPMailer
        return ['success' => false, 'message' => "Lỗi PHPMailer: {$mail->ErrorInfo}"];
    }
}

/**
 * Hàm tạo và lưu mã OTP
 */
function generate_and_store_otp($db, $email) {
    $otp = rand(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + 120); // Hết hạn sau 2 phút

    // Xóa OTP cũ nếu có
    $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
    // Lưu OTP mới vào bảng password_resets
    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $otp, $expires]);

    return $otp;
}

// Xử lý logic theo từng bước
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Bước 1: Gửi email chứa OTP
    if ($action === 'send_otp') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Địa chỉ email không hợp lệ.');
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $otp = generate_and_store_otp($db, $email);
                $send_result = send_otp_email($email, $otp); // Gọi hàm mới
                if ($send_result['success']) { // Kiểm tra kết quả
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_step'] = 'verify_otp';
                    flash_set('success', 'Mã OTP đã được gửi đến email của bạn.');
                    header('Location: forgot.php');
                    exit;
                } else {
                    // Hiển thị lỗi chi tiết từ PHPMailer
                    flash_set('error', 'Lỗi gửi email: ' . htmlspecialchars($send_result['message']));
                }
            } else {
                flash_set('error', 'Email không tồn tại trong hệ thống.');
            }
        }
    }

    // Bước 2: Xác thực OTP
    elseif ($action === 'verify_otp') {
        $otp_input = trim($_POST['otp'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';

        if (empty($otp_input) || empty($email)) {
            flash_set('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            $_SESSION['reset_step'] = 'enter_email';
        } else {
            $stmt = $db->prepare("SELECT token, expires_at FROM password_resets WHERE email = ? AND token = ?");
            $stmt->execute([$email, $otp_input]);
            $reset_request = $stmt->fetch();

            if ($reset_request && strtotime($reset_request['expires_at']) > time()) {
                $_SESSION['reset_step'] = 'reset_password';
                // Xóa token sau khi xác thực thành công để tránh dùng lại
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                flash_set('success', 'Xác thực OTP thành công. Vui lòng nhập mật khẩu mới.');
            } else {
                flash_set('error', 'Mã OTP không chính xác hoặc đã hết hạn.');
            }
        }
    }

    // Bước 3: Đặt lại mật khẩu
    elseif ($action === 'reset_password') {
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $email = $_SESSION['reset_email'] ?? '';

        if (empty($email)) {
            flash_set('error', 'Phiên làm việc đã hết hạn. Vui lòng thử lại.');
            $_SESSION['reset_step'] = 'enter_email';
        } elseif (empty($password) || strlen($password) < 6) {
            flash_set('error', 'Mật khẩu phải có ít nhất 6 ký tự.');
        } elseif ($password !== $password_confirm) {
            flash_set('error', 'Mật khẩu xác nhận không khớp.');
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->execute([$hashed_password, $email]);

            unset($_SESSION['reset_step'], $_SESSION['reset_email']);
            flash_set('success', 'Mật khẩu của bạn đã được đặt lại thành công. Vui lòng đăng nhập.');
            header('Location: login.php');
            exit;
        }
    }

    // Gửi lại mã OTP
    elseif ($action === 'resend_otp') {
        $email = $_SESSION['reset_email'] ?? '';
        if (!empty($email)) {
            $otp = generate_and_store_otp($db, $email);
            $send_result = send_otp_email($email, $otp); // Gọi hàm mới
            if ($send_result['success']) {
                flash_set('success', 'Mã OTP mới đã được gửi lại.');
            } else {
                flash_set('error', 'Lỗi gửi lại mã: ' . htmlspecialchars($send_result['message'])); // Hiển thị lỗi mới
            }
        } else {
            flash_set('error', 'Phiên làm việc đã hết hạn.');
            $_SESSION['reset_step'] = 'enter_email';
        }
    }

    // Quay lại bước nhập email
    elseif ($action === 'back_to_email') {
        unset($_SESSION['reset_step'], $_SESSION['reset_email']);
        header('Location: forgot.php');
        exit;
    }
}

// Cập nhật lại step sau khi xử lý POST
$step = $_SESSION['reset_step'] ?? 'enter_email';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.auth-card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 18px;
  padding: 38px 32px 32px 32px;
  box-shadow: 0 8px 32px rgba(15,23,42,0.13);
  border: 1px solid #e3e8ee;
  margin: 48px auto; /* Căn giữa form sau khi bỏ form-auth */
}
.auth-head {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  margin-bottom: 22px;
}
.auth-head h2 {
  margin: 0 0 2px 0;
  font-size: 2rem;
  color: #0f1724;
  font-weight: 800;
  letter-spacing: -1px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.auth-head h2::before {
  content: '🔑';
  font-size: 1.5rem;
  margin-right: 2px;
}
.auth-sub {
  margin: 0 0 0 2px;
  color: #64748b;
  font-size: 1rem;
  font-weight: 500;
}
.form-row { margin-bottom: 20px; }
.form-row label { display: block; font-weight: 700; margin-bottom: 7px; color: #334155; font-size: 15px; letter-spacing: 0.1px; }
.input {
  width: 100%;
  padding: 13px 15px;
  border-radius: 10px;
  border: 1.5px solid #e6eefb;
  background: #f8fafc;
  font-size: 16px;
  box-sizing: border-box;
  transition: box-shadow .13s, border-color .13s;
}
.input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 2px #bae6fd;
}
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  background: linear-gradient(90deg,#0ea5ff 60%,#2563eb 100%);
  color: #fff;
  border: 0;
  cursor: pointer;
  font-weight: 700;
  font-size: 1rem;
  box-shadow: 0 2px 8px rgba(14,165,233,0.07);
  transition: background 0.18s;
}
.btn.secondary {
  background: #64748b;
  color: #fff;
  font-weight: 600;
}
.small { padding: 8px 12px; font-size: 14px; }
.alert {
  padding: 13px 16px;
  border-radius: 9px;
  margin-bottom: 18px;
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
}
.alert.error {
  background: #fff1f2;
  color: #b91c1c;
  border: 1.5px solid #fecaca;
}
.alert.error::before { content: '\26A0'; font-size: 1.3em; }
.alert.success {
  background: #ecfdf5;
  color: #047857;
  border: 1.5px solid #bbf7d0;
}
.alert.success::before { content: '\2714'; font-size: 1.2em; }
.form-actions {
  margin-top: 28px;
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: flex-start;
}
</style>

<div>
    <div class="auth-card">
        <div class="auth-head">
            <h2>Quên mật khẩu</h2>
            <div class="auth-sub">Lấy lại quyền truy cập vào tài khoản của bạn</div>
        </div>
        <?php if ($m = flash_get('error')): ?><div class="alert error"><?= htmlspecialchars($m) ?></div><?php endif; ?>
        <?php if ($m = flash_get('success')): ?><div class="alert success"><?= htmlspecialchars($m) ?></div><?php endif; ?>

        <?php if ($step === 'enter_email'): ?>
            <p class="auth-sub" style="margin-bottom: 20px;">Nhập địa chỉ email của bạn. Chúng tôi sẽ gửi mã OTP để đặt lại mật khẩu.</p>
            <form method="post">
                <input type="hidden" name="action" value="send_otp">
                <div class="form-row">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="input" required placeholder="you@example.com">
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Gửi mã OTP</button>
                    <a href="login.php" class="btn secondary small">Quay lại</a>
                </div>
            </form>

        <?php elseif ($step === 'verify_otp'): ?>
            <p class="auth-sub" style="margin-bottom: 20px;">Mã OTP đã được gửi đến <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong>. Vui lòng nhập mã vào ô bên dưới.</p>
            <form method="post">
                <input type="hidden" name="action" value="verify_otp">
                <div class="form-row">
                    <label for="otp">Mã OTP</label>
                    <input id="otp" name="otp" type="text" class="input" required inputmode="numeric" pattern="\d{6}">
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Xác nhận</button>
            </form>
            <form method="post" style="margin-left: 10px;">
                <input type="hidden" name="action" value="resend_otp">
                <button type="submit" class="btn secondary small">Gửi lại mã</button>
            </form>
            <form method="post" style="margin-left: 10px;">
                <input type="hidden" name="action" value="back_to_email">
                <button type="submit" class="btn secondary small">Quay lại</button>
            </form>
            </div>

        <?php elseif ($step === 'reset_password'): ?>
            <p class="auth-sub" style="margin-bottom: 20px;">Xác thực thành công. Vui lòng nhập mật khẩu mới cho tài khoản <strong><?= htmlspecialchars($_SESSION['reset_email']) ?></strong>.</p>
            <form method="post">
                <input type="hidden" name="action" value="reset_password">
                <div class="form-row">
                    <label for="password">Mật khẩu mới</label>
                    <input id="password" name="password" type="password" class="input" required>
                </div>
                <div class="form-row">
                    <label for="password_confirm">Xác nhận mật khẩu mới</label>
                    <input id="password_confirm" name="password_confirm" type="password" class="input" required>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Lưu mật khẩu</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>