<?php
/**
 * Manual class loader for Soreta Electronics
 * Use this if autoloader doesn't work
 */

function loadRequiredClasses() {
    $classes = [
        'Database' => __DIR__ . '/Database.php',
        'AppointmentManager' => __DIR__ . '/AppointmentManager.php',
        'CSRFProtection' => __DIR__ . '/config.php', // CSRF is in config
        'Security' => __DIR__ . '/config.php', // Security is in config
    ];
    
    foreach ($classes as $class => $file) {
        if (!class_exists($class) && file_exists($file)) {
            require_once $file;
        }
    }
}

// Load all required classes
loadRequiredClasses();
?>