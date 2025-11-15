<?php
if (!isset($db)) {
    header('Location: index.php');
    exit;
}

$action = $action ?? ($_GET['action'] ?? 'list');
$id     = isset($id) ? (int)$id : (int)($_GET['id'] ?? 0);

// optional: initialize $errors if not present
$errors = $errors ?? [];

if ($action === 'add' || $action === 'edit') {
    include __DIR__ . '/product_form.php';
    return; // Dừng ở đây để không hiển thị danh sách bên dưới
} else {
    // --- Product list display ---
    $search_query = trim($_GET['q'] ?? '');
    $itemsPerPage = 10;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));

    $where_clause = '';
    $params = [];
    if ($search_query !== '') {
        $where_clause = "WHERE p.name LIKE :search OR p.code LIKE :search";
        $params[':search'] = "%$search_query%";
    }

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM products p $where_clause");
    $countStmt->execute($params);
    $totalItems = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
    $offset = ($currentPage - 1) * $itemsPerPage;

    // Fetch paginated - FIX: Use prepared statement for LIMIT/OFFSET
    $sql = "
        SELECT p.*, c.name AS category_name, b.name AS brand_name,
            (SELECT url FROM product_images WHERE product_id=p.id AND is_main=1 LIMIT 1) AS main_image_url
        FROM products p
        LEFT JOIN categories c ON p.category_id=c.id
        LEFT JOIN brands b ON p.brand_id=b.id
        $where_clause
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    
    // Bind search params if exist
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    
    // Bind pagination params
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="admin-container">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <header class="admin-header">
        <h2><i class="fi fi-rr-boxes"></i> Quản lý sản phẩm</h2>
        <div class="admin-tools">
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="products">
                <input type="text" name="q" placeholder="Tìm theo tên hoặc mã..." value="<?= htmlspecialchars($search_query) ?>">
                <button type="submit">Tìm</button>
            </form>
            <a href="index.php?page=products&action=add" class="add-btn"><i class="fi fi-rr-plus"></i> Thêm sản phẩm</a>
        </div>
    </header>

    <div class="table-wrapper">
        <table class="admin-table product-list">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Giá</th>
                    <th>Danh mục</th>
                    <th>Thương hiệu</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="7" class="empty">Không có sản phẩm nào</td></tr>
                <?php else: foreach ($products as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><img src="../<?= htmlspecialchars($p['main_image_url'] ?? 'assets/images/product-placeholder.png') ?>" alt="" class="thumb"></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><span class="price"><?= number_format($p['price'], 0) ?>₫</span></td>
                        <td><?= htmlspecialchars($p['category_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($p['brand_name'] ?? '—') ?></td>
                        <td>
                            <div class="actions">
                                <a href="index.php?page=products&action=edit&id=<?= $p['id'] ?>" class="btn edit" title="Sửa"><i class="fi fi-rr-pencil"></i></a>
                                <a href="index.php?page=products&action=delete&id=<?= $p['id'] ?>" class="btn delete" title="Xóa" onclick="return confirm('Xóa sản phẩm này?');"><i class="fi fi-rr-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="index.php?page=products&p=1&q=<?= urlencode($search_query) ?>">« Đầu</a>
                <a href="index.php?page=products&p=<?= $currentPage - 1 ?>&q=<?= urlencode($search_query) ?>">‹ Trước</a>
            <?php endif; ?>
            <?php
            $window = 5;
            $half = floor($window / 2);
            $start = $currentPage - $half;
            $end = $currentPage + $half;
            if ($start < 1) {
                $start = 1;
                $end = min($window, $totalPages);
            }
            if ($end > $totalPages) {
                $end = $totalPages;
                $start = max(1, $end - $window + 1);
            }
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="index.php?page=products&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="<?= $i == $currentPage ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="index.php?page=products&p=<?= $currentPage + 1 ?>&q=<?= urlencode($search_query) ?>">Tiếp ›</a>
                <a href="index.php?page=products&p=<?= $totalPages ?>&q=<?= urlencode($search_query) ?>">Cuối »</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>