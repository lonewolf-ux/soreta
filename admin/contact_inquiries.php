<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = 'Contact inquiry deleted successfully.';
    $_SESSION['message_type'] = 'success';
    redirect('contact_inquiries.php');
}

// Get all contact inquiries
$stmt = $pdo->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC");
$inquiries = $stmt->fetchAll();

// Calculate stats
$totalInquiries = count($inquiries);
$unreadInquiries = count(array_filter($inquiries, function($inq) {
    return !isset($inq['read_at']) || empty($inq['read_at']);
}));
$todayInquiries = count(array_filter($inquiries, function($inq) {
    return date('Y-m-d', strtotime($inq['created_at'])) === date('Y-m-d');
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Inquiries - Soreta Admin</title>
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
                    <h1 class="h3 mb-0">Contact Inquiries</h1>
                </div>
                <div class="header-right">
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <div class="admin-content">
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-primary mx-auto mb-3">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <h3 class="mb-1"><?= $totalInquiries ?></h3>
                                <p class="text-muted mb-0">Total Inquiries</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-warning mx-auto mb-3">
                                    <i class="bi bi-envelope-exclamation"></i>
                                </div>
                                <h3 class="mb-1"><?= $unreadInquiries ?></h3>
                                <p class="text-muted mb-0">Unread</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-success mx-auto mb-3">
                                    <i class="bi bi-calendar-today"></i>
                                </div>
                                <h3 class="mb-1"><?= $todayInquiries ?></h3>
                                <p class="text-muted mb-0">Today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inquiries List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">All Contact Inquiries</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inquiries)): ?>
                            <div class="empty-state">
                                <i class="bi bi-envelope empty-state-icon"></i>
                                <h5>No Inquiries Yet</h5>
                                <p class="text-muted">Contact inquiries from customers will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Subject</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inquiries as $inquiry): ?>
                                            <tr class="inquiry-row" data-id="<?= $inquiry['id'] ?>" style="cursor: pointer;">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle me-3">
                                                            <?= strtoupper(substr($inquiry['name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?= htmlspecialchars($inquiry['name']) ?></div>
                                                            <?php if (!empty($inquiry['phone'])): ?>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-telephone me-1"></i>
                                                                    <?= htmlspecialchars($inquiry['phone']) ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?= htmlspecialchars($inquiry['email']) ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($inquiry['email']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($inquiry['subject']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= date('M j, Y', strtotime($inquiry['created_at'])) ?><br>
                                                        <?= date('g:i A', strtotime($inquiry['created_at'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $isRecent = strtotime($inquiry['created_at']) > strtotime('-24 hours');
                                                    $isUnread = !isset($inquiry['read_at']) || empty($inquiry['read_at']);
                                                    ?>
                                                    <?php if ($isRecent && $isUnread): ?>
                                                        <span class="badge bg-danger">New</span>
                                                    <?php elseif ($isUnread): ?>
                                                        <span class="badge bg-warning">Unread</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Read</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary view-inquiry" data-id="<?= $inquiry['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <a href="mailto:<?= htmlspecialchars($inquiry['email']) ?>?subject=Re: <?= urlencode($inquiry['subject']) ?>"
                                                           class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-reply"></i>
                                                        </a>
                                                        <a href="?delete=<?= $inquiry['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this inquiry?')">
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
        </main>
    </div>

    <!-- Inquiry Details Modal -->
    <div class="modal fade" id="inquiryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Contact Inquiry Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="inquiryDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        });

        // View inquiry details
        document.querySelectorAll('.view-inquiry').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const inquiryId = this.getAttribute('data-id');
                loadInquiryDetails(inquiryId);
            });
        });

        // Click row to view details
        document.querySelectorAll('.inquiry-row').forEach(row => {
            row.addEventListener('click', function() {
                const inquiryId = this.getAttribute('data-id');
                loadInquiryDetails(inquiryId);
            });
        });

        function loadInquiryDetails(id) {
            fetch(`get_inquiry_details.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('inquiryDetails').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('inquiryModal')).show();
                })
                .catch(error => {
                    console.error('Error loading inquiry details:', error);
                    alert('Error loading inquiry details. Please try again.');
                });
        }
    </script>

    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
        }

        .inquiry-row:hover {
            background-color: rgba(var(--primary-rgb, 67, 97, 238), 0.05);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
    </style>
</body>
</html>
