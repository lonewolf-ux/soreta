<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

// Get all customers
$stmt = $pdo->query("
    SELECT u.*, COUNT(a.id) as appointment_count 
    FROM users u 
    LEFT JOIN appointments a ON u.id = a.customer_id 
    WHERE u.role = 'customer' 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Soreta Admin</title>
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
                    <h1 class="h3 mb-0">Customer Management</h1>
                </div>
                <div class="header-right">
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <div class="admin-content">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">All Customers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($customers)): ?>
                            <div class="empty-state">
                                <i class="bi bi-people empty-state-icon"></i>
                                <h5>No Customers Yet</h5>
                                <p class="text-muted">Customer accounts will appear here once they register.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Contact</th>
                                            <th>Address</th>
                                            <th>Appointments</th>
                                            <th>Member Since</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="customer-avatar me-3">
                                                            <?= strtoupper(substr($customer['name'], 0, 2)) ?>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($customer['contact_number']) ?></td>
                                                <td>
                                                    <small><?= htmlspecialchars($customer['address']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?= $customer['appointment_count'] ?></span>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, Y', strtotime($customer['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $customer['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $customer['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        });
    </script>

    <style>
    .customer-avatar {
        width: 40px;
        height: 40px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: var(--font-size-sm);
    }
    </style>
</body>
</html>