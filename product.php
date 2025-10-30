<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

// Handle review edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!is_logged_in()) {
        flash_set('error', 'Please login to manage your reviews.');
        header('Location: login.php'); exit;
    }

    $review_id = (int)($_POST['review_id'] ?? 0);
    $user_id = current_user_id();

    if ($_POST['action'] === 'delete_review' || $_POST['action'] === 'edit_review') {
        // Verify ownership only for edit/delete actions
        $stmt = $db->prepare("SELECT user_id FROM product_reviews WHERE id = ?");
        $stmt->execute([$review_id]);
        $owner_id = $stmt->fetchColumn();

        if ($owner_id != $user_id) {
            flash_set('error', 'You can only manage your own reviews.');
            header('Location: product.php?id=' . $id); exit;
        }
    }

    if ($_POST['action'] === 'delete_review' && $review_id > 0) {
        $del = $db->prepare("DELETE FROM product_reviews WHERE id = ?");
        $del->execute([$review_id]);
        flash_set('success', 'Your review has been deleted.');
        header('Location: product.php?id=' . $id); exit;
    } elseif ($_POST['action'] === 'edit_review') {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $upd = $db->prepare("UPDATE product_reviews SET rating = ?, comment = ? WHERE id = ?");
        $upd->execute([$rating, $comment, $review_id]);
        flash_set('success', 'Your review has been updated.');
        header('Location: product.php?id=' . $id); exit;
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    if (!is_logged_in()) {
        flash_set('error','Please login to submit a review');
        header('Location: login.php'); exit;
    }
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        flash_set('error','Rating must be between 1 and 5');
        header('Location: product.php?id=' . $id); exit;
    } elseif ($comment === '') {
        flash_set('error','Please enter a comment');
        header('Location: product.php?id=' . $id); exit;
    } else {
        try {
            $ins = $db->prepare('INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?,?,?,?)');
            $ins->execute([$id, current_user_id(), $rating, $comment]);
            flash_set('success','Thank you for your review');
            header('Location: product.php?id=' . $id); exit;
        } catch (PDOException $e) {
            flash_set('error','Could not save review');
            header('Location: product.php?id=' . $id); exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';

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
        $w = $db->prepare('SELECT    1 FROM wishlists WHERE user_id = ? AND product_id = ?');
        $w->execute([current_user_id(), $id]);
        $inWishlist = (bool)$w->fetch();
    } catch (Exception $e) { }
}

// Vouchers
$vouchers = [];
try {
    $vouchers = $db->query("SELECT code, discount_percent FROM coupons WHERE (valid_to IS NULL OR valid_to >= NOW()) AND (valid_from IS NULL OR valid_from <= NOW()) ORDER BY discount_percent DESC")->fetchAll();
} catch (Exception $e) {
    // ignore if table doesn't exist
}

