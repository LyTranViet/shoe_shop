<?php
// Cart: handles add/update/remove via POST (AJAX-aware) and renders cart
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

// helper to send JSON
function json_res($arr) { header('Content-Type: application/json'); echo json_encode($arr); exit; }

// compute session cart total
function session_cart_total() {
    $db = get_db();
    $total = 0.0;
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $price = (float)($p->fetchColumn() ?: 0);
        $total += $price * ((int)$it['quantity']);
    }
    return $total;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    // ADD
    if ($action === 'add') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $qty = max(1,(int)($_POST['quantity'] ?? 1));
        $size = $_POST['size'] ?? null;
        $coupon_code = trim($_POST['coupon_code'] ?? ''); // Lấy coupon code từ form

        // If a coupon code is submitted with the add-to-cart action, store it in the session
        if (!empty($coupon_code)) {
            $_SESSION['coupon_code'] = $coupon_code;
        }

        if (is_logged_in()) {
            $db = get_db();
            $uid = current_user_id();
            try {
                // find or create cart
                $st = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
                $st->execute([$uid]);
                $cartId = $st->fetchColumn();
                if (!$cartId) {
                    $ins = $db->prepare('INSERT INTO carts (user_id, session_id) VALUES (?,?)');
                    $ins->execute([$uid, null]);
                    $cartId = $db->lastInsertId();
                }
                // upsert item
                $ci = $db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL)) LIMIT 1');
                $ci->execute([$cartId, $pid, $size, $size]);
                $row = $ci->fetch();
                if ($row) {
                    $newQty = (int)$row['quantity'] + $qty;
                    $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                    $up->execute([$newQty, $row['id']]);
                    $itemSubtotal = 0;
                } else {
                    $pstmt = $db->prepare('SELECT price FROM products WHERE id = ?');
                    $pstmt->execute([$pid]);
                    $price = $pstmt->fetchColumn() ?: 0;
                    $ins = $db->prepare('INSERT INTO cart_items (cart_id, product_id, size, quantity, price) VALUES (?,?,?,?,?)');
                    $ins->execute([$cartId, $pid, $size, $qty, $price]);
                    $itemSubtotal = $price * $qty;
                }
                // compute totals
                $countSt = $db->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = ?');
                $countSt->execute([$cartId]);
                $count = (int)$countSt->fetchColumn();
                $totalSt = $db->prepare('SELECT COALESCE(SUM(quantity * price),0) FROM cart_items WHERE cart_id = ?');
                $totalSt->execute([$cartId]);
                $total = (float)$totalSt->fetchColumn();
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    json_res(['success'=>true,'count'=>$count,'item_subtotal'=>$itemSubtotal,'total'=>$total]);
                }
                header('Location: cart.php'); exit;
            } catch (Exception $e) {
                // fallback to session
            }
        }

        // session fallback
        $sessionKey = $pid . '|' . ($size ?? '');
        if (!isset($_SESSION['cart'][$sessionKey])) {
            $_SESSION['cart'][$sessionKey] = ['product_id'=>$pid,'quantity'=>$qty,'size'=>$size];
        } else {
            $_SESSION['cart'][$sessionKey]['quantity'] = (int)$_SESSION['cart'][$sessionKey]['quantity'] + $qty;
        }
        $count = 0; foreach ($_SESSION['cart'] as $it) { $count += (int)$it['quantity']; }
        $total = session_cart_total();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_res(['success'=>true,'count'=>$count,'item_subtotal'=>0,'total'=>$total]);
        }
        header('Location: cart.php'); exit;
    }

    // UPDATE
    if ($action === 'update') {
        $newQty = max(1,(int)($_POST['quantity'] ?? 1));
        if (is_logged_in()) {
            $db = get_db();
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
            try {
                // update
                $up = $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
                $up->execute([$newQty, $cartItemId]);
                // item subtotal and total
                $itSt = $db->prepare('SELECT ci.quantity, ci.price FROM cart_items ci WHERE ci.id = ? LIMIT 1');
                $itSt->execute([$cartItemId]);
                $itRow = $itSt->fetch();
                $itemSubtotal = $itRow ? ((int)$itRow['quantity'] * (float)$itRow['price']) : 0;
                $uid = current_user_id();
                $totalSt = $db->prepare('SELECT COALESCE(SUM(ci.quantity * ci.price),0) FROM carts c JOIN cart_items ci ON c.id = ci.cart_id WHERE c.user_id = ?');
                $totalSt->execute([$uid]);
                $total = (float)$totalSt->fetchColumn();
                json_res(['success'=>true,'count'=>null,'item_subtotal'=>$itemSubtotal,'total'=>$total]);
            } catch (Exception $e) { json_res(['success'=>false,'error'=>'Could not update']); }
        } else {
            $sessionKey = $_POST['session_key'] ?? null;
            if ($sessionKey && isset($_SESSION['cart'][$sessionKey])) {
                $_SESSION['cart'][$sessionKey]['quantity'] = $newQty;
            } else {
                $pid = (int)($_POST['product_id'] ?? 0);
                foreach ($_SESSION['cart'] as $k => $it) {
                    if ((int)$it['product_id'] === $pid) { $_SESSION['cart'][$k]['quantity'] = $newQty; $sessionKey = $k; break; }
                }
            }
            // compute item subtotal
            $db = get_db();
            $item = $_SESSION['cart'][$sessionKey];
            $p = $db->prepare('SELECT price FROM products WHERE id = ?'); $p->execute([(int)$item['product_id']]);
            $price = (float)($p->fetchColumn() ?: 0);
            $itemSubtotal = $price * (int)$item['quantity'];
            $total = session_cart_total();
            json_res(['success'=>true,'count'=>null,'item_subtotal'=>$itemSubtotal,'total'=>$total]);
        }
    }

    // REMOVE
    if ($action === 'remove') {
        if (is_logged_in()) {
            $db = get_db();
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
            try {
                $del = $db->prepare('DELETE FROM cart_items WHERE id = ?');
                $del->execute([$cartItemId]);
                $uid = current_user_id();
                $totalSt = $db->prepare('SELECT COALESCE(SUM(ci.quantity * ci.price),0) FROM carts c JOIN cart_items ci ON c.id = ci.cart_id WHERE c.user_id = ?');
                $totalSt->execute([$uid]);
                $total = (float)$totalSt->fetchColumn();
                $countSt = $db->prepare('SELECT COALESCE(SUM(ci.quantity),0) FROM carts c JOIN cart_items ci ON c.id = ci.cart_id WHERE c.user_id = ?');
                $countSt->execute([$uid]);
                $count = (int)$countSt->fetchColumn();
                json_res(['success'=>true,'count'=>$count,'item_subtotal'=>0,'total'=>$total]);
            } catch (Exception $e) { json_res(['success'=>false,'error'=>'Could not remove']); }
        } else {
            $sessionKey = $_POST['session_key'] ?? null;
            if ($sessionKey && isset($_SESSION['cart'][$sessionKey])) unset($_SESSION['cart'][$sessionKey]);
            else {
                $pid = (int)($_POST['product_id'] ?? 0);
                foreach ($_SESSION['cart'] as $k => $it) { if ((int)$it['product_id'] === $pid) { unset($_SESSION['cart'][$k]); break; } }
            }
            $count = 0; foreach ($_SESSION['cart'] as $it) { $count += (int)$it['quantity']; }
            $total = session_cart_total();
            json_res(['success'=>true,'count'=>$count,'item_subtotal'=>0,'total'=>$total]);
        }
    }

    json_res(['success'=>false,'error'=>'Unknown action']);
}

