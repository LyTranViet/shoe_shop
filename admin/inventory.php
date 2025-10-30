<?php
if (!isset($db)) {
	header('Location: index.php');
	exit;
}

// --- LIST VIEW ---
$search_query = trim($_GET['q'] ?? '');
$itemsPerPage = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$where_clause = '';
$params = [];

if (!empty($search_query)) {
    $where_clause = "WHERE p.name LIKE :q OR p.code LIKE :q OR pb.batch_code LIKE :q";
    $params[':q'] = "%$search_query%";
}

$countSql = "SELECT COUNT(*) FROM product_batch pb JOIN product_sizes ps ON pb.productsize_id = ps.id JOIN products p ON ps.product_id = p.id $where_clause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

$dataSql = "
    SELECT pb.*, p.id as product_id, p.name as product_name, p.code as product_code, ps.size, 
           (SELECT import_id FROM import_receipt_detail WHERE batch_id = pb.id LIMIT 1) as import_id
    FROM product_batch pb
    JOIN product_sizes ps ON pb.productsize_id = ps.id
    JOIN products p ON ps.product_id = p.id
    $where_clause
    ORDER BY pb.import_date DESC, p.name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $db->prepare($dataSql);
if (!empty($search_query)) $stmt->bindValue(':q', "%$search_query%", PDO::PARAM_STR);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$inventory_batches = $stmt->fetchAll();
?>

<div class="admin-container">
    <header class="admin-header">
        <h2><i class="fi fi-rr-inventory"></i> Tồn kho theo lô</h2>
        <div class="admin-tools">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="inventory">
                <input type="text" name="q" placeholder="Tìm theo tên, mã SP, mã lô..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit">Tìm</button>
            </form>
        </div>
    </header>

    <div class="table-wrapper">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success" style="margin: 10px;"><?= htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Size</th>
                    <th>Mã lô</th>
                    <th>SL Nhập</th>
                    <th>SL Còn lại</th>
                    <th>Ngày nhập</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($inventory_batches)): ?>
                <tr><td colspan="7" class="empty">Không có dữ liệu tồn kho.</td></tr>
            <?php else: foreach ($inventory_batches as $batch): ?>
                <tr>
                    <td><?= htmlspecialchars($batch['product_name']) ?> (<?= htmlspecialchars($batch['product_code']) ?>)</td>
                    <td><?= htmlspecialchars($batch['size']) ?></td>
                    <td><?= htmlspecialchars($batch['batch_code']) ?></td>
                    <td><?= (int)$batch['quantity_in'] ?></td>
                    <td><strong><?= (int)$batch['quantity_remaining'] ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($batch['import_date'])) ?></td>
                    <td>
                        <div class="actions">
                            <?php if (!empty($batch['import_id'])): ?>
                                <a href="index.php?page=stock_in&action=view&id=<?= $batch['import_id'] ?>" class="btn view" title="Xem phiếu nhập"><i class="fi fi-rr-document"></i></a>
                                <a href="index.php?page=stock_out&product_id=<?= $batch['product_id'] ?>" class="btn export-btn" title="Xem lịch sử xuất kho của sản phẩm này"><i class="fi fi-rr-arrow-up-from-square"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="index.php?page=inventory&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="page-btn <?= ($i == $currentPage) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>