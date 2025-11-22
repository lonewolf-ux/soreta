-- Security-related tables and modifications for Soreta Electronics

USE `soreta_db`;

-- Table: login_attempts (for brute force protection)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_time` (`email`, `attempt_time`)
);

-- Add remember me functionality columns to users table
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `remember_token` VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS `token_expiry` DATETIME NULL,
ADD INDEX `idx_remember_token` (`remember_token`);
