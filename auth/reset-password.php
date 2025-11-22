<?php
require_once '../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validate token
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();
    
    if (!$resetRequest) {
        $error = "Invalid or expired reset link. Please request a new password reset.";
        $token = ''; // Clear invalid token
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);
        
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($password) || empty($confirm_password)) {
            throw new Exception("Please fill in all fields.");
        }
        
        if (!Security::validatePassword($password)) {
            throw new Exception("Password must be at least 8 characters with 1 letter and 1 number.");
        }
        
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }
        
        // Hash new password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Update user password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$password_hash, $resetRequest['email']]);
        
        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        $success = "Password reset successfully! You can now login with your new password.";
        $token = ''; // Clear token after successful reset
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Soreta Electronics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h2 class="brand-title">Reset Password</h2>
                <p class="text-muted">
                    <?= $token ? 'Enter your new password' : 'Password Reset' ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php elseif ($token): ?>
                <form method="POST" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="password-input-container">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <i class="bi bi-eye-slash toggle-password-icon" data-target="password"></i>
                        </div>
                        <div class="form-text">Minimum 8 characters with 1 letter and 1 number</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="password-input-container">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <i class="bi bi-eye-slash toggle-password-icon" data-target="confirm_password"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-3">Reset Password</button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <p class="text-muted">Please check your email for a valid password reset link.</p>
                    <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <a href="login.php" class="text-decoration-none">Back to Login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.querySelectorAll('.toggle-password-icon').forEach(icon => {
            icon.addEventListener('click', function() {
                const target = document.getElementById(this.dataset.target);
                const type = target.type === 'password' ? 'text' : 'password';
                target.type = type;
                if (type === 'password') {
                    this.className = 'bi bi-eye-slash toggle-password-icon';
                } else {
                    this.className = 'bi bi-eye toggle-password-icon';
                }
            });
        });

        // Form validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
        });
    </script>
</body>
</html>