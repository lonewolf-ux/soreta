USE `soreta_db`;

-- Insert default admin (password: Admin123!)
INSERT INTO `users` (`email`, `password`, `name`, `contact_number`, `address`, `role`) VALUES
('admin@soreta.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Soreta', '09171234567', 'Manila Office', 'admin');

-- Insert sample customers
INSERT INTO `users` (`email`, `password`, `name`, `contact_number`, `address`) VALUES
('maria.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Santos', '09172345678', '123 Main St, Quezon City'),
('juan.dela@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', '09173456789', '456 Elm St, Makati'),
('ana.reyes@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Reyes', '09174567890', '789 Oak St, Mandaluyong'),
('carlos.lim@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos Lim', '09175678901', '321 Pine St, Pasig'),
('liza.tan@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Liza Tan', '09176789012', '654 Maple St, Taguig');

-- Insert technicians
INSERT INTO `technicians` (`name`, `contact_number`, `specialization`) VALUES
('Michael Techson', '09177890123', 'Laptop Repair & Data Recovery'),
('Sarah Component', '09178901234', 'Mobile Devices & Tablets'),
('David Circuit', '09179012345', 'Desktop PCs & Networking'),
('Jennifer Board', '09170123456', 'Audio/Video Equipment');

-- Insert company settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('company_name', 'A.D. Soreta Electronics Enterprises', 'text'),
('company_address', '123 Electronics St, Manila, Philippines 1000', 'text'),
('company_phone', '(02) 8123-4567', 'text'),
('company_email', 'info@soreta-electronics.com', 'text'),
('company_about', '<p>Since 2005, A.D. Soreta Electronics Enterprises has been providing reliable electronic repair services with quality craftsmanship and excellent customer service.</p>', 'html'),
('services', '["Laptop Repair", "Phone Repair", "Desktop PC Service", "Data Recovery", "Software Installation", "Virus Removal", "Hardware Upgrade"]', 'json'),
('business_hours', '{"monday": "9:00 AM - 6:00 PM", "tuesday": "9:00 AM - 6:00 PM", "wednesday": "9:00 AM - 6:00 PM", "thursday": "9:00 AM - 6:00 PM", "friday": "9:00 AM - 6:00 PM", "saturday": "9:00 AM - 4:00 PM", "sunday": "Closed"}', 'json');

-- Insert troubleshooting guide (decision tree)
INSERT INTO `troubleshooting_guide` (`parent_id`, `title`, `description`, `fix_steps`, `preventive_tip`, `display_order`) VALUES
(NULL, 'Laptop Issues', 'Common laptop problems and solutions', 'Select specific issue below', 'Regular cleaning and updates prevent many laptop issues', 1),
(1, 'Laptop won\'t turn on', 'No power when pressing power button', '1. Check power adapter connection\n2. Try different power outlet\n3. Remove battery and connect directly via adapter\n4. Check for LED indicators\n5. If still not working, bring for professional diagnosis', 'Use surge protector and avoid draining battery completely', 1),
(1, 'Laptop overheating', 'Fan noise, slow performance, automatic shutdown', '1. Clean ventilation ports\n2. Use on hard surface (not on bed/cushions)\n3. Check task manager for resource-heavy apps\n4. Update BIOS and drivers\n5. Consider professional thermal paste replacement', 'Clean vents monthly, use cooling pad for intensive tasks', 2),
(1, 'Slow performance', 'Takes long to boot, programs lag', '1. Run disk cleanup\n2. Check for malware/viruses\n3. Upgrade RAM if possible\n4. Defragment HDD (if not SSD)\n5. Clean install operating system if severe', 'Regular maintenance, avoid installing unnecessary software', 3),

