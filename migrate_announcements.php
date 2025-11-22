<?php
require_once 'includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Create announcements table
    $sql = file_get_contents('sql/create_announcements_table.sql');
    $pdo->exec($sql);
    echo 'Announcements table created successfully.' . PHP_EOL;

    // Migrate existing announcements from settings
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'announcements'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['setting_value'])) {
        $announcements = json_decode($result['setting_value'], true);
        if (is_array($announcements)) {
            $insertStmt = $pdo->prepare('INSERT INTO announcements (title, content, date, is_active, display_order) VALUES (?, ?, ?, 1, ?)');
            $order = 0;
            foreach ($announcements as $announcement) {
                $date = !empty($announcement['date']) ? $announcement['date'] : date('Y-m-d');
                $insertStmt->execute([
                    $announcement['title'] ?? 'Untitled',
                    $announcement['content'] ?? '',
                    $date,
                    $order++
                ]);
            }
            echo 'Migrated ' . count($announcements) . ' announcements from settings.' . PHP_EOL;
        }
    }

    echo 'Migration completed successfully.' . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
