<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

try {
    // التحقق من وجود معرف المرفق
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new InvalidArgumentException('معرف المرفق غير صحيح');
    }

    $db = getDB();
    $attachmentId = (int) $_GET['id'];

    // جلب المرفق
    $stmt = $db->prepare("SELECT * FROM work_order_attachments WHERE id = ?");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        throw new InvalidArgumentException('المرفق غير موجود');
    }

    // التحقق من وجود ملف
    if (empty($attachment['file_path']) || empty($attachment['original_filename'])) {
        throw new InvalidArgumentException('لا يوجد ملف مرفق');
    }

    $filePath = __DIR__ . '/../../' . $attachment['file_path'];

    // التحقق من وجود الملف
    if (!file_exists($filePath)) {
        throw new InvalidArgumentException('الملف غير موجود على الخادم');
    }

    // تحديد نوع المحتوى
    $fileType = $attachment['file_type'];
    $fileName = $attachment['original_filename'];

    // إرسال headers للتحميل
    header('Content-Type: ' . $fileType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // قراءة وإرسال الملف
    readfile($filePath);
    exit;

} catch (Exception $e) {
    // في حالة الخطأ، إرسال رسالة خطأ
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="utf-8">
        <title>خطأ في التحميل</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .error { color: #dc3545; font-size: 18px; }
        </style>
    </head>
    <body>
        <h1>خطأ في التحميل</h1>
        <p class="error">' . htmlspecialchars($e->getMessage()) . '</p>
        <button onclick="window.close()">إغلاق النافذة</button>
    </body>
    </html>';
}
?>
