<?php
class NotificationManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Create a new notification
     * Backwards compatible: older code may pass (userId, title, message, relatedId) or (userId, type, message, relatedId)
     * Preferred signature: create($userId, $type, $message, $relatedId = null, $title = null)
     */
    public function create($userId, $typeOrTitle, $message, $relatedId = null, $title = null) {
        // If caller passed title as third param (legacy), detect: if $title is null and $typeOrTitle looks like a human title
        // We won't try to perfectly detect; prefer that callers use the new signature. We'll support both simple cases.

        // If $title is null and $typeOrTitle contains spaces (likely a title), swap
        if ($title === null && is_string($typeOrTitle) && strpos($typeOrTitle, ' ') !== false) {
            $title = $typeOrTitle;
            $type = null;
        } else {
            $type = $typeOrTitle;
        }

        // Detect available columns and build dynamic insert to be robust across environments
        $colsStmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
        $colsStmt->execute();
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $available = array_flip($cols);

        $insertCols = ['user_id'];
        $placeholders = ['?'];
        $values = [$userId];

        if (isset($available['type']) && $type !== null) {
            $insertCols[] = 'type'; $placeholders[] = '?'; $values[] = $type;
        }

        if (isset($available['title']) && $title !== null) {
            $insertCols[] = 'title'; $placeholders[] = '?'; $values[] = $title;
        }

        if (isset($available['message'])) {
            $insertCols[] = 'message'; $placeholders[] = '?'; $values[] = $message;
        }

        // related_type can be set by caller via $type (if type looks like 'appointment_*' we can use related_type='appointment')
        if (isset($available['related_type'])) {
            $relatedType = null;
            if ($type && strpos($type, 'appointment') !== false) $relatedType = 'appointment';
            if ($type && strpos($type, 'feedback') !== false) $relatedType = 'feedback';
            if ($relatedType !== null) { $insertCols[] = 'related_type'; $placeholders[] = '?'; $values[] = $relatedType; }
        }

        if (isset($available['related_id'])) {
            $insertCols[] = 'related_id'; $placeholders[] = '?'; $values[] = $relatedId;
        }

        // Support both is_read and read_at fields
        if (isset($available['is_read'])) {
            $insertCols[] = 'is_read'; $placeholders[] = '?'; $values[] = 0;
        } elseif (isset($available['read_at'])) {
            // leave read_at NULL by not including
        }

        // created_at default is handled by DB if column exists

        $sql = "INSERT INTO notifications (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnread($userId) {
        $stmt = $this->pdo->prepare("
            SELECT n.*, 
                   a.appointment_date, 
                   a.appointment_time,
                   a.service_type
            FROM notifications n
            LEFT JOIN appointments a ON n.related_id = a.id
            WHERE n.user_id = ? 
            AND n.read_at IS NULL
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark notifications as read
     */
    public function markAsRead($notificationIds, $userId) {
        if (empty($notificationIds)) return true;
        
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET read_at = NOW() 
            WHERE id IN ($placeholders) 
            AND user_id = ?
        ");
        $params = array_merge($notificationIds, [$userId]);
        return $stmt->execute($params);
    }

    /**
     * Create an appointment notification
     */
    public function createAppointmentNotification($userId, $appointment, $type) {
        $messages = [
            'booked' => 'Your appointment has been booked for {date} at {time}.',
            'rescheduled' => 'Your appointment has been rescheduled to {date} at {time}.',
            'cancelled' => 'Your appointment for {date} at {time} has been cancelled.',
            'reminder' => 'Reminder: Your appointment is tomorrow at {time}.',
            'confirmed' => 'Your appointment for {date} at {time} has been confirmed.',
            'completed' => 'Your service appointment has been completed. Please provide feedback on your experience.'
        ];

        if (!isset($messages[$type])) {
            throw new Exception('Invalid notification type');
        }

        $date = date('F j, Y', strtotime($appointment['appointment_date']));
        $time = date('g:i A', strtotime($appointment['appointment_time']));
        $message = str_replace(['{date}', '{time}'], [$date, $time], $messages[$type]);

        return $this->create($userId, "appointment_$type", $message, $appointment['id']);
    }

    /**
     * Send appointment reminders
     */
    public function sendAppointmentReminders() {
        // Get appointments for tomorrow
        $stmt = $this->pdo->prepare("
            SELECT * FROM appointments 
            WHERE appointment_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND status = 'scheduled'
        ");
        $stmt->execute();
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($appointments as $appointment) {
            $this->createAppointmentNotification(
                $appointment['user_id'],
                $appointment,
                'reminder'
            );
        }
    }

    /**
     * Get all notifications for a user
     */
    public function getUserNotifications($userId, $limit = 50) {
        try {
            // Detect available columns
            $colsStmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
            $colsStmt->execute();
            $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $available = array_flip($cols);

            $selectCols = ['id', 'user_id', 'created_at'];

            if (isset($available['title'])) $selectCols[] = 'title';
            if (isset($available['message'])) $selectCols[] = 'message';
            if (isset($available['type'])) $selectCols[] = 'type';
            if (isset($available['related_id'])) $selectCols[] = 'related_id';
            if (isset($available['appointment_id'])) $selectCols[] = 'appointment_id';
            if (isset($available['is_read'])) $selectCols[] = 'is_read';
            if (isset($available['read_at'])) $selectCols[] = 'read_at';

            $sql = "SELECT " . implode(', ', $selectCols) . " FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        try {
            // Detect available columns
            $colsStmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
            $colsStmt->execute();
            $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $available = array_flip($cols);

            if (isset($available['is_read'])) {
                $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            } elseif (isset($available['read_at'])) {
                $stmt = $this->pdo->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
            } else {
                return false; // No read tracking column found
            }

            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications($daysOld = 30) {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND read_at IS NOT NULL
        ");
        return $stmt->execute([$daysOld]);
    }
}
?>