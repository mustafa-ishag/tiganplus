<?php
/**
 * تحديث حالة شهادة الإنجاز عبر AJAX
 * Update Completion Certificate Status via AJAX
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// تعيين نوع المحتوى
header('Content-Type: application/json; charset=utf-8');

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة'
    ]);
    exit();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_certificates_status_update')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لتحديث حالة الشهادات'
    ]);
    exit();
}

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات غير صحيحة');
    }
    
    $certificateId = (int)($input['certificate_id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');
    
    // التحقق من صحة البيانات
    if (!$certificateId) {
        throw new Exception('رقم الشهادة مطلوب');
    }
    
    if (!in_array($newStatus, ['in_progress', 'completed'])) {
        throw new Exception('حالة غير صحيحة');
    }
    
    $db = getDB();
    
    // التحقق من وجود الشهادة
    $checkStmt = $db->prepare("SELECT id, status, title FROM completion_certificates WHERE id = ?");
    $checkStmt->execute([$certificateId]);
    $certificate = $checkStmt->fetch();

    if (!$certificate) {
        throw new Exception('الشهادة غير موجودة');
    }

    // التحقق من أن الحالة مختلفة
    if ($certificate['status'] === $newStatus) {
        throw new Exception('الشهادة لها نفس الحالة المطلوبة بالفعل');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // تحديث حالة الشهادة
        $updateStmt = $db->prepare("
            UPDATE completion_certificates 
            SET status = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        
        $updateStmt->execute([$newStatus, $_SESSION['user_id'], $certificateId]);
        
        if ($updateStmt->rowCount() === 0) {
            throw new Exception('فشل في تحديث حالة الشهادة');
        }
        
        // تسجيل العملية في سجل النشاطات (إذا كان موجوداً)
        try {
            $logStmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $statusText = $newStatus === 'completed' ? 'مكتمل' : 'جاري الإعداد';
            $description = "تم تغيير حالة شهادة الإنجاز '{$certificate['title']}' إلى '{$statusText}'";
            
            $logStmt->execute([
                $_SESSION['user_id'],
                'update_status',
                'completion_certificates',
                $certificateId,
                $description
            ]);
        } catch (Exception $e) {
            // تجاهل خطأ السجل إذا لم يكن الجدول موجوداً
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث حالة الشهادة بنجاح',
            'data' => [
                'certificate_id' => $certificateId,
                'new_status' => $newStatus,
                'status_text' => $newStatus === 'completed' ? 'مكتمل' : 'جاري الإعداد'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
