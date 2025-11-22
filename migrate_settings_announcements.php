<?php
require_once 'includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Get announcements from settings
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'announcements'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && !empty($result['setting_value'])) {
        $announcementsFromSettings = json_decode($result['setting_value'], true);
        if (is_array($announcementsFromSettings)) {
            echo "Found " . count($announcementsFromSettings) . " announcements in settings.\n";

            // Get existing announcements from table
            $existingStmt = $pdo->query("SELECT title, date FROM announcements");
            $existing = $existingStmt->fetchAll(PDO::FETCH_KEY_PAIR); // title => date

            $inserted = 0;
            $skipped = 0;

            foreach ($announcementsFromSettings as $ann) {
                $title = $ann['title'] ?? '';
                $date = $ann['date'] ?? date('Y-m-d');
                $content = $ann['content'] ?? '';

                // Check if this announcement already exists in table
                if (isset($existing[$title])) {
                    echo "Skipping existing announcement: $title\n";
                    $skipped++;
                    continue;
                }

                // Insert new announcement
                $insertStmt = $pdo->prepare('INSERT INTO announcements (title, content, date, is_active, display_order) VALUES (?, ?, ?, 1, ?)');
                $insertStmt->execute([$title, $content, $date, count($existing) + $inserted]);
                echo "Inserted announcement: $title\n";
                $inserted++;
            }

            echo "Migration complete: $inserted inserted, $skipped skipped.\n";
        } else {
            echo "Invalid JSON in settings.\n";
        }
    } else {
        echo "No announcements found in settings.\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
