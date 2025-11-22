<?php
/**
 * Soreta Electronics - Core Configuration
 * Database connection, security settings, and global functions
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session security settings - only if session not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'soreta_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application paths
define('ROOT_PATH', '/soreta/');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . ROOT_PATH . 'uploads/');

// SMTP configuration for PHPMailer
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM', 'noreply@soreta-electronics.com');
define('SMTP_FROM_NAME', 'Soreta Electronics');

// Security constants
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds
define('SESSION_TIMEOUT', 1800); // 30 minutes

// File upload constraints
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Include autoloader
require_once 'autoload.php';

/**
 * CSRF Token Management
 */
class CSRFProtection {
    /**
     * Generate and store a CSRF token in session
     * @return string
     */
    public static function generateToken() {
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || time() - $_SESSION['csrf_token_time'] > 1800) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a given token against the session
     * @param string $token
     * @return bool
     */
    public static function validateToken($token) {
        if (empty($token) || empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        // Optional: Regenerate token after validation for added security
        $_SESSION['csrf_token'] = self::generateToken();
        return true;
    }

    /**
     * Generate a hidden input field containing the CSRF token
     * @return string
     */
    public static function getHiddenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Backwards-compatible alias used by some pages
     */
    public static function validatePassword($password) {
        return Security::validatePasswordPolicy($password);
    }

    /**
     * Hash a password using bcrypt
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verify a password against a hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate a Job Order number of the form JO-YYYYMMDD-0001
     * This uses the appointments table to determine the next sequence for the day.
     * @param PDO $pdo
     * @return string
     */
    public static function generateJobOrderNo(PDO $pdo) {
        $date = new DateTime('now');
        $dstr = $date->format('Ymd');
        try {
            $stmt = $pdo->prepare("SELECT job_order_no FROM appointments WHERE job_order_no LIKE ? ORDER BY id DESC LIMIT 1");
            $like = "JO-{$dstr}-%";
            $stmt->execute([$like]);
            $last = $stmt->fetchColumn();

            if ($last) {
                // extract last 4 digits
                $parts = explode('-', $last);
                $seq = (int)end($parts);
                $next = $seq + 1;
            } else {
                $next = 1;
            }

            return sprintf('JO-%s-%04d', $dstr, $next);
        } catch (Exception $e) {
            // fallback
            return sprintf('JO-%s-%04d', $dstr, random_int(1000, 9999));
        }
    }
}

// Global helper functions
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        redirect('auth/login.php');
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        redirect('auth/login.php');
    }

    $_SESSION['last_activity'] = time();
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function redirect($url) {
    header('Location: ' . ROOT_PATH . $url);
    exit;
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>
