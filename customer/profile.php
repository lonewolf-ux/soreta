<?php
require_once '../includes/config.php';
checkAuth();

if (isAdmin()) {
    redirect('admin/dashboard.php');
}

$db = new Database();
$pdo = $db->getConnection();

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    redirect('../auth/logout.php');
}

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

// Get user data for display
$userStmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userData = $userStmt->fetch();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);
        
        $name = Security::sanitizeInput($_POST['name']);
        $contact_number = Security::sanitizeInput($_POST['contact_number']);
        $address = Security::sanitizeInput($_POST['address']);
        
        if (empty($name) || empty($contact_number) || empty($address)) {
            throw new Exception("All fields are required.");
        }
        
        // Update user profile
        $stmt = $pdo->prepare("UPDATE users SET name = ?, contact_number = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $contact_number, $address, $_SESSION['user_id']]);
        
        // Update session
        $_SESSION['user_name'] = $name;
        
        $success_message = "Profile updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
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
    <title>My Profile - <?= htmlspecialchars($settings['company_name'] ?? 'Soreta Electronics') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/unified-theme.css" rel="stylesheet">
    <?php
    $favicon = $settings['favicon'] ?? '';
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
        .profile-container {
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

        /* Profile Card */
        .profile-card {
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
            min-height: 80px;
        }

        .form-select option {
            background: #1e293b;
            color: #f1f5f9;
        }

        .form-text {
            color: #94a3b8 !important;
            font-size: 0.875rem;
        }

        .form-control:disabled {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
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
            flex-shrink: 0;
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

        /* Password Change Card */
        .password-section {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
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

        /* Info Box */
        .info-box {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .info-box i {
            font-size: 1.5rem;
            color: var(--primary);
            flex-shrink: 0;
        }

        .info-box-text {
            color: #cbd5e0;
        }

        .info-box-text small {
            display: block;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 1rem 0.75rem;
                padding-top: calc(56px + 1rem);
            }

            .page-title {
                font-size: 1.75rem;
            }

            .page-subtitle {
                font-size: 1rem;
            }

            .profile-card {
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
                $logo = $settings['site_logo'] ?? '';
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
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

    <div class="profile-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Manage your account information</p>
        </div>

        <!-- Profile Card -->
        <div class="profile-card fade-in">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Account Information -->
            <div class="info-box">
                <i class="bi bi-info-circle"></i>
                <div class="info-box-text">
                    Member since <?= htmlspecialchars(date('F j, Y', strtotime($userData['created_at'] ?? 'now'))) ?>
                    <small>Account ID: #<?= htmlspecialchars($_SESSION['user_id']) ?></small>
                </div>
            </div>

            <!-- Edit Profile Section -->
            <div class="section-header">
                <i class="bi bi-pencil-square"></i>
                <h3>Edit Profile</h3>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <div class="form-text"><i class="bi bi-lock-fill me-1"></i>Email cannot be changed</div>
                    </div>

                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact Number *</label>
                        <input type="tel" class="form-control" id="contact_number" name="contact_number" 
                               value="<?= htmlspecialchars($user['contact_number']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">Address *</label>
                        <textarea class="form-control" id="address" name="address" required><?= htmlspecialchars($user['address']) ?></textarea>
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-end flex-wrap">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>

            <!-- Change Password Section -->
            <div class="password-section">
                <div class="section-header">
                    <i class="bi bi-key"></i>
                    <h3>Change Password</h3>
                </div>

                <form method="POST" action="change-password.php" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>

                        <div class="col-md-6">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                        </div>

                        <div class="col-12">
                            <div class="form-text mb-3">
                                <i class="bi bi-info-circle me-1"></i>Password must be at least 8 characters long
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-lock me-2"></i>Update Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

        // Password validation
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_new_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match. Please check and try again.');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                return false;
            }
        });
    </script>
</body>
</html>