<?php
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $pdo = $db->getConnection();
        
        // Validate CSRF token
        CSRFProtection::validateToken($_POST['csrf_token'] ?? '');
        
        // Sanitize and validate inputs
        $name = Security::sanitizeInput($_POST['name']);
        $email = Security::sanitizeInput($_POST['email']);
        $contact_number = Security::sanitizeInput($_POST['contact_number']);
        $address = Security::sanitizeInput($_POST['address']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validation
        if (empty($name) || empty($email) || empty($contact_number) || empty($address) || empty($password)) {
            throw new Exception("All fields are required.");
        }

        if (!Security::validateEmail($email)) {
            throw new Exception("Invalid email format.");
        }

        if (!Security::validatePassword($password)) {
            throw new Exception("Password must be at least 8 characters with 1 letter and 1 number.");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email already registered.");
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, contact_number, address, role) 
            VALUES (?, ?, ?, ?, ?, 'customer')
        ");
        $stmt->execute([$name, $email, $password_hash, $contact_number, $address]);

        // Auto-login after registration
        $user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['user_name'] = $name;
        $_SESSION['last_activity'] = time();

        jsonResponse(true, "Registration successful! Redirecting...", [
            'redirect' => ROOT_PATH . 'customer/dashboard.php'
        ]);

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
    <title>Register - A.D. Soreta Electronics Eterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ROOT_PATH ?>assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h2 class="brand-title">A.D. Soreta Electronics Enterprises</h2>
                <p class="text-muted">Create your account</p>
            </div>

            <form id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= CSRFProtection::generateToken() ?>">
                
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>

                <div class="mb-3">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" id="contact_number" name="contact_number" required>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-input-container">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <i class="bi bi-eye-slash toggle-password-icon" data-target="password"></i>
                    </div>
                    <div class="form-text">Minimum 8 characters with 1 letter and 1 number</div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="password-input-container">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <i class="bi bi-eye-slash toggle-password-icon" data-target="confirm_password"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Create Account</button>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">Already have an account? Sign in</a>
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

        // Form submission
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                const formData = new FormData(this);
                const response = await fetch('register.php', {
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
                submitBtn.textContent = 'Create Account';
            }
        });
    </script>
</body>
</html>