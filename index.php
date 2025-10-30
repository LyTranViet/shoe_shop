<?php
require_once __DIR__ . '/includes/header.php';
$db = get_db();

// Load banners (if table exists)
$banners = [];
try {
    $banners = $db->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY id DESC LIMIT 3")->fetchAll();
} catch (PDOException $e) { /* ignore if table missing */ }

// New products (latest by id)
$newProducts = [];
try {
    $newProducts = $db->query('SELECT p.*, (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock FROM products p ORDER BY id DESC LIMIT 7')->fetchAll();
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
        ORDER BY sold DESC, p.id DESC 
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
                ROW_NUMBER() OVER (PARTITION BY p.category_id ORDER BY p.id DESC) as rn
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
?>

<style>
    .hero-banner {
        position: relative;
        height: 500px;
        background: linear-gradient(45deg, #1a1a1a, #2c3e50);
        overflow: hidden;
        margin-bottom: 3rem;
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
        background: #0056b3;
        color: white;
        text-decoration: none;
        border-radius: 30px;
        font-weight: 600;
        transition: transform 0.3s, background 0.3s;
    }
    .banner-cta:hover {
        background: #003d82;
        transform: translateY(-2px);
    }
    .features-section {
        padding: 4rem 0;
        background: #f8f9fa;
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
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    .feature-card:hover {
        transform: translateY(-5px);
    }
    .feature-icon {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: #0056b3;
    }
    .feature-title {
        font-size: 1.2rem;
        color: #1a1a1a;
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
        color: #1a1a1a;
        margin-bottom: 1rem;
    }
    .section-title p {
        color: #6c757d;
        font-size: 1.1rem;
        max-width: 600px;
        margin: 0 auto;
    }
    .banner-slider {
        margin-top: 2rem;
    }
    .slides {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .slide img {
        width: 100%;
        height: 400px;
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
        transition: background 0.3s;
    }
    .slider-nav.prev { left: 20px; }
    .slider-nav.next { right: 20px; }
    .slider-nav:hover {
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
                    <img src="<?php echo htmlspecialchars($b['image_url'] ?? 'shoe_shop-main/assets/images/banner/banner2.jpg'); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? 'Banner'); ?>">
                <?php if (!empty($b['link'])): ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="slider-nav prev" aria-label="Previous slide">‹</button>
        <button class="slider-nav next" aria-label="Next slide">›</button>
    </section>
<?php endif; ?>

<section class="hero-banner">
    <div class="banner-content">
        <h1 class="banner-title">Step into Style</h1>
        <p class="banner-subtitle">Discover our latest collection of trendy and comfortable footwear for every occasion</p>
        <a href="category.php?sort=newest" class="banner-cta">Shop Now</a>
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
            background: white;
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
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
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
            color: #1a1a1a;
            margin-bottom: 0.5rem;
            text-decoration: none;
        }
        .product-name:hover {
            color: #0056b3;
        }
        .product-price {
            color: #0056b3;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-cart, .btn-wishlist {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-cart {
            background: #0056b3;
            color: white;
        }
        .btn-cart:hover {
            background: #003d82;
        }
        .btn-wishlist {
            background: #f8f9fa;
            color: #1a1a1a;
        }
        .btn-wishlist:hover {
            background: #e9ecef;
        }
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .section-header h2 {
            font-size: 2rem;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
        }
        .section-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .view-more {
            text-align: center;
            margin-top: 2rem;
        }
        .view-more-link {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #f8f9fa;
            color: #1a1a1a;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .view-more-link:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
    </style>

    <section class="product-section">
        <div class="section-header">
            <h2>New Arrivals</h2>
            <p>Be the first to discover our latest shoe collections</p>
        </div>
        <div class="product-grid">
            <?php foreach ($newProducts as $p): ?>
                    <div class="swiper-slide product">
                    <div class="thumb">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                            <div class="out-of-stock-badge">Hết hàng</div>
                        <?php endif; ?>
                        <?php $img = $imagesByProduct[$p['id']] ?? 'assets/images/product-placeholder.png'; ?>
                        <a href="product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                    </div>
                    <h3><a href="product.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a></h3>
                    <p class="price">$<?php echo number_format($p['price'],2); ?></p>
                    <div class="product-actions">
                        <form class="ajax-add-cart" method="post" action="cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn" type="submit" <?= (isset($p['total_stock']) && $p['total_stock'] <= 0) ? 'disabled' : '' ?>>Add to cart</button>
                        </form>
                        <form class="ajax-wishlist" method="post" action="wishlist.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <button class="btn" type="submit">❤</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
    <div class="section-footer">
        <a href="category.php?sort=newest" class="section-view-more">View more →</a>
    </div>
<?php endif; ?>

<?php if (!empty($bestSellers)): ?>
    <div class="section-header"><h2>Best Sellers</h2></div>
    <div class="product-carousel-wrapper">
        <div class="swiper product-carousel">
            <div class="swiper-wrapper">
            <?php foreach ($bestSellers as $p): ?>
                    <div class="swiper-slide product">
                    <div class="thumb">
                        <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                            <div class="out-of-stock-badge">Hết hàng</div>
                        <?php endif; ?>
                        <?php $img = $imagesByProduct[$p['id']] ?? 'assets/images/product-placeholder.png'; ?>
                        <a href="product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a>
                    </div>
                    <h3><a href="product.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a></h3>
                    <p class="price">$<?php echo number_format($p['price'],2); ?></p>
                    <div class="product-actions">
                        <form class="ajax-add-cart" method="post" action="cart.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn" type="submit" <?= (isset($p['total_stock']) && $p['total_stock'] <= 0) ? 'disabled' : '' ?>>Add to cart</button>
                        </form>
                        <form class="ajax-wishlist" method="post" action="wishlist.php">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <button class="btn" type="submit">❤</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-button-next"></div>
    </div>
    <div class="section-footer">
        <a href="category.php?sort=bestsellers" class="section-view-more">View more →</a>
    </div>
<?php endif; ?>

<?php if (!empty($categories)): ?>
    <div class="section-header"><h2>Categories</h2></div>
    <div class="category-sections">
        <?php foreach ($categories as $c): ?>
            <section class="category-showcase">
                <div class="section-header">
                    <h3><?php echo htmlspecialchars($c['name']); ?></h3>
                </div>
                <div class="product-carousel-wrapper">
                    <div class="swiper product-carousel">
                        <div class="swiper-wrapper">
                        <?php if (!empty($productsByCategory[$c['id']])): foreach ($productsByCategory[$c['id']] as $p): ?>
                                <div class="swiper-slide product">
                                <div class="thumb">
                                    <?php if (isset($p['total_stock']) && $p['total_stock'] <= 0): ?>
                                        <div class="out-of-stock-badge">Hết hàng</div>
                                    <?php endif; ?>
                                    <?php $img = $imagesByProduct[$p['id']] ?? 'assets/images/product-placeholder.png'; ?>
                                    <a href="product.php?id=<?php echo $p['id']; ?>"><img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"></a></div>
                                <h4><a href="product.php?id=<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a></h4>
                                <p class="price">$<?php echo number_format($p['price'],2); ?></p>
                            </div>
                        <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
                <?php if (($categoryProductCounts[$c['id']] ?? 0) > 7): ?>
                    <div class="section-footer">
                        <a href="category.php?category_id=<?php echo $c['id']; ?>" class="section-view-more">View all →</a>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
    .testimonials-section {
        padding: 4rem 0;
        background: #f8f9fa;
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
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .testimonial-text {
        color: #495057;
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
        color: #1a1a1a;
        margin: 0;
        font-size: 1.1rem;
    }
    .author-info p {
        color: #6c757d;
        margin: 0;
        font-size: 0.9rem;
    }
    .newsletter-section {
        padding: 4rem 0;
        background: linear-gradient(45deg, #0056b3, #0077cc);
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
        background: #1a1a1a;
        color: white;
        border: none;
        border-radius: 30px;
        cursor: pointer;
        font-weight: 600;
        transition: background 0.3s;
    }
    .newsletter-button:hover {
        background: #333;
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>