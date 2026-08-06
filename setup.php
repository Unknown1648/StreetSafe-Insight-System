<?php
/**
 * Database Initialization Script
 * Run this once to set up the database and tables
 * 
 * Usage: Open in browser at http://localhost/StreetSafe-Insight-System/setup.php
 */

// Database credentials (modify as needed)
$db_host = 'localhost';
$db_user = 'streetsafe_app';
$db_pass = 'StreetSafeLocal123';
$db_name = 'streetsafe_insight';

// Connect directly to the application database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "<h1>StreetSafe Insight System - Database Setup</h1>";

echo "<p style='color: green;'>✅ Connected to database '{$db_name}'.</p>";

// Read and execute schema file
$schema_file = __DIR__ . '/db_schema.sql';
if (!file_exists($schema_file)) {
    die("<p style='color: red;'>❌ Schema file not found: {$schema_file}</p>");
}

$sql = file_get_contents($schema_file);

// Execute schema file
if ($conn->multi_query($sql)) {
    echo "<p style='color: green;'>✅ Database tables created successfully!</p>";
    
    // Consume all results
    while ($conn->next_result()) {
        if ($conn->more_results()) {
            $conn->use_result();
        }
    }
} else {
    echo "<p style='color: orange;'>⚠️ Some tables may already exist (this is OK): " . $conn->error . "</p>";
}

$conn->close();

echo "<hr>";
echo "<h2>Setup Complete!</h2>";
echo "<p>Your database is ready. No users or reports were seeded.</p>";
echo "<ul>";
echo "<li><strong>1. Provision police accounts:</strong> Use <a href='api/init-officers.php'>admin office provisioning</a> if needed</li>";
echo "<li><strong>2. Edit credentials:</strong> Modify <code>config.php</code> with your database credentials if needed</li>";
echo "<li><strong>3. Start using:</strong>";
echo "<ul>";
echo "<li>Community Login: <a href='login.html'>login.html</a></li>";
echo "<li>Community Registration: <a href='register.html'>register.html</a></li>";
echo "<li>Public Dashboard: <a href='index.html'>index.html</a></li>";
echo "</ul>";
echo "</li>";
echo "</ul>";
?>
