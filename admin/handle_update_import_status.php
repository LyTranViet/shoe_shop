<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff(); // Ensure only admin/staff can access this endpoint

// Xóa mọi output trước đó
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$import_id = (int)($_POST['import_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

// Validation
if ($import_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID phiếu nhập không hợp lệ.']);
    exit;
}

if (!in_array($new_status, ['Đang chờ xác nhận', 'Xác nhận', 'Hủy'])) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ.']);
    exit;
}

try {
    $db = get_db();
    
    // Kiểm tra phiếu nhập tồn tại và lấy trạng thái hiện tại
    $stmt = $db->prepare("SELECT status FROM import_receipt WHERE id = ?");
    $stmt->execute([$import_id]);
    $current_status = $stmt->fetchColumn();
    
    if (!$current_status) {
        echo json_encode(['success' => false, 'message' => 'Phiếu nhập không tồn tại.']);
        exit;
    }

    // Không cho phép chuyển trạng thái từ 'Xác nhận' hoặc 'Hủy' về 'Đang chờ xác nhận'
    if (($current_status === 'Xác nhận' || $current_status === 'Hủy') && $new_status === 'Đang chờ xác nhận') {
        echo json_encode(['success' => false, 'message' => 'Không thể chuyển trạng thái từ "Xác nhận" hoặc "Hủy" về "Đang chờ xác nhận".']);
        exit;
    }

    // Nếu trạng thái hiện tại là 'Hủy', không cho phép thay đổi thêm
    if ($current_status === 'Hủy' && $new_status !== 'Hủy') {
        echo json_encode(['success' => false, 'message' => 'Phiếu nhập đã bị hủy, không thể thay đổi trạng thái.']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Lấy chi tiết phiếu nhập để điều chỉnh tồn kho
    $detailStmt = $db->prepare("
        SELECT batch_id, productsize_id, quantity 
        FROM import_receipt_detail 
        WHERE import_id = ?
    ");
    $detailStmt->execute([$import_id]);
    $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    // Logic điều chỉnh tồn kho dựa trên sự thay đổi trạng thái
    if ($new_status === 'Xác nhận' && $current_status === 'Đang chờ xác nhận') {
        foreach ($details as $detail) {
            // Cập nhật tồn kho cho lô hàng và tổng tồn kho của size
            $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining + ? WHERE id = ?")
               ->execute([$detail['quantity'], $detail['batch_id']]);
            $db->prepare("UPDATE product_sizes SET stock = stock + ? WHERE id = ?")
               ->execute([$detail['quantity'], $detail['productsize_id']]);
        }
    } elseif ($new_status === 'Hủy' && $current_status === 'Xác nhận') {
        foreach ($details as $detail) {
            // Hoàn lại tồn kho đã nhập (trừ khỏi lô và tổng tồn kho)
            $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining - ? WHERE id = ?")
               ->execute([$detail['quantity'], $detail['batch_id']]);
            $db->prepare("UPDATE product_sizes SET stock = stock - ? WHERE id = ?")
               ->execute([$detail['quantity'], $detail['productsize_id']]);
        }
    }
    
    // Cập nhật trạng thái của phiếu nhập
    $updateStmt = $db->prepare("UPDATE import_receipt SET status = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $import_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "✅ Đã cập nhật trạng thái phiếu nhập thành '{$new_status}'!",
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
exit;