// Render cart page
require_once __DIR__ . '/includes/header.php';
$db = get_db();
if (is_logged_in()) {
    $uid = current_user_id();
    $st = $db->prepare('SELECT c.id as cart_id FROM carts c WHERE c.user_id = ? LIMIT 1');
    $st->execute([$uid]);
    $cartId = $st->fetchColumn();
    if ($cartId) {
        $itemsSt = $db->prepare('SELECT ci.id, ci.product_id, ci.size, ci.quantity, ci.price, p.name FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $itemsSt->execute([$cartId]);
        $items = $itemsSt->fetchAll();
    } else {
        $items = [];
    }
} else {
    // session items keyed by session_key
    $items = [];
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $p = $db->prepare('SELECT name, price FROM products WHERE id = ?');
        $p->execute([(int)$it['product_id']]);
        $row = $p->fetch();
        if ($row) {
            $items[] = ['session_key'=>$k,'product_id'=>$it['product_id'],'size'=>$it['size'] ?? null,'quantity'=>$it['quantity'],'price'=>$row['price'],'name'=>$row['name']];
        }
    }
}

// compute total
$total = 0.0;
foreach ($items as $it) { $total += ((float)$it['price']) * ((int)$it['quantity']); }
?>
<h2>Your Cart</h2>

<?php
// Load main images for cart items
$imagesByProduct = [];
if (!empty($items)) {
    try {
        $productIds = array_column($items, 'product_id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $db->prepare("SELECT product_id, url FROM product_images WHERE product_id IN ($placeholders) AND is_main = 1");
        $stmt->execute($productIds);
        $imagesByProduct = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        // ignore
    }
}
?>

<?php if (empty($items)): ?>
    <p>Your cart is empty.</p>
<?php else: ?>
    <div class="cart-layout">
        <div class="cart-items-list">
            <div class="cart-header">
                <div class="cart-header-product">Product</div>
                <div class="cart-header-quantity">Quantity</div>
                <div class="cart-header-subtotal">Subtotal</div>
                <div class="cart-header-remove"></div>
            </div>
            <?php foreach ($items as $it): ?>
                <?php
                $item_id_attr = is_logged_in() ? 'data-cart-item-id="' . (int)$it['id'] . '"' : 'data-session-key="' . htmlspecialchars($it['session_key']) . '"';
                $img_url = $imagesByProduct[$it['product_id']] ?? 'assets/images/product-placeholder.png';
                ?>
                <div class="cart-item" <?php echo $item_id_attr; ?>>
                    <div class="cart-item-product">
                        <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>" class="cart-item-image">
                        <div class="cart-item-info">
                            <a href="product.php?id=<?php echo $it['product_id']; ?>" class="cart-item-name"><?php echo htmlspecialchars($it['name']); ?></a>
                            <?php if (!empty($it['size'])): ?>
                                <p class="cart-item-meta">Size: <?php echo htmlspecialchars($it['size']); ?></p>
                            <?php endif; ?>
                            <p class="cart-item-meta cart-item-price-mobile">Price: $<?php echo number_format($it['price'], 2); ?></p>
                        </div>
                    </div>
                    <div class="cart-item-quantity">
                        <form class="ajax-cart-update" method="post" action="cart.php">
                            <input type="hidden" name="action" value="update">
                            <?php if (is_logged_in()): ?>
                                <input type="hidden" name="cart_item_id" value="<?php echo (int)$it['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="session_key" value="<?php echo htmlspecialchars($it['session_key']); ?>">
                            <?php endif; ?>
                            <div class="quantity-input">
                                <button type="button" class="qty-btn minus" aria-label="Decrease quantity">-</button>
                                <input type="number" name="quantity" value="<?php echo (int)$it['quantity']; ?>" min="1" readonly>
                                <button type="button" class="qty-btn plus" aria-label="Increase quantity">+</button>
                            </div>
                        </form>
                    </div>
                    <div class="cart-item-subtotal">
                        $<span><?php echo number_format($it['price'] * $it['quantity'], 2); ?></span>
                    </div>
                    <div class="cart-item-remove">
                        <form class="ajax-cart-remove" method="post" action="cart.php">
                            <input type="hidden" name="action" value="remove">
                            <?php if (is_logged_in()): ?>
                                <input type="hidden" name="cart_item_id" value="<?php echo (int)$it['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="session_key" value="<?php echo htmlspecialchars($it['session_key']); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn-remove-icon" title="Xóa sản phẩm">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <aside class="cart-summary">
            <h3>Order Summary</h3>
            <div class="summary-row">
                <span>Subtotal</span>
                <strong id="cart-total">$<?php echo number_format($total, 2); ?></strong>
            </div>
            <p>Shipping & taxes calculated at checkout.</p>
            <a class="btn checkout-btn" href="checkout.php">Proceed to checkout</a>
        </aside>
    </div>
<?php endif; ?>
<script>
document.querySelectorAll('.cart-item').forEach(item=>{
    const input = item.querySelector('input[type=number]');
    const subtotalEl = item.querySelector('.cart-item-subtotal span');
    const cartTotalEl = document.getElementById('cart-total');

    // Tăng / giảm
    item.querySelector('.qty-btn.plus').addEventListener('click', ()=>{
        let qty = parseInt(input.value)+1;
        updateCartItem(item, qty, input, subtotalEl, cartTotalEl);
    });
    item.querySelector('.qty-btn.minus').addEventListener('click', ()=>{
        let qty = Math.max(1, parseInt(input.value)-1);
        updateCartItem(item, qty, input, subtotalEl, cartTotalEl);
    });

    // Xóa
    item.querySelector('.btn-remove-icon').addEventListener('click', (e)=>{
        e.preventDefault(); // Ngăn form submit ngay lập tức
        removeCartItem(item, cartTotalEl);
    });
});

function updateCartItem(item, qty, inputEl, subtotalEl, cartTotalEl){
    inputEl.value = qty;
    let formData = new FormData();
    formData.append('action','update');
    formData.append('quantity', qty);
    if(item.dataset.cartItemId) formData.append('cart_item_id', item.dataset.cartItemId);
    else if(item.dataset.sessionKey) formData.append('session_key', item.dataset.sessionKey);

    fetch('cart.php',{method:'POST', body: formData})
    .then(r=>r.json()).then(data=>{
        if(data.success){
            subtotalEl.textContent = parseFloat(data.item_subtotal).toFixed(2);
            cartTotalEl.textContent = parseFloat(data.total).toFixed(2);
        }
    });
}

function removeCartItem(item, cartTotalEl){
    let formData = new FormData();
    formData.append('action','remove');
    if(item.dataset.cartItemId) formData.append('cart_item_id', item.dataset.cartItemId);
    else if(item.dataset.sessionKey) formData.append('session_key', item.dataset.sessionKey);

    fetch('cart.php',{method:'POST', body: formData})
    .then(r=>r.json()).then(data=>{
        if(data.success){
            item.remove();
            cartTotalEl.textContent = parseFloat(data.total).toFixed(2);
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php';
 
