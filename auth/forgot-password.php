<?php
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $pdo = $db->getConnection();
        
        CSRFProtection::validateToken($_POST['csrf_token']);
        
        $email = Security::sanitizeInput($_POST['email']);
        
        if (!Security::validateEmail($email)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            
            // Delete any existing tokens for this email
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);
            
            // Insert new token
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);
            
            // Send email (you'll need to implement PHPMailer)
            $resetLink = "http://localhost" . ROOT_PATH . "auth/reset-password.php?token=" . $token;
            
            // For now, we'll just return the link - in production, send via email
            jsonResponse(true, "Password reset instructions have been sent to your email.", [
                'debug_link' => $resetLink // Remove this in production
            ]);
        } else {
            // Don't reveal whether email exists
            jsonResponse(true, "If that email address exists in our system, we've sent password reset instructions.");
        }

    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Soreta Electronics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h2 class="brand-title">Forgot Password</h2>
                <p class="text-muted">Enter your email to reset your password</p>
            </div>

            <form id="forgotPasswordForm">
                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="form-text">We'll send reset instructions to this email</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Instructions</button>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';

            try {
                const formData = new FormData(this);
                const response = await fetch('forgot-password.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    if (result.data && result.data.debug_link) {
                        console.log('Reset link:', result.data.debug_link); // Remove in production
                    }
                    window.location.href = 'login.php';
                } else {
                    alert(result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Reset Instructions';
            }
        });
    </script>
</body>
</html>