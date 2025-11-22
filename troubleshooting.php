<?php
session_start();
require_once 'includes/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? null;

if ($isLoggedIn && $userRole === 'admin') { redirect('admin/dashboard.php'); }

$db = new Database();
$pdo = $db->getConnection();

// Get company settings
$settings = [];
try {
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'");
    $colsStmt->execute();
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $colKey = in_array('setting_key', $cols) ? 'setting_key' : 'setting_key';
    $colValue = in_array('setting_value', $cols) ? 'setting_value' : 'setting_value';
    $stmt = $pdo->query("SELECT `{$colKey}` AS setting_key, `{$colValue}` AS setting_value FROM settings");
    while ($row = $stmt->fetch()) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) {}

function flatten_to_string($val) {
    if (is_array($val)) return implode(' ', array_map('flatten_to_string', $val));
    elseif (is_string($val)) { $d = json_decode($val, true); if (json_last_error() === JSON_ERROR_NONE && is_array($d)) return flatten_to_string($d); return $val; }
    return $val === null ? '' : (string)$val;
}
foreach ($settings as $key => $val) { $settings[$key] = flatten_to_string($val); }
$settings['favicon'] = is_array($settings['favicon'] ?? '') ? '' : ($settings['favicon'] ?? '');
$settings['site_logo'] = is_array($settings['site_logo'] ?? '') ? '' : ($settings['site_logo'] ?? '');

// Get troubleshooting categories
$stmt = $pdo->query("SELECT * FROM troubleshooting_guide WHERE parent_id IS NULL AND is_active = 1 ORDER BY display_order");
$categories = $stmt->fetchAll();

$guideId = $_GET['id'] ?? null;
$currentGuide = null; $children = []; $parentGuide = null;

if ($guideId) {
    $stmt = $pdo->prepare("SELECT * FROM troubleshooting_guide WHERE id = ? AND is_active = 1");
    $stmt->execute([$guideId]);
    $currentGuide = $stmt->fetch();
    if ($currentGuide) {
        $stmt = $pdo->prepare("SELECT * FROM troubleshooting_guide WHERE parent_id = ? AND is_active = 1 ORDER BY display_order");
        $stmt->execute([$guideId]);
        $children = $stmt->fetchAll();
        if ($currentGuide['parent_id']) {
            $stmt = $pdo->prepare("SELECT * FROM troubleshooting_guide WHERE id = ?");
            $stmt->execute([$currentGuide['parent_id']]);
            $parentGuide = $stmt->fetch();
        }
    }
}

