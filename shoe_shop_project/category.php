<?php
require_once __DIR__ . '/includes/header.php';
$db = get_db();

// Load filter data
$categories = $db->query('SELECT * FROM categories')->fetchAll();
$brands = [];
try { $brands = $db->query('SELECT * FROM brands')->fetchAll(); } catch (Exception $e) { }
$sizes = [];
try { $sizes = $db->query('SELECT DISTINCT size FROM product_sizes ORDER BY size')->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { }

// Read filters from GET
$filters = [
    'category_id' => !empty($_GET['category_id']) && is_array($_GET['category_id']) ? array_map('intval', $_GET['category_id']) : [],
    'brand_id' => !empty($_GET['brand_id']) && is_array($_GET['brand_id']) ? array_map('intval', $_GET['brand_id']) : [],
    'price_min' => isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : null,
    'price_max' => isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : null,
    'size' => !empty($_GET['size']) && is_array($_GET['size']) ? array_map('htmlspecialchars', $_GET['size']) : [],
    'q' => trim($_GET['q'] ?? ''),
    'sort' => $_GET['sort'] ?? 'popularity',
    'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
    'perPage' => 16,
];

// Persist selected category in session so header can show the selection across pages
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($filters['category_id'])) {
    $_SESSION['selected_category_id'] = $filters['category_id'];
} else {
    // if no category filter, remove previous selection
    if (isset($_SESSION['selected_category_id'])) unset($_SESSION['selected_category_id']);
}
// Build products query with filters
$params = [];
$joins = [];
$where = [];

if ($filters['q'] !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$filters['q']}%";
    $params[] = "%{$filters['q']}%";
}
if (!empty($filters['category_id'])) {
    $placeholders = implode(',', array_fill(0, count($filters['category_id']), '?'));
    $where[] = "p.category_id IN ($placeholders)";
    $params = array_merge($params, $filters['category_id']);
}
if (!empty($filters['brand_id'])) {
    $placeholders = implode(',', array_fill(0, count($filters['brand_id']), '?'));
    $where[] = "p.brand_id IN ($placeholders)";
    $params = array_merge($params, $filters['brand_id']);
}
if (!is_null($filters['price_min'])) { $where[] = 'p.price >= ?'; $params[] = $filters['price_min']; }
if (!is_null($filters['price_max'])) { $where[] = 'p.price <= ?'; $params[] = $filters['price_max']; }

if (!empty($filters['size'])) {
    $placeholders = implode(',', array_fill(0, count($filters['size']), '?'));
    $joins[] = "JOIN product_sizes ps ON ps.product_id = p.id AND ps.size IN ($placeholders)";
    $params = array_merge($params, $filters['size']);
}

if ($filters['sort'] === 'bestsellers') {
    $joins[] = 'LEFT JOIN (SELECT product_id, SUM(quantity) AS total_sold FROM order_items GROUP BY product_id) oi ON p.id = oi.product_id';
}

$baseSql = 'FROM products p ' . (!empty($joins) ? implode(' ', $joins) : '');
if (!empty($where)) { $baseSql .= ' WHERE ' . implode(' AND ', $where); }

// Count total products for pagination
$countSql = 'SELECT COUNT(DISTINCT p.id) ' . $baseSql;
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $filters['perPage']);
$offset = ($filters['page'] - 1) * $filters['perPage'];

$sql = 'SELECT p.*, (SELECT SUM(stock) FROM product_sizes ps_stock WHERE ps_stock.product_id = p.id) as total_stock ' . $baseSql . ' GROUP BY p.id'; // Group by to handle joins correctly

switch ($filters['sort']) {
    case 'newest': $sql .= ' ORDER BY p.id DESC'; break;
    case 'bestsellers': $sql .= ' ORDER BY COALESCE(oi.total_sold, 0) DESC, p.id DESC'; break;
    case 'price_asc': $sql .= ' ORDER BY p.price ASC'; break;
    case 'price_desc': $sql .= ' ORDER BY p.price DESC'; break;
    case 'name_desc': $sql .= ' ORDER BY p.name DESC'; break;
    case 'name_asc':
    default: $sql .= ' ORDER BY p.name ASC'; break;
}
$sql .= ' LIMIT ' . $filters['perPage'] . ' OFFSET ' . $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Load main images for displayed products
$imagesByProduct = [];
try {
    $ids = array_map(function($p){ return $p['id']; }, $products);
    $ids = array_values(array_unique($ids));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT product_id, url, is_main FROM product_images WHERE product_id IN ($placeholders) ORDER BY is_main DESC");
        $st->execute($ids);
        $imgs = $st->fetchAll();
        foreach ($imgs as $img) {
            if (!isset($imagesByProduct[$img['product_id']])) {
                $imagesByProduct[$img['product_id']] = $img['url'];
            }
        }
    }
} catch (Exception $e) { }

