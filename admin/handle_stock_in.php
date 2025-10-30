<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$db = get_db();
$errors = [];

$id = (int)($_POST['id'] ?? 0);
$supplier_id = (int)($_POST['supplier_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$employee_id = current_user_id();

$product_ids = $_POST['product_id'] ?? [];
$size_ids = $_POST['size_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$prices = $_POST['price'] ?? [];
if ($supplier_id <= 0) $errors[] = "Vui lòng chọn nhà cung cấp.";
if (empty($product_ids) || count($product_ids) === 0) {
    $errors[] = "Vui lòng thêm ít nhất một sản phẩm vào phiếu nhập.";
}
else {
    // Validate each product line
    foreach ($product_ids as $key => $pid) {
        $product_id = (int)($pid ?? 0);
        $productsize_id = (int)($size_ids[$key] ?? 0);
        $quantity = (int)($quantities[$key] ?? 0);
        $price = (float)($prices[$key] ?? 0);

        if ($product_id <= 0) {
            $errors[] = "Dòng " . ($key + 1) . ": Vui lòng chọn sản phẩm.";
        }
        if ($productsize_id <= 0) {
            $errors[] = "Dòng " . ($key + 1) . ": Vui lòng chọn size cho sản phẩm.";
        }
        if ($quantity <= 0) {
            $errors[] = "Dòng " . ($key + 1) . ": Số lượng phải lớn hơn 0.";
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode("\n", $errors)]);
    exit;
}

// --- Process if valid ---
$db->beginTransaction();
try {
    // --- CREATE ---
    // 1. Create Import Receipt
    $receipt_code = 'PN' . date('YmdHis');
    $total_amount = 0;
    foreach ($product_ids as $key => $pid) {
        $quantity = (int)($quantities[$key] ?? 0);
        $price = (float)($prices[$key] ?? 0);
        if ($quantity > 0 && $price > 0) {
            $total_amount += $quantity * $price;
        }
    }

    // Sửa đổi: Thêm cột 'status' với giá trị mặc định là 'Đang chờ xác nhận'
    $stmt = $db->prepare("INSERT INTO import_receipt (receipt_code, supplier_id, employee_id, total_amount, note, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$receipt_code, $supplier_id, $employee_id, $total_amount, $note, 'Đang chờ xác nhận']);

    $import_id = $db->lastInsertId();

    // 2. Create Details and Update Stock
    foreach ($product_ids as $key => $pid) {
        $productsize_id = (int)($size_ids[$key] ?? 0);
        $quantity = (int)($quantities[$key] ?? 0);
        $price = (float)($prices[$key] ?? 0);
        $batch_code = 'L' . date('Ymd') . '-' . $pid . '-' . $productsize_id;

        if ($productsize_id > 0 && $quantity > 0) {
            // a. Insert into product_batch. quantity_remaining is 0 initially, updated on 'Xác nhận' status.
            $batchStmt = $db->prepare("INSERT INTO product_batch (productsize_id, batch_code, quantity_in, quantity_remaining, import_date) VALUES (?, ?, ?, 0, NOW())");
            $batchStmt->execute([$productsize_id, $batch_code, $quantity]);
            $batch_id = $db->lastInsertId();

            // b. Insert into import_receipt_detail with the new batch_id
            $detailStmt = $db->prepare("INSERT INTO import_receipt_detail (import_id, productsize_id, batch_id, batch_code, quantity, price) VALUES (?, ?, ?, ?, ?, ?)");
            $detailStmt->execute([$import_id, $productsize_id, $batch_id, $batch_code, $quantity, $price]);
        }
    }

    $db->commit();
    echo json_encode([
        'success' => true, 
        'message' => "Đã tạo phiếu nhập {$receipt_code} thành công!",
        'redirect' => 'index.php?page=stock_in'
    ]);
    exit;

} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => "Lỗi cơ sở dữ liệu: " . $e->getMessage()]);
    exit;
}