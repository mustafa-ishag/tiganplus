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

    // قراءة البيانات
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['attachment_id'])) {
        throw new InvalidArgumentException('بيانات غير صحيحة');
    }

    $attachmentId = (int) $input['attachment_id'];

    // جلب المرفق
    $stmt = $db->prepare("SELECT * FROM work_order_attachments WHERE id = ?");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        throw new InvalidArgumentException('المرفق غير موجود');
    }

    // التحقق من وجود ملف
    if (empty($attachment['file_path'])) {
        throw new InvalidArgumentException('لا يوجد ملف مرفق');
    }

    // حذف الملف من النظام
    $filePath = __DIR__ . '/../' . $attachment['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // التحقق من نوع النموذج
    // إذا كان "مستندات أخرى"، احذف الصف بالكامل
    // إذا كان نموذج أساسي، فقط امسح بيانات الملف
    if ($attachment['form_type'] === 'other_document') {
        // حذف الصف بالكامل للمستندات الأخرى
        $deleteStmt = $db->prepare("DELETE FROM work_order_attachments WHERE id = ?");
        $deleteStmt->execute([$attachmentId]);

        echo json_encode([
            'success' => true,
            'message' => 'تم حذف المستند بنجاح'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // تحديث المرفق في قاعدة البيانات (للنماذج الأساسية)
        $updateStmt = $db->prepare("
            UPDATE work_order_attachments SET
                file_path = NULL,
                original_filename = NULL,
                file_size = NULL,
                file_type = NULL,
                uploaded_by = NULL,
                uploaded_at = NULL,
                updated_at = NOW(),
                status = 'not_attached'
            WHERE id = ?
        ");

        $updateStmt->execute([$attachmentId]);

        echo json_encode([
            'success' => true,
            'message' => 'تم حذف الملف بنجاح'
        ], JSON_UNESCAPED_UNICODE);
    }

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
