document.addEventListener("DOMContentLoaded", () => {
  const chatForm = document.querySelector("#chat-form");
  const input = document.querySelector("#chat-input");
  const chatBox = document.querySelector("#chat-box");

  chatForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const q = input.value.trim();
    if (!q) return;

    appendMessage("user", q);
    input.value = "";

    await searchProducts(q);
  });

  async function searchProducts(query) {
    appendMessage("bot", "⏳ Đang tìm sản phẩm...");

    try {
      const res = await fetch("chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "q=" + encodeURIComponent(query),
      });
      const data = await res.json();

      const lastBot = chatBox.querySelector(".bot:last-child");
      if (lastBot) lastBot.remove();

      if (!data.success) {
        appendMessage("bot", data.reply);
        return;
      }

      appendMessage("bot", data.reply);

      if (data.results && data.results.length) {
        data.results.forEach((p) => {
          const html = `
          <div class="product-card">
            <img src="${p.image}" alt="">
            <div class="info">
              <h4>${p.name}</h4>
              <p class="price">${p.price.toLocaleString()}₫</p>
              
              ${p.color ? `<p class="colors">Màu: ${renderColorButtons(p.color)}</p>` : ""}
              ${p.sizes.length ? `<p class="sizes">Size: ${renderSizeButtons(p.sizes)}</p>` : ""}

              <a href="${p.url}" class="btn" target="_blank">Xem chi tiết</a>
            </div>
          </div>`;
          appendMessage("bot", html, true);
        });

        // Gắn sự kiện cho button size/màu
        chatBox.querySelectorAll(".size-btn").forEach(btn => {
          btn.addEventListener("click", () => searchProducts("size " + btn.dataset.size));
        });
        chatBox.querySelectorAll(".color-btn").forEach(btn => {
          btn.addEventListener("click", () => searchProducts(btn.dataset.color));
        });
      }
    } catch (err) {
      appendMessage("bot", "⚠️ Lỗi kết nối server.");
    }
  }

  function renderSizeButtons(sizes) {
    return sizes.map(s => `<button class="size-btn" data-size="${s}">${s}</button>`).join(" ");
  }

  function renderColorButtons(color) {
    // Có thể mở rộng nhiều màu, hiện tại chỉ 1 màu theo data
    return `<button class="color-btn" data-color="${color}">${color}</button>`;
  }

  function appendMessage(role, text, isHTML = false) {
    const msg = document.createElement("div");
    msg.className = role;
    msg.innerHTML = isHTML ? text : `<p>${text}</p>`;
    chatBox.appendChild(msg);
    chatBox.scrollTop = chatBox.scrollHeight;
  }
});
