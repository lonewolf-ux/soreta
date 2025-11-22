<?php
// Check if user is admin and logged in
require_once '../../includes/config.php';
checkAuth();
if (!isAdmin()) {
    redirect('customer/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Soreta Electronics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>Soreta Admin</h3>
                <button type="button" id="sidebarCollapse" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li>
                    <a href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a>
                </li>
                <li>
                    <a href="technicians.php"><i class="bi bi-tools"></i> Technicians</a>
                </li>
                <li>
                    <a href="troubleshooting.php"><i class="bi bi-wrench"></i> Troubleshooting Guide</a>
                </li>
                <li>
                    <a href="feedback.php"><i class="bi bi-star"></i> Feedback</a>
                </li>
                <li>
                    <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Page Content -->
        <div id="content" class="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarToggle" class="btn btn-info">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="navbar-nav ms-auto d-flex align-items-center">
                        <?php include_once '../../includes/notification-component.php'; ?>
                        <li class="nav-item ms-3">
                            <span class="navbar-text">
                                Hello, <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </span>
                        </li>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <div class="container-fluid p-4">