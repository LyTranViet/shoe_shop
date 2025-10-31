<?php
require_once __DIR__ . '/includes/init.php';
header('Content-Type: application/json; charset=utf-8');

// --- Lấy input từ user ---
$input_raw = trim($_POST['q'] ?? '');
if ($input_raw === '') {
    echo json_encode(['success' => false, 'reply' => 'Vui lòng nhập câu hỏi hoặc từ khóa.']);
    exit;
}

$db = get_db();
$input = mb_strtolower($input_raw, 'UTF-8');

// --- Helper: parse giá kiểu "500k", "1.2tr" ---
function parse_price_token($token) {
    $token = trim($token);
    if (preg_match('/(\d+[\.,]?\d*)\s*(k|nghìn|ngàn)\b/i', $token, $m))
        return (int)round((float)str_replace(',', '.', $m[1]) * 1000);
    if (preg_match('/(\d+[\.,]?\d*)\s*(tr|triệu)\b/i', $token, $m))
        return (int)round((float)str_replace(',', '.', $m[1]) * 1000000);
    if (preg_match('/^\d+$/', $token))
        return (int)$token;
    return null;
}

// --- Color map ---
$color_map = [
    'black'=>'đen','white'=>'trắng','red'=>'đỏ','blue'=>'xanh','green'=>'xanh',
    'yellow'=>'vàng','brown'=>'nâu','grey'=>'xám','gray'=>'xám','pink'=>'hồng',
    'purple'=>'tím','orange'=>'cam','silver'=>'bạc','beige'=>'be','be'=>'be',
    'đen'=>'đen','trắng'=>'trắng','đỏ'=>'đỏ','xanh'=>'xanh','vàng'=>'vàng','nâu'=>'nâu',
    'xám'=>'xám','hồng'=>'hồng','tím'=>'tím','cam'=>'cam','bạc'=>'bạc'
];

// --- Tách token và build điều kiện SQL ---
$tokens = preg_split('/\s+/', $input);
$conditions = [];
$params = [];
$keywords = [];
$price_handled = $size_handled = $color_handled = false;

foreach ($tokens as $t) {
    // --- Price ---
    $p = parse_price_token($t);
    if ($p !== null) {
        if (strpos($input, 'dưới') !== false) $conditions[] = 'p.price <= ?';
        elseif (strpos($input, 'trên') !== false) $conditions[] = 'p.price >= ?';
        else $conditions[] = 'p.price <= ?';
        $params[] = $p;
        $price_handled = true;
        continue;
    }

    // --- Size ---
    if (preg_match('/^(size|số|sz)[:]?(\d{1,2}(?:[\.,]\d)?)$/i', $t, $m)) {
        $size = str_replace(',', '.', $m[2]);
        $conditions[] = 'EXISTS (SELECT 1 FROM product_sizes ps WHERE ps.product_id = p.id AND ps.size = ?)';
        $params[] = $size;
        $size_handled = true;
        continue;
    }

    // --- Color ---
    if (isset($color_map[$t])) {
        $cv = $color_map[$t];
        $conditions[] = '(p.name LIKE ? OR p.description LIKE ? OR EXISTS (SELECT 1 FROM product_images pi2 WHERE pi2.product_id = p.id AND pi2.url LIKE ?))';
        $params = array_merge($params, ["%$cv%", "%$cv%", "%$cv%"]);
        $color_handled = true;
        continue;
    }

    // --- Keywords ---
    if (mb_strlen($t) > 1) $keywords[] = $t;
}

// --- Kiểm tra pattern "size 42" nếu chưa handle ---
if (!$size_handled && preg_match('/(?:size|số|sz)[:\s]*([\d,\.]+)/i', $input, $m2)) {
    $size = str_replace(',', '.', $m2[1]);
    $conditions[] = 'EXISTS (SELECT 1 FROM product_sizes ps WHERE ps.product_id = p.id AND ps.size = ?)';
    $params[] = $size;
}

// --- Keywords condition ---
if (!empty($keywords)) {
    $kwConds = [];
    foreach ($keywords as $kw) {
        $like = "%$kw%";
        $kwConds[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR b.name LIKE ?)';
        $params = array_merge($params, [$like,$like,$like,$like]);
    }
    $conditions[] = '(' . implode(' OR ', $kwConds) . ')';
}

// --- SQL query ---
$sql = "SELECT p.id, p.name, p.description, p.price,
        pi.url AS image,
        COALESCE(ps_all.sizes, '') AS sizes,
        c.name AS category, b.name AS brand
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
    LEFT JOIN (
        SELECT product_id, GROUP_CONCAT(DISTINCT size ORDER BY size SEPARATOR ',') AS sizes
        FROM product_sizes GROUP BY product_id
    ) ps_all ON ps_all.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id";

if (!empty($conditions)) $sql .= ' WHERE ' . implode(' AND ', $conditions);
$sql .= ' GROUP BY p.id ORDER BY p.price ASC LIMIT 12';

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'reply'=>'Lỗi truy vấn: '.$e->getMessage()]);
    exit;
}

// --- Chuẩn bị kết quả ---
$results = [];
foreach ($rows as $r) {
    $detectedColor = null;
    $hay = mb_strtolower(($r['name'] ?? '').' '.($r['description'] ?? ''),'UTF-8');
    foreach ($color_map as $key=>$canon) {
        if (mb_strpos($hay,$key)!==false){ $detectedColor=$canon; break; }
    }
    $results[] = [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'description' => $r['description'],
        'price' => (float)$r['price'],
        'image' => $r['image'] ?? 'assets/images/product/default.jpg',
        'color' => $detectedColor,
        'sizes' => $r['sizes'] ? explode(',', $r['sizes']) : [],
        'category' => $r['category'] ?? null,
        'brand' => $r['brand'] ?? null,
        'url' => 'product.php?id='.$r['id']
    ];
}

// --- Reply ---
$reply = empty($results)
    ? "Không tìm thấy sản phẩm phù hợp với '".$input_raw."'. Hãy thử từ khóa khác (ví dụ: 'Nike', 'size 42', 'đen', 'dưới 500k')."
    : 'Tôi tìm thấy '.count($results).' sản phẩm phù hợp. Bạn có thể click vào size để xem tất cả sản phẩm cùng size.';

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'results' => $results
], JSON_UNESCAPED_UNICODE);
