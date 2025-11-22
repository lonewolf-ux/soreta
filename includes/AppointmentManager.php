<?php
/**
 * Appointment Management Class
 * Handles all appointment-related operations
 */
class AppointmentManager {
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function createAppointment($customerId, array $data) {
        try {
            $this->pdo->beginTransaction();

            // Generate job order number
            $jobOrderNo = Security::generateJobOrderNo($this->pdo);

            // Get form data
            $serviceType = trim(Security::sanitizeInput($data['service_type'] ?? ''));
            $appointmentDate = $data['appointment_date'] ?? null;
            $appointmentTime = $data['appointment_time'] ?? null;
            $brand = trim(Security::sanitizeInput($data['brand'] ?? ''));
            $product = trim(Security::sanitizeInput($data['product'] ?? ''));
            $modelNumber = trim(Security::sanitizeInput($data['model_number'] ?? ''));
            $serial = trim(Security::sanitizeInput($data['serial'] ?? ''));
            $accessories = trim(Security::sanitizeInput($data['accessories'] ?? ''));
            $troubleDescription = trim(Security::sanitizeInput($data['trouble_description'] ?? ''));

            // Basic validation: only require date and time
            if (empty($appointmentDate)) {
                throw new Exception('Please choose a preferred appointment date.');
            }
            if (empty($appointmentTime)) {
                throw new Exception('Please choose a preferred appointment time.');
            }

            // Validate date/time formats
            $d = DateTime::createFromFormat('Y-m-d', $appointmentDate);
            if (!$d || $d->format('Y-m-d') !== $appointmentDate) {
                throw new Exception('Invalid appointment date format.');
            }
            $t = DateTime::createFromFormat('H:i', $appointmentTime);
            if (!$t) {
                // allow H:i:s too
                $t = DateTime::createFromFormat('H:i:s', $appointmentTime);
                if (!$t) {
                    throw new Exception('Invalid appointment time format.');
                }
            }

            // Build product_details from brand and model
            $productDetails = trim($brand . ' ' . $modelNumber);

            $stmt = $this->pdo->prepare(
                "INSERT INTO appointments
                (job_order_no, customer_id, service_type, product_details, trouble_description,
                 appointment_date, appointment_time, accessories, brand, product, model_number, serial_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $jobOrderNo,
                $customerId,
                $serviceType,
                $productDetails, // Combined brand + model
                $troubleDescription,
                $appointmentDate,
                $appointmentTime,
                $accessories,
                $brand,
                $product,
                $modelNumber,
                $serial
            ]);

            $appointmentId = $this->pdo->lastInsertId();

            // Create notification for the customer (confirmation)
            try {
                $notificationManager = new NotificationManager($this->pdo);
                $stmtApp = $this->pdo->prepare("SELECT * FROM appointments WHERE id = ?");
                $stmtApp->execute([$appointmentId]);
                $appointmentRow = $stmtApp->fetch(PDO::FETCH_ASSOC);
                if ($appointmentRow) {
                    // create a booked confirmation for the customer
                    $notificationManager->createAppointmentNotification($customerId, $appointmentRow, 'booked');
                }
            } catch (Exception $e) {
                // Non-fatal: notification failure should not block appointment creation
                // Log if available; otherwise continue
            }

            // Create notification for admin
            $this->createNewAppointmentNotification($appointmentId);

            $this->pdo->commit();

            return [
                'success' => true,
                'job_order_no' => $jobOrderNo,
                'appointment_id' => $appointmentId
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Failed to create appointment: " . $e->getMessage());
        }
    }

    public function getCustomerAppointments(int $customerId) {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, t.name as technician_name 
            FROM appointments a 
            LEFT JOIN technicians t ON a.technician_id = t.id 
            WHERE a.customer_id = ? 
            ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public function getAllAppointments(array $filters = []) {
        $sql = "SELECT a.*, u.name as customer_name, u.contact_number, u.address as customer_address, t.name as technician_name 
            FROM appointments a 
            JOIN users u ON a.customer_id = u.id 
            LEFT JOIN technicians t ON a.technician_id = t.id 
            WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['technician_id'])) {
            $sql .= " AND a.technician_id = ?";
            $params[] = $filters['technician_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND a.appointment_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND a.appointment_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (a.job_order_no LIKE ? OR u.name LIKE ? OR a.product_details LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateAppointment(int $appointmentId, array $data) {
        // Fetch current appointment to check for status change
        $currentStmt = $this->pdo->prepare("SELECT status, customer_id FROM appointments WHERE id = ?");
        $currentStmt->execute([$appointmentId]);
        $currentAppointment = $currentStmt->fetch();
        if (!$currentAppointment) {
            throw new Exception("Appointment not found.");
        }
        $oldStatus = $currentAppointment['status'];
        $customerId = $currentAppointment['customer_id'];

        $allowedFields = [
            'service_type', 'product_details', 'trouble_description',
            'appointment_date', 'appointment_time', 'status',
            'payment_status', 'technician_id', 'accessories', 'admin_notes',
            'brand', 'product', 'model_number', 'serial_number'
        ];

        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                if ($field === 'technician_id' && empty($value)) {
                    $updates[] = "technician_id = NULL";
                } else {
                    $updates[] = "$field = ?";
                    $params[] = Security::sanitizeInput($value);
                }
            }
        }

        if (empty($updates)) {
            throw new Exception("No valid fields to update.");
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $appointmentId;

        $sql = "UPDATE appointments SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            // Check if status changed to in-progress or completed
            $newStatus = $data['status'] ?? $oldStatus;
            if ($newStatus !== $oldStatus && ($newStatus === 'in-progress' || $newStatus === 'completed')) {
                $this->createStatusUpdateNotification($appointmentId, $newStatus, $customerId);
            }
        }

        return $success;
    }

