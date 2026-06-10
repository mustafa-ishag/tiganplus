<?php
/**
 * حذف شهادة الإنجاز
 * Delete Completion Certificate
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
if (!hasPermission('inventory_certificates_delete')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لحذف الشهادات'
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
    
    // التحقق من صحة البيانات
    if (!$certificateId) {
        throw new Exception('رقم الشهادة مطلوب');
    }
    
    $db = getDB();
    
    // التحقق من وجود الشهادة وحالتها
    $checkStmt = $db->prepare("
        SELECT id, status, title, work_order_id, total_certificate_value 
        FROM completion_certificates 
        WHERE id = ?
    ");
    $checkStmt->execute([$certificateId]);
    $certificate = $checkStmt->fetch();
    
    if (!$certificate) {
        throw new Exception('الشهادة غير موجودة');
    }
    
    // التحقق من أن الشهادة في حالة "جاري الإعداد" فقط
    if ($certificate['status'] !== 'in_progress') {
        throw new Exception('لا يمكن حذف الشهادات المكتملة. يجب تغيير الحالة إلى "جاري الإعداد" أولاً');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // حذف مواد الشهادة أولاً (بسبب القيود الخارجية)
        $deleteMaterialsStmt = $db->prepare("DELETE FROM completion_certificate_materials WHERE certificate_id = ?");
        $deleteMaterialsStmt->execute([$certificateId]);
        $deletedMaterials = $deleteMaterialsStmt->rowCount();
        
        // حذف أعمال الشهادة
        $deleteWorksStmt = $db->prepare("DELETE FROM completion_certificate_works WHERE certificate_id = ?");
        $deleteWorksStmt->execute([$certificateId]);
        $deletedWorks = $deleteWorksStmt->rowCount();
        
        // حذف الشهادة نفسها
        $deleteCertStmt = $db->prepare("DELETE FROM completion_certificates WHERE id = ?");
        $deleteCertStmt->execute([$certificateId]);
        
        if ($deleteCertStmt->rowCount() === 0) {
            throw new Exception('فشل في حذف الشهادة');
        }
        
        // تسجيل العملية في سجل النشاطات (إذا كان موجوداً)
        try {
            $logStmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, table_name, record_id, description, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $description = "تم حذف شهادة الإنجاز '{$certificate['title']}' (القيمة: " . number_format($certificate['total_certificate_value'], 2) . " ريال)";
            
            $logStmt->execute([
                $_SESSION['user_id'],
                'delete',
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
            'message' => 'تم حذف الشهادة بنجاح',
            'data' => [
                'certificate_id' => $certificateId,
                'deleted_materials' => $deletedMaterials,
                'deleted_works' => $deletedWorks,
                'certificate_title' => $certificate['title']
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
