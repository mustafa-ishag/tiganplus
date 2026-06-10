<?php

declare(strict_types=1);

/**
 * تغيير حالة نوع أمر العمل
 * Toggle Work Order Type Status
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

try {
    // قراءة البيانات JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !is_numeric($input['id'])) {
        throw new Exception('معرف نوع أمر العمل غير صحيح');
    }
    
    if (!isset($input['action']) || !in_array($input['action'], ['activate', 'deactivate'])) {
        throw new Exception('إجراء غير صحيح');
    }
    
    $typeId = (int) $input['id'];
    $action = $input['action'];
    $newStatus = $action === 'activate' ? 'active' : 'inactive';
    
    $db = getDB();
    
    // التحقق من وجود نوع أمر العمل
    $checkStmt = $db->prepare("SELECT type_code, status FROM work_order_types WHERE id = ?");
    $checkStmt->execute([$typeId]);
    $workOrderType = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workOrderType) {
        throw new Exception('نوع أمر العمل غير موجود');
    }
    
    // التحقق من أن الحالة مختلفة
    if ($workOrderType['status'] === $newStatus) {
        $statusText = $newStatus === 'active' ? 'نشط' : 'غير نشط';
        throw new Exception("نوع أمر العمل {$statusText} بالفعل");
    }
    
    // تحديث الحالة
    $updateStmt = $db->prepare("UPDATE work_order_types SET status = ?, updated_at = NOW() WHERE id = ?");
    
    if ($updateStmt->execute([$newStatus, $typeId])) {
        $actionText = $action === 'activate' ? 'تفعيل' : 'إلغاء تفعيل';
        echo json_encode([
            'success' => true,
            'message' => "تم {$actionText} نوع أمر العمل '{$workOrderType['type_code']}' بنجاح"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('فشل في تحديث حالة نوع أمر العمل');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
