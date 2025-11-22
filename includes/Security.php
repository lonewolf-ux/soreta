<?php
/**
 * Security Class
 * Handles input validation, sanitization, and security-related functions
 */
class Security {
    /**
     * Sanitize input to prevent XSS attacks
     * @param string $input
     * @return string
     */
    public static function sanitizeInput($input) {
        if (is_null($input)) {
            return '';
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email format
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password policy
     * @param string $password
     * @return bool
     */
    public static function validatePasswordPolicy($password) {
        // At least 8 characters, 1 letter, 1 number
        return strlen($password) >= 8 &&
               preg_match('/[a-zA-Z]/', $password) &&
               preg_match('/[0-9]/', $password);
    }

    /**
     * Validate password (alias for backwards compatibility)
     * @param string $password
     * @return bool
     */
    public static function validatePassword($password) {
        return self::validatePasswordPolicy($password);
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
?>
