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
        // flash_set('success', 'Đã cập nhật đánh giá của bạn.');
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
    $st2 = $db->prepare('
        SELECT p.*, 
               (SELECT SUM(stock) FROM product_sizes ps WHERE ps.product_id = p.id) as total_stock
        FROM products p 
        WHERE p.category_id = ? AND p.id != ? LIMIT 7');
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

// Load sizes for suggested products
$suggested_sizes = [];
if (!empty($sids)) {
    try {
        $st_sizes = $db->prepare("SELECT product_id, size, stock FROM product_sizes WHERE product_id IN ($place) AND stock > 0 ORDER BY size ASC");
        $st_sizes->execute($sids);
        foreach ($st_sizes->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $suggested_sizes[$s['product_id']][] = $s['size'];
        }
    } catch (Exception $e) { /* ignore */ }
}

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
                    // === FIX: Lấy discount_percent từ bên trong đối tượng data.coupon ===
                    const couponData = data.coupon || {};
                    const discountPercent = parseFloat(couponData.discount_percent) || 0;
                    const newPrice = originalPrice * (1 - discountPercent / 100);
                    resultDiv.innerHTML = `Áp dụng thành công! Giảm <strong>${discountPercent}%</strong>`;
                    resultDiv.classList.add('success');
                    originalPriceEl.textContent = formatVND(originalPrice);
                    originalPriceEl.style.display = 'block';
                    priceEl.textContent = formatVND(newPrice);

                    // === FIX: LƯU COUPON VÀO LOCALSTORAGE ===
                    localStorage.setItem('product_coupon_code', couponData.code);
                    localStorage.setItem('product_coupon_data', JSON.stringify(couponData));
                }
            } else {
                resultDiv.textContent = data.message || 'Mã không hợp lệ.';
                resultDiv.classList.add('error');
                if (!isShipping) {
                    originalPriceEl.style.display = 'none';
                    priceEl.textContent = formatVND(originalPrice);
                    // === FIX: XÓA COUPON KHỎI LOCALSTORAGE KHI MÃ KHÔNG HỢP LỆ ===
                    localStorage.removeItem('product_coupon_code');
                    localStorage.removeItem('product_coupon_data');
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
                'Accept': 'application/json'
            }
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

    // Gắn sự kiện cho các nút "Thêm vào giỏ hàng" trong phần sản phẩm gợi ý
    document.querySelectorAll('.suggested-products .btn-choose-size').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            const productCard = this.closest('.product');
            if (!productCard) return;

            const sizesContainer = productCard.querySelector('.product-sizes');
            if (!sizesContainer) return;

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

    // Gắn sự kiện cho các nút size trong phần sản phẩm gợi ý
    document.querySelectorAll('.suggested-products .btn-size').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation(); // Ngăn sự kiện click lan ra ngoài
            addToCart(this.dataset.productId, this.dataset.size);
        });
    });

    // Đóng size container khi click ra ngoài
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.suggested-products .product')) {
            document.querySelectorAll('.suggested-products .product-sizes.active').forEach(container => {
                container.classList.remove('active');
            });
        }
    });
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
            <p id="stock-message" class="stock-message" style="display: none; color: red; font-weight: 500; margin-bottom: 1rem;"></p>
            
            <div class="price-container">
                <span class="price" data-original-price="<?php echo $prod['price']; ?>">
                    <?php echo number_format($prod['price'], 0); ?> đ
                </span>
                <span class="price-original" style="display: none;"></span>
            </div>

            <?php if ($is_out_of_stock): ?>
                <div class="alert-error" style="text-align: center; padding: 15px; margin-top: 20px; background-color: var(--danger-light); color: var(--danger); border: 1px solid var(--danger-light);">
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
                            <input type="number" name="quantity" value="1" min="1">
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

                    <!-- THÊM 2 HIDDEN CHO PRODUCT COUPON -->
                    <input type="hidden" id="validated_product_coupon_code" name="coupon_code_hidden" value="">
                    <input type="hidden" id="product_discount_percent" name="product_discount_percent" value="0">
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
            <p>Chưa có đánh giá nào.</p>
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
                <?php if ($m = flash_get('error')): ?><p style="color:var(--danger);"><?php echo $m; ?></p><?php endif; ?>
                <?php 
                // Lấy thông báo thành công
                $success_message = flash_get('success');
                // Chỉ hiển thị nếu đó không phải là thông báo đăng nhập mặc định
                if ($success_message && $success_message !== 'Đăng nhập thành công!'): 
                ?>
                    <p style="color:var(--success);"><?php echo htmlspecialchars($success_message); ?></p>
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
            <p><a href="login.php" style="color: var(--primary);">Đăng nhập</a> để viết đánh giá.</p>
        <?php endif; ?>
    </div>

    <!-- Suggested Products -->
    <style>
    /* CSS được sao chép từ category.php để đồng bộ giao diện */
    .suggested-products .product {
        background: var(--bg-white);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        text-align: center;
        padding: 18px 12px 16px 12px;
        box-shadow: 0 4px 18px rgba(203, 213, 225, 0.13);
        transition: box-shadow 0.2s, transform 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .suggested-products .product:hover { 
        box-shadow: 0 8px 28px rgba(14, 165, 255, 0.13); 
        transform: translateY(-6px) scale(1.03); 
    }
    .suggested-products .product .thumb { 
        margin-bottom: 14px; 
        position: relative;
    }
    .suggested-products .product .thumb img { 
        max-width: 100%; 
        height: 180px; 
        object-fit: cover; 
        border-radius: 8px; 
        box-shadow: 0 2px 8px rgba(186, 230, 253, 0.2); 
    }
    .suggested-products .product .product-main {
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }
    .suggested-products .product h3 { 
        font-size: 1.13em; 
        margin: 8px 0 6px 0; 
        font-weight: 700; 
        color: var(--text-dark); 
        flex-grow: 1;
    }
    .suggested-products .product p.price { 
        font-size: 1.08em; 
        color: var(--primary-dark); 
        margin: 0 0 8px 0; 
        font-weight: 700; 
    }
    .suggested-products .product-actions { 
        display: flex; 
        justify-content: center; 
        gap: 8px; 
        margin-top: auto; 
        padding-top: 10px; 
    }
    .suggested-products .product-actions .btn { 
        font-size: 0.9em; 
        padding: 8px 12px; 
        border-radius: 7px; 
    }
    .suggested-products .product-actions .btn-choose-size { 
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%); 
        color: #fff; 
    }
    .suggested-products .product-actions .btn-wishlist { 
        background: var(--bg-light); 
        color: var(--primary); 
        border: 1px solid var(--primary-light); 
    }
    .suggested-products .product-actions .btn-wishlist:hover { 
        background: var(--primary); 
        color: #fff; 
    }
    
    /* CSS cho Swiper container */
    .suggested-products .product-carousel-wrapper .swiper {
        overflow: hidden;
    }

    .suggested-products .product-carousel-wrapper .swiper-slide {
        width: auto !important;
        flex-shrink: 0 !important;
    }

    /* CSS cho nhãn hết hàng */
    .suggested-products .out-of-stock-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(220, 53, 69, 0.95); /* var(--danger) với độ mờ */
        color: white;
        padding: 4px 8px;
        font-size: 0.8em;
        font-weight: bold;
        border-radius: 4px;
        z-index: 1;
    }

    /* CSS cho product-sizes - THÊM PHẦN NÀY */
    .suggested-products .product-sizes {
        display: none;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--bg-gray);
    }
    .suggested-products .product-sizes.active { 
        display: flex; 
    }
    .suggested-products .product-sizes .btn-size {
        font-size: 0.85em; 
        padding: 6px 10px; 
        background: var(--primary-light);
        border: none;
        border-radius: 4px;
        cursor: pointer;
        color: var(--primary-dark);
    }
    .suggested-products .product-sizes .btn-size:hover {
        background: var(--primary); 
        color: #fff;
    }
