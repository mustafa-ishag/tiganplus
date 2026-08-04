<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

try {
    $db = getDB();
    $tables = ['productivity_work_items', 'productivity_daily_logs', 'productivity_approvals', 'productivity_approvers'];
    
    foreach ($tables as $table) {
        echo "Table: $table\n";
        $stmt = $db->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row['Create Table'] . "\n\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
