<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';
$db = get_db();
$id = (int)($_GET['id'] ?? 0);

// --- XỬ LÝ CÁC HÀNH ĐỘNG REVIEW (POST) ---

// Xóa review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    if (is_logged_in()) {
        $review_id = (int)($_POST['review_id'] ?? 0);
        // Chỉ xóa review nếu nó thuộc về người dùng hiện tại
        $stmt = $db->prepare("DELETE FROM product_reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$review_id, current_user_id()]);
        if ($stmt->rowCount() > 0) {
            flash_set('success', 'Đã xóa đánh giá của bạn.');
        } else {
            flash_set('error', 'Không thể xóa đánh giá này.');
        }
    }
    header('Location: product.php?id=' . $id);
    exit;
}

// Sửa review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_review') {
    if (is_logged_in()) {
        $review_id = (int)($_POST['review_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        // Chỉ sửa review nếu nó thuộc về người dùng hiện tại
        $stmt = $db->prepare("UPDATE product_reviews SET rating = ?, comment = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$rating, $comment, $review_id, current_user_id()]);
        flash_set('success', 'Đã cập nhật đánh giá của bạn.');
    }
    header('Location: product.php?id=' . $id);
    exit;
}

// Thêm review mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    if (!is_logged_in()) {
        flash_set('error','Please login to submit a review');
        header('Location: login.php'); exit;
    }
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        flash_set('error','Rating must be between 1 and 5');
    } elseif ($comment === '') {
        flash_set('error','Please enter a comment');
    } else {
        try {
            $ins = $db->prepare('INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?)');
            $ins->execute([$id, current_user_id(), $rating, $comment]);
            flash_set('success','Thank you for your review');
        } catch (PDOException $e) {
            flash_set('error','Could not save review');
        }
    }
    header('Location: product.php?id=' . $id); 
    exit;
}


