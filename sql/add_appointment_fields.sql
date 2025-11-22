-- Add new fields to appointments table for enhanced product details
-- Note: Run these ALTER statements one by one if some columns already exist

-- Add brand column if it doesn't exist
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `brand` VARCHAR(255) DEFAULT NULL AFTER `product_details`;

-- Add product column if it doesn't exist
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `product` VARCHAR(255) DEFAULT NULL AFTER `brand`;

-- Add model_number column if it doesn't exist
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `model_number` VARCHAR(255) DEFAULT NULL AFTER `product`;

-- Add serial_number column if it doesn't exist
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `serial_number` VARCHAR(255) DEFAULT NULL AFTER `model_number`;

-- Add accessories column if it doesn't exist
ALTER TABLE `appointments` ADD COLUMN IF NOT EXISTS `accessories` TEXT DEFAULT NULL AFTER `serial_number`;
