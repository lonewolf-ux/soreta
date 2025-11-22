<?php
require_once __DIR__ . '/../includes/config.php';

$email = $argv[1] ?? 'juan@email.com';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "Found user: " . $user['name'] . " (id=" . $user['id'] . ")\n";
    } else {
        echo "No active user found with email: $email\n";
    }
} catch (PDOException $e) {
    echo "PDO ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
