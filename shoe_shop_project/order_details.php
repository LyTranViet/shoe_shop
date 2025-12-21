<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helper.php';

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

// === TÍNH TOÁN CHÍNH XÁC ===
$discountAmount         = (float)$order['discount_amount'];
$shippingDiscountAmount = (float)$order['shipping_discount_amount'];
$finalShippingFee       = (float)$order['shipping_fee'];
$originalShippingFee    = (float)($order['original_shipping_fee'] ?? $order['shipping_fee']);
$couponCode             = $order['coupon_code'] ?? '';
$shippingCouponCode     = $order['shipping_coupon_code'] ?? '';

// Tạm tính = tổng + giảm + phí vận chuyển thực tế
$subtotal = $order['total_amount'] + $discountAmount - $finalShippingFee;

require_once __DIR__ . '/includes/header.php';
?>

<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng #<?php echo $orderId; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                margin: 0;
                padding: 10mm;
                font-size: 12pt;
            }

            .no-print,
            .action-buttons {
                display: none !important;
            }

            .container {
                box-shadow: none;
                border: none;
                max-width: 100%;
            }

            .panel {
                border: none;
                padding: 10px 0;
            }

            .summary-row {
                font-size: 11pt;
            }

            .item-image {
                width: 60px;
                height: 60px;
            }

            .order-item {
                padding: 8px 0;
            }
        }

        .print-btn {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
        }

        .print-btn:hover {
            background: #0b5ed7;
        }

        .print-btn i {
            margin-right: 6px;
        }
    </style>
</head>

