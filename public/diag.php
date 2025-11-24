<?php
// diag.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Diagnostics</h2>";

// Load config - app folder is inside htdocs
$config_path = __DIR__ . '/app/config.php';

echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Config Path:</strong> " . htmlspecialchars($config_path) . "</p>";

if (!file_exists($config_path)) {
    die("<p style='color:red;'>❌ Config file not found! Make sure config.php exists at: " . htmlspecialchars($config_path) . "</p>");
}

echo "<p style='color:green;'>✅ Config file found!</p>";

$config = require $config_path;

echo "<p>✅ Config file loaded successfully</p>";
echo "<h3>Configuration Values:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> " . htmlspecialchars($config['db_host']) . "</li>";
echo "<li><strong>User:</strong> " . htmlspecialchars($config['db_user']) . "</li>";
echo "<li><strong>Database:</strong> " . htmlspecialchars($config['db_name']) . "</li>";
echo "<li><strong>Password:</strong> " . (empty($config['db_pass']) ? "❌ EMPTY" : "✅ Set (length: " . strlen($config['db_pass']) . ")") . "</li>";
echo "</ul>";

echo "<h3>Testing Connection...</h3>";

// Attempt connection
$connection = @new mysqli(
    $config['db_host'],
    $config['db_user'],
    $config['db_pass'],
    $config['db_name']
);

if ($connection->connect_error) {
    echo "<p style='color:red;'>❌ <strong>Connection Failed!</strong></p>";
    echo "<p><strong>Error Code:</strong> " . $connection->connect_errno . "</p>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($connection->connect_error) . "</p>";
    
    echo "<h3>Common InfinityFree Issues:</h3>";
    echo "<ul>";
    echo "<li><strong>Wrong hostname:</strong> InfinityFree uses specific SQL hostnames (e.g., sqlXXX.byetcluster.com or sql.infinityfree.com). Check your cPanel MySQL Remote section.</li>";
    echo "<li><strong>Remote connections:</strong> InfinityFree only allows connections from their own servers (localhost won't work from external).</li>";
    echo "<li><strong>Database name format:</strong> Should be like 'if0_XXXXX_YourDBName'</li>";
    echo "<li><strong>Password:</strong> Make sure you're using the MySQL password, not your cPanel password.</li>";
    echo "<li><strong>User format:</strong> Should be 'if0_XXXXX' format</li>";
    echo "</ul>";
    
    echo "<h3>Troubleshooting Steps:</h3>";
    echo "<ol>";
    echo "<li>Log into InfinityFree cPanel</li>";
    echo "<li>Go to MySQL Databases</li>";
    echo "<li>Verify your database name, username, and password</li>";
    echo "<li>Check the MySQL hostname in 'Remote MySQL' section</li>";
    echo "<li>Ensure the database user is added to the database</li>";
    echo "</ol>";
    
} else {
    echo "<p style='color:green;'>✅ <strong>Connection Successful!</strong></p>";
    echo "<p><strong>Server Info:</strong> " . htmlspecialchars($connection->server_info) . "</p>";
    echo "<p><strong>Host Info:</strong> " . htmlspecialchars($connection->host_info) . "</p>";
    echo "<p><strong>Character Set:</strong> " . htmlspecialchars($connection->character_set_name()) . "</p>";
    
    // Test query
    $result = $connection->query("SHOW TABLES");
    if ($result) {
        echo "<h3>Database Tables:</h3>";
        echo "<ul>";
        while ($row = $result->fetch_array()) {
            echo "<li>" . htmlspecialchars($row[0]) . "</li>";
        }
        echo "</ul>";
        $result->close();
    } else {
        echo "<p style='color:orange;'>⚠️ Could not list tables: " . htmlspecialchars($connection->error) . "</p>";
    }
    
    $connection->close();
}

echo "<hr>";
echo "<h3>PHP/Server Information:</h3>";
echo "<ul>";
echo "<li><strong>PHP Version:</strong> " . phpversion() . "</li>";
echo "<li><strong>MySQLi Extension:</strong> " . (extension_loaded('mysqli') ? "✅ Loaded" : "❌ Not loaded") . "</li>";
echo "<li><strong>Server Software:</strong> " . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>";
echo "</ul>";
?>