<?php
// ob_start() và session_start() được xử lý trong init.php và header.php
require_once __DIR__ . '/includes/init.php'; // Bao gồm init.php trước để định nghĩa BASE_URL
require_once __DIR__ . '/vendor/autoload.php'; // Nạp PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = get_db();

// Helper: generate a simple math captcha and store in session
function generate_register_captcha()
{
    $a = rand(1, 9);
    $b = rand(1, 9);
    $ops = ['+', '-'];
    $op = $ops[array_rand($ops)];
    $answer = ($op === '+') ? ($a + $b) : ($a - $b);
    $_SESSION['register_captcha_q'] = "$a $op $b";
    $_SESSION['register_captcha'] = (string)$answer;
    return $_SESSION['register_captcha_q'];
}

/**
 * Hàm gửi email kích hoạt
 */
function send_activation_email($email, $token) {
    $mail = new PHPMailer(true);

    // Tạo URL tuyệt đối để đảm bảo hoạt động trong email
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $activation_link = $protocol . $host . BASE_URL . "activate.php?token=$token";

    try {
        // Cấu hình SMTP - **BẠN CẦN THAY THÔNG TIN CỦA MÌNH VÀO ĐÂY**
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // VD: smtp.gmail.com
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lyviettran01@gmail.com'; // Email của bạn
        $mail->Password   = 'akfh swgt kosz tpgc'; // Mật khẩu ứng dụng
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Người gửi và người nhận
        $mail->setFrom('lyviettran01@gmail.com', 'Púp Bờ Si Store');
        $mail->addAddress($email);

        // Nội dung
        $mail->isHTML(true);
        $mail->Subject = "Kích hoạt tài khoản của bạn tại Púp Bờ Si Store";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;font-size:16px;line-height:1.6;'>
                <h2>Chào mừng bạn đến với Púp Bờ Si Store!</h2>
                <p>Cảm ơn bạn đã đăng ký. Vui lòng nhấn vào nút bên dưới để kích hoạt tài khoản của bạn:</p>
                <p style='text-align:center;margin:25px 0;'>
                    <a href='$activation_link' style='background-color:#0d6efd;color:white;padding:12px 25px;text-decoration:none;border-radius:5px;font-weight:bold;'>Kích hoạt tài khoản</a>
                </p>
                <p>Trân trọng,<br>Đội ngũ Púp Bờ Si Store</p>
            </div>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Regenerate captcha on explicit request
if (isset($_GET['regen'])) {
    $captcha_question = generate_register_captcha();
}

// Ensure a captcha exists
if (empty($_SESSION['register_captcha']) || empty($_SESSION['register_captcha_q'])) {
    $captcha_question = generate_register_captcha();
} else {
    $captcha_question = $_SESSION['register_captcha_q'];
}

// Preserve posted values
$email = $_POST['email'] ?? '';
$name = $_POST['name'] ?? '';
$show_shake = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $captcha_input = trim((string)($_POST['captcha'] ?? ''));

    // Verify captcha first
    if (!isset($_SESSION['register_captcha']) || $captcha_input === '' || $captcha_input !== (string)$_SESSION['register_captcha']) {
        flash_set('error', 'Invalid captcha. Please try again.');
        $show_shake = true;
        $captcha_question = generate_register_captcha();
    } else {
        // captcha valid: remove to avoid reuse
        unset($_SESSION['register_captcha'], $_SESSION['register_captcha_q']);

        // Kiểm tra xem email đã tồn tại chưa
        $stmt_check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt_check->execute([$email]);

        if ($stmt_check->fetch()) {
            // Nếu email đã tồn tại, hiển thị thông báo lỗi
            flash_set('error', 'Email này đã tồn tại, vui lòng thử lại.');
            $show_shake = true;
            $captcha_question = generate_register_captcha();
        } else {
            // Nếu email chưa tồn tại, tiến hành đăng ký
            $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
            $activation_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 86400); // Hết hạn sau 24 giờ

            try {
                $db->beginTransaction();
                // Thêm is_active = 0 khi tạo user
                $st = $db->prepare('INSERT INTO users (email, name, password, is_active) VALUES (?, ?, ?, 0)');
                $st->execute([$email, $name, $pwd_hash]);
                $userId = $db->lastInsertId();

                // Lưu token kích hoạt vào bảng password_resets
                $token_stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $token_stmt->execute([$email, $activation_token, $expires]);

                // Gán vai trò 'Customer'
                $r = $db->prepare('SELECT id FROM roles WHERE name = ?');
                $r->execute(['Customer']);
                $role = $r->fetch();
                if (!$role) {
                    $insr = $db->prepare('INSERT INTO roles (name) VALUES (?)');
                    $insr->execute(['Customer']);
                    $roleId = $db->lastInsertId();
                } else {
                    $roleId = $role['id'];
                }
                $ur = $db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)');
                $ur->execute([$userId, $roleId]);
                $db->commit();

                // Gửi email kích hoạt
                if (send_activation_email($email, $activation_token)) {
                    flash_set('success', 'Đăng ký thành công! Vui lòng kiểm tra email để kích hoạt tài khoản.');
                } else {
                    flash_set('error', 'Đăng ký thành công nhưng không thể gửi email kích hoạt. Vui lòng liên hệ hỗ trợ.');
                }
                
                // Chuyển hướng về trang đăng nhập để họ thấy thông báo
                header('Location: login.php');
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) { $db->rollBack(); }
                flash_set('error', 'Không thể đăng ký do lỗi hệ thống. Vui lòng thử lại.');
                $show_shake = true;
                $captcha_question = generate_register_captcha();
            }
        }
    }
}
?>
<?php
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
.auth-card.shake { animation: shake 420ms cubic-bezier(.36,.07,.19,.97); }
@keyframes shake { 10%,90%{transform:translate3d(-2px,0,0)} 20%,80%{transform:translate3d(3px,0,0)} 30%,50%,70%{transform:translate3d(-6px,0,0)} 40%,60%{transform:translate3d(6px,0,0)} }
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
    content: '\1F465';
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
.row-inline { display: flex; gap: 10px; align-items: center; }
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 10px; 
    background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%);
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
.captcha-box {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-top: 2px;
}
.captcha-pill {
    padding: 11px 16px;
    background: linear-gradient(90deg,#f1f5f9 60%,#e0e7ef 100%);
    border: 1.5px solid #e6eefb;
    border-radius: 10px;
    font-weight: 800;
    color: #0f1724;
    font-size: 1.1rem;
    letter-spacing: 1px;
    min-width: 70px;
    text-align: center;
    box-shadow: 0 1px 4px rgba(100,116,139,0.04);
}
.captcha-input { flex: 1; }
.captcha-refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 10px;
    border-radius: 10px;
    border: 0;
    background: #f1f5f9;
    color: #2563eb;
    font-size: 1.2em;
    cursor: pointer;
    transition: background 0.15s;
}
.captcha-refresh:hover { background: #e0e7ef; color: #0ea5ff; }
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
.alert.error::before {
    content: '\26A0';
    font-size: 1.3em;
}
.alert.success {
    background: #ecfdf5;
    color: #047857;
    border: 1.5px solid #bbf7d0;
}
.alert.success::before {
    content: '\2714';
    font-size: 1.2em;
}
.helper {
    font-size: 13.5px;
    color: #64748b;
    margin-top: 2px;
}
.show-pass {
    cursor: pointer;
    color: #0ea5ff;
    font-weight: 700;
    background: none;
    border: none;
    font-size: 1em;
    padding: 0 8px;
}
.form-actions {
    margin-top: 28px;
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: flex-start;
}
@media (max-width:520px){
    .auth-card{ padding:16px 6px; border-radius:12px; }
    .form-auth{ padding: 16px 2px; }
}
</style>


<div aria-labelledby="register-heading">
    <div class="auth-card<?php echo $show_shake ? ' shake' : ''; ?>" id="authCard">
        <div class="auth-head">
            <h2 id="register-heading">Create your account</h2>
            <div class="auth-sub">Register to shop and track your orders</div>
        </div>
        <?php if ($m = flash_get('error')): ?>
            <div class="alert error" role="alert"><?php echo htmlspecialchars($m); ?></div>
        <?php endif; ?>
        <?php if ($m = flash_get('success')): ?>
            <div class="alert success" role="status"><?php echo htmlspecialchars($m); ?></div>
        <?php endif; ?>
        <form method="post" class="auth-form" novalidate autocomplete="off">
            <div class="form-row">
                <label for="name">Full Name</label>
                <input id="name" name="name" type="text" class="input" placeholder="Your name" value="<?php echo htmlspecialchars($name); ?>">
            </div>
            <div class="form-row">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" required class="input" placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <div class="row-inline">
                    <input id="password" name="password" type="password" required class="input" placeholder="••••••••">
                    <button type="button" class="show-pass" id="togglePass" title="Show/Hide password">👁️</button>
                </div>
                <div class="helper">At least 8 characters recommended.</div>
            </div>
            <div class="form-row">
                <label for="captcha">Captcha</label>
                <div class="captcha-box">
                    <span class="captcha-pill" aria-hidden="true">🔢 <?php echo htmlspecialchars($captcha_question); ?> = ?</span>
                    <input id="captcha" name="captcha" type="text" inputmode="numeric" pattern="[0-9\-\+]+" class="input captcha-input" placeholder="Answer">
                    <a href="?regen=1" class="captcha-refresh small" id="regenBtn" title="New captcha">↻</a>
                </div>
                <div class="helper">Solve the math question to continue.</div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Register</button>
                <a class="btn secondary small" href="<?php echo BASE_URL; ?>login.php">Sign in</a>
            </div>
        </form>
        <div class="helper" style="margin-top:18px;text-align:center;">Already have an account? <a href="<?php echo BASE_URL; ?>login.php">Sign in</a></div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function(){
    var card = document.getElementById('authCard');
    if (card && card.classList.contains('shake')) {
        card.addEventListener('animationend', function(){ card.classList.remove('shake'); });
    }
    // Toggle password visibility
    var pass = document.getElementById('password');
    var toggle = document.getElementById('togglePass');
    if (toggle && pass) {
        toggle.addEventListener('click', function(){
            if (pass.type === 'password') {
                pass.type = 'text';
            toggle.innerHTML = '<i class="fi fi-rr-eye-crossed"></i>';
            } else {
                pass.type = 'password';
            toggle.innerHTML = '<i class="fi fi-rr-eye"></i>';
            }
        });
    }
    // Preserve email/name when clicking regen
    var regen = document.getElementById('regenBtn');
    if (regen) {
        regen.addEventListener('click', function(e){
            var email = encodeURIComponent(document.getElementById('email').value || '');
            var name = encodeURIComponent(document.getElementById('name').value || '');
            var href = '?regen=1';
            if (email) href += '&email=' + email;
            if (name) href += (email ? '&' : '?') + 'name=' + name;
            regen.setAttribute('href', href);
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php';
