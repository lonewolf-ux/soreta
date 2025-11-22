<?php
require_once 'includes/config.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query('SELECT id, title, is_active, date FROM announcements');
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'Announcements in table with active status:' . PHP_EOL;
foreach ($announcements as $ann) {
    echo 'ID: ' . $ann['id'] . ', Title: ' . $ann['title'] . ', Active: ' . ($ann['is_active'] ? 'YES' : 'NO') . ', Date: ' . $ann['date'] . PHP_EOL;
}
?>
