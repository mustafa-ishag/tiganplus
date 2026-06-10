<?php
/**
 * صفحة تسجيل الخروج المباشرة
 * Direct Logout Page
 */

// منع عرض الأخطاء
error_reporting(0);
ini_set('display_errors', 0);

// بدء output buffering
ob_start();

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// تضمين ملفات التكوين مع معالجة الأخطاء
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    
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
} catch (Exception $e) {
    // في حالة فشل تحميل الملفات، قم بتسجيل الخروج الأساسي
    error_log('Logout config error: ' . $e->getMessage());
}

// مسح جميع متغيرات الجلسة
$_SESSION = array();

// الحصول على domain للكوكيز
$domain = '';
if (isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    // إزالة البورت إذا كان موجوداً
    $domain = preg_replace('/:\d+$/', '', $host);
}

// مسح كوكي الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
@session_destroy();

// مسح كوكيز النظام
$cookies_to_clear = ['remember_token', 'etgan_session', session_name()];
foreach ($cookies_to_clear as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        setcookie($cookie, '', time() - 3600, '/');
        setcookie($cookie, '', time() - 3600, '/', $domain);
        setcookie($cookie, '', time() - 3600, '/', '');
        setcookie($cookie, '', time() - 3600);
    }
}

// تنظيف output buffer
ob_end_clean();

// إعادة التوجيه لصفحة تسجيل الدخول
header('Location: auth/login.php?message=' . urlencode('تم تسجيل الخروج بنجاح'));
exit();
?>