// Load product with category, brand, and total stock
$stmt = $db->prepare('
    SELECT p.*, c.name AS category_name, b.name AS brand_name,
           (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN brands b ON p.brand_id = b.id 
    WHERE p.id = ?');
$stmt->execute([$id]);
$prod = $stmt->fetch();

// Include header AFTER processing POST request
require_once __DIR__ . '/includes/header.php';

if (!$prod) {
    echo '<p>Product not found</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Images (main + gallery)
$images = [];
try {
    $st = $db->prepare('SELECT url, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC');
    $st->execute([$id]);
    $images = $st->fetchAll();
} catch (Exception $e) { }
$mainImage = $images[0]['url'] ?? 'assets/images/product-placeholder.png';

// Sizes and stock
$sizes = [];
try {
    $s = $db->prepare('SELECT id, size, stock FROM product_sizes WHERE product_id = ? ORDER BY size ASC');
    $s->execute([$id]);
    $sizes = $s->fetchAll();
} catch (Exception $e) { }

// Reviews
$reviews = [];
try {
    $r = $db->prepare('SELECT pr.*, u.name AS user_name FROM product_reviews pr LEFT JOIN users u ON pr.user_id = u.id WHERE pr.product_id = ? ORDER BY pr.created_at DESC');
    $r->execute([$id]);
    $reviews = $r->fetchAll();
} catch (Exception $e) { }

// Check wishlist status for logged-in user
$inWishlist = false;
if (is_logged_in()) {
    try {
        $w = $db->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?');
        $w->execute([current_user_id(), $id]);
        $inWishlist = (bool)$w->fetch();
    } catch (Exception $e) { }
}

// Vouchers
$vouchers = [];
try {
    $vouchers = $db->query("SELECT code, discount_percent, discount_type FROM coupons WHERE (valid_to IS NULL OR valid_to >= NOW()) AND (valid_from IS NULL OR valid_from <= NOW()) ORDER BY discount_percent DESC")->fetchAll();
} catch (Exception $e) { }

// Lọc vouchers theo discount_type
$productVouchers = array_filter($vouchers, fn($v) => $v['discount_type'] === 'product');
$shippingVouchers = [];
try {
    $shippingVouchers = $db->query("
        SELECT CODE AS code, VALUE AS discount_percent 
        FROM shipping_coupons 
        WHERE active = 1 
        AND (expire_date IS NULL OR expire_date >= CURDATE()) 
        ORDER BY VALUE DESC
    ")->fetchAll();
} catch (Exception $e) { }

// Suggested products
$suggested = [];
try {
    $st2 = $db->prepare('SELECT p.* FROM products p WHERE p.category_id = ? AND p.id != ? LIMIT 7');
    $st2->execute([$prod['category_id'], $id]);
    $suggested = $st2->fetchAll();
    $sids = array_column($suggested, 'id');
    $imagesBy = [];
    if (!empty($sids)) {
        $place = implode(',', array_fill(0, count($sids), '?'));
        $st3 = $db->prepare("SELECT product_id, url, is_main FROM product_images WHERE product_id IN ($place) ORDER BY is_main DESC");
        $st3->execute($sids);
        foreach ($st3->fetchAll() as $img) {
            if (!isset($imagesBy[$img['product_id']])) $imagesBy[$img['product_id']] = $img['url'];
        }
    }
} catch (Exception $e) { }

$is_out_of_stock = (!isset($prod['total_stock']) || $prod['total_stock'] <= 0);
?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // === Hàm format VND ===
    const formatVND = (amount) => Math.round(amount).toLocaleString('vi-VN') + ' đ';

    // === Voucher Copy Logic ===
    document.querySelectorAll('.voucher-code').forEach(button => {
        button.addEventListener('click', function() {
            const code = this.dataset.code;
            navigator.clipboard.writeText(code).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Đã chép!';
                this.disabled = true;
                setTimeout(() => {
                    this.textContent = originalText;
                    this.disabled = false;
                }, 2000);
            }).catch(() => {
                alert('Không thể sao chép. Vui lòng thử lại.');
            });
        });
    });

    // === Paste & Validate Buttons ===
    const pasteBtn = document.getElementById('paste-and-validate-btn');
    if (pasteBtn) pasteBtn.addEventListener('click', () => handlePasteAndValidate('product'));

    const pasteShippingBtn = document.getElementById('paste-and-validate-shipping-btn');
    if (pasteShippingBtn) pasteShippingBtn.addEventListener('click', () => handlePasteAndValidate('shipping'));

    // === Xử lý dán & validate mã ===
    async function handlePasteAndValidate(type) {
        const isShipping = type === 'shipping';
        const couponInput = document.getElementById(isShipping ? 'coupon-code-shipping' : 'coupon-code-product');
        const resultDiv = document.querySelector(isShipping ? '#shipping-coupon-result' : '.product-actions-form .coupon-result');
        const priceEl = document.querySelector('.price-container .price');
        const originalPriceEl = document.querySelector('.price-container .price-original');
        const originalPrice = parseFloat(priceEl.dataset.originalPrice) || 0;

        resultDiv.textContent = '';
        resultDiv.className = 'coupon-result';

        try {
            // Cố gắng đọc từ clipboard và dán vào input.
            // Lỗi sẽ được bỏ qua một cách âm thầm.
            const clipboardText = await navigator.clipboard.readText();
            if (clipboardText.trim()) {
                couponInput.value = clipboardText.trim();
            }
        } catch (err) {
            // Bỏ qua lỗi clipboard (ví dụ: không có quyền trên HTTP)
            // Bỏ qua lỗi clipboard một cách âm thầm, không cần log ra console.
        }
        // Luôn luôn chạy validation sau khi đã cố gắng dán.
        await handleManualValidate(type);
    }

    // === Gõ tay + Enter ===
    document.getElementById('coupon-code-product')?.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleManualValidate('product');
        }
    });

    document.getElementById('coupon-code-shipping')?.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleManualValidate('shipping');
        }
    });


    async function handleManualValidate(type) {
        const isShipping = type === 'shipping';
        const input = document.getElementById(isShipping ? 'coupon-code-shipping' : 'coupon-code-product');
        const code = input.value.trim();
        if (!code) return;

        const url = isShipping ? 'validate_shipping_coupon.php' : 'validate_coupon.php';
        const formData = new FormData();
        formData.append('code', code);

        try {
            const response = await fetch(url, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            const resultDiv = document.querySelector(isShipping ? '#shipping-coupon-result' : '.product-actions-form .coupon-result');
            const priceEl = document.querySelector('.price-container .price');
            const originalPriceEl = document.querySelector('.price-container .price-original');
            const originalPrice = parseFloat(priceEl.dataset.originalPrice) || 0;

            resultDiv.textContent = '';
            resultDiv.className = 'coupon-result';

            if (data.success) {
                if (isShipping) {
                    resultDiv.innerHTML = data.message || 'Mã hợp lệ!';
                    resultDiv.classList.add('success');
                    const coupon = data.coupon || {};
                    document.getElementById('validated_shipping_coupon_code').value = coupon.code || code;
                    document.getElementById('shipping_discount_amount').value = coupon.value || 0;
                } else {
                    const discountPercent = parseFloat(data.discount_percent) || 0;
                    const newPrice = originalPrice * (1 - discountPercent / 100);
                    resultDiv.innerHTML = `Áp dụng thành công! Giảm <strong>${discountPercent}%</strong>`;
                    resultDiv.classList.add('success');
                    originalPriceEl.textContent = formatVND(originalPrice);
                    originalPriceEl.style.display = 'block';
                    priceEl.textContent = formatVND(newPrice);
                }
            } else {
                resultDiv.textContent = data.message || 'Mã không hợp lệ.';
                resultDiv.classList.add('error');
                if (!isShipping) {
                    originalPriceEl.style.display = 'none';
                    priceEl.textContent = formatVND(originalPrice);
                }
            }
        } catch (err) {
            console.error(err);
            const resultDiv = document.querySelector(isShipping ? '#shipping-coupon-result' : '.product-actions-form .coupon-result');
            resultDiv.textContent = 'Lỗi hệ thống khi kiểm tra mã.';
            resultDiv.classList.add('error');
        }
    }
}); // DOMContentLoaded
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewSections = document.querySelectorAll('.review');

    // Hàm khởi tạo star rating cho một form cụ thể
    function initializeStarRating(form) {
        const starWrapper = form.querySelector('.star-rating-js');
        if (!starWrapper) return;

        const stars = starWrapper.querySelectorAll('.star');
        const ratingInput = form.querySelector('input[name="rating"]');

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const value = parseInt(star.dataset.value, 10);
                ratingInput.value = value;
                updateStars(stars, value);
            });
        });
    }

    reviewSections.forEach(review => {
        const editBtn = review.querySelector('.btn-edit-review');
        const cancelBtn = review.querySelector('.btn-cancel-edit');

        if (editBtn) {
            editBtn.addEventListener('click', function() {
                const isCurrentlyEditing = review.classList.contains('editing');

                // Đóng tất cả các form đang mở khác
                reviewSections.forEach(r => r.classList.remove('editing'));

                // Nếu review này chưa mở, thì mở nó ra
                if (!isCurrentlyEditing) {
                    review.classList.add('editing');
                    // Khởi tạo star rating cho form edit này
                    const editForm = review.querySelector('.review-edit-form');
                    if (editForm) {
                        initializeStarRating(editForm);
                        updateStars(editForm.querySelectorAll('.star'), editForm.querySelector('input[name="rating"]').value);
                    }
                }
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                review.classList.remove('editing');
            });
        }
    });

    // Hàm cập nhật hiển thị sao
    function updateStars(stars, rating) {
        stars.forEach(star => {
            const starValue = parseInt(star.dataset.value, 10);
            star.textContent = starValue <= rating ? '★' : '☆';
            star.classList.toggle('selected', starValue <= rating);
        });
    }

    // Khởi tạo cho form "Add review"
    initializeStarRating(document.querySelector('.add-review-form'));
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- JS-powered Star Rating ---
    const starRatingWrapper = document.querySelector('.star-rating-js');
    if (starRatingWrapper) {
        const stars = starRatingWrapper.querySelectorAll('.star');
        const ratingInput = document.getElementById('rating-value');

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const value = parseInt(star.dataset.value, 10);
                ratingInput.value = value;
                stars.forEach((s, i) => {
                    s.textContent = i < value ? '★' : '☆';
                    s.classList.toggle('selected', i < value);
                });
            });
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sizeOptionsContainer = document.querySelector('.size-options-boxes');
    const stockDisplaySpan = document.getElementById('size-stock-display');
    const stockMessageP = document.getElementById('stock-message');

    // Hàm cập nhật hiển thị tồn kho
    function updateStockDisplay() {
        const selectedRadio = document.querySelector('input[name="size"]:checked');
        if (selectedRadio) {
            const stock = selectedRadio.dataset.stock;
            if (stockDisplaySpan) {
                stockDisplaySpan.textContent = `${stock} sản phẩm`;
            }
            // Ẩn thông báo lỗi nếu có
            if (stockMessageP) {
                stockMessageP.style.display = 'none';
            }
        } else if (stockDisplaySpan) {
            // Nếu không có size nào được chọn (trường hợp tất cả đều hết hàng)
            stockDisplaySpan.textContent = 'Hết hàng';
        }
    }

    // Lắng nghe sự kiện khi người dùng chọn size
    if (sizeOptionsContainer) {
        sizeOptionsContainer.addEventListener('change', function(event) {
            if (event.target.name === 'size') {
                updateStockDisplay();
            }
        });

        // Xử lý khi click vào label của size đã hết hàng
        sizeOptionsContainer.addEventListener('click', function(event) {
            const label = event.target.closest('label');
            if (label) {
                const radioId = label.getAttribute('for');
                const radio = document.getElementById(radioId);
                if (radio && radio.disabled) {
                    if (stockMessageP) {
                        stockMessageP.textContent = `Size ${radio.value} đã hết hàng. Vui lòng chọn size khác.`;
                        stockMessageP.style.display = 'block';
                    }
                }
            }
        });
    }

    // Cập nhật lần đầu khi tải trang
    updateStockDisplay();
});
</script>

