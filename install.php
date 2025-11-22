<?php
/**
 * Installation Script for Soreta Electronics
 * Run this once to set up the database and initial admin user
 */

// Allow access only from localhost for security
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Installation can only be run from localhost.');
}

echo "<h1>Soreta Electronics - Installation</h1>";

try {
    // Create database connection without selecting database first
    $pdo = new PDO("mysql:host=localhost", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS soreta_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE soreta_db");
    
    echo "✓ Database created or already exists<br>";

    // Read and execute schema
    $schema = file_get_contents('sql/schema.sql');
    $pdo->exec($schema);
    echo "✓ Database schema created<br>";

    // Execute security tables
    $security_schema = file_get_contents('sql/security_tables.sql');
    $pdo->exec($security_schema);
    echo "✓ Security tables created<br>";

    // Insert sample data
    $sample_data = file_get_contents('sql/sample_data.sql');
    $pdo->exec($sample_data);
    echo "✓ Sample data inserted<br>";

    echo "<h3>🎉 Installation Completed Successfully!</h3>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li><a href='index.php'>Visit the public website</a></li>";
    echo "<li><a href='auth/login.php'>Login as admin (admin@soreta.com / Admin123!)</a></li>";
    echo "<li><a href='customer/register.php'>Register as a customer</a></li>";
    echo "</ul>";

    echo "<p><strong>Security Note:</strong> Delete this install.php file after installation.</p>";

} catch (PDOException $e) {
    echo "<div style='color: red;'>";
    echo "❌ Installation failed: " . $e->getMessage();
    echo "<br>Please check your XAMPP MySQL configuration.";
    echo "</div>";
}
?>