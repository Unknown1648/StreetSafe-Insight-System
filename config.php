<?php
/**
 * Database Configuration & Connection
 * StreetSafe Insight System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'streetsafe_app');
define('DB_PASS', 'StreetSafeLocal123');
define('DB_NAME', 'streetsafe_insight');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set charset to UTF-8
$conn->set_charset('utf8');

function streetsafeEnsureSchema($conn) {
    $requiredTables = ['accounts', 'streets', 'incidents', 'notifications', 'report_audit_logs'];
    $missingTables = [];

    foreach ($requiredTables as $tableName) {
        $tableCheck = $conn->query("SHOW TABLES LIKE '{$tableName}'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $missingTables[] = $tableName;
        }
    }

    if (empty($missingTables)) {
        return;
    }

    $schemaFile = __DIR__ . '/db_schema.sql';
    if (!file_exists($schemaFile)) {
        die(json_encode(['error' => 'Schema file not found: ' . $schemaFile]));
    }

    $sql = file_get_contents($schemaFile);
    if (!$conn->multi_query($sql)) {
        die(json_encode(['error' => 'Schema bootstrap failed: ' . $conn->error]));
    }

    while ($conn->more_results()) {
        $conn->next_result();
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

streetsafeEnsureSchema($conn);

// Headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
