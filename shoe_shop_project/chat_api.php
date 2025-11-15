<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$db = get_db();
$userId = current_user_id();
$action = $_POST['action'] ?? '';
$isAdmin = is_admin() || is_superadmin();

try {
    switch ($action) {
        case 'start_conversation':
            // Tìm hoặc tạo cuộc trò chuyện mới cho user
            $stmt = $db->prepare("SELECT id FROM chat_conversations WHERE user_id = ? AND status = 'open' LIMIT 1");
            $stmt->execute([$userId]);
            $conversationId = $stmt->fetchColumn();

            if (!$conversationId) {
                $stmt = $db->prepare("INSERT INTO chat_conversations (user_id) VALUES (?)");
                $stmt->execute([$userId]);
                $conversationId = $db->lastInsertId();
            }

            // Lấy các tin nhắn cũ
            $stmt = $db->prepare("
                SELECT m.*, u.name as user_name 
                FROM chat_messages m 
                LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'user'
                WHERE m.conversation_id = ? ORDER BY m.created_at ASC
            ");
            $stmt->execute([$conversationId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'conversation_id' => $conversationId, 'messages' => $messages]);
            break;

        case 'send_message':
            $conversationId = (int)($_POST['conversation_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');

            if (empty($message) || $conversationId <= 0) {
                throw new Exception("Dữ liệu không hợp lệ.");
            }

            // Kiểm tra xem user có quyền truy cập cuộc trò chuyện này không
            if (!$isAdmin) {
                $stmt = $db->prepare("SELECT user_id FROM chat_conversations WHERE id = ?");
                $stmt->execute([$conversationId]);
                if ($stmt->fetchColumn() != $userId) {
                    throw new Exception("Không có quyền truy cập.");
                }
            }

            $senderType = $isAdmin ? 'admin' : 'user';
            $stmt = $db->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conversationId, $userId, $senderType, $message]);

            if ($isAdmin && !$db->query("SELECT admin_id FROM chat_conversations WHERE id = $conversationId")->fetchColumn()) {
                $db->prepare("UPDATE chat_conversations SET admin_id = ? WHERE id = ?")->execute([$userId, $conversationId]);
                throw new Exception("Không có quyền truy cập.");
            }

            // Cập nhật thời gian cho cuộc trò chuyện
            $db->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);

            echo json_encode(['success' => true]);
            break;

        case 'get_messages':
            // Đây là phần long-polling
            set_time_limit(40); // Cho phép script chạy tối đa 40 giây
            $conversationId = (int)($_POST['conversation_id'] ?? 0);
            $lastId = (int)($_POST['last_id'] ?? 0);

            // Kiểm tra quyền
            if (!$isAdmin) {
                $stmt = $db->prepare("SELECT user_id FROM chat_conversations WHERE id = ?");
                $stmt->execute([$conversationId]);
                if ($stmt->fetchColumn() != $userId) {
                    throw new Exception("Không có quyền truy cập.");
                }
            }

            // Vòng lặp để chờ tin nhắn mới
            for ($i = 0; $i < 250; $i++) { // Tăng số lần lặp để giữ nguyên timeout
                $stmt = $db->prepare("
                    SELECT m.*, u.name as user_name FROM chat_messages m 
                    LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'user'
                    WHERE m.conversation_id = ? AND m.id > ? ORDER BY m.id ASC
                ");
                $stmt->execute([$conversationId, $lastId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($messages) > 0) {
                    echo json_encode(['success' => true, 'messages' => $messages]);
                    exit;
                }

                // Nếu không có tin nhắn, đợi 0.1 giây (100,000 microseconds) rồi thử lại
                usleep(100000);
            }

            // Nếu sau 25 giây (250 * 0.1s) vẫn không có gì, trả về mảng rỗng
            echo json_encode(['success' => true, 'messages' => []]);
            break;
        
        // --- HÀNH ĐỘNG CHO ADMIN ---

        case 'admin_get_conversations':
            if (!$isAdmin) throw new Exception("Không có quyền truy cập.");
            
            $stmt = $db->query("
                SELECT c.id, c.updated_at, u.name as user_name, 
                       (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) as last_message
                FROM chat_conversations c
                JOIN users u ON c.user_id = u.id
                WHERE c.status = 'open'
                ORDER BY c.updated_at DESC
            ");
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'conversations' => $conversations]);
            break;

        case 'admin_close_conversation':
            if (!$isAdmin) throw new Exception("Không có quyền truy cập.");

            $conversationId = (int)($_POST['conversation_id'] ?? 0);
            if ($conversationId <= 0) throw new Exception("ID cuộc trò chuyện không hợp lệ.");

            $stmt = $db->prepare("UPDATE chat_conversations SET status = 'closed' WHERE id = ?");
            $stmt->execute([$conversationId]);
            echo json_encode(['success' => true, 'message' => 'Đã đóng cuộc trò chuyện.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
            break;
    }
} catch (Exception $e) {
    // Ghi log lỗi nếu cần
    // error_log('Chat API Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi: ' . $e->getMessage()]);
}

?>