<?php
require_once '../includes/config.php';
checkAuth();

$db = new Database();
$pdo = $db->getConnection();
$error_message = null;
$success_message = null;

// Get appointment details
if (isset($_GET['id'])) {
    $appointmentId = intval($_GET['id']);
    
    try {
        // Debug info
        error_log("Attempting to fetch appointment ID: " . $appointmentId . " for user ID: " . $_SESSION['user_id']);
        
        // First check if appointment exists and get its status
        $checkStmt = $pdo->prepare("
            SELECT status
            FROM appointments
            WHERE id = ?
            AND customer_id = ?
        ");
        $checkStmt->execute([$appointmentId, $_SESSION['user_id']]);
        $appointmentStatus = $checkStmt->fetch(PDO::FETCH_COLUMN);
        
        if (!$appointmentStatus) {
            throw new Exception("Appointment not found or you don't have permission to access it.");
        } elseif ($appointmentStatus !== 'scheduled') {
            throw new Exception("This appointment cannot be rescheduled because its status is: " . htmlspecialchars($appointmentStatus));
        }
        
        $stmt = $pdo->prepare("
            SELECT a.*, u.name as customer_name
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            WHERE a.id = ?
            AND a.customer_id = ?
            AND a.status = 'scheduled'
        ");
        $stmt->execute([$appointmentId, $_SESSION['user_id']]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            $error_message = "Appointment not found or cannot be rescheduled. Please ensure the appointment is scheduled and try again.";
            error_log("No appointment found for ID: {$appointmentId} and user: {$_SESSION['user_id']}");
        }
    } catch (Exception $e) {
        error_log("Error in reschedule-appointment.php: " . $e->getMessage());
        $error_message = "Error retrieving appointment details. Please try again or contact support.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);
        
        $appointmentId = intval($_POST['appointment_id']);
        $newDate = Security::sanitizeInput($_POST['appointment_date']);
        $newTime = Security::sanitizeInput($_POST['appointment_time']);
        $reason = Security::sanitizeInput($_POST['reschedule_reason'] ?? '');
        
        // Validate new date and time
        if (empty($newDate) || empty($newTime)) {
            throw new Exception('Please select both date and time for rescheduling.');
        }
        
        // Save the current date and time before updating
        $stmt = $pdo->prepare("
            UPDATE appointments SET
                previous_date = appointment_date,
                previous_time = appointment_time,
                appointment_date = ?,
                appointment_time = ?,
                last_modified = NOW(),
                reschedule_reason = ?,
                status = 'rescheduled'
            WHERE id = ?
            AND customer_id = ?
            AND status = 'scheduled'");

        $result = $stmt->execute([$newDate, $newTime, $reason, $appointmentId, $_SESSION['user_id']]);
        
        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception('Unable to reschedule appointment. Please ensure the appointment exists and is still in scheduled status.');
        }
        
        // Create notifications for both customer and admin
        $message = "Appointment has been rescheduled to " . date('F j, Y', strtotime($newDate)) . 
                  " at " . date('g:i A', strtotime($newTime));
        
        // Notify customer and admins using NotificationManager
        try {
            $notificationManager = new NotificationManager($pdo);
            // Customer
            $notificationManager->create($_SESSION['user_id'], 'appointment_rescheduled', $message, $appointmentId, 'Appointment Rescheduled');

            // Admins
            $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
            while ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
                $notificationManager->create($admin['id'], 'appointment_rescheduled', $message, $appointmentId, 'Appointment Rescheduled');
            }
        } catch (Exception $e) {
            error_log('Notification error (reschedule): ' . $e->getMessage());
        }
        
        $_SESSION['success_message'] = "Appointment rescheduled successfully!";
        redirect('dashboard.php');
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - Soreta Electronics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/unified-theme.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-lightning-charge-fill"></i>
                Soreta Electronics
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Hello, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <?php include '../includes/notification-component.php'; ?>
                <a href="../auth/logout.php" class="btn btn-outline-primary btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-center mb-0">Reschedule Appointment</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= htmlspecialchars($error_message) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($appointment)): ?>
                            <div class="current-appointment mb-4">
                                <h5>Current Appointment Details:</h5>
                                <?php if (isset($appointment['appointment_date']) && isset($appointment['appointment_time'])): ?>
                                    <p>
                                        Date: <?= date('F j, Y', strtotime($appointment['appointment_date'])) ?><br>
                                        Time: <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-danger">Appointment details not available. The appointment may have been cancelled or already completed.</p>
                                <?php endif; ?>
                            </div>

                            <form method="POST" id="rescheduleForm">
                                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                                <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="appointment_date" class="form-label">New Date *</label>
                                        <input type="date" class="form-control" id="appointment_date" 
                                               name="appointment_date" required
                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                               max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="appointment_time" class="form-label">New Time *</label>
                                        <select class="form-control" id="appointment_time" name="appointment_time" required>
                                            <option value="">Select time</option>
                                            <option value="09:00">09:00 AM</option>
                                            <option value="09:30">09:30 AM</option>
                                            <option value="10:00">10:00 AM</option>
                                            <option value="10:30">10:30 AM</option>
                                            <option value="11:00">11:00 AM</option>
                                            <option value="11:30">11:30 AM</option>
                                            <option value="13:00">01:00 PM</option>
                                            <option value="13:30">01:30 PM</option>
                                            <option value="14:00">02:00 PM</option>
                                            <option value="14:30">02:30 PM</option>
                                            <option value="15:00">03:00 PM</option>
                                            <option value="15:30">03:30 PM</option>
                                            <option value="16:00">04:00 PM</option>
                                            <option value="16:30">04:30 PM</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label for="reschedule_reason" class="form-label">Reason for Rescheduling</label>
                                        <textarea class="form-control" id="reschedule_reason" name="reschedule_reason" 
                                                  rows="3" placeholder="Please provide a reason for rescheduling..."></textarea>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <a href="dashboard.php" class="btn btn-outline-secondary me-md-2">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-calendar-check"></i> Confirm Reschedule
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Disable weekends in date picker
        document.getElementById('appointment_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const day = selectedDate.getDay();

            if (day === 0 || day === 6) { // Sunday or Saturday
                alert('We are closed on weekends. Please select a weekday.');
                this.value = '';
            }
        });
    </script>
</body>
</html>