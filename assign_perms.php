<?php
require 'config/config.php';
require 'includes/functions.php';
$db=getDB();
$perms = $db->query("SELECT id FROM permissions WHERE name LIKE 'inventory_loans%' OR name LIKE 'inventory_clients%'")->fetchAll(PDO::FETCH_COLUMN);
foreach($perms as $p) {
    try {
        $db->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, $p)");
        $db->query("INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES (1, $p)");
    } catch (Exception $e) {}
}
echo 'Done';
