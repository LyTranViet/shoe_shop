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

$export_type = trim($_POST['export_type'] ?? '');
$note = trim($_POST['note'] ?? '');
$employee_id = current_user_id();

$product_ids = $_POST['product_id'] ?? [];
$size_ids = $_POST['size_id'] ?? [];
$batch_ids = $_POST['batch_id'] ?? [];
$quantities = $_POST['quantity'] ?? [];

// --- Validation ---
if (empty($export_type)) $errors[] = "Vui lòng chọn loại xuất kho.";
if (empty($product_ids)) {
    $errors[] = "Vui lòng thêm ít nhất một sản phẩm để xuất kho.";
} else { // Only proceed if there's at least one product
    foreach ($product_ids as $key => $product_id) {
        $product_id = (int)($product_id ?? 0);
        $productsize_id = (int)($size_ids[$key] ?? 0);
        $batch_id = (int)($batch_ids[$key] ?? 0);
        $quantity = (int)($quantities[$key] ?? 0);

        // Basic validation for each item
        if ($product_id <= 0) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Vui lòng chọn sản phẩm.";
            continue;
        }
        if ($productsize_id <= 0) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Vui lòng chọn size.";
            continue;
        }
        if ($batch_id <= 0) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Vui lòng chọn lô hàng.";
            continue;
        }
        if ($quantity <= 0) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Số lượng xuất phải lớn hơn 0.";
            continue;
        }

        // 1. Validate product_id and productsize_id relationship
        $psCheckStmt = $db->prepare("SELECT COUNT(*) FROM product_sizes WHERE id = ? AND product_id = ?");
        $psCheckStmt->execute([$productsize_id, $product_id]);
        if ($psCheckStmt->fetchColumn() == 0) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Sản phẩm và size không khớp hoặc không tồn tại.";
            continue;
        }

        // 2. Validate batch_id and productsize_id relationship, and get current stock
        $batchStockStmt = $db->prepare("SELECT quantity_remaining, batch_code FROM product_batch WHERE id = ? AND productsize_id = ?");
        $batchStockStmt->execute([$batch_id, $productsize_id]);
        $batch_info = $batchStockStmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch_info) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Lô hàng không hợp lệ hoặc không thuộc size đã chọn.";
            continue;
        }

        $current_batch_stock = (int)$batch_info['quantity_remaining'];
        $batch_code = $batch_info['batch_code'];

        if ($quantity > $current_batch_stock) {
            $errors[] = "Dòng sản phẩm " . ($key + 1) . ": Số lượng xuất ({$quantity}) vượt quá tồn kho ({$current_batch_stock}) cho lô hàng {$batch_code}.";
            continue;
        }
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode("\n", $errors), 'redirect' => '']);
    exit;
}

$db->beginTransaction();
try {
    // 1. Tạo phiếu xuất rỗng trước
    $total_amount = 0;
    $receipt_code = 'PX' . date('YmdHis');
    $stmt = $db->prepare("INSERT INTO export_receipt (receipt_code, export_type, status, employee_id, total_amount, note) VALUES (?, ?, 'Đang xử lý', ?, 0, ?)");
    $stmt->execute([$receipt_code, $export_type, $employee_id, $note]);
    $export_id = $db->lastInsertId();

    // 2. Xử lý từng sản phẩm: Tạo chi tiết phiếu xuất dựa trên lô đã chọn
    foreach ($product_ids as $key => $product_id) {
        $productsize_id = (int)($size_ids[$key] ?? 0);
        $batch_id = (int)($batch_ids[$key] ?? 0);
        $quantity_to_export = (int)($quantities[$key] ?? 0);

        if ($productsize_id > 0 && $batch_id > 0 && $quantity_to_export > 0) {
            // Lấy giá nhập từ lô hàng (import_receipt_detail) để tính tổng tiền
            $priceStmt = $db->prepare("
                SELECT ird.price 
                FROM import_receipt_detail ird
                JOIN product_batch pb ON ird.batch_id = pb.id
                WHERE pb.id = ?
            ");
            $priceStmt->execute([$batch_id]);
            $import_price = (float)($priceStmt->fetchColumn() ?: 0);

            // Tạo chi tiết phiếu xuất
            $detailStmt = $db->prepare("INSERT INTO export_receipt_detail (export_id, batch_id, productsize_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $detailStmt->execute([$export_id, $batch_id, $productsize_id, $quantity_to_export, $import_price]);

            // Tạm giữ hàng: Trừ tồn kho ngay khi tạo phiếu
            $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining - ? WHERE id = ?")->execute([$quantity_to_export, $batch_id]);
            $db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ?")->execute([$quantity_to_export, $productsize_id]);

            $total_amount += $quantity_to_export * $import_price;
        }
    }

    // 3. Cập nhật lại tổng tiền cho phiếu xuất
    $updateTotalStmt = $db->prepare("UPDATE export_receipt SET total_amount = ? WHERE id = ?");
    $updateTotalStmt->execute([$total_amount, $export_id]);

    $db->commit();
    echo json_encode([
        'success' => true, 
        'message' => "✅ Đã tạo phiếu xuất {$receipt_code} ở trạng thái 'Đang xử lý'.",
        'redirect' => 'index.php?page=stock_out'
    ]);
    exit;

} catch (PDOException $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => "❌ Lỗi cơ sở dữ liệu: " . $e->getMessage()]);
    exit;
}