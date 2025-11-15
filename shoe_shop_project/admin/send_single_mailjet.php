<?php
require __DIR__ . '/../vendor/autoload.php';
use \Mailjet\Resources;

// 🔑 THÔNG TIN MAILJET
$MJ_APIKEY_PUBLIC = 'f2e2402ae342abb7278d543d42a02c08';
$MJ_APIKEY_PRIVATE = '1051dffcf5b6976dcb8b7f6916a4159f';

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
                'From' => ['Email' => "Tranvankhanh2k4@gmail.com", 'Name' => "Shoe Shop"],
                'To' => [['Email' => $to_email, 'Name' => $to_name]],
                'Subject' => $subject,
                'HTMLPart' => "
                    <div style='font-family:Arial;font-size:16px;'>
                        <h2 style='color:#ff6600;'>🔥 $subject</h2>
                        <p>$message</p>
                        <p>
                            🛍️ <a href='https://yourshop.com' target='_blank' style='color:#ff6600;text-decoration:none;'>
                                Truy cập cửa hàng ngay!
                            </a>
                        </p>
                        <hr>
                        <small style='color:#888;'>Shoe Shop - Cảm ơn bạn đã quan tâm!</small>
                    </div>"
            ]]
        ];

        $response = $mj->post(Resources::$Email, ['body' => $body]);

        if ($response->success()) {
            $data = $response->getData();
            $msg_id = $data['Messages'][0]['To'][0]['MessageID'] ?? '(Không có ID)';

            $status = "
                <div class='alert alert-success mt-3'>
                    ✅ Email đã gửi tới <b>$to_email</b><br>
                    Message ID: <b>$msg_id</b>
                </div>";
        } else {
            $status = "<div class='alert alert-danger mt-3'>❌ Lỗi: " . json_encode($response->getBody()) . "</div>";
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

    <style>
        body {
            background: #111;
            color: #eee;
            font-family: 'Segoe UI', sans-serif;
        }
        .card-custom {
            background: #1c1c1c;
            border: 1px solid #333;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 0 20px rgba(255, 193, 7, 0.07);
        }
        .card-custom h2 {
            font-weight: 700;
        }
        .form-control {
            background: #2a2a2a;
            border: 1px solid #444;
            color: #fff;
        }
        .form-control:focus {
            border-color: #fbc02d;
            box-shadow: 0 0 10px rgba(251, 192, 45, 0.4);
        }
        .btn-send {
            background: linear-gradient(45deg, #ffc107, #ff9800);
            border: none;
            color: #000;
            font-weight: 700;
            padding: 12px;
            border-radius: 10px;
        }
        .btn-send:hover {
            background: linear-gradient(45deg, #ffb300, #ff6f00);
            box-shadow: 0 0 15px rgba(255, 152, 0, 0.5);
        }

        /* NÚT DẪN TRANG */
        .goto-btn {
            margin-top: 25px;
            display: block;
            text-align: center;
            padding: 12px;
            font-weight: bold;
            border-radius: 10px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            transition: 0.3s;
        }
        .goto-btn:hover {
            background: #0b5ed7;
            box-shadow: 0 0 12px rgba(13, 110, 253, 0.6);
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="card-custom mx-auto" style="max-width:600px;">
            <h2 class="text-center text-warning mb-4">📨 Gửi Email Khuyến Mãi</h2>

            <form method="POST">
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

                <button type="submit" class="btn-send w-100">🚀 Gửi Email</button>
            </form>

            <?php if ($status) echo $status; ?>

            <!-- ⭐ NÚT DẪN TRANG ĐẸP -->
            <a href="https://app.mailjet.com/" class="goto-btn">🔗 Đi đến trang Chỉnh Gmail Gửi</a>
        </div>
    </div>
</body>
</html>
