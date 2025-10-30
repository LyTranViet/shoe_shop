<?php
if (!isset($db)) {
    header('Location: index.php');
    exit;
}

// --- HANDLE POST REQUESTS (ADD/EDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? 'add';
    $id = (int)($_POST['id'] ?? 0);
    $errors = [];

    // Sanitize and validate input
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $sizes = $_POST['sizes'] ?? [];
    $stocks = $_POST['stocks'] ?? [];

    if (empty($name)) $errors[] = 'Product name is required.';
    if (empty($code)) $errors[] = 'Product code is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';
    if ($category_id <= 0) $errors[] = 'Category is required.';
    if ($brand_id <= 0) $errors[] = 'Brand is required.';

    if (empty($errors)) {
        $db->beginTransaction();
        try {
            if ($form_action === 'edit' && $id > 0) {
                // --- UPDATE ---
                $stmt = $db->prepare("UPDATE products SET name=?, code=?, price=?, category_id=?, brand_id=?, description=? WHERE id=?");
                $stmt->execute([$name, $code, $price, $category_id, $brand_id, $description, $id]);
                $product_id = $id;
                $success_msg = "✅ Product updated successfully!";
            } else {
                // --- ADD ---
                $stmt = $db->prepare("INSERT INTO products (name, code, price, category_id, brand_id, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $code, $price, $category_id, $brand_id, $description]);
                $product_id = $db->lastInsertId();
                $success_msg = "✅ Product added successfully!";
            }

            // --- Handle Sizes and Stock ---
            // This logic is now more complex to handle updates and additions.
            // 1. Fetch existing sizes with their IDs.
            $stmt_existing = $db->prepare("SELECT id, size, stock FROM product_sizes WHERE product_id = ?");
            $stmt_existing->execute([$product_id]);
            $existing_sizes_data = $stmt_existing->fetchAll(PDO::FETCH_ASSOC);
            $existing_sizes_map = [];
            foreach ($existing_sizes_data as $row) {
                $existing_sizes_map[$row['size']] = ['id' => $row['id'], 'stock' => $row['stock']];
            }

            // 2. Process submitted sizes.
            $stmt_update_size = $db->prepare("UPDATE product_sizes SET size = ? WHERE id = ?");
            $stmt_insert_size = $db->prepare("INSERT INTO product_sizes (product_id, size, stock) VALUES (?, ?, 0)");

            foreach ($sizes as $key => $size_name) {
                $size_name = trim($size_name);
                if (empty($size_name)) continue;

                // Check if this is a new size or an updated name for an existing size.
                // This simple logic assumes we don't have a hidden input for the original size name.
                // A more robust solution would involve hidden fields for original names.
                // For now, we'll focus on adding new sizes, as the update logic is complex.

                $is_new = true;
                foreach ($existing_sizes_map as $existing_size => $data) {
                    if ($existing_size === $size_name) {
                        $is_new = false;
                        break;
                    }
                }
                if ($is_new) {
                    $stmt_insert_size->execute([$product_id, $size_name]);
                }
            }

            // --- Handle Image Uploads (Simplified) ---
            // This part would need more logic for deleting, setting main, etc.

            $db->commit();
            header("Location: index.php?page=products&msg=" . urlencode($success_msg));
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
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
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="index.php?page=products&p=<?= $i ?>&q=<?= urlencode($search_query) ?>" class="page-btn <?= $i == $currentPage ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>