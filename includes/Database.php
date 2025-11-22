<?php
/**
 * Database Connection Class
 * Handles PDO database connection with error handling
 */
class Database {
    /** @var PDO|null */
    private $pdo;
    /** @var string|null */
    private $error;

    public function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Test connection with a simple query
            $this->pdo->query("SELECT 1");
            
        } catch (PDOException $e) {
            // Log detailed error for admin
            error_log("Database connection failed: " . $e->getMessage());
            
            // Show user-friendly message
            $this->error = "Database connection failed. Please try again later.";
            
            // Check if we're in a web context
            if (php_sapi_name() !== 'cli' && !headers_sent()) {
                http_response_code(503);
                header('Content-Type: application/json');
                
                // If it's an AJAX request, return JSON
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Service temporarily unavailable. Please try again later.'
                    ]);
                } else {
                    // For regular page requests, show HTML error page
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Service Unavailable</title>
                        <style>
                            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                            .error-container { max-width: 500px; margin: 0 auto; }
                        </style>
                    </head>
                    <body>
                        <div class="error-container">
                            <h1>Service Temporarily Unavailable</h1>
                            <p>We\'re experiencing technical difficulties. Please try again in a few minutes.</p>
                            <p><small>If the problem persists, please contact support.</small></p>
                        </div>
                    </body>
                    </html>';
                }
                exit;
            }
            
            throw new Exception($this->error);
        }
    }

    /**
     * Get the PDO connection
     * @return PDO
     * @throws Exception if connection not available
     */
    public function getConnection(): PDO {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        throw new Exception('Database connection is not available.');
    }
}
?>