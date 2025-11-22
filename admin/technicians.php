<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle delete (GET request)
if ($action === 'delete' && $id) {
    try {
        // Check if technician is assigned to any appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE technician_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $_SESSION['error_message'] = "Cannot delete technician assigned to appointments.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM technicians WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Technician deleted successfully!";
        }

        redirect('admin/technicians.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        redirect('admin/technicians.php');
    }
}

// Handle form submissions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO technicians (name, contact_number, specialization) VALUES (?, ?, ?)");
            $stmt->execute([
                Security::sanitizeInput($_POST['name']),
                Security::sanitizeInput($_POST['contact_number']),
                Security::sanitizeInput($_POST['specialization'])
            ]);
            $_SESSION['success_message'] = "Technician added successfully!";
        } elseif ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("UPDATE technicians SET name = ?, contact_number = ?, specialization = ? WHERE id = ?");
            $stmt->execute([
                Security::sanitizeInput($_POST['name']),
                Security::sanitizeInput($_POST['contact_number']),
                Security::sanitizeInput($_POST['specialization']),
                $id
            ]);
            $_SESSION['success_message'] = "Technician updated successfully!";
        }

        redirect('admin/technicians.php');
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        redirect('admin/technicians.php');
    }
}

// Get all technicians
$stmt = $pdo->query("SELECT * FROM technicians ORDER BY name");
$technicians = $stmt->fetchAll();

// Get technician for editing
$editTechnician = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = ?");
    $stmt->execute([$id]);
    $editTechnician = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technicians - Soreta Admin</title>
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
                    <h1 class="h3 mb-0">Technician Management</h1>
                </div>
                <div class="header-right">
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <div class="admin-content">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-error">
                        <?= $_SESSION['error_message'] ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['success_message'] ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><?= $editTechnician ? 'Edit' : 'Add' ?> Technician</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="?action=<?= $editTechnician ? 'edit&id=' . $id : 'add' ?>">
                                    <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                                    
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= $editTechnician ? htmlspecialchars($editTechnician['name']) : '' ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="contact_number" class="form-label">Contact Number</label>
                                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                               value="<?= $editTechnician ? htmlspecialchars($editTechnician['contact_number']) : '' ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="specialization" class="form-label">Specialization</label>
                                        <input type="text" class="form-control" id="specialization" name="specialization" 
                                               value="<?= $editTechnician ? htmlspecialchars($editTechnician['specialization']) : '' ?>">
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <?= $editTechnician ? 'Update' : 'Add' ?> Technician
                                        </button>
                                        <?php if ($editTechnician): ?>
                                            <a href="admin/technicians.php" class="btn btn-outline-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">All Technicians</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($technicians)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-tools empty-state-icon"></i>
                                        <h5>No Technicians</h5>
                                        <p class="text-muted">Add your first technician to get started.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Contact</th>
                                                    <th>Specialization</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($technicians as $tech): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($tech['name']) ?></td>
                                                        <td><?= htmlspecialchars($tech['contact_number']) ?></td>
                                                        <td><?= htmlspecialchars($tech['specialization']) ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="?action=edit&id=<?= $tech['id'] ?>" class="btn btn-outline-primary">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <a href="?action=delete&id=<?= $tech['id'] ?>" class="btn btn-outline-danger" 
                                                                   onclick="return confirm('Are you sure you want to delete this technician?')">
                                                                    <i class="bi bi-trash"></i>
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
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        });
    </script>
</body>
</html>