<?php
require_once __DIR__ . '/includes/header.php';

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $stmt = $db->prepare("SELECT * FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT 30");
    $like = "%$q%";
    $stmt->execute([$like]);
    $results = $stmt->fetchAll();
}
?>

<main style="max-width:900px;margin:auto;padding:2rem 1rem;">
    <h2>Kết quả tìm kiếm cho: <span style="color:var(--primary)"><?php echo htmlspecialchars($q); ?></span></h2>
    <?php if ($q === ''): ?>
        <p>Vui lòng nhập từ khóa để tìm kiếm sản phẩm.</p>
    <?php elseif (empty($results)): ?>
        <p>Không tìm thấy sản phẩm phù hợp.</p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:2rem;">
            <?php foreach ($results as $p): ?>
                <div style="background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.07);padding:1rem;text-align:center;">
                    <a href="product.php?id=<?php echo $p['id']; ?>">
                        <img src="<?php echo htmlspecialchars($p['image'] ?? 'assets/images/product-placeholder.png'); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" style="width:100%;max-width:160px;height:160px;object-fit:cover;border-radius:8px;">
                        <h3 style="margin:0.5rem 0 0.25rem;font-size:1.1rem;color:var(--dark);"><?php echo htmlspecialchars($p['name']); ?></h3>
                    </a>
                    <div style="color:var(--primary);font-weight:600;"><?php echo number_format($p['price'], 0); ?>₫</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
