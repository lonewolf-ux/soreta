<?php
require_once '../includes/config.php';
checkAuth();

if (isAdmin()) {
    jsonResponse(false, "Admins cannot cancel appointments from customer interface.");
}

// Support GET-based cancellation (dashboard uses an anchor link with ?id=)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $appointmentId = (int)($_GET['id'] ?? 0);
        if (!$appointmentId) {
            throw new Exception("Invalid appointment ID.");
        }

        $db = new Database();
        $pdo = $db->getConnection();

        // Verify appointment belongs to customer and is scheduled
        $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND customer_id = ? AND status = 'scheduled'");
        $stmt->execute([$appointmentId, $_SESSION['user_id']]);

        if (!$stmt->fetch()) {
            throw new Exception("Appointment not found, already processed, or you don't have permission to cancel it.");
        }

        // Cancel appointment
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$appointmentId]);

        // Try to create notifications for admins (non-fatal)
        try {
            $notificationManager = new NotificationManager($pdo);
            $message = 'An appointment has been cancelled by the customer.';
            $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                $notificationManager->create($admin_id, 'appointment_cancelled', $message, $appointmentId, 'Appointment Cancelled');
            }
        } catch (Exception $e) {
            error_log('Notification error (cancel appointment - GET): ' . $e->getMessage());
        }

        // Redirect back to dashboard (with a simple query message)
        redirect('customer/dashboard.php?msg=' . urlencode('Appointment cancelled successfully.'));
    } catch (Exception $e) {
        redirect('customer/dashboard.php?err=' . urlencode($e->getMessage()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        CSRFProtection::validateToken($input['csrf_token'] ?? '');
        
        $appointmentId = $input['appointment_id'] ?? 0;
        
        if (!$appointmentId) {
            throw new Exception("Invalid appointment ID.");
        }
        
        $db = new Database();
        $pdo = $db->getConnection();
        
        // Verify appointment belongs to customer and is scheduled
        $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND customer_id = ? AND status = 'scheduled'");
        $stmt->execute([$appointmentId, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Appointment not found, already processed, or you don't have permission to cancel it.");
        }
        
        // Cancel appointment
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$appointmentId]);

        // Create notification for admin (non-fatal)
        try {
            $notificationManager = new NotificationManager($pdo);
            $message = 'An appointment has been cancelled by the customer.';
            $adminStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                $notificationManager->create($admin_id, 'appointment_cancelled', $message, $appointmentId, 'Appointment Cancelled');
            }
        } catch (Exception $e) {
            error_log('Notification error (cancel appointment): ' . $e->getMessage());
        }
        
        jsonResponse(true, "Appointment cancelled successfully.");
        
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
}
?>