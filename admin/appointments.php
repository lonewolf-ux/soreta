<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();
$appointmentManager = new AppointmentManager($pdo);

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);
        
        $result = $appointmentManager->updateAppointment($id, $_POST);
        
        if ($result) {
            $_SESSION['success_message'] = "Appointment updated successfully!";
        } else {
            throw new Exception("Failed to update appointment.");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    redirect('admin/appointments.php');
}

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'technician_id' => $_GET['technician_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get appointments
$appointments = $appointmentManager->getAllAppointments($filters);

// Get technicians for filter and assignment
$stmt = $pdo->query("SELECT id, name FROM technicians WHERE is_active = 1");
$technicians = $stmt->fetchAll();

// Get services from settings for edit modal
$servicesStmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'services'");
$servicesJson = $servicesStmt->fetch()['setting_value'] ?? '[]';
$services = json_decode($servicesJson, true) ?: [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Soreta Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/main.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <?php include 'layout/sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <button class="btn btn-sm btn-outline-secondary d-md-none" id="mobileMenuToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0">Appointment Logbook</h1>
                </div>
                <div class="header-right">
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <div class="admin-content">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?= $filters['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                    <option value="in-progress" <?= $filters['status'] === 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Technician</label>
                                <select name="technician_id" class="form-control">
                                    <option value="">All Technicians</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?= $tech['id'] ?>" <?= $filters['technician_id'] == $tech['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tech['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Job order or customer..." value="<?= $filters['search'] ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                                    <a href="appointments.php" class="btn btn-outline-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appointments Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">All Appointments</h5>
                        <span class="badge bg-primary"><?= count($appointments) ?> records</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="empty-state">
                                <i class="bi bi-calendar-x empty-state-icon"></i>
                                <h5>No appointments found</h5>
                                <p class="text-muted">Try adjusting your filters or check back later.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Job Order</th>
                                            <th>Customer</th>
                                            <th>Address</th>
                                            <th>Brand</th>
                                            <th>Product</th>
                                            <th>Model</th>
                                            <th>Serial</th>
                                            <th>Accessories</th>
                                            <th>Service</th>
                                            <th>Date & Time</th>
                                            <th>Technician</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($appointment['job_order_no']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['product_details']) ?></small>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($appointment['customer_name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['contact_number']) ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['customer_address'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['brand'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['product'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['model_number'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['serial_number'] ?? '') ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($appointment['accessories'] ?? '') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($appointment['service_type']) ?></td>
                                                <td>
                                                    <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                                                    <br>
                                                    <small class="text-muted"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($appointment['technician_name']): ?>
                                                        <?= htmlspecialchars($appointment['technician_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= getStatusBadgeClass($appointment['status']) ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $appointment['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($appointment['payment_status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary view-appointment"
                                                                data-appointment='<?= base64_encode(json_encode($appointment)) ?>'>
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-secondary edit-appointment"
                                                                data-appointment='<?= base64_encode(json_encode($appointment)) ?>'>
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Appointment Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?action=update&id=0" id="editForm">
                    <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="in-progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-control" required>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Technician</label>
                                <select name="technician_id" class="form-control">
                                    <option value="">Not Assigned</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment Date</label>
                                <input type="date" name="appointment_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Service Type</label>
                                <select name="service_type" class="form-control" required>
                                    <option value="">Select Service</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= htmlspecialchars($service) ?>"><?= htmlspecialchars($service) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Appointment Time</label>
                                <input type="time" name="appointment_time" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Product Details</label>
                                <textarea name="product_details" class="form-control" rows="2" placeholder="e.g., Samsung Galaxy S21, model SM-G991B" required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Trouble Description</label>
                                <textarea name="trouble_description" class="form-control" rows="3" placeholder="Describe the issue..." required></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-control" placeholder="e.g., Samsung">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Product</label>
                                <input type="text" name="product" class="form-control" placeholder="e.g., Galaxy S21">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Model Number</label>
                                <input type="text" name="model_number" class="form-control" placeholder="e.g., SM-G991B">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial_number" class="form-control" placeholder="e.g., ABC123456">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Accessories</label>
                                <textarea name="accessories" class="form-control" rows="2" placeholder="e.g., Charger, Case"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control" rows="3" placeholder="Internal notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit appointment
        document.querySelectorAll('.edit-appointment').forEach(button => {
            button.addEventListener('click', function() {
                const appointment = JSON.parse(atob(this.dataset.appointment));
                const form = document.getElementById('editForm');
                const modal = document.getElementById('editModal');
                const modalTitle = modal.querySelector('.modal-title');
                const submitBtn = modal.querySelector('button[type="submit"]');

                // Update modal title
                modalTitle.textContent = 'Edit Appointment';

                // Show submit button
                submitBtn.style.display = 'block';

                // Enable form fields
                form.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);

                // Update form action with correct ID
                form.action = `?action=update&id=${appointment.id}`;

                // Populate form fields
                form.querySelector('[name="status"]').value = appointment.status;
                form.querySelector('[name="payment_status"]').value = appointment.payment_status;
                form.querySelector('[name="technician_id"]').value = appointment.technician_id || '';
                form.querySelector('[name="appointment_date"]').value = appointment.appointment_date;
                form.querySelector('[name="appointment_time"]').value = appointment.appointment_time;
                form.querySelector('[name="service_type"]').value = appointment.service_type || '';
                form.querySelector('[name="product_details"]').value = appointment.product_details || '';
                form.querySelector('[name="trouble_description"]').value = appointment.trouble_description || '';
                form.querySelector('[name="brand"]').value = appointment.brand || '';
                form.querySelector('[name="product"]').value = appointment.product || '';
                form.querySelector('[name="model_number"]').value = appointment.model_number || '';
                form.querySelector('[name="serial_number"]').value = appointment.serial_number || '';
                form.querySelector('[name="accessories"]').value = appointment.accessories || '';
                form.querySelector('[name="admin_notes"]').value = appointment.admin_notes || '';

                // Show modal
                new bootstrap.Modal(modal).show();
            });
        });

        // View appointment
        document.querySelectorAll('.view-appointment').forEach(button => {
            button.addEventListener('click', function() {
                const appointment = JSON.parse(atob(this.dataset.appointment));
                const form = document.getElementById('editForm');
                const modal = document.getElementById('editModal');
                const modalTitle = modal.querySelector('.modal-title');
                const submitBtn = modal.querySelector('button[type="submit"]');

                // Update modal title
                modalTitle.textContent = 'View Appointment';

                // Hide submit button
                submitBtn.style.display = 'none';

                // Disable form fields
                form.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);

                // Populate form fields
                form.querySelector('[name="status"]').value = appointment.status;
                form.querySelector('[name="payment_status"]').value = appointment.payment_status;
                form.querySelector('[name="technician_id"]').value = appointment.technician_id || '';
                form.querySelector('[name="appointment_date"]').value = appointment.appointment_date;
                form.querySelector('[name="appointment_time"]').value = appointment.appointment_time;
                form.querySelector('[name="service_type"]').value = appointment.service_type || '';
                form.querySelector('[name="product_details"]').value = appointment.product_details || '';
                form.querySelector('[name="trouble_description"]').value = appointment.trouble_description || '';
                form.querySelector('[name="brand"]').value = appointment.brand || '';
                form.querySelector('[name="product"]').value = appointment.product || '';
                form.querySelector('[name="model_number"]').value = appointment.model_number || '';
                form.querySelector('[name="serial_number"]').value = appointment.serial_number || '';
                form.querySelector('[name="accessories"]').value = appointment.accessories || '';
                form.querySelector('[name="admin_notes"]').value = appointment.admin_notes || '';

                // Show modal
                new bootstrap.Modal(modal).show();
            });
        });

        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        });

        // Notification functionality removed
    </script>
</body>
</html>

<?php
function getStatusBadgeClass($status) {
    $classes = [
        'scheduled' => 'primary',
        'in-progress' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}
?>