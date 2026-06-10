<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('غير مصرح');
}

// التحقق من معرف المرفق
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('معرف المرفق غير صحيح');
}

$attachmentId = (int) $_GET['id'];
$db = getDB();

try {
    // جلب بيانات المرفق
    $stmt = $db->prepare("
        SELECT woa.*, wo.work_order_number
        FROM work_order_attachments woa
        JOIN work_orders wo ON woa.work_order_id = wo.id
        WHERE woa.id = ?
    ");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(404);
        die('المرفق غير موجود');
    }

    // التحقق من وجود الملف
    if (empty($attachment['file_path'])) {
        http_response_code(404);
        die('مسار الملف غير محدد في قاعدة البيانات');
    }

    // تحديد المسار الكامل للملف
    // المسار المحفوظ في قاعدة البيانات نسبي من مجلد public
    $filePath = __DIR__ . '/../' . $attachment['file_path'];

    // التحقق من وجود الملف
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('الملف غير موجود على الخادم. المسار المحفوظ: ' . htmlspecialchars($attachment['file_path']) . ' - المسار الكامل: ' . htmlspecialchars($filePath));
    }

    // تحديد نوع المحتوى
    $fileExtension = strtolower(pathinfo($attachment['original_filename'], PATHINFO_EXTENSION));
    $contentType = 'application/octet-stream';

    $mimeTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed'
    ];

    if (isset($mimeTypes[$fileExtension])) {
        $contentType = $mimeTypes[$fileExtension];
    }

    // مسح أي مخرجات سابقة
    if (ob_get_level()) {
        ob_end_clean();
    }

    // إرسال الهيدرز
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $attachment['original_filename'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');

    // قراءة وإرسال الملف
    readfile($filePath);
    exit;

} catch (Exception $e) {
    error_log("Download Attachment Error: " . $e->getMessage());
    http_response_code(500);
    die('حدث خطأ أثناء تحميل الملف: ' . $e->getMessage());
}
?>

