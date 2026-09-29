<?php
// Load .env variables if file exists
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$db_name  = getenv('DB_NAME') ?: 'kugu2744_mykelasi';
$username = getenv('DB_USER') ?: 'kugu2744_mykelasi';
$password = getenv('DB_PASS');
$app_env  = getenv('APP_ENV') ?: 'production';

// Error reporting based on environment
if ($app_env === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    if (!defined('DEBUG')) define('DEBUG', true);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    if (!defined('DEBUG')) define('DEBUG', false);
}

// Security Headers
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:;");
}

// Set PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Turn on errors in the form of exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Make the default fetch be an associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Turn off emulation mode for prepared statements
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, $options);
    // echo "Database connection successful!"; // Optional: for testing connection
} catch (PDOException $e) {
    // Log the error or display a user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    // For a production environment, you might want to display a generic error message
    // and log the detailed error.
    die("Database connection failed. Please try again later or contact support. Details: " . $e->getMessage());
}

?>