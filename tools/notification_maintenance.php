<?php
require_once '../includes/config.php';
require_once '../includes/NotificationManager.php';

$db = new Database();
$pdo = $db->getConnection();

$notificationManager = new NotificationManager($pdo);

// Clean up notifications older than 30 days that have been read
$notificationManager->cleanupOldNotifications(30);

// Send reminders for tomorrow's appointments
$notificationManager->sendAppointmentReminders();

echo "Notification maintenance completed successfully.\n";
?>