<div class="product-page-container">
    <div class="product-detail">
        <div class="gallery">
            <div class="main-image"><img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>"></div>
            <?php if (!empty($images)): ?>
            <?php if ($is_out_of_stock): ?>
                <div class="out-of-stock-badge">Hết hàng</div>
            <?php endif; ?>
            <div class="thumbs-wrap">
                <button class="thumb-nav prev" aria-label="Previous thumbnails">‹</button>
                <div class="thumbs">
                <?php foreach ($images as $img):
                    $url = $img['url'];
                    $isVideo = preg_match('/\.(mp4|webm|ogg)$/i', $url);
                ?>
                    <div class="thumb" data-type="<?php echo $isVideo ? 'video' : 'image'; ?>" data-src="<?php echo htmlspecialchars($url); ?>">
                        <img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                        <?php if ($isVideo): ?><span class="play">Play</span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <button class="thumb-nav next" aria-label="Next thumbnails">›</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="details">
            <h1><?php echo htmlspecialchars($prod['name']); ?></h1>
            <p class="meta">
                Brand: <a href="category.php?brand_id[]=<?php echo $prod['brand_id']; ?>"><?php echo htmlspecialchars($prod['brand_name'] ?? ''); ?></a> | 
                Category: <a href="category.php?category_id[]=<?php echo $prod['category_id']; ?>"><?php echo htmlspecialchars($prod['category_name'] ?? ''); ?></a> | 
                <span class="stock-display">Tồn kho: <span id="size-stock-display">--</span></span>
            </p>
            <!-- Vùng hiển thị tồn kho -->
            <div class="stock-display-container">
                <p id="stock-message" class="stock-message" style="display: none; color: red; font-weight: 500;"></p>
            </div>
            <div class="price-container">
                <p class="price" data-original-price="<?php echo $prod['price']; ?>">
                    <?php echo number_format($prod['price'], 0); ?> đ
                </p>
                <p class="price-original" style="display: none;"></p>
            </div>

            <?php if ($is_out_of_stock): ?>
                <div class="alert-error" style="text-align: center; padding: 15px; margin-top: 20px;">
                    Sản phẩm này hiện đã hết hàng.
                </div>
            <?php else: ?>
                <form class="ajax-add-cart product-actions-form" method="post" action="cart.php">
                    <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                    <div class="form-group size-selector">
                        <label>Size</label>
                        <div class="size-options-boxes">
                            <?php
                            $first_available_checked = false;
                            foreach ($sizes as $sz):
                                $is_disabled = (int)$sz['stock'] <= 0;
                                $is_checked = !$is_disabled && !$first_available_checked;
                                if ($is_checked) $first_available_checked = true;
                            ?>
                                <input type="radio" name="size" value="<?php echo htmlspecialchars($sz['size']); ?>" id="size-<?php echo htmlspecialchars($sz['size']); ?>" <?php echo $is_checked ? 'checked' : ''; ?> <?php echo $is_disabled ? 'disabled' : ''; ?> data-stock="<?php echo (int)$sz['stock']; ?>">
                                <label for="size-<?php echo htmlspecialchars($sz['size']); ?>" class="size-box" title="<?php echo $is_disabled ? 'Hết hàng' : ''; ?>">
                                    <?php echo htmlspecialchars($sz['size']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group quantity-selector">
                        <label>Quantity</label>
                        <div class="quantity-input">
                            <button type="button" class="qty-btn minus" aria-label="Decrease quantity">-</button>
                            <input type="number" name="quantity" value="1" min="1" readonly>
                            <button type="button" class="qty-btn plus" aria-label="Increase quantity">+</button>
                        </div>
                    </div>
                    <div class="form-group coupon-group">
                        <label for="coupon-code-product">Mã giảm giá</label>
                        <div class="input-with-button">
                            <input type="text" id="coupon-code-product" name="coupon_code" placeholder="Dán mã vào đây">
                            <button type="button" id="paste-and-validate-btn" class="btn small">Dán & Kiểm tra</button>
                        </div>
                        <div class="coupon-result"></div>
                    </div>
                    <div class="form-group coupon-group">
                        <label for="coupon-code-shipping">Mã giảm phí vận chuyển</label>
                        <div class="input-with-button">
                            <input type="text" id="coupon-code-shipping" name="shipping_coupon_input" placeholder="Dán mã vào đây" value="<?php echo htmlspecialchars($sessionShippingCoupon ?? ''); ?>">
                            <button type="button" id="paste-and-validate-shipping-btn" class="btn small">Dán & Kiểm tra</button>
                        </div>
                        <div class="coupon-result" id="shipping-coupon-result">
                            <?php if (!empty($shippingCouponMessage)) echo $shippingCouponMessage; ?>
                        </div>
                        <input type="hidden" id="validated_shipping_coupon_code" name="validated_shipping_coupon_code" value="">
                        <input type="hidden" id="shipping_discount_amount" name="shipping_discount_amount" value="0">
                    </div>
                    <div class="form-group full-width">
                        <button class="btn">Add to cart</button>
                        <span class="add-cart-status" aria-live="polite"></span>
                    </div>
                </form>
            <?php endif; ?>

            <form class="ajax-wishlist" method="post" action="wishlist.php">
                <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                <button class="btn secondary" type="submit"><?php echo $inWishlist ? '♥ In wishlist' : '♡ Add to wishlist'; ?></button>
            </form>
        </div>
    </div>

    <!-- Mô tả -->
    <div class="product-section description-section">
        <div class="section-header-styled"><h3>Mô tả sản phẩm</h3></div>
        <div class="desc-content"><?php echo nl2br(htmlspecialchars($prod['description'])); ?></div>
    </div>

    <!-- Voucher sản phẩm -->
    <?php if (!empty($productVouchers)): ?>
    <div class="product-section vouchers-section">
        <h3>Voucher Giảm Giá Sản Phẩm</h3>
        <div class="vouchers-list">
            <?php foreach ($productVouchers as $v): ?>
            <div class="voucher-item">
                <div class="voucher-info">Giảm <?php echo (int)$v['discount_percent']; ?>% giá sản phẩm</div>
                <button class="voucher-code" data-code="<?php echo htmlspecialchars($v['code']); ?>">Copy</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Voucher vận chuyển -->
    <?php if (!empty($shippingVouchers)): ?>
    <div class="product-section vouchers-section">
        <h3>Voucher Giảm Phí Vận Chuyển</h3>
        <div class="vouchers-list">
            <?php foreach ($shippingVouchers as $v): ?>
            <div class="voucher-item">
                <div class="voucher-info">Giảm <?php echo (int)$v['discount_percent']; ?>% phí ship</div>
                <button class="voucher-code" data-code="<?php echo htmlspecialchars($v['code']); ?>">Copy</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reviews -->
    <div class="product-section reviews-section">
        <h3>Reviews</h3>
        <?php if (empty($reviews)): ?>
            <p>No reviews yet.</p>
        <?php else: foreach ($reviews as $rev): 
            $can_edit = is_logged_in() && $rev['user_id'] === current_user_id();
        ?>
            <div class="review" data-review-id="<?php echo $rev['id']; ?>">
                <div class="review-content-wrapper">
                    <div class="review-main-content">
                        <!-- View Mode -->
                        <div class="review-header">
                            <strong><?php echo htmlspecialchars($rev['user_name'] ?? 'Guest'); ?></strong>
                            <span class="review-rating"><?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?></span>
                        </div>
                        <p class="review-comment"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                        <small class="review-date"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></small>
                    </div>

                    <?php if ($can_edit): ?>
                    <div class="review-actions">
                        <button type="button" class="btn-edit-review" title="Sửa"><i class="fi fi-rr-pencil"></i></button>
                        <form method="post" action="product.php?id=<?php echo $id; ?>" onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete_review">
                            <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                            <button type="submit" class="btn-delete-review" title="Xóa"><i class="fi fi-rr-trash"></i></button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Edit Mode (hidden by default) -->
                <?php if ($can_edit): ?>
                <form class="review-edit-form" method="post" action="product.php?id=<?php echo $id; ?>">
                    <input type="hidden" name="action" value="edit_review">
                    <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                    <div class="form-group">
                        <label>Đánh giá của bạn</label>
                        <div class="star-rating-js">
                            <span class="star" data-value="1">☆</span>
                            <span class="star" data-value="2">☆</span>
                            <span class="star" data-value="3">☆</span>
                            <span class="star" data-value="4">☆</span>
                            <span class="star" data-value="5">☆</span>
                        </div>
                        <input type="hidden" name="rating" value="<?php echo $rev['rating']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Comment</label>
                        <textarea name="comment" rows="3" required><?php echo htmlspecialchars($rev['comment']); ?></textarea>
                    </div>
                    <button type="submit" class="btn">Lưu</button>
                    <button type="button" class="btn secondary btn-cancel-edit">Hủy</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

        <?php if (is_logged_in()): ?>
            <div class="add-review-form">
                <h4>Write a review</h4>
                <?php if ($m = flash_get('error')): ?><p style="color:red"><?php echo $m; ?></p><?php endif; ?>
                <?php 
                // Lấy thông báo thành công
                $success_message = flash_get('success');
                // Chỉ hiển thị nếu đó không phải là thông báo đăng nhập mặc định
                if ($success_message && $success_message !== 'Đăng nhập thành công!'): 
                ?>
                    <p style="color:green"><?php echo htmlspecialchars($success_message); ?></p>
                <?php endif; ?>
                <form method="post" action="product.php?id=<?php echo $id; ?>">
                    <input type="hidden" name="action" value="add_review">
                    <div class="form-group">
                        <label>Rating</label>
                        <div class="star-rating-js">
                            <span class="star" data-value="1">☆</span>
                            <span class="star" data-value="2">☆</span>
                            <span class="star" data-value="3">☆</span>
                            <span class="star" data-value="4">☆</span>
                            <span class="star" data-value="5">☆</span>
                        </div>
                        <input type="hidden" name="rating" id="rating-value" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="comment-text">Comment</label>
                        <textarea id="comment-text" name="comment" rows="4" required></textarea>
                    </div>
                    <button class="btn" type="submit">Submit review</button>
                </form>
            </div>
        <?php else: ?>
            <p><a href="login.php">Login</a> to write a review.</p>
        <?php endif; ?>
    </div>

    <!-- Suggested Products -->
    <?php if (!empty($suggested)): ?>
    <div class="product-section suggested-products">
        <h3>Suggested products</h3>
        <div class="product-carousel-wrapper">
            <div class="swiper product-carousel">
                <div class="swiper-wrapper">
                    <?php foreach ($suggested as $s): ?>
                        <?php $simg = $imagesBy[$s['id']] ?? 'assets/images/product-placeholder.png'; ?>
                        <div class="swiper-slide product">
                            <div class="thumb"><a href="product.php?id=<?php echo $s['id']; ?>"><img src="<?php echo htmlspecialchars($simg); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>"></a></div>
                            <h4><a href="product.php?id=<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></a></h4>
                            <p class="price"><?php echo number_format($s['price'], 0); ?> đ</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.product-carousel-wrapper').forEach(wrapper => {
        new Swiper(wrapper.querySelector('.swiper.product-carousel'), {
            slidesPerView: 2,
            spaceBetween: 20,
            navigation: {
                nextEl: wrapper.querySelector('.swiper-button-next'),
                prevEl: wrapper.querySelector('.swiper-button-prev'),
            },
            breakpoints: {
                768: { slidesPerView: 3 },
                992: { slidesPerView: 4 }
            }
        });
    });
});
</script>
<style>
    /* Ẩn form sửa review mặc định */
    .review .review-edit-form { display: none; }
    /* Khi review có class 'editing', hiện form sửa và ẩn nội dung review */
    .review.editing .review-edit-form { display: block; margin-top: 15px; }
    .review.editing .review-header, .review.editing .review-comment, .review.editing .review-date, .review.editing .review-actions { display: none; }
