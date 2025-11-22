-- Soreta Electronics Enterprises Database Schema
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

CREATE DATABASE IF NOT EXISTS `soreta_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `soreta_db`;

-- Table: users (admins + customers)
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20) NOT NULL,
  `address` TEXT NOT NULL,
  `role` ENUM('admin', 'customer') DEFAULT 'customer',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: technicians (reference only, not users)
CREATE TABLE `technicians` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20),
  `specialization` VARCHAR(255),
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: appointments (serves as shop logbook)
CREATE TABLE `appointments` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `job_order_no` VARCHAR(20) UNIQUE NOT NULL,
  `customer_id` INT NOT NULL,
  `service_type` VARCHAR(255) NOT NULL,
  `product_details` TEXT NOT NULL,
  `trouble_description` TEXT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `appointment_time` TIME NOT NULL,
  `status` ENUM('scheduled', 'in-progress', 'completed', 'cancelled') DEFAULT 'scheduled',
  `payment_status` ENUM('paid', 'unpaid') DEFAULT 'unpaid',
  `technician_id` INT NULL,
  `accessories` TEXT,
  `admin_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`technician_id`) REFERENCES `technicians`(`id`)
);

-- Table: troubleshooting_guide (decision tree structure)
CREATE TABLE `troubleshooting_guide` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `parent_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `fix_steps` TEXT NOT NULL,
  `preventive_tip` TEXT,
  `image_path` VARCHAR(500),
  `display_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `troubleshooting_guide`(`id`)
);

-- Table: feedback
CREATE TABLE `feedback` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `troubleshooting_guide_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `rating` INT CHECK (rating >= 1 AND rating <= 5),
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`troubleshooting_guide_id`) REFERENCES `troubleshooting_guide`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`)
);

-- Table: settings (editable content)
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `setting_key` VARCHAR(255) UNIQUE NOT NULL,
  `setting_value` TEXT NOT NULL,
  `setting_type` ENUM('text', 'html', 'json') DEFAULT 'text',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: notifications
CREATE TABLE `notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `related_type` ENUM('appointment', 'feedback', 'system') DEFAULT 'system',
  `related_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Table: user_preferences (for sidebar state, etc.)
CREATE TABLE `user_preferences` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT UNIQUE NOT NULL,
  `preferences` JSON DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

COMMIT;