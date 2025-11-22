<?php
/**
 * Quick Setup Script for Soreta Electronics
 * Creates directories and tests database connection
 */

// Allow only from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Setup can only be run from localhost.');
}

echo "<h2>Soreta Electronics - Quick Setup</h2>";

// Test database connection
echo "<h3>Testing Database Connection...</h3>";
try {
    require_once 'includes/config.php';
    $db = new Database();
    $pdo = $db->getConnection();
    echo "✓ Database connection successful!<br>";
    
    // Test if tables exist
    $tables = ['users', 'appointments', 'technicians'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "✓ Table '$table' exists<br>";
        } else {
            echo "✗ Table '$table' missing - run install.php<br>";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    echo "Please run install.php first to create the database.<br>";
}

// Check required files
echo "<h3>Checking Required Files...</h3>";
$required_files = [
    'includes/config.php',
    'includes/Database.php',
    'includes/AppointmentManager.php',
    'includes/autoload.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file missing<br>";
    }
}

// Check directories
echo "<h3>Checking Directories...</h3>";
$required_dirs = [
    'assets/css',
    'assets/js', 
    'uploads',
    'admin/layout'
];

foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "✓ $dir/ exists<br>";
    } else {
        echo "✗ $dir/ missing - creating...<br>";
        mkdir($dir, 0755, true);
    }
}

echo "<hr><h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If database tables are missing, run <a href='install.php'>install.php</a></li>";
echo "<li>If files are missing, check the file structure</li>";
echo "<li>Visit <a href='index.php'>public website</a> to test</li>";
echo "<li>Login as admin: <a href='auth/login.php'>admin@soreta.com</a> / Admin123!</li>";
echo "</ol>";

echo "<p><strong>Note:</strong> Delete setup.php after installation.</p>";
?>