<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'kmtc_photo_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// File paths - use correct relative paths
$baseDir = dirname(__FILE__) . '/../';
define('BASE_PATH', realpath($baseDir));
define('UPLOAD_PENDING_PATH', BASE_PATH . '/uploads/pending/');
define('UPLOAD_ASSIGNED_PATH', BASE_PATH . '/uploads/assigned/');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Set headers for JSON responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}
?>