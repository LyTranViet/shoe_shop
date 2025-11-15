<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';

// Bắt buộc đăng nhập
if (!is_logged_in()) {
    $_SESSION['return_to'] = BASE_URL . 'live_chat.php';
    flash_set('info', 'Vui lòng đăng nhập để bắt đầu trò chuyện.');
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userId = current_user_id();
$userName = $_SESSION['user_name'] ?? 'Bạn';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hỗ trợ trực tuyến - Púp Bờ Si</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #38bdf8; --primary-dark: #0c8ad8; --primary-light: #bae6fd;
            --accent: #2563eb; --accent-hover: #1d4ed8; --bg-white: #ffffff;
            --bg-light: #f8f9fa; --bg-gray: #f1f5f9; --text-dark: #1a202c;
            --text-body: #4a5568; --text-muted: #6c757d; --border: #e2e8f0;
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden; /* Ngăn cuộn trang */
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-gray);
        }
    </style>
</head>
<body>

<div class="chat-wrapper">
    <div class="chat-sidebar">
        <div class="sidebar-header">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <div class="logo">👟</div>
                <span>Púp Bờ Si</span>
            </a>
        </div>
        <div class="sidebar-content">
            <h4>Chào, <?php echo htmlspecialchars($userName); ?>!</h4>
            <p>Chúng tôi luôn sẵn sàng hỗ trợ bạn. Hãy bắt đầu cuộc trò chuyện.</p>
            <a href="<?php echo BASE_URL; ?>index.php" class="back-to-shop-btn">‹ Quay lại cửa hàng</a>
        </div>
    </div>
    <div class="chat-main">
        <div class="chat-header">
            <h3>Hỗ trợ trực tuyến</h3>
            <p>Chúng tôi ở đây để giúp bạn!</p>
        </div>
        <div class="chat-messages" id="chat-messages">
            <!-- Tin nhắn sẽ được tải vào đây -->
            <div class="message-system">Vui lòng chờ, đang kết nối tới bộ phận hỗ trợ...</div>
        </div>
        <div class="chat-input-area">
            <form id="chat-form" method="POST">
                <input type="text" id="message-input" placeholder="Nhập tin nhắn của bạn..." autocomplete="off" required>
                <button type="submit" aria-label="Gửi tin nhắn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" /></svg>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.chat-wrapper {
    display: flex;
    height: 100vh;
    width: 100vw;
    background-color: var(--bg-white);
}
.chat-main {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    height: 100vh;
}
.chat-sidebar {
    width: 320px;
    background: var(--bg-white);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 1.75rem;
    flex-shrink: 0;
}
.sidebar-header .brand {
    text-decoration: none; color: var(--text-dark); font-weight: 700;
    font-size: 1.5rem; display: flex; align-items: center; gap: 0.75rem;
}
.sidebar-header .brand span { color: var(--accent); }
.sidebar-header .logo { font-size: 2rem; }
.sidebar-content {
    margin-top: 2rem;
}
.sidebar-content h4 { font-weight: 600; }
.sidebar-content p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; }
.back-to-shop-btn {
    display: inline-block; margin-top: 1.5rem; padding: 10px 15px;
    background: var(--bg-light); color: var(--text-body); text-decoration: none;
    border-radius: 8px; font-weight: 500; transition: all 0.2s;
}
.back-to-shop-btn:hover { background: var(--border); color: var(--text-dark); }

