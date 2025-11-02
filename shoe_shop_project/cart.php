<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// Kiểm tra AJAX
function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Trả JSON
function json_res($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

// === TÍNH TỔNG GIỎ HÀNG (áp dụng mã giảm sản phẩm) ===
function calculate_cart_total($db = null, $userId = null) {
    if (!$db) $db = get_db();
    $subtotal = 0;
    $items = [];

    if ($userId) {
        $stmt = $db->prepare('SELECT ci.price, ci.quantity FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?');
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();
    } else {
        foreach ($_SESSION['cart'] ?? [] as $it) {
            $p = $db->prepare('SELECT price FROM products WHERE id = ?');
            $p->execute([(int)$it['product_id']]);
            $price = (float)($p->fetchColumn() ?: 0);
            $items[] = ['price' => $price, 'quantity' => (int)$it['quantity']];
        }
    }

    foreach ($items as $it) $subtotal += $it['price'] * $it['quantity'];

    // Áp dụng mã giảm giá sản phẩm
    if (!empty($_SESSION['coupon_code'])) {
        $code = strtoupper($_SESSION['coupon_code']);
        $stmt = $db->prepare("SELECT discount_percent FROM coupons WHERE UPPER(code) = ? AND discount_type = 'product' AND (valid_to IS NULL OR valid_to >= NOW())");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();
        if ($coupon) {
            $subtotal = $subtotal * (1 - $coupon['discount_percent'] / 100);
        }
    }

    return round($subtotal);
}

// === XỬ LÝ POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    // ADD TO CART
    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));
        $size = $_POST['size'] ?? null;

        // Lưu mã giảm giá sản phẩm
        $coupon_code = trim($_POST['coupon_code'] ?? '');
        if (!empty($coupon_code)) {
            $_SESSION['coupon_code'] = $coupon_code;
        }

        // Lưu mã giảm phí vận chuyển
        $shipping_coupon = trim($_POST['validated_shipping_coupon_code'] ?? '');
        if (!empty($shipping_coupon)) {
            $_SESSION['shipping_coupon'] = $shipping_coupon;
            $_SESSION['shipping_discount'] = (float)($_POST['shipping_discount_amount'] ?? 0);
        } else {
            unset($_SESSION['shipping_coupon'], $_SESSION['shipping_discount']);
        }

        if (is_logged_in()) {
            $db = get_db();
            $uid = current_user_id();
            try {
                $st = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
                $st->execute([$uid]);
                $cartId = $st->fetchColumn();
                if (!$cartId) {
                    $ins = $db->prepare('INSERT INTO carts (user_id) VALUES (?)');
                    $ins->execute([$uid]);
                    $cartId = $db->lastInsertId();
                }

                $ci = $db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL))');
                $ci->execute([$cartId, $pid, $size, $size]);
                $row = $ci->fetch();
                if ($row) {
                    $newQty = $row['quantity'] + $qty;
                    $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                    $up->execute([$newQty, $row['id']]);
                } else {
                    $pstmt = $db->prepare('SELECT price FROM products WHERE id = ?');
                    $pstmt->execute([$pid]);
                    $price = $pstmt->fetchColumn() ?: 0;
                    $ins = $db->prepare('INSERT INTO cart_items (cart_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
                    $ins->execute([$cartId, $pid, $size, $qty, $price]);
                }

                $count = $db->query("SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = $cartId")->fetchColumn();
                $total = calculate_cart_total($db, $uid);

                if (is_ajax()) json_res(['success' => true, 'count' => $count, 'total' => $total]);
                header('Location: cart.php'); exit;
            } catch (Exception $e) { }
        }

        // Session fallback
        $key = $pid . '|' . ($size ?? '');
        if (!isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key] = ['product_in' => $pid, 'quantity' => $qty, 'size' => $size];
        } else {
            $_SESSION['cart'][$key]['quantity'] += $qty;
        }

        $count = array_sum(array_column($_SESSION['cart'], 'quantity'));
        $total = calculate_cart_total();

        if (is_ajax()) json_res(['success' => true, 'count' => $count, 'total' => $total]);
        header('Location: cart.php'); exit;
    }

    // UPDATE
    if ($action === 'update') {
        $newQty = max(1, (int)($_POST['quantity'] ?? 1));
        if (is_logged_in()) {
            $db = get_db();
            $id = (int)($_POST['cart_item_id'] ?? 0);
            $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $up->execute([$newQty, $id]);
            $total = calculate_cart_total($db, current_user_id());
            $stmt = $db->prepare('SELECT price FROM cart_items WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $subtotal = $row ? $row['price'] * $newQty : 0;

            // Áp dụng giảm giá cho subtotal
            if (!empty($_SESSION['coupon_code'])) {
                $code = strtoupper($_SESSION['coupon_code']);
                $cstmt = $db->prepare("SELECT discount_percent FROM coupons WHERE UPPER(code) = ? AND discount_type = 'product' AND (valid_to IS NULL OR valid_to >= NOW())");
                $cstmt->execute([$code]);
                $coupon = $cstmt->fetch();
                if ($coupon) {
                    $subtotal = $subtotal * (1 - $coupon['discount_percent'] / 100);
                }
            }
            $subtotal = round($subtotal);

            json_res(['success' => true, 'item_subtotal' => $subtotal, 'total' => $total]);
        } else {
            $key = $_POST['session_key'] ?? null;
            if ($key && isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantity'] = $newQty;
            }
            $total = calculate_cart_total();
            $db = get_db();
            $item = $_SESSION['cart'][$key];
            $p = $db->prepare('SELECT price FROM products WHERE id = ?');
            $p->execute([(int)$item['product_id']]);
            $price = $p->fetchColumn() ?: 0;
            $subtotal = $price * $newQty;

            // Áp dụng mã giảm
            if (!empty($_SESSION['coupon_code'])) {
                $code = strtoupper($_SESSION['coupon_code']);
                $cstmt = $db->prepare("SELECT discount_percent FROM coupons WHERE UPPER(code) = ? AND discount_type = 'product' AND (valid_to IS NULL OR valid_to >= NOW())");
                $cstmt->execute([$code]);
                $coupon = $cstmt->fetch();
                if ($coupon) {
                    $subtotal = $subtotal * (1 - $coupon['discount_percent'] / 100);
                }
            }
            $subtotal = round($subtotal);

            json_res(['success' => true, 'item_subtotal' => $subtotal, 'total' => $total]);
        }
    }

    // REMOVE
    if ($action === 'remove') {
        if (is_logged_in()) {
            $db = get_db();
            $id = (int)($_POST['cart_item_id'] ?? 0);
            $db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$id]);
            $total = calculate_cart_total($db, current_user_id());
            $count = $db->query("SELECT COALESCE(SUM(quantity),0) FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = " . current_user_id())->fetchColumn();
            json_res(['success' => true, 'count' => $count, 'total' => $total]);
        } else {
            $key = $_POST['session_key'] ?? null;
            if ($key) unset($_SESSION['cart'][$key]);
            $total = calculate_cart_total();
            $count = array_sum(array_column($_SESSION['cart'], 'quantity'));
            json_res(['success' => true, 'count' => $count, 'total' => $total]);
        }
    }

    json_res(['success' => false, 'error' => 'Unknown action']);
}

