
<?php
// This file is included by orders.php when action is 'view'
if (!isset($db) || !isset($order)) {
    header('Location: index.php?page=orders');
    exit;
}
?>

<header class="admin-header">
    <h2><i class="fi fi-rr-receipt"></i> Chi tiết đơn hàng #<?php echo $order['id']; ?></h2>
    <a href="index.php?page=orders" class="btn-back"><i class="fi fi-rr-arrow-left"></i> Quay lại danh sách</a>
</header>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success" style="margin: 10px 0;"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="margin: 10px 0;"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<?php if (isset($_GET['status_updated'])): ?>
    <div class="alert-success">✅ Order status has been updated successfully.</div>
<?php endif; ?>

<div class="order-details-grid">
    <div class="panel">
        <h3><i class="fi fi-rr-user"></i> Thông tin khách hàng</h3>
        <div class="info-grid">
            <div><strong>Name:</strong></div>
            <div><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></div>
            <div><strong>Email:</strong></div>
            <div><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></div>
            <div><strong>Phone:</strong></div>
            <div><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></div>
        </div>
    </div>

    <div class="panel">
        <h3><i class="fi fi-rr-truck-side"></i> Thông tin đơn hàng</h3>
        <div class="info-grid">
            <div><strong>Date:</strong></div>
            <div><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></div>
            <div><strong>Payment:</strong></div>
            <div><?php echo htmlspecialchars($order['payment_method']); ?></div>
            <div><strong>Address:</strong></div>
            <div><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></div>
        </div>
    </div>

    <div class="panel order-items-panel">
        <h3><i class="fi fi-rr-box-open"></i> Các sản phẩm đã đặt</h3>
        <table class="admin-table">
            <thead>
            <tr>
                <th>Product</th>
                <th>Size</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order_items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['product_code']); ?>)</td>
                    <td><?php echo htmlspecialchars($item['size']); ?></td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td><?php echo number_format($item['price'], 0); ?>₫</td>
                    <td><?php echo number_format($item['price'] * $item['quantity'], 0); ?>₫</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right;">Tổng cộng:</td>
                    <td style="color:#e74c3c; font-size: 16px;"><?php echo number_format($order['total_amount'], 0); ?>₫</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="panel">
        <h3><i class="fi fi-rr-refresh"></i> Cập nhật trạng thái</h3>
        <p>Current Status:
            <span class="status <?php echo strtolower(str_replace(' ', '-', $order['status_name'] ?? '')); ?>">
                <?php echo htmlspecialchars($order['status_name'] ?? 'N/A'); ?>
            </span>
        </p>
        <form method="POST" action="index.php?page=orders">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            <div class="form-group">
                <label for="status_id">Change Status To:</label>
                <select name="status_id" id="status_id" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status['id']; ?>" <?php echo ($status['id'] == $order['status_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="update_status" class="btn-submit small"><i class="fi fi-rr-disk"></i> Cập nhật</button>
        </form>
    </div>
</div>
