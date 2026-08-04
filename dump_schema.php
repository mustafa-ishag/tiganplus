<?php
$config = require __DIR__ . '/config/database.php';
$dbConfig = $config['connections']['mysql'];
$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);

$tables = ['work_orders', 'contract_work_items', 'productivity_work_items', 'productivity_daily_logs'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    $stmt = $db->query("SHOW CREATE TABLE $t");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n\n";
}
