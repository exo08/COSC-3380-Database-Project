<?php
function db(): mysqli {
    static $connection = null;
    if ($connection) return $connection;

    // Load config file - app folder is in htdocs
    $config = require __DIR__ . '/config.php';

    // Create mysqli connection
    $connection = @new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );

    // Check for connection errors
    if ($connection->connect_error) {
        if (($config['app_env'] ?? 'development') === 'development') {
            die('Database connection failed: ' . $connection->connect_error);
        }
        http_response_code(500);
        die('Database connection error.');
    }
    
    $connection->set_charset('utf8mb4');
    return $connection;
}