<?php
/**
 * رفض سجل إنتاجية يومي - AJAX
 * Reject Daily Productivity Log - AJAX
 */

// منع عرض الأخطاء والتحذيرات في الاستجابة
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityApproval.php';

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
if (!hasPermission('productivity_approvals_reject')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لرفض السجلات'
    ]);
    exit();
}

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات غير صحيحة');
    }
    
    $logId = $input['log_id'] ?? null;
    $comments = $input['comments'] ?? '';
    
    // التحقق من صحة البيانات
    if (!$logId || !is_numeric($logId)) {
        throw new Exception('معرف السجل مطلوب ويجب أن يكون رقماً');
    }
    
    if (empty(trim($comments))) {
        throw new Exception('سبب الرفض مطلوب');
    }
    
    // إنشاء كائن النموذج
    $approvalModel = new ProductivityApproval();
    
    // التحقق من وجود السجل وحالته
    $db = getDB();
    $logStmt = $db->prepare("
        SELECT pdl.*, pwi.work_order_id, wo.branch_id
        FROM productivity_daily_logs pdl
        JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
        JOIN work_orders wo ON pwi.work_order_id = wo.id
        WHERE pdl.id = ?
    ");
    $logStmt->execute([$logId]);
    $log = $logStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
        throw new Exception('السجل غير موجود');
    }
    
    if ($log['status'] !== 'submitted') {
        throw new Exception('لا يمكن رفض هذا السجل. الحالة الحالية: ' . $log['status']);
    }
    
    // التحقق من صلاحية الفرع
    if (!hasPermission('productivity_daily_logs_view_all_branches') && 
        isset($_SESSION['branch_id']) && 
        $log['branch_id'] != $_SESSION['branch_id']) {
        throw new Exception('ليس لديك صلاحية لرفض سجلات هذا الفرع');
    }
    
    // رفض السجل
    $result = $approvalModel->reject($logId, $_SESSION['user_id'], $comments);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم رفض السجل',
            'log_id' => $logId
        ]);
    } else {
        throw new Exception('فشل في رفض السجل');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
