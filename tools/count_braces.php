<?php
$s = file_get_contents(__DIR__ . '/../includes/AppointmentManager.php');
echo 'Length: ' . strlen($s) . "\n";
echo 'Open { : ' . substr_count($s, '{') . "\n";
echo 'Close } : ' . substr_count($s, '}') . "\n";
$pos = strpos($s, 'public function getAppointmentStats');
if ($pos === false) {
    echo "getAppointmentStats not found in source text\n";
} else {
    echo "getAppointmentStats found at byte pos: $pos\n";
}
