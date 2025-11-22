<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    redirect('customer/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

$message = '';

// Detect settings table columns to support multiple schemas (setting_key/setting_value or key/value)
$colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'");
$colsStmt->execute();
$cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$colKey = in_array('setting_key', $cols) ? 'setting_key' : (in_array('key', $cols) ? 'key' : 'setting_key');
$colValue = in_array('setting_value', $cols) ? 'setting_value' : (in_array('value', $cols) ? 'value' : 'setting_value');
$colType = in_array('setting_type', $cols) ? 'setting_type' : null;

// Helper: escaped column names for use in SQL
$colKeyEsc = "`" . str_replace('`', '', $colKey) . "`";
$colValueEsc = "`" . str_replace('`', '', $colValue) . "`";
$colTypeEsc = $colType ? "`" . str_replace('`', '', $colType) . "`" : null;

// Define sections and their keys
$sections = [
    'company_info' => ['company_name', 'company_phone', 'company_address', 'company_map_link', 'company_email', 'company_tagline'],
    'services' => ['services'],
    'design' => ['site_logo', 'hero_image', 'site_logo_circle', 'site_logo_dataurl', 'hero_image_dataurl', 'delete_site_logo', 'delete_hero_image'],
    'hero_content' => ['hero_title', 'hero_subtitle'],
    'business_hours' => ['business_hours'],
    'about' => ['company_about'],
    'footer' => ['footer_text'],
    'announcements' => ['announcements'],
    'optional' => ['google_analytics', 'facebook_pixel', 'social_facebook', 'social_messenger', 'seo_meta_title', 'seo_meta_description'],
];

$selectCols = [$colKeyEsc . ' AS setting_key', $colValueEsc . ' AS setting_value'];
if ($colTypeEsc) $selectCols[] = $colTypeEsc . ' AS setting_type';
$stmt = $pdo->query("SELECT " . implode(', ', $selectCols) . " FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $k = $row['setting_key'];
    $settings[$k] = $row;
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);
        $posted = $_POST['settings'] ?? [];
        $section = $_POST['section'] ?? null;

        // If section specified, preserve existing values for keys in the section
        if ($section && isset($sections[$section])) {
            $allowedKeys = $sections[$section];
            $currentSectionSettings = [];
            foreach ($allowedKeys as $key) {
                if (isset($settings[$key])) {
                    $currentSectionSettings[$key] = $settings[$key]['setting_value'];
                }
            }
            // Filter posted data to only that section
            $filteredPosted = [];
            foreach ($posted as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $filteredPosted[$key] = $value;
                }
            }
            // Merge current with posted to preserve existing values
            $posted = array_merge($currentSectionSettings, $filteredPosted);
        }

        // Handle deletions BEFORE file uploads
        if (!empty($posted['delete_site_logo'])) {
            // Delete the actual file if it exists
            if (!empty($settings['site_logo']['setting_value'])) {
                $oldFile = __DIR__ . '/../' . $settings['site_logo']['setting_value'];
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $posted['site_logo'] = '';
            unset($posted['delete_site_logo']);
        }
        if (!empty($posted['delete_hero_image'])) {
            // Delete the actual file if it exists
            if (!empty($settings['hero_image']['setting_value'])) {
                $oldFile = __DIR__ . '/../' . $settings['hero_image']['setting_value'];
                if (file_exists($oldFile) && is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $posted['hero_image'] = '';
            unset($posted['delete_hero_image']);
        }

        // Handle uploaded files (logo, hero image) - map file inputs to setting keys
        $uploadDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'];
        $fileMap = [
            'site_logo' => 'site_logo_file',
            'hero_image' => 'hero_image_file',
            'favicon' => 'favicon_file'
        ];

        foreach ($fileMap as $settingKey => $fileField) {
            if (!empty($_FILES[$fileField]) && isset($_FILES[$fileField]['error']) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES[$fileField]['tmp_name'];
                $origName = basename($_FILES[$fileField]['name']);
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmp);
                finfo_close($finfo);

                if (!in_array($mime, $allowedMimes)) {
                    $message .= "Invalid file type for {$settingKey}. ";
                    continue;
                }

                // Limit size to 20MB
                if ($_FILES[$fileField]['size'] > 20 * 1024 * 1024) {
                    $message .= "File too large for {$settingKey}. Maximum is 20MB. ";
                    continue;
                }

                $ext = pathinfo($origName, PATHINFO_EXTENSION) ?: 'png';
                $safeBase = preg_replace('/[^a-z0-9_\-]/i', '_', pathinfo($origName, PATHINFO_FILENAME));
                try {
                    $random = bin2hex(random_bytes(6));
                } catch (Exception $e) {
                    $random = uniqid();
                }
                $newName = time() . '_' . $safeBase . '_' . $random . '.' . $ext;
                $dest = $uploadDir . $newName;

                if (move_uploaded_file($tmp, $dest)) {
                    // Delete old file if exists
                    if (!empty($settings[$settingKey]['setting_value'])) {
                        $oldFile = __DIR__ . '/../' . $settings[$settingKey]['setting_value'];
                        if (file_exists($oldFile) && is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                    // Store the relative web path
                    $posted[$settingKey] = 'assets/uploads/' . $newName;
                } else {
                    $message .= "Failed to move uploaded file for {$settingKey}. ";
                }
            }
        }

        // Handle site_logo_dataurl (client-cropped circular PNG) if provided
        if (!empty($posted['site_logo_dataurl'])) {
            $dataUrl = $posted['site_logo_dataurl'];
            if (preg_match('#^data:image/(png|png8|gif|jpeg|jpg|webp);base64,#i', $dataUrl)) {
                $parts = explode(',', $dataUrl, 2);
                if (isset($parts[1])) {
                    $bin = base64_decode($parts[1]);
                    if ($bin !== false) {
                        try {
                            $random = bin2hex(random_bytes(6));
                        } catch (Exception $e) {
                            $random = uniqid();
                        }
                        $newName = time() . '_logo_' . $random . '.png';
                        $dest = $uploadDir . $newName;
                        if (file_put_contents($dest, $bin) !== false) {
                            // Delete old file if exists
                            if (!empty($settings['site_logo']['setting_value'])) {
                                $oldFile = __DIR__ . '/../' . $settings['site_logo']['setting_value'];
                                if (file_exists($oldFile) && is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            $posted['site_logo'] = 'assets/uploads/' . $newName;
                        } else {
                            $message .= "Failed to save cropped logo. ";
                        }
                    }
                }
            }
            // remove the dataurl from posted so we don't try to JSON-encode huge strings
            unset($posted['site_logo_dataurl']);
        }

        // Handle hero_image_dataurl if provided
        if (!empty($posted['hero_image_dataurl'])) {
            $dataUrl = $posted['hero_image_dataurl'];
            if (preg_match('#^data:image/(png|png8|gif|jpeg|jpg|webp);base64,#i', $dataUrl)) {
                $parts = explode(',', $dataUrl, 2);
                if (isset($parts[1])) {
                    $bin = base64_decode($parts[1]);
                    if ($bin !== false) {
                        try {
                            $random = bin2hex(random_bytes(6));
                        } catch (Exception $e) {
                            $random = uniqid();
                        }
                        $newName = time() . '_hero_' . $random . '.png';
                        $dest = $uploadDir . $newName;
                        if (file_put_contents($dest, $bin) !== false) {
                            // Delete old file if exists
                            if (!empty($settings['hero_image']['setting_value'])) {
                                $oldFile = __DIR__ . '/../' . $settings['hero_image']['setting_value'];
                                if (file_exists($oldFile) && is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            $posted['hero_image'] = 'assets/uploads/' . $newName;
                        } else {
                            $message .= "Failed to save cropped hero image. ";
                        }
                    }
                }
            }
            // remove the dataurl from posted
            unset($posted['hero_image_dataurl']);
        }

        // Normalize posted settings: skip empty keys and convert arrays to JSON
        foreach ($posted as $rawKey => $rawValue) {
            $key = trim((string)$rawKey);
            if ($key === '') continue; // ignore malformed/empty keys

            $value = $rawValue;
            if (is_array($value)) {
                // Convert arrays (e.g., services, business_hours) to JSON for storage
                $value = json_encode($value);
            }

            // Check if setting exists using detected key column
            $checkSql = "SELECT id FROM settings WHERE " . $colKeyEsc . " = ?";
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute([$key]);

            if ($stmt->fetch()) {
                // Update existing
                $updateSql = "UPDATE settings SET " . $colValueEsc . " = ? WHERE " . $colKeyEsc . " = ?";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute([$value, $key]);
            } else {
                // Insert new using detected columns
                $insertCols = [$colKeyEsc, $colValueEsc];
                $placeholders = ['?', '?'];
                $insertSql = "INSERT INTO settings (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute([$key, $value]);
            }
        }

        // Reload settings after update
        $selectCols = [$colKeyEsc . ' AS setting_key', $colValueEsc . ' AS setting_value'];
        if ($colTypeEsc) $selectCols[] = $colTypeEsc . ' AS setting_type';
        $stmt = $pdo->query("SELECT " . implode(', ', $selectCols) . " FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $k = $row['setting_key'];
            $settings[$k] = $row;
        }

        if (empty($message)) {
            $message = $section ? ucfirst(str_replace('_', ' ', $section)) . " updated successfully!" : "Settings updated successfully!";
        }

        // If AJAX request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => empty($message) || strpos($message, 'Error') === false, 'message' => $message]);
            exit;
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
    }
}

// Parse JSON settings for editing, handling both JSON strings and arrays
$servicesRaw = $settings['services']['setting_value'] ?? ($settings['services'] ?? '[]');
if (is_string($servicesRaw)) {
    $services = json_decode($servicesRaw, true) ?: [];
} elseif (is_array($servicesRaw)) {
    $services = $servicesRaw;
} else {
    $services = [];
}

// Handle legacy format (array of strings) and convert to new structured format
$servicesStructured = [];
if (!empty($services) && is_array($services)) {
    foreach ($services as $service) {
        if (is_string($service)) {
            // Legacy format: just the service name
            $servicesStructured[] = [
                'title' => $service,
                'description' => '',
                'features' => []
            ];
        } elseif (is_array($service) && isset($service['title'])) {
            // New structured format
            $servicesStructured[] = [
                'title' => $service['title'] ?? '',
                'description' => $service['description'] ?? '',
                'features' => is_array($service['features']) ? $service['features'] : []
            ];
        }
    }
}

// If no services, provide default structure
if (empty($servicesStructured)) {
    $servicesStructured = [
        [
            'title' => 'Installation',
            'description' => 'Get the most out of your technology with our expert installation services. We ensure your equipment is set up correctly, safely, and optimally from day one.',
            'features' => ['Home & Office Automation', 'Security & Surveillance Systems', 'Audio-Visual (AV) Systems', 'Structured Cabling', 'Lighting Solutions']
        ],
        [
            'title' => 'Repair',
            'description' => 'When your equipment fails, you need a fast and dependable solution. Our repair service is designed to diagnose issues accurately and get your systems back online with minimal downtime.',
            'features' => ['Diagnosis & Troubleshooting', 'Component-Level Repair', 'Unit Replacement & Swap-Out', 'Emergency Repair Services']
        ],
        [
            'title' => 'Maintenance',
            'description' => 'Prevention is better than cure. Our maintenance services are designed to extend the lifespan of your equipment, improve reliability, and prevent costly unexpected breakdowns.',
            'features' => ['Routine Check-ups', 'System Optimization', 'Preventive Cleaning & Care', 'Health Reports', '24/7 Remote Monitoring']
        ]
    ];
}

$businessRaw = $settings['business_hours']['setting_value'] ?? ($settings['business_hours'] ?? '{}');
if (is_string($businessRaw)) {
    $business_hours = json_decode($businessRaw, true) ?: [];
} elseif (is_array($businessRaw)) {
    $business_hours = $businessRaw;
} else {
    $business_hours = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Soreta Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.css" rel="stylesheet">
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
                    <h1 class="h3 mb-0">System Settings</h1>
                </div>
                <div class="header-right">
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Navigation Sidebar -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Settings Sections</h6>
                            </div>
                            <div class="card-body p-0">
                                <nav class="nav flex-column nav-pills">
                                    <a class="nav-link active" href="#company-info" data-bs-toggle="collapse" data-bs-target="#collapse-company" aria-expanded="true" aria-controls="collapse-company">
                                        <i class="bi bi-building me-2"></i>Company Information
                                    </a>
                                    <a class="nav-link" href="#design" data-bs-toggle="collapse" data-bs-target="#collapse-design" aria-expanded="false" aria-controls="collapse-design">
                                        <i class="bi bi-palette me-2"></i>Website Design & Hero
                                    </a>
                                    <a class="nav-link" href="#hero-content" data-bs-toggle="collapse" data-bs-target="#collapse-hero" aria-expanded="false" aria-controls="collapse-hero">
                                        <i class="bi bi-type me-2"></i>Hero Content
                                    </a>
                                    <a class="nav-link" href="#about" data-bs-toggle="collapse" data-bs-target="#collapse-about" aria-expanded="false" aria-controls="collapse-about">
                                        <i class="bi bi-info-circle me-2"></i>About Us Content
                                    </a>
                                    <a class="nav-link" href="#services" data-bs-toggle="collapse" data-bs-target="#collapse-services" aria-expanded="false" aria-controls="collapse-services">
                                        <i class="bi bi-tools me-2"></i>Services Offered
                                    </a>
                                    <a class="nav-link" href="#business-hours" data-bs-toggle="collapse" data-bs-target="#collapse-hours" aria-expanded="false" aria-controls="collapse-hours">
                                        <i class="bi bi-clock me-2"></i>Business Hours
                                    </a>
                                    <a class="nav-link" href="#footer" data-bs-toggle="collapse" data-bs-target="#collapse-footer" aria-expanded="false" aria-controls="collapse-footer">
                                        <i class="bi bi-file-text me-2"></i>Footer Text
                                    </a>
                                    <a class="nav-link" href="#announcements" data-bs-toggle="collapse" data-bs-target="#collapse-announcements" aria-expanded="false" aria-controls="collapse-announcements">
                                        <i class="bi bi-megaphone me-2"></i>Announcements
                                    </a>
                                    <a class="nav-link" href="#optional" data-bs-toggle="collapse" data-bs-target="#collapse-optional" aria-expanded="false" aria-controls="collapse-optional">
                                        <i class="bi bi-gear me-2"></i>Optional Modules
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <!-- Live preview panel -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Live Website Preview</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary me-2" id="reloadPreviewBtn" title="Reload Preview">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="expandPreviewBtn" title="Expand Preview">
                                        <i class="bi bi-arrows-fullscreen"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0" style="position: relative;">
                                <iframe id="sitePreviewIframe" src="<?= ROOT_PATH ?>index.php" style="border:none; width: 100%; height: 400px; border-radius: 8px; transition: height 0.3s ease;"></iframe>
                            </div>
                        </div>

                        <form method="POST" id="settingsForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">

                            <!-- Accordion Sections -->
                            <div class="accordion" id="settingsAccordion">

                                <!-- Company Information -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-company">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-company" aria-expanded="true" aria-controls="collapse-company">
                                            <i class="bi bi-building me-2"></i>Company Information
                                        </button>
                                    </h2>
                                    <div id="collapse-company" class="accordion-collapse collapse show" aria-labelledby="heading-company" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Company Information</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="company_info">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="company_name" class="form-label">Company Name</label>
                                                    <input type="text" class="form-control" id="company_name"
                                                           name="settings[company_name]"
                                                           value="<?= htmlspecialchars($settings['company_name']['setting_value'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="company_phone" class="form-label">Phone Number</label>
                                                    <input type="text" class="form-control" id="company_phone"
                                                           name="settings[company_phone]"
                                                           value="<?= htmlspecialchars($settings['company_phone']['setting_value'] ?? '') ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label for="company_address" class="form-label">Address</label>
                                                    <input type="text" class="form-control" id="company_address"
                                                           name="settings[company_address]"
                                                           value="<?= htmlspecialchars($settings['company_address']['setting_value'] ?? '') ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label for="company_map_link" class="form-label">Map Link</label>
                                                    <input type="url" class="form-control" id="company_map_link"
                                                           name="settings[company_map_link]"
                                                           value="<?= htmlspecialchars($settings['company_map_link']['setting_value'] ?? '') ?>"
                                                           placeholder="https://maps.google.com/...">
                                                    <div class="form-text">Link to Google Maps or similar for your business location.</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="company_email" class="form-label">Email</label>
                                                    <input type="email" class="form-control" id="company_email"
                                                           name="settings[company_email]"
                                                           value="<?= htmlspecialchars($settings['company_email']['setting_value'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="company_tagline" class="form-label">Tagline</label>
                                                    <input type="text" class="form-control" id="company_tagline" name="settings[company_tagline]" value="<?= htmlspecialchars($settings['company_tagline']['setting_value'] ?? '') ?>" placeholder="Short description or slogan">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Website Design & Hero Banner -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-design">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-design" aria-expanded="false" aria-controls="collapse-design">
                                            <i class="bi bi-palette me-2"></i>Website Design & Hero Banner
                                        </button>
                                    </h2>
                                    <div id="collapse-design" class="accordion-collapse collapse" aria-labelledby="heading-design" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Website Design & Hero Banner</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="design">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-6">
                                                    <label class="form-label">Site Logo</label>
                                                    <div class="mb-2 d-flex align-items-center">
                                                        <?php if (!empty($settings['site_logo']['setting_value'])): ?>
                                                            <img src="<?= ROOT_PATH . htmlspecialchars($settings['site_logo']['setting_value']) ?>" alt="Site Logo" id="siteLogoPreview" style="max-height:80px; margin-right:10px;">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="deleteSiteLogoBtn" title="Delete Logo">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <img src="<?= ROOT_PATH ?>assets/images/placeholder-logo.png" alt="No logo" id="siteLogoPreview" style="max-height:80px; margin-right:10px;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- ensure checkbox posts when unchecked -->
                                                    <input type="hidden" name="settings[site_logo_circle]" value="0">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" id="siteLogoCircleToggle" name="settings[site_logo_circle]" value="1" <?= (!empty($settings['site_logo_circle']['setting_value']) && ($settings['site_logo_circle']['setting_value'] === '1' || $settings['site_logo_circle']['setting_value'] === 'on')) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="siteLogoCircleToggle">Crop logo to a circular shape</label>
                                                    </div>
                                                    <input type="file" class="form-control" name="site_logo_file" id="site_logo_file" accept="image/*">
                                                    <button type="button" class="btn btn-sm btn-secondary mt-2" id="cropLogoBtn">Crop Logo Image</button>
                                                    <div class="form-text">Upload a logo (PNG/JPG/SVG/WebP). Max 20MB. Recommended height: 80px. Use the Crop Logo Image button to crop the logo before saving.</div>
                                                </div>

                                                <div class="col-md-6">
                                                    <label class="form-label">Hero Banner Image</label>
                                                    <div class="mb-2 d-flex align-items-center">
                                                        <?php if (!empty($settings['hero_image']['setting_value'])): ?>
                                                            <img src="<?= ROOT_PATH . htmlspecialchars($settings['hero_image']['setting_value']) ?>" alt="Hero Image" id="heroPreview" style="max-height:120px; width:auto; margin-right:10px;">
                                                            <button type="button" class="btn btn-sm btn-outline-danger" id="deleteHeroImageBtn" title="Delete Hero Image">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <img src="<?= ROOT_PATH ?>assets/images/placeholder-hero.png" alt="No hero" id="heroPreview" style="max-height:120px; width:auto; margin-right:10px;">
                                                        <?php endif; ?>
                                                    </div>
                                                    <input type="file" class="form-control" name="hero_image_file" id="hero_image_file" accept="image/*">
                                                    <button type="button" class="btn btn-sm btn-secondary mt-2" id="cropHeroBtn">Crop Hero Image</button>
                                                    <div class="form-text">Upload a hero image for the homepage. Max 20MB. Recommended size: 1200x500px. You can crop the image using the Crop Hero Image button.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hero Content -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-hero">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-hero" aria-expanded="false" aria-controls="collapse-hero">
                                            <i class="bi bi-type me-2"></i>Hero Content
                                        </button>
                                    </h2>
                                    <div id="collapse-hero" class="accordion-collapse collapse" aria-labelledby="heading-hero" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Hero Content</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="hero_content">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label for="hero_title" class="form-label">Hero Title</label>
                                                    <input type="text" class="form-control" id="hero_title" name="settings[hero_title]" value="<?= htmlspecialchars($settings['hero_title']['setting_value'] ?? '') ?>" placeholder="Big, bold headline for the hero">
                                                    <div class="form-text">Main headline shown in the homepage hero.</div>
                                                </div>
                                                <div class="col-12">
                                                    <label for="hero_subtitle" class="form-label">Hero Subtitle</label>
                                                    <textarea class="form-control" id="hero_subtitle" name="settings[hero_subtitle]" rows="3" placeholder="Short supporting sentence"><?= htmlspecialchars($settings['hero_subtitle']['setting_value'] ?? '') ?></textarea>
                                                    <div class="form-text">Short descriptive text under the hero title.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- About Us Content -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-about">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-about" aria-expanded="false" aria-controls="collapse-about">
                                            <i class="bi bi-info-circle me-2"></i>About Us Content
                                        </button>
                                    </h2>
                                    <div id="collapse-about" class="accordion-collapse collapse" aria-labelledby="heading-about" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">About Us Content</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="about">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label for="company_about" class="form-label">About Us Text</label>
                                                    <textarea class="form-control" id="company_about" name="settings[company_about]" rows="6" placeholder="Describe your company, mission, and values"><?= htmlspecialchars($settings['company_about']['setting_value'] ?? '') ?></textarea>
                                                    <div class="form-text">This content will be displayed in the About Us section of your website.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Services Offered -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-services">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-services" aria-expanded="false" aria-controls="collapse-services">
                                            <i class="bi bi-tools me-2"></i>Services Offered
                                        </button>
                                    </h2>
                                    <div id="collapse-services" class="accordion-collapse collapse" aria-labelledby="heading-services" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Services Offered</h5>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary me-2" id="addService">
                                                        <i class="bi bi-plus"></i> Add Service
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="services">
                                                        <i class="bi bi-save"></i> Save Section
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="servicesContainer">
                                                <?php foreach ($servicesStructured as $index => $service): ?>
                                                    <div class="service-item mb-4 p-3 border rounded">
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Service Title</label>
                                                                <input type="text" class="form-control" name="settings[services][<?= $index ?>][title]" value="<?= htmlspecialchars($service['title']) ?>" placeholder="e.g., Laptop Repair">
                                                            </div>
                                                            <div class="col-md-7">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" rows="2" name="settings[services][<?= $index ?>][description]" placeholder="Brief description of the service"><?= htmlspecialchars($service['description']) ?></textarea>
                                                            </div>
                                                            <div class="col-md-1 d-flex align-items-end">
                                                                <button type="button" class="btn btn-outline-danger remove-service">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Key Features (one per line)</label>
                                                                <textarea class="form-control" rows="3" name="settings[services][<?= $index ?>][features]" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?= htmlspecialchars(implode("\n", $service['features'])) ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Business Hours -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-hours">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-hours" aria-expanded="false" aria-controls="collapse-hours">
                                            <i class="bi bi-clock me-2"></i>Business Hours
                                        </button>
                                    </h2>
                                    <div id="collapse-hours" class="accordion-collapse collapse" aria-labelledby="heading-hours" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Business Hours</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="business_hours">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <?php
                                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                                foreach ($days as $day):
                                                ?>
                                                    <div class="col-md-6">
                                                        <label for="business_hours_<?= $day ?>" class="form-label"><?= ucfirst($day) ?></label>
                                                        <input type="text" class="form-control" id="business_hours_<?= $day ?>" name="settings[business_hours][<?= $day ?>]" value="<?= htmlspecialchars($business_hours[$day] ?? '') ?>" placeholder="e.g., 9:00 AM - 5:00 PM">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="form-text mt-3">Leave blank for closed days. Format: 9:00 AM - 5:00 PM</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Text -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-footer">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-footer" aria-expanded="false" aria-controls="collapse-footer">
                                            <i class="bi bi-file-text me-2"></i>Footer Text
                                        </button>
                                    </h2>
                                    <div id="collapse-footer" class="accordion-collapse collapse" aria-labelledby="heading-footer" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Footer Text</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="footer">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label for="footer_text" class="form-label">Footer Content</label>
                                                    <textarea class="form-control" id="footer_text" name="settings[footer_text]" rows="4" placeholder="Copyright information, contact details, or other footer content"><?= htmlspecialchars($settings['footer_text']['setting_value'] ?? '') ?></textarea>
                                                    <div class="form-text">This text will appear in the footer of your website.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Announcements -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-announcements">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-announcements" aria-expanded="false" aria-controls="collapse-announcements">
                                            <i class="bi bi-megaphone me-2"></i>Announcements
                                        </button>
                                    </h2>
                                    <div id="collapse-announcements" class="accordion-collapse collapse" aria-labelledby="heading-announcements" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Announcements</h5>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-outline-primary me-2" id="addAnnouncement">
                                                        <i class="bi bi-plus"></i> Add Announcement
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="announcements">
                                                        <i class="bi bi-save"></i> Save Section
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="announcementsContainer">
                                                <?php
                                                $announcementsRaw = $settings['announcements']['setting_value'] ?? ($settings['announcements'] ?? '[]');
                                                if (is_string($announcementsRaw)) {
                                                    $announcements = json_decode($announcementsRaw, true) ?: [];
                                                } elseif (is_array($announcementsRaw)) {
                                                    $announcements = $announcementsRaw;
                                                } else {
                                                    $announcements = [];
                                                }
                                                foreach ($announcements as $index => $announcement):
                                                ?>
                                                    <div class="announcement-item mb-3 p-3 border rounded">
                                                        <div class="row g-2">
                                                            <div class="col-md-8">
                                                                <input type="text" class="form-control" name="settings[announcements][<?= $index ?>][title]" value="<?= htmlspecialchars($announcement['title'] ?? '') ?>" placeholder="Announcement title">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <input type="date" class="form-control" name="settings[announcements][<?= $index ?>][date]" value="<?= htmlspecialchars($announcement['date'] ?? '') ?>">
                                                            </div>
                                                            <div class="col-md-1">
                                                                <button type="button" class="btn btn-outline-danger remove-announcement">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </div>
                                                            <div class="col-12">
                                                                <textarea class="form-control" name="settings[announcements][<?= $index ?>][content]" rows="2" placeholder="Announcement content"><?= htmlspecialchars($announcement['content'] ?? '') ?></textarea>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Optional Modules -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-optional">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-optional" aria-expanded="false" aria-controls="collapse-optional">
                                            <i class="bi bi-gear me-2"></i>Optional Modules
                                        </button>
                                    </h2>
                                    <div id="collapse-optional" class="accordion-collapse collapse" aria-labelledby="heading-optional" data-bs-parent="#settingsAccordion">
                                        <div class="accordion-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0">Optional Modules</h5>
                                                <button type="button" class="btn btn-sm btn-primary save-section-btn" data-section="optional">
                                                    <i class="bi bi-save"></i> Save Section
                                                </button>
                                            </div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="google_analytics" class="form-label">Google Analytics ID</label>
                                                    <input type="text" class="form-control" id="google_analytics" name="settings[google_analytics]" value="<?= htmlspecialchars($settings['google_analytics']['setting_value'] ?? '') ?>" placeholder="GA-XXXXXXXXXX">
                                                    <div class="form-text">Your Google Analytics tracking ID (optional).</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="facebook_pixel" class="form-label">Facebook Pixel ID</label>
                                                    <input type="text" class="form-control" id="facebook_pixel" name="settings[facebook_pixel]" value="<?= htmlspecialchars($settings['facebook_pixel']['setting_value'] ?? '') ?>" placeholder="123456789012345">
                                                    <div class="form-text">Your Facebook Pixel ID for tracking (optional).</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_facebook" class="form-label">Facebook Page URL</label>
                                                    <input type="url" class="form-control" id="social_facebook" name="settings[social_facebook]" value="<?= htmlspecialchars($settings['social_facebook']['setting_value'] ?? '') ?>" placeholder="https://facebook.com/yourpage">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_messenger" class="form-label">Facebook Messenger Link</label>
                                                    <input type="url" class="form-control" id="social_messenger" name="settings[social_messenger]" value="<?= htmlspecialchars($settings['social_messenger']['setting_value'] ?? '') ?>" placeholder="https://m.me/yourpage">
                                                </div>
                                                <div class="col-12">
                                                    <label for="seo_meta_title" class="form-label">SEO Meta Title</label>
                                                    <input type="text" class="form-control" id="seo_meta_title" name="settings[seo_meta_title]" value="<?= htmlspecialchars($settings['seo_meta_title']['setting_value'] ?? '') ?>" placeholder="Your website title for search engines">
                                                    <div class="form-text">Default page title for SEO (leave blank to use company name).</div>
                                                </div>
                                                <div class="col-12">
                                                    <label for="seo_meta_description" class="form-label">SEO Meta Description</label>
                                                    <textarea class="form-control" id="seo_meta_description" name="settings[seo_meta_description]" rows="3" placeholder="Brief description of your website for search engines"><?= htmlspecialchars($settings['seo_meta_description']['setting_value'] ?? '') ?></textarea>
                                                    <div class="form-text">Meta description for search engine results (150-160 characters recommended).</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.12/dist/cropper.min.js"></script>
    <script>
        // Services management
        (function() {
            const addServiceBtn = document.getElementById('addService');
            if (!addServiceBtn) return;

            addServiceBtn.addEventListener('click', function() {
                const container = document.getElementById('servicesContainer');
                const index = container ? container.children.length : 0;
                const newItem = document.createElement('div');
                newItem.className = 'service-item mb-4 p-3 border rounded';
                newItem.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Service Title</label>
                            <input type="text" class="form-control" name="settings[services][${index}][title]" placeholder="e.g., Laptop Repair">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="2" name="settings[services][${index}][description]" placeholder="Brief description of the service"></textarea>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger remove-service">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Key Features (one per line)</label>
                            <textarea class="form-control" rows="3" name="settings[services][${index}][features]" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                        </div>
                    </div>
                `;
                if (container) container.appendChild(newItem);
            });
        })();

        // Remove service
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-service') || e.target.parentElement.classList.contains('remove-service')) {
                const btn = e.target.classList.contains('remove-service') ? e.target : e.target.parentElement;
                const serviceItem = btn.closest('.service-item');
                if (document.querySelectorAll('.service-item').length > 1) {
                    serviceItem.remove();
                } else {
                    // Don't remove the last one, just clear it
                    const inputs = serviceItem.querySelectorAll('input, textarea');
                    inputs.forEach(input => input.value = '');
                }
            }
        });

        // Announcements management
        (function() {
            const addAnnouncementBtn = document.getElementById('addAnnouncement');
            if (!addAnnouncementBtn) return;

            addAnnouncementBtn.addEventListener('click', function() {
                const container = document.getElementById('announcementsContainer');
                const index = container ? container.children.length : 0;
                const newItem = document.createElement('div');
                newItem.className = 'announcement-item mb-3 p-3 border rounded';
                newItem.innerHTML = `
                    <div class="row g-2">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="settings[announcements][${index}][title]" placeholder="Announcement title">
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control" name="settings[announcements][${index}][date]">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger remove-announcement">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="col-12">
                            <textarea class="form-control" name="settings[announcements][${index}][content]" rows="2" placeholder="Announcement content"></textarea>
                        </div>
                    </div>
                `;
                if (container) container.appendChild(newItem);
            });
        })();

        // Remove announcement
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-announcement') || e.target.parentElement.classList.contains('remove-announcement')) {
                const btn = e.target.classList.contains('remove-announcement') ? e.target : e.target.parentElement;
                const announcementItem = btn.closest('.announcement-item');
                if (document.querySelectorAll('.announcement-item').length > 0) {
                    announcementItem.remove();
                }
            }
        });

        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobileMenuToggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('mobile-open');
                }
            });
        }

        // Form submission
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            // Let normal form submission handle the business hours and services conversion
        });

        // Preview for logo and hero
        function previewImage(fileInputId, imgId) {
            const input = document.getElementById(fileInputId);
            const img = document.getElementById(imgId);
            if (!input || !img) return;
            input.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        previewImage('site_logo_file', 'siteLogoPreview');
        previewImage('hero_image_file', 'heroPreview');

        // Reload preview button
        const reloadBtn = document.getElementById('reloadPreviewBtn');
        if (reloadBtn) {
            reloadBtn.addEventListener('click', function() {
                const iframe = document.getElementById('sitePreviewIframe');
                if (iframe) {
                    iframe.src = iframe.src;
                }
            });
        }

        // Expand preview toggle
        const expandBtn = document.getElementById('expandPreviewBtn');
        const sitePreviewIframe = document.getElementById('sitePreviewIframe');
        let expanded = false;

        if (expandBtn && sitePreviewIframe) {
            expandBtn.addEventListener('click', () => {
                if (!expanded) {
                    sitePreviewIframe.style.height = '800px';
                    expandBtn.innerHTML = '<i class="bi bi-arrows-collapse"></i>';
                    expandBtn.title = 'Collapse Preview';
                } else {
                    sitePreviewIframe.style.height = '400px';
                    expandBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
                    expandBtn.title = 'Expand Preview';
                }
                expanded = !expanded;
            });
        }
    </script>

    <!-- Hero Crop Modal -->
    <div class="modal fade" id="heroCropModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crop Hero Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Aspect Ratio:</label>
                        <select id="heroAspectRatio" class="form-select">
                            <option value="2.4">2.4:1 (1200x500)</option>
                            <option value="NaN">Freeform</option>
                        </select>
                    </div>
                    <div id="heroCropContainer" style="max-height:400px; overflow:hidden;">
                        <img id="heroCropImage" style="max-width:100%;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyHeroCrop">Apply Crop</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hero crop functionality
        let heroCropper = null;

        (function() {
            const cropHeroBtn = document.getElementById('cropHeroBtn');
            if (!cropHeroBtn) return;

            cropHeroBtn.addEventListener('click', function() {
                const input = document.getElementById('hero_image_file');
                const file = input && input.files && input.files[0];
                if (!file) {
                    alert('Please select a hero image first.');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('heroCropImage');
                    img.src = e.target.result;
                    img.onload = function() {
                        if (heroCropper) heroCropper.destroy();
                        heroCropper = new Cropper(img, {
                            aspectRatio: 2.4,
                            viewMode: 1,
                            responsive: true,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: false,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: false,
                        });
                        const modal = new bootstrap.Modal(document.getElementById('heroCropModal'));
                        modal.show();
                    };
                };
                reader.readAsDataURL(file);
            });
        })();

        (function() {
            const heroAspect = document.getElementById('heroAspectRatio');
            if (heroAspect) {
                heroAspect.addEventListener('change', function() {
                    if (heroCropper) {
                        const ratio = this.value === 'NaN' ? NaN : parseFloat(this.value);
                        heroCropper.setAspectRatio(ratio);
                    }
                });
            }
        })();

        (function() {
            const applyHeroBtn = document.getElementById('applyHeroCrop');
            if (!applyHeroBtn) return;

            applyHeroBtn.addEventListener('click', function() {
                if (!heroCropper) return;
                const canvas = heroCropper.getCroppedCanvas({
                    width: 1200,
                    height: 500,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                const dataUrl = canvas.toDataURL('image/png');
                // Update preview
                const preview = document.getElementById('heroPreview');
                if (preview) preview.src = dataUrl;
                // Inject dataURL
                let hidden = document.querySelector('input[name="settings[hero_image_dataurl]"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'settings[hero_image_dataurl]';
                    document.getElementById('settingsForm').appendChild(hidden);
                }
                hidden.value = dataUrl;
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('heroCropModal'));
                modal.hide();
            });
        })();
    </script>

    <!-- Logo Crop Modal -->
    <div class="modal fade" id="logoCropModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crop Logo Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Aspect Ratio:</label>
                        <select id="logoAspectRatio" class="form-select">
                            <option value="1">1:1 (Square)</option>
                            <option value="NaN">Freeform</option>
                        </select>
                    </div>
                    <div id="logoCropContainer" style="max-height:400px; overflow:hidden;">
                        <img id="logoCropImage" style="max-width:100%;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyLogoCrop">Apply Crop</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Logo crop functionality
        let logoCropper = null;

        (function() {
            const cropLogoBtn = document.getElementById('cropLogoBtn');
            if (!cropLogoBtn) return;

            cropLogoBtn.addEventListener('click', function() {
                const input = document.getElementById('site_logo_file');
                const file = input && input.files && input.files[0];
                if (!file) {
                    alert('Please select a logo image first.');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('logoCropImage');
                    img.src = e.target.result;
                    img.onload = function() {
                        if (logoCropper) logoCropper.destroy();
                        logoCropper = new Cropper(img, {
                            aspectRatio: 1,
                            viewMode: 1,
                            responsive: true,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: false,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: false,
                        });
                        const modal = new bootstrap.Modal(document.getElementById('logoCropModal'));
                        modal.show();
                    };
                };
                reader.readAsDataURL(file);
            });
        })();

        (function() {
            const logoAspect = document.getElementById('logoAspectRatio');
            if (logoAspect) {
                logoAspect.addEventListener('change', function() {
                    if (logoCropper) {
                        const ratio = this.value === 'NaN' ? NaN : parseFloat(this.value);
                        logoCropper.setAspectRatio(ratio);
                    }
                });
            }
        })();

        (function() {
            const applyLogoBtn = document.getElementById('applyLogoCrop');
            if (!applyLogoBtn) return;

            applyLogoBtn.addEventListener('click', function() {
                if (!logoCropper) return;
                const canvas = logoCropper.getCroppedCanvas({
                    width: 240,
                    height: 240,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                });
                const dataUrl = canvas.toDataURL('image/png');
                // Update preview
                const preview = document.getElementById('siteLogoPreview');
                if (preview) preview.src = dataUrl;
                // Inject dataURL
                let hidden = document.querySelector('input[name="settings[site_logo_dataurl]"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'settings[site_logo_dataurl]';
                    document.getElementById('settingsForm').appendChild(hidden);
                }
                hidden.value = dataUrl;
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('logoCropModal'));
                modal.hide();
            });
        })();

        // Delete site logo
        (function() {
            const deleteSiteLogoBtn = document.getElementById('deleteSiteLogoBtn');
            if (!deleteSiteLogoBtn) return;

            deleteSiteLogoBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete the site logo?')) return;
            
            const preview = document.getElementById('siteLogoPreview');
            if (preview) {
                preview.src = '<?= ROOT_PATH ?>assets/images/placeholder-logo.png';
            }
            // Clear the file input
            const fileInput = document.getElementById('site_logo_file');
            if (fileInput) fileInput.value = '';
            
            // Add hidden input to clear the setting
            let hidden = document.querySelector('input[name="settings[delete_site_logo]"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'settings[delete_site_logo]';
                document.getElementById('settingsForm').appendChild(hidden);
            }
            hidden.value = '1';
            
            // Submit the form to actually delete
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            formData.append('section', 'design');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the delete flag after successful deletion
                    if (hidden) hidden.remove();
                    // Hide the delete button
                    this.style.display = 'none';
                    alert('Logo deleted successfully');
                    // Reload preview
                    const iframe = document.getElementById('sitePreviewIframe');
                    if (iframe) iframe.src = iframe.src;
                } else {
                    alert('Error deleting logo: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting.');
            });
            });
        })();

        // Delete hero image
        (function() {
            const deleteHeroImageBtn = document.getElementById('deleteHeroImageBtn');
            if (!deleteHeroImageBtn) return;

            deleteHeroImageBtn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete the hero image?')) return;
            
            const preview = document.getElementById('heroPreview');
            if (preview) {
                preview.src = '<?= ROOT_PATH ?>assets/images/placeholder-hero.png';
            }
            // Clear the file input
            const fileInput = document.getElementById('hero_image_file');
            if (fileInput) fileInput.value = '';
            
            // Add hidden input to clear the setting
            let hidden = document.querySelector('input[name="settings[delete_hero_image]"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'settings[delete_hero_image]';
                document.getElementById('settingsForm').appendChild(hidden);
            }
            hidden.value = '1';
            
            // Submit the form to actually delete
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            formData.append('section', 'design');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the delete flag after successful deletion
                    if (hidden) hidden.remove();
                    // Hide the delete button
                    this.style.display = 'none';
                    alert('Hero image deleted successfully');
                    // Reload preview
                    const iframe = document.getElementById('sitePreviewIframe');
                    if (iframe) iframe.src = iframe.src;
                } else {
                    alert('Error deleting hero image: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting.');
            });
            });
        })();
        
        // Clear delete flags when new files are selected
        (function() {
            const siteLogoInput = document.getElementById('site_logo_file');
            if (siteLogoInput) {
                siteLogoInput.addEventListener('change', function() {
                    const deleteFlag = document.querySelector('input[name="settings[delete_site_logo]"]');
                    if (deleteFlag) deleteFlag.remove();
                });
            }
        })();

        (function() {
            const heroImageInput = document.getElementById('hero_image_file');
            if (heroImageInput) {
                heroImageInput.addEventListener('change', function() {
                    const deleteFlag = document.querySelector('input[name="settings[delete_hero_image]"]');
                    if (deleteFlag) deleteFlag.remove();
                });
            }
        })();

        // Handle save section buttons
        document.querySelectorAll('.save-section-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const section = this.getAttribute('data-section');
                const form = document.getElementById('settingsForm');
                const formData = new FormData(form);
                formData.append('section', section);

                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
                this.disabled = true;

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Reset button
                    this.innerHTML = originalText;
                    this.disabled = false;

                    const messageDiv = document.querySelector('.alert');
                    if (messageDiv) {
                        messageDiv.className = 'alert alert-' + (data.success ? 'success' : 'danger');
                        messageDiv.innerHTML = data.message;
                        messageDiv.style.display = 'block';
                    } else {
                        alert(data.message);
                    }
                    
                    if (data.success) {
                        // Reload preview iframe
                        const iframe = document.getElementById('sitePreviewIframe');
                        if (iframe) {
                            iframe.src = iframe.src;
                        }
                        
                        // Reload page after 1 second to show updated settings
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                })
                .catch(error => {
                    // Reset button
                    this.innerHTML = originalText;
                    this.disabled = false;
                    
                    console.error('Error:', error);
                    alert('An error occurred while saving.');
                });
            });
        });

        // Update navigation active state on accordion show
        const collapses = ['collapse-company', 'collapse-design', 'collapse-hero', 'collapse-about', 'collapse-services', 'collapse-hours', 'collapse-footer', 'collapse-announcements', 'collapse-optional'];
        collapses.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('show.bs.collapse', function () {
                    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                    const navLink = document.querySelector(`a[data-bs-target="#${id}"]`);
                    if (navLink) navLink.classList.add('active');
                });
            }
        });
    </script>
</body>
</html>