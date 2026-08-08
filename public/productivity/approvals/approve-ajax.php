<?php
/**
 * اعتماد سجل إنتاجية يومي - AJAX
 * Approve Daily Productivity Log - AJAX
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

// بدء output buffering لضمان استجابة JSON نظيفة
ob_start();

// تعيين نوع المحتوى
header('Content-Type: application/json; charset=utf-8');

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة'
    ]);
    exit();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_approvals_approve')) {
    ob_clean();
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
    
    $logId = $input['log_id'] ?? null;
    $comments = $input['comments'] ?? '';
    
    // التحقق من صحة البيانات
    if (!$logId || !is_numeric($logId)) {
        throw new Exception('معرف السجل مطلوب ويجب أن يكون رقماً');
    }
    
    // إنشاء كائن النموذج
    $approvalModel = new ProductivityApproval();
    
    // التحقق من وجود السجل وحالته
    $db = getDB();
    $logStmt = $db->prepare("
        SELECT pdl.*, pwi.work_order_id, pwi.unit_price, wo.branch_id
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
        throw new Exception('لا يمكن اعتماد هذا السجل. الحالة الحالية: ' . $log['status']);
    }
    
    // التحقق من صلاحية الفرع
    if (!hasPermission('productivity_daily_logs_view_all_branches') && 
        isset($_SESSION['branch_id']) && 
        $log['branch_id'] != $_SESSION['branch_id']) {
        throw new Exception('ليس لديك صلاحية لاعتماد سجلات هذا الفرع');
    }
    
    // حساب القيمة المعتمدة
    $approvalValue = $log['quantity_completed'] * $log['unit_price'];
    
    // اعتماد السجل
    $result = $approvalModel->approve($logId, $_SESSION['user_id'], $comments, $approvalValue);
    
    if ($result) {
        // تحديث إحصائيات بند الإنتاجية بعد الاعتماد
        try {
            $workItemId = $log['work_item_id'];
            
            // حساب إجمالي الكمية المعتمدة لهذا البند
            $statsStmt = $db->prepare("
                SELECT 
                    COALESCE(SUM(quantity_completed), 0) as total_approved
                FROM productivity_daily_logs
                WHERE work_item_id = ? AND status = 'approved'
            ");
            $statsStmt->execute([$workItemId]);
            $totalApproved = floatval($statsStmt->fetchColumn());
            
            // جلب الكمية المستهدفة
            $targetStmt = $db->prepare("SELECT target_quantity FROM productivity_work_items WHERE id = ?");
            $targetStmt->execute([$workItemId]);
            $targetQuantity = floatval($targetStmt->fetchColumn());
            
            $remaining = max(0, $targetQuantity - $totalApproved);
            $progress = $targetQuantity > 0 ? round(($totalApproved / $targetQuantity) * 100, 2) : 0;
            
            $updateStmt = $db->prepare("
                UPDATE productivity_work_items 
                SET actual_quantity_completed = ?,
                    remaining_quantity = ?,
                    progress_percentage = ?,
                    status = CASE 
                        WHEN ? >= target_quantity THEN 'completed'
                        WHEN ? > 0 THEN 'active'
                        ELSE status
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$totalApproved, $remaining, $progress, $totalApproved, $totalApproved, $workItemId]);
        } catch (Exception $statsError) {
            error_log("Error updating work item stats after approval: " . $statsError->getMessage());
        }
        
        // تنظيف output buffer وإرسال الاستجابة
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'تم اعتماد السجل بنجاح',
            'log_id' => $logId,
            'approved_value' => $approvalValue
        ]);
    } else {
        throw new Exception('فشل في اعتماد السجل');
    }

} catch (Exception $e) {
    // تنظيف output buffer وإرسال رسالة الخطأ
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
