CREATE DATABASE ShoeStoreDemo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ShoeStoreDemo;
-- ROLES
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE
);

INSERT INTO roles(name) VALUES 
('Guest'),('Customer'),('Staff'),('Admin');

-- USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    phone VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO users(name,email,password,phone) VALUES
('Admin Demo','admin@demo.com','$2y$10$E2O.q2j9n3C6d5B.o9A.d.0Y.p1J.e1C.f1G.h3I.j5K.l7M.n9O','0123456789'), -- password is 'admin123'
('John Doe','john@example.com','$2y$10$g.3aX2b.C4d.E6f.G8h.I.jK.l1M.n3O.p5Q.r7S.t9U.v1W.x3Y','0987654321'), -- password is '123456'
('Jane Smith','jane@example.com','$2y$10$g.3aX2b.C4d.E6f.G8h.I.jK.l1M.n3O.p5Q.r7S.t9U.v1W.x3Y','0987123456'); -- password is '123456'

-- USER_ROLES
CREATE TABLE user_roles (
    user_id INT,
    role_id INT,
    PRIMARY KEY(user_id,role_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
);

INSERT INTO user_roles(user_id,role_id) VALUES
(1,4),  -- Admin
(2,2),  -- Customer
(3,2);  -- Customer
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    description TEXT
);

INSERT INTO categories(name,description) VALUES
('Nam','Giày dành cho nam'),('Nữ','Giày dành cho nữ'),('Trẻ em','Giày trẻ em'),
('Sneakers','Giày Sneakers'),('Boots','Giày Boots');

CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    description TEXT
);

INSERT INTO brands(name,description) VALUES
('Nike','Thương hiệu Nike'),('Adidas','Thương hiệu Adidas'),('Puma','Thương hiệu Puma');
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    code VARCHAR(50) UNIQUE,
    description TEXT,
    price DECIMAL(10,2),
    category_id INT,
    brand_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(category_id) REFERENCES categories(id),
    FOREIGN KEY(brand_id) REFERENCES brands(id)
);

INSERT INTO products(name,code,description,price,category_id,brand_id) VALUES
('Nike Air Max','NIKE001','Giày thể thao nam',2500000,1,1),
('Adidas Runner','ADIDAS001','Giày chạy bộ nữ',2300000,2,2),
('Puma Kids','PUMA001','Giày trẻ em',1200000,3,3);

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    url VARCHAR(255),
    is_main BOOLEAN DEFAULT FALSE,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

INSERT INTO product_images(product_id,url,is_main) VALUES
(1,'assets/images/nike_air1.jpg',1),
(1,'assets/images/nike_air2.jpg',0),
(2,'assets/images/adidas_runner1.jpg',1),
(3,'assets/images/puma_kids1.jpg',1);

CREATE TABLE product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    size VARCHAR(10),
    stock INT,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

INSERT INTO product_sizes(product_id,size,stock) VALUES
(1,'40',10),(1,'41',5),(2,'38',8),(2,'39',12),(3,'30',6);
CREATE TABLE product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    user_id INT,
    rating INT CHECK(rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO product_reviews(product_id,user_id,rating,comment) VALUES
(1,2,5,'Giày rất đẹp và êm chân'),
(2,3,4,'Chạy bộ thoải mái, màu sắc đẹp');
CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    session_id VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT,
    product_id INT,
    size VARCHAR(10),
    quantity INT,
    price DECIMAL(10,2),
    FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY(product_id) REFERENCES products(id)
);

INSERT INTO carts(user_id,session_id) VALUES
(2,NULL),(3,NULL);

INSERT INTO cart_items(cart_id,product_id,size,quantity,price) VALUES
(1,1,'40',1,2500000),
(2,2,'38',2,2300000);
CREATE TABLE order_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50)
);

INSERT INTO order_status(name) VALUES
('Chờ xử lý'),('Đang giao'),('Hoàn tất'),('Hủy');

CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    discount_percent INT,
    valid_from DATETIME,
    valid_to DATETIME,
    usage_limit INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO coupons(code,discount_percent,valid_from,valid_to,usage_limit) VALUES
('SUMMER2025',10,'2025-06-01','2025-08-31',100);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total_amount DECIMAL(10,2),
    shipping_address TEXT,
    status_id INT,
    coupon_id INT NULL,
    payment_method VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(status_id) REFERENCES order_status(id),
    FOREIGN KEY(coupon_id) REFERENCES coupons(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    size VARCHAR(10),
    quantity INT,
    price DECIMAL(10,2),
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY(product_id) REFERENCES products(id)
);

INSERT INTO orders(user_id,total_amount,shipping_address,status_id,payment_method) VALUES
(2,2500000,'123 Đường ABC, HCM',1,'COD');

INSERT INTO order_items(order_id,product_id,size,quantity,price) VALUES
(1,1,'40',1,2500000);
CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    product_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(product_id) REFERENCES products(id)
);

INSERT INTO wishlists(user_id,product_id) VALUES
(2,2),(3,3);

CREATE TABLE banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    image_url VARCHAR(255),
    link VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE
);

INSERT INTO banners(title,image_url,link,is_active) VALUES
('Khuyến mãi Hè','assets/images/banner1.jpg','',1);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(150),
    content TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO notifications(user_id,title,content) VALUES
(2,'Giảm giá 10%','Coupon SUMMER2025 áp dụng cho đơn hàng đầu tiên');
INSERT INTO users(name,email,password,phone) 
VALUES ('Admin 2','admin2@demo.com',
'$2y$10$E2O.q2j9n3C6d5B.o9A.d.0Y.p1J.e1C.f1G.h3I.j5K.l7M.n9O',  -- hash của 'admin123'
'0999999999');

-- Gán quyền admin (role_id = 4)
INSERT INTO user_roles(user_id, role_id)
VALUES (LAST_INSERT_ID(), 4);
