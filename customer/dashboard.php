<?php
require_once '../includes/config.php';
checkAuth();

if (isAdmin()) {
    redirect('admin/dashboard.php');
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

$appointmentManager = new AppointmentManager($pdo);
$customerAppointments = $appointmentManager->getCustomerAppointments($_SESSION['user_id']);

// Get statistics
$totalAppointments = count($customerAppointments);
$scheduledCount = 0;
$completedCount = 0;
$cancelledCount = 0;

foreach ($customerAppointments as $appointment) {
    $status = strtolower($appointment['status']);
    if (in_array($status, ['scheduled', 'rescheduled'])) {
        $scheduledCount++;
    } elseif ($status === 'completed') {
        $completedCount++;
    } elseif ($status === 'cancelled') {
        $cancelledCount++;
    }
}

// Get next upcoming appointment
$upcomingAppointment = null;
foreach ($customerAppointments as $appointment) {
    if (in_array(strtolower($appointment['status']), ['scheduled', 'rescheduled'])) {
        $upcomingAppointment = $appointment;
        break;
    }
}

// Get user feedback
$stmt = $pdo->prepare("
    SELECT f.id, f.rating, f.comment, f.created_at, f.troubleshooting_guide_id, tg.title as guide_title
    FROM troubleshooting_feedback f
    JOIN troubleshooting_guide tg ON f.troubleshooting_guide_id = tg.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$userFeedback = $stmt->fetchAll();

// Get user info for avatar
$userStmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userData = $userStmt->fetch();

$isLoggedIn = true;
$userRole = 'customer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - <?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></title>
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

        /* Dark background for entire page */
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #f1f5f9;
        }

        /* Keep navbar links visible on dark background */
        .navbar-nav .nav-link {
            color: #f1f5f9 !important;
            transition: color 0.3s ease;
        }

        .navbar-brand,
        .navbar-brand span,
        .navbar-brand i {
            color: #ffffff !important;
            transition: color 0.3s ease;
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

        /* Main Container */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1rem;
            padding-top: 2rem;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
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

        /* Stats Cards - Glassmorphism */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-light), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card > * {
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-icon-primary {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 80%, #000));
            color: white;
        }

        .stat-icon-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .stat-icon-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .stat-icon-info {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 80%, #000));
            color: white;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #cbd5e0;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        /* Next Appointment Highlight */
        .next-appointment {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, #000));
            color: white;
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .next-appointment::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.05" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            pointer-events: none;
        }

        .next-appointment > * {
            position: relative;
            z-index: 1;
        }

        .next-label {
            font-size: 0.875rem;
            opacity: 0.9;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .next-job-order {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .next-device {
            font-size: 1.125rem;
            opacity: 0.95;
            margin-bottom: 2rem;
        }

        .next-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-label {
            font-size: 0.8125rem;
            opacity: 0.85;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Button Styles */
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

        .btn-white {
            background: white;
            color: var(--primary);
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-white:hover {
            background: #f8f9fa;
            color: var(--primary);
            transform: translateY(-2px);
        }

        .btn-outline-white {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 0.75rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-white:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-color: white;
        }

        .btn-ripple {
            position: relative;
            overflow: hidden;
        }

        .btn-ripple::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-ripple:active::after {
            width: 220px;
            height: 220px;
        }

        /* Cards - Glassmorphism - Fully Transparent */
        .dashboard-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-light), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        .dashboard-card > * {
            position: relative;
            z-index: 1;
        }

        /* Ensure card header and body are transparent */
        .dashboard-card .card-header {
            background: transparent !important;
        }

        .dashboard-card .card-body {
            background: transparent !important;
            padding: 0;
        }

        /* Make sure section titles inside cards are visible */
        .dashboard-card .section-title {
            color: #f1f5f9;
        }

        /* Ensure text inside cards is visible */
        .dashboard-card .text-muted {
            color: #94a3b8 !important;
        }

        /* Collapsible Card */
        .collapsible-card {
            cursor: pointer;
        }

        .collapsible-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: transparent !important;
        }

        .collapsible-card .collapse-icon {
            transition: transform 0.3s ease;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .collapsible-card.collapsed .collapse-icon {
            transform: rotate(-90deg);
        }

        .collapsible-card .card-body {
            max-height: 1000px;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .collapsible-card.collapsed .card-body {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        /* Section Title */
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary);
            font-size: 2rem;
        }

        /* Filter Bar - Glassmorphism */
        .filter-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .filter-label {
            font-weight: 600;
            color: #f1f5f9;
        }

        .form-select {
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: #f1f5f9;
        }

        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem var(--primary-light);
            background: rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
        }

        .form-select option {
            background: #1e293b;
            color: #f1f5f9;
        }

        /* Appointment Card - Glassmorphism */
        .appointment-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .appointment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-light), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .appointment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .appointment-card:hover::before {
            opacity: 1;
        }

        .appointment-card > * {
            position: relative;
            z-index: 1;
        }

        .appointment-header {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
            align-items: flex-start;
        }

        .appointment-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            flex-shrink: 0;
            color: var(--primary);
            transition: transform 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .appointment-card:hover .appointment-icon {
            transform: scale(1.1) rotate(5deg);
            background: rgba(255, 255, 255, 0.15);
        }

        .appointment-info {
            flex: 1;
        }

        .appointment-info h6 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: #f1f5f9;
        }

        .appointment-device {
            color: #cbd5e0;
            font-size: 1rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .appointment-meta {
            display: flex;
            gap: 1.75rem;
            flex-wrap: wrap;
            font-size: 0.9375rem;
            color: #94a3b8;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            color: var(--primary);
            font-size: 1.125rem;
        }

        /* Badges - More visible on dark background */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 24px;
            font-size: 0.8125rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-scheduled {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .badge-in-progress {
            background: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-completed {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge-rescheduled {
            background: rgba(139, 92, 246, 0.2);
            color: #c4b5fd;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        /* Feedback Item */
        .feedback-item {
            padding: 1.25rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .feedback-item:last-child {
            border-bottom: none;
        }

        .feedback-title {
            font-size: 1.0625rem;
            font-weight: 600;
            color: #f1f5f9;
            text-decoration: none;
            transition: color 0.3s ease;
            display: block;
            margin-bottom: 0.5rem;
        }

        .feedback-title:hover {
            color: var(--primary);
        }

        .feedback-comment {
            color: #cbd5e0;
            font-size: 0.9375rem;
            margin: 0.75rem 0;
            line-height: 1.6;
        }

        .feedback-date {
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .star-rating i {
            color: #fbbf24;
            font-size: 1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #cbd5e0;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: rgba(255, 255, 255, 0.2);
        }

        .empty-state h4 {
            color: #f1f5f9;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 1.0625rem;
            color: #cbd5e0;
        }

        /* Modal - Dark theme */
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
            background: transparent;
        }

        .modal-body h6 {
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 1rem;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body h6 i {
            color: var(--primary);
        }

        .modal-body p {
            color: #cbd5e0;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .modal-body strong {
            color: #f1f5f9;
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem 0.75rem;
                padding-top: 1rem;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .page-subtitle {
                font-size: 1rem;
            }

            /* 2x2 grid for stats on mobile */
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-icon {
                width: 48px;
                height: 48px;
                font-size: 1.5rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }

            .next-appointment {
                padding: 1.75rem;
            }

            .next-job-order {
                font-size: 1.5rem;
            }

            .next-device {
                font-size: 1rem;
            }

            .next-details {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }

            .detail-value {
                font-size: 1.125rem;
            }

            .section-title {
                font-size: 1.375rem;
            }

            .section-title i {
                font-size: 1.5rem;
            }

            .filter-bar {
                padding: 1rem;
            }

            .form-select {
                width: 100%;
                font-size: 0.875rem;
            }

            .appointment-card {
                padding: 1.25rem;
            }

            .appointment-header {
                gap: 1rem;
            }

            .appointment-icon {
                width: 48px;
                height: 48px;
                font-size: 1.5rem;
            }

            .appointment-info h6 {
                font-size: 1.125rem;
            }

            .appointment-meta {
                gap: 1rem;
            }

            .meta-item {
                font-size: 0.875rem;
            }

            .dashboard-card {
                padding: 1.5rem;
            }

            /* Sidebar cards order on mobile */
            .mobile-order-1 { order: 1; }
            .mobile-order-2 { order: 2; }
            .mobile-order-3 { order: 3; }
        }

        <?= $settings['custom_css'] ?? '' ?>
    </style>
    <?php include '../includes/mega-navbar-css.php'; ?>
    <style>
        /* Override mega-navbar for dark page */
        .mega-navbar { 
            background: rgba(255,255,255,0.08) !important; 
            border-color: rgba(255,255,255,0.1) !important; 
        }
        .mega-navbar.scrolled { 
            background: rgba(255,255,255,0.12) !important; 
        }
        .mega-navbar-brand-text, .mega-nav-link { 
            color: #f1f5f9 !important; 
        }
        .mega-nav-link:hover, .mega-nav-link.active { 
            color: var(--primary) !important; 
            background: rgba(255,255,255,0.1) !important; 
        }
        .mega-mobile-toggle { 
            color: #f1f5f9 !important; 
        }
        .mega-nav-btn-ghost { 
            color: #f1f5f9 !important; 
        }
        .mega-user-avatar { 
            border-color: rgba(255,255,255,0.3) !important; 
        }
    </style>
</head>
<body>
    <?php include '../includes/mega-navbar.php'; ?>

    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">Welcome Back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Customer') ?>!</h1>
            <p class="page-subtitle">Track and manage your repair and installation appointments</p>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-label">Total Appointments</div>
                <div class="stat-value"><?= $totalAppointments ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-label">Scheduled</div>
                <div class="stat-value"><?= $scheduledCount ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?= $completedCount ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-warning">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-label">Cancelled</div>
                <div class="stat-value"><?= $cancelledCount ?></div>
            </div>
        </div>

        <!-- Action Button -->
        <div class="mb-4 fade-in">
            <a href="book-appointment.php" class="btn btn-primary btn-lg btn-ripple">
                <i class="bi bi-plus-circle me-2"></i> Book New Appointment
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8 mobile-order-2">
                <!-- Next Appointment Highlight -->
                <?php if ($upcomingAppointment): ?>
                    <div class="next-appointment fade-in">
                        <div class="next-label">
                            <i class="bi bi-star-fill"></i>
                            Next Appointment
                        </div>
                        <div class="next-job-order"><?= htmlspecialchars($upcomingAppointment['job_order_no']) ?></div>
                        <div class="next-device"><?= htmlspecialchars($upcomingAppointment['product_details']) ?></div>
                        <div class="next-details">
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="bi bi-calendar3 me-1"></i>Date
                                </div>
                                <div class="detail-value"><?= date('M j, Y', strtotime($upcomingAppointment['appointment_date'])) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="bi bi-clock me-1"></i>Time
                                </div>
                                <div class="detail-value"><?= date('g:i A', strtotime($upcomingAppointment['appointment_time'])) ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="bi bi-wrench me-1"></i>Service Type
                                </div>
                                <div class="detail-value"><?= htmlspecialchars($upcomingAppointment['service_type']) ?></div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn-white view-details" 
                                    data-appointment='<?= htmlspecialchars(json_encode($upcomingAppointment), ENT_QUOTES) ?>'>
                                <i class="bi bi-eye me-2"></i>View Details
                            </button>
                            <a href="reschedule-appointment.php?id=<?= $upcomingAppointment['id'] ?>" 
                               class="btn-outline-white">
                                <i class="bi bi-calendar2-week me-2"></i>Reschedule
                            </a>
                            <a href="cancel-appointment.php?id=<?= $upcomingAppointment['id'] ?>" 
                               class="btn-outline-white"
                               onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Section Title -->
                <h3 class="section-title fade-in">
                    <i class="bi bi-calendar-check"></i>
                    All Appointments
                </h3>

                <!-- Filter Bar -->
                <div class="filter-bar fade-in">
                    <span class="filter-label">Filter:</span>
                    <select class="form-select" id="statusFilter" style="max-width: 200px;">
                        <option value="all">All Status</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select class="form-select" id="serviceFilter" style="max-width: 200px;">
                        <option value="all">All Services</option>
                        <option value="repair">Repair</option>
                        <option value="installation">Installation</option>
                    </select>
                </div>

                <!-- Appointments List -->
                <?php if (empty($customerAppointments)): ?>
                    <div class="dashboard-card fade-in">
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <h4>No appointments yet</h4>
                            <p class="mb-4">Book your first service appointment to get started.</p>
                            <a href="book-appointment.php" class="btn btn-primary btn-lg btn-ripple">
                                <i class="bi bi-plus-circle me-2"></i>Book Appointment
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="appointmentsList">
                        <?php foreach ($customerAppointments as $appointment): ?>
                            <div class="appointment-card fade-in" 
                                 data-status="<?= strtolower($appointment['status']) ?>"
                                 data-service="<?= strtolower($appointment['service_type']) ?>">
                                <div class="appointment-header">
                                    <div class="appointment-icon">
                                        <i class="bi bi-<?= $appointment['service_type'] === 'Installation' ? 'box-seam' : 'wrench' ?>"></i>
                                    </div>
                                    <div class="appointment-info">
                                        <h6><?= htmlspecialchars($appointment['job_order_no']) ?></h6>
                                        <div class="appointment-device"><?= htmlspecialchars($appointment['product_details']) ?></div>
                                        <div class="appointment-meta">
                                            <div class="meta-item">
                                                <i class="bi bi-calendar3"></i>
                                                <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                                            </div>
                                            <div class="meta-item">
                                                <i class="bi bi-clock"></i>
                                                <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                            </div>
                                            <div class="meta-item">
                                                <i class="bi bi-<?= $appointment['service_type'] === 'Installation' ? 'box-seam' : 'wrench' ?>"></i>
                                                <?= htmlspecialchars($appointment['service_type']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge badge-<?= strtolower($appointment['status']) ?>">
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary btn-sm view-details" 
                                            data-appointment='<?= htmlspecialchars(json_encode($appointment), ENT_QUOTES) ?>'>
                                        <i class="bi bi-eye me-1"></i> View Details
                                    </button>
                                    <?php if (strtolower($appointment['status']) === 'scheduled'): ?>
                                        <a href="reschedule-appointment.php?id=<?= $appointment['id'] ?>" 
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-calendar2-week me-1"></i> Reschedule
                                        </a>
                                        <a href="cancel-appointment.php?id=<?= $appointment['id'] ?>" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                            <i class="bi bi-x-circle me-1"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 mobile-order-1">
                <!-- My Feedback - Collapsible on Mobile -->
                <div class="dashboard-card fade-in collapsible-card d-lg-block" id="feedbackCard">
                    <div class="card-header d-lg-none" onclick="toggleCard('feedbackCard')">
                        <h5 class="section-title mb-0" style="font-size: 1.375rem;">
                            <i class="bi bi-star"></i>
                            My Feedback
                        </h5>
                        <i class="bi bi-chevron-down collapse-icon"></i>
                    </div>
                    <h5 class="section-title d-none d-lg-flex" style="font-size: 1.375rem;">
                        <i class="bi bi-star"></i>
                        My Feedback
                    </h5>
                    <div class="card-body">
                        <?php if (empty($userFeedback)): ?>
                            <div class="text-center py-3">
                                <p class="text-muted mb-3">You haven't submitted any feedback yet.</p>
                                <a href="../troubleshooting.php" class="btn btn-primary btn-sm w-100 btn-ripple">
                                    <i class="bi bi-tools me-2"></i> Browse Guides
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($userFeedback as $feedback): ?>
                                <div class="feedback-item">
                                    <a href="../troubleshooting.php?id=<?= $feedback['troubleshooting_guide_id'] ?>" 
                                       class="feedback-title">
                                        <?= htmlspecialchars($feedback['guide_title']) ?>
                                    </a>
                                    <div class="star-rating mt-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $feedback['rating'] ? '-fill' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if ($feedback['comment']): ?>
                                        <p class="feedback-comment">
                                            <?= htmlspecialchars(substr($feedback['comment'], 0, 100)) ?>
                                            <?php if (strlen($feedback['comment']) > 100) echo '...'; ?>
                                        </p>
                                    <?php endif; ?>
                                    <small class="feedback-date">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= date('M j, Y', strtotime($feedback['created_at'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Legend - Collapsible on Mobile -->
                <div class="dashboard-card fade-in collapsible-card d-lg-block" id="legendCard">
                    <div class="card-header d-lg-none" onclick="toggleCard('legendCard')">
                        <h5 class="section-title mb-0" style="font-size: 1.375rem;">
                            <i class="bi bi-info-circle"></i>
                            Status Legend
                        </h5>
                        <i class="bi bi-chevron-down collapse-icon"></i>
                    </div>
                    <h5 class="section-title d-none d-lg-flex" style="font-size: 1.375rem;">
                        <i class="bi bi-info-circle"></i>
                        Status Legend
                    </h5>
                    <div class="card-body">
                        <div class="mb-3">
                            <span class="status-badge badge-scheduled me-2">Scheduled</span>
                            <small class="text-muted d-block mt-1" style="color: #94a3b8 !important;">Appointment confirmed</small>
                        </div>
                        <div class="mb-3">
                            <span class="status-badge badge-in-progress me-2">In Progress</span>
                            <small class="text-muted d-block mt-1" style="color: #94a3b8 !important;">Currently being serviced</small>
                        </div>
                        <div class="mb-3">
                            <span class="status-badge badge-completed me-2">Completed</span>
                            <small class="text-muted d-block mt-1" style="color: #94a3b8 !important;">Service finished</small>
                        </div>
                        <div class="mb-3">
                            <span class="status-badge badge-cancelled me-2">Cancelled</span>
                            <small class="text-muted d-block mt-1" style="color: #94a3b8 !important;">Appointment cancelled</small>
                        </div>
                        <div>
                            <span class="status-badge badge-rescheduled me-2">Rescheduled</span>
                            <small class="text-muted d-block mt-1" style="color: #94a3b8 !important;">Date/time changed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-text me-2"></i>
                        Appointment Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentDetails"></div>
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
    <?php include '../includes/mega-navbar-js.php'; ?>
    <script>
        // Collapsible card toggle for mobile
        function toggleCard(cardId) {
            const card = document.getElementById(cardId);
            if (window.innerWidth < 992) {
                card.classList.toggle('collapsed');
            }
        }

        // Initialize collapsed state on mobile
        function initCollapsibleCards() {
            if (window.innerWidth < 992) {
                document.getElementById('feedbackCard').classList.add('collapsed');
                document.getElementById('legendCard').classList.add('collapsed');
            } else {
                document.getElementById('feedbackCard').classList.remove('collapsed');
                document.getElementById('legendCard').classList.remove('collapsed');
            }
        }

        // Run on load and resize
        initCollapsibleCards();
        window.addEventListener('resize', initCollapsibleCards);

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

        // View appointment details
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const appointment = JSON.parse(this.dataset.appointment);
                document.getElementById('appointmentDetails').innerHTML = `
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6><i class="bi bi-file-text me-2"></i>Job Information</h6>
                            <p><strong>Job Order:</strong> ${appointment.job_order_no}</p>
                            <p><strong>Service Type:</strong> ${appointment.service_type}</p>
                            <p><strong>Status:</strong> <span class="status-badge badge-${appointment.status.toLowerCase().replace(' ', '-')}">${appointment.status}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-calendar-check me-2"></i>Schedule</h6>
                            <p><strong>Date:</strong> ${new Date(appointment.appointment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            <p><strong>Time:</strong> ${appointment.appointment_time}</p>
                            ${appointment.technician_name ? `<p><strong>Technician:</strong> ${appointment.technician_name}</p>` : ''}
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6><i class="bi bi-box me-2"></i>Product Details</h6>
                            <p>${appointment.product_details}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="bi bi-exclamation-triangle me-2"></i>Problem Description</h6>
                            <p>${appointment.trouble_description}</p>
                        </div>
                    </div>
                    ${appointment.accessories ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="bi bi-plug me-2"></i>Accessories</h6>
                            <p>${appointment.accessories}</p>
                        </div>
                    </div>` : ''}
                    ${appointment.admin_notes ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6><i class="bi bi-sticky me-2"></i>Admin Notes</h6>
                            <p>${appointment.admin_notes}</p>
                        </div>
                    </div>` : ''}
                `;
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            });
        });

        // Filter functionality
        const statusFilter = document.getElementById('statusFilter');
        const serviceFilter = document.getElementById('serviceFilter');

        function filterAppointments() {
            const statusValue = statusFilter.value;
            const serviceValue = serviceFilter.value;
            const cards = document.querySelectorAll('.appointment-card');

            let visibleCount = 0;

            cards.forEach(card => {
                const cardStatus = card.dataset.status;
                const cardService = card.dataset.service;

                const statusMatch = statusValue === 'all' || cardStatus === statusValue;
                const serviceMatch = serviceValue === 'all' || cardService === serviceValue;

                if (statusMatch && serviceMatch) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show empty state if no results
            const appointmentsList = document.getElementById('appointmentsList');
            const existingEmpty = appointmentsList ? appointmentsList.querySelector('.empty-state') : null;
            
            if (visibleCount === 0 && appointmentsList && !existingEmpty) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'dashboard-card';
                emptyDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-filter"></i>
                        <h4>No appointments match your filters</h4>
                        <p>Try adjusting your filter criteria.</p>
                    </div>
                `;
                appointmentsList.appendChild(emptyDiv);
            } else if (visibleCount > 0 && existingEmpty) {
                existingEmpty.parentElement.remove();
            }
        }

        if (statusFilter) statusFilter.addEventListener('change', filterAppointments);
        if (serviceFilter) serviceFilter.addEventListener('change', filterAppointments);

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offset = 70;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
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