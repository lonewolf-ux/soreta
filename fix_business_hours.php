<?php
require_once 'includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $business_hours = array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '9:00 AM - 6:00 PM');
    $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute([json_encode($business_hours), 'business_hours']);
    echo 'Updated business_hours to per-day format.';
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
