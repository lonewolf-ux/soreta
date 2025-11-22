<?php
require_once 'includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Read the SQL file
    $sql = file_get_contents('sql/add_appointment_fields.sql');

    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...<br>";
        }
    }

    echo "All SQL statements executed successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
