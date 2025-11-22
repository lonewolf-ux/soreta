<?php
require_once __DIR__ . '/../includes/config.php';

$email = 'testuser+' . time() . '@example.com';
$password = 'Test1234';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Prepare data matching register.php
    $name = 'Test User';
    $contact_number = '09170000000';
    $address = 'Test Address';

    // Server-side validation
    if (!Security::validateEmail($email)) throw new Exception('Invalid email');
    if (!Security::validatePassword($password)) throw new Exception('Weak password');

    // Check duplicates
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already registered');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, contact_number, address, role, is_active) VALUES (?, ?, ?, ?, ?, "customer", 1)');
    $stmt->execute([$name, $email, $hash, $contact_number, $address]);

    echo json_encode(['success' => true, 'message' => 'Registered', 'email' => $email]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
