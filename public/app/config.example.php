<?php
// Copy this file to config.php and fill in real credentials.
date_default_timezone_set('America/Chicago');
return [
    'db_host' => 'localhost', // since website and database are on same server (hostinger)
    'db_user' => 'u452501794_DBuser',
    'db_pass' => '',
    'db_name' => 'u452501794_MuseumDB',
    'app_env' => 'development', // production for live site, development for testing
    'force_https' => true,
];