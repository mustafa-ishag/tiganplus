<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين ترميز UTF-8
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من طريقة الطلب
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new InvalidArgumentException('طريقة طلب غير صحيحة');
    }

    $db = getDB();

    // التحقق من البيانات
    if (!isset($_FILES['file'])) {
        throw new InvalidArgumentException('لم يتم اختيار ملف');
    }

    $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : null;
    $formType = trim($_POST['form_type'] ?? '');
    $workOrderId = (int) ($_POST['work_order_id'] ?? 0);
    $uploadedFile = $_FILES['file'];

    // التحقق من البيانات الأساسية
    if (empty($formType)) {
        throw new InvalidArgumentException('نوع النموذج مطلوب');
    }

    if ($workOrderId <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    // التحقق من وجود خطأ في الرفع
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('حدث خطأ أثناء رفع الملف');
    }

    // التحقق من حجم الملف (الحد الأقصى 10 ميجابايت)
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    if ($uploadedFile['size'] > $maxFileSize) {
        throw new InvalidArgumentException('حجم الملف كبير جداً. الحد الأقصى 10 ميجابايت');
    }

    // التحقق من نوع الملف
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif'
    ];

    $fileType = mime_content_type($uploadedFile['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        throw new InvalidArgumentException('نوع الملف غير مدعوم. الأنواع المدعومة: PDF, DOC, DOCX, JPG, PNG, GIF');
    }

    // التحقق من وجود المرفق أو إنشاؤه
    $attachment = null;
    if ($attachmentId && $attachmentId > 0) {
        $stmt = $db->prepare("SELECT * FROM work_order_attachments WHERE id = ? AND work_order_id = ? AND form_type = ?");
        $stmt->execute([$attachmentId, $workOrderId, $formType]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // إنشاء مجلد التحميل إذا لم يكن موجوداً
    $uploadDir = __DIR__ . '/../../public/uploads/work-orders/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // إنشاء اسم ملف فريد
    $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $fileName = 'attachment_' . $workOrderId . '_' . $formType . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;

    // نقل الملف
    if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
        throw new InvalidArgumentException('فشل في حفظ الملف');
    }

    if ($attachment) {
        // تحديث المرفق الموجود
        $updateStmt = $db->prepare("
            UPDATE work_order_attachments SET
                file_path = ?,
                original_filename = ?,
                file_size = ?,
                file_type = ?,
                uploaded_by = ?,
                uploaded_at = NOW(),
                updated_at = NOW(),
                status = 'attached'
            WHERE id = ?
        ");

        $updateStmt->execute([
            'uploads/work-orders/' . $fileName,
            $uploadedFile['name'],
            $uploadedFile['size'],
            $fileType,
            $_SESSION['user_id'],
            $attachmentId
        ]);
    } else {
        // إنشاء مرفق جديد
        $insertStmt = $db->prepare("
            INSERT INTO work_order_attachments (
                work_order_id, form_type, file_path, original_filename,
                file_size, file_type, uploaded_by, uploaded_at,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'attached', NOW())
        ");

        $insertStmt->execute([
            $workOrderId,
            $formType,
            'uploads/work-orders/' . $fileName,
            $uploadedFile['name'],
            $uploadedFile['size'],
            $fileType,
            $_SESSION['user_id']
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'تم رفع الملف بنجاح',
        'file_name' => $uploadedFile['name'],
        'file_size' => $uploadedFile['size']
    ], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
