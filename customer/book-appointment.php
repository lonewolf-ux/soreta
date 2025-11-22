<?php
require_once '../includes/config.php';
checkAuth();

if (isAdmin()) {
    redirect('admin/dashboard.php');
}

$addrParts = ['', '', '', '']; // street, barangay, city, province
if (!empty($_SESSION['user_address'])) {
    $parts = array_map('trim', explode(',', $_SESSION['user_address']));
    for ($i = 0; $i < 4; $i++) {
        $addrParts[$i] = $parts[$i] ?? '';
    }
}

$db = new Database();
$pdo = $db->getConnection();

// Get company settings
$settings = [];
try {
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'");
    $colsStmt->execute();
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $colKey = in_array('setting_key', $cols) ? 'setting_key' : (in_array('key', $cols) ? 'key' : 'setting_key');
    $colValue = in_array('setting_value', $cols) ? 'setting_value' : (in_array('value', $cols) ? 'value' : 'setting_value');

    $colKeyEsc = "`" . str_replace('`', '', $colKey) . "`";
    $colValueEsc = "`" . str_replace('`', '', $colValue) . "`";

    $stmt = $pdo->query("SELECT " . $colKeyEsc . " AS setting_key, " . $colValueEsc . " AS setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // ignore
}

// Function to flatten settings
function flatten_to_string($val) {
    if (is_array($val)) {
        $flattened = [];
        foreach ($val as $v) {
            $flattened[] = flatten_to_string($v);
        }
        return implode(' ', $flattened);
    } elseif (is_string($val)) {
        $decoded = json_decode($val, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return flatten_to_string($decoded);
        }
        $unserialized = @unserialize($val);
        if ($unserialized !== false && is_array($unserialized)) {
            return flatten_to_string($unserialized);
        }
        return $val;
    } else {
        return $val === null ? '' : (string)$val;
    }
}

foreach ($settings as $key => $val) {
    $settings[$key] = flatten_to_string($val);
}

$value = $settings['favicon'] ?? '';
$settings['favicon'] = is_array($value) ? (string)($value[0] ?? '') : (string)$value;

$value = $settings['site_logo'] ?? '';
$settings['site_logo'] = is_array($value) ? (string)($value[0] ?? '') : (string)$value;

// Get user data
$userStmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userData = $userStmt->fetch();

// Define default services
$services = [
    'Repair',
    'Maintenance',
    'Installation',
    'Troubleshooting',
    'Parts Replacement',
    'Software Update',
    'Diagnostics'
];

// Try to get services from settings if available
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'services'");
    $servicesJson = $stmt->fetch()['setting_value'];
    $dbServices = json_decode($servicesJson, true);
    if (!empty($dbServices)) {
        $services = $dbServices;
    }
} catch (Exception $e) {
    // Keep using default services
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);

        $customer_name = Security::sanitizeInput($_POST['customer_name'] ?? '');
        $customer_contact = Security::sanitizeInput($_POST['customer_contact'] ?? '');

        $street = Security::sanitizeInput($_POST['street'] ?? '');
        $barangay = Security::sanitizeInput($_POST['barangay'] ?? '');
        $city = Security::sanitizeInput($_POST['city'] ?? '');
        $province = Security::sanitizeInput($_POST['province'] ?? '');

        if (empty($customer_name)) {
            throw new Exception('Please provide your name.');
        }
        if (empty($barangay) || empty($city) || empty($province)) {
            throw new Exception('Please provide at least barangay, city/municipality, and province.');
        }

        $serviceType = trim(Security::sanitizeInput($_POST['service_type'] ?? ''));
        $brand = trim(Security::sanitizeInput($_POST['brand'] ?? ''));
        $product = trim(Security::sanitizeInput($_POST['product'] ?? ''));
        $modelNumber = trim(Security::sanitizeInput($_POST['model_number'] ?? ''));
        $serial = trim(Security::sanitizeInput($_POST['serial'] ?? ''));
        $accessories = trim(Security::sanitizeInput($_POST['accessories'] ?? ''));
        $troubleDescription = trim(Security::sanitizeInput($_POST['trouble_description'] ?? ''));

        if (empty($serviceType)) {
            throw new Exception('Please select a service type.');
        }
        if (empty($brand)) {
            throw new Exception('Please provide the brand name.');
        }
        if (empty($product)) {
            throw new Exception('Please provide the product name.');
        }
        if (empty($modelNumber)) {
            throw new Exception('Please provide the model number.');
        }
        if (empty($troubleDescription)) {
            throw new Exception('Please describe the trouble.');
        }

        $fullAddress = trim(implode(', ', array_filter([$street, $barangay, $city, $province])));

        $stmt = $pdo->prepare("UPDATE users SET name = ?, contact_number = ?, address = ? WHERE id = ?");
        $stmt->execute([$customer_name, $customer_contact, $fullAddress, $_SESSION['user_id']]);

        $_SESSION['user_name'] = $customer_name;
        $_SESSION['user_contact'] = $customer_contact;
        $_SESSION['user_address'] = $fullAddress;

        $appointmentManager = new AppointmentManager($pdo);
        $result = $appointmentManager->createAppointment($_SESSION['user_id'], $_POST);

        if ($result['success']) {
            $_SESSION['success_message'] = "Appointment booked successfully! Your Job Order Number is: " . $result['job_order_no'];
            redirect('customer/dashboard.php');
        }
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
    <title>Book Appointment - <?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/unified-theme.css" rel="stylesheet">
    <link href="../assets/css/main.css" rel="stylesheet">
    <?php
    $favicon = $settings['favicon'];
    if (!empty($favicon) && is_string($favicon)): ?>
        <link rel="icon" href="<?= htmlspecialchars($favicon[0] === '/' ? $favicon : ROOT_PATH . $favicon) ?>" />
    <?php endif; ?>
    <style>
        :root {
            --primary: <?= htmlspecialchars($settings['primary_color'] ?? '#2563eb') ?>;
            --secondary: <?= htmlspecialchars($settings['secondary_color'] ?? '#64748b') ?>;
            --primary-light: <?= htmlspecialchars($settings['primary_color'] ?? '#2563eb') ?>20;
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Inter') ?>, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #f1f5f9;
        }

        /* Glassmorphism Navigation */
        nav.navbar {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(10px);
        }

        nav.navbar.scrolled {
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .navbar-nav .nav-link {
            color: #f1f5f9 !important;
            transition: color 0.3s ease;
        }

        .navbar-brand,
        .navbar-brand span,
        .navbar-brand i {
            color: #ffffff !important;
        }

        /* User Avatar Dropdown */
        .user-avatar-wrapper {
            position: relative;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, #000));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            text-transform: uppercase;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 280px;
            background: rgba(30, 41, 59, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1050;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: linear-gradient(135deg, var(--primary-light), transparent);
        }

        .user-dropdown-name {
            font-weight: 700;
            color: #f1f5f9;
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }

        .user-dropdown-email {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .user-dropdown-menu {
            padding: 0.5rem;
        }

        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #f1f5f9;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9375rem;
        }

        .user-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
        }

        .user-dropdown-item i {
            font-size: 1.125rem;
            color: var(--primary);
        }

        .user-dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 0.5rem 0;
        }

        /* Main Container */
        .booking-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
            padding-top: calc(56px + 2rem);
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #cbd5e0;
            font-size: 1.125rem;
        }

        /* Glassmorphism Card */
        .booking-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .section-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
        }

        /* Form Controls */
        .form-label {
            color: #f1f5f9;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }

        .form-control,
        .form-select {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 0.875rem 1rem;
            color: #f1f5f9;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }

        input[type="date"] {
            background: rgba(255, 255, 255, 0.1) !important;
            -webkit-appearance: none;
            appearance: none;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem var(--primary-light);
            color: #f1f5f9;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-select option {
            background: #1e293b;
            color: #f1f5f9;
        }

        /* Alert Messages */
        .alert {
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            backdrop-filter: blur(10px);
        }

        .alert i {
            font-size: 1.5rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-error,
        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary) !important;
            border: none !important;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
            color: #ffffff !important;
        }

        .btn-primary:hover {
            background: color-mix(in srgb, var(--primary) 90%, #000) !important;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3) !important;
        }

        .btn-outline-secondary {
            background: transparent;
            color: #f1f5f9;
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            color: #f1f5f9;
        }

        .btn-outline-warning {
            background: transparent;
            color: #fbbf24;
            border: 2px solid rgba(251, 191, 36, 0.3);
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-warning:hover {
            background: rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.5);
            color: #fbbf24;
        }

        /* Troubleshooting Card */
        .suggestion-card {
            background: rgba(251, 191, 36, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(251, 191, 36, 0.2);
            border-radius: 16px;
            padding: 2rem;
        }

        .suggestion-card .icon-wrapper {
            width: 56px;
            height: 56px;
            background: rgba(251, 191, 36, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .suggestion-card i {
            font-size: 2rem;
            color: #fbbf24;
        }

        .suggestion-card h5 {
            color: #f1f5f9;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .suggestion-card p {
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        /* Form Check */
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-check-label {
            color: #cbd5e0;
            margin-left: 0.5rem;
            cursor: pointer;
        }

        .form-check-label a {
            color: var(--primary);
            text-decoration: none;
        }

        .form-check-label a:hover {
            text-decoration: underline;
        }

        /* Modal */
        .modal-content {
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.75rem 2rem;
            background: linear-gradient(135deg, var(--primary-light), transparent);
        }

        .modal-title {
            font-weight: 700;
            color: #f1f5f9;
            font-size: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
            color: #cbd5e0;
        }

        .modal-body h6 {
            color: #f1f5f9;
            font-weight: 700;
            margin-top: 1rem;
            margin-bottom: 0.75rem;
        }

        .modal-body ul {
            color: #cbd5e0;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Fade in animation */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .booking-container {
                padding: 1rem 0.75rem;
                padding-top: calc(56px + 1rem);
            }

            .page-title {
                font-size: 1.75rem;
            }

            .page-subtitle {
                font-size: 1rem;
            }

            .booking-card {
                padding: 1.5rem;
                border-radius: 16px;
            }

            .section-header h3 {
                font-size: 1.25rem;
            }

            .user-dropdown {
                position: fixed;
                top: 60px;
                right: 10px;
                left: 10px;
                width: auto;
                min-width: unset;
            }
        }

        <?= $settings['custom_css'] ?? '' ?>
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="../index.php">
                <?php
                $logo = $settings['site_logo'];
                if (!empty($logo) && is_string($logo)): ?>
                    <img src="<?= htmlspecialchars($logo[0] === '/' ? $logo : ROOT_PATH . $logo) ?>" alt="<?= htmlspecialchars($settings['company_name'] ?? 'Soreta') ?> logo" style="height:36px; margin-right:8px;">
                <?php else: ?>
                    <i class="bi bi-lightning-charge-fill me-2"></i>
                <?php endif; ?>
                <span><?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="../services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="../about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="../announcements.php">Announcements</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">My Dashboard</a></li>
                    <li class="nav-item">
                        <?php include '../includes/notification-component.php'; ?>
                    </li>
                    <li class="nav-item">
                        <div class="user-avatar-wrapper">
                            <div class="user-avatar" id="userAvatarBtn">
                                <?= strtoupper(substr($userData['name'] ?? 'U', 0, 2)) ?>
                            </div>
                            <div class="user-dropdown" id="userDropdown">
                                <div class="user-dropdown-header">
                                    <div class="user-dropdown-name"><?= htmlspecialchars($userData['name'] ?? 'User') ?></div>
                                    <div class="user-dropdown-email"><?= htmlspecialchars($userData['email'] ?? '') ?></div>
                                </div>
                                <div class="user-dropdown-menu">
                                    <a href="profile.php" class="user-dropdown-item">
                                        <i class="bi bi-person"></i>
                                        <span>My Profile</span>
                                    </a>
                                    <a href="dashboard.php" class="user-dropdown-item">
                                        <i class="bi bi-speedometer2"></i>
                                        <span>Dashboard</span>
                                    </a>
                                    <a href="book-appointment.php" class="user-dropdown-item">
                                        <i class="bi bi-calendar-plus"></i>
                                        <span>Book Appointment</span>
                                    </a>
                                    <div class="user-dropdown-divider"></div>
                                    <a href="../auth/logout.php" class="user-dropdown-item">
                                        <i class="bi bi-box-arrow-right"></i>
                                        <span>Logout</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="booking-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">Book Service Appointment</h1>
            <p class="page-subtitle">Schedule your electronic repair service</p>
        </div>

        <!-- Main Booking Form -->
        <div class="booking-card fade-in">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <form method="POST" id="bookingForm">
                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">

                <!-- Customer Information Section -->
                <div class="section-header">
                    <i class="bi bi-person-circle"></i>
                    <h3>Customer Information</h3>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="customer_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" required
                               value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>"
                               placeholder="Enter your full name">
                    </div>

                    <div class="col-md-6">
                        <label for="customer_contact" class="form-label">Contact Number</label>
                        <input type="tel" class="form-control" id="customer_contact" name="customer_contact"
                               value="<?= htmlspecialchars($_SESSION['user_contact'] ?? '') ?>"
                               placeholder="e.g., 0912-345-6789">
                    </div>

                    <div class="col-12">
                        <label for="street" class="form-label">Street (Optional)</label>
                        <input type="text" class="form-control" id="street" name="street"
                               value="<?= htmlspecialchars($addrParts[0] ?? '') ?>"
                               placeholder="House/Building No., Street Name">
                    </div>

                    <div class="col-md-4">
                        <label for="barangay" class="form-label">Barangay *</label>
                        <input type="text" class="form-control" id="barangay" name="barangay" required
                               value="<?= htmlspecialchars($addrParts[1] ?? '') ?>"
                               placeholder="Enter barangay">
                    </div>

                    <div class="col-md-4">
                        <label for="city" class="form-label">City / Municipality *</label>
                        <input type="text" class="form-control" id="city" name="city" required
                               value="<?= htmlspecialchars($addrParts[2] ?? '') ?>"
                               placeholder="Enter city/municipality">
                    </div>

                    <div class="col-md-4">
                        <label for="province" class="form-label">Province *</label>
                        <input type="text" class="form-control" id="province" name="province" required
                               value="<?= htmlspecialchars($addrParts[3] ?? '') ?>"
                               placeholder="Enter province">
                    </div>
                </div>

                <!-- Product Information Section -->
                <div class="section-header">
                    <i class="bi bi-box-seam"></i>
                    <h3>Product Information</h3>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="brand" class="form-label">Brand *</label>
                        <input type="text" class="form-control" id="brand" name="brand"
                               placeholder="e.g., Samsung, Sharp, Daikin, other" required>
                    </div>

                    <div class="col-md-6">
                        <label for="product" class="form-label">Product *</label>
                        <input type="text" class="form-control" id="product" name="product"
                               placeholder="e.g., Aircon, Washing machine, TV, Ref" required>
                    </div>

                    <div class="col-md-6">
                        <label for="model_number" class="form-label">Model Number *</label>
                        <input type="text" class="form-control" id="model_number" name="model_number"
                               placeholder="e.g., SM-G991B, A2179, OLED43C1PUB" required>
                    </div>

                    <div class="col-md-6">
                        <label for="serial" class="form-label">Serial Number</label>
                        <input type="text" class="form-control" id="serial" name="serial" 
                               placeholder="e.g., RF1K123456">
                    </div>

                    <div class="col-12">
                        <label for="accessories" class="form-label">Accessories</label>
                        <textarea class="form-control" id="accessories" name="accessories" rows="2"
                                  placeholder="e.g., charger, remote control, HDMI cable"></textarea>
                    </div>
                </div>

                <!-- Appointment Details Section -->
                <div class="section-header">
                    <i class="bi bi-calendar-check"></i>
                    <h3>Appointment Details</h3>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="appointment_date" class="form-label">Preferred Date *</label>
                        <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                               max="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="appointment_time" class="form-label">Preferred Time *</label>
                        <select class="form-select" id="appointment_time" name="appointment_time" required>
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

                    <div class="col-md-6">
                        <label for="service_type" class="form-label">Service Type *</label>
                        <select class="form-select" id="service_type" name="service_type" required>
                            <option value="">Select service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= htmlspecialchars($service) ?>"><?= htmlspecialchars($service) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="trouble_description" class="form-label">Trouble Description *</label>
                        <textarea class="form-control" id="trouble_description" name="trouble_description" rows="4" 
                                  placeholder="Describe the issue you're experiencing in detail..." required></textarea>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terms_agree" required>
                        <label class="form-check-label" for="terms_agree">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">terms and conditions</a> *
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex gap-3 justify-content-end flex-wrap">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        <i class="bi bi-calendar-check me-2"></i> Submit Appointment
                    </button>
                </div>
            </form>
        </div>

        <!-- Troubleshooting Suggestion -->
        <div class="suggestion-card fade-in">
            <div class="d-flex align-items-start gap-3">
                <div class="icon-wrapper flex-shrink-0">
                    <i class="bi bi-lightbulb"></i>
                </div>
                <div class="flex-grow-1">
                    <h5>Try Our Troubleshooting Guide First</h5>
                    <p class="mb-3">
                        Many common issues can be resolved quickly using our step-by-step troubleshooting guide. 
                        You might save time and avoid an appointment!
                    </p>
                    <a href="../troubleshooting.php" class="btn btn-outline-warning">
                        <i class="bi bi-wrench me-2"></i> Visit Troubleshooting Guide
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text me-2"></i>
                        Terms and Conditions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Service Agreement</h6>
                    <p>By booking an appointment, you agree to the following terms:</p>
                    <ul>
                        <li>Appointments must be cancelled at least 2 hours in advance</li>
                        <li>Please bring all necessary accessories and power cords</li>
                        <li>Back up your data before service - we're not responsible for data loss</li>
                        <li>Diagnosis fee may apply if repair is not proceeded with</li>
                        <li>Warranty applies only to replaced parts and labor</li>
                    </ul>
                    <h6>Privacy Policy</h6>
                    <p>We respect your privacy and will protect your personal information according to our privacy policy.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <section id="contact" class="py-5 bg-dark text-white mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></h5>
                    <?php
                    $company_address = $settings['company_address'] ?? '';
                    $company_phone = $settings['company_phone'] ?? '';
                    $company_email = $settings['company_email'] ?? '';
                    ?>
                    <?php if (!empty($company_address)): ?>
                        <p class="mb-1"><i class="bi bi-geo-alt-fill me-2"></i><?= nl2br(htmlspecialchars($company_address)) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($company_phone)): ?>
                        <p class="mb-1"><i class="bi bi-telephone-fill me-2"></i><?= htmlspecialchars($company_phone) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($company_email)): ?>
                        <p class="mb-0"><i class="bi bi-envelope-fill me-2"></i><a href="mailto:<?= htmlspecialchars($company_email) ?>" class="text-white"><?= htmlspecialchars($company_email) ?></a></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-white"><?= htmlspecialchars($settings['footer_text'] ?? '&copy; ' . date('Y') . ' ' . ($settings['company_name'] ?? 'Soreta Electronics')) ?></p>
                    <?php if (!empty($settings['social_facebook']) || !empty($settings['social_messenger'])): ?>
                        <div class="mt-2">
                            <?php if (!empty($settings['social_facebook'])): ?>
                                <a href="<?= htmlspecialchars($settings['social_facebook']) ?>" target="_blank" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($settings['social_messenger'])): ?>
                                <a href="<?= htmlspecialchars($settings['social_messenger']) ?>" target="_blank" class="text-white"><i class="bi bi-messenger"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User Avatar Dropdown Toggle
        const userAvatarBtn = document.getElementById('userAvatarBtn');
        const userDropdown = document.getElementById('userDropdown');

        if (userAvatarBtn && userDropdown) {
            userAvatarBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!userAvatarBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });

            userDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Fade in on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

        // Form submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Booking...';
        });

        // Date validation - disable weekends
        const appointmentDate = document.getElementById('appointment_date');
        if (appointmentDate) {
            appointmentDate.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const day = selectedDate.getDay();

                if (day === 0 || day === 6) {
                    alert('We are closed on weekends. Please select a weekday.');
                    this.value = '';
                }
            });
        }
    </script>

    <?php if (!empty($settings['google_analytics'])): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($settings['google_analytics']) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?= htmlspecialchars($settings['google_analytics']) ?>');
        </script>
    <?php endif; ?>

    <?php if (!empty($settings['facebook_pixel'])): ?>
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '<?= htmlspecialchars($settings['facebook_pixel']) ?>');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=<?= htmlspecialchars($settings['facebook_pixel']) ?>&ev=PageView&noscript=1"
        /></noscript>
    <?php endif; ?>
</body>
</html>