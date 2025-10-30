<?php
if (!isset($db) || !isset($receipt) || !isset($receipt_details)) {
    header('Location: index.php?page=stock_out');
    exit;
}
?>

<div class="admin-container">
    <header class="admin-header">
        <h2>📤 Chi tiết Phiếu xuất #<?= htmlspecialchars($receipt['receipt_code']) ?></h2>
        <a href="index.php?page=stock_out" class="btn-back">⬅ Quay lại danh sách</a>
    </header>

    <div class="order-details-grid">
        <div class="panel">
            <h3>Thông tin chung</h3>
            <div class="info-grid">
                <div><strong>Ngày xuất:</strong></div>
                <div><?= date('d/m/Y H:i', strtotime($receipt['export_date'])) ?></div>
                
                <div><strong>Loại xuất:</strong></div>
                <div><?= htmlspecialchars($receipt['export_type']) ?></div>

                <div><strong>Trạng thái:</strong></div>
                <div><span class="status <?= strtolower(str_replace(' ', '-', $receipt['status'] ?? '')) ?>"><?= htmlspecialchars($receipt['status'] ?? 'N/A') ?></span></div>

                <div><strong>Nhân viên:</strong></div>
                <div><?= htmlspecialchars($receipt['employeeName'] ?? 'N/A') ?></div>

                <div><strong>Ghi chú:</strong></div>
                <div><?= nl2br(htmlspecialchars($receipt['note'] ?? '—')) ?></div>
            </div>
        </div>
        
        <?php if ($receipt['status'] === 'Chờ xác nhận'): ?>
        <div class="panel">
            <h3>Hành động</h3>
            <a href="index.php?page=orders&action=confirm_export&id=<?= $receipt['id'] ?>" class="btn-submit" style="background: #28a745; margin-right: 10px;" onclick="return confirm('Bạn có chắc chắn muốn xác nhận xuất kho cho phiếu này? Hành động này sẽ trừ tồn kho và không thể hoàn tác.');">✅ Xác nhận & Xuất kho</a>
            <a href="index.php?page=stock_out&action=cancel_export&id=<?= $receipt['id'] ?>" class="btn-submit" style="background: #e74c3c;" onclick="return confirm('Bạn có chắc chắn muốn hủy phiếu xuất này?');">❌ Hủy Phiếu</a>
        </div>
        <?php endif; ?>

        <div class="panel order-items-panel">
            <h3>Chi tiết sản phẩm xuất</h3>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Size</th>
                    <th>Mã lô</th>
                    <th>Số lượng</th>
                    <th>Giá bán</th>
                    <th>Thành tiền</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($receipt_details as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><?= htmlspecialchars($item['size']) ?></td>
                        <td><?= htmlspecialchars($item['batch_code']) ?></td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><span class="price"><?= number_format($item['price'], 0) ?>₫</span></td>
                        <td><span class="price"><?= number_format($item['price'] * $item['quantity'], 0) ?>₫</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align: right;"><strong>Tổng cộng:</strong></td>
                        <td><strong class="price" style="font-size: 18px;"><?= number_format($receipt['total_amount'], 0) ?>₫</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>