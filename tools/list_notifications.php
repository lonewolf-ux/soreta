<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->query("SELECT id, user_id, title, message, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 20");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Recent notifications:\n";
    foreach ($rows as $r) {
        echo sprintf("#%d user_id=%s is_read=%s created=%s\n title=%s\n message=%s\n\n",
            $r['id'], $r['user_id'], $r['is_read'], $r['created_at'], $r['title'], $r['message']);
    }

    if (empty($rows)) echo "(none)\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
