<?php
// This file is included from admin/index.php
defined('IS_ADMIN_PAGE') or die('Direct access not allowed.');

$db = get_db();

// Lấy danh sách các cuộc trò chuyện
$conversations_stmt = $db->query("
    SELECT 
        cm.user_id, 
        cm.session_id, 
        u.name AS user_name, 
        cm.message AS last_message, 
        cm.created_at AS last_message_time,
        (SELECT COUNT(*) FROM chat_messages sub WHERE (sub.user_id = cm.user_id OR sub.session_id = cm.session_id) AND sub.is_read_by_admin = 0 AND sub.sender = 'user') as unread_count
    FROM chat_messages cm
    LEFT JOIN users u ON cm.user_id = u.id
    WHERE cm.id IN (
        SELECT MAX(id) 
        FROM chat_messages 
        GROUP BY COALESCE(user_id, session_id)
    )
    ORDER BY cm.created_at DESC
");
$conversations = $conversations_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    .chat-admin-layout {
        display: flex;
        height: calc(100vh - 150px); /* Adjust based on your admin header/footer height */
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
    }

    .conversation-list {
        width: 320px;
        border-right: 1px solid #dee2e6;
        display: flex;
        flex-direction: column;
        background: #fff;
    }

    .conversation-list-header {
        padding: 1rem;
        font-weight: 600;
        font-size: 1.2rem;
        border-bottom: 1px solid #dee2e6;
        color: #343a40;
        flex-shrink: 0;
    }

    .conversations {
        flex-grow: 1;
        overflow-y: auto;
    }

    .conversation-item {
        padding: 1rem;
        border-bottom: 1px solid #f1f3f5;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .conversation-item:hover {
        background-color: #f8f9fa;
    }
    .conversation-item.active {
        background-color: #e9ecef;
    }

    .conversation-item.unread {
        font-weight: bold;
        background-color: #e3f2fd;
    }

    .conv-name {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.25rem;
    }

    .conv-name .name {
        color: #0056b3;
    }

    .unread-badge {
        background-color: #dc3545;
        color: white;
        font-size: 0.75rem;
        padding: 2px 6px;
        border-radius: 10px;
    }

    .conv-last-message {
        font-size: 0.9rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: normal;
    }

    .chat-panel {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .chat-panel-header {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        background: #fff;
        flex-shrink: 0;
    }

    .chat-messages {
        flex-grow: 1;
        padding: 1.5rem;
        overflow-y: auto;
        background-color: #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .message {
        max-width: 75%;
        padding: 0.75rem 1rem;
        border-radius: 18px;
        line-height: 1.5;
        word-wrap: break-word;
    }

    .message.user {
        background-color: #fff;
        color: #334155;
        border-bottom-left-radius: 4px;
        align-self: flex-start; /* Tin nhắn của khách hàng sẽ ở bên trái */
    }

    .message.admin {
        background-color: #0ea5ff;
        color: white;
        border-bottom-right-radius: 4px;
        align-self: flex-end; /* Tin nhắn của admin sẽ ở bên phải */
    }

    .chat-input-form {
        display: flex;
        padding: 1rem;
        border-top: 1px solid #e2e8f0;
        background-color: #fff;
        flex-shrink: 0;
    }

    #chat-input {
        flex-grow: 1;
        border: 1px solid #cbd5e1;
        border-radius: 20px;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        outline: none;
    }

    #send-button {
        background-color: #0ea5ff;
        color: white;
        border: none;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        margin-left: 0.75rem;
        cursor: pointer;
        font-size: 1.2rem;
    }
    
    #chat-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #6c757d;
        font-size: 1.2rem;
    }
</style>

<div class="admin-header">
    <h2>Hỗ trợ Chat</h2>
</div>

