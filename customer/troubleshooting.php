<?php
session_start();
require_once '../includes/config.php';
checkAuth();

if (isAdmin()) {
    redirect('admin/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

// Get company settings (schema-agnostic: supports setting_key/setting_value or key/value)
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

// Function to recursively flatten values to strings
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

// Sanitize all settings
foreach ($settings as $key => $val) {
    $settings[$key] = flatten_to_string($val);
}

$value = $settings['favicon'] ?? '';
$settings['favicon'] = is_array($value) ? (string)($value[0] ?? '') : (string)$value;

$value = $settings['site_logo'] ?? '';
$settings['site_logo'] = is_array($value) ? (string)($value[0] ?? '') : (string)$value;

$value = $settings['hero_image'] ?? '';
$settings['hero_image'] = is_array($value) ? (string)($value[0] ?? '') : (string)$value;

// Parse JSON settings
$servicesRaw = $settings['services'] ?? '[]';
if (is_string($servicesRaw)) {
    $services = json_decode($servicesRaw, true) ?: [];
} elseif (is_array($servicesRaw)) {
    $services = $servicesRaw;
} else {
    $services = [];
}
$services = array_map(function($s) {
    return is_string($s) ? $s : (is_array($s) ? implode(', ', $s) : strval($s));
}, $services);
$business_hours = json_decode($settings['business_hours'] ?? '{}', true) ?: [];

// Parse announcements
$announcements = [];
try {
    $stmt = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY display_order ASC, date DESC, created_at DESC LIMIT 6");
    $announcements = $stmt->fetchAll();
} catch (Exception $e) {
    $announcementsRaw = $settings['announcements'] ?? '[]';
    if (is_string($announcementsRaw)) {
        $announcements = json_decode($announcementsRaw, true) ?: [];
    } elseif (is_array($announcementsRaw)) {
        $announcements = $announcementsRaw;
    } else {
        $announcements = [];
    }
}

// Public-facing settings
$company_about = $settings['company_about'] ?? '';
$company_phone = $settings['company_phone'] ?? '';
$company_address = $settings['company_address'] ?? '';
$company_email = $settings['company_email'] ?? '';

// Logged-in state
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'customer';

// Get troubleshooting categories (top-level nodes)
$stmt = $pdo->query("
    SELECT * FROM troubleshooting_guide 
    WHERE parent_id IS NULL AND is_active = 1 
    ORDER BY display_order
");
$categories = $stmt->fetchAll();

// Get specific guide if ID provided
$guideId = $_GET['id'] ?? null;
$currentGuide = null;
$children = [];
$parentGuide = null;

if ($guideId) {
    // Get current guide
    $stmt = $pdo->prepare("SELECT * FROM troubleshooting_guide WHERE id = ? AND is_active = 1");
    $stmt->execute([$guideId]);
    $currentGuide = $stmt->fetch();

    if ($currentGuide) {
        // Get children for decision tree
        $stmt = $pdo->prepare("
            SELECT * FROM troubleshooting_guide
            WHERE parent_id = ? AND is_active = 1
            ORDER BY display_order
        ");
        $stmt->execute([$guideId]);
        $children = $stmt->fetchAll();

        // Fix: Ensure fix_steps is fetched fully (not truncated)
        $stmt = $pdo->prepare("SELECT fix_steps, preventive_tip FROM troubleshooting_guide WHERE id = ?");
        $stmt->execute([$guideId]);
        $fixData = $stmt->fetch();
        if ($fixData) {
            $currentGuide['fix_steps'] = $fixData['fix_steps'];
            $currentGuide['preventive_tip'] = $fixData['preventive_tip'];
        }

        // Get parent guide for breadcrumb
        if ($currentGuide['parent_id']) {
            $stmt = $pdo->prepare("SELECT * FROM troubleshooting_guide WHERE id = ?");
            $stmt->execute([$currentGuide['parent_id']]);
            $parentGuide = $stmt->fetch();
        }

        // Check if user has already submitted feedback for this guide and fetch details
        $existingFeedback = null;
        $hasFeedback = false;
        if (isset($_SESSION['user_id']) && $guideId) {
            $stmt = $pdo->prepare("SELECT id, rating, comment, anonymous FROM troubleshooting_feedback WHERE user_id = ? AND troubleshooting_guide_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id'], $guideId]);
            $existingFeedback = $stmt->fetch();
            $hasFeedback = $existingFeedback !== false;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Troubleshooting Guide - Soreta Electronics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/unified-theme.css" rel="stylesheet">
    <?php include '../includes/mega-navbar-css.php'; ?>
</head>
<body class="troubleshooting-page">
    <?php include '../includes/mega-navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar Categories -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Problem Categories</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="troubleshooting.php" 
                           class="list-group-item list-group-item-action <?= !$currentGuide ? 'active' : '' ?>">
                            <i class="bi bi-house me-2"></i>All Categories
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a href="troubleshooting.php?id=<?= $category['id'] ?>" 
                               class="list-group-item list-group-item-action <?= $currentGuide && $currentGuide['id'] == $category['id'] ? 'active' : '' ?>">
                                <i class="bi bi-<?= getCategoryIcon($category['title']) ?> me-2"></i>
                                <?= htmlspecialchars($category['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Help -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">Need More Help?</h6>
                        <p class="card-text text-muted small">If our guide doesn't solve your issue, book a professional service appointment.</p>
                        <a href="book-appointment.php" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-calendar-plus"></i> Book Appointment
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if (!$currentGuide): ?>
                    <!-- Category List View -->
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title mb-0">Troubleshooting Guide</h2>
                            <p class="text-muted mb-0">Select a category to find solutions for common electronic problems</p>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <?php foreach ($categories as $category): ?>
                                    <div class="col-md-6">
                                        <div class="card category-card h-100">
                                            <div class="card-body text-center">
                                                <div class="category-icon mb-3">
                                                    <i class="bi bi-<?= getCategoryIcon($category['title']) ?>"></i>
                                                </div>
                                                <h5 class="card-title"><?= htmlspecialchars($category['title']) ?></h5>
                                                <p class="card-text text-muted">
                                                    <?= strlen($category['description']) > 100 ? 
                                                        substr($category['description'], 0, 100) . '...' : 
                                                        $category['description'] ?>
                                                </p>
                                                <a href="troubleshooting.php?id=<?= $category['id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    Explore Solutions
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Guide Detail View -->
                    <div class="card">
                        <div class="card-header">
                            <!-- Breadcrumb -->
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="troubleshooting.php">Troubleshooting</a>
                                    </li>
                                    <?php if ($parentGuide): ?>
                                        <li class="breadcrumb-item">
                                            <a href="troubleshooting.php?id=<?= $parentGuide['id'] ?>">
                                                <?= htmlspecialchars($parentGuide['title']) ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <li class="breadcrumb-item active"><?= htmlspecialchars($currentGuide['title']) ?></li>
                                </ol>
                            </nav>
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?= htmlspecialchars($currentGuide['title']) ?></h3>
                            <p class="lead"><?= nl2br(htmlspecialchars($currentGuide['description'])) ?></p>

                            <?php if (!empty($children)): ?>
                                <!-- Decision Tree - Next Steps -->
                                <div class="decision-tree mt-5">
                                    <h5>What best describes your specific issue?</h5>
                                    <div class="row g-3 mt-3">
                                        <?php foreach ($children as $child): ?>
                                            <div class="col-md-6">
                                                <a href="troubleshooting.php?id=<?= $child['id'] ?>" 
                                                   class="card decision-card h-100 text-decoration-none">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?= htmlspecialchars($child['title']) ?></h6>
                                                        <p class="card-text text-muted small">
                                                            <?= strlen($child['description']) > 100 ? 
                                                                substr($child['description'], 0, 100) . '...' : 
                                                                $child['description'] ?>
                                                        </p>
                                                        <div class="text-primary">
                                                            <small>View solution <i class="bi bi-arrow-right"></i></small>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php elseif (!empty(trim($currentGuide['fix_steps']))): ?>
                                <!-- Solution View -->
                                <div class="solution-view mt-5">
                                    <div class="solution-steps">
                                        <h5>Fix Steps:</h5>
                                        <div class="steps-container">
                                            <?php $steps = explode("\n", $currentGuide['fix_steps']); ?>
                                            <?php foreach ($steps as $index => $step): ?>
                                                <?php if (trim($step)): ?>
                                                    <div class="step-item">
                                                        <div class="step-number"><?= $index + 1 ?></div>
                                                        <div class="step-content">
                                                            <?= nl2br(htmlspecialchars(trim($step))) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <?php if ($currentGuide['preventive_tip']): ?>
                                        <div class="preventive-tip mt-5">
                                            <div class="alert alert-info">
                                                <h6><i class="bi bi-lightbulb me-2"></i>Preventive Tip</h6>
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($currentGuide['preventive_tip'])) ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Feedback Section -->
                            <?php if ($currentGuide): ?>
                            <div class="feedback-section mt-5">
                                <hr>
                                <?php if ($hasFeedback): ?>
                                    <h5>Edit Your Feedback</h5>
                                    <form id="feedbackForm" class="mt-3">
                                        <input type="hidden" name="troubleshooting_guide_id" value="<?= $currentGuide['id'] ?>">
                                        <input type="hidden" name="edit_id" value="<?= $existingFeedback['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">

                                        <div class="rating-stars mb-3">
                                            <?php $currentRating = $existingFeedback['rating']; ?>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="rating-star<?= $i <= $currentRating ? ' active' : '' ?>" data-rating="<?= $i ?>">
                                                    <i class="bi bi-star"></i>
                                                </span>
                                            <?php endfor; ?>
                                            <input type="hidden" name="rating" id="rating" value="<?= $currentRating ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="comment" class="form-label">Optional Comment</label>
                                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Any additional feedback or suggestions..."><?= htmlspecialchars($existingFeedback['comment']) ?></textarea>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="anonymous" id="anonymous" value="1"<?= $existingFeedback['anonymous'] ? ' checked' : '' ?>>
                                            <label class="form-check-label" for="anonymous">
                                                Post anonymously (hide my name publicly)
                                            </label>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Update Feedback</button>
                                    </form>
                                <?php else: ?>
                                    <h5>Was this guide helpful?</h5>
                                    <form id="feedbackForm" class="mt-3">
                                        <input type="hidden" name="troubleshooting_guide_id" value="<?= $currentGuide['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">

                                        <div class="rating-stars mb-3">
                                            <?php $currentRating = 0; ?>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="rating-star<?= $i <= $currentRating ? ' active' : '' ?>" data-rating="<?= $i ?>">
                                                    <i class="bi bi-star"></i>
                                                </span>
                                            <?php endfor; ?>
                                            <input type="hidden" name="rating" id="rating" value="<?= $currentRating ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="comment" class="form-label">Optional Comment</label>
                                            <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Any additional feedback or suggestions..."></textarea>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="anonymous" id="anonymous" value="1">
                                            <label class="form-check-label" for="anonymous">
                                                Post anonymously (hide my name publicly)
                                            </label>
                                        </div>

                                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                    </form>
                                <?php endif; ?>

                                <!-- Public Feedback Display -->
                                <div class="public-feedback mt-5">
                                    <h5>Recent Feedback</h5>
                                    <?php
                                    // Fetch recent feedback for this guide
                                    $stmt = $pdo->prepare("
                                        SELECT f.*, u.name as customer_name 
                                        FROM troubleshooting_feedback f 
                                        LEFT JOIN users u ON f.user_id = u.id 
                                        WHERE f.troubleshooting_guide_id = ? 
                                        ORDER BY f.created_at DESC 
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$currentGuide['id']]);
                                    $publicFeedback = $stmt->fetchAll();

                                    if (!empty($publicFeedback)): ?>
                                        <div class="feedback-list mt-3">
                                            <?php foreach ($publicFeedback as $fb): ?>
                                                <div class="feedback-item mb-3 p-3 border rounded">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <strong><?= $fb['anonymous'] ? 'Anonymous' : htmlspecialchars($fb['customer_name']) ?></strong>
                                                            <small class="text-muted ms-2"><?= date('M j, Y', strtotime($fb['created_at'])) ?></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?= $i <= $fb['rating'] ? '-fill text-warning' : '' ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($fb['comment'])): ?>
                                                        <p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($fb['comment'])) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No feedback yet for this guide.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
    /* Troubleshooting Page Styles - Cross-browser compatible */
    .troubleshooting-page {
        /* Base theme variables for better browser support */
        --page-bg: #ffffff;
        --page-text: #0f172a;
        --page-surface: #f8fafc;
        --page-border: #e2e8f0;
        --page-primary: #2563eb;
        --page-muted: #6b7280;
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        .troubleshooting-page {
            --page-bg: #0f172a;
            --page-text: #f8fafc;
            --page-surface: #1e293b;
            --page-border: #334155;
            --page-primary: #3b82f6;
            --page-muted: #94a3b8;
        }
    }

    /* Apply theme colors without excessive !important */
    .troubleshooting-page .navbar {
        background: var(--page-bg);
        border-bottom: 1px solid var(--page-border);
        color: var(--page-text);
    }

    .troubleshooting-page .navbar-brand,
    .troubleshooting-page .navbar-text,
    .troubleshooting-page .nav-link {
        color: var(--page-text);
    }

    .troubleshooting-page .card {
        background: var(--page-bg);
        border: 1px solid var(--page-border);
        color: var(--page-text);
    }

    .troubleshooting-page .card-header {
        background: var(--page-surface);
        color: var(--page-text);
        border-bottom: 1px solid var(--page-border);
    }

    .troubleshooting-page .card-body {
        background: var(--page-bg);
        color: var(--page-text);
    }

    .troubleshooting-page .list-group-item {
        background: var(--page-bg);
        color: var(--page-text);
        border-color: var(--page-border);
    }

    .troubleshooting-page .list-group-item.active {
        background: var(--page-primary);
        color: white;
    }

    .troubleshooting-page .list-group-item.active * {
        color: white;
    }

    .troubleshooting-page .form-label {
        color: var(--page-text);
    }

    .troubleshooting-page .form-control {
        background: var(--page-bg);
        color: var(--page-text);
        border-color: var(--page-border);
    }

    .troubleshooting-page .text-muted,
    .troubleshooting-page .text-secondary,
    .troubleshooting-page .card-text.text-muted {
        color: var(--page-muted);
    }

    .troubleshooting-page h1, .troubleshooting-page h2, .troubleshooting-page h3,
    .troubleshooting-page h4, .troubleshooting-page h5, .troubleshooting-page h6,
    .troubleshooting-page .card-title, .troubleshooting-page .lead {
        color: var(--page-text);
    }

    .troubleshooting-page .btn-primary {
        background: var(--page-primary);
        border-color: var(--page-primary);
        color: white;
    }

    .troubleshooting-page .btn-primary:hover {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: white;
    }

    .troubleshooting-page .btn-outline-primary {
        border-color: var(--page-primary);
        color: var(--page-primary);
    }

    .troubleshooting-page .btn-outline-primary:hover {
        background: var(--page-primary);
        color: white;
    }

    .troubleshooting-page .alert-info {
        background: var(--page-surface);
        border-color: var(--page-primary);
        color: var(--page-text);
    }

    /* Category and Decision Cards */
    .troubleshooting-page .category-card,
    .troubleshooting-page .decision-card {
        background: var(--page-bg);
        border: 1px solid var(--page-border);
        color: var(--page-text);
        transition: all 0.2s ease;
    }

    .troubleshooting-page .category-card:hover,
    .troubleshooting-page .decision-card:hover {
        background: var(--page-surface);
        border-color: var(--page-primary);
        transform: translateY(-2px);
    }

    .troubleshooting-page .category-icon {
        background: var(--page-primary);
        color: white;
    }

    /* Steps */
    .troubleshooting-page .steps-container {
        margin-left: 0;
        padding-left: 1rem;
    }

    .troubleshooting-page .step-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        min-height: 32px;
    }

    .troubleshooting-page .step-number {
        width: 32px;
        height: 32px;
        background: var(--page-primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        margin-right: 1.25rem;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .troubleshooting-page .step-content {
        flex: 1;
        padding-left: 0;
        padding-top: 2px;
        min-height: 32px;
        line-height: 1.5;
        color: var(--page-text);
        font-weight: 500;
    }

    .troubleshooting-page .solution-steps h5 {
        color: var(--page-text);
    }

    /* Rating Stars */
    .troubleshooting-page .rating-star {
        background: transparent;
        border: none;
        outline: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        display: inline-block;
        cursor: pointer;
        font-size: 1.5rem;
        color: var(--page-muted);
        transition: color 0.2s ease;
        padding: 0.25rem;
        margin: 0 0.1rem;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        box-shadow: none;
        text-decoration: none;
    }

    .troubleshooting-page .rating-star:focus,
    .troubleshooting-page .rating-star:active,
    .troubleshooting-page .rating-star:focus-visible {
        outline: none;
        box-shadow: none;
        background: transparent;
        border: none;
    }

    .troubleshooting-page .rating-star:hover,
    .troubleshooting-page .rating-star.active {
        color: var(--page-primary);
        background: transparent;
        border: none;
        outline: none;
        box-shadow: none;
    }

    .troubleshooting-page .rating-star.active i,
    .troubleshooting-page .rating-star:hover i {
        color: var(--page-primary);
    }

    /* Ensure proper inheritance for nested elements */
    .troubleshooting-page .card-body *,
    .troubleshooting-page .step-content * {
        color: inherit;
        background: inherit;
    }

    /* Ensure checkbox works properly */
    .troubleshooting-page .form-check-input {
        appearance: auto !important;
        -webkit-appearance: auto !important;
        -moz-appearance: auto !important;
        pointer-events: auto !important;
        background: var(--page-bg) !important;
        border: 1px solid var(--page-border) !important;
        color: var(--page-text) !important;
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/mega-navbar-js.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Troubleshooting page loaded');

        // Rating stars functionality
        const ratingStars = document.querySelectorAll('.rating-star');
        const ratingInput = document.getElementById('rating');
        let currentRating = parseInt(ratingInput.value) || 0;

        console.log('Found rating stars:', ratingStars.length);
        console.log('Initial rating:', currentRating);

        function updateStars(rating) {
            ratingStars.forEach(star => {
                const starRating = parseInt(star.dataset.rating);
                const icon = star.querySelector('i');
                if (starRating <= rating) {
                    star.classList.add('active');
                    icon.className = 'bi bi-star-fill';
                } else {
                    star.classList.remove('active');
                    icon.className = 'bi bi-star';
                }
            });
        }

        // Initialize stars with current rating
        updateStars(currentRating);

        // Click to set rating
        ratingStars.forEach(star => {
            star.addEventListener('click', function(e) {
                e.preventDefault();
                const rating = parseInt(this.dataset.rating);
                currentRating = rating;
                ratingInput.value = rating;
                updateStars(rating);
                console.log('Rating set to:', rating);
            });

            // Hover to preview
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                updateStars(rating);
            });

            // Mouse leave to reset to current rating
            star.addEventListener('mouseleave', function() {
                updateStars(currentRating);
            });
        });

        // Feedback form submission
        const feedbackForm = document.getElementById('feedbackForm');
        if (feedbackForm) {
            console.log('Feedback form found');
            feedbackForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                console.log('Form submitted');

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

                try {
                    const formData = new FormData(this);
                    console.log('Sending data:', Object.fromEntries(formData));

                    const response = await fetch('../feedback/submit_feedback.php', {
                        method: 'POST',
                        body: formData
                    });

                    console.log('Response status:', response.status);
                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success) {
                        // Show success message
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-success alert-dismissible fade show';
                        alert.innerHTML = `
                            <i class="bi bi-check-circle"></i> Thank you for your feedback!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        this.parentNode.insertBefore(alert, this);

                        // Reset form
                        this.reset();
                        currentRating = 0;
                        updateStars(0);
                    } else {
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        } else {
            console.log('Feedback form not found');
        }
    });
    </script>
</body>
</html>

<?php
// Helper functions
function getCategoryIcon($title) {
    $icons = [
        'Laptop' => 'laptop',
        'Phone' => 'phone',
        'Desktop' => 'pc-display',
        'Tablet' => 'tablet',
        'Audio' => 'speaker',
        'Video' => 'tv',
        'Network' => 'wifi',
        'Software' => 'code-slash',
        'Hardware' => 'motherboard'
    ];
    
    foreach ($icons as $keyword => $icon) {
        if (stripos($title, $keyword) !== false) {
            return $icon;
        }
    }
    
    return 'tools';
}
?>