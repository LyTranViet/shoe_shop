</main>
<footer class="footer-modern">
    <style>
        .footer-modern {
            background: #181c1f;
            color: #f3f4f6;
            padding: 48px 0 0 0;
            font-size: 1.08rem;
            border-top: 1.5px solid #23272b;
            margin-top: 48px;
        }
        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            padding: 0 24px;
        }
        .footer-col h3 {
            color: #fff;
            font-size: 1.18em;
            font-weight: 700;
            margin-bottom: 18px;
            letter-spacing: 0.2px;
        }
        .footer-about {
            color: #e5e7eb;
            font-size: 1.04em;
            margin-bottom: 22px;
        }
        .footer-social {
            display: flex;
            gap: 18px;
            margin-top: 18px;
        }
        .footer-social a {
            color: #fff;
            background: #23272b;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25em;
            transition: background 0.18s, color 0.18s, transform 0.18s;
            box-shadow: 0 2px 8px #0002;
        }
        .footer-social a:hover {
            background: #0ea5ff;
            color: #fff;
            transform: translateY(-2px) scale(1.12);
        }
        .footer-links, .footer-service {
            list-style: none;
            padding: 0;
            margin: 0;
            color: #e5e7eb;
        }
        .footer-links li, .footer-service li {
            margin-bottom: 12px;
        }
        .footer-links a, .footer-service a {
            color: #f3f4f6;
            text-decoration: none;
            transition: color 0.15s;
        }
        .footer-links a:hover, .footer-service a:hover {
            color: #0ea5ff;
            text-decoration: underline;
        }
        .footer-newsletter input[type="email"] {
            padding: 10px 12px;
            border-radius: 6px 0 0 6px;
            border: none;
            outline: none;
            font-size: 1em;
            background: #23272b;
            color: #fff;
            width: 180px;
        }
        .footer-newsletter button {
            padding: 10px 18px;
            border-radius: 0 6px 6px 0;
            border: none;
            background: #0ea5ff;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.15s;
        }
        .footer-newsletter button:hover {
            background: #2563eb;
        }
        .footer-contact {
            margin-top: 18px;
            color: #e5e7eb;
            font-size: 1em;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .footer-contact span {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .footer-bottom {
            background: #15181b;
            color: #a1a1aa;
            text-align: center;
            font-size: 0.98em;
            padding: 18px 0 10px 0;
            margin-top: 38px;
            border-top: 1px solid #23272b;
        }
        @media (max-width: 900px) {
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 32px; }
        }
        @media (max-width: 600px) {
            .footer-grid { grid-template-columns: 1fr; gap: 24px; }
        }
    </style>
    <div class="footer-grid">
        <div class="footer-col">
            <h3>About Us</h3>
            <div class="footer-about">We're passionate about providing quality footwear with exceptional service. Find your perfect pair with us.</div>
            <div class="footer-social">
                <a href="#" title="Facebook" aria-label="Facebook"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor"><path d="M22.675 0h-21.35C.595 0 0 .592 0 1.326v21.348C0 23.408.595 24 1.325 24h11.495v-9.294H9.692v-3.622h3.128V8.413c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.797.143v3.24l-1.918.001c-1.504 0-1.797.715-1.797 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116C23.406 24 24 23.408 24 22.674V1.326C24 .592 23.406 0 22.675 0"/></svg></a>
                <a href="#" title="Instagram" aria-label="Instagram"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 1.366.062 2.633.334 3.608 1.308.974.974 1.246 2.241 1.308 3.608.058 1.266.069 1.646.069 4.85s-.011 3.584-.069 4.85c-.062 1.366-.334 2.633-1.308 3.608-.974.974-2.241 1.246-3.608 1.308-1.266.058-1.646.069-4.85.069s-3.584-.011-4.85-.069c-1.366-.062-2.633-.334-3.608-1.308-.974-.974-1.246-2.241-1.308-3.608C2.175 15.647 2.163 15.267 2.163 12s.012-3.584.07-4.85c.062-1.366.334-2.633 1.308-3.608C4.515 2.567 5.782 2.295 7.148 2.233 8.414 2.175 8.794 2.163 12 2.163zm0-2.163C8.741 0 8.332.013 7.052.072 5.771.131 4.659.363 3.678 1.344 2.697 2.325 2.465 3.437 2.406 4.718 2.347 5.998 2.334 6.407 2.334 12c0 5.593.013 6.002.072 7.282.059 1.281.291 2.393 1.272 3.374.981.981 2.093 1.213 3.374 1.272 1.28.059 1.689.072 7.282.072s6.002-.013 7.282-.072c1.281-.059 2.393-.291 3.374-1.272.981-.981 1.213-2.093 1.272-3.374.059-1.28.072-1.689.072-7.282s-.013-6.002-.072-7.282c-.059-1.281-.291-2.393-1.272-3.374C21.393.363 20.281.131 19 .072 17.72.013 17.311 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zm0 10.162a3.999 3.999 0 1 1 0-7.998 3.999 3.999 0 0 1 0 7.998zm6.406-11.845a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/></svg></a>
                <a href="#" title="Twitter" aria-label="Twitter"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor"><path d="M24 4.557a9.83 9.83 0 0 1-2.828.775 4.932 4.932 0 0 0 2.165-2.724c-.951.564-2.005.974-3.127 1.195a4.916 4.916 0 0 0-8.38 4.482C7.691 8.095 4.066 6.13 1.64 3.161c-.542.929-.856 2.01-.857 3.17 0 2.188 1.115 4.116 2.823 5.247a4.904 4.904 0 0 1-2.229-.616c-.054 2.281 1.581 4.415 3.949 4.89a4.936 4.936 0 0 1-2.224.084c.627 1.956 2.444 3.377 4.6 3.417A9.867 9.867 0 0 1 0 21.543a13.94 13.94 0 0 0 7.548 2.209c9.058 0 14.009-7.513 14.009-14.009 0-.213-.005-.425-.014-.636A10.012 10.012 0 0 0 24 4.557z"/></svg></a>
                <a href="#" title="Pinterest" aria-label="Pinterest"><svg width="1.2em" height="1.2em" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.396 7.627 11.093-.105-.943-.2-2.393.042-3.425.219-.963 1.408-6.142 1.408-6.142s-.36-.719-.36-1.781c0-1.668.968-2.915 2.172-2.915 1.025 0 1.52.77 1.52 1.693 0 1.032-.656 2.574-.995 4.008-.283 1.197.601 2.174 1.782 2.174 2.138 0 3.782-2.254 3.782-5.506 0-2.88-2.07-4.89-5.03-4.89-3.43 0-5.44 2.572-5.44 5.23 0 1.033.398 2.143.895 2.744.099.12.113.225.083.346-.09.377-.292 1.197-.332 1.363-.05.207-.162.252-.375.152-1.398-.65-2.27-2.687-2.27-4.326 0-3.523 2.563-6.76 7.39-6.76 3.877 0 6.89 2.76 6.89 6.44 0 3.857-2.43 6.96-5.81 6.96-1.162 0-2.255-.627-2.627-1.34l-.715 2.724c-.217.823-.642 1.853-.956 2.482.72.222 1.48.343 2.273.343 6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg></a>
            </div>
        </div>
        <div class="footer-col">
            <h3>Quick Links</h3>
            <ul class="footer-links">
                <li><a href="#">Home</a></li>
                <li><a href="#">Shop</a></li>
                <li><a href="#">New Arrivals</a></li>
                <li><a href="#">Sale</a></li>
                <li><a href="#">Size Guide</a></li>
                <li><a href="#">Contact Us</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h3>Customer Service</h3>
            <ul class="footer-service">
                <li><a href="#">Shipping Information</a></li>
                <li><a href="#">Returns & Exchange</a></li>
                <li><a href="#">Payment Methods</a></li>
                <li><a href="#">Order Tracking</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms & Conditions</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h3>Newsletter</h3>
            <div style="color:#e5e7eb; font-size:1.01em; margin-bottom:14px;">Subscribe to receive updates, access to exclusive deals, and more.</div>
            <form class="footer-newsletter" method="post" onsubmit="return false;" autocomplete="off" style="display:flex; gap:0; margin-bottom:18px;">
                <input type="email" name="newsletter_email" placeholder="Enter your email" required>
                <button type="submit">Subscribe</button>
            </form>
            <div class="footer-contact">
                <span><svg width="1.1em" height="1.1em" fill="currentColor" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.72 19.72 0 0 1 3.08 5.18 2 2 0 0 1 5 3h3a2 2 0 0 1 2 1.72c.13 1.05.37 2.07.73 3.05a2 2 0 0 1-.45 2.11l-1.27 1.27a16 16 0 0 0 6.6 6.6l1.27-1.27a2 2 0 0 1 2.11-.45c.98.36 2 .6 3.05.73A2 2 0 0 1 22 16.92z"/></svg> +1 234 567 890</span>
                <span><svg width="1.1em" height="1.1em" fill="currentColor" viewBox="0 0 24 24"><path d="M21 8V7l-3 2-2-2-7 7 2 2 7-7 2 2 3-2z"/></svg> contact@shoestore.com</span>
                <span><svg width="1.1em" height="1.1em" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5z"/></svg> 123 Shoe Street, Fashion City</span>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; 2025 ShoeStore. All Rights Reserved.
    </div>
</footer>
            </div>
        </div>
    </div>
</footer>