.chat-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-white);
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    z-index: 10;
    flex-shrink: 0;
}
.chat-header h3 { margin: 0; font-size: 1.25rem; }
.chat-header p { margin: 0; color: #666; }

.chat-messages {
    flex-grow: 1;
    padding: 1.5rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    background-color: var(--bg-gray);
}
/* Custom scrollbar */
.chat-messages::-webkit-scrollbar { width: 8px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb {
    background-color: var(--border-dark);
    border-radius: 10px;
    border: 2px solid var(--bg-gray);
}
.message {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    max-width: 80%;
}
.message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--text-muted);
    flex-shrink: 0;
}
.message-content {
    padding: 10px 15px;
    border-radius: 18px;
    line-height: 1.4;
    position: relative;
}
.message.sent {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.message.sent .message-content {
    background: var(--primary);
    color: var(--bg-white);
    border-bottom-right-radius: 4px;
}
.message.received {
    align-self: flex-start;
}
.message.received .message-content {
    background: var(--bg-white);
    color: var(--text-dark);
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.message-time {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 5px;
}
.message-system {
    text-align: center;
    color: #999;
    font-size: 0.85rem;
    margin: 10px 0;
}
.chat-input-area {
    padding: 1rem;
    border-top: 1px solid var(--border);
    background: var(--bg-white);
}
#chat-form {
    display: flex;
    gap: 10px;
}
#message-input {
    flex-grow: 1;
    padding: 12px;
    border: 1.5px solid var(--border);
    background-color: var(--bg-light);
    border-radius: 25px;
    font-size: 1rem;
    transition: border-color 0.2s, box-shadow 0.2s;
}
#message-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
}
#chat-form button {
    width: 48px;
    height: 48px;
    border: none;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
}

/* Responsive */
@media (max-width: 768px) {
    .chat-sidebar {
        display: none; /* Ẩn sidebar trên mobile */
    }
    .chat-header h3 { font-size: 1.1rem; }
    .chat-header p { font-size: 0.9rem; }
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    const chatBox = $('#chat-messages');
    const messageInput = $('#message-input');
    let conversationId = null;
    let lastMessageId = 0;

    function scrollToBottom(smooth = false) {
        if (smooth) {
            chatBox.animate({ scrollTop: chatBox[0].scrollHeight }, 500);
        } else {
            chatBox.scrollTop(chatBox[0].scrollHeight);
        }
    }

    function appendMessage(msg) {
        const senderClass = msg.sender_type === 'user' ? 'sent' : 'received';
        const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

        const avatarHtml = senderClass === 'received' ? `
            <div class="message-avatar">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M4.913 2.658c2.27-.525 4.693.225 6.29 1.823l.068.067.068-.067c1.597-1.598 4.02-2.348 6.29-1.823 3.451.796 5.225 4.477 4.242 7.928L12 21.75l-8.583-9.164C2.46 9.135 1.46 5.454 4.913 2.658z" /></svg>
            </div>` : '';

        const messageHtml = `
            <div class="message ${senderClass}">
                ${avatarHtml}
                <div class="message-body">
                    <div class="message-content">${msg.message}</div>
                    <div class="message-time">${time}</div>
                </div>
            </div>`;
        chatBox.append(messageHtml);
        lastMessageId = msg.id;
    }

    function pollMessages() {
        if (!conversationId) return;
        $.ajax({
            url: 'chat_api.php',
            method: 'POST',
            data: { action: 'get_messages', conversation_id: conversationId, last_id: lastMessageId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.messages.length > 0) {
                    response.messages.forEach(appendMessage);
                    scrollToBottom(true);
                }
            },
            complete: pollMessages, // Gọi lại chính nó sau khi hoàn thành
            timeout: 30000 // Timeout 30 giây
        });
    }

    $('#chat-form').on('submit', function(e) {
        e.preventDefault();
        const message = messageInput.val().trim();
        if (!message || !conversationId) return;

        messageInput.val('');
        $.post('chat_api.php', { action: 'send_message', conversation_id: conversationId, message: message });
    });

    // Bắt đầu cuộc trò chuyện
    $.post('chat_api.php', { action: 'start_conversation' }, function(response) {
        if (response.success) {
            conversationId = response.conversation_id;
            chatBox.html(''); // Xóa tin nhắn "đang kết nối"
            if(response.messages.length > 0) {
                response.messages.forEach(appendMessage);
                scrollToBottom(false);
            } else {
                chatBox.append('<div class="message-system">Hãy gửi tin nhắn đầu tiên để bắt đầu.</div>');
            }
            pollMessages(); // Bắt đầu lắng nghe tin nhắn mới
        } else {
            chatBox.html(`<div class="message-system" style="color:red;">${response.message}</div>`);
        }
    }, 'json');
});
</script>
</body>
</html>