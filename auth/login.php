<?php
require_once '../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    // Admins should go to the admin panel, customers should go to the public website root
    redirect(isAdmin() ? 'admin/dashboard.php' : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $pdo = $db->getConnection();
        
        CSRFProtection::validateToken($_POST['csrf_token'] ?? '');
        
        $email = Security::sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);

        // Check login attempts
        $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$email, LOCKOUT_TIME]);
        $attempts = $stmt->rowCount();

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            throw new Exception("Too many failed attempts. Please try again in 15 minutes.");
        }

        // Get user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            // Record failed attempt
            $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR']]);
            
            throw new Exception("Invalid email or password.");
        }

        // Clear login attempts
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['last_activity'] = time();

        // Remember me functionality (30 days)
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
            
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);
            
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), ROOT_PATH, '', false, true);
        }

    // Use absolute paths (prepend ROOT_PATH) so client redirects correctly from any folder
    // Admins -> admin panel; Customers -> customer dashboard
    $redirectPath = $user['role'] === 'admin' ? ROOT_PATH . 'admin/dashboard.php' : ROOT_PATH . 'customer/dashboard.php';
        jsonResponse(true, "Login successful!", [
            'redirect' => $redirectPath
        ]);

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
}
?>

<!-- Login form HTML similar to register.php but simpler -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - A.D. Soreta Electronics Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h2 class="brand-title"> A.D. Soreta Electronics Enterprises</h2>
                <p class="text-muted">Sign in to your account</p>
            </div>

            <form id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-input-container">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <i class="bi bi-eye-slash toggle-password-icon" data-target="password"></i>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>
                
                <div class="text-center">
                    <a href="forgot-password.php" class="text-decoration-none d-block mb-2">Forgot your password?</a>
                    <a href="register.php" class="text-decoration-none">Don't have an account? Sign up</a>
                </div>
            </form>
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

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing In...';

            try {
                const formData = new FormData(this);
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = result.data.redirect;
                } else {
                    alert(result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In';
            }
        });
    </script>
</body>
</html>