<body>

    <div class="container mt-4">

        <!-- NÚT IN ĐƠN HÀNG -->
        <div class="text-end mb-3 no-print">
            <button onclick="printOrder()" class="print-btn">
                In đơn hàng
            </button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Đơn hàng #<?php echo $orderId; ?></h4>
                <span class="badge bg-success fs-6"><?php echo htmlspecialchars($order['status_name']); ?></span>
            </div>

            <div class="card-body">
                <!-- THÔNG TIN CHUNG -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <small class="text-muted">Ngày đặt</small>
                        <p class="fw-bold"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Thanh toán</small>
                        <p class="fw-bold mb-1"><?php echo htmlspecialchars($order['payment_method'] ?: '—'); ?></p>
                        <?php
                        $pm = strtoupper($order['payment_method'] ?? '');
                        $tx = '';
                        if ($pm === 'PAYPAL' && !empty($order['paypal_order_id'])) {
                            $tx = $order['paypal_order_id'];
                        } elseif ($pm === 'VNPAY' && !empty($order['vnp_transaction_id'])) {
                            $tx = $order['vnp_transaction_id'];
                        }
                        ?>
                        <?php if (!empty($tx)): ?>
                            <div class="small text-muted">Mã GD: <?php echo htmlspecialchars($tx); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ĐỊA CHỈ GIAO HÀNG -->
                <div class="mb-4 p-3 bg-light rounded">
                    <small class="text-muted d-block mb-2">Địa chỉ giao hàng</small>
                    <div class="fw-medium"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
                </div>

                <!-- DANH SÁCH SẢN PHẨM -->
                <h5 class="mb-3">Sản phẩm</h5>
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
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <img src="<?php echo $image; ?>" alt="" class="item-image me-3"
                                style="width:70px;height:70px;object-fit:cover;border-radius:6px;">
                            <div class="flex-grow-1">
                                <a href="product.php/<?php echo createSlug($item['product_name']); ?>-<?php echo $item['product_id']; ?>"
                                    class="text-decoration-none text-dark fw-semibold">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </a>
                                <div class="text-muted small">
                                    SL: <strong><?php echo $item['quantity']; ?></strong>
                                    <?php if (!empty($item['size'])): ?>
                                        • Size: <strong><?php echo htmlspecialchars($item['size']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end fw-bold"><?php echo number_format($itemTotal, 0); ?>₫</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- TỔNG KẾT -->
                <div class="mt-4 p-3 bg-light rounded">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính</span>
                        <span><?php echo number_format($subtotal, 0); ?>₫</span>
                    </div>

                    <?php if ($discountAmount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Mã KM: <strong><?php echo htmlspecialchars($couponCode); ?></strong></span>
                            <span><?php echo number_format($discountAmount, 0); ?>₫</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($shippingDiscountAmount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Mã vận chuyển:
                                <strong><?php echo htmlspecialchars($shippingCouponCode); ?></strong></span>
                            <span>- <?php echo number_format($shippingDiscountAmount, 0); ?>%</span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển
                            <?php if (!empty($order['shipping_carrier'])): ?>
                                <small
                                    class="text-muted">(<?php echo strtoupper(htmlspecialchars($order['shipping_carrier'])); ?>)</small>
                            <?php endif; ?>
                        </span>
                        <span>
                            <?php if ($finalShippingFee > 0): ?>
                                <?php if ($finalShippingFee < $originalShippingFee): ?>
                                    <del class="text-muted small"><?php echo number_format($originalShippingFee, 0); ?>₫</del>
                                    <strong><?php echo number_format($finalShippingFee, 0); ?>₫</strong>
                                <?php else: ?>
                                    <strong><?php echo number_format($finalShippingFee, 0); ?>₫</strong>
                                <?php endif; ?>
                            <?php else: ?>
                                Miễn phí
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between pt-2 border-top mt-2 fw-bold fs-5">
                        <strong>Tổng cộng</strong>
                        <strong class="text-primary"><?php echo number_format($order['total_amount'], 0); ?>₫</strong>
                    </div>
                </div>
            </div>

            <!-- NÚT HÀNH ĐỘNG -->
            <div class="card-footer bg-white text-center no-print">
                <a href="order_history.php" class="btn btn-outline-secondary me-2">Quay lại</a>
            </div>
        </div>
    </div>

    <!-- SCRIPT IN ĐƠN HÀNG -->
    <script>
        function printOrder() {
            const printWindow = window.open('', '_blank');
            const printContent = `
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đơn hàng #${<?php echo $orderId; ?>}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 15mm; font-size: 12pt; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        .header h1 { font-size: 18pt; margin: 0; }
        .info { margin: 15px 0; }
        .info strong { display: inline-block; width: 140px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .total { font-weight: bold; font-size: 14pt; }
        .text-center { text-align: center; }
        .mt-3 { margin-top: 15px; }
        .badge { display: inline-block; padding: 4px 8px; background: #28a745; color: white; border-radius: 4px; font-size: 10pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PHIẾU ĐƠN HÀNG</h1>
        <p><strong>Mã đơn:</strong> #${<?php echo $orderId; ?>} | <strong>Ngày:</strong> ${new Date().toLocaleDateString('vi-VN')}</p>
    </div>

    <div class="info">
        <p><strong>Khách hàng:</strong> ${'<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Khách'); ?>'}</p>
        <p><strong>Địa chỉ:</strong> ${'<?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>'}</p>
        <p><strong>PTTT:</strong> ${'<?php echo $order['payment_method'] ?: '—'; ?>'}</p>
        <p><strong>Trạng thái:</strong> <span class="badge">${'<?php echo htmlspecialchars($order['status_name']); ?>'}</span></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Sản phẩm</th>
                <th>SL</th>
                <th>Đơn giá</th>
                <th class="text-right">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_items as $item):
                $itemTotal = $item['price'] * $item['quantity'];
            ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($item['product_name']); ?>
                    <?php if (!empty($item['size'])): ?> (Size: <?php echo htmlspecialchars($item['size']); ?>)<?php endif; ?>
                </td>
                <td class="text-center"><?php echo $item['quantity']; ?></td>
                <td><?php echo number_format($item['price'], 0); ?>₫</td>
                <td class="text-right"><?php echo number_format($itemTotal, 0); ?>₫</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-3">
        <table style="width: 60%; margin-left: auto; border: none;">
            <tr><td>Tạm tính</td><td class="text-right"><?php echo number_format($subtotal, 0); ?>₫</td></tr>
            <?php if ($discountAmount > 0): ?>
            <tr><td>Mã KM: <strong><?php echo htmlspecialchars($couponCode); ?></strong></td><td class="text-right">-<?php echo number_format($discountAmount, 0); ?>₫</td></tr>
            <?php endif; ?>
            <?php if ($shippingDiscountAmount > 0): ?>
            <tr><td>Mã vận chuyển: <strong><?php echo htmlspecialchars($shippingCouponCode); ?></strong></td><td class="text-right">-<?php echo number_format($shippingDiscountAmount, 0); ?>₫</td></tr>
            <?php endif; ?>
            <tr><td>Phí vận chuyển</td><td class="text-right">
                <?php if ($finalShippingFee > 0): ?>
                    <?php if ($finalShippingFee < $originalShippingFee): ?>
                        <del><?php echo number_format($originalShippingFee, 0); ?>₫</del>
                    <?php endif; ?>
                    <?php echo number_format($finalShippingFee, 0); ?>₫
                <?php else: ?>Miễn phí<?php endif; ?>
            </td></tr>
            <tr class="total"><td><strong>TỔNG CỘNG</strong></td><td class="text-right"><strong><?php echo number_format($order['total_amount'], 0); ?>₫</strong></td></tr>
        </table>
    </div>

    <div class="text-center mt-5" style="font-size: 10pt; color: #666;">
        <p>Cảm ơn quý khách đã mua sắm tại <strong>ShoeShop</strong>!</p>
        <p>Hotline: 1900 1234 | Website: shoesho.vn</p>
    </div>
</body>
</html>
    `;
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => printWindow.print(), 500);
        }
    </script>

    <style>
        .container {
            max-width: 800px;
        }

        .card {
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .card-header {
            border-radius: 12px 12px 0 0 !important;
            background-color: var(--bg-light) !important;
            border-bottom: 1px solid var(--border);
        }

        .card-header h4 {
            color: var(--text-dark);
            font-weight: 600;
        }

        .badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Status colors from order_history.php */
        .badge.bg-success {
            background-color: rgba(40, 167, 69, 0.15) !important;
            color: var(--success) !important;
        }

        .badge.bg-warning {
            background-color: rgba(255, 193, 7, 0.15) !important;
            color: #b58500 !important;
        }

        .badge.bg-info {
            background-color: rgba(23, 162, 184, 0.15) !important;
            color: var(--info) !important;
        }

        .badge.bg-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: var(--danger) !important;
        }

        .order-items-list .d-flex {
            gap: 15px;
        }

        .item-image {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .flex-grow-1 a {
            text-decoration: none;
            color: var(--text-dark);
        }

        .flex-grow-1 a:hover {
            color: var(--primary);
        }

        .mt-4.p-3.bg-light {
            background-color: var(--bg-light) !important;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding-top: 12px;
            margin-top: 8px;
            border-top: 1px solid var(--border);
            font-size: 1.2rem;
            font-weight: bold;
        }

        .total-row .text-primary {
            color: var(--primary) !important;
        }
    </style>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>