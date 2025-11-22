<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

// Get all feedback with guide and customer info
$stmt = $pdo->query("
    SELECT f.*, tg.title as guide_title, u.name as customer_name
    FROM troubleshooting_feedback f
    JOIN troubleshooting_guide tg ON f.troubleshooting_guide_id = tg.id
    JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
");
$feedback = $stmt->fetchAll();

// Process feedback to handle anonymous entries
foreach ($feedback as &$item) {
    if ($item['anonymous']) {
        $item['display_name'] = 'Anonymous';
    } else {
        $item['display_name'] = $item['customer_name'];
    }
}
unset($item);

// Calculate average ratings
$stmt = $pdo->query("
    SELECT
        AVG(rating) as avg_rating,
        COUNT(*) as total_feedback,
        COUNT(DISTINCT user_id) as unique_customers
    FROM troubleshooting_feedback
");
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback - Soreta Admin</title>
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
                    <h1 class="h3 mb-0">Customer Feedback</h1>
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
                <!-- Feedback Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-primary mx-auto mb-3">
                                    <i class="bi bi-star-fill"></i>
                                </div>
                                <h3 class="mb-1"><?= number_format($stats['avg_rating'] ?? 0, 1) ?></h3>
                                <p class="text-muted mb-0">Average Rating</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-success mx-auto mb-3">
                                    <i class="bi bi-chat-text"></i>
                                </div>
                                <h3 class="mb-1"><?= $stats['total_feedback'] ?? 0 ?></h3>
                                <p class="text-muted mb-0">Total Feedback</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <div class="stat-icon bg-info mx-auto mb-3">
                                    <i class="bi bi-people"></i>
                                </div>
                                <h3 class="mb-1"><?= $stats['unique_customers'] ?? 0 ?></h3>
                                <p class="text-muted mb-0">Unique Customers</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">All Feedback</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($feedback)): ?>
                            <div class="empty-state">
                                <i class="bi bi-star empty-state-icon"></i>
                                <h5>No Feedback Yet</h5>
                                <p class="text-muted">Customer feedback will appear here once they rate troubleshooting guides.</p>
                            </div>
                        <?php else: ?>
                            <div class="feedback-list">
                                <?php foreach ($feedback as $item): ?>
                                    <div class="feedback-item border-bottom pb-4 mb-4">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($item['guide_title']) ?></h6>
                                                <p class="text-muted mb-0">By <?= htmlspecialchars($item['display_name']) ?></p>
                                            </div>
                                            <div class="text-end">
                                                <div class="rating-stars mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?= $i <= $item['rating'] ? '-fill text-warning' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('M j, Y g:i A', strtotime($item['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php if ($item['comment']): ?>
                                            <div class="feedback-comment mt-2 p-3 bg-light rounded">
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($item['comment'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <a href="feedback_delete.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this feedback?')">Delete Feedback</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
    .rating-stars {
        font-size: 0.875rem;
    }
    
    .feedback-comment {
        border-left: 3px solid var(--primary);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
    }
    </style>
</body>
</html>