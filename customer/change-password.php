<?php
require_once '../includes/config.php';
checkAuth();

if (isAdmin()) {
    jsonResponse(false, "Admins cannot change password from this page.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRFProtection::validateToken($_POST['csrf_token']);
        
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            throw new Exception("All password fields are required.");
        }
        
        if ($new_password !== $confirm_new_password) {
            throw new Exception("New passwords do not match.");
        }
        
        if (!Security::validatePassword($new_password)) {
            throw new Exception("New password must be at least 8 characters with 1 letter and 1 number.");
        }
        
        $db = new Database();
        $pdo = $db->getConnection();
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            throw new Exception("Current password is incorrect.");
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
        
        jsonResponse(true, "Password changed successfully!");
        
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
}
?>