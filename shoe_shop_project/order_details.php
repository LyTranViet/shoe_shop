<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    flash_set('info', 'Vui lòng đăng nhập để xem chi tiết đơn hàng.');
    header('Location: login.php');
    exit;
}

$db = get_db();
$userId = current_user_id();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: order_history.php');
    exit;
}

// LẤY ĐƠN HÀNG
$stmt = $db->prepare("
    SELECT o.*, os.name AS status_name
    FROM orders o
    JOIN order_status os ON o.status_id = os.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    flash_set('error', 'Không tìm thấy đơn hàng.');
    header('Location: order_history.php');
    exit;
}

// === LẤY DỮ LIỆU TỪ DB ===
$discountAmount         = (float)$order['discount_amount'];
$shippingDiscountAmount = (float)$order['shipping_discount_amount'];
$finalShippingFee       = (float)$order['shipping_fee'];
$originalShippingFee    = (float)($order['original_shipping_fee'] ?? $order['shipping_fee']);
$couponCode             = $order['coupon_code'] ?? '';
$shippingCouponCode     = $order['shipping_coupon_code'] ?? '';

// === TẠM TÍNH ĐÚNG: tổng sản phẩm + phí vận chuyển thực tế ===
$subtotal = $order['total_amount'] + $discountAmount + $finalShippingFee;

