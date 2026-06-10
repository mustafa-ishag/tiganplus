<?php
/**
 * حذف مرفق المستخلص الجزئي
 * Delete Partial Extract Attachment
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit();
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_attachments')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة المرفقات']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();
    
    // التحقق من البيانات المطلوبة
    if (!isset($_POST['attachment_id']) || !is_numeric($_POST['attachment_id'])) {
        throw new InvalidArgumentException('معرف المرفق مطلوب');
    }
    
    $attachmentId = (int) $_POST['attachment_id'];
    $userId = $_SESSION['user_id'];
    
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
        throw new InvalidArgumentException('المرفق غير موجود');
    }
    
    // التحقق من الصلاحيات (يمكن إضافة منطق أكثر تعقيداً هنا)
    // مثلاً: فقط من رفع المرفق أو المدير يمكنه حذفه
    
    // بناء مسار الملف
    $filePath = __DIR__ . '/../../../' . $attachment['file_path'];
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // حذف المرفق من قاعدة البيانات
        $deleteQuery = "DELETE FROM partial_extract_attachments WHERE id = ?";
        $stmt = $db->prepare($deleteQuery);
        $stmt->execute([$attachmentId]);
        
        // حذف الملف من الخادم
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // إضافة سجل نشاط
        try {
            $activityQuery = "
                INSERT INTO partial_extract_activities (
                    partial_extract_id, activity_type, activity_description, 
                    performed_by, performed_at
                ) VALUES (?, 'attachment_deleted', ?, ?, NOW())
            ";
            
            $stmt = $db->prepare($activityQuery);
            $stmt->execute([
                $attachment['partial_extract_id'],
                'تم حذف المرفق: ' . $attachment['original_name'],
                $userId
            ]);
        } catch (Exception $e) {
            // تجاهل خطأ سجل الأنشطة
        }
        
        // تأكيد المعاملة
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف المرفق بنجاح'
        ]);
        
    } catch (Exception $e) {
        // التراجع عن المعاملة
        $db->rollBack();
        throw $e;
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?>
