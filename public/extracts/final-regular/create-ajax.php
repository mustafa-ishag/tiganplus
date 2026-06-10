<?php
/**
 * معالجة إنشاء المستخلص النهائي العادي عبر AJAX
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_create')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإنشاء المستخلصات']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مدعومة']);
    exit();
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // التحقق من البيانات المطلوبة
    $required_fields = ['extract_number', 'extract_date', 'work_order_ids', 'branch_id'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("الحقل '$field' مطلوب");
        }
    }
    
    // التحقق من أوامر العمل
    $work_order_ids = $_POST['work_order_ids'];
    if (!is_array($work_order_ids) || empty($work_order_ids)) {
        throw new Exception('يجب إضافة أمر عمل واحد على الأقل');
    }
    
    // التحقق من عدم تكرار رقم المستخلص
    $stmt = $db->prepare("SELECT id FROM final_regular_extracts WHERE extract_number = ?");
    $stmt->execute([$_POST['extract_number']]);
    if ($stmt->fetch()) {
        throw new Exception('رقم المستخلص موجود مسبقاً');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // حساب المبالغ
    $total_amount = 0;
    $total_penalty_amount = 0;
    
    foreach ($work_order_ids as $wo_id) {
        $extract_value = floatval($_POST['extract_values'][$wo_id] ?? 0);
        $penalty_amount = floatval($_POST['penalty_amounts'][$wo_id] ?? 0);
        
        $total_amount += $extract_value;
        $total_penalty_amount += $penalty_amount;
    }
    
    $tax_rate = 15.00;
    $tax_amount = $total_amount * ($tax_rate / 100);
    $net_amount = $total_amount + $tax_amount - $total_penalty_amount;

    // الحصول على معلومات الفرع من النموذج
    $branch_id = intval($_POST['branch_id']);

    // التحقق من صحة الفرع
    $stmt = $db->prepare("SELECT id FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    if (!$stmt->fetch()) {
        throw new Exception('الفرع المحدد غير صحيح');
    }
    
    // تحديد مرحلة الاعتماد حسب نوع الحفظ
    $action = $_POST['action'] ?? 'save';
    $approval_stage = null; // مسودة
    $submission_date = null;
    
    // التقديم مباشرة للمرحلة الأولى (الدعم الفني)
    if ($action === 'submit' || $action === 'save') {
        $approval_stage = 'technical_support';
        $submission_date = date('Y-m-d');
    }
    
    // إدراج المستخلص النهائي العادي
    $stmt = $db->prepare("
        INSERT INTO final_regular_extracts (
            extract_number, invoice_number, branch_id, created_by, description, 
            extract_date, submission_date, total_amount, tax_rate, tax_amount, 
            total_penalty_amount, net_amount, approval_stage, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $_POST['extract_number'],
        $_POST['invoice_number'] ?? null,
        $branch_id,
        $user_id,
        $_POST['description'] ?? null,
        $_POST['extract_date'],
        $submission_date,
        $total_amount,
        $tax_rate,
        $tax_amount,
        $total_penalty_amount,
        $net_amount,
        $approval_stage
    ]);
    
    $extract_id = $db->lastInsertId();
    
    // إدراج أوامر العمل المرتبطة
    $stmt = $db->prepare("
        INSERT INTO final_regular_extract_work_orders (
            final_regular_extract_id, work_order_id, extract_value,
            completion_date, penalty_amount, added_by, added_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($work_order_ids as $wo_id) {
        $extract_value = floatval($_POST['extract_values'][$wo_id] ?? 0);
        $penalty_amount = floatval($_POST['penalty_amounts'][$wo_id] ?? 0);

        // جلب تاريخ الإنجاز من جدول أوامر العمل
        $woStmt = $db->prepare("SELECT receipt_date, assignment_date FROM work_orders WHERE id = ?");
        $woStmt->execute([$wo_id]);
        $workOrder = $woStmt->fetch();

        // استخدام تاريخ الاستلام أو تاريخ التكليف أو التاريخ الحالي
        $completion_date = $workOrder['receipt_date'] ?? $workOrder['assignment_date'] ?? date('Y-m-d');

        // إذا كان المستخدم أدخل تاريخ إنجاز مخصص، استخدمه
        if (!empty($_POST['completion_dates'][$wo_id])) {
            $completion_date = $_POST['completion_dates'][$wo_id];
        }

        $stmt->execute([
            $extract_id,
            $wo_id,
            $extract_value,
            $completion_date,
            $penalty_amount,
            $user_id
        ]);

        // تحديث تاريخ الإنجاز في جدول أوامر العمل
        // إذا تم تغيير التاريخ من قبل المستخدم، نحدث أمر العمل
        if (!empty($_POST['completion_dates'][$wo_id])) {
            $userCompletionDate = $_POST['completion_dates'][$wo_id];

            // تحديث receipt_date في أمر العمل إذا كان مختلفاً
            if ($workOrder['receipt_date'] !== $userCompletionDate) {
                $updateWoStmt = $db->prepare("UPDATE work_orders SET receipt_date = ? WHERE id = ?");
                $updateWoStmt->execute([$userCompletionDate, $wo_id]);

                // تسجيل التحديث للمراجعة
                error_log("Updated work_order {$wo_id} receipt_date from '{$workOrder['receipt_date']}' to '{$userCompletionDate}'");
            }
        }
    }

    
    $db->commit();
    
    // إرسال الاستجابة
    $response = [
        'success' => true,
        'message' => 'تم إنشاء وتقديم المستخلص النهائي العادي بنجاح',
        'extract_id' => $extract_id,
        'extract_number' => $_POST['extract_number'],
        'approval_stage' => $approval_stage,
        'redirect_url' => '../index.php'
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
