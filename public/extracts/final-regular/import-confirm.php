<?php
/**
 * صفحة تأكيد استيراد المستخلصات النهائية العادية
 * Final Regular Extracts Import Confirmation Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

// التحقق من وجود بيانات الاستيراد
if (!isset($_SESSION['import_preview_data'])) {
    header('Location: import.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_import')) {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/../../../includes/FinalRegularExtractImporter.php';

$pageTitle = 'تأكيد استيراد المستخلصات النهائية العادية';
$user_id = $_SESSION['user_id'];

try {
    $db = getDB();
    $importer = new FinalRegularExtractImporter($db, $user_id);
    
    // تأكيد الاستيراد
    $result = $importer->confirmImport($_SESSION['import_preview_data']);
    
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        $_SESSION['import_stats'] = $result['stats'];
        
        // حذف الملف المؤقت
        if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
            unlink($_SESSION['import_file_path']);
        }
        
        // تنظيف بيانات الجلسة
        unset($_SESSION['import_file_path']);
        unset($_SESSION['import_file_name']);
        unset($_SESSION['import_preview_data']);
        
        header('Location: index.php');
        exit();
    } else {
        $_SESSION['error_message'] = $result['message'] ?? 'فشل الاستيراد';
        header('Location: import.php');
        exit();
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'خطأ في الاستيراد: ' . $e->getMessage();
    header('Location: import.php');
    exit();
}

