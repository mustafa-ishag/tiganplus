<?php
/**
 * db.php
 * ======
 * اتصال مباشر بقاعدة البيانات المحلية باستخدام PDO
 */

$host = 'srv1416.hstgr.io';
$db = 'u248904668_scrap';
$user = 'u248904668_scrap';
$pass = '@Mustafasaad20513'; // افتراضي XAMPP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}
