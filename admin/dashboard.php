<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();
$appointmentManager = new AppointmentManager($pdo);

// Get dashboard stats
$stats = $appointmentManager->getAppointmentStats();

// Get recent appointments
$recentAppointments = $appointmentManager->getAllAppointments(['limit' => 5]);

// Get technicians
$stmt = $pdo->query("SELECT id, name FROM technicians WHERE is_active = 1");
$technicians = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Soreta Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/main.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-layout">
        <?php include 'layout/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Bar -->
            <header class="admin-header">
                <div class="header-left">
                    <button class="btn btn-sm btn-outline-secondary d-md-none" id="mobileMenuToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0">Dashboard</h1>
                </div>
                <div class="header-right">
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="admin-content">
                <!-- Stats Grid -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted">Total Appointments</h6>
                                        <h3 class="mb-0"><?= $stats['total'] ?></h3>
                                    </div>
                                    <div class="stat-icon bg-primary">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                </div>
                                <p class="text-primary mb-0">
                                    <i class="bi bi-arrow-up"></i>
                                    <span>All time</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted">Today's Appointments</h6>
                                        <h3 class="mb-0"><?= $stats['today'] ?></h3>
                                    </div>
                                    <div class="stat-icon bg-success">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                </div>
                                <p class="text-success mb-0">
                                    <span>Scheduled for today</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted">In Progress</h6>
                                        <h3 class="mb-0"><?= $stats['by_status']['in-progress'] ?? 0 ?></h3>
                                    </div>
                                    <div class="stat-icon bg-warning">
                                        <i class="bi bi-tools"></i>
                                    </div>
                                </div>
                                <p class="text-warning mb-0">
                                    <span>Being serviced</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title text-muted">Completed</h6>
                                        <h3 class="mb-0"><?= $stats['by_status']['completed'] ?? 0 ?></h3>
                                    </div>
                                    <div class="stat-icon bg-info">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                                <p class="text-info mb-0">
                                    <span>Finished jobs</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Recent Appointments -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Appointments</h5>
                                <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentAppointments)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-calendar-x empty-state-icon"></i>
                                        <h5>No appointments yet</h5>
                                        <p class="text-muted">Appointments will appear here once customers start booking.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Job Order</th>
                                                    <th>Customer</th>
                                                    <th>Service</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentAppointments as $appointment): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($appointment['job_order_no']) ?></strong>
                                                        </td>
                                                        <td><?= htmlspecialchars($appointment['customer_name']) ?></td>
                                                        <td><?= htmlspecialchars($appointment['service_type']) ?></td>
                                                        <td>
                                                            <?= date('M j', strtotime($appointment['appointment_date'])) ?>
                                                            <br>
                                                            <small class="text-muted"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= getStatusBadgeClass($appointment['status']) ?>">
                                                                <?= ucfirst($appointment['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <button class="btn btn-outline-primary view-appointment" 
                                                                        data-id="<?= $appointment['id'] ?>">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                <a href="appointments.php?edit=<?= $appointment['id'] ?>" 
                                                                   class="btn btn-outline-secondary">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
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

                    <!-- Quick Actions & Technicians -->
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="appointments.php?new=true" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> New Appointment
                                    </a>
                                    <a href="technicians.php" class="btn btn-outline-primary">
                                        <i class="bi bi-tools"></i> Manage Technicians
                                    </a>
                                    <a href="troubleshooting.php" class="btn btn-outline-primary">
                                        <i class="bi bi-wrench"></i> Troubleshooting Guide
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Technicians -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Technicians</h5>
                                <a href="technicians.php" class="btn btn-sm btn-outline-primary">Manage</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($technicians)): ?>
                                    <div class="text-center py-3">
                                        <i class="bi bi-tools text-muted" style="font-size: 2rem;"></i>
                                        <p class="text-muted mt-2">No technicians added</p>
                                        <a href="technicians.php" class="btn btn-sm btn-primary">Add Technician</a>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($technicians as $tech): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($tech['name']) ?></h6>
                                                    <small class="text-muted">Active</small>
                                                </div>
                                                <span class="badge bg-primary">Available</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
    .admin-layout {
        display: flex;
        min-height: 100vh;
    }

    .admin-main {
        flex: 1;
        margin-left: 280px;
        transition: var(--transition-slow);
    }

    .admin-sidebar.collapsed ~ .admin-main {
        margin-left: 80px;
    }

    .admin-header {
        background: var(--background);
        border-bottom: 1px solid var(--border);
        padding: var(--space-6);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .admin-content {
        padding: var(--space-6);
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .stat-card {
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: var(--font-size-xl);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .admin-main {
            margin-left: 0;
        }
        
        .admin-sidebar.collapsed ~ .admin-main {
            margin-left: 0;
        }
        
        .admin-header {
            padding: var(--space-4);
        }
        
        .admin-content {
            padding: var(--space-4);
        }
        
        .row.g-4 {
            margin: 0 -0.5rem;
        }
        
        .col-md-3, .col-lg-8, .col-lg-4 {
            padding: 0 0.5rem;
        }
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Mobile menu toggle
    document.getElementById('mobileMenuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('mobile-open');
    });

    </script>
</body>
</html>

<?php
// Helper function for status badge classes
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