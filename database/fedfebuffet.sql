-- Fedfe Buffet / MariaDB / UTF-8
-- Import once into a fresh project database named fedfebuffet.
-- This script never drops data and never selects or changes the existing buffet database.
-- MENU.description is the only extra column added to the supplied architecture.
-- TABLE.is_available: TRUE = open for customer ordering, FALSE = closed.
-- ITEM.price is the captured LINE TOTAL (quantity times extra unit price).
-- MEMBER and SESSION_MEMBER are retained but unused in this version.
-- Demonstration employee password: FedfeDemo2026! (all accounts below).
-- Fictional demonstration data; all physical tables start closed.

CREATE DATABASE IF NOT EXISTS `fedfebuffet`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fedfebuffet`;
SET NAMES utf8mb4;

CREATE TABLE `TABLE` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` INT UNSIGNED NOT NULL,
  `is_available` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_table_number` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `MENU` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image_path` VARCHAR(255) NULL DEFAULT NULL,
  `is_available` BOOLEAN NOT NULL DEFAULT TRUE,
  `is_deleted` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  KEY `idx_menu_category` (`category`, `is_deleted`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `EMPLOYEE` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_date` DATE NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `telephone_number` VARCHAR(20) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `national_id` VARCHAR(13) NOT NULL,
  `position` VARCHAR(80) NOT NULL,
  `system_role` ENUM('STAFF','MANAGER') NOT NULL,
  `hire_date` DATE NOT NULL,
  `is_deactivated` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_username` (`username`),
  UNIQUE KEY `uq_employee_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `MEMBER` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_date` DATE NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(80) NOT NULL,
  `last_name` VARCHAR(80) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `telephone_number` VARCHAR(20) NOT NULL,
  `birth_date` DATE NOT NULL,
  `gender` VARCHAR(16) NOT NULL,
  `points` INT UNSIGNED NOT NULL DEFAULT 0,
  `birthday_promotion_used_at` DATE NULL DEFAULT NULL,
  `is_deactivated` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_username` (`username`),
  UNIQUE KEY `uq_member_email` (`email`),
  UNIQUE KEY `uq_member_phone` (`telephone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SESSION` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_number` VARCHAR(32) NOT NULL,
  `table_id` INT UNSIGNED NOT NULL,
  `people_amount` SMALLINT UNSIGNED NOT NULL,
  `buffet_price` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  `need_help` BOOLEAN NOT NULL DEFAULT FALSE,
  `request_bill` BOOLEAN NOT NULL DEFAULT FALSE,
  `total_price` DECIMAL(12,2) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_number` (`session_number`),
  KEY `idx_session_table_time` (`table_id`, `created_at`, `completed_at`),
  KEY `idx_session_completed` (`completed_at`),
  CONSTRAINT `fk_session_table` FOREIGN KEY (`table_id`) REFERENCES `TABLE` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `SESSION_MEMBER` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` INT UNSIGNED NOT NULL,
  `member_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_member` (`session_id`, `member_id`),
  KEY `idx_session_member_member` (`member_id`),
  CONSTRAINT `fk_session_member_session` FOREIGN KEY (`session_id`) REFERENCES `SESSION` (`id`),
  CONSTRAINT `fk_session_member_member` FOREIGN KEY (`member_id`) REFERENCES `MEMBER` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ORDER` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(32) NOT NULL,
  `session_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL,
  `completed_at` DATETIME NULL DEFAULT NULL,
  `status` ENUM('PREPARING','SERVED','CANCELLED') NOT NULL DEFAULT 'PREPARING',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`number`),
  KEY `idx_order_session_status_time` (`session_id`, `status`, `created_at`),
  KEY `idx_order_created_status` (`created_at`, `status`),
  CONSTRAINT `fk_order_session` FOREIGN KEY (`session_id`) REFERENCES `SESSION` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ITEM` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `food_id` INT UNSIGNED NOT NULL,
  `quantity` SMALLINT UNSIGNED NOT NULL,
  `note` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_item_order` (`order_id`),
  KEY `idx_item_food` (`food_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `ORDER` (`id`),
  CONSTRAINT `fk_item_food` FOREIGN KEY (`food_id`) REFERENCES `MENU` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


START TRANSACTION;

INSERT INTO `TABLE` (`id`, `number`, `is_available`) VALUES
(1, 1, FALSE),
(2, 2, FALSE),
(3, 3, FALSE),
(4, 4, FALSE),
(5, 5, FALSE),
(6, 6, FALSE),
(7, 7, FALSE),
(8, 8, FALSE),
(9, 9, FALSE);

INSERT INTO `MENU` (`id`, `name`, `category`, `price`, `image_path`, `is_available`, `is_deleted`) VALUES
(1, 'เนื้อสไลซ์', 'เนื้อ', 0.00, 'images/menu/1.jpg', TRUE, FALSE),
(2, 'เนื้อริบอาย', 'เนื้อ', 0.00, 'images/menu/2.jpg', TRUE, FALSE),
(3, 'เนื้อวากิว', 'เนื้อ', 0.00, 'images/menu/3.jpg', TRUE, FALSE),
(4, 'หมูสไลซ์', 'หมู', 0.00, 'images/menu/4.jpg', TRUE, FALSE),
(5, 'หมูสามชั้น', 'หมู', 0.00, 'images/menu/5.jpg', TRUE, FALSE),
(6, 'คอหมูย่าง', 'หมู', 0.00, 'images/menu/6.jpg', TRUE, FALSE),
(7, 'หมูเกาหลี', 'หมู', 0.00, 'images/menu/7.jpg', TRUE, FALSE),
(8, 'ลูกชิ้นหมู', 'หมู', 0.00, 'images/menu/8.jpg', TRUE, FALSE),
(9, 'ไก่สไลซ์', 'ไก่', 0.00, 'images/menu/9.jpg', TRUE, FALSE),
(10, 'ปีกไก่', 'ไก่', 0.00, 'images/menu/10.jpg', TRUE, FALSE),
(11, 'ลูกชิ้นไก่', 'ไก่', 0.00, 'images/menu/11.jpg', TRUE, FALSE),
(12, 'กุ้ง', 'ทะเล', 0.00, 'images/menu/12.jpg', TRUE, FALSE),
(13, 'ปลาหมึก', 'ทะเล', 0.00, 'images/menu/13.jpg', TRUE, FALSE),
(14, 'หอยแมลงภู่', 'ทะเล', 0.00, 'images/menu/14.jpg', TRUE, FALSE),
(15, 'ปลาแซลมอน', 'ทะเล', 0.00, 'images/menu/15.jpg', TRUE, FALSE),
(16, 'ลูกชิ้นปลา', 'ทะเล', 0.00, 'images/menu/16.jpg', TRUE, FALSE),
(17, 'ผักกาดขาว', 'ผักและเห็ด', 0.00, 'images/menu/17.jpg', TRUE, FALSE),
(18, 'ผักบุ้ง', 'ผักและเห็ด', 0.00, 'images/menu/18.jpg', TRUE, FALSE),
(19, 'ข้าวโพด', 'ผักและเห็ด', 0.00, 'images/menu/19.jpg', TRUE, FALSE),
(20, 'เห็ดเข็มทอง', 'ผักและเห็ด', 0.00, 'images/menu/20.jpg', TRUE, FALSE),
(21, 'เห็ดหอม', 'ผักและเห็ด', 0.00, 'images/menu/21.jpg', TRUE, FALSE),
(22, 'เห็ดนางฟ้า', 'ผักและเห็ด', 0.00, 'images/menu/22.jpg', TRUE, FALSE),
(23, 'บะหมี่', 'เส้นและข้าว', 0.00, 'images/menu/23.jpg', TRUE, FALSE),
(24, 'วุ้นเส้น', 'เส้นและข้าว', 0.00, 'images/menu/24.jpg', TRUE, FALSE),
(25, 'มาม่า', 'เส้นและข้าว', 0.00, 'images/menu/25.jpg', TRUE, FALSE),
(26, 'ข้าวสวย', 'เส้นและข้าว', 0.00, 'images/menu/26.jpg', TRUE, FALSE),
(27, 'น้ำซุปธรรมดา', 'น้ำซุปและน้ำจิ้ม', 0.00, 'images/menu/27.jpg', TRUE, FALSE),
(28, 'น้ำซุปต้มยำ', 'น้ำซุปและน้ำจิ้ม', 0.00, 'images/menu/28.jpg', TRUE, FALSE),
(29, 'น้ำจิ้มสุกี้', 'น้ำซุปและน้ำจิ้ม', 0.00, 'images/menu/29.jpg', TRUE, FALSE),
(30, 'น้ำจิ้มแจ่ว', 'น้ำซุปและน้ำจิ้ม', 0.00, 'images/menu/30.jpg', TRUE, FALSE),
(31, 'ผลไม้ตามฤดูกาล', 'ของหวาน', 0.00, 'images/menu/31.jpg', TRUE, FALSE),
(32, 'เฉาก๊วย', 'ของหวาน', 0.00, 'images/menu/32.jpg', TRUE, FALSE),
(33, 'วุ้นมะพร้าว', 'ของหวาน', 0.00, 'images/menu/33.jpg', TRUE, FALSE),
(34, 'ไอศกรีมวานิลลา', 'ของหวาน', 20.00, 'images/menu/34.jpg', TRUE, FALSE),
(35, 'ไอศกรีมช็อกโกแลต', 'ของหวาน', 20.00, 'images/menu/35.jpg', TRUE, FALSE),
(36, 'ไอศกรีมสตรอว์เบอร์รี', 'ของหวาน', 20.00, 'images/menu/36.jpg', TRUE, FALSE),
(37, 'ไอศกรีมชาเขียว', 'ของหวาน', 25.00, 'images/menu/37.jpg', TRUE, FALSE),
(38, 'บิงซูผลไม้รวม', 'ของหวาน', 89.00, 'images/menu/38.jpg', TRUE, FALSE),
(39, 'โมจิไอศกรีม (3 ชิ้น)', 'ของหวาน', 45.00, 'images/menu/39.jpg', TRUE, FALSE),
(40, 'น้ำเปล่า', 'เครื่องดื่ม', 0.00, 'images/menu/40.jpg', TRUE, FALSE),
(41, 'น้ำอัดลม', 'เครื่องดื่ม', 0.00, 'images/menu/41.jpg', TRUE, FALSE),
(42, 'ชาไทย', 'เครื่องดื่ม', 0.00, 'images/menu/42.jpg', TRUE, FALSE),
(43, 'น้ำผลไม้', 'เครื่องดื่ม', 0.00, 'images/menu/43.jpg', TRUE, FALSE);

INSERT INTO `EMPLOYEE` (`id`, `registration_date`, `username`, `password_hash`, `first_name`, `last_name`, `email`, `telephone_number`, `address`, `national_id`, `position`, `system_role`, `hire_date`, `is_deactivated`) VALUES
(1, '2026-11-01', 'manager', '$2y$10$Ln8SPUaMUPnxvISTHAAVT.zTQIAoxASVuaPfQYBny2UQVd2uPCZKy', 'ผู้จัดการ', 'ตัวอย่าง', 'manager@example.com', '', '', '', 'ผู้จัดการร้าน', 'MANAGER', '2026-11-01', FALSE),
(2, '2026-11-01', 'staff', '$2y$10$Ln8SPUaMUPnxvISTHAAVT.zTQIAoxASVuaPfQYBny2UQVd2uPCZKy', 'พนักงาน', 'ตัวอย่าง', 'staff@example.com', '', '', '', 'พนักงานร้าน', 'STAFF', '2026-11-01', FALSE);

-- Completed visits without promotions: 638.00 + 986.00 + 1241.00 = 2865.00 baht.
INSERT INTO `SESSION` (`id`, `session_number`, `table_id`, `people_amount`, `buffet_price`, `created_at`, `completed_at`, `need_help`, `request_bill`, `total_price`) VALUES
(1, 'T01-0001', 1, 2, 299.00, '2026-11-03 12:00:00', '2026-11-03 13:00:00', FALSE, FALSE, 638.00),
(2, 'T02-0001', 2, 3, 299.00, '2026-11-04 18:00:00', '2026-11-04 19:00:00', FALSE, FALSE, 986.00),
(3, 'T01-0002', 1, 4, 299.00, '2026-11-05 11:00:00', '2026-11-05 12:00:00', FALSE, FALSE, 1241.00);

INSERT INTO `ORDER` (`id`, `number`, `session_id`, `created_at`, `completed_at`, `status`) VALUES
(1, 'ORD-20261103-000001', 1, '2026-11-03 12:05:00', '2026-11-03 12:12:00', 'SERVED'),
(2, 'ORD-20261104-000002', 2, '2026-11-04 18:05:00', '2026-11-04 18:12:00', 'SERVED'),
(3, 'ORD-20261105-000003', 3, '2026-11-05 11:05:00', '2026-11-05 11:12:00', 'SERVED'),
(4, 'ORD-20261105-000004', 3, '2026-11-05 11:10:00', '2026-11-05 11:11:00', 'CANCELLED');

INSERT INTO `ITEM` (`id`, `order_id`, `food_id`, `quantity`, `note`, `price`) VALUES
(1, 1, 1, 2, NULL, 0.00),
(2, 1, 34, 2, NULL, 40.00),
(3, 2, 4, 2, NULL, 0.00),
(4, 2, 38, 1, NULL, 89.00),
(5, 3, 12, 2, NULL, 0.00),
(6, 3, 39, 1, NULL, 45.00),
(7, 4, 38, 1, NULL, 89.00);

COMMIT;
