<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin_or_staff();

// Xóa mọi output trước đó
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$export_id = (int)($_POST['export_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

// Validation
if ($export_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID phiếu xuất không hợp lệ.']);
    exit;
}

if (!in_array($new_status, ['Đang xử lý', 'Đã xuất kho', 'Đã hủy'])) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ.']);
    exit;
}

try {
    $db = get_db();
    
    // Kiểm tra phiếu xuất tồn tại
    $stmt = $db->prepare("SELECT status FROM export_receipt WHERE id = ?");
    $stmt->execute([$export_id]);
    $current_status = $stmt->fetchColumn();
    
    if (!$current_status) {
        echo json_encode(['success' => false, 'message' => 'Phiếu xuất không tồn tại.']);
        exit;
    }
    
    // Chỉ cho phép cập nhật từ "Đang xử lý"
    if ($current_status !== 'Đang xử lý') {
        echo json_encode(['success' => false, 'message' => 'Chỉ có thể thay đổi trạng thái của phiếu đang xử lý.']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Nếu chuyển sang "Đã xuất kho", không cần làm gì vì kho đã bị trừ khi tạo phiếu.
    // Nếu chuyển sang "Đã hủy", hoàn lại tồn kho đã bị tạm giữ.
    if ($new_status === 'Đã hủy' && $current_status === 'Đang xử lý') {
        $detailStmt = $db->prepare("
            SELECT batch_id, productsize_id, quantity 
            FROM export_receipt_detail 
            WHERE export_id = ?
        ");
        $detailStmt->execute([$export_id]);
        $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($details as $detail) {
            // Hoàn lại số lượng vào lô hàng
            $db->prepare("UPDATE product_batch SET quantity_remaining = quantity_remaining + ? WHERE id = ?")
               ->execute([$detail['quantity'], $detail['batch_id']]);
            
            // Hoàn lại tồn kho size
            $db->prepare("UPDATE product_sizes SET stock = stock + ? WHERE id = ?")
               ->execute([$detail['quantity'], $detail['productsize_id']]);
        }
    }
    
    // Cập nhật trạng thái
    $updateStmt = $db->prepare("UPDATE export_receipt SET status = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $export_id]);
    
    $db->commit();
    
    // Tạo thông báo phù hợp
    $message = '';
    if ($new_status === 'Đã xuất kho') {
        $message = '✅ Đã xác nhận xuất kho thành công!';
    } elseif ($new_status === 'Đã hủy') {
        $message = '✅ Đã hủy phiếu xuất và hoàn lại tồn kho!';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
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