</style>
    <?php if (!empty($suggested)): ?>
    <div class="product-section suggested-products">
        <div class="section-header">
            <h2>Sản phẩm gợi ý</h2>
            <p>Có thể bạn cũng sẽ thích những sản phẩm này</p>
        </div>
        <div class="product-carousel-wrapper">
            <div class="swiper product-carousel">
                <div class="swiper-wrapper">
                    <?php foreach ($suggested as $s): ?>
                        <div class="swiper-slide product">
                            <div class="product-main">
                                <div class="thumb">
                                    <?php if (isset($s['total_stock']) && $s['total_stock'] <= 0): ?>
                                        <div class="out-of-stock-badge">Hết hàng</div>
                                    <?php endif; ?>
                                    <?php $simg = $imagesBy[$s['id']] ?? 'assets/images/product-placeholder.png'; ?>
                                    <a href="product.php?id=<?php echo $s['id']; ?>"><img src="<?php echo htmlspecialchars($simg); ?>" alt="<?php echo htmlspecialchars($s['name']); ?>"></a>
                                </div>
                                <h3><a href="product.php?id=<?php echo $s['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($s['name']); ?></a></h3>
                                <p class="price"><?php echo number_format($s['price'], 0); ?>₫</p>
                                <div class="product-actions">
                                    <?php if (isset($s['total_stock']) && $s['total_stock'] > 0 && !empty($suggested_sizes[$s['id']])): ?>
                                        <button type="button" class="btn btn-choose-size" data-product-id="<?php echo $s['id']; ?>">Thêm vào giỏ hàng</button>
                                    <?php else: ?>
                                        <button class="btn" disabled>Thêm vào giỏ hàng</button>
                                    <?php endif; ?>
                                    <form class="ajax-wishlist" method="post" action="wishlist.php">
                                        <input type="hidden" name="product_id" value="<?php echo $s['id']; ?>">
                                        <button class="btn btn-wishlist" type="submit">❤</button>
                                    </form>
                                </div>
                            </div>
                            <div class="product-sizes" id="sizes-for-<?php echo $s['id']; ?>">
                                <?php if (!empty($suggested_sizes[$s['id']])): foreach ($suggested_sizes[$s['id']] as $size): ?>
                                    <button class="btn btn-size" data-product-id="<?php echo $s['id']; ?>" data-size="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></button>
                                <?php endforeach; endif; ?>
                            </div>
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
        const swiperEl = wrapper.querySelector('.swiper.product-carousel');
        if (!swiperEl) return;

        new Swiper(swiperEl, {
            slidesPerView: 5,
            slidesPerGroup: 1,
            spaceBetween: 20,
            loop: false,
            watchOverflow: true,
            navigation: {
                nextEl: wrapper.querySelector('.swiper-button-next'),
                prevEl: wrapper.querySelector('.swiper-button-prev'),
            },
            breakpoints: {
                0: { slidesPerView: 2 },
                576: { slidesPerView: 3 },
                768: { slidesPerView: 3 },
                992: { slidesPerView: 4 },
                1200: { slidesPerView: 5 }
            }
        });
    });
});
<script>
document.addEventListener("DOMContentLoaded", () => {
    console.log("🧾 [DEBUG] Kiểm tra mã giảm phí vận chuyển khi load product.php...");

    try {
        const stored = localStorage.getItem("shipping_coupon");
        if (stored) {
            const coupon = JSON.parse(stored);
            console.log("✅ Đã tìm thấy mã giảm phí vận chuyển trong localStorage:", coupon);

            // Hiển thị tạm thông tin mã ngay trên trang
            const infoBox = document.createElement("div");
            infoBox.style.cssText = `
                position: fixed; bottom: 10px; right: 10px;
                background: #f0f9ff; border: 1px solid #0ea5e9;
                color: #0369a1; padding: 8px 12px;
                border-radius: 6px; font-size: 14px;
                z-index: 9999;
            `;
            infoBox.innerHTML = `🚚 Mã vận chuyển: <b>${coupon.code}</b><br>Giảm: ${coupon.discount_type} (${coupon.discount_value})`;
            document.body.appendChild(infoBox);

            setTimeout(() => infoBox.remove(), 6000);
        } else {
            console.warn("⚠️ Không tìm thấy mã giảm phí vận chuyển trong localStorage.");
        }
    } catch (err) {
        console.error("❌ Lỗi khi đọc shipping_coupon từ localStorage:", err);
    }
});
</script>

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
        font-size: 16px;
        color: var(--text-muted);
        transition: color 0.2s ease-in-out; /* Hiệu ứng chuyển màu mượt mà */
    }

    .review-actions .btn-edit-review:hover {
        color: var(--primary);
    }

    .review-actions .btn-delete-review:hover {
        color: var(--danger);
    }
