<?php
require 'config/config.php';
require 'includes/functions.php';
$db = getDB();
$stmt = $db->query('SELECT COUNT(*) FROM user_permissions');
echo "user_permissions count: " . $stmt->fetchColumn() . "\n";
$stmt = $db->query('SELECT COUNT(*) FROM role_permissions');
echo "role_permissions count: " . $stmt->fetchColumn() . "\n";
