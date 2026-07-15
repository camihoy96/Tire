-- Tire Management System Database Backup
-- Generated: 2026-06-08 12:59:30
-- Database: tire

SET FOREIGN_KEY_CHECKS=0;

-- Table structure for table `audit_log`
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_audit_date` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `categories`
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `cname` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_category_active` (`is_active`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `categories`
INSERT INTO `categories` (`category_id`, `cname`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES ('1', 'Car', 'This is for car', '1', NULL, '2026-04-28 20:16:58', '2026-05-31 14:21:50');
INSERT INTO `categories` (`category_id`, `cname`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES ('2', 'Motorcycle', 'for motorcyle', '1', '10', '2026-05-31 14:24:23', '2026-05-31 14:24:23');
INSERT INTO `categories` (`category_id`, `cname`, `description`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES ('3', 'Bycicle', 'for bike only', '1', '10', '2026-05-31 14:25:12', '2026-05-31 14:25:12');

-- Table structure for table `customers`
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `expiry_notifications`
DROP TABLE IF EXISTS `expiry_notifications`;
CREATE TABLE `expiry_notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `notification_type` enum('expiry_soon','expired','inspection_due') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `expiry_notifications_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `expiry_settings`
DROP TABLE IF EXISTS `expiry_settings`;
CREATE TABLE `expiry_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `default_notification_days` int(11) DEFAULT 90,
  `email_notifications` tinyint(1) DEFAULT 1,
  `dashboard_notifications` tinyint(1) DEFAULT 1,
  `last_checked` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `expiry_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `expiry_settings`
INSERT INTO `expiry_settings` (`setting_id`, `default_notification_days`, `email_notifications`, `dashboard_notifications`, `last_checked`, `updated_by`, `updated_at`) VALUES ('1', '90', '1', '1', NULL, NULL, '2026-05-26 15:50:16');

-- Table structure for table `inventory_log`
DROP TABLE IF EXISTS `inventory_log`;
CREATE TABLE `inventory_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('add','remove','adjust','sale') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `previous_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_log_date` (`created_at`),
  CONSTRAINT `inventory_log_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `payment_methods`
DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `method_id` int(11) NOT NULL AUTO_INCREMENT,
  `method_name` varchar(50) NOT NULL,
  `method_code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`method_id`),
  UNIQUE KEY `method_code` (`method_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `products`
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `tire_size` varchar(50) DEFAULT NULL,
  `vehicle_type` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `min_quantity` int(11) DEFAULT 10,
  `category_id` int(11) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `manufacturing_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `expiry_notification_days` int(11) DEFAULT 90,
  `batch_number` varchar(50) DEFAULT NULL,
  `last_inspection_date` date DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `category_id` (`category_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_product_active` (`is_active`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_quantity` (`quantity`),
  KEY `idx_expiration_date` (`expiration_date`),
  KEY `idx_manufacturing_date` (`manufacturing_date`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `products`
INSERT INTO `products` (`product_id`, `name`, `barcode`, `description`, `tire_size`, `vehicle_type`, `price`, `cost_price`, `quantity`, `min_quantity`, `category_id`, `supplier`, `location`, `is_active`, `created_by`, `created_at`, `updated_at`, `manufacturing_date`, `expiration_date`, `expiry_notification_days`, `batch_number`, `last_inspection_date`) VALUES ('4', 'Black Tire', '0551225411', 'for SUV pick up only', '175/50', 'SUV / Crossover', '1500.00', '160000.00', '46', '10', '1', 'APPLE INK TRADING', 'Cab 1', '1', '10', '2026-05-31 15:46:26', '2026-05-31 17:36:12', '2025-07-10', '2029-06-28', '90', '212511', '2026-05-31');

-- Table structure for table `sales`
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) DEFAULT 0.00,
  `sold_by` int(11) DEFAULT NULL,
  `sale_date` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`sale_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_sale_date` (`sale_date`),
  KEY `idx_sold_by` (`sold_by`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`sold_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `sales`
INSERT INTO `sales` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `profit`, `sold_by`, `sale_date`, `notes`) VALUES ('7', '4', '1', '1500.00', '1500.00', '-158500.00', '10', '2026-05-31 16:09:28', 'POS Receipt #RCPT-20260531-89913C | Payment: cash');
INSERT INTO `sales` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `profit`, `sold_by`, `sale_date`, `notes`) VALUES ('8', '4', '1', '1500.00', '1500.00', '-158500.00', '10', '2026-05-31 16:24:13', 'POS Receipt #RCPT-20260531-D46848 | Payment: cash');
INSERT INTO `sales` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `profit`, `sold_by`, `sale_date`, `notes`) VALUES ('9', '4', '1', '1500.00', '1500.00', '-158500.00', '10', '2026-05-31 16:28:58', 'POS Receipt #RCPT-20260531-A9AEC1 | Payment: cash');
INSERT INTO `sales` (`sale_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `profit`, `sold_by`, `sale_date`, `notes`) VALUES ('10', '4', '1', '1500.00', '1500.00', '-158500.00', '9', '2026-05-31 17:36:12', 'POS Receipt #RCPT-20260531-C30FB9 | Payment: cash');

-- Table structure for table `sessions`
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `sessions`
INSERT INTO `sessions` (`session_id`, `user_id`, `ip_address`, `user_agent`, `last_activity`, `expires_at`) VALUES ('e98b173d0b70948b1a1001f9cb85b9617fabdcdd281fe78c4370030eebf0b6f0', '10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-06-08 12:59:30', '2026-06-08 20:59:30');

-- Table structure for table `system_settings`
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','textarea') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings`
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('1', 'company_name', 'TireTrack Pro', 'text', 'Company/Business Name', '2026-05-26 17:29:38', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('2', 'company_logo', 'http://localhost/tire/uploads/logos/logo_1780216455.png', 'text', 'Company Logo URL', '2026-05-31 16:34:15', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('3', 'company_email', 'admin@example.com', 'text', 'Company Email Address', '2026-05-26 17:29:38', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('4', 'company_phone', '+63 XXX XXX XXXX', 'text', 'Company Phone Number', '2026-05-26 17:29:38', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('5', 'company_address', 'Manila, Philippines', 'textarea', 'Company Address', '2026-05-26 17:29:38', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('6', 'low_stock_threshold', '10', 'number', 'Low stock alert threshold (quantity)', '2026-05-26 17:29:52', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('7', 'expiry_alert_days', '10', 'number', 'Days before expiry to show alerts', '2026-05-26 17:29:52', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('8', 'currency_symbol', '₱', 'text', 'Currency Symbol', '2026-05-26 17:29:52', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('9', 'date_format', 'Y-m-d', 'text', 'Date Format', '2026-05-26 16:54:41', NULL);
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('10', 'timezone', 'Asia/Manila', 'text', 'System Timezone', '2026-05-26 16:54:41', NULL);
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('11', 'smtp_host', '', 'text', 'SMTP Server for Email', '2026-05-26 16:54:41', NULL);
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('12', 'smtp_port', '587', 'number', 'SMTP Port', '2026-05-26 16:54:41', NULL);
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('13', 'smtp_user', '', 'text', 'SMTP Username', '2026-05-26 16:54:41', NULL);
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('14', 'smtp_pass', '', 'text', 'SMTP Password', '2026-05-26 16:54:41', NULL);
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('15', 'enable_notifications', '1', 'boolean', 'Enable Email Notifications', '2026-05-26 17:30:00', '10');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`, `updated_by`) VALUES ('16', 'maintenance_mode', '0', 'boolean', 'Maintenance Mode', '2026-05-26 16:54:41', NULL);

-- Table structure for table `transaction_items`
DROP TABLE IF EXISTS `transaction_items`;
CREATE TABLE `transaction_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`),
  CONSTRAINT `transaction_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `transactions`
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cash','card','mobile','credit') DEFAULT 'cash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('completed','voided','refunded') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  KEY `customer_id` (`customer_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('admin','manager','staff') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`user_id`, `fullname`, `username`, `email`, `password_hash`, `user_type`, `is_active`, `last_login`, `login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES ('9', 'Sample User', 'user', 'user@gmail.com', '$2y$12$MKP7wO6kLPwcud2vdFg/yuj.JEaoRGGkw64S6s9QcdVBVoLml0Dsq', 'staff', '1', '2026-05-31 17:51:18', '0', NULL, '2026-04-28 21:17:30', '2026-05-31 17:51:18');
INSERT INTO `users` (`user_id`, `fullname`, `username`, `email`, `password_hash`, `user_type`, `is_active`, `last_login`, `login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES ('10', 'Administrator', 'admin', 'admin@tire.com', '$2y$10$WFIT8sFzNGRyFBzA.H3VjeXotVEA/fWeO9ZSiQa4mH8GgpXxFQbau', 'admin', '1', '2026-06-08 09:52:02', '0', NULL, '2026-04-28 21:25:57', '2026-06-08 09:52:02');

-- Table structure for table `vehicle_types`
DROP TABLE IF EXISTS `vehicle_types`;
CREATE TABLE `vehicle_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT '?',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `vehicle_types`
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'Car / Sedan', '🚗', 'Standard passenger cars and sedans', '1', '2026-05-29 17:05:05', '2026-05-29 17:05:05');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'SUV / Crossover', '🚗', 'Sport Utility Vehicles and Crossovers', '1', '2026-05-29 17:05:05', '2026-05-31 14:25:40');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'Truck / Pickup', '🚗', 'Light trucks and pickup trucks', '1', '2026-05-29 17:05:05', '2026-05-31 14:25:57');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'Van / MPV', '🚗', 'Vans and Multi-Purpose Vehicles', '1', '2026-05-29 17:05:05', '2026-05-31 14:26:09');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('5', 'Motorcycle', '🏍️', 'Motorcycles and two-wheelers', '1', '2026-05-29 17:05:05', '2026-05-29 17:05:05');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('6', 'Bicycle', '📂', 'Bicycles and e-bikes', '1', '2026-05-29 17:05:05', '2026-05-31 14:25:22');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('7', 'Bus', '🚗', 'Buses and coaches', '1', '2026-05-29 17:05:05', '2026-05-31 14:24:37');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('8', 'ATV / Off-road', '🚗', 'All-Terrain Vehicles and off-road vehicles', '1', '2026-05-29 17:05:05', '2026-05-31 14:22:55');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('9', 'Tractor', '🚗', 'Tractors and heavy equipment', '1', '2026-05-29 17:05:05', '2026-05-31 14:25:30');
INSERT INTO `vehicle_types` (`id`, `name`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES ('10', 'Scooter', '🏍️', 'Scooters and mopeds', '1', '2026-05-29 17:05:05', '2026-05-31 14:24:51');

SET FOREIGN_KEY_CHECKS=1;
