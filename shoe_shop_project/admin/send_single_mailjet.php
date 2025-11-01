<?php
require __DIR__ . '/../vendor/autoload.php';use \Mailjet\Resources;

// 🔑 THÔNG TIN MAILJET CỦA BẠN
$MJ_APIKEY_PUBLIC = 'f2e2402ae342abb7278d543d42a02c08';
$MJ_APIKEY_PRIVATE = '1051dffcf5b6976dcb8b7f6916a4159f';

// 💌 XỬ LÝ GỬI MAIL KHI NGƯỜI DÙNG NHẤN "GỬI"
$status = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $to_name = trim($_POST['name']);
    $to_email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        $status = "<div class='alert alert-danger mt-3'>⚠️ Email không hợp lệ!</div>";
    } else {
        $mj = new \Mailjet\Client($MJ_APIKEY_PUBLIC, $MJ_APIKEY_PRIVATE, true, ['version' => 'v3.1']);

        $body = [
            'Messages' => [[
                'From' => [
                    'Email' => "Tranvankhanh2k4@gmail.com", // Email của bạn (đã xác minh trên Mailjet)
                    'Name' => "Shoe Shop"
                ],
                'To' => [[
                    'Email' => $to_email,
                    'Name' => $to_name
                ]],
                'Subject' => $subject,
                'HTMLPart' => "
                    <div style='font-family:Arial,sans-serif;font-size:16px;'>
                        <h2 style='color:#ff6600;'>🔥 $subject</h2>
                        <p>$message</p>
                        <p>🛍️ <a href='https://yourshop.com' target='_blank' style='color:#ff6600;text-decoration:none;'>Truy cập cửa hàng ngay!</a></p>
                        <hr>
                        <small style='color:#888;'>Shoe Shop - Cảm ơn bạn đã quan tâm!</small>
                    </div>"
            ]]
        ];

        $response = $mj->post(Resources::$Email, ['body' => $body]);

        if ($response->success()) {
            $data = $response->getData();
            $msg_id = $data['Messages'][0]['To'][0]['MessageID'] ?? '(Không có ID)';
            $status = "<div class='alert alert-success mt-3'>✅ Email đã gửi thành công tới <b>$to_email</b><br>Message ID: <b>$msg_id</b></div>";
        } else {
            $status = "<div class='alert alert-danger mt-3'>❌ Gửi thất bại: " . json_encode($response->getBody()) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Gửi Email Khuyến Mãi - Shoe Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
    <div class="container py-5">
        <div class="card shadow-lg p-4 mx-auto" style="max-width:600px; background-color:#222;">
            <h2 class="text-center text-warning mb-4">📨 Gửi Email Khuyến Mãi</h2>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Tên người nhận</label>
                    <input type="text" name="name" class="form-control" required placeholder="VD: Phan Le Minh">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email người nhận</label>
                    <input type="email" name="email" class="form-control" required placeholder="VD: example@gmail.com">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tiêu đề</label>
                    <input type="text" name="subject" class="form-control" value="🎉 Giảm giá 30% toàn bộ sản phẩm!">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nội dung khuyến mãi</label>
                    <textarea name="message" rows="4" class="form-control">Giảm giá 30% cho toàn bộ sản phẩm - Áp dụng đến hết tuần này!</textarea>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold">🚀 Gửi Email</button>
            </form>

            <?php if ($status) echo $status; ?>
        </div>
    </div>
</body>
</html>