</style>

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
        font-size: 16px;
        color: var(--text-muted);
        transition: color 0.2s ease-in-out; /* Hiệu ứng chuyển màu mượt mà */
    }

    .review-actions .btn-edit-review:hover {
        color: var(--primary);
    }

    .review-actions .btn-delete-review:hover {
        color: var(--danger);
    }
</style>

<style>
    /* CSS cho giá gốc và giá mới */
    .price-container {
        display: flex; /* Sử dụng flexbox */
        flex-direction: column; /* Xếp các phần tử theo chiều dọc */
        align-items: flex-start; /* Căn các phần tử về bên trái */
        gap: 4px; /* Khoảng cách nhỏ giữa giá mới và giá gốc */
    }
    .price-original {
        font-size: 1.1rem; /* Cỡ chữ nhỏ hơn giá mới */
        color: var(--text-muted);
        text-decoration: line-through;
        font-weight: 500;
    }
    .price {
        font-size: 1.5rem; /* Cỡ chữ lớn hơn cho giá mới */
        font-weight: 700;
        color: var(--primary);
    }
</style>
<style>
    /* CSS để input và button nằm chung 1 hàng */
    .input-with-button {
        display: flex;
        gap: 8px;
        align-items: stretch; /* Đảm bảo cả input và button có cùng chiều cao */
    }

    .input-with-button input[type="text"] {
        flex: 1; /* Input chiếm phần còn lại của container */
        min-width: 0; /* Cho phép input co lại khi cần */
    }

    .input-with-button .btn {
        white-space: nowrap; /* Không cho text trong button xuống dòng */
        flex-shrink: 0; /* Không cho button bị co lại */
    }

    /* Responsive cho màn hình nhỏ (nếu cần) */
    @media (max-width: 576px) {
        .input-with-button { flex-direction: column; }
        .input-with-button .btn { width: 100%; }
    }