// === LOAD GIỎ HÀNG ===
require_once __DIR__ . '/includes/header.php';
$db = get_db();
$items = [];

if (is_logged_in()) {
    $uid = current_user_id();
    $st = $db->prepare('SELECT c.id FROM carts c WHERE c.user_id = ?');
    $st->execute([$uid]);
    $cartId = $st->fetchColumn();
    if ($cartId) {
        $stmt = $db->prepare('SELECT ci.id, ci.product_id, ci.size, ci.quantity, ci.price, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll();
    }
} else {
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $row = $p->fetch();
        if ($row) {
            $items[] = [
                'session_key' => $k,
                'product_id' => $it['product_id'],
                'size' => $it['size'] ?? null,
                'quantity' => $it['quantity'],
                'price' => $row['price'],
                'name' => $row['name']
            ];
        }
    }
}

// Tính tổng đã giảm (dùng chung hàm)
$final_total = calculate_cart_total($db, is_logged_in() ? current_user_id() : null);

// Load ảnh
$imagesByProduct = [];
if (!empty($items)) {
    $ids = array_column($items, 'product_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT product_id, url FROM product_images WHERE product_id IN ($placeholders) AND is_main = 1");
    $stmt->execute($ids);
    $imagesByProduct = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<h2>Giỏ hàng của bạn</h2>

<!-- HIỂN THỊ MÃ ĐÃ ÁP DỤNG (chỉ khi có sản phẩm) -->
<div id="coupon-alerts">
    <?php if (!empty($_SESSION['coupon_code']) && !empty($items)): ?>
        <div class="alert alert-success">
            Đang áp dụng mã giảm giá: <strong><?php echo htmlspecialchars($_SESSION['coupon_code']); ?></strong>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['shipping_coupon']) && !empty($items)): ?>
        <div class="alert alert-info">
            Đang áp dụng mã giảm phí vận chuyển: <strong><?php echo htmlspecialchars($_SESSION['shipping_coupon']); ?></strong>
            <?php if (!empty($_SESSION['shipping_discount'])): ?>
                (Giảm <?php echo number_format($_SESSION['shipping_discount'], 0); ?>đ)
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($items)): ?>
    <p>Giỏ hàng trống.</p>
