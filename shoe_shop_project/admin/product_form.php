<?php
// This file is included by products.php
if (!isset($db) || !isset($action)) {
    header('Location: index.php?page=products');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$product = null;
$product_sizes = [];
$product_images = [];

// Fetch data for editing
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    $stmt_images = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id");
    $stmt_images->execute([$id]);
    $product_images = $stmt_images->fetchAll();

    $stmt_sizes = $db->prepare("SELECT size, stock FROM product_sizes WHERE product_id = ?");
    $stmt_sizes->execute([$id]);
    $product_sizes = $stmt_sizes->fetchAll(PDO::FETCH_KEY_PAIR);
}

$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$brands = $db->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
?>
<header class="admin-header">
    <h2><?php echo $action === 'edit' ? '✏️ Edit Product' : '➕ Add New Product'; ?></h2>
    <a href="index.php?page=products" class="btn">← Back to List</a>
</header>

<?php if (!empty($errors)): ?>
    <div class="alert-error">
        <?php foreach ($errors as $error) echo "<p>⚠️ $error</p>"; ?>
    </div>
<?php endif; ?>

<form class="admin-form" method="POST" action="index.php?page=products" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo $product['id'] ?? 0; ?>">
    <input type="hidden" name="form_action" value="<?php echo $action; ?>">

    <div class="form-group">
        <label>Product Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label>Product Code</label>
        <input type="text" name="code" value="<?php echo htmlspecialchars($product['code'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label>Price (₫)</label>
        <input type="number" name="price" step="1" value="<?php echo htmlspecialchars($product['price'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
        <label>Category</label>
        <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($product) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Brand</label>
        <select name="brand_id" required>
            <option value="">-- Select Brand --</option>
            <?php foreach ($brands as $brand): ?>
                <option value="<?php echo $brand['id']; ?>" <?php echo (isset($product) && $product['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($brand['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="5" placeholder="Write a short description..."><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label>Sizes and Stock</label>
        <div id="sizes-container">
            <?php if (!empty($product_sizes)): ?>
                <?php foreach ($product_sizes as $size => $stock): ?>
                    <?php $has_stock = (int)$stock > 0; ?>
                    <div>
                        <input type="text" name="sizes[]" value="<?php echo htmlspecialchars($size); ?>" <?php if ($has_stock) echo 'readonly'; ?> title="<?php if ($has_stock) echo 'Không thể sửa size đã có tồn kho.'; ?>">
                        <input type="number" name="stocks[]" value="<?php echo htmlspecialchars($stock); ?>" readonly title="Không thể sửa tồn kho trực tiếp. Vui lòng dùng chức năng Nhập/Xuất kho.">
                        <button type="button" class="btn-remove-size" onclick="this.parentElement.remove()" <?php if ($has_stock) echo 'disabled'; ?> title="<?php if ($has_stock) echo 'Không thể xóa size đã có tồn kho.'; ?>">❌</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" id="add-size-btn" style="margin-top: 10px;">➕ Add Size</button>
    </div>

    <div class="form-group">
        <label>Product Images</label>
        <div class="image-management">
            <?php if (!empty($product_images)): ?>
                <div class="existing-images">
                    <?php foreach ($product_images as $image): ?>
                        <div class="img-card">
                            <img src="../<?php echo htmlspecialchars($image['url']); ?>" alt="">
                            <div class="img-actions">
                                <label><input type="radio" name="main_image" value="<?php echo $image['id']; ?>" <?php echo $image['is_main'] ? 'checked' : ''; ?>> Main</label><br>
                                <label><input type="checkbox" name="delete_images[]" value="<?php echo $image['id']; ?>"> Delete</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="file-upload-wrapper">
                <label for="images" class="btn">📁 Choose Images</label>
                <input type="file" id="images" name="images[]" multiple accept="image/*" hidden>
                <span id="file-name-display">No file chosen</span>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">
            <?php echo $action === 'edit' ? '💾 Update Product' : '✅ Save Product'; ?>
        </button>
    </div>
</form>

<script>
// Hàm xử lý việc thêm dòng size mới
function addNewSizeRow() {
    const container = document.getElementById('sizes-container');
    const div = document.createElement('div');
    div.innerHTML = `
        <input type="text" name="sizes[]" placeholder="Nhập size mới (ví dụ: 41)" required>
        <input type="number" name="stocks[]" value="0" readonly title="Tồn kho ban đầu là 0. Dùng chức năng Nhập kho để thêm hàng.">
        <button type="button" class="btn-remove-size" onclick="this.parentElement.remove()">❌</button>
    `;
    container.appendChild(div);
}

// Gán hàm addNewSizeRow cho sự kiện click của nút bấm
document.getElementById('add-size-btn').addEventListener('click', addNewSizeRow);

document.getElementById('images').addEventListener('change', function() {
    const fileDisplay = document.getElementById('file-name-display');
    fileDisplay.textContent = this.files.length > 0
        ? Array.from(this.files).map(f => f.name).join(', ')
        : 'No file chosen';
});
</script>
<style>
    #sizes-container div { display: flex; gap: 10px; margin-bottom: 5px; }
    #sizes-container input[type="text"] { flex: 1; }
    #sizes-container input[type="number"] { width: 100px; }
    .btn-remove-size { background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer; padding: 0 10px; }
    .btn-remove-size:disabled { background: #ccc; cursor: not-allowed; }
</style>
