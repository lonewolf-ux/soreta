<?php
require_once 'includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

echo "Current settings:\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    echo $row['setting_key'] . ": " . substr($row['setting_value'], 0, 50) . "\n";
}

echo "\nUpdating company_name to 'Test Company Name'\n";
$stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
$stmt->execute(['Test Company Name', 'company_name']);

echo "Updated.\n";

echo "\nReloading settings:\n";
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'company_name'");
$row = $stmt->fetch();
echo $row['setting_key'] . ": " . $row['setting_value'] . "\n";

echo "\nTest complete.\n";
