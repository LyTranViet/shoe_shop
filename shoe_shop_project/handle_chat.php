<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = get_db();
$action = $_REQUEST['action'] ?? '';

$currentUserId = current_user_id();
$currentUserRole = $_SESSION['user_role'] ?? 'guest';
$isCurrentUserAdmin = in_array($currentUserRole, ['supperadmin', 'admin', 'staff']);

switch ($action) {
    case 'send':
        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Tin nhắn không được để trống.']);
            exit;
        }

        $sender = 'user';
        $userId = $currentUserId;
        $sessionId = session_id();

        // Nếu admin gửi tin nhắn, họ phải cung cấp target
        if ($isCurrentUserAdmin) {
            $sender = 'admin';
            $userId = $_POST['user_id'] ?? null;
            $sessionId = $_POST['session_id'] ?? null;
            // Chuyển 'null' (string) thành null thật
            if ($userId === 'null' || $userId === '') $userId = null;
        }

        try {
            $stmt = $db->prepare(
                "INSERT INTO chat_messages (user_id, session_id, message, sender) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $sessionId, $message, $sender]);

            // Trả lời tự động nếu người gửi là 'user'
            $reply = null;
            if ($sender === 'user') {
                $reply = "Cảm ơn bạn đã liên hệ với Púp Bờ Si. Chúng tôi sẽ trả lời bạn trong thời gian sớm nhất.";
            }
            echo json_encode(['success' => true, 'reply' => $reply]);

        } catch (PDOException $e) {
            error_log("Chat send error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi gửi tin nhắn.']);
        }
        break;

    case 'fetch':
        $userId = $currentUserId;
        $sessionId = session_id();

        // Admin có thể fetch cho người dùng khác
        if ($isCurrentUserAdmin) {
            $userId = $_GET['user_id'] ?? null;
            $sessionId = $_GET['session_id'] ?? null;
            if ($userId === 'null' || $userId === '') $userId = null;
        }

        try {
            $query = "SELECT id, message, sender, created_at FROM chat_messages WHERE ";
            $params = [];

            if ($userId) {
                $query .= "user_id = ?";
                $params[] = $userId;
            } else {
                $query .= "session_id = ?";
                $params[] = $sessionId;
            }
            
            $query .= " ORDER BY created_at ASC";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Thêm tin nhắn chào mừng nếu chưa có tin nhắn nào
            if (empty($messages)) {
                 $messages[] = [
                    'message' => 'Cảm ơn bạn đã liên hệ với Púp Bờ Si. Chúng tôi sẽ trả lời bạn trong thời gian sớm nhất.',
                    'sender' => 'admin',
                    'created_at' => date('Y-m-d H:i:s'),
                    'id' => null // Đánh dấu đây là tin nhắn tạm thời
                ];
            }

            echo json_encode(['success' => true, 'messages' => $messages]);

        } catch (PDOException $e) {
            error_log("Chat fetch error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tải tin nhắn.']);
        }
        break;

    case 'fetch_conversations':
        if (!$isCurrentUserAdmin) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.']);
            exit;
        }
        try {
            $stmt = $db->query("
                SELECT 
                    cm.user_id, 
                    cm.session_id, 
                    u.name AS user_name, 
                    cm.message AS last_message, 
                    cm.created_at AS last_message_time,
                    (SELECT COUNT(*) FROM chat_messages sub WHERE (sub.user_id = cm.user_id OR (sub.user_id IS NULL AND sub.session_id = cm.session_id)) AND sub.is_read_by_admin = 0 AND sub.sender = 'user') as unread_count
                FROM chat_messages cm
                LEFT JOIN users u ON cm.user_id = u.id
                WHERE cm.id IN (
                    SELECT MAX(id) 
                    FROM chat_messages 
                    GROUP BY COALESCE(user_id, session_id)
                )
                ORDER BY cm.created_at DESC
            ");
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'conversations' => $conversations]);
        } catch (PDOException $e) {
            error_log("Chat fetch conversations error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tải các cuộc trò chuyện.']);
        }
        break;

    case 'mark_as_read':
        if (!$isCurrentUserAdmin) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.']);
            exit;
        }
        $userId = $_POST['user_id'] ?? null;
        $sessionId = $_POST['session_id'] ?? null;
        if ($userId === 'null' || $userId === '') $userId = null;

        $query = "UPDATE chat_messages SET is_read_by_admin = 1 WHERE sender = 'user' AND " . ($userId ? "user_id = ?" : "session_id = ?");
        $stmt = $db->prepare($query);
        $stmt->execute([$userId ?: $sessionId]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
        break;
}
?>