<?php
require_once __DIR__ . '/../includes/config.php';

echo "Checking AppointmentManager class...\n";

if (class_exists('AppointmentManager')) {
    $ref = new ReflectionClass('AppointmentManager');
    echo "Class already exists, defined in: " . $ref->getFileName() . "\n";
    echo "Methods:\n";
    foreach ($ref->getMethods() as $m) echo " - " . $m->getName() . "\n";
} else {
    echo "AppointmentManager NOT defined yet.\n";
}

echo "\nNow requiring includes/AppointmentManager.php explicitly...\n";
require_once __DIR__ . '/../includes/AppointmentManager.php';

if (class_exists('AppointmentManager')) {
    $ref = new ReflectionClass('AppointmentManager');
    echo "After require: defined in: " . $ref->getFileName() . "\n";
    echo "Methods:\n";
    foreach ($ref->getMethods() as $m) echo " - " . $m->getName() . "\n";
} else {
    echo "Still not defined after require.\n";
}

// Print a snippet of the file around the later methods to ensure they exist on disk
$path = __DIR__ . '/../includes/AppointmentManager.php';
$contents = file_get_contents($path);
$lines = explode("\n", $contents);
$start = 150; $end = 230;
echo "\nFile snippet (lines $start-$end):\n";
for ($i=$start; $i<=$end && $i<count($lines); $i++) {
    printf("%4d: %s\n", $i+1, $lines[$i]);
}