</style>

<style>
    .review-content-wrapper {
        display: flex;
        justify-content: space-between; /* Đẩy nội dung và nút hành động ra hai bên */
        align-items: flex-start; /* Căn các mục theo đầu dòng */
    }
</style>
<style>
    /* CSS cho nút Sửa và Xóa review */
    .review-actions .btn-edit-review,
    .review-actions .btn-delete-review {
        background: none; /* Xóa màu nền */
        border: none; /* Xóa viền */
        cursor: pointer;
        padding: 5px;
        font-size: 16px; /* Có thể điều chỉnh kích thước icon */
        color: #6c757d; /* Màu mặc định cho icon */
        transition: color 0.2s ease-in-out; /* Hiệu ứng chuyển màu mượt mà */
    }

    .review-actions .btn-edit-review:hover {
        color: #007bff; /* Màu xanh khi di chuột vào icon sửa */
    }

    .review-actions .btn-delete-review:hover {
        color: #dc3545; /* Màu đỏ khi di chuột vào icon xóa */
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<style>
.vouchers-list { display: flex; gap: 15px; flex-wrap: wrap; }
.voucher-item { display: flex; align-items: center; background: #e9f5ff; border: 1px dashed #007bff; border-radius: 8px; overflow: hidden; }
.voucher-info { padding: 8px 12px; font-weight: 500; color: #0056b3; }
.voucher-code { background: #007bff; color: white; border: none; padding: 8px 15px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
.voucher-code:hover { background: #0056b3; }
.voucher-code:disabled { background: #28a745; cursor: default; }

.input-with-button { display: flex; margin-top: 5px; }
.input-with-button input { flex-grow: 1; border-radius: 0; border-right: none; }
.input-with-button button { border-radius: 0; white-space: nowrap; }

.price-container .price { font-size: 1.8em; }
.price-container .price-original { text-decoration: line-through; color: #999; font-size: 1.1em; margin-top: -10px; }

.coupon-result { margin-top: 8px; font-weight: 500; font-size: 0.9em; }
.coupon-result.success { color: #28a745; }
.coupon-result.error { color: #dc3545; }

.price-container .price, .price-container .price-original { transition: all 0.3s ease; }
</style>