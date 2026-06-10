<?php
session_start();

// فحص تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    die('غير مسموح');
}

// فحص الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('طريقة خاطئة');
}

// فحص البيانات
if (empty($_POST['extract_id']) || !isset($_FILES['file'])) {
    die('بيانات مفقودة');
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // التحقق من الصلاحيات
    if (!hasPermission('extracts_attachments')) {
        die('ليس لديك صلاحية لرفع المرفقات');
    }

    // الاتصال بقاعدة البيانات
    $pdo = getDB();
    
    $extractId = (int) $_POST['extract_id'];
    $userId = $_SESSION['user_id'];
    $file = $_FILES['file'];
    
    // فحص الملف
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die('خطأ في رفع الملف');
    }
    
    if ($file['size'] > 10 * 1024 * 1024) {
        die('الملف كبير جداً');
    }
    
    // إنشاء مجلد
    $uploadDir = __DIR__ . '/../../uploads/extracts/partial/' . $extractId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // نقل الملف
    $fileName = time() . '_' . $file['name'];
    $filePath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        die('فشل في حفظ الملف');
    }
    
    // حفظ في قاعدة البيانات
    $stmt = $pdo->prepare("
        INSERT INTO partial_extract_attachments 
        (partial_extract_id, file_name, original_name, file_path, file_size, file_type, uploaded_by, uploaded_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $dbPath = 'public/uploads/extracts/partial/' . $extractId . '/' . $fileName;
    
    $stmt->execute([
        $extractId,
        $fileName,
        $file['name'],
        $dbPath,
        $file['size'],
        $fileExt,
        $userId
    ]);
    
    echo 'تم رفع الملف بنجاح';
    
} catch (Exception $e) {
    echo 'خطأ: ' . $e->getMessage();
}
?>
