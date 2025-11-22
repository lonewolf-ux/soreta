<?php
require_once '../includes/config.php';
checkAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo "<p>Invalid appointment ID.</p>";
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT a.*, u.name as customer_name, t.name as technician_name
    FROM appointments a
    JOIN users u ON a.customer_id = u.id
    LEFT JOIN technicians t ON a.technician_id = t.id
    WHERE a.id = ?");
$stmt->execute([$id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    echo "<p>Appointment not found.</p>";
    exit;
}

// Authorization: customers can only view their own appointments
if (!isAdmin() && $appointment['customer_id'] != ($_SESSION['user_id'] ?? 0)) {
    echo "<p>Access denied.</p>";
    exit;
}

// Helper function for status badge classes
function getStatusBadgeClass($status) {
    $classes = [
        'scheduled' => 'scheduled',
        'in-progress' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        'rescheduled' => 'rescheduled'
    ];
    return $classes[$status] ?? 'secondary';
}

// If this request expects a fragment (inserted into modal via AJAX), return only the inner HTML
$isFragment = false;
if (!empty($_GET['fragment']) && $_GET['fragment'] == '1') $isFragment = true;
if (!$isFragment && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') $isFragment = true;

if ($isFragment) {
    // Output only the modal/body fragment (no full document)
    ?>
    <style>
        /* Modal Detail Styles - Glassmorphism */
        .appointment-detail-modal {
            padding: 0;
        }

        .appointment-detail-section {
            margin-bottom: 2rem;
        }

        .appointment-detail-section:last-child {
            margin-bottom: 0;
        }

        .appointment-detail-section h6 {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .appointment-detail-section h6 i {
            color: #2563eb;
            font-size: 1.25rem;
        }

        .appointment-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .appointment-detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .appointment-detail-label {
            font-size: 0.8125rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .appointment-detail-value {
            color: #f1f5f9;
            font-weight: 600;
            font-size: 1rem;
        }

        .appointment-detail-value i {
            color: #2563eb;
            margin-right: 0.5rem;
        }

        .appointment-detail-content {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            color: #cbd5e0;
            line-height: 1.7;
        }

        .appointment-detail-content p {
            color: #cbd5e0;
            margin: 0;
        }

        .status-badge-custom {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 24px;
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid;
        }

        .status-badge-custom.scheduled {
            background: rgba(59, 130, 246, 0.15);
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .status-badge-custom.in-progress {
            background: rgba(245, 158, 11, 0.15);
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .status-badge-custom.completed {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .status-badge-custom.cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-badge-custom.rescheduled {
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            border-color: rgba(139, 92, 246, 0.3);
        }

        .appointment-detail-note {
            background: rgba(37, 99, 235, 0.15);
            border: 1px solid rgba(37, 99, 235, 0.3);
            border-radius: 12px;
            padding: 1.25rem;
            color: #cbd5e0;
            line-height: 1.7;
            margin-top: 1rem;
        }

        .appointment-detail-note i {
            color: #2563eb;
            margin-right: 0.75rem;
            font-size: 1.125rem;
        }

        .appointment-detail-note h6 {
            color: #f1f5f9;
            font-weight: 700;
            margin-bottom: 0.75rem;
            border-bottom: none;
            padding-bottom: 0;
        }

        .appointment-detail-note p {
            color: #cbd5e0;
            margin: 0;
        }

        .divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 2rem 0;
        }
    </style>

    <div class="appointment-detail-modal">
        <!-- Job Information Section -->
        <div class="appointment-detail-section">
            <h6>
                <i class="bi bi-file-text"></i>
                Job Information
            </h6>
            <div class="appointment-detail-grid">
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Job Order</span>
                    <span class="appointment-detail-value"><?= htmlspecialchars($appointment['job_order_no']) ?></span>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Customer</span>
                    <span class="appointment-detail-value"><?= htmlspecialchars($appointment['customer_name']) ?></span>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Service Type</span>
                    <span class="appointment-detail-value"><?= htmlspecialchars($appointment['service_type']) ?></span>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Status</span>
                    <span class="status-badge-custom <?= getStatusBadgeClass($appointment['status']) ?>">
                        <?= ucfirst($appointment['status']) ?>
                    </span>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Payment Status</span>
                    <span class="status-badge-custom <?= ($appointment['payment_status'] ?? 'unpaid') === 'paid' ? 'completed' : 'in-progress' ?>">
                        <?= ucfirst($appointment['payment_status'] ?? 'Unpaid') ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Schedule & Assignment Section -->
        <div class="appointment-detail-section">
            <h6>
                <i class="bi bi-calendar-check"></i>
                Schedule & Assignment
            </h6>
            <div class="appointment-detail-grid">
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Appointment Date</span>
                    <span class="appointment-detail-value">
                        <i class="bi bi-calendar3"></i>
                        <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                    </span>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Appointment Time</span>
                    <span class="appointment-detail-value">
                        <i class="bi bi-clock"></i>
                        <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                    </span>
                </div>
                <div class="appointment-detail-item">
                    <span class="appointment-detail-label">Technician</span>
                    <span class="appointment-detail-value">
                        <i class="bi bi-person-badge"></i>
                        <?= htmlspecialchars($appointment['technician_name'] ?? 'Unassigned') ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Product Details Section -->
        <div class="appointment-detail-section">
            <h6>
                <i class="bi bi-box-seam"></i>
                Product Details
            </h6>
            <div class="appointment-detail-content">
                <p><?= htmlspecialchars($appointment['product_details']) ?></p>
            </div>
        </div>

        <!-- Problem Description Section -->
        <div class="appointment-detail-section">
            <h6>
                <i class="bi bi-exclamation-triangle"></i>
                Problem Description
            </h6>
            <div class="appointment-detail-content">
                <p><?= nl2br(htmlspecialchars($appointment['trouble_description'])) ?></p>
            </div>
        </div>

        <!-- Accessories Section (if available) -->
        <?php if (!empty($appointment['accessories'])): ?>
        <div class="appointment-detail-section">
            <h6>
                <i class="bi bi-plug"></i>
                Accessories
            </h6>
            <div class="appointment-detail-content">
                <p><?= nl2br(htmlspecialchars($appointment['accessories'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin Notes Section (if available) -->
        <?php if (!empty($appointment['admin_notes'])): ?>
        <div class="appointment-detail-section">
            <div class="appointment-detail-note">
                <h6>
                    <i class="bi bi-sticky"></i>
                    Admin Notes
                </h6>
                <p><?= nl2br(htmlspecialchars($appointment['admin_notes'])) ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment <?= htmlspecialchars($appointment['job_order_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #2563eb20;
            font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #f1f5f9;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            color: #ffffff !important;
            font-weight: 700;
        }

        .navbar-brand i {
            color: #2563eb;
            margin-right: 0.5rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f1f5f9 !important;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: #f1f5f9;
        }

        /* Container */
        .container-appointment {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
            padding-top: calc(70px + 2rem);
        }

        /* Card */
        .appointment-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        /* Header */
        .appointment-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .appointment-header i {
            font-size: 2.5rem;
            color: #2563eb;
        }

        .appointment-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
        }

        /* Detail Section */
        .appointment-detail-section {
            margin-bottom: 2rem;
        }

        .appointment-detail-section:last-child {
            margin-bottom: 0;
        }

        .appointment-detail-section h6 {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .appointment-detail-section h6 i {
            color: #2563eb;
            font-size: 1.5rem;
        }

        .appointment-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .appointment-detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .appointment-detail-label {
            font-size: 0.8125rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .appointment-detail-value {
            color: #f1f5f9;
            font-weight: 600;
            font-size: 1rem;
        }

        .appointment-detail-value i {
            color: #2563eb;
            margin-right: 0.5rem;
        }

        .appointment-detail-content {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            color: #cbd5e0;
            line-height: 1.7;
        }

        .appointment-detail-content p {
            color: #cbd5e0;
            margin: 0;
        }

        .status-badge-custom {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 24px;
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 1px solid;
        }

        .status-badge-custom.scheduled {
            background: rgba(59, 130, 246, 0.15);
            color: #93c5fd;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .status-badge-custom.in-progress {
            background: rgba(245, 158, 11, 0.15);
            color: #fcd34d;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .status-badge-custom.completed {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .status-badge-custom.cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-badge-custom.rescheduled {
            background: rgba(139, 92, 246, 0.15);
            color: #c4b5fd;
            border-color: rgba(139, 92, 246, 0.3);
        }

        .appointment-detail-note {
            background: rgba(37, 99, 235, 0.15);
            border: 1px solid rgba(37, 99, 235, 0.3);
            border-radius: 12px;
            padding: 1.25rem;
            color: #cbd5e0;
            line-height: 1.7;
            margin-top: 1rem;
        }

        .appointment-detail-note i {
            color: #2563eb;
            margin-right: 0.75rem;
            font-size: 1.125rem;
        }

        .appointment-detail-note h6 {
            color: #f1f5f9;
            font-weight: 700;
            margin-bottom: 0.75rem;
            border-bottom: none;
            padding-bottom: 0;
        }

        .appointment-detail-note p {
            color: #cbd5e0;
            margin: 0;
        }

        .divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 2rem 0;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-primary-custom {
            background: var(--primary);
            border: none;
            color: white;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-custom:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-secondary-custom {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f1f5f9;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary-custom:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: #f1f5f9;
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container-appointment {
                padding: 1rem 0.75rem;
                padding-top: calc(70px + 1rem);
            }

            .appointment-card {
                padding: 1.5rem;
                border-radius: 16px;
            }

            .appointment-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 1.5rem;
            }

            .appointment-header i {
                font-size: 2rem;
            }

            .appointment-header h1 {
                font-size: 1.5rem;
            }

            .divider {
                margin: 1.5rem 0;
            }

            .appointment-detail-section {
                margin-bottom: 1.5rem;
            }

            .appointment-detail-section h6 {
                font-size: 1rem;
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
            }

            .appointment-detail-section h6 i {
                font-size: 1.25rem;
            }

            .appointment-detail-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .appointment-detail-item {
                gap: 0.25rem;
            }

            .appointment-detail-label {
                font-size: 0.75rem;
            }

            .appointment-detail-value {
                font-size: 0.95rem;
            }

            .appointment-detail-content {
                padding: 1rem;
                font-size: 0.9rem;
            }

            .status-badge-custom {
                padding: 0.4rem 0.75rem;
                font-size: 0.75rem;
            }

            .appointment-detail-note {
                padding: 1rem;
            }

            .appointment-detail-note h6 {
                font-size: 0.95rem;
                margin-bottom: 0.5rem;
            }

            .appointment-detail-note p {
                font-size: 0.9rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.75rem;
                margin-top: 1.5rem;
                padding-top: 1.5rem;
            }

            .btn-primary-custom,
            .btn-secondary-custom {
                width: 100%;
                justify-content: center;
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }

            .btn-primary-custom i,
            .btn-secondary-custom i {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 0.75rem 1rem !important;
            }

            .navbar-brand {
                font-size: 1rem;
            }

            .btn-back {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            .container-appointment {
                padding: 0.75rem 0.5rem;
                padding-top: calc(70px + 0.75rem);
            }

            .appointment-card {
                padding: 1rem;
                border-radius: 12px;
            }

            .appointment-header {
                gap: 0.75rem;
            }

            .appointment-header i {
                font-size: 1.75rem;
            }

            .appointment-header h1 {
                font-size: 1.25rem;
            }

            .appointment-detail-section h6 {
                font-size: 0.9rem;
                margin-bottom: 0.75rem;
            }

            .appointment-detail-label {
                font-size: 0.7rem;
            }

            .appointment-detail-value {
                font-size: 0.9rem;
            }

            .appointment-detail-content {
                padding: 0.75rem;
                border-radius: 8px;
            }

            .appointment-detail-content p {
                font-size: 0.85rem;
            }

            .appointment-detail-note {
                padding: 0.75rem;
                border-radius: 8px;
            }

            .appointment-detail-note i {
                font-size: 1rem;
                margin-right: 0.5rem;
            }

            .action-buttons {
                gap: 0.5rem;
                margin-top: 1rem;
                padding-top: 1rem;
            }

            .btn-primary-custom,
            .btn-secondary-custom {
                padding: 0.65rem 1rem;
                font-size: 0.85rem;
                border-radius: 10px;
            }

            .btn-primary-custom i,
            .btn-secondary-custom i {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?= ROOT_PATH ?>index.php">
                <i class="bi bi-lightning-charge-fill"></i>
                Appointment Details
            </a>
            <div class="ms-auto">
                <?php if (isAdmin()): ?>
                    <a href="<?= ROOT_PATH ?>admin/dashboard.php" class="btn-back">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= ROOT_PATH ?>customer/dashboard.php" class="btn-back">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-appointment">
        <div class="appointment-card">
            <!-- Header -->
            <div class="appointment-header">
                <i class="bi bi-calendar-check"></i>
                <h1>Appointment Details</h1>
            </div>

            <div class="divider"></div>

            <!-- Job Information Section -->
            <div class="appointment-detail-section">
                <h6>
                    <i class="bi bi-file-text"></i>
                    Job Information
                </h6>
                <div class="appointment-detail-grid">
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Job Order</span>
                        <span class="appointment-detail-value"><?= htmlspecialchars($appointment['job_order_no']) ?></span>
                    </div>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Customer</span>
                        <span class="appointment-detail-value"><?= htmlspecialchars($appointment['customer_name']) ?></span>
                    </div>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Service Type</span>
                        <span class="appointment-detail-value"><?= htmlspecialchars($appointment['service_type']) ?></span>
                    </div>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Status</span>
                        <span class="status-badge-custom <?= getStatusBadgeClass($appointment['status']) ?>">
                            <?= ucfirst($appointment['status']) ?>
                        </span>
                    </div>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Payment Status</span>
                        <span class="status-badge-custom <?= ($appointment['payment_status'] ?? 'unpaid') === 'paid' ? 'completed' : 'in-progress' ?>">
                            <?= ucfirst($appointment['payment_status'] ?? 'Unpaid') ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Schedule & Assignment Section -->
            <div class="appointment-detail-section">
                <h6>
                    <i class="bi bi-calendar-check"></i>
                    Schedule & Assignment
                </h6>
                <div class="appointment-detail-grid">
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Appointment Date</span>
                        <span class="appointment-detail-value">
                            <i class="bi bi-calendar3"></i>
                            <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                        </span>
                    </div>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Appointment Time</span>
                        <span class="appointment-detail-value">
                            <i class="bi bi-clock"></i>
                            <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                        </span>
                    </div>
                    <div class="appointment-detail-item">
                        <span class="appointment-detail-label">Technician</span>
                        <span class="appointment-detail-value">
                            <i class="bi bi-person-badge"></i>
                            <?= htmlspecialchars($appointment['technician_name'] ?? 'Unassigned') ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Product Details Section -->
            <div class="appointment-detail-section">
                <h6>
                    <i class="bi bi-box-seam"></i>
                    Product Details
                </h6>
                <div class="appointment-detail-content">
                    <p><?= htmlspecialchars($appointment['product_details']) ?></p>
                </div>
            </div>

            <!-- Problem Description Section -->
            <div class="appointment-detail-section">
                <h6>
                    <i class="bi bi-exclamation-triangle"></i>
                    Problem Description
                </h6>
                <div class="appointment-detail-content">
                    <p><?= nl2br(htmlspecialchars($appointment['trouble_description'])) ?></p>
                </div>
            </div>

            <!-- Accessories Section (if available) -->
            <?php if (!empty($appointment['accessories'])): ?>
            <div class="appointment-detail-section">
                <h6>
                    <i class="bi bi-plug"></i>
                    Accessories
                </h6>
                <div class="appointment-detail-content">
                    <p><?= nl2br(htmlspecialchars($appointment['accessories'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Notes Section (if available) -->
            <?php if (!empty($appointment['admin_notes'])): ?>
            <div class="appointment-detail-section">
                <div class="appointment-detail-note">
                    <h6>
                        <i class="bi bi-sticky"></i>
                        Admin Notes
                    </h6>
                    <p><?= nl2br(htmlspecialchars($appointment['admin_notes'])) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if (isAdmin()): ?>
                    <a href="<?= ROOT_PATH ?>admin/dashboard.php" class="btn-primary-custom">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                <?php else: ?>
                    <a href="<?= ROOT_PATH ?>customer/dashboard.php" class="btn-primary-custom">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn-secondary-custom">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>