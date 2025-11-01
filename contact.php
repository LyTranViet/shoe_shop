<?php
include_once __DIR__ . '/includes/header.php';

// Xử lý form liên hệ
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $message) {
        try {
            $stmt = $db->prepare("INSERT INTO contacts (name, email, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $message]);
            $success = true;
        } catch (PDOException $e) {
            $error = "Không thể gửi liên hệ. Vui lòng thử lại sau.";
        }
    } else {
        $error = "Vui lòng nhập đầy đủ thông tin.";
    }
}
?>

<main class="container my-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold">📞 Liên hệ với <span class="text-primary">Púp Bờ Si</span></h1>
        <p class="text-muted">Chúng tôi luôn sẵn sàng hỗ trợ bạn 24/7.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success text-center rounded-pill fw-semibold">
            ✅ Cảm ơn bạn! Chúng tôi đã nhận được tin nhắn và sẽ phản hồi sớm nhất.
        </div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger text-center rounded-pill fw-semibold">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="row g-5 align-items-center">
        <!-- Form liên hệ -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h4 class="fw-semibold mb-4">Gửi tin nhắn cho chúng tôi</h4>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Họ tên</label>
                            <input type="text" id="name" name="name" class="form-control rounded-pill" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control rounded-pill" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Nội dung</label>
                            <textarea id="message" name="message" class="form-control rounded-4" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold w-100">
                            Gửi liên hệ ✉️
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Thông tin liên hệ -->
        <div class="col-md-6">
            <h4 class="fw-semibold mb-3">Thông tin cửa hàng</h4>
            <p><strong>Địa chỉ:</strong> 123 Đường Sneaker, Quận Thời Trang, TP. Hồ Chí Minh</p>
            <p><strong>Điện thoại:</strong> <a href="tel:+1234567890" class="text-decoration-none">123-456-7890</a></p>
            <p><strong>Email:</strong> <a href="mailto:support@pupbosi.com" class="text-decoration-none">support@pupbosi.com</a></p>

            <hr class="my-4">

            <h4 class="fw-semibold mb-3">Giờ mở cửa</h4>
            <ul class="list-unstyled">
                <li>🕘 Thứ 2 - Thứ 7: 9:00 - 21:00</li>
                <li>🌤 Chủ nhật: 10:00 - 18:00</li>
            </ul>

            <hr class="my-4">

            <h4 class="fw-semibold mb-3">Kết nối với chúng tôi</h4>
            <div class="d-flex gap-3">
                <a href="#" class="btn btn-outline-primary rounded-circle"><i class="fi fi-rr-facebook"></i></a>
                <a href="#" class="btn btn-outline-danger rounded-circle"><i class="fi fi-rr-instagram"></i></a>
                <a href="#" class="btn btn-outline-info rounded-circle"><i class="fi fi-rr-twitter"></i></a>
                            <a href="#" class="btn btn-outline-info rounded-circle"><i class="fi fi-rr-twitter"></i></a>

            </div>
        </div>
    </div>
</main>
<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d22174.870620632857!2d106.70422334854723!3d10.73445604976019!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f9f2353ffb9%3A0x6ab49da47594ce7b!2sLOTTE%20Mart%20Qu%E1%BA%ADn%207!5e0!3m2!1svi!2s!4v1761800881843!5m2!1svi!2s" width="1400" height="550" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