?>


<style>
    .sidebar {
        background: #fff;
        padding: 28px 20px 22px 20px;
        border-radius: 14px;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 4px 18px #cbd5e122;
        min-width: 220px;
        font-size: 1.04em;
        margin-top: 8px;
    }
    .sidebar h3 {
        font-size: 1.18em;
        font-weight: 700;
        color: #0ea5ff;
        margin-bottom: 18px;
        letter-spacing: 0.2px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sidebar h3::before { content: '\1F50D'; font-size: 1.1em; }
    .sidebar .filter-title { font-weight: 600; margin-bottom: 8px; color: #2563eb; display: block; }
    .sidebar .filter-list { border: none; border-radius: 0; background: none; max-height: 160px; overflow-y: auto; }
    .sidebar .filter-list li { padding: 4px 0; }
    .sidebar .filter-list li label { gap: 7px; }
    .sidebar input[type="number"] { width: 48%; margin-bottom: 6px; border-radius: 6px; border: 1px solid #e2e8f0; padding: 7px 10px; }
    .sidebar .filter-actions { margin-top: 12px; gap: 8px; }
    .sidebar .btn { font-size: 1em; }
    .products-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        border-bottom: 1.5px solid #e2e8f0;
        padding-bottom: 10px;
    }
    .products-header h2 {
        font-size: 1.5em;
        font-weight: 800;
        color: #0ea5ff;
        letter-spacing: -1px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .products-header h2::before { content: '\1F6CD'; font-size: 1.1em; }
    .sort-form label { font-weight: 600; color: #2563eb; }
    .sort-form select {
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        padding: 7px 12px;
        font-size: 1em;
        margin-left: 6px;
        background: #f8fafc;
        color: #2563eb;
    }
    .grid { gap: 28px; }
    .product {
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        text-align: center;
        padding: 18px 12px 16px 12px;
        box-shadow: 0 4px 18px #cbd5e122;
        transition: box-shadow 0.2s, transform 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .product:hover { box-shadow: 0 8px 28px #0ea5ff22; transform: translateY(-6px) scale(1.03); }
    .product .thumb { margin-bottom: 14px; }
    .product .thumb img { max-width: 100%; height: 180px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 8px #bae6fd33; }
    .product h4 { font-size: 1.13em; margin: 8px 0 6px 0; font-weight: 700; color: #2563eb; }
    .product p { font-size: 1.08em; color: #0ea5ff; margin: 0 0 8px 0; font-weight: 700; }
    .product-actions { display: flex; justify-content: center; gap: 8px; margin-top: 10px; }
    .product-actions .btn { font-size: 0.98em; padding: 8px 14px; border-radius: 7px; }
    .product-actions .btn[type="submit"]:first-child { background: linear-gradient(90deg,#0ea5ff 60%,#2563eb 100%); }
    .product-actions .btn[type="submit"]:last-child { background: #f1f5f9; color: #0ea5ff; border: 1px solid #bae6fd; }
    .product-actions .btn[type="submit"]:last-child:hover { background: #0ea5ff; color: #fff; }
            .pagination { display: flex; gap: 18px; justify-content: center; margin: 60px 0 0 0; }
    .pagination .btn { border-radius: 7px; font-size: 1em; padding: 8px 16px; }
    .pagination .btn.current { background: #0ea5ff; color: #fff; font-weight: 700; }
    @media (max-width: 900px) {
        .layout { grid-template-columns: 1fr; }
        .sidebar { margin-bottom: 24px; }
        .products-area .grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
        .products-area .grid { grid-template-columns: 1fr; }
        .sidebar { padding: 14px 6px; }
    }
</style>
<style>
    .out-of-stock-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        padding: 4px 8px;
        font-size: 0.8em;
        font-weight: bold;
        border-radius: 4px;
        z-index: 1;
    }
</style>
<div class="layout">
    <aside class="sidebar">
        <form method="get" action="category.php">
            <h3>Bộ lọc</h3>
            <div>
                <label class="filter-title">Danh mục</label>
                <ul class="filter-list">
                    <?php foreach ($categories as $c): ?>
                        <li><label><input type="checkbox" name="category_id[]" value="<?php echo $c['id']; ?>" <?php echo in_array($c['id'], $filters['category_id']) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($c['name']); ?></label></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <label class="filter-title">Thương hiệu</label>
                <ul class="filter-list">
                    <?php foreach ($brands as $b): ?>
                        <li><label><input type="checkbox" name="brand_id[]" value="<?php echo $b['id']; ?>" <?php echo in_array($b['id'], $filters['brand_id']) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($b['name']); ?></label></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <label class="filter-title">Giá</label>
                <input type="number" step="0.01" name="price_min" placeholder="Tối thiểu" value="<?php echo htmlspecialchars($filters['price_min'] ?? ''); ?>">
                <input type="number" step="0.01" name="price_max" placeholder="Tối đa" value="<?php echo htmlspecialchars($filters['price_max'] ?? ''); ?>">
            </div>
            <div>
                <label class="filter-title">Kích cỡ</label>
                <ul class="filter-list">
                    <?php foreach ($sizes as $s): ?>
                        <li><label><input type="checkbox" name="size[]" value="<?php echo htmlspecialchars($s); ?>" <?php echo in_array($s, $filters['size']) ? 'checked' : ''; ?>> <?php echo htmlspecialchars($s); ?></label></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="filter-actions">
                <button class="btn" type="submit">Áp dụng</button>
                <a class="btn secondary" href="category.php">Đặt lại</a>
            </div>
        </form>
    </aside>
    <section class="products-area">
        <div class="products-header">
            <h2>Sản phẩm</h2>
            <?php if ($filters['q']): ?>
                <p style="margin: 0; font-size: 1.1rem;">Kết quả cho: <strong>"<?= htmlspecialchars($filters['q']) ?>"</strong></p>
            <?php endif; ?>
            <form method="get" action="category.php" class="sort-form">
                <?php // preserve filters when changing sort ?>
                <?php foreach ($filters as $key => $values):
                    if ($key === 'sort') continue;
                    if (is_array($values)) {
                        foreach ($values as $value) {
                            echo '<input type="hidden" name="' . htmlspecialchars($key) . '[]" value="' . htmlspecialchars($value) . '">';
                        }
                    } elseif ($values !== null && $values !== '') {
                        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($values) . '">';
                    }
                endforeach; ?>
                <label>Sắp xếp
                    <select name="sort" onchange="this.form.submit()">
                        <option value="popularity" <?php echo $filters['sort']=='popularity'?'selected':''; ?>>Phổ biến</option>
                        <option value="newest" <?php echo $filters['sort']=='newest'?'selected':''; ?>>Mới nhất</option>
                        <option value="bestsellers" <?php echo $filters['sort']=='bestsellers'?'selected':''; ?>>Bán chạy</option>
                        <option value="name_asc" <?php echo $filters['sort']=='name_asc'?'selected':''; ?>>Tên A-Z</option>
                        <option value="name_desc" <?php echo $filters['sort']=='name_desc'?'selected':''; ?>>Tên Z-A</option>
                        <option value="price_asc" <?php echo $filters['sort']=='price_asc'?'selected':''; ?>>Giá tăng dần</option>
                        <option value="price_desc" <?php echo $filters['sort']=='price_desc'?'selected':''; ?>>Giá giảm dần</option>
                    </select>
                </label>
            </form>
        </div>
        <div class="grid">
            <?php if (empty($products)): ?>
                <p>Không tìm thấy sản phẩm nào.</p>
            <?php else: foreach ($products as $p): ?>
                <div class="product">
                    <div class="thumb">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                            <div class="out-of-stock-badge">Hết hàng</div>
                        <?php endif; ?>
                        <?php $img = $imagesByProduct[$p['id']] ?? 'assets/images/product-placeholder.png'; ?>
                        <a href="product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                    </div>
                    <h4><a href="product.php?id=<?php echo $p['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($p['name']); ?></a></h4>
                    <p><strong><?php echo number_format($p['price'], 0); ?>₫</strong></p>
                    <div class="product-actions">
                        <form class="ajax-add-cart" method="post" action="cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn" type="submit" <?= (isset($p['total_stock']) && $p['total_stock'] <= 0) ? 'disabled' : '' ?>>Thêm vào giỏ</button>
                        </form>
                        <form class="ajax-wishlist" method="post" action="wishlist.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <button class="btn" type="submit">❤</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php
                // Build query string without page number
                $queryParams = $_GET;
                unset($queryParams['page']);
                $queryString = http_build_query($queryParams);
                ?>
                <?php if ($filters['page'] > 1): ?>
                    <a href="?page=<?php echo $filters['page'] - 1; ?>&<?php echo $queryString; ?>" class="btn">‹ Trước</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&<?php echo $queryString; ?>" class="btn <?php echo $i === $filters['page'] ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($filters['page'] < $totalPages): ?>
                    <a href="?page=<?php echo $filters['page'] + 1; ?>&<?php echo $queryString; ?>" class="btn">Tiếp ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
 
