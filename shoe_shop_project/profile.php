<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// require login
if (!is_logged_in()) {
    flash_set('info', 'Please login to view your profile.');
    header('Location: login.php');
    exit;
}

$db = get_db();
$userId = current_user_id();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $errors = [];
    if (empty($name)) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';

    // Check if email is already taken by another user
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        $errors[] = 'This email address is already in use.';
    }

    // Password update logic
    $password_sql_part = '';
    $password_params = [];
    if (!empty($password)) {
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        } else {
            $password_sql_part = ', password = ?';
            $password_params[] = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET name = ?, email = ?, phone = ? {$password_sql_part} WHERE id = ?";
            $params = array_merge([$name, $email, $phone], $password_params, [$userId]);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            flash_set('success', 'Your profile has been updated successfully.');
        } catch (Exception $e) {
            flash_set('error', 'Could not update your profile. Please try again.');
        }
    } else {
        foreach ($errors as $error) {
            flash_set('error', $error);
        }
    }
    header('Location: profile.php');
    exit;
}

// Fetch current user data
$stmt = $db->prepare('SELECT name, email, phone FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<div class="profile-page">
    <h2>My Profile</h2>
    <?php if ($m = flash_get('error')): echo "<div class='alert-error'>$m</div>"; endif; ?>
    <?php if ($m = flash_get('success')): echo "<div class='alert-success'>$m</div>"; endif; ?>

    <form class="profile-form" method="post">
        <h3>Personal Information</h3>
        <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required></div>
        <div class="form-group"><label for="phone">Phone</label><input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"></div>

        <h3>Change Password</h3>
        <p class="form-hint">Leave blank to keep your current password.</p>
        <div class="form-group"><label for="password">New Password</label><input type="password" id="password" name="password"></div>
        <div class="form-group"><label for="password_confirm">Confirm New Password</label><input type="password" id="password_confirm" name="password_confirm"></div>

        <div class="form-actions"><button type="submit" class="btn">Save Changes</button></div>
    </form>
</div>

<style>
    .profile-page {
        max-width: 700px;
        margin: 40px auto;
        padding: 20px;
    }
    .profile-page h2 {
        text-align: center;
        margin-bottom: 30px;
        color: var(--text-dark);
        font-weight: 700;
    }
    .profile-form {
        background: var(--bg-white);
        padding: 30px;
        border-radius: 12px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border);
    }
    .profile-form h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--primary);
        margin-top: 20px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
    }
    .profile-form .form-group {
        margin-bottom: 20px;
    }
    .profile-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-body);
    }
    .profile-form input[type="text"],
    .profile-form input[type="email"],
    .profile-form input[type="tel"],
    .profile-form input[type="password"] {
        width: 100%;
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--bg-light);
        font-size: 1rem;
        transition: box-shadow .2s, border-color .2s;
    }
    .profile-form input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light);
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>