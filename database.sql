-- GreenLoop Net Zero : ฐานข้อมูล + ข้อมูลตัวอย่าง
-- นำเข้าผ่าน phpMyAdmin > Import ได้ทันที (ไฟล์นี้สร้างฐานข้อมูล greencarbon ให้เอง)

CREATE DATABASE IF NOT EXISTS greencarbon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE greencarbon;
SET NAMES utf8mb4;
SET time_zone = '+07:00';

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications, contents, redemptions, rewards, green_activities, carbon_footprint_items,
    carbon_footprints, emission_factors, recycle_requests, orders, products, categories, settings, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------- สมาชิก
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(10) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    avatar VARCHAR(150) NULL,
    password_hash VARCHAR(255) NOT NULL,
    secret_question VARCHAR(150) NOT NULL,
    secret_answer_hash VARCHAR(255) NOT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    green_points INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ตั้งค่าระบบ Green
CREATE TABLE settings (
    name VARCHAR(50) PRIMARY KEY,
    value VARCHAR(255) NOT NULL,
    label VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- หมวดอุปกรณ์ (ใช้ร่วมกันทั้งตลาดมือสองและรีไซเคิล)
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '📦',
    co2_reuse_kg DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'kgCO2e ที่ลดได้เมื่อใช้ซ้ำ 1 ชิ้น',
    co2_recycle_per_kg DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'kgCO2e ที่ลดได้เมื่อรีไซเคิล 1 กก.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ตลาดมือสอง
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    item_condition ENUM('like_new','very_good','good','fair') NOT NULL DEFAULT 'good',
    image VARCHAR(150) NULL,
    status ENUM('pending','approved','rejected','sold','hidden') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_products_status (status),
    CONSTRAINT fk_products_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    buyer_id INT UNSIGNED NOT NULL,
    seller_id INT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    contact_note VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_seller FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- รีไซเคิล E-Waste
CREATE TABLE recycle_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    weight_kg DECIMAL(6,2) NOT NULL,
    method ENUM('dropoff','parcel','pickup') NOT NULL DEFAULT 'dropoff',
    note VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('pending','accepted','completed','rejected') NOT NULL DEFAULT 'pending',
    points_awarded INT UNSIGNED NOT NULL DEFAULT 0,
    co2_saved_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    KEY idx_recycle_status (status),
    CONSTRAINT fk_recycle_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_recycle_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Net Zero Man : คาร์บอนฟุตพริ้นท์รายปี
CREATE TABLE emission_factors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('energy','transport','food','waste','device') NOT NULL,
    name VARCHAR(120) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    periods_per_year SMALLINT UNSIGNED NOT NULL DEFAULT 12 COMMENT '12 = ต่อเดือน, 52 = ต่อสัปดาห์, 1 = ต่อปี',
    factor DECIMAL(10,4) NOT NULL COMMENT 'kgCO2e ต่อ 1 หน่วย',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carbon_footprints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    year SMALLINT UNSIGNED NOT NULL COMMENT 'ปี ค.ศ.',
    total_kg DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_footprint_user_year (user_id, year),
    CONSTRAINT fk_footprint_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carbon_footprint_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    footprint_id INT UNSIGNED NOT NULL,
    factor_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'ปริมาณต่อรอบที่ผู้ใช้กรอก',
    co2e_kg DECIMAL(12,2) NOT NULL COMMENT 'ผลคำนวณต่อปี',
    CONSTRAINT fk_fpitem_footprint FOREIGN KEY (footprint_id) REFERENCES carbon_footprints (id) ON DELETE CASCADE,
    CONSTRAINT fk_fpitem_factor FOREIGN KEY (factor_id) REFERENCES emission_factors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- บัญชีกลาง: Green Point + CO2e ที่ลดได้ของทุกกิจกรรม
CREATE TABLE green_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('footprint','sell','buy','recycle','redeem','bonus') NOT NULL,
    ref_id INT UNSIGNED NULL COMMENT 'id ของรายการต้นทางตามประเภท',
    description VARCHAR(255) NOT NULL,
    points INT NOT NULL DEFAULT 0 COMMENT 'ค่าลบ = ใช้แต้ม',
    co2_saved_kg DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_created (created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ของรางวัล
CREATE TABLE rewards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    points_cost INT UNSIGNED NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redemptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reward_id INT UNSIGNED NOT NULL,
    points_used INT UNSIGNED NOT NULL,
    code CHAR(8) NOT NULL,
    status ENUM('pending','delivered') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_redemption_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_redemption_reward FOREIGN KEY (reward_id) REFERENCES rewards (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- เนื้อหา (ข่าว / แบนเนอร์ / แคมเปญ) พร้อมกำหนดวันเวลาเปิด-ปิด
CREATE TABLE contents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('news','banner','campaign') NOT NULL DEFAULT 'news',
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    image VARCHAR(150) NULL,
    point_multiplier DECIMAL(3,1) NOT NULL DEFAULT 1.0 COMMENT 'ใช้กับแคมเปญ: ตัวคูณ Green Point',
    publish_at DATETIME NOT NULL COMMENT 'เริ่มแสดงผล',
    expire_at DATETIME NULL COMMENT 'สิ้นสุดการแสดงผล (NULL = ไม่มีกำหนด)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contents_window (publish_at, expire_at),
    CONSTRAINT fk_contents_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- แจ้งเตือนผู้ดูแลระบบ (รวมรายงานปัญหาจากสมาชิก)
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('member','product','order','recycle','redeem','issue') NOT NULL,
    user_id INT UNSIGNED NULL COMMENT 'สมาชิกที่ทำให้เกิดเหตุการณ์',
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL DEFAULT '',
    link VARCHAR(150) NOT NULL DEFAULT '',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_noti_read (is_read),
    CONSTRAINT fk_noti_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================ ข้อมูลเริ่มต้น
-- รหัสผ่าน: admin = admin1234, สมาชิกตัวอย่าง = demo1234, คำตอบกันลืมทุกบัญชี = greenloop
INSERT INTO users (id, name, email, phone, address, password_hash, secret_question, secret_answer_hash, role, green_points, created_at) VALUES
(1, 'ผู้ดูแลระบบ', 'admin@greenloop.local', '0800000000', 'วิทยาลัยอาชีวศึกษา', '$2y$10$Hlspc86w66632ObfEZvRJ.wq7fHdnVnTd6YJFsJAqLV5qfTt78sbC', 'ชื่อโครงการของเราคืออะไร', '$2y$10$XH5vVtX06Oezhe.zHP.j1.Y0InpB1KEstUe65NM6WLd9cua7wBfaa', 'admin', 0, NOW() - INTERVAL 150 DAY),
(2, 'สมชาย ใจดี', 'demo@greenloop.local', '0811111111', '12 ถ.สุขุมวิท กรุงเทพฯ', '$2y$10$2otFyK2pF5rcaI5rtbnbvuWkcaJncBeHlcx5LYGHIQnMXmDRKRIrK', 'ชื่อโครงการของเราคืออะไร', '$2y$10$XH5vVtX06Oezhe.zHP.j1.Y0InpB1KEstUe65NM6WLd9cua7wBfaa', 'user', 198, NOW() - INTERVAL 120 DAY),
(3, 'สมหญิง รักษ์โลก', 'seller@greenloop.local', '0822222222', '45 ถ.นิมมานเหมินท์ เชียงใหม่', '$2y$10$2otFyK2pF5rcaI5rtbnbvuWkcaJncBeHlcx5LYGHIQnMXmDRKRIrK', 'ชื่อโครงการของเราคืออะไร', '$2y$10$XH5vVtX06Oezhe.zHP.j1.Y0InpB1KEstUe65NM6WLd9cua7wBfaa', 'user', 160, NOW() - INTERVAL 95 DAY),
(4, 'วิชัย ประหยัดไฟ', 'wichai@greenloop.local', '0833333333', '7 ถ.มิตรภาพ ขอนแก่น', '$2y$10$2otFyK2pF5rcaI5rtbnbvuWkcaJncBeHlcx5LYGHIQnMXmDRKRIrK', 'ชื่อโครงการของเราคืออะไร', '$2y$10$XH5vVtX06Oezhe.zHP.j1.Y0InpB1KEstUe65NM6WLd9cua7wBfaa', 'user', 70, NOW() - INTERVAL 40 DAY),
(5, 'มานี มีนา', 'manee@greenloop.local', '0844444444', '99 ถ.ราษฎร์ยินดี สงขลา', '$2y$10$2otFyK2pF5rcaI5rtbnbvuWkcaJncBeHlcx5LYGHIQnMXmDRKRIrK', 'ชื่อโครงการของเราคืออะไร', '$2y$10$XH5vVtX06Oezhe.zHP.j1.Y0InpB1KEstUe65NM6WLd9cua7wBfaa', 'user', 0, NOW() - INTERVAL 5 DAY);

INSERT INTO settings (name, value, label) VALUES
('points_footprint', '30', 'แต้มเมื่อคำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี'),
('points_sell', '20', 'แต้มเมื่อขายสินค้ามือสองสำเร็จ'),
('points_buy', '10', 'แต้มเมื่อซื้อสินค้ามือสองสำเร็จ'),
('points_recycle_per_kg', '20', 'แต้มต่อ E-Waste 1 กก. ที่รีไซเคิลสำเร็จ'),
('tree_kg_per_year', '9.5', 'kgCO2e ที่ต้นไม้ 1 ต้นดูดซับต่อปี (ใช้แปลงผลเป็นจำนวนต้นไม้)'),
('avg_footprint_kg', '3700', 'คาร์บอนฟุตพริ้นท์เฉลี่ยต่อคนต่อปี (kgCO2e) สำหรับเปรียบเทียบ');

INSERT INTO categories (id, name, icon, co2_reuse_kg, co2_recycle_per_kg) VALUES
(1, 'โทรศัพท์มือถือ', '📱', 55.00, 2.00),
(2, 'โน้ตบุ๊ก / คอมพิวเตอร์', '💻', 250.00, 1.80),
(3, 'แท็บเล็ต', '📲', 100.00, 1.90),
(4, 'กล้องถ่ายภาพ', '📷', 40.00, 1.60),
(5, 'แบตเตอรี่ / สายชาร์จ', '🔋', 5.00, 2.50),
(6, 'อุปกรณ์ IT อื่นๆ', '🖨️', 30.00, 1.50);

-- ค่าสัมประสิทธิ์เป็นค่าประมาณสำหรับสาธิต ผู้ดูแลระบบแก้ไขให้ตรงกับค่าอ้างอิงล่าสุดได้ที่เมนู "ตั้งค่า Green"
INSERT INTO emission_factors (id, category, name, unit, periods_per_year, factor) VALUES
(1, 'energy', 'ไฟฟ้าที่ใช้ในบ้าน', 'หน่วย (kWh)', 12, 0.4999),
(2, 'energy', 'ก๊าซหุงต้ม (LPG)', 'กก.', 12, 3.1100),
(3, 'transport', 'รถยนต์ส่วนตัว (เบนซิน)', 'กม.', 52, 0.1700),
(4, 'transport', 'รถจักรยานยนต์', 'กม.', 52, 0.0500),
(5, 'transport', 'รถโดยสารสาธารณะ / รถไฟฟ้า', 'กม.', 52, 0.0400),
(6, 'food', 'มื้ออาหารที่มีเนื้อสัตว์', 'มื้อ', 52, 1.5000),
(7, 'food', 'มื้ออาหารมังสวิรัติ', 'มื้อ', 52, 0.5000),
(8, 'waste', 'ขยะทั่วไปที่ทิ้ง', 'กก.', 52, 0.7000),
(9, 'device', 'ซื้อสมาร์ตโฟนเครื่องใหม่', 'เครื่อง', 1, 55.0000),
(10, 'device', 'ซื้อโน้ตบุ๊ก / คอมพิวเตอร์เครื่องใหม่', 'เครื่อง', 1, 250.0000);

INSERT INTO products (id, user_id, category_id, title, description, price, item_condition, status, created_at) VALUES
(1, 3, 2, 'MacBook Air M1 8/256GB', 'ใช้งานมา 2 ปี แบตเตอรี่ 88% ไม่มีรอยตก อุปกรณ์ครบกล่อง', 18900, 'very_good', 'sold', NOW() - INTERVAL 80 DAY),
(2, 3, 1, 'iPhone 12 64GB สีดำ', 'เครื่องศูนย์ไทย สุขภาพแบต 85% มีรอยขนแมวเล็กน้อย', 8500, 'good', 'sold', NOW() - INTERVAL 50 DAY),
(3, 3, 3, 'iPad Gen 9 Wi-Fi 64GB', 'ใช้เรียนออนไลน์ สภาพดีมาก พร้อมเคสและฟิล์ม', 6900, 'very_good', 'approved', NOW() - INTERVAL 20 DAY),
(4, 2, 4, 'กล้อง Canon EOS M50 พร้อมเลนส์คิท', 'ชัตเตอร์ประมาณ 9,000 ครั้ง ใช้งานปกติทุกฟังก์ชัน', 12500, 'good', 'approved', NOW() - INTERVAL 12 DAY),
(5, 4, 6, 'จอมอนิเตอร์ Dell 24 นิ้ว', 'ไม่มีเดดพิกเซล ขาตั้งปรับระดับได้', 2200, 'good', 'approved', NOW() - INTERVAL 6 DAY),
(6, 4, 1, 'Samsung Galaxy A52 128GB', 'จอมีรอยเล็กน้อย ใช้งานได้ปกติ', 3900, 'fair', 'pending', NOW() - INTERVAL 1 DAY);

INSERT INTO orders (id, product_id, buyer_id, seller_id, price, contact_note, status, created_at, completed_at) VALUES
(1, 1, 2, 3, 18900, 'นัดรับที่ BTS อโศก', 'completed', NOW() - INTERVAL 78 DAY, NOW() - INTERVAL 75 DAY),
(2, 2, 4, 3, 8500, 'ส่งพัสดุเก็บเงินปลายทาง', 'completed', NOW() - INTERVAL 35 DAY, NOW() - INTERVAL 32 DAY);

INSERT INTO recycle_requests (id, user_id, category_id, item_name, quantity, weight_kg, method, note, status, points_awarded, co2_saved_kg, created_at, completed_at) VALUES
(1, 2, 1, 'โทรศัพท์เก่าเปิดไม่ติด', 2, 0.40, 'dropoff', '', 'completed', 10, 0.80, NOW() - INTERVAL 100 DAY, NOW() - INTERVAL 97 DAY),
(2, 2, 2, 'โน้ตบุ๊กจอแตก', 1, 2.30, 'parcel', '', 'completed', 46, 4.14, NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 55 DAY),
(3, 3, 5, 'แบตเตอรี่สำรองบวม 3 ก้อน', 3, 0.90, 'dropoff', '', 'completed', 18, 2.25, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 43 DAY),
(4, 4, 6, 'เครื่องพิมพ์อิงค์เจ็ตเสีย', 1, 4.50, 'pickup', 'สะดวกช่วงเย็น', 'completed', 90, 6.75, NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 15 DAY),
(5, 3, 3, 'แท็บเล็ตเก่าแบตเสื่อม', 1, 0.50, 'parcel', '', 'accepted', 0, 0, NOW() - INTERVAL 3 DAY, NULL),
(6, 5, 5, 'สายชาร์จชำรุดหลายเส้น', 6, 0.30, 'dropoff', '', 'pending', 0, 0, NOW() - INTERVAL 1 DAY, NULL);

INSERT INTO carbon_footprints (id, user_id, year, total_kg, created_at) VALUES
(1, 2, YEAR(NOW()) - 1, 3699.89, NOW() - INTERVAL 118 DAY),
(2, 2, YEAR(NOW()), 3039.19, NOW() - INTERVAL 30 DAY),
(3, 3, YEAR(NOW()), 2311.62, NOW() - INTERVAL 90 DAY),
(4, 4, YEAR(NOW()), 4406.70, NOW() - INTERVAL 38 DAY);

INSERT INTO carbon_footprint_items (footprint_id, factor_id, amount, co2e_kg) VALUES
(1, 1, 260, 1559.69), (1, 3, 120, 1060.80), (1, 6, 10, 780.00), (1, 8, 6, 218.40), (1, 9, 1, 55.00), (1, 7, 1, 26.00),
(2, 1, 210, 1259.75), (2, 3, 80, 707.20), (2, 5, 60, 124.80), (2, 6, 8, 624.00), (2, 7, 6, 156.00), (2, 8, 4.6, 167.44),
(3, 1, 150, 899.82), (3, 4, 90, 234.00), (3, 5, 100, 208.00), (3, 6, 7, 546.00), (3, 7, 10, 260.00), (3, 8, 4.5, 163.80),
(4, 1, 320, 1919.62), (4, 2, 4, 149.28), (4, 3, 150, 1326.00), (4, 6, 9, 702.00), (4, 8, 7, 254.80), (4, 9, 1, 55.00);

INSERT INTO green_activities (user_id, type, ref_id, description, points, co2_saved_kg, created_at) VALUES
(2, 'footprint', 1, 'คำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี', 30, 0, NOW() - INTERVAL 118 DAY),
(2, 'recycle', 1, 'รีไซเคิล: โทรศัพท์เก่าเปิดไม่ติด', 10, 0.80, NOW() - INTERVAL 97 DAY),
(3, 'footprint', 3, 'คำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี', 30, 0, NOW() - INTERVAL 90 DAY),
(2, 'buy', 1, 'ซื้อมือสอง: MacBook Air M1 8/256GB', 10, 125.00, NOW() - INTERVAL 75 DAY),
(3, 'sell', 1, 'ขายมือสอง: MacBook Air M1 8/256GB', 20, 125.00, NOW() - INTERVAL 75 DAY),
(2, 'recycle', 2, 'รีไซเคิล: โน้ตบุ๊กจอแตก', 46, 4.14, NOW() - INTERVAL 55 DAY),
(3, 'recycle', 3, 'รีไซเคิล: แบตเตอรี่สำรองบวม 3 ก้อน', 18, 2.25, NOW() - INTERVAL 43 DAY),
(4, 'footprint', 4, 'คำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี', 30, 0, NOW() - INTERVAL 38 DAY),
(4, 'buy', 2, 'ซื้อมือสอง: iPhone 12 64GB สีดำ', 10, 27.50, NOW() - INTERVAL 32 DAY),
(3, 'sell', 2, 'ขายมือสอง: iPhone 12 64GB สีดำ', 20, 27.50, NOW() - INTERVAL 32 DAY),
(2, 'footprint', 2, 'คำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี', 30, 0, NOW() - INTERVAL 30 DAY),
(4, 'recycle', 4, 'รีไซเคิล: เครื่องพิมพ์อิงค์เจ็ตเสีย', 90, 6.75, NOW() - INTERVAL 15 DAY),
(3, 'bonus', NULL, 'โบนัสต้อนรับสมาชิกรุ่นแรก', 100, 0, NOW() - INTERVAL 14 DAY),
(2, 'bonus', NULL, 'โบนัสต้อนรับสมาชิกรุ่นแรก', 100, 0, NOW() - INTERVAL 14 DAY),
(4, 'redeem', 1, 'แลกของรางวัล: ถุงผ้าลดโลกร้อน', -60, 0, NOW() - INTERVAL 10 DAY),
(3, 'redeem', 2, 'แลกของรางวัล: ต้นกล้าไม้ยืนต้น 1 ต้น', -28, 0, NOW() - INTERVAL 8 DAY),
(2, 'redeem', 3, 'แลกของรางวัล: ต้นกล้าไม้ยืนต้น 1 ต้น', -28, 0, NOW() - INTERVAL 7 DAY);

INSERT INTO rewards (id, name, description, points_cost, stock) VALUES
(1, 'ถุงผ้าลดโลกร้อน', 'ถุงผ้าแคนวาสลาย GreenLoop', 60, 19),
(2, 'ต้นกล้าไม้ยืนต้น 1 ต้น', 'รับต้นกล้าไปปลูกเพื่อดูดซับคาร์บอน', 28, 48),
(3, 'แก้วน้ำพกพาสเตนเลส', 'ลดการใช้แก้วพลาสติกแบบใช้ครั้งเดียว', 150, 10),
(4, 'ส่วนลดค่าซ่อมอุปกรณ์ 100 บาท', 'ใช้ได้ที่ร้านซ่อมพันธมิตร ยืดอายุการใช้งานอุปกรณ์', 200, 30);

INSERT INTO redemptions (id, user_id, reward_id, points_used, code, status, created_at) VALUES
(1, 4, 1, 60, 'GL7K2M9Q', 'delivered', NOW() - INTERVAL 10 DAY),
(2, 3, 2, 28, 'GL4T8XWP', 'delivered', NOW() - INTERVAL 8 DAY),
(3, 2, 2, 28, 'GL9B3HZD', 'pending', NOW() - INTERVAL 7 DAY);

INSERT INTO contents (type, title, body, point_multiplier, publish_at, expire_at, created_by, created_at) VALUES
('banner', 'ส่งต่อเทคโนโลยี เพื่อโลกที่ยั่งยืน', 'วัดคาร์บอนฟุตพริ้นท์ของคุณ แล้วลดด้วยการใช้ซ้ำและรีไซเคิล', 1.0, NOW() - INTERVAL 30 DAY, NULL, 1, NOW() - INTERVAL 30 DAY),
('news', 'E-Waste คืออะไร ทำไมต้องรีไซเคิลให้ถูกวิธี', 'ขยะอิเล็กทรอนิกส์มีทั้งโลหะมีค่าและสารอันตราย เช่น ตะกั่ว ปรอท แคดเมียม หากทิ้งรวมกับขยะทั่วไปจะปนเปื้อนสู่ดินและแหล่งน้ำ\n\nการส่งรีไซเคิลผ่านจุดรับที่ถูกต้องช่วยนำวัสดุกลับมาใช้ใหม่ และลดการปล่อยก๊าซเรือนกระจกจากการผลิตวัตถุดิบใหม่', 1.0, NOW() - INTERVAL 25 DAY, NULL, 1, NOW() - INTERVAL 25 DAY),
('news', '5 วิธีลดคาร์บอนฟุตพริ้นท์จากอุปกรณ์ดิจิทัล', '1. ใช้อุปกรณ์ให้นานขึ้นอย่างน้อย 1 ปี\n2. เลือกซื้อมือสองแทนเครื่องใหม่\n3. ซ่อมก่อนเปลี่ยน\n4. ปิดเครื่องและถอดปลั๊กเมื่อไม่ใช้งาน\n5. ส่งรีไซเคิลเมื่อหมดอายุการใช้งาน', 1.0, NOW() - INTERVAL 10 DAY, NULL, 1, NOW() - INTERVAL 10 DAY),
('campaign', 'สัปดาห์รีไซเคิล แต้ม x2', 'ทุกกิจกรรมในช่วงแคมเปญรับ Green Point คูณสอง', 2.0, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 6 DAY, 1, NOW() - INTERVAL 2 DAY),
('news', 'เปิดจุดรับ E-Waste แห่งใหม่ที่อาคารกิจกรรม', 'เริ่มให้บริการตามวันเวลาที่ตั้งไว้ ข่าวนี้ตั้งเวลาเผยแพร่ล่วงหน้า', 1.0, NOW() + INTERVAL 3 DAY, NOW() + INTERVAL 33 DAY, 1, NOW()),
('news', 'สรุปกิจกรรม Big Cleaning Day', 'ข่าวนี้หมดช่วงเวลาแสดงผลแล้ว', 1.0, NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 40 DAY, 1, NOW() - INTERVAL 60 DAY);

INSERT INTO notifications (type, user_id, title, message, link, is_read, created_at) VALUES
('member', 4, 'สมาชิกใหม่: วิชัย ประหยัดไฟ', 'wichai@greenloop.local', 'admin/users.php', 1, NOW() - INTERVAL 40 DAY),
('member', 5, 'สมาชิกใหม่: มานี มีนา', 'manee@greenloop.local', 'admin/users.php', 0, NOW() - INTERVAL 5 DAY),
('recycle', 3, 'คำขอรีไซเคิลใหม่', 'แท็บเล็ตเก่าแบตเสื่อม (0.50 กก.)', 'admin/recycles.php', 1, NOW() - INTERVAL 3 DAY),
('issue', 2, 'รายงานปัญหา: จุดรับรีไซเคิล', 'อยากให้เพิ่มจุดรับ E-Waste ใกล้หอพักนักศึกษา', 'admin/notifications.php', 0, NOW() - INTERVAL 2 DAY),
('recycle', 5, 'คำขอรีไซเคิลใหม่', 'สายชาร์จชำรุดหลายเส้น (0.30 กก.)', 'admin/recycles.php', 0, NOW() - INTERVAL 1 DAY),
('product', 4, 'สินค้ารออนุมัติ', 'Samsung Galaxy A52 128GB', 'admin/products.php', 0, NOW() - INTERVAL 1 DAY);
