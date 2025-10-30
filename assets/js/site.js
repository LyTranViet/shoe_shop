// Minimal JS for demo
console.log('ShoeStoreDemo loaded');

// User menu dropdown toggle for both admin and site headers
document.addEventListener('click', function(e){
    var btn = e.target.closest('.user-name-btn');
    if (btn) {
        var wrap = btn.closest('.user-menu');
        wrap.classList.toggle('open');
        return;
    }
    // click outside closes
    document.querySelectorAll('.user-menu.open').forEach(function(el){ el.classList.remove('open'); });
});

// AJAX Add-to-cart handling
document.addEventListener('DOMContentLoaded', function() {

    // --- Initialize Product Carousels with Swiper.js ---
    const productCarousels = document.querySelectorAll('.product-carousel');
    productCarousels.forEach(carousel => {
        const swiper = new Swiper(carousel, {
            // Optional parameters
            loop: false,
            slidesPerView: 2,
            spaceBetween: 15,
            // Navigation arrows
            navigation: {
                nextEl: carousel.nextElementSibling.nextElementSibling, // .swiper-button-next
                prevEl: carousel.nextElementSibling, // .swiper-button-prev
            },
            // Responsive breakpoints
            breakpoints: {
                // when window width is >= 640px
                640: { slidesPerView: 3, spaceBetween: 20 },
                // when window width is >= 768px
                768: { slidesPerView: 4, spaceBetween: 20 },
                // when window width is >= 1024px
                1024: { slidesPerView: 5, spaceBetween: 24 },
            }
        });
    });

    // --- Banner Slider ---
    const slider = document.querySelector('.banner-slider');
    if (slider) {
        const slidesContainer = slider.querySelector('.slides');
        const slides = slider.querySelectorAll('.slide');
        const prevBtn = slider.querySelector('.slider-nav.prev');
        const nextBtn = slider.querySelector('.slider-nav.next');
        let currentIndex = 0;

        function goToSlide(index) {
            slidesContainer.style.transform = `translateX(-${index * 100}%)`;
            currentIndex = index;
        }

        function nextSlide() {
            const nextIndex = (currentIndex + 1) % slides.length;
            goToSlide(nextIndex);
        }

        if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', () => {
                const prevIndex = (currentIndex - 1 + slides.length) % slides.length;
                goToSlide(prevIndex);
            });
            nextBtn.addEventListener('click', nextSlide);
        }

        if (slides.length > 1) setInterval(nextSlide, 5000); // Auto-play every 5 seconds
    }

    // Display file names for product image upload
    const imageInput = document.getElementById('images');
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            const display = document.getElementById('file-name-display');
            if (this.files.length > 0) {
                display.textContent = Array.from(this.files).map(f => f.name).join(', ');
            } else {
                display.textContent = 'No file chosen';
            }
        });
    }

    document.querySelectorAll('form.ajax-add-cart').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var data = new FormData(form);
            var url = form.getAttribute('action') || location.href;
            fetch(url, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var j = null;
                try {
                    j = JSON.parse(text);
                } catch (e) {
                    // not JSON (maybe an HTML login page or server error)
                    console.warn('Add-to-cart: non-JSON response', text);
                    var status = form.querySelector('.add-cart-status');
                    if (status) { status.textContent = 'Error'; }
                    return;
                }

                if (!j) return;
                if (j.success) {
                    // update cart count in header
                    var cartLink = document.getElementById('cart-link');
                    if (cartLink) {
                        cartLink.textContent = 'Cart (' + (j.count || 0) + ')';
                    }
                    // show success message
                    var status = form.querySelector('.add-cart-status');
                    if (status) {
                        status.textContent = 'Added!';
                        setTimeout(function () { status.textContent = ''; }, 2000);
                    }
                } else if (j.login_required) {
                    window.location = j.redirect || 'login.php';
                } else if (j.error) {
                    var status = form.querySelector('.add-cart-status');
                    if (status) { status.textContent = j.error; }
                }
            })
            .catch(function (err) {
                var status = form.querySelector('.add-cart-status');
                if (status) { status.textContent = 'Error'; }
                console.error('Add to cart failed', err);
            });
        });
    });

    // AJAX Wishlist handling
    document.querySelectorAll('form.ajax-wishlist').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var data = new FormData(form);
            var url = form.getAttribute('action') || location.href;
            fetch(url, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var j = null;
                try { j = JSON.parse(text); } catch (e) {
                    console.warn('Wishlist: non-JSON response', text);
                    return;
                }
                if (!j) return;
                if (j.login_required) {
                    window.location = j.redirect || 'login.php';
                    return;
                }
                if (j.success) {
                    var btn = form.querySelector('button');
                    if (btn) { btn.textContent = '❤'; }
                    // optional: temporary message
                    var msg = document.createElement('span'); msg.textContent = ' Added to wishlist';
                    form.appendChild(msg);
                    setTimeout(function () { msg.remove(); }, 2000);
                } else if (j.error) {
                    console.warn('Wishlist error', j.error);
                }
            })
            .catch(function (err) {
                console.error('Wishlist failed', err);
            });
        });
    });

    // AJAX Cart update (quantity) and remove handlers
    document.querySelectorAll('form.ajax-cart-update').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var data = new FormData(form);
            var url = form.getAttribute('action') || location.href;
            fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var j = null; try { j = JSON.parse(text); } catch (e) { console.warn('Cart update non-JSON', text); return; }
                if (!j) return;
                if (j.success) {
                    var cartLink = document.getElementById('cart-link'); if (cartLink && typeof j.count !== 'undefined') cartLink.textContent = 'Cart (' + (j.count || 0) + ')';
                    // update item subtotal and total without reload if provided
                    var cartItemInput = form.querySelector('input[name="cart_item_id"], input[name="session_key"]');
                    var id = cartItemInput ? cartItemInput.value : null;
                    if (typeof j.item_subtotal !== 'undefined') {
                        // find item element
                        var itemEl = form.closest('.cart-item');
                        if (itemEl) {
                            var sub = itemEl.querySelector('.cart-item-subtotal span');
                            if (sub) sub.textContent = parseFloat(j.item_subtotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                    if (typeof j.total !== 'undefined') {
                        var totalEl = document.getElementById('cart-total');
                        // The total in cart summary might have a $ sign
                        if (totalEl) totalEl.textContent = '$' + parseFloat(j.total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                    if (j.reload) location.reload();
                }
            })
            .catch(function (err) { console.error('Cart update failed', err); });
        });
    });

    // AJAX Wishlist remove (from wishlist.php grid)
    document.querySelectorAll('form.ajax-wishlist-remove').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var data = new FormData(form);
            var url = form.getAttribute('action') || location.href;
            fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var j = null; try { j = JSON.parse(text); } catch (e) { console.warn('Wishlist remove non-JSON', text); return; }
                if (!j) return;
                if (j.success) {
                    var el = form.closest('.product'); if (el) el.remove();
                }
            })
            .catch(function (err) { console.error('Wishlist remove failed', err); });
        });
    });

    document.querySelectorAll('form.ajax-cart-remove').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (!confirm('Remove this item from cart?')) return;
            var data = new FormData(form);
            var url = form.getAttribute('action') || location.href;
            fetch(url, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var j = null; try { j = JSON.parse(text); } catch (e) { console.warn('Cart remove non-JSON', text); return; }
                if (!j) return;
                if (j.success) {
                    var cartLink = document.getElementById('cart-link'); 
                    if (cartLink && typeof j.count !== 'undefined') cartLink.textContent = 'Cart (' + (j.count || 0) + ')';
                    if (typeof j.total !== 'undefined') { 
                        var totalEl = document.getElementById('cart-total'); if (totalEl) totalEl.textContent = '$' + parseFloat(j.total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); 
                    }
                    // remove the item element from DOM
                    var el = form.closest('.cart-item'); if (el) el.remove();
                    if (j.reload) location.reload();
                }
            })
            .catch(function (err) { console.error('Cart remove failed', err); });
        });
    });

    // Product gallery thumbnail click -> swap main image/video, with nav buttons and lightbox
    document.querySelectorAll('.gallery').forEach(function (gallery) {
        var mainWrap = gallery.querySelector('.main-image');
        var thumbsContainer = gallery.querySelector('.thumbs');
        var thumbs = gallery.querySelectorAll('.thumbs .thumb');
        var prevBtn = gallery.querySelector('.thumb-nav.prev');
        var nextBtn = gallery.querySelector('.thumb-nav.next');
        if (!mainWrap || !thumbsContainer) return;

        function setMain(type, src) {
            // remove existing media
            mainWrap.innerHTML = '';
            if (type === 'video') {
                var v = document.createElement('video'); v.src = src; v.controls = true; v.autoplay = false; v.style.maxWidth='100%'; v.style.maxHeight='100%';
                mainWrap.appendChild(v);
            } else {
                var i = document.createElement('img'); i.src = src; i.alt = '';
                mainWrap.appendChild(i);
            }
        }

        thumbs.forEach(function (t) {
            t.addEventListener('click', function () {
                thumbs.forEach(function(x){ x.classList.remove('selected'); });
                var type = t.getAttribute('data-type');
                var src = t.getAttribute('data-src');
                setMain(type, src);
                t.classList.add('selected');
            });
        });

        // nav buttons scroll the thumbs container
        if (prevBtn) prevBtn.addEventListener('click', function(){ thumbsContainer.scrollBy({left:-200, behavior:'smooth'}); });
        if (nextBtn) nextBtn.addEventListener('click', function(){ thumbsContainer.scrollBy({left:200, behavior:'smooth'}); });

        // draggable thumbs (mouse)
        var isDown = false, startX, scrollLeft;
        let scroller = thumbsContainer;
        scroller.addEventListener('mousedown', function(e){ isDown = true; scroller.classList.add('active'); startX = e.pageX - scroller.offsetLeft; scrollLeft = scroller.scrollLeft; });
        scroller.addEventListener('mouseleave', function(){ isDown = false; scroller.classList.remove('active'); });
        scroller.addEventListener('mouseup', function(){ isDown = false; scroller.classList.remove('active'); });
        scroller.addEventListener('mousemove', function(e){ if(!isDown) return; e.preventDefault(); var x = e.pageX - scroller.offsetLeft; var walk = (x - startX) * 2; scroller.scrollLeft = scrollLeft - walk; });

        // click main to open lightbox (image/video)
        mainWrap.addEventListener('click', function () {
            var media = mainWrap.querySelector('img,video');
            if (!media) return;
            openLightbox(media.tagName.toLowerCase(), media.src);
        });

        // initialize first thumb as selected
        if (thumbs.length > 0) {
            thumbs[0].click();
        }
    });

    // Lightbox implementation
    var lightbox = document.createElement('div'); lightbox.className = 'lightbox';
    var closeBtn = document.createElement('button'); closeBtn.className='close'; closeBtn.innerHTML='×'; lightbox.appendChild(closeBtn);
    var mediaWrap = document.createElement('div'); mediaWrap.className='media'; lightbox.appendChild(mediaWrap);
    document.body.appendChild(lightbox);

    function openLightbox(type, src){
        mediaWrap.innerHTML='';
        if(type==='video'){ var v=document.createElement('video'); v.src=src; v.controls=true; v.autoplay=true; mediaWrap.appendChild(v); }
        else { var i=document.createElement('img'); i.src=src; mediaWrap.appendChild(i); }
        lightbox.classList.add('active');
    }

    closeBtn.addEventListener('click', function(){ lightbox.classList.remove('active'); mediaWrap.innerHTML=''; });

    lightbox.addEventListener('click', function(e){ if(e.target===lightbox) { lightbox.classList.remove('active'); mediaWrap.innerHTML=''; } });

    // --- Quantity Input on Product Page ---
    document.querySelectorAll('.quantity-input').forEach(wrapper => {
        const input = wrapper.querySelector('input[type="number"]');
        const btnPlus = wrapper.querySelector('.qty-btn.plus');
        const btnMinus = wrapper.querySelector('.qty-btn.minus');

        function triggerUpdate(input) {
            // If we are on the cart page, submit the update form
            const form = input.closest('form.ajax-cart-update');
            if (form) {
                // Use a small delay to allow users to click multiple times quickly
                clearTimeout(input.updateTimeout);
                input.updateTimeout = setTimeout(() => {
                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }, 500);
            }
        }

        if (btnPlus) {
            btnPlus.addEventListener('click', () => {
                input.value = parseInt(input.value, 10) + 1;
                triggerUpdate(input);
            });
        }
        if (btnMinus) {
            btnMinus.addEventListener('click', () => {
                input.value = Math.max(1, parseInt(input.value, 10) - 1);
                triggerUpdate(input);
            });
        }
    });

    // --- Coupon Validation on Product Page ---
    const validateBtn = document.getElementById('validate-coupon-btn');
    if (validateBtn) {
        validateBtn.addEventListener('click', function() {
            const couponInput = document.getElementById('coupon-code-product');
            const code = couponInput.value.trim();
            const priceText = document.querySelector('.details .price');
            const resultDiv = document.querySelector('.product-actions-form .coupon-result');
            
            if (!code || !priceText || !resultDiv) {
                if(resultDiv) {
                    resultDiv.textContent = '';
                    resultDiv.className = 'coupon-result';
                }
                return;
            }

            const price = parseFloat(priceText.textContent.replace(/[^0-9.]/g, ''));

            const formData = new FormData();
            formData.append('code', code); // The API can get the price itself if needed

            fetch((window.siteBasePath || '') + '/validate_coupon.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const discountAmount = (price * data.discount_percent / 100);
                        resultDiv.textContent = `Hợp lệ! Giảm ${data.discount_percent}%.`;
                        resultDiv.className = 'coupon-result success';
                    } else {
                        resultDiv.textContent = data.message;
                        resultDiv.className = 'coupon-result error';
                    }
                })
                .catch(err => {
                    resultDiv.textContent = 'Lỗi khi xác thực coupon.';
                    resultDiv.className = 'coupon-result error';
                    console.error('Coupon validation error:', err);
                });
        });
    }

    // --- Coupon Validation on Checkout Page ---
    const checkoutValidateBtn = document.getElementById('paste-and-validate-checkout-btn');
    if (checkoutValidateBtn) {
        checkoutValidateBtn.addEventListener('click', handlePasteAndValidateCheckout);

        // Tự động xác thực coupon nếu có sẵn khi tải trang
        const couponInput = document.getElementById('coupon_code');
        if (couponInput && couponInput.dataset.autoValidate === 'true' && couponInput.value) {
            // Sử dụng một chút delay để đảm bảo trang đã tải xong
            setTimeout(() => handlePasteAndValidateCheckout(false), 100);
        }
    }


    async function handlePasteAndValidateCheckout(fromClick = true) {
        const couponInput = document.getElementById('coupon_code');
        const resultDiv = document.querySelector('.checkout-layout .coupon-result');
        resultDiv.textContent = '';
        resultDiv.className = 'coupon-result';

        // Tìm hoặc tạo trường input ẩn để lưu mã đã xác thực
        let validatedCouponInput = document.getElementById('validated_coupon_code');
        if (!validatedCouponInput) {
            validatedCouponInput = document.createElement('input');
            validatedCouponInput.type = 'hidden';
            validatedCouponInput.id = 'validated_coupon_code';
            validatedCouponInput.name = 'validated_coupon_code'; // Tên này sẽ được gửi lên server
            couponInput.form.appendChild(validatedCouponInput);
        }

        let code = couponInput.value.trim();

        // Nếu người dùng nhấn nút, cố gắng dán từ clipboard
        if (fromClick) {
            try {
                const clipboardText = await navigator.clipboard.readText();
                if (clipboardText) {
                    couponInput.value = clipboardText;
                    code = clipboardText;
                }
            } catch (err) {
                console.warn('Không thể đọc clipboard:', err);
            }
        }

        if (!code) {
            resultDiv.textContent = 'Vui lòng nhập hoặc dán mã giảm giá.';
            resultDiv.className = 'coupon-result error';
            updateCheckoutSummary(0); // Reset discount
            return;
        }

        try {
            const formData = new FormData();
            formData.append('code', code);

            const response = await fetch((window.siteBasePath || '') + '/validate_coupon.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (data.success) {
                resultDiv.textContent = `✓ ${data.message}`;
                resultDiv.className = 'coupon-result success';
                validatedCouponInput.value = code; // Lưu mã hợp lệ vào trường ẩn
                updateCheckoutSummary(data.discount_percent); // Gọi hàm cập nhật giao diện
            } else {
                throw new Error(data.message || 'Mã không hợp lệ.');
            }
        } catch (err) {
            resultDiv.textContent = `✗ ${err.message}`;
            resultDiv.className = 'coupon-result error';
            validatedCouponInput.value = ''; // Xóa mã không hợp lệ
            updateCheckoutSummary(0); // Reset discount on error
        } finally {
            // Cập nhật lại trạng thái của nút sau khi hoàn tất
            const btn = document.getElementById('paste-and-validate-checkout-btn');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Dán & Kiểm tra';
            }
        }
    }

    // Hàm cập nhật giao diện Order Summary
    function updateCheckoutSummary(discountPercent) {
        const subtotalEl = document.getElementById('summary-subtotal');
        const discountRowEl = document.getElementById('summary-discount-row');
        const discountAmountEl = document.getElementById('summary-discount-amount');
        const discountLabelEl = document.getElementById('summary-discount-label');
        const subtotalAfterDiscountRowEl = document.getElementById('summary-subtotal-after-discount-row');
        const subtotalAfterDiscountEl = document.getElementById('summary-subtotal-after-discount');
        const totalEl = document.getElementById('summary-total');
        const shippingFeeEl = document.getElementById('summary-shipping-fee');
        if (!subtotalEl || !discountRowEl || !discountAmountEl || !discountLabelEl || !totalEl || !shippingFeeEl || !subtotalAfterDiscountRowEl || !subtotalAfterDiscountEl) return;

        const subtotal = parseFloat(subtotalEl.dataset.value || '0');
        const shippingFee = parseFloat(shippingFeeEl.dataset.value || '0');
        const discountAmount = (subtotal * discountPercent) / 100;
        const subtotalAfterDiscount = subtotal - discountAmount;
        const total = subtotalAfterDiscount + shippingFee;

        const formatVND = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

        if (discountPercent > 0) {
            discountLabelEl.textContent = `Giảm giá (${discountPercent}%)`;
            discountAmountEl.textContent = `- ${formatVND(discountAmount)}`;
            subtotalAfterDiscountEl.textContent = formatVND(subtotalAfterDiscount);
            discountRowEl.style.display = 'flex';
            subtotalAfterDiscountRowEl.style.display = 'flex';
            subtotalEl.style.textDecoration = 'line-through'; // Gạch giá gốc
        } else {
            discountRowEl.style.display = 'none';
            subtotalAfterDiscountRowEl.style.display = 'none';
            subtotalEl.style.textDecoration = 'none'; // Bỏ gạch giá gốc
        }
        totalEl.textContent = formatVND(total);
    }

    // --- JS-powered Star Rating ---
    const starRatingWrapper = document.querySelector('.star-rating-js');
    if (starRatingWrapper) {
        const stars = starRatingWrapper.querySelectorAll('.star');
        const ratingInput = document.getElementById('rating-value');

        function updateStars(rating) {
            stars.forEach(star => {
                const starValue = parseInt(star.dataset.value, 10);
                if (starValue <= rating) {
                    star.classList.add('selected');
                    star.textContent = '★';
                } else {
                    star.classList.remove('selected');
                    star.textContent = '☆';
                }
            });
        }

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const value = parseInt(star.dataset.value, 10);
                ratingInput.value = value;
                updateStars(value);
            });

            star.addEventListener('mouseover', () => {
                const value = parseInt(star.dataset.value, 10);
                stars.forEach((s, i) => {
                    s.classList.toggle('hover', i < value);
                });
            });
        });

        starRatingWrapper.addEventListener('mouseleave', () => {
            stars.forEach(s => s.classList.remove('hover'));
        });
    }
});