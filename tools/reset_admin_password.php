<?php
require_once __DIR__ . '/../includes/config.php';

$adminEmail = $argv[1] ?? 'admin@soreta.com';
$newPassword = $argv[2] ?? 'Admin123!';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
    $stmt->execute([$hash, $adminEmail]);
    echo "Password for $adminEmail updated (rows affected: " . $stmt->rowCount() . ")\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
