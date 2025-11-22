<?php
require_once '../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

// Default services
$services = [
    'Repair',
    'Maintenance',
    'Installation',
    'Troubleshooting',
    'Parts Replacement',
    'Software Update',
    'Diagnostics'
];

try {
    // Convert array to JSON
    $servicesJson = json_encode($services);
    
    // First try to update if exists
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('services', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$servicesJson, $servicesJson]);
    
    echo "Services have been set up successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>