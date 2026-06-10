<?php
/**
 * معالجة إنشاء المستخلص النهائي للجزئي عبر AJAX
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
    $required_fields = ['extract_number', 'extract_date', 'work_order_ids', 'branch_id', 'related_partial_extract_id'];
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
    $stmt = $db->prepare("SELECT id FROM final_for_partial_extracts WHERE extract_number = ?");
    $stmt->execute([$_POST['extract_number']]);
    if ($stmt->fetch()) {
        throw new Exception('رقم المستخلص موجود مسبقاً');
    }
    
    // التحقق من وجود المستخلص الجزئي واستيفاء الشروط (مع جلب القسم)
    $stmt = $db->prepare("
        SELECT pe.id, pe.extract_number, pe.department,
               COUNT(DISTINCT pewo.id) as total_work_orders,
               COUNT(DISTINCT CASE WHEN cc.completion_certificate_confirmation = 'confirmed' THEN pewo.id END) as confirmed_certificates
        FROM partial_extracts pe
        LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
        LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
        LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
        LEFT JOIN final_for_partial_extracts ffpe ON pe.id = ffpe.related_partial_extract_id
        WHERE pe.id = ? AND pe.approval_stage IN ('disbursed', 'taif_finance') AND ffpe.id IS NULL
        GROUP BY pe.id, pe.extract_number, pe.department
        HAVING total_work_orders > 0 AND confirmed_certificates = total_work_orders
    ");
    $stmt->execute([$_POST['related_partial_extract_id']]);
    $partialExtract = $stmt->fetch();
    if (!$partialExtract) {
        throw new Exception('المستخلص الجزئي غير موجود أو لا يستوفي الشروط المطلوبة. يجب أن يكون في مرحلة مصروف أو مالية الطائف مع تأكيد جميع شهادات الإنجاز ولم يتم إنشاء مستخلص نهائي له مسبقاً');
    }

    // بدء المعاملة
    $db->beginTransaction();

    // إنشاء المستخلص النهائي للجزئي (مع تحديد القسم من المستخلص الجزئي)
    $stmt = $db->prepare("
        INSERT INTO final_for_partial_extracts (
            extract_number, extract_date, branch_id, related_partial_extract_id,
            department, total_amount, tax_amount, net_amount, total_penalty_amount,
            created_by, approval_stage
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
    ");
    
    // حساب المجاميع
    $total_amount = 0;
    $total_penalty = 0;

    foreach ($work_order_ids as $work_order_id) {
        if (isset($_POST['extract_values'][$work_order_id])) {
            $total_amount += floatval($_POST['extract_values'][$work_order_id]);
        }
        if (isset($_POST['penalty_amounts'][$work_order_id])) {
            $total_penalty += floatval($_POST['penalty_amounts'][$work_order_id]);
        }
    }

    // جلب ضريبة المستخلص الجزئي المرتبط
    $stmt_tax = $db->prepare("SELECT tax_amount FROM partial_extracts WHERE id = ?");
    $stmt_tax->execute([$_POST['related_partial_extract_id']]);
    $partial_tax = $stmt_tax->fetch();
    $partial_extract_tax_amount = $partial_tax ? floatval($partial_tax['tax_amount']) : 0;

    // حساب المبالغ
    // الصافي = مجموع قيم أوامر العمل + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامة
    $tax_amount = $total_amount * 0.15;
    $net_amount = $total_amount + $tax_amount + $partial_extract_tax_amount - $total_penalty;

    $stmt->execute([
        $_POST['extract_number'],
        $_POST['extract_date'],
        $_POST['branch_id'],
        $_POST['related_partial_extract_id'],
        $partialExtract['department'], // القسم من المستخلص الجزئي
        $total_amount,
        $tax_amount,
        $net_amount,
        $total_penalty,
        $user_id
    ]);
    
    $extract_id = $db->lastInsertId();
    
    // إضافة أوامر العمل
    $stmt = $db->prepare("
        INSERT INTO final_for_partial_extract_work_orders (
            final_for_partial_extract_id, work_order_id, extract_value, penalty_amount, completion_date, added_by
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($work_order_ids as $work_order_id) {
        $extract_value = floatval($_POST['extract_values'][$work_order_id] ?? 0);
        $penalty_amount = floatval($_POST['penalty_amounts'][$work_order_id] ?? 0);
        $completion_date = $_POST['completion_dates'][$work_order_id] ?? null;
        
        $stmt->execute([
            $extract_id,
            $work_order_id,
            $extract_value,
            $penalty_amount,
            $completion_date,
            $user_id
        ]);
        
        // تحديث تاريخ الاستلام في أمر العمل إذا كان مختلفاً
        if ($completion_date) {
            $updateWorkOrderQuery = "
                UPDATE work_orders 
                SET receipt_date = ?
                WHERE id = ? AND (receipt_date IS NULL OR receipt_date != ?)
            ";
            $updateStmt = $db->prepare($updateWorkOrderQuery);
            $updateStmt->execute([$completion_date, $work_order_id, $completion_date]);
        }
    }
    
    // معالجة المرفقات
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $upload_dir = __DIR__ . '/../../../uploads/extracts/final-for-partial/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $stmt = $db->prepare("
            INSERT INTO final_for_partial_extract_attachments (
                final_for_partial_extract_id, file_name, file_path, file_size, uploaded_by, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($_FILES['attachments']['name'] as $key => $filename) {
            if (!empty($filename)) {
                $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
                $new_filename = 'extract_' . $extract_id . '_' . time() . '_' . $key . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$key], $file_path)) {
                    $stmt->execute([
                        $extract_id,
                        $filename,
                        'uploads/extracts/final-for-partial/' . $new_filename,
                        $_FILES['attachments']['size'][$key],
                        $user_id
                    ]);
                }
            }
        }
    }
    
    // تأكيد المعاملة
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء المستخلص النهائي للجزئي بنجاح',
        'extract_id' => $extract_id,
        'redirect_url' => 'view.php?id=' . $extract_id
    ]);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Final for Partial Extract Creation Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
