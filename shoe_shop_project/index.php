<?php
require_once __DIR__ . '/includes/header.php';
// Load banners (if table exists)
$banners = [];
try {
    $banners = $db->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY id DESC LIMIT 3")->fetchAll();
} catch (PDOException $e) { /* ignore if table missing */ }

// New products (latest by id)
$newProducts = [];
try {
    $newProducts = $db->query('SELECT p.*, (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock FROM products p ORDER BY (total_stock > 0) DESC, id DESC LIMIT 7')->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Best sellers (by quantity in order_items) - fallback to popular by price if not available
$bestSellers = [];
try {
    $bestSellers = $db->query("
        SELECT p.*, 
               COALESCE(SUM(oi.quantity),0) AS sold,
               (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock
        FROM products p 
        LEFT JOIN order_items oi ON p.id = oi.product_id 
        GROUP BY p.id 
        ORDER BY (total_stock > 0) DESC, sold DESC, p.id DESC 
        LIMIT 7
    ")->fetchAll();
} catch (PDOException $e) {
    try {
        $bestSellers = $db->query('SELECT * FROM products ORDER BY price DESC LIMIT 7')->fetchAll();
    } catch (PDOException $e) { /* ignore */ }
}

// Categories
$categories = [];
try {
    $categories = $db->query('SELECT * FROM categories LIMIT 6')->fetchAll();
} catch (PDOException $e) { /* ignore */ }

// Fetch products for each category
$productsByCategory = [];
$categoryProductCounts = [];
if (!empty($categories)) {
    $categoryIds = array_column($categories, 'id');
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    // Get total product count for each category
    $countStmt = $db->prepare("SELECT category_id, COUNT(*) as total FROM products WHERE category_id IN ($placeholders) GROUP BY category_id");
    $countStmt->execute($categoryIds);
    $categoryProductCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Use a window function to get top 5 products per category efficiently
    // This is more efficient than N+1 queries in a loop.
    // Note: Requires MySQL 8+ or SQLite 3.25+
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $productSql = "
        WITH RankedProducts AS (
            SELECT 
                p.*,
                (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock,
                ROW_NUMBER() OVER (PARTITION BY p.category_id ORDER BY (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) > 0 DESC, p.id DESC) as rn
            FROM products p
            WHERE p.category_id IN ($placeholders)
        )
        SELECT * FROM RankedProducts WHERE rn <= 7
    ";

    $productStmt = $db->prepare($productSql);
    $productStmt->execute($categoryIds);
    $allCatProducts = $productStmt->fetchAll();

    foreach ($allCatProducts as $product) {
        $productsByCategory[$product['category_id']][] = $product;
    }
}

// Load main images for products we'll display (new + best)
$imagesByProduct = [];
try {
    $ids = [];
    foreach ($newProducts as $p) { $ids[] = $p['id']; }
    foreach ($bestSellers as $p) { $ids[] = $p['id']; }
    if (isset($allCatProducts)) {
        foreach ($allCatProducts as $p) { $ids[] = $p['id']; }
    }
    $ids = array_values(array_unique($ids));
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT product_id, url, is_main FROM product_images WHERE product_id IN ($placeholders) ORDER BY is_main DESC");
        $stmt->execute($ids);
        $imgs = $stmt->fetchAll();
        foreach ($imgs as $img) {
            if (!isset($imagesByProduct[$img['product_id']])) {
                $imagesByProduct[$img['product_id']] = $img['url'];
            }
        }
    }
} catch (PDOException $e) { /* ignore if table missing */ }

// Load sizes for all displayed products
$sizesByProduct = [];
try {
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st_sizes = $db->prepare("SELECT product_id, size, stock FROM product_sizes WHERE product_id IN ($placeholders) AND stock > 0 ORDER BY size ASC");
        $st_sizes->execute($ids);
        $all_sizes = $st_sizes->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_sizes as $s) {
            $sizesByProduct[$s['product_id']][] = $s['size'];
        }
    }
} catch (Exception $e) {
    // Log error if needed
}

?>

<!-- Thêm CSS cho Swiper -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<style>
    .hero-banner {
    position: relative;
    height: 450px;
    background: linear-gradient(45deg, var(--text-dark), #2c3e50);
    overflow: hidden;
    border-radius: 0; /* Xóa bo góc */
    margin-bottom: 3rem;
    /* Kỹ thuật CSS để tràn viền */
    width: 100vw;
    margin-left: calc(-50vw + 50%);
    margin-right: calc(-50vw + 50%);
    }
    .banner-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: white;
        z-index: 2;
        width: 90%;
        max-width: 800px;
    }
    .banner-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1rem;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    .banner-subtitle {
        font-size: 1.25rem;
        margin-bottom: 2rem;
        opacity: 0.9;
    }
    .banner-cta {
        display: inline-block;
        padding: 1rem 2rem;
        background: var(--accent);
        color: white;
        text-decoration: none;
        border-radius: 30px;
        font-weight: 600;
        transition: transform 0.3s, background 0.3s;
    }
    .banner-cta:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
    }
    .features-section {
        padding: 4rem 0;
        background: var(--bg-light);
    }
    .features-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 2rem; 
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    .feature-card {
        text-align: center;
        padding: 2rem;
        background: var(--bg-white);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: transform 0.3s;
    }
    .feature-card:hover {
        transform: translateY(-5px);
    }
    .feature-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--accent);
    }
    .feature-title {
        font-size: 1.2rem;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .feature-desc {
        color: #6c757d;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .section-title {
        text-align: center;
        margin-bottom: 3rem;
    }
    .section-title h2 {
        font-size: 2.5rem;
        color: var(--text-dark);
        margin-bottom: 1rem;
    }
    .section-title p {
        color: var(--text-muted);
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
    }
    .banner-slider {
        /* Xóa CSS tràn viền cũ, vì giờ section đã nằm ngoài container */
        width: 100%;
        margin-top: 0;
    }
    .slides {
        display: flex; /* Giữ nguyên */
        /* Xóa bo góc và đổ bóng để ảnh tràn viền */
        border-radius: 0;
        box-shadow: none;
        position: relative; /* For absolute positioning of slides */
        height: 400px; /* Set a fixed height */
    }
    .slide {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        opacity: 0;
        transition: opacity 0.8s ease-in-out;
    }
    .slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .slider-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255,255,255,0.9);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 2;
        transition: background 0.3s, transform 0.2s;
    }
    .slider-nav.prev { left: 20px; }
    .slider-nav.next { right: 20px; }
    .slider-nav:hover {
        background: white;
        transform: scale(1.1);
    }
    .slide.active {
        opacity: 1;
        z-index: 1;
    }
    .slider-dots {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 10px;
        z-index: 2;
    }
    .slider-dots .dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        cursor: pointer;
        transition: background 0.3s, transform 0.2s;
    }
    .slider-dots .dot:hover {
        transform: scale(1.2);
    }
    .slider-dots .dot.active {
        background: white;
    }
    @media (max-width: 992px) {
        .features-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 576px) {
        .hero-banner {
            height: 400px;
        }
        .banner-title {
            font-size: 2rem;
        }
        .features-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if (!empty($banners)): ?>
    <section class="banner-slider">
        <div class="slides">
            <?php foreach ($banners as $b): ?>
                <div class="slide">
                <?php if (!empty($b['link'])): ?><a href="<?php echo htmlspecialchars($b['link']); ?>"><?php endif; ?>
                    <img src="<?php echo htmlspecialchars($b['image_url'] ?? 'assets/images/banner/banner2.jpg'); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? 'Banner'); ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php if (!empty($b['link'])): ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="slider-nav prev" aria-label="Previous slide">‹</button>
        <button class="slider-nav next" aria-label="Next slide">›</button>
        <div class="slider-dots"></div>
    </section>
<?php endif; ?>

<section class="hero-banner">
        <div class="banner-content">
            <h1 class="banner-title">Step into Style</h1>
            <p class="banner-subtitle">Discover our latest collection of trendy and comfortable footwear for every occasion</p>
            <a href="<?php echo BASE_URL; ?>category.php?sort=newest" class="banner-cta">Shop Now</a>
        </div>
</section>

<section class="features-section">
        <div class="section-title">
            <h2>Why Choose Us</h2>
            <p>Experience the best in footwear shopping with our premium services and guarantees</p>
        </div>
        <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">🚚</div>
            <h3 class="feature-title">Free Shipping</h3>
            <p class="feature-desc">Free shipping on all orders over $100. Quick delivery nationwide.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">↩️</div>
            <h3 class="feature-title">Easy Returns</h3>
            <p class="feature-desc">30-day hassle-free returns. Your satisfaction guaranteed.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">💎</div>
            <h3 class="feature-title">Premium Quality</h3>
            <p class="feature-desc">Authentic products from top brands with quality guarantee.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">🎁</div>
            <h3 class="feature-title">Special Offers</h3>
            <p class="feature-desc">Regular discounts and exclusive deals for our customers.</p>
        </div>
    </div>
</section>
<?php if (!empty($newProducts)): ?>
    <style>
        .product-section {
            padding: 4rem 0;
            background: var(--bg-white);
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            padding: 0 1rem; 
            max-width: 1200px;
            margin: 0 auto;
        }
        .product-card {
            background: var(--bg-white);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--border);
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-image {
            position: relative;
            padding-top: 100%;
            overflow: hidden;
        }
        .product-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        .product-info {
            padding: 1.5rem;
        }
        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            text-decoration: none;
        }
        .product-name:hover {
            color: var(--accent);
        }
        .product-price {
            color: var(--text-dark);
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .product-actions {
            display: flex; 
            justify-content: center; 
            gap: 8px; 
            margin-top: 10px; 
        }
        .product-actions .btn {
            font-size: 0.98em; 
            padding: 8px 14px; 
            border-radius: 7px; 
        }
        .product-actions .ajax-add-cart .btn {
            background: linear-gradient(90deg,#0ea5ff 60%,#2563eb 100%);
            color: #fff;
        }
        .product-actions .ajax-wishlist .btn {
            background: #f1f5f9; 
            color: #0ea5ff; 
            border: 1px solid #bae6fd; 
        }
        .product-actions .ajax-wishlist .btn:hover {
            background: #0ea5ff; 
            color: #fff; 
        }
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .section-header h2 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        .section-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        .view-more {
            text-align: center;
            margin-top: 2rem;
        }
        .view-more-link {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: var(--bg-light);
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .view-more-link:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        /* Styles for size selection, copied from category.php */
        .product-sizes {
            display: none; /* Ẩn mặc định */
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }
        .product-sizes.active { display: flex; } /* Hiện khi có class active */
        .product-sizes .btn-size {
            font-size: 0.85em; padding: 6px 10px; background: #e2e8f0;
        }
        .product-sizes .btn-size:hover {
            background: #0ea5ff; color: #fff;
        }
    </style>
    <style>
        .out-of-stock-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(220, 53, 69, 0.95); /* Badge color from suggestion */
            color: white;
            padding: 4px 8px;
            font-size: 0.8em;
            font-weight: bold;
            border-radius: 4px;
            z-index: 1;
        }
    </style>
    <style>
        /* Thay thế phần CSS từ dòng product-section trở đi */
        .product-carousel-wrapper .product {
            background: var(--bg-white);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--border);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-carousel-wrapper .product:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .product-carousel-wrapper .product .thumb {
            position: relative;
            padding-top: 100%;
            overflow: hidden;
            background: #f8f9fa;
        }

        .product-carousel-wrapper .product .thumb img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-carousel-wrapper .product:hover .thumb img {
            transform: scale(1.05);
        }

        .product-carousel-wrapper .product h3,
        .product-carousel-wrapper .product h4 {
            padding: 0 15px;
            margin: 15px 0 10px;
            font-size: 0.95rem;
            font-weight: 600;
            height: 2.8rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.4;
        }

        .product-carousel-wrapper .product h3 a,
        .product-carousel-wrapper .product h4 a {
            color: var(--text-dark);
            text-decoration: none;
        }

        .product-carousel-wrapper .product .price {
            padding: 0 15px;
            color: var(--accent);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .product-carousel-wrapper .product .product-actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 0 15px 15px;
            margin-top: auto;
        }

        .product-carousel-wrapper .product .product-actions .btn {
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .product-carousel-wrapper .product .product-actions button.btn:first-child {
            flex: 1;
            background: linear-gradient(90deg, #0ea5ff 60%, #2563eb 100%);
            color: #fff;
        }

        .product-carousel-wrapper .product .product-actions button.btn:first-child:hover:not(:disabled) {
            background: linear-gradient(90deg, #0c8ad8 60%, #1d4ed8 100%);
            transform: translateY(-2px);
        }

        .product-carousel-wrapper .product .product-actions button.btn:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }
    </style>
    <style>
        /* Phải có overflow hidden để các sản phẩm thừa ẩn đi */
        .product-carousel-wrapper .swiper {
            overflow: hidden;
        }

        /* Cho phép Swiper tự chia đều width */
        .product-carousel-wrapper .swiper-slide {
            width: auto !important;
            flex-shrink: 0 !important; /* Tương đương flex: 0 0 auto */
        }

        .product-carousel-wrapper .product {
            width: 100%;
        }
    </style>

    <section class="product-section">
        <style>
            /* CSS được sao chép từ category.php để đồng bộ giao diện */
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
            .product .product-main {
                display: flex;
                flex-direction: column;
                flex-grow: 1; /* Cho phép phần này co giãn */
            }
            .product h3, .product h4 { font-size: 1.13em; margin: 8px 0 6px 0; font-weight: 700; color: #2563eb; flex-grow: 1; /* Cho phép tên sản phẩm co giãn để đẩy các phần tử khác xuống */ }
            .product p.price { font-size: 1.08em; color: #0ea5ff; margin: 0 0 8px 0; font-weight: 700; }
            .product-actions .btn-wishlist { background: #f1f5f9; color: #0ea5ff; border: 1px solid #bae6fd; }
            .product-actions .btn-wishlist:hover { background: #0ea5ff; color: #fff; }
        </style>

        <div class="section-header">
            <h2>New Arrivals</h2>
            <p>Những sản phẩm mới nhất vừa cập bến</p>
        </div>
        <div class="product-carousel-wrapper">
            <div class="swiper product-carousel">
                <div class="swiper-wrapper">
                <?php foreach ($newProducts as $p): ?>
                <div class="swiper-slide product">
                    <div class="product-main">
                        <div class="thumb">
                            <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                                <div class="out-of-stock-badge">Hết hàng</div>
                            <?php endif; ?>
                            <?php $img = $imagesByProduct[$p['id']] ?? BASE_URL . 'assets/images/product-placeholder.png'; ?>
                            <a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                        </div>
                        <h3><a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($p['name']); ?></a></h3>
                        <p class="price"><?php echo number_format($p['price'], 0); ?>₫</p>
                        <div class="product-actions">
                            <?php if (isset($p['total_stock']) && $p['total_stock'] > 0 && !empty($sizesByProduct[$p['id']])): ?>
                                <button class="btn btn-choose-size" data-product-id="<?php echo $p['id']; ?>">Thêm vào giỏ hàng</button>
                            <?php else: ?>
                                <button class="btn" disabled>Thêm vào giỏ hàng</button>
                            <?php endif; ?>
                            <form class="ajax-wishlist" method="post" action="<?php echo BASE_URL; ?>wishlist.php">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <button class="btn btn-wishlist" type="submit">❤</button>
                            </form>
                        </div>
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
            </div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
            <div class="section-footer">
                <a href="<?php echo BASE_URL; ?>category.php?sort=newest" class="section-view-more">View more →</a>
            </div>
    </section>
<?php endif; ?>

<?php if (!empty($bestSellers)): ?><section class="product-section">
    <div class="section-header"><h2>Best Sellers</h2></div>
    <div class="product-carousel-wrapper">
        <div class="swiper product-carousel">
            <div class="swiper-wrapper">
            <?php foreach ($bestSellers as $p): ?>
            <div class="swiper-slide product">
                <div class="product-main">
                    <div class="thumb">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                            <div class="out-of-stock-badge">Hết hàng</div>
                        <?php endif; ?>
                        <?php $img = $imagesByProduct[$p['id']] ?? BASE_URL . 'assets/images/product-placeholder.png'; ?>
                        <a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                    </div>
                    <h3><a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($p['name']); ?></a></h3>
                    <p class="price"><?php echo number_format($p['price'], 0); ?>₫</p>
                    <div class="product-actions">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] > 0 && !empty($sizesByProduct[$p['id']])): ?>
                            <button class="btn btn-choose-size" data-product-id="<?php echo $p['id']; ?>">Thêm vào giỏ hàng</button>
                        <?php else: ?>
                            <button class="btn" disabled>Thêm vào giỏ hàng</button>
                        <?php endif; ?>
                        <form class="ajax-wishlist" method="post" action="<?php echo BASE_URL; ?>wishlist.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <button class="btn btn-wishlist" type="submit">❤</button>
                        </form>
                    </div>
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
        </div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
        <div class="section-footer">
            <a href="<?php echo BASE_URL; ?>category.php?sort=bestsellers" class="section-view-more">View more →</a>
        </div>
</section>
<?php endif; ?>

<?php if (!empty($categories)): ?>
    <div class="section-header"><h2>Categories</h2></div>
    <?php foreach ($categories as $c): ?>
        <section class="product-section">
            <div class="section-header">
                <h3><?php echo htmlspecialchars($c['name']); ?></h3>
            </div>
            <div class="product-carousel-wrapper">
                <div class="swiper product-carousel">
                    <div class="swiper-wrapper">
                        <?php if (!empty($productsByCategory[$c['id']])): foreach ($productsByCategory[$c['id']] as $p): ?>
                        <div class="swiper-slide product">
                            <div class="product-main">
                                <div class="thumb">
                                    <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                                        <div class="out-of-stock-badge">Hết hàng</div>
                                    <?php endif; ?>
                                    <?php $img = $imagesByProduct[$p['id']] ?? BASE_URL . 'assets/images/product-placeholder.png'; ?>
                                    <a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                                </div>
                                <h4><a href="<?php echo BASE_URL; ?>product.php?id=<?php echo $p['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($p['name']); ?></a></h4>
                                <p class="price"><?php echo number_format($p['price'], 0); ?>₫</p>
                                <div class="product-actions">
                                    <?php if (isset($p['total_stock']) && $p['total_stock'] > 0 && !empty($sizesByProduct[$p['id']])): ?>
                                        <button class="btn btn-choose-size" data-product-id="<?php echo $p['id']; ?>">Thêm vào giỏ hàng</button>
                                    <?php else: ?>
                                        <button class="btn" disabled>Thêm vào giỏ hàng</button>
                                    <?php endif; ?>
                                    <form class="ajax-wishlist" method="post" action="<?php echo BASE_URL; ?>wishlist.php">
                                        <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                        <button class="btn btn-wishlist" type="submit">❤</button>
                                    </form>
                                </div>
                            </div>
                            <div class="product-sizes" id="sizes-for-<?php echo $p['id']; ?>">
                                <?php if (!empty($sizesByProduct[$p['id']])): ?>
                                    <?php foreach ($sizesByProduct[$p['id']] as $size): ?><button class="btn btn-size" data-product-id="<?php echo $p['id']; ?>" data-size="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></button><?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
            <div class="section-footer">
                <a href="<?php echo BASE_URL; ?>category.php?category_id[]=<?php echo $c['id']; ?>" class="section-view-more">View more →</a>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<style>
    .testimonials-section {
        padding: 4rem 0;
        background: var(--bg-light);
    }
    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        max-width: 1200px; 
        margin: 0 auto;
        padding: 0 1rem;
    }
    .testimonial-card {
        background: var(--bg-white);
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .testimonial-text {
        color: var(--text-muted);
        font-size: 1.1rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        font-style: italic;
    }
    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .author-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .author-info h4 {
        color: var(--text-dark);
        margin: 0;
        font-size: 1.1rem;
    }
    .author-info p {
        color: var(--text-muted);
        margin: 0;
        font-size: 0.9rem;
    }
    .newsletter-section {
        padding: 4rem 0;
        background: linear-gradient(45deg, var(--text-dark), #2c3e50);
        color: white;
        text-align: center;
    }
    .newsletter-content {
        max-width: 600px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    .newsletter-title {
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    .newsletter-desc {
        opacity: 0.9;
        margin-bottom: 2rem;
    }
    .newsletter-form {
        display: flex;
        gap: 1rem;
        margin: 0 auto;
        max-width: 500px;
    }
    .newsletter-input {
        flex: 1;
        padding: 1rem;
        border: none;
        border-radius: 30px;
        font-size: 1rem;
    }
    .newsletter-button {
        padding: 1rem 2rem;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 30px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s, transform 0.3s;
    }
    .newsletter-button:hover {
        background: var(--accent-hover);
    }
    @media (max-width: 992px) {
        .testimonials-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 768px) {
        .testimonials-grid {
            grid-template-columns: 1fr;
        }
        .newsletter-form {
            flex-direction: column;
        }
        .newsletter-button {
            width: 100%;
        }
    }
</style>

<section class="testimonials-section">
        <div class="section-title">
            <h2>What Our Customers Say</h2>
            <p>Real experiences from our satisfied customers</p>
        </div>
        <div class="testimonials-grid">
        <div class="testimonial-card">
            <p class="testimonial-text">"Amazing selection of shoes! The quality is outstanding, and the delivery was super fast. Will definitely shop here again!"</p>
            <div class="testimonial-author">
                <div class="author-avatar">👤</div>
                <div class="author-info">
                    <h4>John Smith</h4>
                    <p>Verified Buyer</p>
                </div>
            </div>
        </div>
        <div class="testimonial-card">
            <p class="testimonial-text">"The customer service is exceptional. They helped me find the perfect size, and the shoes are incredibly comfortable."</p>
            <div class="testimonial-author">
                <div class="author-avatar">👤</div>
                <div class="author-info">
                    <h4>Sarah Johnson</h4>
                    <p>Verified Buyer</p>
                </div>
            </div>
        </div>
        <div class="testimonial-card">
            <p class="testimonial-text">"Great prices and even better quality. The return process was smooth when I needed a different size. Highly recommend!"</p>
            <div class="testimonial-author">
                <div class="author-avatar">👤</div>
                <div class="author-info">
                    <h4>Michael Brown</h4>
                    <p>Verified Buyer</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="newsletter-section">
    <div class="newsletter-content">
        <h2 class="newsletter-title">Stay in Step with Us</h2>
        <p class="newsletter-desc">Subscribe to our newsletter for exclusive offers, new arrivals, and shoe care tips!</p>
        <form class="newsletter-form" action="#" method="POST">
            <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
            <button type="submit" class="newsletter-button">Subscribe</button>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slider = document.querySelector('.banner-slider');
    if (slider) {
        const slides = Array.from(slider.querySelectorAll('.slide'));
        const prevBtn = slider.querySelector('.slider-nav.prev');
        const nextBtn = slider.querySelector('.slider-nav.next');
        const dotsContainer = slider.querySelector('.slider-dots');
        let currentIndex = 0;
        let slideInterval;

        if (slides.length === 0) return;

        function goToSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
            if (dotsContainer) {
                Array.from(dotsContainer.children).forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
            }
            currentIndex = index;
        }

        function nextSlide() {
            const nextIndex = (currentIndex + 1) % slides.length;
            goToSlide(nextIndex);
        }

        function prevSlide() {
            const prevIndex = (currentIndex - 1 + slides.length) % slides.length;
            goToSlide(prevIndex);
        }

        function startSlider() {
            stopSlider(); // Clear any existing interval
            slideInterval = setInterval(nextSlide, 10000); // Change slide every 10 seconds
        }

        function stopSlider() {
            clearInterval(slideInterval);
        }

        if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', () => { prevSlide(); startSlider(); });
            nextBtn.addEventListener('click', () => { nextSlide(); startSlider(); });
        }

        // Initial setup
        goToSlide(0);
        if (slides.length > 1) {
            startSlider();
            slider.addEventListener('mouseenter', stopSlider);
            slider.addEventListener('mouseleave', startSlider);
        }
    }
});
</script>

<!-- Thêm thư viện Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.product-carousel-wrapper').forEach(wrapper => {
    const swiperEl = wrapper.querySelector('.swiper');
    if (!swiperEl) return;

    new Swiper(swiperEl, {
      slidesPerView: 5,          // chỉ hiển thị 5 sản phẩm cùng lúc
      slidesPerGroup: 1,          // mỗi lần trượt sang 1 sản phẩm
      spaceBetween: 20,           // khoảng cách giữa các sản phẩm
      loop: false,                // không lặp lại
      watchOverflow: true,
            navigation: {
                nextEl: wrapper.querySelector('.swiper-button-next'),
                prevEl: wrapper.querySelector('.swiper-button-prev'),
            },
            breakpoints: {
        0: { slidesPerView: 2 },
        576: { slidesPerView: 3 },
        992: { slidesPerView: 4 },
        1200: { slidesPerView: 5 },
            }
        });
    });
});
</script>
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
                headers: {
                    'Accept': 'application/json' // Báo cho server biết client muốn nhận JSON
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Chuyển hướng đến trang giỏ hàng sau khi thêm thành công
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
                event.stopPropagation(); // Ngăn sự kiện click lan ra ngoài
                const productCard = this.closest('.product');
                if (!productCard) return;

                const sizesContainer = productCard.querySelector('.product-sizes');

                const isCurrentlyActive = sizesContainer.classList.contains('active');

                // Đóng tất cả các size container khác đang mở
                document.querySelectorAll('.product-sizes.active').forEach(container => {
                    container.classList.remove('active');
                });

                // Nếu container này chưa active, thì mở nó ra
                if (!isCurrentlyActive) {
                    sizesContainer.classList.add('active');
                }
            });
        });

        // Gắn sự kiện cho các nút size
        document.querySelectorAll('.btn-size').forEach(button => {
            button.addEventListener('click', function(event) {
                event.stopPropagation(); // Ngăn sự kiện click lan ra ngoài
                const productId = this.dataset.productId;
                const size = this.dataset.size;
                addToCart(productId, size);
            });
        });

        // Thêm sự kiện để đóng tất cả các size container khi click ra ngoài
        document.addEventListener('click', function(event) {
            // Kiểm tra xem có phải click vào bên trong một product card hay không
            if (!event.target.closest('.product')) {
                document.querySelectorAll('.product-sizes.active').forEach(container => {
                    container.classList.remove('active');
                });
            }
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>