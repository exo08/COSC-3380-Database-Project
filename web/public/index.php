<?php
require __DIR__ . '/../app/db.php';
$connection = db();

// get all table names to confirm connection works
$tables = [];
if($res = $connection->query("SHOW TABLES")) {
    while($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }
    $res->free();
} else {
    die("Error fetching tables: " . $connection->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Connection Test</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../templates/header.php'; ?>
    <h1>Database Connection Test</h1>
    <p><strong>Status:</strong> Connected to the database successfully</p>
    <p><strong>Tables found:</strong> <?= count($tables) ?></p>
    <ul>
        <?php foreach($tables as $t): ?>
            <li><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>

    <?php include __DIR__ . '/../templates/footer.php'; ?>
</body>
</html>