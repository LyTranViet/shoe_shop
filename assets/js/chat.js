class ChatWidget {
    constructor(opts = {}) {
        this.endpoint = opts.endpoint || 'chat.php';
        this.init();
        this.isLoading = false;
    }

    init() {
        this.injectStyles();
        this.createElements();
        this.attachEvents();
        this.showWelcome();
    }

    injectStyles() {
        // no-op: styles are in assets/css/chat.css; ensure header.php includes it
    }

    createElements() {
        this.root = document.createElement('div');
        this.root.className = 'chat-widget';

        this.button = document.createElement('button');
        this.button.className = 'chat-button';
        // Avatar image inside circular button with a small chat badge overlay
        this.button.innerHTML = `
            <span class="chat-button-inner">
                <img src="assets/images/product/chatbot.png" class="chat-icon" alt="chat" onerror="this.style.display='none'" />
                <svg class="chat-fallback" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            </span>
        `;

        this.container = document.createElement('div');
        this.container.className = 'chat-container';

        this.header = document.createElement('div');
        this.header.className = 'chat-header';
        this.header.textContent = 'Hỗ trợ trực tuyến cùng Tùng Béo';

        this.messages = document.createElement('div');
        this.messages.className = 'chat-messages';

        this.inputArea = document.createElement('div');
        this.inputArea.className = 'chat-input';
        this.inputArea.innerHTML = `<form><input type="text" placeholder="Nhập câu hỏi của bạn..." autocomplete="off"><button type="submit">Gửi</button></form>`;

        this.container.appendChild(this.header);
        this.container.appendChild(this.messages);
        this.container.appendChild(this.inputArea);
        this.root.appendChild(this.container);
        this.root.appendChild(this.button);
        document.body.appendChild(this.root);
    }

    attachEvents() {
        this.button.addEventListener('click', () => {
            if (!this.container.style.display || this.container.style.display === 'none') {
                this.container.style.display = 'flex';
                this.inputArea.querySelector('input').focus();
            } else {
                this.container.style.display = 'none';
            }
        });

        const form = this.inputArea.querySelector('form');
        const input = form.querySelector('input');

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const q = input.value.trim();
            if (!q || this.isLoading) return;
            this.addMessage(q, 'user');
            input.value = '';
            this.fetchResults(q);
        });

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });

        // Delegated click handler for clickable tags (size/color)
        this.messages.addEventListener('click', (e) => {
            const btn = e.target.closest && e.target.closest('.tag-click');
            if (!btn) return;
            const color = btn.getAttribute('data-color');
            const size = btn.getAttribute('data-size');
            if (size) {
                this.addMessage('Tìm theo size ' + size, 'user');
                this.fetchResults('size ' + size);
            } else if (color) {
                this.addMessage('Tìm màu ' + color, 'user');
                this.fetchResults(color);
            }
        });
    }

    showWelcome() {
        setTimeout(() => {
            this.addMessage('Xin chào! Tôi có thể giúp bạn tìm sản phẩm theo tên, màu sắc hoặc size. Ví dụ: "giày nike size 42", "sneaker đen", "giày dưới 500k"', 'bot');
        }, 400);
    }

    addMessage(text, who='bot', results=null) {
        // remove typing
        const typ = this.messages.querySelector('.typing-indicator');
        if (typ) typ.remove();

        const m = document.createElement('div');
        m.className = 'message ' + (who === 'user' ? 'user-message' : 'bot-message');
        m.textContent = text;
        this.messages.appendChild(m);

        if (results && results.length) {
            const cont = document.createElement('div');
            results.forEach(p => {
                    let imgSrc = 'assets/images/product/default.jpg';
                    if (p.image) {
                        const img = String(p.image).trim();
                        if (/^(https?:\/\/|\/|assets\/)/i.test(img)) {
                            imgSrc = img;
                        } else {
                            imgSrc = 'assets/images/product/' + img;
                        }
                    }
                    const card = document.createElement('div');
                    card.className = 'product-result';
                    card.innerHTML = `
                        <div class="product-image"><img src="${imgSrc}" onerror="this.src='assets/images/product/default.jpg'" alt="${p.name}"></div>
                    <div class="product-info">
                        <a href="${p.url}" target="_blank">${p.name}</a>
                        <div class="price">${new Intl.NumberFormat('vi-VN').format(p.price)}₫</div>
                        <div class="details">
                            ${p.brand?'<span class="tag">'+p.brand+'</span>':''}
                            ${p.category?'<span class="tag">'+p.category+'</span>':''}
                            ${p.color?'<span class="tag">'+p.color+'</span>':''}
                                ${p.sizes && p.sizes.length ? p.sizes.map(s => `<span class="tag">Size ${s}</span>`).join('') : ''}
                        </div>
                    </div>
                `;
                cont.appendChild(card);
            });
            this.messages.appendChild(cont);
        }

        this.messages.scrollTop = this.messages.scrollHeight;
    }

    showTyping() {
        const t = document.createElement('div');
        t.className = 'typing-indicator';
        t.innerHTML = '<span></span><span></span><span></span>';
        this.messages.appendChild(t);
        this.messages.scrollTop = this.messages.scrollHeight;
    }

    async fetchResults(q) {
        this.isLoading = true;
        this.showTyping();
        try {
            const fd = new FormData();
            fd.append('q', q);
            const res = await fetch(this.endpoint, {method: 'POST', body: fd});
            const data = await res.json();
            this.isLoading = false;
            // small delay for UX
            setTimeout(() => {
                if (data.success) this.addMessage(data.reply, 'bot', data.results);
                else this.addMessage('Lỗi: ' + (data.reply || 'Server error'), 'bot');
            }, 300);
        } catch (err) {
            this.isLoading = false;
            this.addMessage('Không thể kết nối tới server.', 'bot');
        }
    }
}

// init
window.addEventListener('DOMContentLoaded', () => new ChatWidget());