// Suggested products (same category)
$suggested = [];
try {
    $st2 = $db->prepare('SELECT p.* FROM products p WHERE p.category_id = ? AND p.id != ? LIMIT 7');
    $st2->execute([$prod['category_id'], $id]);
    $suggested = $st2->fetchAll();
    // load images for suggested
    $sids = array_map(function($p){return $p['id'];}, $suggested);
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
document.addEventListener('DOMContentLoaded', function(){

    // Size selection
    const sizeBoxes = document.querySelectorAll('.size-box');
    const sizeRadios = document.querySelectorAll('input[name="size"]');

    // Thêm một div để hiển thị thông báo về size
    const sizeSelectorDiv = document.querySelector('.size-selector');
    let sizeMessageDiv = sizeSelectorDiv ? sizeSelectorDiv.querySelector('.size-message') : null;
    if (sizeSelectorDiv && !sizeMessageDiv) {
        sizeMessageDiv = document.createElement('div');
        sizeMessageDiv.className = 'size-message';
        sizeSelectorDiv.appendChild(sizeMessageDiv);
    }

    const stockDisplay = document.getElementById('stock-display');

    function updateStockDisplay() {
        if (!stockDisplay) return;
        const selectedRadio = document.querySelector('input[name="size"]:checked');
        if (selectedRadio) {
            const stock = selectedRadio.dataset.stock;
            stockDisplay.textContent = `Tồn kho: ${stock}`;
        } else {
            stockDisplay.textContent = 'Chọn size để xem tồn kho';
        }
    }

    updateStockDisplay(); // Cập nhật lần đầu khi tải trang

    sizeBoxes.forEach((box, idx) => {
        box.addEventListener('click', function(){
            // Kiểm tra xem radio button tương ứng có bị vô hiệu hóa không
            if (sizeRadios[idx].disabled) {
                if (sizeMessageDiv) {
                    sizeMessageDiv.textContent = `Size ${sizeRadios[idx].value} đã hết hàng.`;
                    setTimeout(() => { sizeMessageDiv.textContent = ''; }, 3000); // Xóa thông báo sau 3 giây
                }
                return;
            }
            sizeBoxes.forEach(b => b.classList.remove('selected'));
            box.classList.add('selected');
            sizeRadios[idx].checked = true;
            updateStockDisplay(); // Cập nhật tồn kho khi chọn size mới
        });
    });

    // Review edit logic
    document.querySelectorAll('.btn-edit-review').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const reviewCard = this.closest('.review');
            const editForm = reviewCard.querySelector('.edit-review-form');
            const reviewContent = reviewCard.querySelector('.review-content');

            if (editForm.style.display === 'none' || !editForm.style.display) {
                reviewContent.style.display = 'none';
                editForm.style.display = 'block';
            } else { // This part is now handled by the cancel button
                reviewContent.style.display = 'block';
                editForm.style.display = 'none';
            }
        });
    });

    // Star rating for edit forms
    // This logic is similar to the add review form, but needs to be applied to potentially multiple forms

    // Quantity + / -
    const qtyInput = document.querySelector('input[name="quantity"]');
    const btnPlus = document.querySelector('.qty-btn.plus');
    const btnMinus = document.querySelector('.qty-btn.minus');

    btnPlus.addEventListener('click', function(){
        let current = parseInt(qtyInput.value);
        const selectedSizeRadio = document.querySelector('input[name="size"]:checked');
        if (selectedSizeRadio) {
            const maxStock = parseInt(selectedSizeRadio.dataset.stock, 10);
            if (current < maxStock) {
                qtyInput.value = current + 1;
            }
        }
    });

    btnMinus.addEventListener('click', function(){
        let current = parseInt(qtyInput.value);
        if(current > 1) qtyInput.value = current - 1;
    });

    // Thumbnails click
    const thumbs = document.querySelectorAll('.thumb');
    const mainImage = document.querySelector('.main-image img');
    thumbs.forEach(thumb => {
        thumb.addEventListener('click', function(){
            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
            mainImage.src = thumb.dataset.src;
        });
    });

    // Star rating JS
    const stars = document.querySelectorAll('.star-rating-js .star');
    const ratingInput = document.getElementById('rating-value');
    stars.forEach(star => {
        star.addEventListener('click', function(){
            let val = parseInt(star.dataset.value);
            ratingInput.value = val;
            stars.forEach(s => s.textContent = s.dataset.value <= val ? '★' : '☆');
        });
    });

    // Voucher copy logic
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
            }).catch(err => {
                alert('Không thể sao chép mã. Vui lòng thử lại.');
                console.error('Copy failed', err);
            });
        });
    });

    // Hủy sửa đánh giá
    document.querySelectorAll('.btn-cancel-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('.edit-review-form');
            const reviewDiv = form.closest('.review');
            const reviewContent = reviewDiv.querySelector('.review-content');
            form.style.display = 'none';
            if (reviewContent) reviewContent.style.display = 'block';
        });
    });

    // Star rating for EACH edit form
    document.querySelectorAll('.edit-review-form').forEach(formContainer => {
        const starsInForm = formContainer.querySelectorAll('.star-rating-js .star');
        const ratingInputInForm = formContainer.querySelector('input[name="rating"]');

        if (starsInForm.length > 0 && ratingInputInForm) {
            starsInForm.forEach(star => {
                star.addEventListener('click', function() {
                    const val = parseInt(this.dataset.value, 10);
                    ratingInputInForm.value = val;
                    // Update stars within the same form
                    starsInForm.forEach(s => {
                        s.textContent = parseInt(s.dataset.value, 10) <= val ? '★' : '☆';
                    });
                });
            });
        }
    });

    // Paste and Validate button logic
    const pasteBtn = document.getElementById('paste-and-validate-btn');
    if (pasteBtn) {
        pasteBtn.addEventListener('click', handlePasteAndValidate);
    }

    async function handlePasteAndValidate() {
        const couponInput = document.getElementById('coupon-code-product');
        const resultDiv = document.querySelector('.product-actions-form .coupon-result');
        const priceEl = document.querySelector('.price-container .price');
        const originalPriceEl = document.querySelector('.price-container .price-original');
        const originalPrice = parseFloat(priceEl.dataset.originalPrice);

        // Reset previous state
        resultDiv.textContent = '';
        resultDiv.className = 'coupon-result';

        try {
            // 1. Read from clipboard and paste
            const code = await navigator.clipboard.readText();
            couponInput.value = code;

            if (!code) {
                resultDiv.textContent = 'Clipboard rỗng.';
                resultDiv.className = 'coupon-result error';
                return;
            }

            // 2. Validate the code via fetch
            const formData = new FormData();
            formData.append('code', code);

            const response = await fetch('validate_coupon.php', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
            const data = await response.json();

            if (data.success) {
                const discountPercent = data.discount_percent;
                const newPrice = originalPrice * (1 - discountPercent / 100);

                resultDiv.textContent = `✓ Áp dụng thành công! Giảm ${discountPercent}%.`;
                resultDiv.className = 'coupon-result success';
                originalPriceEl.textContent = new Intl.NumberFormat('vi-VN').format(originalPrice) + '₫';
                originalPriceEl.style.display = 'block';
                priceEl.textContent = new Intl.NumberFormat('vi-VN').format(newPrice) + '₫';
            } else {
                throw new Error(data.message || 'Mã không hợp lệ.');
            }
        } catch (err) {
            resultDiv.textContent = `✗ ${err.message}`;
            resultDiv.className = 'coupon-result error';
            originalPriceEl.style.display = 'none';
            priceEl.textContent = new Intl.NumberFormat('vi-VN').format(originalPrice) + '₫';
        }
    }
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
                        <?php if ($isVideo): ?><span class="play">▶</span><?php endif; ?>
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
                <span id="stock-display" style="font-weight: 500;"></span></p>
            <div class="price-container">
                <p class="price" data-original-price="<?php echo $prod['price']; ?>"><?php echo number_format($prod['price'], 0); ?>₫</p>
                <p class="price-original" style="display: none;"></p> <!-- Giữ lại để JS hoạt động -->
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
                                $is_checked = false;
                                if (!$is_disabled && !$first_available_checked) {
                                    $is_checked = true;
                                    $first_available_checked = true;
                                }
                            ?>
                                <input type="radio" name="size" value="<?php echo htmlspecialchars($sz['size']); ?>" id="size-<?php echo htmlspecialchars($sz['size']); ?>" <?php if ($is_checked) echo 'checked'; ?> <?php if ($is_disabled) echo 'disabled'; ?> data-stock="<?php echo (int)$sz['stock']; ?>">
                                <label for="size-<?php echo htmlspecialchars($sz['size']); ?>" class="size-box" title="<?php if ($is_disabled) echo 'Hết hàng'; ?>"><?php echo htmlspecialchars($sz['size']); ?></label>
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

    <div class="product-section description-section">
        <div class="section-header-styled">
            <h3>Mô tả sản phẩm</h3>
        </div>
        <div class="desc-content"><?php echo nl2br(htmlspecialchars($prod['description'])); ?></div>
    </div>

    <?php if (!empty($vouchers)): ?>
    <div class="product-section vouchers-section">
        <h3>✨ Voucher của Shop</h3>
        <div class="vouchers-list">
            <?php foreach ($vouchers as $v): ?>
            <div class="voucher-item">
                <div class="voucher-info">Giảm <?php echo (int)$v['discount_percent']; ?>%</div>
                <button class="voucher-code" data-code="<?php echo htmlspecialchars($v['code']); ?>">Copy</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="product-section reviews-section">
        <h3>Reviews</h3>
        <?php if (empty($reviews)): ?>
            <p>No reviews yet.</p>
        <?php else: foreach ($reviews as $rev): ?>
            <div class="review">
                <div class="review-content">
                    <div class="review-header">
                        <strong><?php echo htmlspecialchars($rev['user_name'] ?? 'Guest'); ?></strong>
                        <span class="review-rating">Rating: <?php echo str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']); ?></span>
                    </div>
                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                    <small class="review-date"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></small>
                </div>

                <?php if (is_logged_in() && current_user_id() == $rev['user_id']): ?>
                    <div class="review-actions">
                        <button class="btn-edit-review" title="Sửa đánh giá"><i class="fi fi-rr-pencil"></i></button>
                        <form method="post" action="product.php?id=<?php echo $id; ?>" onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này?');" style="display:inline;">
                            <input type="hidden" name="action" value="delete_review">
                            <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                            <button type="submit" class="btn-delete-review" title="Xóa đánh giá"><i class="fi fi-rr-trash"></i></button>
                        </form>
                    </div>
                    <div class="edit-review-form" style="display:none; margin-top: 15px;">
                        <form method="post" action="product.php?id=<?php echo $id; ?>">
                            <input type="hidden" name="action" value="edit_review">
                            <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                            <div class="form-group">
                                <label>Rating</label>
                                <div class="star-rating-js">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star" data-value="<?php echo $i; ?>"><?php echo $i <= $rev['rating'] ? '★' : '☆'; ?></span>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" value="<?php echo $rev['rating']; ?>" required>
                            </div>
                            <div class="form-group">
                                <textarea name="comment" rows="3" required><?php echo htmlspecialchars($rev['comment']); ?></textarea>
                            </div>
                            <button class="btn" type="submit">Lưu thay đổi</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

        <?php if (is_logged_in()): ?>
            <div class="add-review-form">
                <h4>Write a review</h4>
                <?php if ($m = flash_get('error')): ?><p style="color:red"><?php echo $m; ?></p><?php endif; ?>
                <?php if ($m = flash_get('success')): ?><p style="color:green"><?php echo $m; ?></p><?php endif; ?>
                <form method="post" action="product.php?id=<?php echo $id; ?>">
                    <input type="hidden" name="action" value="add_review">
                    <div class="form-group">
                        <label>Rating</label>
                        <!-- JS-powered star rating -->
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
                            <h4><a href="product.php?id=<?php echo $s['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($s['name']); ?></a></h4>
                            <p class="price"><?php echo number_format($s['price'], 0); ?>₫</p>
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

<?php require_once __DIR__ . '/includes/footer.php';
?>
<style>
/* --- Voucher & Coupon Styles --- */
.vouchers-list {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}
.voucher-item {
    display: flex;
    align-items: center;
    background: #e9f5ff;
    border: 1px dashed #007bff;
    border-radius: 8px;
    overflow: hidden;
}
.voucher-info {
    padding: 8px 12px;
    font-weight: 500;
    color: #0056b3;
}
.voucher-code {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 15px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}
.voucher-code:hover {
    background: #0056b3;
}
.voucher-code:disabled {
    background: #28a745; /* Green for 'Copied' */
    cursor: default;
}

/* Coupon Input Group */
.input-with-button {
    display: flex;
    margin-top: 5px;
}
.input-with-button input {
    flex-grow: 1;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
}
.input-with-button button {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    white-space: nowrap; /* Prevent text wrapping */
}

/* Price Display */
.price-container .price {
    font-size: 1.8em; /* Make current price bigger */
}
.price-container .price-original {
    text-decoration: line-through;
    color: #999;
    font-size: 1.1em;
    margin-top: -10px;
}

/* Coupon result message */
.coupon-result {
    margin-top: 8px;
    font-weight: 500;
    font-size: 0.9em;
}
.coupon-result.success { color: #28a745; }
.coupon-result.error { color: #dc3545; }

/* Size message style */
.size-message {
    margin-top: 8px;
    font-size: 0.9em;
    color: #dc3545; /* Red color for error/warning */
    font-weight: 500;
    height: 1em; /* Reserve space to prevent layout shift */
}

/* Review Actions */
.review {
    position: relative;
}
.review-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: inline-flex; /* Changed to inline-flex */
    align-items: center; /* Vertically align items */
    gap: 10px;
}
.btn-edit-review, .btn-delete-review {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.1em; /* Tăng kích thước icon */
    text-decoration: none; /* Bỏ gạch chân */
}
.btn-delete-review { color: #dc3545; }

/* Review Actions */
.review {
    position: relative;
}
.review-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: inline-flex; /* Changed to inline-flex */
    align-items: center; /* Vertically align items */
    gap: 10px;
}
</style>
 
