<?php
require_once __DIR__ . '/includes/init.php';
$db = $db; // already set in init.php

// ========== Helper: Captcha ==========
function generate_login_captcha()
{
    $a = rand(1, 9);
    $b = rand(1, 9);
    $op = rand(0, 1) ? '+' : '-';
    $ans = $op === '+' ? $a + $b : $a - $b;
    $_SESSION['login_captcha_q'] = "$a $op $b";
    $_SESSION['login_captcha'] = (string)$ans;
    return $_SESSION['login_captcha_q'];
}

// ========== Captcha logic ==========
if (isset($_GET['regen'])) $captcha_q = generate_login_captcha();
elseif (empty($_SESSION['login_captcha'])) $captcha_q = generate_login_captcha();
else $captcha_q = $_SESSION['login_captcha_q'];

$email = $_GET['e'] ?? '';
$shake = false;

// ========== Handle POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pwd = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');

    if ($captcha === '' || $captcha !== ($_SESSION['login_captcha'] ?? '')) {
        flash_set('error', 'Captcha không hợp lệ, vui lòng thử lại.');
        $shake = true;
        $captcha_q = generate_login_captcha();
    } else {
        unset($_SESSION['login_captcha'], $_SESSION['login_captcha_q']);
        $st = $db->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && password_verify($pwd, $u['password'])) {
            $_SESSION['user_id'] = $u['id'];

            // Merge wishlist từ session vào DB
            if (!empty($_SESSION['wishlist'])) {
                foreach ($_SESSION['wishlist'] as $pid) {
                    try {
                        $ins = $db->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)');
                        $ins->execute([$u['id'], $pid]);
                    } catch (PDOException $e) { /* bỏ qua lỗi nhỏ */ }
                }
                unset($_SESSION['wishlist']);
            }

            // Hợp nhất giỏ hàng từ session vào DB
            if (!empty($_SESSION['cart'])) {
                $st_cart = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
                $st_cart->execute([$u['id']]);
                $cartId = $st_cart->fetchColumn();
                if (!$cartId) {
                    $ins_cart = $db->prepare('INSERT INTO carts (user_id) VALUES (?)');
                    $ins_cart->execute([$u['id']]);
                    $cartId = $db->lastInsertId();
                }

                foreach ($_SESSION['cart'] as $sessionKey => $item) {
                    // Sử dụng logic tương tự như trong cart.php để thêm/cập nhật sản phẩm
                    $pid = (int)$item['product_id'];
                    $qty = (int)$item['quantity'];
                    $size = $item['size'] ?? null;
                    
                    add_or_update_cart_item($db, $cartId, $pid, $qty, $size);
                }
                unset($_SESSION['cart']); // Xóa giỏ hàng của khách
            }

            flash_set('success', 'Đăng nhập thành công!');

            // Chuyển hướng dựa trên vai trò người dùng
            // Admin và Staff sẽ được chuyển thẳng đến trang quản trị.
            // SuperAdmin sẽ được chuyển đến trang chủ của người dùng và có thể truy cập cả hai khu vực.
            if (is_admin() || is_staff()) {
                header('Location: ' . BASE_URL . 'admin/');
            } elseif (isset($_SESSION['return_to'])) {
                $return_url = $_SESSION['return_to'];
                unset($_SESSION['return_to']); // Xóa session sau khi sử dụng
                header('Location: ' . $return_url);
            } else {
                // SuperAdmin và Customer sẽ được chuyển đến trang chủ
                header('Location: ' . BASE_URL . 'index.php');
            }
            exit;
        } else {
            flash_set('error', 'Email hoặc mật khẩu không đúng.');
            $shake = true;
            $captcha_q = generate_login_captcha();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.form-auth {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(120deg, #f8fafc 0%, #e0e7ef 100%);
  font-family: 'Inter', sans-serif;
}
.auth-card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 18px;
  padding: 38px 32px 32px 32px;
  box-shadow: 0 8px 32px rgba(15,23,42,0.13);
  border: 1px solid #e3e8ee;
  position: relative;
  margin: 32px 0;
  transition: box-shadow .2s, transform .2s;
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
  content: '\1F511';
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


<div class="form-auth">
  <div class="auth-card<?php echo $shake ? ' shake' : ''; ?>" id="authCard">
    <div class="auth-head">
      <h2>Đăng nhập</h2>
      <div class="auth-sub">Chào mừng trở lại — vui lòng đăng nhập để tiếp tục</div>
    </div>
    <?php if ($msg = flash_get('error')): ?>
      <div class="alert error"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('success')): ?>
      <div class="alert success"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <form method="post" class="auth-form" novalidate>
      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" class="input" required placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
      </div>
      <div class="form-row">
        <label for="password">Mật khẩu</label>
        <div class="row-inline">
          <input id="password" name="password" type="password" required class="input" placeholder="••••••••">
          <button type="button" class="show-pass" id="togglePass" title="Hiện/Ẩn mật khẩu">👁️</button>
        </div>
        <div class="helper">Ít nhất 8 ký tự, phân biệt hoa thường.</div>
      </div>
      <div class="form-row">
        <label for="captcha">Captcha</label>
        <div class="captcha-box">
          <span class="captcha-pill" aria-hidden="true">🔢 <?php echo htmlspecialchars($captcha_q); ?> = ?</span>
          <input id="captcha" name="captcha" type="text" inputmode="numeric" class="input captcha-input" placeholder="Kết quả">
          <a href="?regen=1" class="captcha-refresh small" id="regenBtn" title="Làm mới captcha">↻</a>
        </div>
        <div class="helper">Giải phép toán nhỏ để tiếp tục.</div>
      </div>
      <div class="form-row row-inline" style="justify-content:space-between;align-items:center;">
        <label class="helper"><input type="checkbox" name="remember"> Ghi nhớ đăng nhập</label>
        <a href="<?php echo BASE_URL; ?>forgot.php" class="helper">Quên mật khẩu?</a>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Đăng nhập</button>
        <a class="btn secondary small" href="<?php echo BASE_URL; ?>register.php">Tạo tài khoản</a>
      </div>
      <div class="auth-divider"><span>hoặc</span></div>
      <div class="helper" style="text-align:center;">Tiếp tục với tư cách khách — <a href="<?php echo BASE_URL; ?>index.php">Vào cửa hàng</a></div>
    </form>
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
  // Preserve email when clicking regen
  var regen = document.getElementById('regenBtn');
  if (regen) {
    regen.addEventListener('click', function(e){
      var email = encodeURIComponent(document.getElementById('email').value || '');
      var href = '?regen=1';
      if (email) href += '&e=' + email;
      regen.setAttribute('href', href);
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
