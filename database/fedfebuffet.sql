-- Fedfe Buffet: fresh installation for InfinityFree / MariaDB.
-- Select an EMPTY database in phpMyAdmin, then import this file once.
-- Six application tables, nine closed dining tables, and two demo accounts.
-- MENU, SESSION, ORDER and ITEM start empty. Import menu.json as manager.
-- Both manager and staff use the initial password: FedfeDemo2026!
-- Menu photos are images/menu/<id>.jpg. No image_path column is required.
-- Six or more guests receive exactly one free buffet admission per visit.

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

CREATE TABLE `SESSION` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_number` VARCHAR(32) NOT NULL,
  `table_id` INT UNSIGNED NOT NULL,
  `people_amount` SMALLINT UNSIGNED NOT NULL,
  `free_guests` TINYINT UNSIGNED NOT NULL DEFAULT 0,
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

INSERT INTO `EMPLOYEE` (`id`, `registration_date`, `username`, `password_hash`, `first_name`, `last_name`, `email`, `telephone_number`, `address`, `national_id`, `position`, `system_role`, `hire_date`, `is_deactivated`) VALUES
(1, '2026-11-01', 'manager', '$2y$10$Ln8SPUaMUPnxvISTHAAVT.zTQIAoxASVuaPfQYBny2UQVd2uPCZKy', 'ผู้จัดการ', 'ตัวอย่าง', 'manager@example.com', '', '', '', 'ผู้จัดการร้าน', 'MANAGER', '2026-11-01', FALSE),
(2, '2026-11-01', 'staff', '$2y$10$Ln8SPUaMUPnxvISTHAAVT.zTQIAoxASVuaPfQYBny2UQVd2uPCZKy', 'พนักงาน', 'ตัวอย่าง', 'staff@example.com', '', '', '', 'พนักงานร้าน', 'STAFF', '2026-11-01', FALSE);

COMMIT;
