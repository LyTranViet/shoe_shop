<?php
ob_start(); // giữ lại để đảm bảo không có output
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';
$db = get_db();

$vnp_HashSecret = '3W1G21VMGLI30U99923AM5JF1A9RE7K1';

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

        // 2. Lấy các sản phẩm trong đơn hàng để tạo phiếu xuất
        $itemsStmt = $db->prepare('
            SELECT oi.product_id, oi.size, oi.quantity, oi.price, ps.id as productsize_id
            FROM order_items oi
            JOIN product_sizes ps ON oi.product_id = ps.product_id AND oi.size = ps.size
            WHERE oi.order_id = ?
        ');
        $itemsStmt->execute([$vnp_TxnRef]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Tự động tạo phiếu xuất kho
        $exportCode = 'PX-ORD' . $vnp_TxnRef;
        $exportNote = 'Tự động tạo cho đơn hàng VNPAY #' . $vnp_TxnRef;
        $exportStmt = $db->prepare("INSERT INTO export_receipt (receipt_code, export_type, status, employee_id, total_amount, note, order_id) VALUES (?, 'Bán hàng', 'Đang xử lý', ?, ?, ?, ?)");
        $exportStmt->execute([$exportCode, $order['user_id'], $order['total_amount'], $exportNote, $vnp_TxnRef]);
        $export_id = $db->lastInsertId();

        foreach ($items as $item) {
            $quantity_to_export = (int)$item['quantity'];
            $productsize_id = $item['productsize_id'];

            $batchesStmt = $db->prepare("SELECT id, quantity_remaining FROM product_batch WHERE productsize_id = ? AND quantity_remaining > 0 ORDER BY import_date ASC");
            $batchesStmt->execute([$productsize_id]);

            $quantity_left_to_deduct = $quantity_to_export;
            while ($quantity_left_to_deduct > 0 && ($batch = $batchesStmt->fetch())) {
                $deduct_from_this_batch = min($quantity_left_to_deduct, (int)$batch['quantity_remaining']);

                $db->prepare("INSERT INTO export_receipt_detail (export_id, batch_id, productsize_id, quantity, price) VALUES (?, ?, ?, ?, ?)")->execute([$export_id, $batch['id'], $productsize_id, $deduct_from_this_batch, (float)$item['price']]);
                $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining - ? WHERE id = ?")->execute([$deduct_from_this_batch, $batch['id']]);
                $db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ?")->execute([$deduct_from_this_batch, $productsize_id]);
                $quantity_left_to_deduct -= $deduct_from_this_batch;
            }
        }

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
