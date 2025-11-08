<?php
ob_start(); // giữ lại để đảm bảo không có output
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';
$db = get_db();

$vnp_HashSecret = 'UPP5TZKX60XTIKA60DRSV6HHE847PKS5';

// Debug: log all incoming parameters
error_log('VNPay return params: ' . print_r($_GET, true));

// Collect vnp_ params for hash verification
$inputData = [];
foreach ($_GET as $key => $value) {
    if (strpos($key, 'vnp_') === 0) {
        $inputData[$key] = $value;
    }
}

// Get order info first
$vnp_TxnRef = $_GET['vnp_TxnRef'] ?? null;
$vnp_Amount = isset($_GET['vnp_Amount']) ? (int)$_GET['vnp_Amount'] : null;
$vnp_RespCode = $_GET['vnp_ResponseCode'] ?? null;
$vnp_SecureHash = $inputData['vnp_SecureHash'] ?? null;

// Verify hash signature
unset($inputData['vnp_SecureHash'], $inputData['vnp_SecureHashType']);
ksort($inputData);
$hashData = [];
foreach ($inputData as $key => $value) {
    $hashData[] = $key . '=' . urlencode($value); // Use urlencoded values
}
$hashString = implode('&', $hashData);
$computedHash = hash_hmac('sha512', $hashString, $vnp_HashSecret);

// Debug hash computation
error_log('Hash string: ' . $hashString);
error_log('Computed hash: ' . $computedHash);
error_log('Received hash: ' . $vnp_SecureHash);

if (!$vnp_TxnRef) {
    $_SESSION['error'] = 'Thiếu mã giao dịch';
    header('Location: /shoe_shop/shoe_shop_project/checkout.php');
    exit;
}

// Load order first to verify
$orderStmt = $db->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$orderStmt->execute([$vnp_TxnRef]);
$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = 'Không tìm thấy đơn hàng';
    header('Location: /shoe_shop/shoe_shop_project/checkout.php');
    exit;
}

// Verify hash after loading order
if ($computedHash !== $vnp_SecureHash) {
    error_log('VNPay hash mismatch for order ' . $vnp_TxnRef);
    $_SESSION['error'] = 'Chữ ký VNPay không hợp lệ';
    header('Location: /shoe_shop/shoe_shop_project/checkout.php');
    exit;
}

// Verify amount
$expectedAmount = (int)round($order['total_amount'] * 100);
if ($vnp_Amount !== $expectedAmount) {
    $_SESSION['error'] = 'Số tiền không khớp';
    header('Location: /shoe_shop/shoe_shop_project/checkout.php');
    exit;
}

// Process successful payment
if ($vnp_RespCode === '00') {
    try {
        $db->beginTransaction();

        // 1. Update order status
        $db->prepare("UPDATE orders SET status_id = 1, payment_method = 'VNPAY', paid_at = NOW() WHERE id = ?")
            ->execute([$vnp_TxnRef]);

        // 2. Clear cart in DB
        if (!empty($order['user_id'])) {
            $cartSt = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
            $cartSt->execute([$order['user_id']]);
            if ($cartId = $cartSt->fetchColumn()) {
                $db->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
            }
        }

        // 3. Clear session cart
        unset($_SESSION['cart']);

        $db->commit();

        // 4. Set success message và order_success vào session
        $_SESSION['success'] = 'Đặt hàng thành công!';
        $_SESSION['order_success'] = $vnp_TxnRef;

        // 5. Redirect về checkout.php với order_success parameter
        ob_end_clean();
        header('Location: /shoe_shop/shoe_shop_project/checkout.php?order_success=' . $vnp_TxnRef);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        error_log('VNPay error: ' . $e->getMessage());
        ob_end_clean();
        header('Location: /shoe_shop/shoe_shop_project/checkout.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Thanh toán không thành công (mã: ' . htmlspecialchars($vnp_RespCode) . ')';
    ob_end_clean();
    header('Location: /shoe_shop/shoe_shop_project/checkout.php');
    exit;
}
