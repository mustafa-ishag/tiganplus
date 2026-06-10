<?php
/**
 * ملف التكوين للاستضافة - نظام تِقان
 * Production Configuration File for Tiqan ERP System
 */

// منع الوصول المباشر
if (!defined('TIQAN_SYSTEM')) {
    define('TIQAN_SYSTEM', true);
}

// إعدادات قاعدة البيانات - يجب تحديثها حسب بيانات الاستضافة
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');  // ← غير هذا
define('DB_USER', 'your_database_user');  // ← غير هذا
define('DB_PASS', 'your_database_password');  // ← غير هذا
define('DB_CHARSET', 'utf8mb4');

// إعدادات النظام
define('SITE_NAME', 'نظام تِقان لإدارة أعمال المقاولات');
define('SITE_URL', 'https://toot.tiqantik.com');  // ← بدون /public/
define('ADMIN_EMAIL', 'admin@tiqantik.com');
define('SYSTEM_VERSION', '1.0.0');

// إعدادات الأمان
define('SESSION_TIMEOUT', 3600); // ساعة واحدة
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 دقيقة
define('PASSWORD_MIN_LENGTH', 8);

// إعدادات الملفات
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10485760); // 10 MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// إعدادات التاريخ والوقت
date_default_timezone_set('Asia/Riyadh');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// إعدادات اللغة
define('DEFAULT_LANGUAGE', 'ar');
define('RTL_SUPPORT', true);

// إعدادات الضرائب
define('VAT_RATE', 0.15); // 15%

// إعدادات الصفحات
define('RECORDS_PER_PAGE', 25);

// إعدادات البريد الإلكتروني
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');

// إعدادات الإنتاج - إخفاء الأخطاء
define('DEBUG_MODE', false);
define('SHOW_ERRORS', false);

// إخفاء الأخطاء في بيئة الإنتاج
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// إعدادات الجلسة (فقط إذا لم تبدأ الجلسة بعد)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1); // HTTPS فقط
}

// دالة للحصول على URL الأساسي
function getBaseUrl() {
    $protocol = 'https'; // دائماً HTTPS في الإنتاج
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

// دالة للحصول على مسار الملف
function getFilePath($relativePath) {
    return __DIR__ . '/../' . $relativePath;
}

// دالة للحصول على URL الملف
function getFileUrl($relativePath) {
    return getBaseUrl() . '/' . $relativePath;
}

// تحميل مساعد المسارات
if (file_exists(__DIR__ . '/../includes/path-helper.php')) {
    require_once __DIR__ . '/../includes/path-helper.php';
}
?>

