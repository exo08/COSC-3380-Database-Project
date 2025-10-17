<?php
// diag.php — delete after use
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostics</h2>";

// 1) Sanity: where are we and what files exist?
echo "<p><b>cwd:</b> " . getcwd() . "</p>";
$need = ['app/config.php','app/db.php','templates/header.php','index.php'];
echo "<ul>";
foreach ($need as $f) {
  echo "<li>$f : " . (file_exists(__DIR__."/$f") ? "✅ found" : "❌ missing") . "</li>";
}
echo "</ul>";

// 2) Load config
$cfgPath = __DIR__ . '/app/config.php';
if (!file_exists($cfgPath)) { die("<p>❌ config.php not found.</p>"); }
$cfg = require $cfgPath;
echo "<p><b>app_env:</b> " . htmlspecialchars($cfg['app_env'] ?? 'n/a') . "</p>";
echo "<p><b>db_host:</b> " . htmlspecialchars($cfg['db_host'] ?? 'n/a') . "</p>";
echo "<p><b>db_name:</b> " . htmlspecialchars($cfg['db_name'] ?? 'n/a') . "</p>";

// 3) Try DB connect
echo "<h3>DB connection</h3>";
$mysqli = @new mysqli($cfg['db_host'], $cfg['db_user'], $cfg['db_pass'], $cfg['db_name']);
if ($mysqli->connect_error) {
  die("<p>❌ Connect failed: (" . $mysqli->connect_errno . ") " . htmlspecialchars($mysqli->connect_error) . "</p>");
}
echo "<p>✅ Connected</p>";

// 4) List tables
$res = $mysqli->query("SHOW TABLES");
echo "<p><b>Tables:</b></p><ul>";
while ($row = $res->fetch_array()) { echo "<li>".htmlspecialchars($row[0])."</li>"; }
echo "</ul>";

echo "<p>PHP " . PHP_VERSION . "</p>";