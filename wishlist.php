<?php
// Wishlist page: supports add/remove via POST and renders wishlist as grid
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// helper for JSON
function json_res($arr) { header('Content-Type: application/json'); echo json_encode($arr); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $pid = (int)($_POST['product_id'] ?? 0);

    // require login for wishlist DB operations; allow session fallback
    if (!is_logged_in()) {
        // operate on session wishlist for guests
        if ($action === 'add') {
            $_SESSION['wishlist'] = $_SESSION['wishlist'] ?? [];
            if (!in_array($pid, $_SESSION['wishlist'])) $_SESSION['wishlist'][] = $pid;
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') json_res(['success'=>true]);
            flash_set('success','Added to wishlist'); header('Location: wishlist.php'); exit;
        }
        if ($action === 'remove') {
            if (!empty($_SESSION['wishlist'])) {
                $_SESSION['wishlist'] = array_values(array_filter($_SESSION['wishlist'], function($v) use ($pid){ return $v != $pid; }));
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') json_res(['success'=>true]);
            flash_set('success','Removed from wishlist'); header('Location: wishlist.php'); exit;
        }
    }

    // logged-in user DB operations
    $db = get_db(); $uid = current_user_id();
    try {
        if ($action === 'add') {
            $st = $db->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
            $st->execute([$uid, $pid]);
            if (!$st->fetch()) {
                $ins = $db->prepare('INSERT INTO wishlists (user_id, product_id) VALUES (?,?)');
                $ins->execute([$uid, $pid]);
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') json_res(['success'=>true]);
            flash_set('success','Added to wishlist'); header('Location: wishlist.php'); exit;
        }
        if ($action === 'remove') {
            $del = $db->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?');
            $del->execute([$uid, $pid]);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') json_res(['success'=>true]);
            flash_set('success','Removed from wishlist'); header('Location: wishlist.php'); exit;
        }
    } catch (PDOException $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') json_res(['success'=>false,'error'=>'DB error']);
        flash_set('error','Wishlist error'); header('Location: wishlist.php'); exit;
    }
}

// Render wishlist as product grid (like category.php)
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$ids = [];
if (is_logged_in()) {
    try { $st = $db->prepare('SELECT product_id FROM wishlists WHERE user_id = ?'); $st->execute([current_user_id()]); $ids = $st->fetchAll(PDO::FETCH_COLUMN); } catch (PDOException $e) { $ids = array_values($_SESSION['wishlist'] ?? []); }
} else {
    $ids = array_values($_SESSION['wishlist'] ?? []);
}

// --- Pagination Logic ---
$productsPerPage = 20; // 5 columns * 4 rows
$totalProducts = count($ids);
$totalPages = ceil($totalProducts / $productsPerPage);
$currentPage = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $productsPerPage;

$products = [];
if (!empty($ids)) {
    $ids = array_values(array_unique($ids));
    $paginated_ids = array_slice($ids, $offset, $productsPerPage);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $paginated_placeholders = implode(',', array_fill(0, count($paginated_ids), '?'));

    $stmt = $db->prepare("SELECT * FROM products WHERE id IN ($paginated_placeholders)");
    $stmt->execute($paginated_ids);
    $products = $stmt->fetchAll();

    // load main images
    $imagesByProduct = [];
    try {
        $st = $db->prepare("SELECT product_id, url, is_main FROM product_images WHERE product_id IN ($paginated_placeholders) ORDER BY is_main DESC");
        $st->execute($paginated_ids);
        foreach ($st->fetchAll() as $img) { if (!isset($imagesByProduct[$img['product_id']])) $imagesByProduct[$img['product_id']] = $img['url']; }
    } catch (PDOException $e) { }
}
?>

<style>
    /* Wishlist page compact styles */
    .wishlist-wrap { max-width:1200px; margin: 18px auto 40px; padding: 0 16px; }
    .wishlist-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; }
    .wishlist-header h2 { font-size:1.6rem; color:#0ea5ff; margin:0; }
    .wishlist-empty { color:#64748b; font-size:1.02rem; padding:28px; background:#fff; border-radius:10px; text-align:center; }
    .wishlist-grid { display:grid; grid-template-columns: repeat(5, 1fr); gap:22px; }
    .wishlist-card { background:#0f1724; color:#e6eefb; border-radius:12px; padding:14px; border:1px solid #21303a; display:flex; flex-direction:column; align-items:center; position:relative; box-shadow: 0 6px 20px #0008; }
    .wishlist-card .thumb img { width:100%; height:160px; object-fit:cover; border-radius:8px; margin-bottom:10px; }
    .wishlist-card h4 { margin:6px 0; font-size:1.02rem; color:#0ea5ff; text-align:center; }
    .wishlist-card .price { font-weight:800; color:#0ea5ff; margin-bottom:10px; }
    .wishlist-actions { display:flex; gap:10px; width:100%; justify-content:center; }
    .wishlist-actions .btn { padding:10px 16px; border-radius:10px; font-weight:700; }
    .btn-add { background:linear-gradient(90deg,#0ea5ff,#2563eb); color:#fff; }
    .btn-remove { background:#1f2937; color:#fff; border:1px solid #334155; }
    .btn-remove:hover { background:#ef4444; border-color:#ef4444; }
    .btn-remove-circle { position:absolute; right:12px; top:12px; width:36px; height:36px; border-radius:50%; background:#111827; color:#fff; border:1px solid #2b3942; display:inline-flex; align-items:center; justify-content:center; font-size:18px; cursor:pointer; }
    .pagination { display:flex; gap:16px; justify-content:center; margin:48px 0 0 0; }
    .pagination .btn { border-radius:10px; padding:10px 14px; }
    @media (max-width:1100px) { .wishlist-grid { grid-template-columns: repeat(4,1fr); } }
    @media (max-width:900px) { .wishlist-grid { grid-template-columns: repeat(3,1fr); } }
    @media (max-width:700px) { .wishlist-grid { grid-template-columns: repeat(2,1fr); } .btn-add{padding:8px 12px} }
    @media (max-width:480px) { .wishlist-grid { grid-template-columns: 1fr; } .wishlist-wrap{padding:0 8px} }
</style>

<div class="wishlist-wrap">
    <div class="wishlist-header">
        <h2>Wishlist của bạn</h2>
        <div class="wishlist-count"><?php echo $totalProducts; ?> sản phẩm</div>
    </div>

    <?php if (empty($products)): ?>
        <div class="wishlist-empty">Danh sách yêu thích của bạn đang trống. Ghé thăm <a href="index.php">cửa hàng</a> để thêm sản phẩm.</div>
    <?php else: ?>
        <div class="grid wishlist-grid">
            <?php foreach ($products as $p): ?>
                <div class="wishlist-card">
                    <?php $img = $imagesByProduct[$p['id']] ?? 'assets/images/product-placeholder.png'; ?>
                    <button type="submit" form="remove-<?php echo $p['id']; ?>" class="btn-remove-circle" title="Xóa">&times;</button>
                    <div class="thumb"><a href="product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a></div>
                    <h4><?php echo htmlspecialchars($p['name']); ?></h4>
                    <div class="price">$<?php echo number_format($p['price'],2); ?></div>
                    <div class="wishlist-actions">
                        <form class="ajax-add-cart" method="post" action="cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-add" type="submit">Thêm vào giỏ</button>
                        </form>
                        <form id="remove-<?php echo $p['id']; ?>" class="ajax-wishlist-remove" method="post" action="wishlist.php">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-remove">Xóa</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?p=<?php echo $currentPage - 1; ?>" class="btn">‹ Trước</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?p=<?php echo $i; ?>" class="btn <?php echo $i === $currentPage ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?p=<?php echo $currentPage + 1; ?>" class="btn">Tiếp ›</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
 
