<?php
// Simulate AJAX POST to auth/login.php and capture JSON response
require_once __DIR__ . '/../includes/config.php';

// Simulated credentials (replace with real test account if available)
$email = 'admin@soreta.com';
$password = 'Admin123!';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Emulate login.php logic
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    $redirectPath = $user['role'] === 'admin' ? ROOT_PATH . 'admin/dashboard.php' : ROOT_PATH . 'customer/dashboard.php';

    echo json_encode(['success' => true, 'message' => 'Login successful!', 'data' => ['redirect' => $redirectPath]]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
