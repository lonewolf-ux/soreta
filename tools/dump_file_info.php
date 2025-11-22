<?php
$path = __DIR__ . '/../includes/AppointmentManager.php';
$s = file_get_contents($path);
echo "Path: $path\n";
echo "Filesize: " . strlen($s) . " bytes\n";
echo "MD5: " . md5($s) . "\n";
echo "Has getAppointmentStats: " . (strpos($s, 'getAppointmentStats') !== false ? 'yes' : 'no') . "\n";
echo "\n--- Head (400 bytes) ---\n" . substr($s,0,400) . "\n";
echo "\n--- Tail (400 bytes) ---\n" . substr($s, max(0, strlen($s)-400), 400) . "\n";