<div class="chat-admin-layout">
    <div class="conversation-list">
        <div class="conversation-list-header">Các cuộc trò chuyện</div>
        <div class="conversations" id="conversation-list">
            <div class="p-3 text-muted">Đang tải...</div>
        </div>
    </div>
    <div class="chat-panel">
        <div class="chat-panel-header" id="chat-panel-header">
            Chọn một cuộc trò chuyện để bắt đầu
        </div>
        <div class="chat-messages" id="chat-messages">
             <div id="chat-placeholder">Vui lòng chọn một cuộc trò chuyện từ danh sách bên trái.</div>
        </div>
        <form class="chat-input-form" id="chat-form" style="display: none;">
            <input type="hidden" id="chat-user-id" value="">
            <input type="hidden" id="chat-session-id" value="">
            <input type="text" id="chat-input" placeholder="Nhập tin nhắn..." autocomplete="off" required>
            <button type="submit" id="send-button" title="Gửi">➤</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const convList = document.getElementById('conversation-list');
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatPanelHeader = document.getElementById('chat-panel-header');
    const chatPlaceholder = document.getElementById('chat-placeholder');
    const chatUserIdInput = document.getElementById('chat-user-id');
    const chatSessionIdInput = document.getElementById('chat-session-id');
    
    let messageFetchInterval = null; // Biến để lưu interval tải tin nhắn
    let lastMessageId = 0; // ID của tin nhắn cuối cùng đã hiển thị
    // Tạo một đối tượng Audio để phát âm thanh thông báo
    const notificationSound = new Audio('https://cdn.freesound.org/previews/573/573381_7037-lq.mp3');
    const sendSound = new Audio('https://cdn.freesound.org/previews/510/510112_11157383-lq.mp3');
    const originalTitle = document.title;
    let unreadMessageCount = 0;

    notificationSound.preload = 'auto';


    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        // Thêm ID để tránh trùng lặp, mặc dù logic mới đã giảm thiểu điều này
        const messageId = `msg-${Date.now()}-${Math.random()}`;
        messageDiv.id = messageId;

        messageDiv.classList.add('message', sender);
        messageDiv.textContent = text;

        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    /**
     * Phát âm thanh thông báo nếu tab không hoạt động.
     * Trình duyệt có thể chặn tự động phát âm thanh nếu người dùng chưa tương tác với trang.
     */
    function playNotificationSound() {
        // Chỉ phát âm thanh nếu tab chat không được focus
        if (document.hidden) {
            notificationSound.play().catch(error => {
                console.warn("Không thể phát âm thanh thông báo:", error);
            });
        }
    }

    /**
     * Cập nhật tiêu đề tab để thông báo có tin nhắn mới.
     */
    function showTitleNotification() {
        if (document.hidden) {
            unreadMessageCount++;
            document.title = `(${unreadMessageCount}) Tin nhắn mới | ${originalTitle}`;
        }
    }

    /**
     * Khôi phục tiêu đề tab gốc.
     */
    function clearTitleNotification() {
        unreadMessageCount = 0;
        document.title = originalTitle;
    }

    function loadConversations() {
        fetch('../handle_chat.php?action=fetch_conversations')
            .then(res => res.json())
            .then(data => {
                convList.innerHTML = '';
                if (data.success && data.conversations.length > 0) {
                    data.conversations.forEach(conv => {
                        const item = document.createElement('div');
                        item.className = 'conversation-item';
                        if (conv.unread_count > 0) {
                            item.classList.add('unread');
                        }
                        item.dataset.userId = conv.user_id;
                        item.dataset.sessionId = conv.session_id;

                        const displayName = conv.user_name || `Khách #${conv.session_id.substring(0, 6)}`;
                        
                        item.innerHTML = `
                            <div class="conv-name">
                                <span class="name">${displayName}</span>
                                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                            </div>
                            <div class="conv-last-message">${conv.last_message}</div>
                        `;
                        
                        item.addEventListener('click', () => loadChat(conv.user_id, conv.session_id, displayName, item));
                        convList.appendChild(item);
                    });
                } else {
                    convList.innerHTML = '<div class="p-3 text-muted">Không có cuộc trò chuyện nào.</div>';
                }
            });
    }

    function loadChat(userId, sessionId, displayName, element) {
        // Dừng việc tải tin nhắn của cuộc trò chuyện cũ (nếu có)
        if (messageFetchInterval) {
            clearInterval(messageFetchInterval);
        }
        lastMessageId = 0; // Reset ID khi chuyển cuộc trò chuyện

        // Highlight active conversation
        document.querySelectorAll('.conversation-item.active').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        chatMessages.innerHTML = '';
        chatPlaceholder.style.display = 'none';
        chatForm.style.display = 'flex';
        chatPanelHeader.textContent = `Trò chuyện với ${displayName}`;
        chatUserIdInput.value = userId;
        chatSessionIdInput.value = sessionId;

        // Hàm để tải tin nhắn
        function fetchNewMessages() {
            // Gửi ID của tin nhắn cuối cùng để chỉ lấy tin nhắn mới
            fetch(`../handle_chat.php?action=fetch&user_id=${userId || ''}&session_id=${sessionId}&since=${lastMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        let hasNewUserMessage = false; // Cờ để kiểm tra có tin nhắn mới từ khách không

                        data.messages.forEach(msg => {
                            // Logic mới: chỉ thêm tin nhắn, không vẽ lại
                            addMessage(msg.message, msg.sender);
                            if (msg.id) {
                                lastMessageId = Math.max(lastMessageId, msg.id);
                            }
                            // Nếu có tin nhắn từ khách, đặt cờ thành true
                            if (msg.sender === 'user') {
                                hasNewUserMessage = true;
                            }
                        });
                        
                        // Đánh dấu đã đọc và xóa badge
                        if (element.classList.contains('unread')) {
                            markAsRead(userId, sessionId);
                            element.classList.remove('unread');
                            const badge = element.querySelector('.unread-badge');
                            if(badge) badge.remove();
                        }

                        // Phát âm thanh nếu có tin nhắn mới từ khách
                        if (hasNewUserMessage) {
                            playNotificationSound();
                            showTitleNotification();
                        }
                    }
                });
        }
        
        fetchNewMessages(); // Tải lần đầu ngay lập tức
        // Bắt đầu tự động tải tin nhắn mới sau mỗi 5 giây
        messageFetchInterval = setInterval(fetchNewMessages, 2000); // Cập nhật tin nhắn mỗi 2 giây
    }
    
    function markAsRead(userId, sessionId) {
        const formData = new FormData();
        formData.append('action', 'mark_as_read');
        formData.append('user_id', userId);
        formData.append('session_id', sessionId);
        fetch('../handle_chat.php', { method: 'POST', body: formData });
    }

    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const messageText = chatInput.value.trim();
        if (messageText === '') return;

        sendSound.play().catch(e => console.warn("Audio play failed"));
        addMessage(messageText, 'admin');
        chatInput.value = '';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', messageText);
        formData.append('user_id', chatUserIdInput.value);
        formData.append('session_id', chatSessionIdInput.value);

        fetch('../handle_chat.php', { method: 'POST', body: formData });
    });

    loadConversations();
    setInterval(loadConversations, 5000); // Làm mới danh sách cuộc trò chuyện mỗi 5 giây
});

// Lắng nghe sự kiện khi admin quay lại tab và xóa thông báo trên tiêu đề
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        document.title = "Admin - Púp Bờ Si Shoes"; // Giả sử đây là tiêu đề gốc
    }
});
</script>