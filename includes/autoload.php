<?php
/**
 * Autoloader for Soreta Electronics Classes
 * Automatically loads classes when they are instantiated
 */

spl_autoload_register(function ($class_name) {
    // List of directories to search for classes
    $directories = [
        __DIR__ . '/',
        __DIR__ . '/../admin/',
        __DIR__ . '/../customer/',
        __DIR__ . '/../auth/',
        __DIR__ . '/../feedback/',
    ];
    
    // Convert class name to file name
    $file_name = $class_name . '.php';
    
    // Search in each directory
    foreach ($directories as $directory) {
        $file_path = $directory . $file_name;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
    
    // If class not found, log error but don't break
    error_log("Class not found: " . $class_name);
});

// No manual requires needed - let autoloader handle everything
?>