$existingFeedback = null; $hasFeedback = false;
if (isset($_SESSION['user_id']) && $guideId) {
    $stmt = $pdo->prepare("SELECT id, rating, comment, anonymous FROM troubleshooting_feedback WHERE user_id = ? AND troubleshooting_guide_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], $guideId]);
    $existingFeedback = $stmt->fetch();
    $hasFeedback = $existingFeedback !== false;
}

function getCategoryIcon($title) {
    $icons = ['Laptop'=>'laptop','Phone'=>'phone','Desktop'=>'pc-display','Tablet'=>'tablet','Audio'=>'speaker','Video'=>'tv','Network'=>'wifi','Software'=>'code-slash','Hardware'=>'motherboard','Refrigerator'=>'snow','Air Conditioner'=>'fan','Washing Machine'=>'water','TV'=>'tv','Printer'=>'printer'];
    foreach ($icons as $k => $v) { if (stripos($title, $k) !== false) return $v; }
    return 'tools';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Troubleshooting Guide - <?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/unified-theme.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <?php if (!empty($settings['favicon'])): ?><link rel="icon" href="<?= htmlspecialchars($settings['favicon'][0] === '/' ? $settings['favicon'] : ROOT_PATH . $settings['favicon']) ?>" /><?php endif; ?>
    <style>
        :root { --primary: <?= htmlspecialchars($settings['primary_color'] ?? '#2563eb') ?>; --secondary: <?= htmlspecialchars($settings['secondary_color'] ?? '#64748b') ?>; --primary-light: <?= htmlspecialchars($settings['primary_color'] ?? '#2563eb') ?>20; font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Inter') ?>, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        html { scroll-behavior: smooth; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; color: #f1f5f9; }
    </style>
    <?php include 'includes/mega-navbar-css.php'; ?>
    <style>
        /* Override navbar for dark page */
        .mega-navbar { background: rgba(255,255,255,0.08) !important; border-color: rgba(255,255,255,0.1) !important; }
        .mega-navbar.scrolled { background: rgba(255,255,255,0.12) !important; }
        .mega-navbar-brand-text, .mega-nav-link { color: #f1f5f9 !important; }
        .mega-nav-link:hover, .mega-nav-link.active { color: var(--primary) !important; background: rgba(255,255,255,0.1) !important; }
        .mega-mobile-toggle { color: #f1f5f9 !important; }
        .mega-nav-btn-ghost { color: #f1f5f9 !important; }
        .mega-user-avatar { border-color: rgba(255,255,255,0.3) !important; }
        
        .fade-in { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }
        
        .troubleshooting-container { max-width: 1400px; margin: 0 auto; padding: 2rem 1rem; }
        .troubleshooting-hero { background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, #000)); color: white; padding: 3rem 2rem; border-radius: 20px; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .troubleshooting-hero::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.05" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom; background-size: cover; }
        .troubleshooting-hero > * { position: relative; z-index: 1; }
        .troubleshooting-hero h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem; }
        
        .category-filter-wrapper { margin-bottom: 2rem; }
        .category-filter-toggle { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; cursor: pointer; transition: all 0.3s ease; }
        .category-filter-toggle:hover { background: rgba(255,255,255,0.12); }
        .category-filter-toggle.active { border-radius: 12px 12px 0 0; border-bottom-color: transparent; }
        .filter-toggle-left { display: flex; align-items: center; gap: 0.75rem; }
        .filter-toggle-left i { font-size: 1.25rem; color: var(--primary); }
        .filter-toggle-left span { font-weight: 600; color: #f1f5f9; }
        .selected-category-badge { background: var(--primary); color: white; padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.8125rem; font-weight: 600; }
        .filter-chevron { color: #94a3b8; font-size: 1.25rem; transition: transform 0.3s ease; }
        .category-filter-toggle.active .filter-chevron { transform: rotate(180deg); }
        .category-filter-dropdown { display: none; background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-top: none; border-radius: 0 0 12px 12px; padding: 1rem; }
        .category-filter-dropdown.show { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .category-pills { display: flex; gap: 0.625rem; flex-wrap: wrap; }
        .category-pill { padding: 0.625rem 1.25rem; background: rgba(255,255,255,0.1); color: #f1f5f9; border-radius: 50px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; border: 2px solid transparent; }
        .category-pill:hover { background: rgba(255,255,255,0.15); color: #ffffff; transform: translateY(-2px); }
        .category-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .category-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; overflow: hidden; transition: all 0.3s ease; height: 100%; }
        .category-card:hover { transform: translateY(-8px); box-shadow: 0 12px 40px rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.2); }
        .category-thumbnail { width: 100%; height: 220px; overflow: hidden; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; }
        .category-thumbnail img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .category-card:hover .category-thumbnail img { transform: scale(1.1); }
        .category-thumbnail-icon { width: 90px; height: 90px; background: var(--primary); color: white; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .category-card .card-body { padding: 1.75rem; }
        .category-card .card-title { font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin-bottom: 0.75rem; }
        .category-card .card-text { color: #cbd5e0; margin-bottom: 1.25rem; line-height: 1.6; }
        
        .guide-detail-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2.5rem; margin-bottom: 2rem; }
        .guide-detail-card .lead { color: #cbd5e0 !important; }
        .breadcrumb { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; }
        .breadcrumb-item a { color: var(--primary); text-decoration: none; font-weight: 500; }
        .breadcrumb-item.active { color: #cbd5e0; }
        .breadcrumb-item + .breadcrumb-item::before { color: #64748b; }
        
        .decision-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 1.5rem; transition: all 0.3s ease; text-decoration: none; display: block; height: 100%; }
        .decision-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.2); background: rgba(255,255,255,0.12); }
        .decision-thumbnail { width: 100%; height: 140px; overflow: hidden; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; border-radius: 10px; margin-bottom: 1rem; }
        .decision-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
        .decision-thumbnail-icon { width: 60px; height: 60px; background: var(--primary); color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; }
        .decision-card h6 { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin-bottom: 0.75rem; }
        .decision-card .text-muted { color: #94a3b8 !important; margin-bottom: 1rem; }
        .decision-card .solution-link { color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        
        .step-item { display: flex; gap: 1.25rem; margin-bottom: 2rem; }
        .step-number { width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 80%, #000)); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
        .step-content { flex: 1; padding-top: 8px; color: #f1f5f9; font-size: 1.0625rem; line-height: 1.7; }
        
        .alert-info { background: rgba(37,99,235,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(37,99,235,0.3); border-radius: 12px; padding: 1.5rem; color: #f1f5f9; }
        .alert-info h6 { color: #60a5fa; font-weight: 700; margin-bottom: 0.75rem; }
        
        .sidebar-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 1.75rem; margin-bottom: 1.5rem; }
        .sidebar-card h6 { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin-bottom: 1.25rem; }
        .sidebar-card p, .sidebar-card ul li { color: #cbd5e0; }
        
        .section-title { font-size: 1.75rem; font-weight: 700; color: #f1f5f9; margin-bottom: 1.5rem; }
        .rating-star { background: transparent; border: none; cursor: pointer; font-size: 2rem; color: #475569; padding: 0.25rem; transition: all 0.2s; }
        .rating-star:hover, .rating-star.active { color: #fbbf24; transform: scale(1.15); }
        
        .form-control, .form-select { border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; padding: 0.75rem 1rem; background: rgba(255,255,255,0.1); color: #f1f5f9; }
        .form-control::placeholder { color: #94a3b8; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(37,99,235,0.25); background: rgba(255,255,255,0.15); color: #f1f5f9; }
        .form-label { font-weight: 600; color: #f1f5f9; margin-bottom: 0.5rem; }
        .form-check-label { color: #cbd5e0; }
        .form-check-input { background-color: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
        
        .btn-primary { background: var(--primary); border: none; padding: 0.875rem 1.75rem; border-radius: 12px; font-weight: 600; box-shadow: 0 4px 12px rgba(37,99,235,0.3); }
        .btn-primary:hover { background: color-mix(in srgb, var(--primary) 85%, #000); transform: translateY(-2px); }
        .btn-outline-primary { border: 2px solid var(--primary); color: var(--primary); background: transparent; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600; }
        .btn-outline-primary:hover { background: var(--primary); color: white; }
        
        hr { border-color: rgba(255,255,255,0.1); }
        .feedback-login-prompt { text-align: center; padding: 2rem; }
        .feedback-login-prompt i { font-size: 3rem; color: #475569; margin-bottom: 1rem; display: block; }
        .feedback-login-prompt h5 { font-weight: 700; color: #f1f5f9; margin-bottom: 1rem; }
        .feedback-login-prompt p { color: #94a3b8; margin-bottom: 1.5rem; }
        .feedback-login-prompt a { color: var(--primary); font-weight: 600; }
        .alert-success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #86efac; }
        .alert-danger { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
        .alert .btn-close { filter: invert(1); }
        
        @media (max-width: 768px) {
            .troubleshooting-container { padding: 1rem 0.75rem; }
            .troubleshooting-hero { padding: 2rem 1.5rem; }
            .troubleshooting-hero h1 { font-size: 1.75rem; }
            .guide-detail-card { padding: 1.5rem; }
        }
        <?= $settings['custom_css'] ?? '' ?>
    </style>
</head>
<body>
    <?php include 'includes/mega-navbar.php'; ?>

    <div class="troubleshooting-container">
        <?php if (!$currentGuide): ?>
            <div class="troubleshooting-hero fade-in">
                <h1><i class="bi bi-tools me-3"></i>Troubleshooting Guide</h1>
                <p>Find step-by-step solutions for common electronic problems. Select a category below to get started.</p>
            </div>

            <div class="category-filter-wrapper fade-in">
                <div class="category-filter-toggle" id="categoryFilterToggle">
                    <div class="filter-toggle-left"><i class="bi bi-funnel"></i><span>Filter by Category</span></div>
                    <div class="filter-toggle-right"><span class="selected-category-badge">All Categories</span><i class="bi bi-chevron-down filter-chevron"></i></div>
                </div>
                <div class="category-filter-dropdown" id="categoryFilterDropdown">
                    <div class="category-pills">
                        <a href="troubleshooting.php" class="category-pill <?= !$guideId ? 'active' : '' ?>"><i class="bi bi-grid"></i>All Categories</a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="troubleshooting.php?id=<?= $cat['id'] ?>" class="category-pill"><i class="bi bi-<?= getCategoryIcon($cat['title']) ?>"></i><?= htmlspecialchars($cat['title']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row g-4 fade-in">
                <?php foreach ($categories as $cat): ?>
                <div class="col-md-6 col-lg-4">
                    <a href="troubleshooting.php?id=<?= $cat['id'] ?>" class="text-decoration-none">
                        <div class="category-card">
                            <div class="category-thumbnail">
                                <?php if (!empty($cat['image_path']) && file_exists($cat['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($cat['image_path']) ?>" alt="<?= htmlspecialchars($cat['title']) ?>">
                                <?php else: ?>
                                    <div class="category-thumbnail-icon"><i class="bi bi-<?= getCategoryIcon($cat['title']) ?>"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($cat['title']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars(substr($cat['description'], 0, 120)) ?><?= strlen($cat['description']) > 120 ? '...' : '' ?></p>
                                <span class="btn btn-primary w-100"><i class="bi bi-arrow-right-circle me-2"></i>Explore Solutions</span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <nav aria-label="breadcrumb" class="fade-in">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="troubleshooting.php"><i class="bi bi-house-door me-1"></i>Troubleshooting</a></li>
                    <?php if ($parentGuide): ?><li class="breadcrumb-item"><a href="troubleshooting.php?id=<?= $parentGuide['id'] ?>"><?= htmlspecialchars($parentGuide['title']) ?></a></li><?php endif; ?>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($currentGuide['title']) ?></li>
                </ol>
            </nav>

            <div class="row">
                <div class="col-lg-8">
                    <div class="guide-detail-card fade-in">
                        <h3 class="section-title"><i class="bi bi-<?= getCategoryIcon($currentGuide['title']) ?> me-2" style="color:var(--primary);"></i><?= htmlspecialchars($currentGuide['title']) ?></h3>
                        <p class="lead"><?= nl2br(htmlspecialchars($currentGuide['description'])) ?></p>

                        <?php if (!empty($children)): ?>
                            <div class="mt-4">
                                <h5 class="mb-4" style="font-size:1.375rem;font-weight:700;color:#f1f5f9;"><i class="bi bi-list-check me-2" style="color:var(--primary);"></i>What best describes your specific issue?</h5>
                                <div class="row g-3">
                                    <?php foreach ($children as $child): ?>
                                    <div class="col-md-6">
                                        <a href="troubleshooting.php?id=<?= $child['id'] ?>" class="decision-card">
                                            <div class="decision-thumbnail">
                                                <?php if (!empty($child['image_path']) && file_exists($child['image_path'])): ?>
                                                    <img src="<?= htmlspecialchars($child['image_path']) ?>" alt="<?= htmlspecialchars($child['title']) ?>">
                                                <?php else: ?>
                                                    <div class="decision-thumbnail-icon"><i class="bi bi-<?= getCategoryIcon($child['title']) ?>"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <h6><?= htmlspecialchars($child['title']) ?></h6>
                                            <p class="text-muted small"><?= htmlspecialchars(substr($child['description'], 0, 100)) ?><?= strlen($child['description']) > 100 ? '...' : '' ?></p>
                                            <span class="solution-link">View solution <i class="bi bi-arrow-right"></i></span>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (!empty(trim($currentGuide['fix_steps']))): ?>
                            <div class="mt-5">
                                <h5 class="mb-4" style="font-size:1.5rem;font-weight:700;color:#f1f5f9;"><i class="bi bi-wrench-adjustable me-2" style="color:var(--primary);"></i>Fix Steps:</h5>
                                <?php $steps = explode("\n", $currentGuide['fix_steps']); foreach ($steps as $i => $step): if (trim($step)): ?>
                                    <div class="step-item"><div class="step-number"><?= $i + 1 ?></div><div class="step-content"><?= nl2br(htmlspecialchars(trim($step))) ?></div></div>
                                <?php endif; endforeach; ?>
                            </div>
                            <?php if ($currentGuide['preventive_tip']): ?>
                                <div class="mt-4"><div class="alert alert-info"><h6><i class="bi bi-lightbulb-fill"></i> Preventive Tip</h6><p class="mb-0"><?= nl2br(htmlspecialchars($currentGuide['preventive_tip'])) ?></p></div></div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($currentGuide && $isLoggedIn): ?>
                        <hr class="my-5">
                        <div>
                            <h5 class="mb-4" style="font-size:1.375rem;font-weight:700;color:#f1f5f9;"><i class="bi bi-chat-heart me-2" style="color:var(--primary);"></i>Was this guide helpful?</h5>
                            <form id="feedbackForm" class="mt-3">
                                <input type="hidden" name="troubleshooting_guide_id" value="<?= $currentGuide['id'] ?>">
                                <?php if ($hasFeedback): ?><input type="hidden" name="edit_id" value="<?= $existingFeedback['id'] ?>"><?php endif; ?>
                                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                                <div class="mb-4">
                                    <label class="form-label">Your Rating</label>
                                    <div>
                                        <?php $cr = $hasFeedback ? $existingFeedback['rating'] : 0; for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="rating-star<?= $i <= $cr ? ' active' : '' ?>" data-rating="<?= $i ?>"><i class="bi bi-star<?= $i <= $cr ? '-fill' : '' ?>"></i></span>
                                        <?php endfor; ?>
                                        <input type="hidden" name="rating" id="rating" value="<?= $cr ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3"><label for="comment" class="form-label">Your Feedback (Optional)</label><textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Share your experience..."><?= $hasFeedback ? htmlspecialchars($existingFeedback['comment']) : '' ?></textarea></div>
                                <div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="anonymous" id="anonymous" value="1"<?= $hasFeedback && $existingFeedback['anonymous'] ? ' checked' : '' ?>><label class="form-check-label" for="anonymous">Post anonymously</label></div>
                                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send me-2"></i><?= $hasFeedback ? 'Update Feedback' : 'Submit Feedback' ?></button>
                            </form>
                        </div>
                        <?php elseif ($currentGuide && !$isLoggedIn): ?>
                        <hr class="my-5">
                        <div class="feedback-login-prompt"><i class="bi bi-heart"></i><h5>Was this guide helpful?</h5><p>Please <a href="auth/login.php">login</a> to leave feedback.</p><a href="auth/login.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Login to Leave Feedback</a></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="sidebar-card fade-in"><h6><i class="bi bi-headset me-2" style="color:var(--primary);"></i>Need More Help?</h6><p>If our guide doesn't solve your issue, book a professional service appointment.</p><a href="<?= $isLoggedIn ? 'customer/book-appointment.php' : 'auth/register.php' ?>" class="btn btn-primary w-100"><i class="bi bi-calendar-plus me-2"></i>Book Appointment</a></div>
                    <div class="sidebar-card fade-in"><h6><i class="bi bi-collection me-2" style="color:var(--primary);"></i>Browse Categories</h6><div class="d-flex flex-column gap-2"><?php foreach ($categories as $cat): ?><a href="troubleshooting.php?id=<?= $cat['id'] ?>" class="btn <?= $currentGuide && $currentGuide['id'] == $cat['id'] ? 'btn-primary' : 'btn-outline-primary' ?> text-start"><i class="bi bi-<?= getCategoryIcon($cat['title']) ?> me-2"></i><?= htmlspecialchars($cat['title']) ?></a><?php endforeach; ?></div></div>
                    <div class="sidebar-card fade-in"><h6><i class="bi bi-lightbulb me-2" style="color:var(--primary);"></i>Quick Tips</h6><ul style="font-size:0.9375rem;line-height:1.8;padding-left:1.25rem;"><li>Always unplug devices before troubleshooting</li><li>Take photos before disassembly</li><li>Keep screws organized</li><li>Consult manual for error codes</li><li>Back up data before repairs</li></ul></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <section class="py-5 bg-dark text-white mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></h5>
                    <?php if (!empty($settings['company_address'])): ?><p class="mb-1"><i class="bi bi-geo-alt-fill me-2"></i><?= nl2br(htmlspecialchars($settings['company_address'])) ?></p><?php endif; ?>
                    <?php if (!empty($settings['company_phone'])): ?><p class="mb-1"><i class="bi bi-telephone-fill me-2"></i><?= htmlspecialchars($settings['company_phone']) ?></p><?php endif; ?>
                    <?php if (!empty($settings['company_email'])): ?><p class="mb-0"><i class="bi bi-envelope-fill me-2"></i><a href="mailto:<?= htmlspecialchars($settings['company_email']) ?>" class="text-white"><?= htmlspecialchars($settings['company_email']) ?></a></p><?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end"><p class="text-white"><?= htmlspecialchars($settings['footer_text'] ?? '&copy; ' . date('Y') . ' ' . ($settings['company_name'] ?? 'Soreta Electronics')) ?></p></div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/mega-navbar-js.php'; ?>
    <script>
        const filterToggle = document.getElementById('categoryFilterToggle');
        const filterDropdown = document.getElementById('categoryFilterDropdown');
        if (filterToggle && filterDropdown) {
            filterToggle.addEventListener('click', function() { this.classList.toggle('active'); filterDropdown.classList.toggle('show'); });
            document.addEventListener('click', function(e) { if (!filterToggle.contains(e.target) && !filterDropdown.contains(e.target)) { filterToggle.classList.remove('active'); filterDropdown.classList.remove('show'); } });
        }
        
        const observer = new IntersectionObserver(entries => { entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('visible'); }); }, {threshold:0.1,rootMargin:'0px 0px -50px 0px'});
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

        const ratingStars = document.querySelectorAll('.rating-star');
        const ratingInput = document.getElementById('rating');
        let currentRating = parseInt(ratingInput?.value) || 0;
        function updateStars(r) { ratingStars.forEach(s => { const sr = parseInt(s.dataset.rating); const i = s.querySelector('i'); if (sr <= r) { s.classList.add('active'); i.className = 'bi bi-star-fill'; } else { s.classList.remove('active'); i.className = 'bi bi-star'; } }); }
        updateStars(currentRating);
        ratingStars.forEach(s => {
            s.addEventListener('click', function() { currentRating = parseInt(this.dataset.rating); if (ratingInput) ratingInput.value = currentRating; updateStars(currentRating); });
            s.addEventListener('mouseenter', function() { updateStars(parseInt(this.dataset.rating)); });
            s.addEventListener('mouseleave', function() { updateStars(currentRating); });
        });

        const feedbackForm = document.getElementById('feedbackForm');
        if (feedbackForm) {
            feedbackForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]'); const orig = btn.innerHTML;
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
                try {
                    const fd = new FormData(this);
                    const res = await fetch('feedback/submit_feedback.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    const alert = document.createElement('div');
                    alert.className = data.success ? 'alert alert-success alert-dismissible fade show' : 'alert alert-danger alert-dismissible fade show';
                    alert.innerHTML = `<i class="bi bi-${data.success ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i>${data.success ? 'Thank you! Your feedback has been submitted.' : (data.message || 'An error occurred')}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
                    this.parentNode.insertBefore(alert, this);
                    alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } catch (err) { console.error(err); } finally { btn.disabled = false; btn.innerHTML = orig; }
            });
        }
    </script>
</body>
</html>