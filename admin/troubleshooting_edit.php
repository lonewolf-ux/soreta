<?php
require_once '../includes/config.php';
checkAuth();
if (!isAdmin()) redirect('customer/dashboard.php');

$db = new Database();
$pdo = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$parentIdPreset = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;
$isEdit = $id > 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $brand = Security::sanitizeInput($_POST['brand'] ?? '');
    $device_type = Security::sanitizeInput($_POST['device_type'] ?? '');
    $title = Security::sanitizeInput($_POST['title'] ?? '');
    $description = Security::sanitizeInput($_POST['description'] ?? '');
    $fix_steps = Security::sanitizeInput($_POST['fix_steps'] ?? '');
    $preventive_tip = Security::sanitizeInput($_POST['preventive_tip'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Basic validation
    if (!$title) $errors[] = 'Title is required.';

    // Handle image upload
    $image_path = null;
    $deleteImage = isset($_POST['delete_image']) && $_POST['delete_image'] == 1;
    @file_put_contents(__DIR__ . '/debug_troubleshooting.log', '[' . date('c') . '] POST_DATA: delete_image=' . ($deleteImage ? '1' : '0') . ' ; _POST_delete_image_raw=' . ($POST['delete_image'] ?? 'NOT_SET') . PHP_EOL, FILE_APPEND);

    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload error.';
        } else {
            if ($f['size'] > MAX_FILE_SIZE) $errors[] = 'Image too large.';
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $f['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ALLOWED_IMAGE_TYPES)) $errors[] = 'Invalid image type.';
            if (empty($errors)) {
                $destDir = __DIR__ . '/../uploads/troubleshooting/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $filename = 'guide_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $dest = $destDir . $filename;
                if (!move_uploaded_file($f['tmp_name'], $dest)) $errors[] = 'Failed to move uploaded file.';
                else $image_path = 'uploads/troubleshooting/' . $filename;
            }
        }
    }

    if (empty($errors)) {
        // Make DB updates resilient to schema differences: query existing columns
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM troubleshooting_guide");
            $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Prepare data map for possible columns
            $data = [];
            if (in_array('parent_id', $existingCols)) $data['parent_id'] = $parent_id;
            if (in_array('brand', $existingCols)) $data['brand'] = $brand;
            if (in_array('device_type', $existingCols)) $data['device_type'] = $device_type;
            if (in_array('title', $existingCols)) $data['title'] = $title;
            if (in_array('description', $existingCols)) $data['description'] = $description;
            if (in_array('fix_steps', $existingCols)) $data['fix_steps'] = $fix_steps;
            if (in_array('preventive_tip', $existingCols)) $data['preventive_tip'] = $preventive_tip;
            if (in_array('display_order', $existingCols)) $data['display_order'] = $display_order;
            if (in_array('is_active', $existingCols)) $data['is_active'] = $is_active;

            // Handle image_path specially: only include if new image uploaded, otherwise leave existing
            // OR if delete_image is set, clear the image_path
            $includeImage = !empty($image_path);
            if ($deleteImage && in_array('image_path', $existingCols)) {
                $data['image_path'] = null;
                $includeImage = true;
            } elseif ($includeImage && in_array('image_path', $existingCols)) {
                $data['image_path'] = $image_path;
            }

                if ($isEdit) {
                    // Fetch current DB row for debugging (compare before/after)
                    try {
                        $curStmt = $pdo->prepare('SELECT * FROM troubleshooting_guide WHERE id = ?');
                        $curStmt->execute([$id]);
                        $currentRow = $curStmt->fetch(PDO::FETCH_ASSOC);
                        $debugLine = '[' . date('c') . '] CURRENT_ROW: id=' . $id . ' ; row=' . json_encode($currentRow) . PHP_EOL;
                        @file_put_contents(__DIR__ . '/debug_troubleshooting.log', $debugLine, FILE_APPEND);
                    } catch (Exception $e) {
                        @file_put_contents(__DIR__ . '/debug_troubleshooting.log', '[' . date('c') . '] CURRENT_ROW_ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                    }

                    // Build dynamic UPDATE
                    $sets = [];
                    $values = [];
                    foreach ($data as $col => $val) {
                        $sets[] = "`$col` = ?";
                        $values[] = $val;
                    }
                    if (empty($sets)) {
                        // Nothing to update
                        $_SESSION['message'] = 'No updatable columns available in the database schema.';
                        $_SESSION['message_type'] = 'warning';
                        redirect('admin/troubleshooting.php');
                    }

                    // Detect no-op update (submitted values identical to current DB row)
                    try {
                        $noOp = true;
                        foreach ($data as $col => $val) {
                            $currVal = isset($currentRow[$col]) ? (string)$currentRow[$col] : '';
                            $newVal = (string)$val;
                            // Normalize newlines and trim for fair comparison
                            $currNorm = preg_replace("/\r\n|\r|\n/", "\n", trim($currVal));
                            $newNorm = preg_replace("/\r\n|\r|\n/", "\n", trim($newVal));
                            if ($currNorm !== $newNorm) {
                                $noOp = false;
                                break;
                            }
                        }
                        if ($noOp) {
                            $msg = 'No changes detected. The submitted values are identical to the current record.';
                            $_SESSION['message'] = $msg;
                            $_SESSION['message_type'] = 'info';
                            @file_put_contents(__DIR__ . '/debug_troubleshooting.log', '[' . date('c') . '] NOOP_UPDATE: id=' . $id . ' ; message="' . $msg . '"' . PHP_EOL, FILE_APPEND);
                            redirect('admin/troubleshooting.php');
                        }
                    } catch (Exception $e) {
                        @file_put_contents(__DIR__ . '/debug_troubleshooting.log', '[' . date('c') . '] NOOP_CHECK_ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
                    }
                $values[] = $id;
                $sql = "UPDATE troubleshooting_guide SET " . implode(', ', $sets) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                // Log update details to PHP error log and a workspace-accessible file for debugging
                $debugLine = '[' . date('c') . '] UPDATE: SQL=' . $sql . ' ; values=' . json_encode($values) . ' ; rowCount=' . $stmt->rowCount() . PHP_EOL;
                try { error_log($debugLine); } catch (Exception $e) {}
                @file_put_contents(__DIR__ . '/debug_troubleshooting.log', $debugLine, FILE_APPEND);
                $_SESSION['message'] = 'Troubleshooting guide updated successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                // Build dynamic INSERT
                $cols = array_keys($data);
                if (empty($cols)) {
                    $_SESSION['message'] = 'No insertable columns available in the database schema.';
                    $_SESSION['message_type'] = 'warning';
                    redirect('admin/troubleshooting.php');
                }
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = 'INSERT INTO troubleshooting_guide (' . implode(', ', array_map(function($c){return "`$c`";}, $cols)) . ') VALUES (' . $placeholders . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                $id = $pdo->lastInsertId();
                $debugLine = '[' . date('c') . '] INSERT: SQL=' . $sql . ' ; values=' . json_encode(array_values($data)) . ' ; newId=' . $id . PHP_EOL;
                try { error_log($debugLine); } catch (Exception $e) {}
                @file_put_contents(__DIR__ . '/debug_troubleshooting.log', $debugLine, FILE_APPEND);
                $_SESSION['message'] = 'Troubleshooting guide created successfully!';
                $_SESSION['message_type'] = 'success';
            }

            redirect('admin/troubleshooting.php');
        } catch (Exception $e) {
            // Surface DB error so admin can see what went wrong
            error_log('Troubleshooting edit failed: ' . $e->getMessage());
            $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Load entry for edit
$entry = null;
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM troubleshooting_guide WHERE id = ?');
    $stmt->execute([$id]);
    $entry = $stmt->fetch();
}

// Parent categories for selection
$stmt = $pdo->query('SELECT id, title FROM troubleshooting_guide WHERE parent_id IS NULL ORDER BY display_order');
$parents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Add' ?> Guide - Soreta Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/main.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/admin.css" rel="stylesheet">
    <style>
        .theme-toggle {
            background: var(--bg-tertiary);
            border: none;
            color: var(--text-primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .theme-toggle:hover {
            background: var(--hover-bg);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-section h5 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .help-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .preview-image {
            max-width: 200px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-top: 0.5rem;
        }

        .info-box {
            background: rgba(45, 136, 255, 0.1);
            border-left: 3px solid var(--primary-color);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .info-box h6 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .info-box p {
            color: var(--text-primary);
            margin: 0;
            font-size: 0.9375rem;
        }
    </style>
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
                    <div>
                        <h1 class="h3 mb-0"><?= $isEdit ? 'Edit' : 'Add' ?> Troubleshooting Guide</h1>
                        <small style="color: var(--text-secondary);">
                            <?= $isEdit ? 'Update existing guide' : 'Create a new troubleshooting guide' ?>
                        </small>
                    </div>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggle" title="Toggle theme">
                        <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                    </button>
                    <?php include_once '../includes/notification-component.php'; ?>
                </div>
            </header>

            <div class="admin-content">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Error!</strong> Please fix the following issues:
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$isEdit && $parentIdPreset): ?>
                    <?php 
                    $stmt = $pdo->prepare('SELECT title FROM troubleshooting_guide WHERE id = ?');
                    $stmt->execute([$parentIdPreset]);
                    $parentInfo = $stmt->fetch();
                    ?>
                    <div class="info-box">
                        <h6><i class="bi bi-info-circle"></i> Creating Sub-item</h6>
                        <p>You're creating a sub-item under: <strong><?= htmlspecialchars($parentInfo['title'] ?? 'Unknown') ?></strong></p>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <?= CSRFProtection::getHiddenField() ?>

                    <!-- Brand & Equipment Guide -->
                    <div class="info-box">
                        <h6><i class="bi bi-award"></i> Authorized Service Center</h6>
                        <p>
                            <strong>Supported Brands:</strong> SAMSUNG, SHARP, HAIER, DAIKIN, CARRIER<br>
                            <strong>Brown Line:</strong> SAMSUNG & SHARP household appliances<br>
                            <strong>Coverage:</strong> LED TV, Washing Machine, Refrigerator, Air Conditioner, Audio Systems, and Office Equipment
                        </p>
                    </div>

                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5><i class="bi bi-file-text"></i> Basic Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Brand</label>
                                <select name="brand" class="form-select">
                                    <option value="">-- All Brands --</option>
                                    <optgroup label="Major Brands">
                                        <option value="SAMSUNG" <?= ($entry['brand'] ?? '') == 'SAMSUNG' ? 'selected' : '' ?>>SAMSUNG</option>
                                        <option value="SHARP" <?= ($entry['brand'] ?? '') == 'SHARP' ? 'selected' : '' ?>>SHARP</option>
                                        <option value="HAIER" <?= ($entry['brand'] ?? '') == 'HAIER' ? 'selected' : '' ?>>HAIER</option>
                                        <option value="DAIKIN" <?= ($entry['brand'] ?? '') == 'DAIKIN' ? 'selected' : '' ?>>DAIKIN</option>
                                        <option value="CARRIER" <?= ($entry['brand'] ?? '') == 'CARRIER' ? 'selected' : '' ?>>CARRIER</option>
                                    </optgroup>
                                    <optgroup label="Brown Line">
                                        <option value="SAMSUNG_BROWN" <?= ($entry['brand'] ?? '') == 'SAMSUNG_BROWN' ? 'selected' : '' ?>>SAMSUNG (Brown Line)</option>
                                        <option value="SHARP_BROWN" <?= ($entry['brand'] ?? '') == 'SHARP_BROWN' ? 'selected' : '' ?>>SHARP (Brown Line)</option>
                                    </optgroup>
                                </select>
                                <div class="help-text">
                                    <i class="bi bi-info-circle"></i> 
                                    Select a specific brand or leave as "All Brands" for generic issues
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Equipment Type</label>
                                <select name="device_type" class="form-select">
                                    <option value="">-- All Equipment --</option>
                                    <optgroup label="Home Entertainment">
                                        <option value="LED_TV" <?= ($entry['device_type'] ?? '') == 'LED_TV' ? 'selected' : '' ?>>LED TV</option>
                                        <option value="AUDIO_SYSTEM" <?= ($entry['device_type'] ?? '') == 'AUDIO_SYSTEM' ? 'selected' : '' ?>>Audio System</option>
                                    </optgroup>
                                    <optgroup label="Kitchen Appliances">
                                        <option value="REFRIGERATOR" <?= ($entry['device_type'] ?? '') == 'REFRIGERATOR' ? 'selected' : '' ?>>Refrigerator</option>
                                        <option value="MICROWAVE" <?= ($entry['device_type'] ?? '') == 'MICROWAVE' ? 'selected' : '' ?>>Microwave</option>
                                        <option value="Electric kettle" <?= ($entry['device_type'] ?? '') == 'Electric kettle' ? 'selected' : '' ?>>Electric Kettle</option>
                                        <option value="Rice Cooker" <?= ($entry['device_type'] ?? '') == 'RICE_COOKER' ? 'selected' : '' ?>>Rice Cooker</option>
                                        <option value="Coffee Maker" <?= ($entry['device_type'] ?? '') == 'Coffee Maker' ? 'selected' : '' ?>>Coffee Maker</option>
                                    </optgroup>
                                    <optgroup label="Laundry">
                                        <option value="WASHING_MACHINE" <?= ($entry['device_type'] ?? '') == 'WASHING_MACHINE' ? 'selected' : '' ?>>Washing Machine</option>
                                        <option value="DRYER" <?= ($entry['device_type'] ?? '') == 'DRYER' ? 'selected' : '' ?>>Dryer</option>
                                    </optgroup>
                                    <optgroup label="Climate Control">
                                        <option value="AIR_CONDITIONER" <?= ($entry['device_type'] ?? '') == 'AIR_CONDITIONER' ? 'selected' : '' ?>>Air Conditioner</option>
                                        <option value="DEHUMIDIFIER" <?= ($entry['device_type'] ?? '') == 'DEHUMIDIFIER' ? 'selected' : '' ?>>Dehumidifier</option>
                                        <option value="Electric Fan" <?= ($entry['device_type'] ?? '') == 'Electric Fan' ? 'selected' : '' ?>>Electric Fan</option>
                                    </optgroup>
-                                    <optgroup label="Other Home Appliances">
                                     <option value="Vacuum Cleaner" <?= ($entry['device_type'] ?? '') == 'Vacuum Cleaner' ? 'selected' : '' ?>>Vacuum Cleaner</option>
                                     </optgroup>
                                    <optgroup label="Office Equipment">
                                        <option value="PRINTER" <?= ($entry['device_type'] ?? '') == 'PRINTER' ? 'selected' : '' ?>>Printer</option>
                                        <option value="PHOTOCOPIER" <?= ($entry['device_type'] ?? '') == 'PHOTOCOPIER' ? 'selected' : '' ?>>Photocopier</option>
                                        <option value="SCANNER" <?= ($entry['device_type'] ?? '') == 'SCANNER' ? 'selected' : '' ?>>Scanner</option>
                                    </optgroup>
                                </select>
                                <div class="help-text">
                                    <i class="bi bi-info-circle"></i>
                                    Specify equipment type for targeted troubleshooting
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Parent Category</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- None (Top-level Category) --</option>
                                <?php foreach ($parents as $p): ?>
                                    <option value="<?= $p['id'] ?>" 
                                        <?= ($entry && $entry['parent_id'] == $p['id']) || (!$entry && $parentIdPreset == $p['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">
                                <i class="bi bi-info-circle"></i> 
                                Leave empty to create a top-level category, or select a parent to create a sub-item
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($entry['title'] ?? '') ?>" 
                                   placeholder="e.g., Power Issues, Won't Turn On" required>
                            <div class="help-text">Brief, descriptive title for the problem or category</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Detailed description of the problem or category..."><?= htmlspecialchars($entry['description'] ?? '') ?></textarea>
                            <div class="help-text">Provide context and details to help users understand this issue</div>
                        </div>
                    </div>

                    <!-- Solution Steps -->
                    <div class="form-section">
                        <h5><i class="bi bi-list-check"></i> Solution Steps</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Fix Steps (one per line)</label>
                            <textarea name="fix_steps" class="form-control" rows="8" 
                                      placeholder="Step 1: Check if the power cable is connected&#10;Step 2: Press and hold the power button for 10 seconds&#10;Step 3: Try a different power outlet"><?= htmlspecialchars($entry['fix_steps'] ?? '') ?></textarea>
                            <div class="help-text">
                                <i class="bi bi-lightbulb"></i> 
                                Enter each step on a new line. Leave empty if this is a category with sub-items.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Preventive Tip</label>
                            <textarea name="preventive_tip" class="form-control" rows="3" 
                                      placeholder="To prevent this issue in the future..."><?= htmlspecialchars($entry['preventive_tip'] ?? '') ?></textarea>
                            <div class="help-text">Optional advice to help prevent this problem from recurring</div>
                        </div>
                    </div>

                    <!-- Media & Settings -->
                    <div class="form-section">
                        <h5><i class="bi bi-image"></i> Media & Settings</h5>
                        
                        <!-- Hidden field for delete_image flag (always in form, moved outside conditional) -->
                        <input type="hidden" name="delete_image" value="0" id="deleteImageInput">
                        
                        <div class="mb-3">
                            <label class="form-label">Image (jpg/png/webp, ≤3MB)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <?php if (!empty($entry['image_path'])): ?>
                                <div class="mt-2 d-flex gap-2 align-items-start">
                                    <img src="<?= ROOT_PATH . htmlspecialchars($entry['image_path']) ?>" 
                                         class="preview-image" 
                                         alt="Current image">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="deleteImageBtn" onclick="toggleDeleteImageConfirm()">
                                        <i class="bi bi-trash"></i> Delete Image
                                    </button>
                                </div>
                                <div id="deleteImageConfirm" style="display:none; margin-top:0.5rem; padding: 1rem; background: #fff3cd; border-radius: 6px; border: 1px solid #ffc107;">
                                    <small class="text-muted"><strong>⚠️ Are you sure you want to delete this image?</strong></small>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteImage()">
                                            <i class="bi bi-check"></i> Yes, Delete Image
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelDeleteImage()">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="help-text">Upload an image to help visualize the problem or solution</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" name="display_order" class="form-control" 
                                       value="<?= htmlspecialchars($entry['display_order'] ?? 0) ?>" 
                                       min="0">
                                <div class="help-text">Lower numbers appear first</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label d-block">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input type="checkbox" name="is_active" class="form-check-input" 
                                           id="is_active" role="switch"
                                           <?= (!isset($entry) || $entry['is_active']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        Active (visible to customers)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> 
                            <?= $isEdit ? 'Save Changes' : 'Create Guide' ?>
                        </button>
                        <a href="troubleshooting.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete image confirmation helpers
        function toggleDeleteImageConfirm() {
            const confirm = document.getElementById('deleteImageConfirm');
            if (confirm) {
                confirm.style.display = confirm.style.display === 'none' ? 'block' : 'none';
            }
        }

        function confirmDeleteImage() {
            const input = document.getElementById('deleteImageInput');
            if (input) {
                input.value = '1';
                console.log('Delete image flag set to 1');
            }
            const confirm = document.getElementById('deleteImageConfirm');
            if (confirm) {
                confirm.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> <strong>Image marked for deletion.</strong> Click "Save Changes" to confirm.</div>';
            }
            const btn = document.getElementById('deleteImageBtn');
            if (btn) {
                btn.style.display = 'none';
            }
        }

        function cancelDeleteImage() {
            const input = document.getElementById('deleteImageInput');
            if (input) {
                input.value = '0';
            }
            const confirm = document.getElementById('deleteImageConfirm');
            if (confirm) {
                confirm.style.display = 'none';
            }
        }

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }

        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('mobile-open');
                }
            });
        }

        // Image preview
        const imageInput = document.querySelector('input[type="file"][name="image"]');
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const existingPreview = document.querySelector('.preview-image');
                        if (existingPreview) {
                            existingPreview.src = e.target.result;
                        } else {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'preview-image';
                            imageInput.parentNode.appendChild(img);
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>