require_once __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng #<?php echo $orderId; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f8f8;
            color: #1a1a1a;
            line-height: 1.6;
            padding: 16px 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 6px rgba(0,0,0,0.1);
        }
        .header {
            background: #f5f5f5;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .header h1 { font-size: 1.4rem; font-weight: 600; }
        .status { font-weight: 600; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .panel { padding: 20px; border-bottom: 1px solid #eee; }
        .panel:last-child { border-bottom: none; }
        .panel-title { font-size: 1.15rem; font-weight: 600; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #eee; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; font-size: 0.95rem; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-weight: 500; color: #555; margin-bottom: 4px; }
        .info-value { font-weight: 600; }
        .info-total { grid-column: 1 / -1; padding-top: 12px; border-top: 1px dashed #ddd; margin-top: 8px; font-size: 1.1rem; }
        .total-label { font-weight: 600; }
        .total-value { font-size: 1.3rem; font-weight: 700; }
        .address-box { background: #f9f9f9; padding: 16px; border-radius: 8px; font-size: 0.95rem; line-height: 1.7; border: 1px solid #eee; }
        .order-items-list { display: flex; flex-direction: column; gap: 16px; margin-top: 16px; }
        .order-item { display: flex; gap: 16px; padding-bottom: 16px; border-bottom: 1px dashed #eee; }
        .order-item:last-child { border-bottom: none; padding-bottom: 0; }
        .item-image { width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; flex-shrink: 0; }
        .item-details { flex: 1; }
        .item-name { font-weight: 600; margin-bottom: 6px; display: block; text-decoration: none; color: #1a1a1a; }
        .item-name:hover { text-decoration: underline; }
        .item-meta { font-size: 0.9rem; color: #555; }
        .item-price { font-weight: 600; white-space: nowrap; font-size: 1.05rem; }
        .order-summary { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.95rem; }
        .summary-row.discount-row { color: #d32f2f; }
        .summary-row.shipping-discount { color: #2e8b57; }
        .summary-row.total { font-weight: 600; font-size: 1.1rem; padding-top: 10px; border-top: 1px dashed #ddd; margin-top: 10px; }
        .action-buttons { padding: 20px; text-align: center; background: #f9f9f9; }
        .btn { display: inline-block; padding: 10px 24px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: #333; font-weight: 500; font-size: 0.95rem; }
        .btn:hover { background: #f0f0f0; }
        @media (max-width: 600px) {
            .info-grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; }
            .order-item { flex-direction: column; }
            .item-image { width: 100%; height: 120px; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>Đơn hàng #<?php echo $orderId; ?></h1>
        <div class="status"><?php echo htmlspecialchars($order['status_name']); ?></div>
    </div>

    <div class="panel">
        <div class="panel-title">Thông tin đơn hàng</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Ngày đặt hàng</div>
                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Phương thức thanh toán</div>
                <div class="info-value"><?php echo htmlspecialchars($order['payment_method'] ?: '—'); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Phí vận chuyển</div>
                <div class="info-value">
                    <?php if ($finalShippingFee > 0): ?>
                        <?php if ($finalShippingFee < $originalShippingFee): ?>
                            <del style="color:#999; font-size:0.9em;"><?php echo number_format($originalShippingFee, 0); ?>₫</del>
                            <strong><?php echo number_format($finalShippingFee, 0); ?>₫</strong>
                        <?php else: ?>
                            <strong><?php echo number_format($finalShippingFee, 0); ?>₫</strong>
                        <?php endif; ?>
                        <?php if (!empty($order['shipping_carrier'])): ?>
                            <small style="color:#777;">(<?php echo strtoupper(htmlspecialchars($order['shipping_carrier'])); ?>)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        Miễn phí
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item info-total">
                <div class="total-label">Tổng cộng</div>
                <div class="total-value"><?php echo number_format($order['total_amount'], 0); ?>₫</div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">Địa chỉ giao hàng</div>
        <div class="address-box">
            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title">Sản phẩm trong đơn hàng</div>
        <div class="order-items-list">
            <?php 
            $items_stmt = $db->prepare("
                SELECT 
                    oi.product_id, oi.quantity, oi.price, oi.size,
                    p.name AS product_name,
                    (SELECT url FROM product_images WHERE product_id = oi.product_id AND is_main = 1 LIMIT 1) AS product_image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $items_stmt->execute([$orderId]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php foreach ($order_items as $item): 
                $itemTotal = $item['price'] * $item['quantity'];
                $image = !empty($item['product_image']) ? htmlspecialchars($item['product_image']) : 'assets/images/product-placeholder.png';
            ?>
                <div class="order-item">
                    <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image" loading="lazy" onerror="this.src='assets/images/product-placeholder.png';">
                    <div class="item-details">
                        <a href="product.php?id=<?php echo $item['product_id']; ?>" class="item-name">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </a>
                        <div class="item-meta">
                            Số lượng: <strong><?php echo $item['quantity']; ?></strong>
                            <?php if (!empty($item['size'])): ?>
                                • Kích thước: <strong><?php echo htmlspecialchars($item['size']); ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="item-price"><?php echo number_format($itemTotal, 0); ?>₫</div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Tổng kết -->
        <div class="order-summary">
            <div class="summary-row">
                <span>Tạm tính</span>
                <span><?= number_format($subtotal, 0) ?>₫</span>
            </div>

            <?php if ($discountAmount > 0): ?>
            <div class="summary-row discount-row">
                <span>Mã KM: <strong><?= htmlspecialchars($couponCode) ?></strong></span>
                <span>- <?= number_format($discountAmount, 0) ?>₫</span>
            </div>
            <?php endif; ?>

            <?php if ($shippingDiscountAmount > 0): ?>
            <div class="summary-row shipping-discount">
                <span>Mã vận chuyển: <strong><?= htmlspecialchars($shippingCouponCode) ?></strong></span>
                <span>- <?= number_format($shippingDiscountAmount, 0) ?>₫</span>
            </div>
            <?php endif; ?>

            <div class="summary-row">
                <span>Phí vận chuyển
                    <?php if (!empty($order['shipping_carrier'])): ?>
                        <small>(<?= strtoupper(htmlspecialchars($order['shipping_carrier'])) ?>)</small>
                    <?php endif; ?>
                </span>
                <span>
                    <?php if ($finalShippingFee > 0): ?>
                        <?php if ($finalShippingFee < $originalShippingFee): ?>
                            <del style="color:#999; font-size:0.9em;"><?= number_format($originalShippingFee, 0) ?>₫</del>
                            <strong><?= number_format($finalShippingFee, 0) ?>₫</strong>
                        <?php else: ?>
                            <strong><?= number_format($finalShippingFee, 0) ?>₫</strong>
                        <?php endif; ?>
                    <?php else: ?>
                        Miễn phí
                    <?php endif; ?>
                </span>
            </div>

            <div class="summary-row total">
                <strong>Tổng cộng</strong>
                <strong><?= number_format($order['total_amount'], 0) ?>₫</strong>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="order_history.php" class="btn">Quay lại lịch sử đơn hàng</a>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>