(NULL, 'Phone Issues', 'Mobile device problems', 'Select specific phone issue below', 'Use protective case and avoid extreme temperatures', 2),
(5, 'Phone not charging', 'No charging indicator, battery drains', '1. Try different charger and cable\n2. Clean charging port gently\n3. Restart phone\n4. Check for software updates\n5. If hardware issue, professional repair needed', 'Use original chargers, avoid charging overnight', 1),
(5, 'Cracked screen', 'Physical damage to display', '1. Stop using to prevent further damage\n2. Backup data immediately\n3. Use screen protector on new screen\n4. Professional replacement recommended', 'Use tempered glass protector and protective case', 2),
(5, 'Poor battery life', 'Battery drains quickly', '1. Check battery usage in settings\n2. Reduce screen brightness\n3. Turn off unnecessary connectivity (Bluetooth, GPS)\n4. Update to latest OS\n5. Consider battery replacement if old device', 'Avoid extreme temperatures, charge between 20-80%', 3),

(NULL, 'Desktop PC Issues', 'Desktop computer problems', 'Select specific desktop issue', 'Keep system updated and clean internally annually', 3),
(9, 'No display', 'Computer on but no screen output', '1. Check monitor power and cables\n2. Try different monitor/port\n3. Reseat RAM and graphics card\n4. Check motherboard beep codes\n5. Test with known working components', 'Secure all connections during setup', 1),
(9, 'Blue screen errors', 'Windows blue screen crashes', '1. Note error code\n2. Boot in safe mode\n3. Run system file checker (sfc /scannow)\n4. Update drivers\n5. Check hardware components', 'Keep drivers updated, maintain adequate cooling', 2);

-- Insert sample appointments
INSERT INTO `appointments` (`job_order_no`, `customer_id`, `service_type`, `product_details`, `trouble_description`, `appointment_date`, `appointment_time`, `status`, `payment_status`, `technician_id`, `accessories`) VALUES
('JO-20241201-0001', 2, 'Laptop Repair', 'ASUS Vivobook X515, 2 years old', 'Overheating and automatic shutdown when gaming', '2024-12-02', '09:00:00', 'completed', 'paid', 1, 'Charger, original box'),
('JO-20241201-0002', 3, 'Phone Repair', 'Samsung Galaxy S21, black', 'Cracked screen from drop accident', '2024-12-02', '10:30:00', 'in-progress', 'unpaid', 2, 'None'),
('JO-20241201-0003', 4, 'Data Recovery', 'WD 1TB External HDD', 'Not detected, clicking sound', '2024-12-02', '13:00:00', 'scheduled', 'unpaid', 1, 'USB cable'),
('JO-20241201-0004', 5, 'Software Installation', 'Custom built desktop PC', 'Windows 11 clean install with drivers', '2024-12-03', '09:00:00', 'scheduled', 'unpaid', 3, 'Monitor, keyboard, mouse'),
('JO-20241201-0005', 6, 'Laptop Repair', 'Acer Aspire 5, 1 year old', 'Keyboard not working, some keys unresponsive', '2024-12-03', '11:00:00', 'scheduled', 'unpaid', 1, 'Charger only'),
('JO-20241202-0001', 2, 'Virus Removal', 'Lenovo Ideapad 3', 'Many popups, browser redirects, slow performance', '2024-12-04', '14:00:00', 'scheduled', 'unpaid', NULL, 'Charger'),
('JO-20241202-0002', 3, 'Hardware Upgrade', 'Dell Inspiron 15', 'RAM upgrade from 8GB to 16GB', '2024-12-05', '10:00:00', 'scheduled', 'unpaid', 3, 'Original RAM sticks'),
('JO-20241202-0003', 4, 'Phone Repair', 'iPhone 13 Pro', 'Battery draining too fast', '2024-12-05', '15:30:00', 'scheduled', 'unpaid', 2, 'Charging cable');

-- Insert sample feedback
INSERT INTO `feedback` (`troubleshooting_guide_id`, `customer_id`, `rating`, `comment`) VALUES
(2, 2, 5, 'The steps helped me identify it was a faulty power adapter. Saved me a trip to the shop!'),
(3, 3, 4, 'Cleaning the vents worked perfectly. My laptop no longer overheats.'),
(6, 4, 3, 'Fixed my charging issue but had to buy a new cable as suggested.'),
(7, 5, 5, 'Very accurate diagnosis. I followed the prevention tips for my new screen.');

-- Insert admin preferences
INSERT INTO `user_preferences` (`user_id`, `preferences`) VALUES
(1, '{"sidebar_collapsed": false, "theme": "light", "notifications_enabled": true}');