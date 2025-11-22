<?php
require_once 'includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("DELETE FROM settings WHERE `key` = ''");
    $stmt->execute();
    echo "Deleted rows with empty key\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
