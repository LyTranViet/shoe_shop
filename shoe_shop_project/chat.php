<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/helper.php';
header('Content-Type: application/json; charset=utf-8');
$input = trim($_POST['q'] ?? '');
if ($input === '') {
    echo json_encode(['success' => false, 'reply' => 'Vui lòng nhập câu hỏi hoặc từ khóa.']);
    exit;
}
$db = get_db();
$input_raw = $input;
$input = mb_strtolower($input, 'UTF-8');

// Helper: normalize numeric like "500k" or "1.2tr" to integer VND
function parse_price_token($token) {
    $token = trim($token);
    if (preg_match('/(\d+[\.,]?\d*)\s*(k|nghìn|ngàn)\b/i', $token, $m)) {
        return (int)round((float)str_replace(',', '.', $m[1]) * 1000);
    }
    if (preg_match('/(\d+[\.,]?\d*)\s*(tr|triệu)\b/i', $token, $m)) {
        return (int)round((float)str_replace(',', '.', $m[1]) * 1000000);
    }
    if (preg_match('/^(\d+)$/', $token)) {
        // plain number assume VND
        return (int)$token;
    }
    return null;
}

// Known color map (english/vietnamese -> canonical VN name)
$color_map = [
    'black' => 'đen','đen' => 'đen',
    'white' => 'trắng','trắng'=>'trắng',
    'red'=>'đỏ','đỏ'=>'đỏ',
    'blue'=>'xanh','green'=>'xanh','xanh'=>'xanh',
    'yellow'=>'vàng','vàng'=>'vàng',
    'brown'=>'nâu','nâu'=>'nâu',
    'grey'=>'xám','gray'=>'xám','xám'=>'xám',
    'pink'=>'hồng','hồng'=>'hồng',
    'purple'=>'tím','tím'=>'tím',
    'orange'=>'cam','cam'=>'cam',
    'silver'=>'bạc','bạc'=>'bạc',
    'beige'=>'be','be'=>'be'
];

// Parse query tokens
$tokens = preg_split('/\s+/', $input);
$conditions = [];
$params = [];
$price_handled = false;
$size_handled = false;
$color_handled = false;
$keywords = [];

foreach ($tokens as $t) {
    // price tokens
    $p = parse_price_token($t);
    if ($p !== null) {
        // look around token for "dưới" or "trên"
        if (strpos($input, 'dưới') !== false || strpos($input, '<=') !== false) {
            $conditions[] = 'p.price <= ?';
            $params[] = $p;
        } elseif (strpos($input, 'trên') !== false || strpos($input, '>=') !== false) {
            $conditions[] = 'p.price >= ?';
            $params[] = $p;
        } else {
            // interpret as max price
            $conditions[] = 'p.price <= ?';
            $params[] = $p;
        }
        $price_handled = true;
        continue;
    }

    // size tokens: size 42 or size:42 or số 42
    if (preg_match('/^(size|số|sz)[:]?([0-9]{1,2}(?:[\.,][0-9])?)$/i', $t, $m)) {
        $size = str_replace(',', '.', $m[2]);
        // filter using EXISTS on product_sizes so aggregation isn't restricted
        $conditions[] = 'EXISTS (SELECT 1 FROM product_sizes psf WHERE psf.product_id = p.id AND psf.size = ?)';
        $params[] = $size;
        $size_handled = true;
        continue;
    }

    // tokens like "size" followed by number in next token handled below
    // color token
    $tn = trim($t);
    if (isset($color_map[$tn])) {
        $cv = $color_map[$tn];
        // color is not a column in products; search name/description or any image filename via EXISTS
        $conditions[] = '(p.name LIKE ? OR p.description LIKE ? OR EXISTS (SELECT 1 FROM product_images pi2 WHERE pi2.product_id = p.id AND pi2.url LIKE ?))';
        $params = array_merge($params, ["%$cv%", "%$cv%", "%$cv%"]);
        $color_handled = true;
        continue;
    }

    // if token looks like "size" then next token maybe number
    if (in_array($tn, ['size','số','sz'])) {
        // handled below
        continue;
    }

    // otherwise treat as keyword
    if (mb_strlen($tn) > 1) {
        $keywords[] = $tn;
    }
}

// handle patterns like "size 42" where number is next token
if (!$size_handled && preg_match('/(?:size|số|sz)\s*[:]?[\s]*(\d+[\.,]?\d*)/i', $input, $m2)) {
    $size = str_replace(',', '.', $m2[1]);
    $conditions[] = 'EXISTS (SELECT 1 FROM product_sizes psf WHERE psf.product_id = p.id AND psf.size = ?)';
    $params[] = $size;
    $size_handled = true;
}

// if keywords exist, build keyword conditions
if (!empty($keywords)) {
    $kwConds = [];
    foreach ($keywords as $kw) {
        $like = "%$kw%";
        $kwConds[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR b.name LIKE ?)';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }
    if (!empty($kwConds)) {
        $conditions[] = '(' . implode(' OR ', $kwConds) . ')';
    }
}

// final SQL
$sql = "SELECT p.id, p.name, p.description, p.price,
        pi.url AS image,
        COALESCE(ps_all.sizes, '') AS sizes,
        c.name AS category, b.name AS brand
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
    LEFT JOIN (
        SELECT product_id, GROUP_CONCAT(DISTINCT size ORDER BY size SEPARATOR ',') AS sizes
        FROM product_sizes
        GROUP BY product_id
    ) ps_all ON ps_all.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id";
if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' GROUP BY p.id ORDER BY p.price ASC LIMIT 12';

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'reply' => 'Lỗi truy vấn: ' . $e->getMessage()]);
    exit;
}

$results = [];
foreach ($rows as $r) {
    // attempt to detect color from name/description using color map
    $detectedColor = null;
    $hay = mb_strtolower(($r['name'] ?? '') . ' ' . ($r['description'] ?? ''), 'UTF-8');
    foreach ($color_map as $key => $canon) {
        if (mb_strpos($hay, $key) !== false) {
            $detectedColor = $canon;
            break;
        }
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
        'url' => 'product.php/' . createSlug($r['name']) . '-' . $r['id']
    ];
}

if (empty($results)) {
    $reply = "Xin lỗi, không tìm thấy sản phẩm phù hợp với '" . htmlspecialchars($input_raw, ENT_QUOTES, 'UTF-8') . "'. Hãy thử các từ khóa khác như 'Nike', 'size 42', 'đen', 'dưới 500k'.";
} else {
    $reply = 'Tôi tìm thấy ' . count($results) . ' sản phẩm phù hợp.';
}

echo json_encode(['success' => true, 'reply' => $reply, 'results' => $results], JSON_UNESCAPED_UNICODE);
