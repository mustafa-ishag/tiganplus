<?php
/**
 * حذف بند إنتاجية - AJAX
 * Delete Productivity Work Item - AJAX
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';

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
if (!hasPermission('productivity_work_items_delete')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لحذف بنود الإنتاجية'
    ]);
    exit();
}

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات غير صحيحة');
    }
    
    $workItemId = $input['id'] ?? null;
    
    // التحقق من صحة البيانات
    if (!$workItemId || !is_numeric($workItemId)) {
        throw new Exception('معرف بند الإنتاجية مطلوب ويجب أن يكون رقماً');
    }
    
    // إنشاء كائن النموذج
    $workItemModel = new ProductivityWorkItem();
    
    // التحقق من وجود البند
    $workItem = $workItemModel->getById($workItemId);
    
    if (!$workItem) {
        throw new Exception('بند الإنتاجية غير موجود');
    }
    
    // التحقق من صلاحية الفرع
    if (!hasPermission('productivity_daily_logs_view_all_branches') && 
        isset($_SESSION['branch_id']) && 
        $workItem['branch_id'] != $_SESSION['branch_id']) {
        throw new Exception('ليس لديك صلاحية لحذف بنود إنتاجية هذا الفرع');
    }
    
    // التحقق من وجود سجلات يومية مرتبطة
    $db = getDB();
    $dailyLogsStmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM productivity_daily_logs 
        WHERE work_item_id = ?
    ");
    $dailyLogsStmt->execute([$workItemId]);
    $dailyLogsCount = $dailyLogsStmt->fetchColumn();
    
    if ($dailyLogsCount > 0) {
        throw new Exception("لا يمكن حذف هذا البند لأنه يحتوي على {$dailyLogsCount} سجل يومي. يجب حذف السجلات اليومية أولاً");
    }
    
    // حذف البند
    $result = $workItemModel->delete($workItemId, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف بند الإنتاجية بنجاح',
            'work_item_id' => $workItemId
        ]);
    } else {
        throw new Exception('فشل في حذف بند الإنتاجية');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