    public function cancelAppointment(int $appointmentId, ?int $customerId = null) {
        $sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $params = [$appointmentId];

        if ($customerId) {
            $sql .= " AND customer_id = ?";
            $params[] = $customerId;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    private function createNewAppointmentNotification($appointmentId) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();

        // Detect available columns in notifications table so we don't reference missing columns
        $colsStmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
        $colsStmt->execute();
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $available = array_flip($cols);

        // Prepare dynamic insert based on available columns
        $baseCols = ['user_id', 'type', 'message'];
        $params = [];

        foreach ($admins as $admin) {
            $insertCols = $baseCols;
            $insertPlaceholders = array_fill(0, count($insertCols), '?');
            $values = [$admin['id'], 'new_appointment', 'A new appointment has been booked.'];

            // include related_type if present
            if (isset($available['related_type'])) {
                $insertCols[] = 'related_type';
                $insertPlaceholders[] = '?';
                $values[] = 'appointment';
            }

            // include related_id if present
            if (isset($available['related_id'])) {
                $insertCols[] = 'related_id';
                $insertPlaceholders[] = '?';
                $values[] = $appointmentId;
            }

            // include is_read if present (default false)
            if (isset($available['is_read']) && !in_array('is_read', $insertCols)) {
                $insertCols[] = 'is_read';
                $insertPlaceholders[] = '?';
                $values[] = 0;
            }

            $sql = "INSERT INTO notifications (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            $ins = $this->pdo->prepare($sql);
            $ins->execute($values);
        }
    }

    private function createStatusUpdateNotification($appointmentId, $newStatus, $customerId) {
        // Detect available columns
        $colsStmt = $this->pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'");
        $colsStmt->execute();
        $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $available = array_flip($cols);

        $type = "status_update";
        $message = "Your appointment has been updated to '" . ucfirst($newStatus) . "'.";
        $relatedType = 'appointment';

        // Notify customer
        $insertCols = ['user_id', 'type', 'message'];
        $insertPlaceholders = array_fill(0, count($insertCols), '?');
        $values = [$customerId, $type, $message];

        if (isset($available['related_type'])) {
            $insertCols[] = 'related_type';
            $insertPlaceholders[] = '?';
            $values[] = $relatedType;
        }

        if (isset($available['related_id'])) {
            $insertCols[] = 'related_id';
            $insertPlaceholders[] = '?';
            $values[] = $appointmentId;
        }

        if (isset($available['is_read'])) {
            $insertCols[] = 'is_read';
            $insertPlaceholders[] = '?';
            $values[] = 0;
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
        $ins = $this->pdo->prepare($sql);
        $ins->execute($values);

        // If in-progress, also notify assigned technician if any
        if ($newStatus === 'in-progress') {
            $techStmt = $this->pdo->prepare("SELECT technician_id FROM appointments WHERE id = ?");
            $techStmt->execute([$appointmentId]);
            $tech = $techStmt->fetch();
            if ($tech && $tech['technician_id']) {
                $techType = "new_assignment";
                $techMessage = "You have been assigned to an appointment.";
                $values[0] = $tech['technician_id']; // user_id
                $values[1] = $techType;
                $values[2] = $techMessage;
                $ins->execute($values); // Reuse prepared statement
            }
        }
    }

    public function getAppointmentStats() {
        $stats = [];

        // Total appointments
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM appointments");
        $stats['total'] = $stmt->fetch()['total'] ?? 0;

        // Appointments by status
        $stmt = $this->pdo->query(
            "SELECT status, COUNT(*) as count FROM appointments GROUP BY status"
        );
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Today's appointments
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()"
        );
        $stmt->execute();
        $stats['today'] = $stmt->fetch()['count'] ?? 0;

        return $stats;
    }
}

?>