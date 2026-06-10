<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
$db = getDB();
$stmt = $db->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
