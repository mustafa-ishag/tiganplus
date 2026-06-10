<?php
/**
 * تحميل مرفق المستخلص الجزئي
 * Download Partial Extract Attachment
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

// التحقق من وجود معرف المرفق
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$attachmentId = (int) $_GET['id'];
$isView = isset($_GET['view']) && $_GET['view'] == '1';

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // التحقق من الصلاحيات
    if (!hasPermission('extracts_view_details')) {
        header('Location: index.php');
        exit();
    }

    $db = getDB();
    
    // جلب بيانات المرفق
    $query = "
        SELECT pea.*, pe.extract_number
        FROM partial_extract_attachments pea
        JOIN partial_extracts pe ON pea.partial_extract_id = pe.id
        WHERE pea.id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch();
    
    if (!$attachment) {
        echo "<div class='alert alert-danger'>المرفق غير موجود</div>";
        echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
        exit();
    }
    
    // بناء مسار الملف
    $filePath = __DIR__ . '/../../../' . $attachment['file_path'];
    
    // التحقق من وجود الملف
    if (!file_exists($filePath)) {
        echo "<div class='alert alert-danger'>الملف غير موجود على الخادم</div>";
        echo "<a href='view.php?id=" . $attachment['partial_extract_id'] . "' class='btn btn-primary'>العودة للمستخلص</a>";
        exit();
    }
    
    // تحديد نوع المحتوى
    $mimeType = mime_content_type($filePath);
    $fileName = $attachment['original_name'];
    
    // إعداد الرؤوس للتحميل أو العرض
    if ($isView && in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'])) {
        // عرض الملف في المتصفح
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $fileName . '"');
    } else {
        // تحميل الملف
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    }
    
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // إرسال الملف
    readfile($filePath);
    
    // تسجيل عملية التحميل (اختياري)
    try {
        $activityQuery = "
            INSERT INTO partial_extract_activities (
                partial_extract_id, activity_type, activity_description, 
                performed_by, performed_at
            ) VALUES (?, 'attachment_downloaded', ?, ?, NOW())
        ";
        
        $stmt = $db->prepare($activityQuery);
        $stmt->execute([
            $attachment['partial_extract_id'],
            ($isView ? 'تم عرض المرفق: ' : 'تم تحميل المرفق: ') . $attachment['original_name'],
            $_SESSION['user_id']
        ]);
    } catch (Exception $e) {
        // تجاهل خطأ سجل الأنشطة
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>خطأ في تحميل الملف: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
}
?>
