<?php
/**
 * حذف سجل إنتاجية يومي - AJAX
 * Delete Daily Productivity Log - AJAX
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityDailyLog.php';

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
if (!hasPermission('productivity_daily_logs_delete')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لحذف السجلات'
    ]);
    exit();
}

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات غير صحيحة');
    }
    
    $logId = $input['id'] ?? null;
    
    // التحقق من صحة البيانات
    if (!$logId || !is_numeric($logId)) {
        throw new Exception('معرف السجل مطلوب ويجب أن يكون رقماً');
    }
    
    // إنشاء كائن النموذج
    $dailyLogModel = new ProductivityDailyLog();
    
    // التحقق من وجود السجل وحالته
    $log = $dailyLogModel->getById($logId);
    
    if (!$log) {
        throw new Exception('السجل غير موجود');
    }
    
    // التحقق من إمكانية الحذف (فقط المسودات والمرجعة)
    if (!in_array($log['status'], ['draft', 'returned'])) {
        throw new Exception('لا يمكن حذف هذا السجل. يمكن حذف المسودات والسجلات المرجعة فقط');
    }
    
    // التحقق من صلاحية الفرع
    if (!hasPermission('productivity_daily_logs_view_all_branches') && 
        isset($_SESSION['branch_id']) && 
        $log['branch_id'] != $_SESSION['branch_id']) {
        throw new Exception('ليس لديك صلاحية لحذف سجلات هذا الفرع');
    }
    
    // التحقق من أن المستخدم هو من أنشأ السجل أو لديه صلاحية إدارية
    if ($log['created_by'] != $_SESSION['user_id'] && !hasPermission('productivity_daily_logs_view_all_branches')) {
        throw new Exception('يمكنك حذف السجلات التي أنشأتها فقط');
    }
    
    // حذف السجل
    $result = $dailyLogModel->delete($logId, $_SESSION['user_id']);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف السجل بنجاح',
            'log_id' => $logId
        ]);
    } else {
        throw new Exception('فشل في حذف السجل');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