<?php else: ?>
    <div class="cart-layout">
        <div class="cart-items-list">
            <div class="cart-header">
                <div class="cart-header-product">Sản phẩm</div>
                <div class="cart-header-quantity">Số lượng</div>
                <div class="cart-header-subtotal">Thành tiền</div>
                <div class="cart-header-remove"></div>
            </div>
            <?php foreach ($items as $it):
                $attr = is_logged_in() ? 'data-cart-item-id="' . $it['id'] . '"' : 'data-session-key="' . htmlspecialchars($it['session_key']) . '"';
                $img = $imagesByProduct[$it['product_id']] ?? 'assets/images/product-placeholder.png';
                $item_price = $it['price'];
                $item_subtotal = $item_price * $it['quantity'];

                // Áp dụng mã giảm cho từng item
                if (!empty($_SESSION['coupon_code'])) {
                    $code = strtoupper($_SESSION['coupon_code']);
                    $stmt = $db->prepare("SELECT discount_percent FROM coupons WHERE UPPER(code) = ? AND discount_type = 'product' AND (valid_to IS NULL OR valid_to >= NOW())");
                    $stmt->execute([$code]);
                    $coupon = $stmt->fetch();
                    if ($coupon) {
                        $item_subtotal = $item_subtotal * (1 - $coupon['discount_percent'] / 100);
                    }
                }
                $item_subtotal = round($item_subtotal);
            ?>
                <div class="cart-item" <?php echo $attr; ?>>
                    <div class="cart-item-product">
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="" class="cart-item-image">
                        <div class="cart-item-info">
                            <a href="product.php?id=<?php echo $it['product_id']; ?>" class="cart-item-name"><?php echo htmlspecialchars($it['name']); ?></a>
                            <?php if (!empty($it['size'])): ?><p class="cart-item-meta">Size: <?php echo htmlspecialchars($it['size']); ?></p><?php endif; ?>
                            <p class="cart-item-meta cart-item-price-mobile">Giá: <?php echo number_format($item_price, 0); ?>đ</p>
                        </div>
                    </div>
                    <div class="cart-item-quantity">
                        <form class="ajax-cart-update" method="post">
                            <input type="hidden" name="action" value="update">
                            <?php if (is_logged_in()): ?>
                                <input type="hidden" name="cart_item_id" value="<?php echo $it['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="session_key" value="<?php echo htmlspecialchars($it['session_key']); ?>">
                            <?php endif; ?>
                            <div class="quantity-input">
                                <button type="button" class="qty-btn minus">−</button>
                                <input type="number" name="quantity" value="<?php echo $it['quantity']; ?>" min="1" readonly>
                                <button type="button" class="qty-btn plus">+</button>
                            </div>
                        </form>
                    </div>
                    <div class="cart-item-subtotal">
                        <span><?php echo number_format($item_subtotal, 0); ?></span>đ
                    </div>
                    <div class="cart-item-remove">
                        <form class="ajax-cart-remove" method="post">
                            <input type="hidden" name="action" value="remove">
                            <?php if (is_logged_in()): ?>
                                <input type="hidden" name="cart_item_id" value="<?php echo $it['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="session_key" value="<?php echo htmlspecialchars($it['session_key']); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn-remove-icon" title="Xóa sản phẩm">Xóa</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h3>Tóm tắt đơn hàng</h3>
            <div class="summary-row">
                <span>Tạm tính</span>
                <strong id="cart-total"><?php echo number_format($final_total, 0); ?>đ</strong>
            </div>
            <p>Phí vận chuyển sẽ được tính tại bước thanh toán.</p>
            <a class="btn checkout-btn" href="checkout.php">Tiến hành thanh toán</a>
        </aside>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const alertsContainer = document.getElementById('coupon-alerts');
    const cartTotalEl = document.getElementById('cart-total');

    // Hàm cập nhật tổng tiền
    const updateTotal = (total) => {
        cartTotalEl.textContent = new Intl.NumberFormat('vi-VN').format(total) + 'đ';
    };

    // Hàm xóa thông báo khi giỏ trống
    const clearAlertsIfEmpty = () => {
        const items = document.querySelectorAll('.cart-item');
        if (items.length === 0) {
            alertsContainer.innerHTML = '';
        }
    };

    document.querySelectorAll('.cart-item').forEach(item => {
        const input = item.querySelector('input[name="quantity"]');
        const subtotalEl = item.querySelector('.cart-item-subtotal span');
        const plus = item.querySelector('.qty-btn.plus');
        const minus = item.querySelector('.qty-btn.minus');
        const removeBtn = item.querySelector('.btn-remove-icon');

        plus.addEventListener('click', () => update(parseInt(input.value) + 1));
        minus.addEventListener('click', () => update(Math.max(1, parseInt(input.value) - 1)));
        removeBtn.addEventListener('click', e => { e.preventDefault(); remove(); });

        function update(qty) {
            input.value = qty;
            const form = item.querySelector('.ajax-cart-update');
            const data = new FormData(form);
            data.append('quantity', qty);

            fetch('cart.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        subtotalEl.textContent = new Intl.NumberFormat('vi-VN').format(res.item_subtotal);
                        updateTotal(res.total);
                        clearAlertsIfEmpty();
                    }
                });
        }

        function remove() {
            const form = item.querySelector('.ajax-cart-remove');
            const data = new FormData(form);
            fetch('cart.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        item.remove();
                        updateTotal(res.total);
                        clearAlertsIfEmpty();
                    }
                });
        }
    });

    // Xóa alert nếu giỏ trống lúc load
    clearAlertsIfEmpty();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>