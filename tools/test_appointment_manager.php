<?php
require_once __DIR__ . '/../includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $am = new AppointmentManager($pdo);
    $ref = new ReflectionClass($am);
    echo "AppointmentManager defined in: " . $ref->getFileName() . "\n";
    echo "Methods available:\n";
    foreach ($ref->getMethods() as $m) {
        echo " - " . $m->getName() . "\n";
    }
    // Attempt to call getAppointmentStats if present
    if ($ref->hasMethod('getAppointmentStats')) {
        $stats = $am->getAppointmentStats();
        echo "OK - stats:\n" . print_r($stats, true);
    } else {
        echo "Method getAppointmentStats() not found on instantiated class.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . " - " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
