<?php

declare(strict_types=1);

use TiqanERP\Infrastructure\Database\DatabaseConnection;

// تحميل Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// تحميل متغيرات البيئة
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

// تحميل إعدادات التطبيق
$appConfig = require __DIR__ . '/../config/app.php';
$databaseConfig = require __DIR__ . '/../config/database.php';

// تعيين المنطقة الزمنية
date_default_timezone_set($appConfig['timezone']);

// تعيين الترميز
mb_internal_encoding($appConfig['charset']);

// تكوين قاعدة البيانات
DatabaseConnection::setConfig($databaseConfig);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $appConfig['session']['lifetime'] * 60,
        'path' => $appConfig['session']['path'],
        'domain' => $appConfig['session']['domain'],
        'secure' => $appConfig['session']['secure'],
        'httponly' => $appConfig['session']['httponly'],
        'samesite' => $appConfig['session']['samesite']
    ]);
    
    session_name($appConfig['session']['name']);
    session_start();
}

// إعداد معالجة الأخطاء
if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// إرجاع إعدادات التطبيق
return $appConfig;
