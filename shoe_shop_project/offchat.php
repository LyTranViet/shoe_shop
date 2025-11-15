<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/init.php'; // Nạp init.php để có BASE_URL và các hàm khác
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hỗ trợ khách hàng - Púp Bờ Si</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body>
<style>
    .chat-container {
        /* Thay đổi để chiếm toàn màn hình */
        width: 100%;
        height: 100vh; /* 100% chiều cao viewport */
        margin: 0;
        background-color: #fff;
        border-radius: 0;
        box-shadow: none;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .chat-header {
        background: linear-gradient(90deg, #0ea5ff, #2563eb);
        color: white;
        padding: 1rem;
        text-align: center;
        font-weight: 600;
        font-size: 1.2rem;
        border-bottom: none;
        border-radius: 0;
        flex-shrink: 0; /* Không co lại */
    }

    .chat-messages {
        flex-grow: 1;
        padding: 1.5rem;
        overflow-y: auto;
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
        background-color: #0ea5ff;
        color: white;
        border-bottom-right-radius: 4px;
        align-self: flex-end;
    }

    .message.admin {
        background-color: #f1f5f9;
        color: #334155;
        border-bottom-left-radius: 4px;
        align-self: flex-start;
    }

    .chat-input-form {
        display: flex;
        padding: 1rem;
        border-top: 1px solid #e2e8f0;
        background-color: #f8fafc;
    }

    #chat-input {
        flex-grow: 1;
        border: 1px solid #cbd5e1;
        border-radius: 20px;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        outline: none;
        transition: border-color 0.2s;
    }

    #chat-input:focus {
        border-color: #0ea5ff;
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
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s;
    }

    #send-button:hover {
        background-color: #2563eb;
    }
</style>

<div class="chat-container">
    <div class="chat-header">
        Hỗ trợ khách hàng
    </div>
    <div class="chat-messages" id="chat-messages">
        <!-- Tin nhắn sẽ được tải vào đây -->
        <div class="message admin">Cảm ơn bạn đã liên hệ với Púp Bờ Si. Chúng tôi sẽ trả lời bạn trong thời gian sớm nhất.</div>
    </div>
    <form class="chat-input-form" id="chat-form">
        <input type="text" id="chat-input" placeholder="Nhập tin nhắn của bạn..." autocomplete="off" required>
        <button type="submit" id="send-button" title="Gửi">➤</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const welcomeMessage = document.querySelector('.message.admin');
    const chatInput = document.getElementById('chat-input');

    // Hàm để cuộn xuống cuối
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Hàm để thêm tin nhắn vào giao diện
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message', sender);
        messageDiv.textContent = text;
        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    // Gửi tin nhắn
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const messageText = chatInput.value.trim();
        if (messageText === '') return;

        addMessage(messageText, 'user');
        chatInput.value = '';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', messageText);

        fetch('handle_chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())  
        .then(data => {
            if (data.success && data.reply) {
                setTimeout(() => addMessage(data.reply, 'admin'), 800);
            }
        });
    });

    // Tải lịch sử chat khi vào trang
    function fetchMessages() {  
        fetch('handle_chat.php?action=fetch')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                // Nếu tin nhắn đầu tiên từ server không có ID (là tin nhắn chào mừng mặc định)
                // và trong khung chat đã có tin nhắn chào mừng, thì không làm gì cả.
                if (data.messages.length === 1 && data.messages[0].id === null && welcomeMessage) {
                    return;
                }

                // Xóa tin nhắn chào mừng mặc định ban đầu nếu có tin nhắn từ DB
                if (welcomeMessage) {
                    welcomeMessage.remove();
                }

                chatMessages.innerHTML = ''; // Xóa tất cả tin nhắn hiện tại
                data.messages.forEach(msg => addMessage(msg.message, msg.sender)); // Vẽ lại toàn bộ
            }
        });
    }

    fetchMessages();
    // Cứ 5 giây lại kiểm tra tin nhắn mới một lần
    setInterval(fetchMessages, 2000); // Cập nhật tin nhắn mỗi 2 giây
});
</script>

<?php
// Không cần footer nữa
?>
</body>
</html>