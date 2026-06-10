<?php
/**
 * اعتماد متعدد للسجلات اليومية - AJAX
 * Bulk Approve Daily Productivity Logs - AJAX
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
if (!hasPermission('productivity_approvals_approve')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لاعتماد السجلات'
    ]);
    exit();
}

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('بيانات غير صحيحة');
    }
    
    $logIds = $input['log_ids'] ?? [];
    
    // التحقق من صحة البيانات
    if (empty($logIds) || !is_array($logIds)) {
        throw new Exception('يجب اختيار سجل واحد على الأقل');
    }
    
    // التحقق من أن جميع المعرفات أرقام
    foreach ($logIds as $logId) {
        if (!is_numeric($logId)) {
            throw new Exception('معرفات السجلات يجب أن تكون أرقاماً');
        }
    }
    
    // إنشاء كائن النموذج
    $approvalModel = new ProductivityApproval();
    $db = getDB();
    
    // جلب السجلات والتحقق من صحتها
    $placeholders = str_repeat('?,', count($logIds) - 1) . '?';
    $logsStmt = $db->prepare("
        SELECT pdl.*, pwi.work_order_id, wo.branch_id,
               (pdl.quantity_completed * pdl.unit_price) as calculated_value
        FROM productivity_daily_logs pdl
        JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
        JOIN work_orders wo ON pwi.work_order_id = wo.id
        WHERE pdl.id IN ($placeholders)
    ");
    $logsStmt->execute($logIds);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) !== count($logIds)) {
        throw new Exception('بعض السجلات غير موجودة');
    }
    
    // التحقق من حالة السجلات وصلاحيات الفروع
    $validLogs = [];
    $errors = [];
    
    foreach ($logs as $log) {
        // التحقق من الحالة
        if ($log['status'] !== 'submitted') {
            $errors[] = "السجل رقم {$log['id']} ليس في حالة معلق للاعتماد";
            continue;
        }
        
        // التحقق من صلاحية الفرع
        if (!hasPermission('productivity_daily_logs_view_all_branches') && 
            isset($_SESSION['branch_id']) && 
            $log['branch_id'] != $_SESSION['branch_id']) {
            $errors[] = "ليس لديك صلاحية لاعتماد السجل رقم {$log['id']}";
            continue;
        }
        
        $validLogs[] = $log;
    }
    
    if (!empty($errors)) {
        throw new Exception(implode(', ', $errors));
    }
    
    if (empty($validLogs)) {
        throw new Exception('لا توجد سجلات صالحة للاعتماد');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    $approvedCount = 0;
    $failedCount = 0;
    $failedLogs = [];
    
    try {
        foreach ($validLogs as $log) {
            $result = $approvalModel->approve(
                $log['id'], 
                $_SESSION['user_id'], 
                'اعتماد متعدد', 
                $log['calculated_value']
            );
            
            if ($result) {
                $approvedCount++;
            } else {
                $failedCount++;
                $failedLogs[] = $log['id'];
            }
        }
        
        // إذا فشل أي اعتماد، إلغاء المعاملة
        if ($failedCount > 0) {
            $db->rollBack();
            throw new Exception("فشل في اعتماد {$failedCount} سجل من أصل " . count($validLogs));
        }
        
        // تأكيد المعاملة
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "تم اعتماد {$approvedCount} سجل بنجاح",
            'approved_count' => $approvedCount,
            'total_requested' => count($logIds)
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
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
