<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    jsonResponse(false, "Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        CSRFProtection::validateToken($input['csrf_token'] ?? '');
        
        $db = new Database();
        $pdo = $db->getConnection();
        
        // Check if preferences exist
        $stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE user_preferences SET preferences = ? WHERE user_id = ?");
            $stmt->execute([json_encode($input['preferences']), $_SESSION['user_id']]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, preferences) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], json_encode($input['preferences'])]);
        }
        
        jsonResponse(true, "Preferences updated successfully.");
        
    } catch (Exception $e) {
        jsonResponse(false, $e->getMessage());
    }
}
?>