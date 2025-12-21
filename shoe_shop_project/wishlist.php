<?php
// Wishlist page: supports add/remove via POST and renders wishlist as grid
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/helper.php';

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
$productsPerPage = 20; // 4 columns * 5 rows
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

    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock 
        FROM products p 
        WHERE p.id IN ($paginated_placeholders)");
    $stmt->execute($paginated_ids);
    $products = $stmt->fetchAll();

    // load main images
    $imagesByProduct = [];
    try {
        $st = $db->prepare("SELECT product_id, url, is_main FROM product_images WHERE product_id IN ($paginated_placeholders) ORDER BY is_main DESC");
        $st->execute($paginated_ids);
        foreach ($st->fetchAll() as $img) { if (!isset($imagesByProduct[$img['product_id']])) $imagesByProduct[$img['product_id']] = $img['url']; }
    } catch (PDOException $e) { }

    // Load sizes for displayed products
    $sizesByProduct = [];
    try {
        if (!empty($paginated_ids)) {
            $st_sizes = $db->prepare("SELECT product_id, size, stock FROM product_sizes WHERE product_id IN ($paginated_placeholders) AND stock > 0 ORDER BY size ASC");
            $st_sizes->execute($paginated_ids);
            $all_sizes = $st_sizes->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_sizes as $s) {
                $sizesByProduct[$s['product_id']][] = $s['size'];
            }
        }
    } catch (PDOException $e) { }
}
?>

<style>
    .wishlist-wrap {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 16px;
    }
    .wishlist-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .wishlist-header h2 {
        font-size: 2rem;
        color: var(--text-dark);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .wishlist-header .wishlist-count {
        color: var(--text-muted);
        font-size: 1.1rem;
    }
    .wishlist-empty {
        color: var(--text-muted);
        font-size: 1.1rem;
        padding: 40px;
        background: var(--bg-white);
        border-radius: 12px;
        text-align: center;
        border: 1px dashed var(--border);
    }
    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 28px;
    }
    .product {
        background: var(--bg-white);
        border: 1px solid var(--border);
        border-radius: 12px;
        text-align: center;
        padding: 18px 12px 16px 12px;
        box-shadow: var(--shadow-sm);
        transition: box-shadow 0.2s, transform 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .product:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-5px);
    }
    .product .thumb { margin-bottom: 14px; position: relative; }
    .product .thumb img { max-width: 100%; height: 180px; object-fit: cover; border-radius: 8px; }
    .product h4 { font-size: 1.1rem; margin: 8px 0 6px 0; font-weight: 600; color: var(--text-dark); }
    .product p.price { font-size: 1.1rem; color: var(--primary); margin: 0 0 8px 0; font-weight: 700; }
    .product-actions { display: flex; justify-content: center; gap: 8px; margin-top: 10px; }
    .product-actions .btn { font-size: 0.9rem; padding: 8px 14px; border-radius: 7px; }
    .btn-remove-circle { position: absolute; right: 10px; top: 10px; width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.8); color: var(--danger); border: 1px solid var(--border); display: inline-flex; align-items: center; justify-content: center; font-size: 18px; cursor: pointer; z-index: 2; transition: all 0.2s; }
    .btn-remove-circle:hover { background: var(--danger); color: #fff; border-color: var(--danger); transform: scale(1.1); }
    .product-sizes { display: none; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--bg-gray); }
    .product-sizes.active { display: flex; }
    .product-sizes .btn-size { font-size: 0.85em; padding: 6px 10px; background: var(--bg-light); border: 1px solid var(--border); cursor: pointer; border-radius: 4px; }
    .product-sizes .btn-size:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    @media (max-width:900px) { .wishlist-grid { grid-template-columns: repeat(3,1fr); gap: 20px; } }
    @media (max-width:700px) { .wishlist-grid { grid-template-columns: repeat(2,1fr); } }
    @media (max-width:480px) { .wishlist-grid { grid-template-columns: 1fr; } .wishlist-wrap{padding:0 8px} }
</style>

<div class="wishlist-wrap">
    <div class="wishlist-header">
        <h2>Danh sách yêu thích</h2>
        <div class="wishlist-count" style="font-weight: 500; color: #475569;"><?php echo $totalProducts; ?> sản phẩm</div>
    </div>

    <?php if (empty($products)): ?>
        <div class="wishlist-empty">Danh sách yêu thích của bạn đang trống. Ghé thăm <a href="index.php">cửa hàng</a> để thêm sản phẩm.</div>
    <?php else: ?>
        <div class="grid wishlist-grid">
            <?php foreach ($products as $p): ?>
                <div class="product">
                    <?php $img = $imagesByProduct[$p['id']] ?? 'assets/images/product-placeholder.png'; ?>
                    <div class="thumb">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                            <div class="out-of-stock-badge">Hết hàng</div>
                        <?php endif; ?>
                        <form class="ajax-wishlist-remove" method="post" action="wishlist.php">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn-remove-circle" title="Xóa">&times;</button>
                        </form>
                        <a href="product.php/<?php echo createSlug($p['name']); ?>-<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                    </div>
                    <h4><a href="product.php/<?php echo createSlug($p['name']); ?>-<?php echo $p['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($p['name']); ?></a></h4>
                    <p class="price"><?php echo number_format($p['price'], 0); ?>₫</p>
                    <div class="product-actions">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] > 0 && !empty($sizesByProduct[$p['id']])): ?>
                            <button class="btn btn-choose-size" data-product-id="<?php echo $p['id']; ?>">Thêm vào giỏ hàng</button>
                        <?php else: ?>
                            <button class="btn" disabled style="background: #9ca3af; cursor: not-allowed;">Thêm vào giỏ hàng</button>
                        <?php endif; ?>
                    </div>
                    <div class="product-sizes" id="sizes-for-<?php echo $p['id']; ?>">
                        <?php if (!empty($sizesByProduct[$p['id']])): ?>
                            <?php foreach ($sizesByProduct[$p['id']] as $size): ?>
                                <button class="btn btn-size" data-product-id="<?php echo $p['id']; ?>" data-size="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?p=1">« Đầu</a>
                    <a href="?p=<?php echo $currentPage - 1; ?>">‹ Trước</a>
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
                    <a href="?p=<?php echo $i; ?>" class="<?php echo $i === $currentPage ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?p=<?php echo $currentPage + 1; ?>">Tiếp ›</a>
                    <a href="?p=<?php echo $totalPages; ?>">Cuối »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hàm xử lý thêm vào giỏ hàng
    function addToCart(productId, size) {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        formData.append('size', size);

        fetch('cart.php', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'cart.php';
            } else {
                alert('Có lỗi xảy ra, không thể thêm vào giỏ hàng.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi kết nối, vui lòng thử lại.');
        });
    }

    // Gắn sự kiện cho các nút "Thêm vào giỏ hàng"
    document.querySelectorAll('.btn-choose-size').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            const productCard = this.closest('.product');
            if (!productCard) return;

            const sizesContainer = productCard.querySelector('.product-sizes');
            if (!sizesContainer) return;

            const isCurrentlyActive = sizesContainer.classList.contains('active');

            document.querySelectorAll('.product-sizes.active').forEach(container => {
                container.classList.remove('active');
            });

            if (!isCurrentlyActive) {
                sizesContainer.classList.add('active');
            }
        });
    });

    // Gắn sự kiện cho các nút size
    document.querySelectorAll('.btn-size').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const size = this.dataset.size;
            addToCart(productId, size);
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php';
 
