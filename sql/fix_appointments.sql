-- Add new columns and update existing columns
ALTER TABLE appointments 
MODIFY COLUMN status ENUM('scheduled', 'rescheduled', 'in-progress', 'completed', 'cancelled') DEFAULT 'scheduled',
ADD COLUMN IF NOT EXISTS customer_id INT NULL,
ADD COLUMN IF NOT EXISTS user_id INT NULL,
ADD COLUMN IF NOT EXISTS reschedule_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_modified TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS previous_date DATE NULL,
ADD COLUMN IF NOT EXISTS previous_time TIME NULL;

-- Update existing appointments to set customer_id from users table
UPDATE appointments a 
JOIN users u ON a.customer_id IS NULL 
SET a.user_id = u.id, 
    a.customer_id = u.id 
WHERE u.role = 'customer';

-- Add foreign key constraints
ALTER TABLE appointments
ADD CONSTRAINT fk_appointment_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_appointment_customer
FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL;