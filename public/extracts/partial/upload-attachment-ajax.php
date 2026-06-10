<?php
/**
 * رفع مرفق للمستخلص الجزئي - نسخة مبسطة وموثوقة
 */

// إعدادات أساسية
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_attachments')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة المرفقات'], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب خاطئة'], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من البيانات الأساسية
if (empty($_POST['extract_id']) || empty($_FILES['attachment_file']['name'])) {
    echo json_encode(['success' => false, 'message' => 'بيانات مفقودة - يرجى اختيار ملف'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // تحميل الإعدادات
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();
    
    $extractId = (int) $_POST['extract_id'];
    $userId = $_SESSION['user_id'];
    $description = $_POST['attachment_description'] ?? '';
    
    // معلومات الملف
    $file = $_FILES['attachment_file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // التحقق من أخطاء الرفع
    if ($fileError !== UPLOAD_ERR_OK) {
        throw new Exception('خطأ في رفع الملف: ' . $fileError);
    }
    
    // التحقق من حجم الملف (10 ميجابايت)
    if ($fileSize > 10 * 1024 * 1024) {
        throw new Exception('حجم الملف كبير جداً. الحد الأقصى 10 ميجابايت');
    }
    
    // التحقق من نوع الملف
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('نوع الملف غير مدعوم. الأنواع المدعومة: ' . implode(', ', $allowedExtensions));
    }
    
    // إنشاء مجلد الحفظ
    $uploadDir = __DIR__ . '/../../uploads/extracts/partial/' . $extractId . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('فشل في إنشاء مجلد الحفظ');
        }
    }
    
    // إنشاء اسم ملف فريد
    $uniqueFileName = 'attachment_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $uniqueFileName;
    
    // نقل الملف
    if (!move_uploaded_file($fileTmpName, $filePath)) {
        throw new Exception('فشل في حفظ الملف');
    }
    
    // حفظ في قاعدة البيانات
    $dbFilePath = 'public/uploads/extracts/partial/' . $extractId . '/' . $uniqueFileName;
    
    $insertQuery = "
        INSERT INTO partial_extract_attachments 
        (partial_extract_id, file_name, original_name, file_path, file_size, file_type, uploaded_by, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $db->prepare($insertQuery);
    $result = $stmt->execute([
        $extractId,
        $uniqueFileName,
        $fileName,
        $dbFilePath,
        $fileSize,
        $fileExtension,
        $userId
    ]);
    
    if (!$result) {
        // حذف الملف إذا فشل حفظ قاعدة البيانات
        unlink($filePath);
        throw new Exception('فشل في حفظ بيانات الملف');
    }
    
    $attachmentId = $db->lastInsertId();
    
    // استجابة النجاح
    echo json_encode([
        'success' => true,
        'message' => 'تم رفع المرفق بنجاح',
        'attachment_id' => $attachmentId,
        'file_name' => $fileName,
        'file_size' => formatFileSize($fileSize)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // حذف الملف إذا كان موجوداً
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
