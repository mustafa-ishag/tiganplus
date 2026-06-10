<?php
/**
 * صفحة تسجيل الخروج
 * Logout Page
 */

// تفعيل output buffering لتجنب مشاكل headers
ob_start();

// إعدادات الأخطاء للاستضافة
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// بدء الجلسة بأمان
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين ملفات التكوين مع معالجة الأخطاء
try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
} catch (Exception $e) {
    // في حالة فشل تحميل الملفات، قم بتسجيل الخروج الأساسي
    error_log('Logout config error: ' . $e->getMessage());
    session_unset();
    session_destroy();
    ob_end_clean();
    header('Location: login.php?message=' . urlencode('تم تسجيل الخروج'));
    exit();
}

// مسح remember token من قاعدة البيانات إذا كان موجوداً
if (isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // تجاهل الأخطاء في تسجيل الخروج
        error_log('Logout database error: ' . $e->getMessage());
    }
}

// الحصول على domain للكوكيز
$domain = '';
if (isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    // إزالة البورت إذا كان موجوداً
    $domain = preg_replace('/:\d+$/', '', $host);
}

// مسح الجلسة
session_unset();
session_destroy();

// مسح remember token cookie مع إعدادات متوافقة مع الاستضافة
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', $domain, false, true);
    // محاولة إضافية لمسح الكوكي
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600);
}

// مسح session cookie مع إعدادات متوافقة مع الاستضافة
$sessionName = session_name();
if (isset($_COOKIE[$sessionName])) {
    setcookie($sessionName, '', time() - 3600, '/', $domain);
    setcookie($sessionName, '', time() - 3600, '/');
    setcookie($sessionName, '', time() - 3600);
}

// مسح أي كوكيز أخرى متعلقة بالنظام
if (isset($_COOKIE['etgan_session'])) {
    setcookie('etgan_session', '', time() - 3600, '/', $domain);
    setcookie('etgan_session', '', time() - 3600, '/');
    setcookie('etgan_session', '', time() - 3600);
}

// تنظيف output buffer
ob_end_clean();

// إعادة التوجيه لصفحة تسجيل الدخول
header('Location: login.php?message=' . urlencode('تم تسجيل الخروج بنجاح'));
exit();
?>
