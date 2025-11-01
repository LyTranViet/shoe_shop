<?php
if (!isset($db) || !isset($receipt) || !isset($receipt_details)) {
    header('Location: index.php?page=stock_in');
    exit;
}
?>

<div class="admin-container">
    <header class="admin-header">
        <h2>🧾 Chi tiết Phiếu nhập #<?= htmlspecialchars($receipt['receipt_code']) ?></h2>
        <div class="admin-tools" style="display: flex; gap: 10px;">
            <a href="export_import_receipt.php?id=<?= $receipt['id'] ?>" class="btn" target="_blank" style="background-color: #2980b9; color: white;">📄 Xuất DOCX</a>
            <a href="index.php?page=stock_in" class="btn-back">⬅ Quay lại danh sách</a>
        </div>
    </header>

    <div class="order-details-grid" style="max-width: 1000px; margin: auto;">
        <div class="panel">
            <h3>Thông tin chung</h3>
            <div class="info-grid">
                <div><strong>Ngày nhập:</strong></div>
                <div><?= date('d/m/Y', strtotime($receipt['import_date'])) ?></div>
                
                <div><strong>Nhà cung cấp:</strong></div>
                <div><?= htmlspecialchars($receipt['supplierName'] ?? 'N/A') ?></div>

                <div><strong>Nhân viên:</strong></div>
                <div><?= htmlspecialchars($receipt['employeeName'] ?? 'N/A') ?></div>

                <div><strong>Ghi chú:</strong></div>
                <div><?= nl2br(htmlspecialchars($receipt['note'] ?? '—')) ?></div>
            </div>
        </div>

        <div class="panel order-items-panel">
            <h3>Chi tiết sản phẩm nhập</h3>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Size</th>
                    <th>Mã lô</th>
                    <th>Số lượng</th>
                    <th>Giá nhập</th>
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