</style>

<style>
    /* CSS cập nhật màu cho các nút và liên kết trong trang product */

    /* Nút Add to cart chính */
    .product-actions-form .btn,
    .product-actions-form button.btn[type="submit"] {
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%) !important;
        color: #fff !important;
        border: none !important;
    }

    .product-actions-form .btn:hover {
        background: linear-gradient(90deg, var(--primary-dark) 60%, var(--accent-hover) 100%) !important;
    }

    /* Nút Dán & Kiểm tra */
    #paste-and-validate-btn,
    #paste-and-validate-shipping-btn {
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%) !important;
        color: #fff !important;
        border: none;
    }

    #paste-and-validate-btn:hover,
    #paste-and-validate-shipping-btn:hover {
        background: linear-gradient(90deg, var(--primary-dark) 60%, var(--accent-hover) 100%) !important;
    }

    /* Nút Copy voucher */
    .voucher-code {
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%) !important;
        color: #fff !important;
        border: none;
    }

    .voucher-code:hover {
        background: linear-gradient(90deg, var(--primary-dark) 60%, var(--accent-hover) 100%) !important;
    }

    /* Nút Thêm vào giỏ hàng trong phần sản phẩm gợi ý */
    .suggested-products .btn-choose-size {
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%) !important;
        color: #fff !important;
    }

    .suggested-products .btn-choose-size:hover {
        background: linear-gradient(90deg, var(--primary-dark) 60%, var(--accent-hover) 100%) !important;
    }

    /* Nút size trong sản phẩm gợi ý */
    .suggested-products .btn-size:hover {
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%) !important;
        color: #fff !important;
    }

    /* Các liên kết màu xanh (Brand, Category) */
    .meta a {
        color: var(--primary) !important;
    }

    .meta a:hover {
        color: var(--primary-dark) !important;
        text-decoration: underline;
    }

    /* Nút Submit review, Lưu, Sửa */
    .add-review-form button.btn[type="submit"],
    .review-edit-form button.btn[type="submit"] {
        background: linear-gradient(90deg, var(--primary) 60%, var(--accent) 100%) !important;
        color: #fff !important;
    }

    .add-review-form button.btn[type="submit"]:hover,
    .review-edit-form button.btn[type="submit"]:hover {
        background: linear-gradient(90deg, var(--primary-dark) 60%, var(--accent-hover) 100%) !important;
    }

    .review-actions .btn-edit-review:hover {
        color: var(--primary) !important;
    }
</style>

<style>
    /* Ẩn các nút mũi tên (spinners) khỏi ô nhập số lượng */
    .quantity-input input[type=number]::-webkit-inner-spin-button,
    .quantity-input input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .quantity-input input[type=number] {
        -moz-appearance: textfield; /* Dành cho Firefox */
    }


</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>