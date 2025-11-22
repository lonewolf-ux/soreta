-- Add new columns for appointment management
ALTER TABLE appointments
ADD COLUMN IF NOT EXISTS status ENUM('scheduled', 'rescheduled', 'in-progress', 'completed', 'cancelled') DEFAULT 'scheduled',
ADD COLUMN IF NOT EXISTS reschedule_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS last_modified TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS previous_date DATE NULL,
ADD COLUMN IF NOT EXISTS previous_time TIME NULL;

-- Add indices for better performance
CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date);

-- Create notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    related_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Add index for notifications
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(read_at);