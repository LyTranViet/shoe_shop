<?php
require_once __DIR__ . '/../includes/init.php';

if (!is_admin() && !is_superadmin()) {
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div class="admin-chat-container">
    <!-- Cột danh sách cuộc trò chuyện -->
    <div class="conversations-list" id="conversations-list">
        <div class="list-header">
            <h4>Cuộc trò chuyện</h4>
            <div id="loading-spinner" class="spinner"></div>
        </div>
        <div class="list-body" id="conv-list-body">
            <!-- Danh sách sẽ được tải vào đây -->
            <p class="no-conversations">Chưa có cuộc trò chuyện nào.</p>
        </div>
    </div>

    <!-- Cửa sổ chat chính -->
    <div class="chat-window">
        <div id="chat-welcome-screen">
            <div class="welcome-icon">💬</div>
            <h3>Chào mừng đến với Live Chat</h3>
            <p>Chọn một cuộc trò chuyện từ danh sách bên trái để bắt đầu.</p>
        </div>

        <div id="chat-main-screen" style="display: none;">
            <div class="chat-header">
                <h5 id="chat-with-user-name"></h5>
                <button id="close-conversation-btn" class="btn btn-sm btn-danger">Đóng cuộc trò chuyện</button>
            </div>
            <div class="chat-messages" id="chat-messages">
                <!-- Tin nhắn sẽ được tải vào đây -->
            </div>
            <div class="chat-input-area">
                <form id="chat-form" method="POST">
                    <input type="text" id="message-input" placeholder="Nhập tin nhắn trả lời..." autocomplete="off" required>
                    <button type="submit" aria-label="Gửi tin nhắn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Kế thừa từ giao diện người dùng, nhưng có điều chỉnh */
.admin-chat-container { display: flex; height: calc(100vh - 56px); /* 56px là chiều cao header admin */ }
.conversations-list {
    width: 350px; border-right: 1px solid #dee2e6; display: flex;
    flex-direction: column; background: #fff; flex-shrink: 0;
}
.list-header {
    padding: 1rem; border-bottom: 1px solid #dee2e6; display: flex;
    justify-content: space-between; align-items: center;
}
.list-header h4 { margin: 0; font-size: 1.1rem; font-weight: 600; }
.list-body { overflow-y: auto; flex-grow: 1; }
.conversation-item {
    padding: 1rem; border-bottom: 1px solid #f1f1f1; cursor: pointer;
    transition: background-color 0.2s;
}
.conversation-item:hover { background-color: #f8f9fa; }
.conversation-item.active { background-color: var(--primary-light); border-right: 3px solid var(--primary); }
.conv-user-name { font-weight: 600; color: #333; }
.conv-last-message {
    font-size: 0.9rem; color: #666; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
.conv-time { font-size: 0.8rem; color: #999; }
.no-conversations { padding: 1rem; color: #777; }

.chat-window { flex-grow: 1; display: flex; flex-direction: column; }
#chat-welcome-screen {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; text-align: center; background: #f8f9fa; color: #6c757d;
}
.welcome-icon { font-size: 4rem; margin-bottom: 1rem; }

#chat-main-screen { display: flex; flex-direction: column; height: 100%; }
.chat-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.5rem; border-bottom: 1px solid #dee2e6; background: #fff;
}
.chat-header h5 { margin: 0; }

/* CSS cho tin nhắn (sao chép từ live_chat.php và đảo ngược) */
.chat-messages { flex-grow: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 0.25rem; background-color: #f1f5f9; }
.message { display: flex; align-items: flex-end; gap: 10px; max-width: 80%; }
.message-avatar { width: 36px; height: 36px; border-radius: 50%; background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #6c757d; flex-shrink: 0; }
.message-content { padding: 10px 15px; border-radius: 18px; line-height: 1.4; }
.message-time { font-size: 0.75rem; color: #6c757d; margin-top: 5px; }

/* Tin nhắn của Admin (sent) */
.message.sent { align-self: flex-end; flex-direction: row-reverse; }
.message.sent .message-content { background: var(--primary); color: white; border-bottom-right-radius: 4px; }

/* Tin nhắn của User (received) */
.message.received { align-self: flex-start; }
.message.received .message-content { background: #fff; color: #333; border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }

.chat-input-area { padding: 1rem; border-top: 1px solid #dee2e6; background: #fff; }
#chat-form { display: flex; gap: 10px; }
#message-input { flex-grow: 1; padding: 12px; border: 1.5px solid #ced4da; background-color: #f8f9fa; border-radius: 25px; font-size: 1rem; }
#message-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
#chat-form button { width: 48px; height: 48px; border: none; background: var(--primary); color: white; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }

.spinner {
    border: 3px solid #f3f3f3; border-top: 3px solid var(--primary);
    border-radius: 50%; width: 20px; height: 20px;
    animation: spin 1s linear infinite; display: none; /* Ẩn ban đầu */
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>

<!-- Thẻ audio cho âm thanh thông báo -->
<audio id="notification-sound" preload="auto">
    <source src="<?php echo BASE_URL; ?>assets/sounds/notification.mp3" type="audio/mpeg">
</audio>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    const convListBody = $('#conv-list-body');
    const chatBox = $('#chat-messages');
    const messageInput = $('#message-input');
    const spinner = $('#loading-spinner');
    const notificationSound = document.getElementById('notification-sound');

    let currentConversationId = null;
    let lastMessageId = 0;
    let longPollXHR = null; // Để hủy request long-polling cũ

    // --- Hàm cuộn xuống cuối ---
    function scrollToBottom(smooth = false) {
        if (smooth) {
            chatBox.animate({ scrollTop: chatBox[0].scrollHeight }, 500);
        } else {
            chatBox.scrollTop(chatBox[0].scrollHeight);
        }
    }

    // --- Hàm phát âm thanh thông báo ---
    function playNotificationSound() {
        if (notificationSound) {
            notificationSound.currentTime = 0; // Tua về đầu để phát lại nếu cần
            const playPromise = notificationSound.play();

            if (playPromise !== undefined) {
                playPromise.catch(error => {
                    // Trình duyệt có thể chặn tự động phát âm thanh
                    // Người dùng cần tương tác với trang (click) ít nhất một lần
                    console.warn("Âm thanh thông báo bị chặn bởi trình duyệt. Cần có tương tác của người dùng để bật âm thanh.");
                });
            }
        }
    }
    // --- Hàm hiển thị tin nhắn ---
    function appendMessage(msg) {
        // Đảo ngược logic: 'admin' là 'sent', 'user' là 'received'
        const senderClass = msg.sender_type === 'admin' ? 'sent' : 'received';
        const time = new Date(msg.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

        const avatarHtml = senderClass === 'received' ? `<div class="message-avatar">${msg.user_name.charAt(0).toUpperCase()}</div>` : '';

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

    // --- Long-polling để lấy tin nhắn mới ---
    function pollMessages() {
        if (!currentConversationId) return;
        if (longPollXHR) longPollXHR.abort(); // Hủy request cũ

        longPollXHR = $.ajax({
            url: '<?php echo BASE_URL; ?>chat_api.php',
            method: 'POST',
            data: { action: 'get_messages', conversation_id: currentConversationId, last_id: lastMessageId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.messages.length > 0) {
                    // Chỉ phát âm thanh nếu có tin nhắn từ người dùng và tab không hoạt động
                    const hasUserMessage = response.messages.some(msg => msg.sender_type === 'user');
                    if (hasUserMessage && document.hidden) {
                        playNotificationSound();
                    }
                    response.messages.forEach(appendMessage);
                    scrollToBottom(true);
                }
            },
            complete: pollMessages,
            timeout: 30000
        });
    }

    // --- Lấy danh sách cuộc trò chuyện ---
    function fetchConversations() {
        spinner.show();
        $.post('<?php echo BASE_URL; ?>chat_api.php', { action: 'admin_get_conversations' }, function(response) {
            spinner.hide();
            if (response.success && response.conversations.length > 0) {
                convListBody.html('');
                response.conversations.forEach(convo => {
                    const time = new Date(convo.updated_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                    const itemHtml = `
                        <div class="conversation-item" data-id="${convo.id}" data-username="${convo.user_name}">
                            <div class="d-flex justify-content-between">
                                <div class="conv-user-name">${convo.user_name}</div>
                                <div class="conv-time">${time}</div>
                            </div>
                            <div class="conv-last-message">${convo.last_message || '...'}</div>
                        </div>`;
                    convListBody.append(itemHtml);
                });
                // Đánh dấu cuộc trò chuyện đang active
                if (currentConversationId) {
                    $(`.conversation-item[data-id=${currentConversationId}]`).addClass('active');
                }
            } else {
                convListBody.html('<p class="no-conversations">Chưa có cuộc trò chuyện nào.</p>');
            }
        }, 'json');
    }

    // --- Xử lý khi click vào một cuộc trò chuyện ---
    convListBody.on('click', '.conversation-item', function() {
        const newId = $(this).data('id');
        if (newId === currentConversationId) return;

        currentConversationId = newId;
        lastMessageId = 0;

        // Cập nhật giao diện
        $('.conversation-item').removeClass('active');
        $(this).addClass('active');
        $('#chat-welcome-screen').hide();
        $('#chat-main-screen').css('display', 'flex');
        $('#chat-with-user-name').text('Trò chuyện với ' + $(this).data('username'));
        chatBox.html('<div class="message-system">Đang tải tin nhắn...</div>');

        // Lấy lịch sử tin nhắn và bắt đầu polling
        $.post('<?php echo BASE_URL; ?>chat_api.php', { action: 'get_messages', conversation_id: currentConversationId, last_id: 0 }, function(response) {
            chatBox.html('');
            if (response.success && response.messages.length > 0) {
                response.messages.forEach(appendMessage);
                scrollToBottom(false);
            }
            pollMessages(); // Bắt đầu polling cho cuộc trò chuyện mới
        }, 'json');
    });

    // --- Gửi tin nhắn ---
    $('#chat-form').on('submit', function(e) {
        e.preventDefault();
        const message = messageInput.val().trim();
        if (!message || !currentConversationId) return;

        messageInput.val('');
        $.post('<?php echo BASE_URL; ?>chat_api.php', {
            action: 'send_message',
            conversation_id: currentConversationId,
            message: message
        });
    });

    // --- Đóng cuộc trò chuyện ---
    $('#close-conversation-btn').on('click', function() {
        if (!currentConversationId || !confirm('Bạn có chắc muốn đóng cuộc trò chuyện này?')) return;

        $.post('<?php echo BASE_URL; ?>chat_api.php', { action: 'admin_close_conversation', conversation_id: currentConversationId }, function(response) {
            if (response.success) {
                // Chuyển về màn hình chào mừng và tải lại danh sách
                currentConversationId = null;
                if (longPollXHR) longPollXHR.abort();
                $('#chat-main-screen').hide();
                $('#chat-welcome-screen').show();
                fetchConversations();
            } else {
                alert('Lỗi: ' + response.message);
            }
        }, 'json');
    });

    // --- Khởi chạy ---
    fetchConversations();
    setInterval(fetchConversations, 1000); // Cập nhật danh sách mỗi 1 giây
});
</script>
