<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_update_fields')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتحديث حقول أوامر العمل'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit();
}

// قراءة البيانات من POST أو JSON
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit();
}

$workOrderId = $input['work_order_id'] ?? $input['id'] ?? null;
$field = $input['field'] ?? null;
$value = $input['value'] ?? null;

// تسجيل الطلب للتشخيص
error_log("Update field request: workOrderId=$workOrderId, field=$field, value=" . ($value ?? 'NULL'));

if (!$workOrderId || !$field) {
    echo json_encode(['success' => false, 'message' => 'معاملات مطلوبة مفقودة']);
    exit();
}

try {
    $db = getDB();

    // التحقق من وجود أمر العمل
    $stmt = $db->prepare("SELECT id FROM work_orders WHERE id = ?");
    $stmt->execute([$workOrderId]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'أمر العمل غير موجود']);
        exit();
    }

    // تحديد الحقول المسموحة
    $allowedFields = [
        'department',
        'current_entity_id',
        'location',
        'assignment_date',
        'actual_value',
        'disbursement_status',
        'status',
        'completion_certificate_status',
        'completion_certificate_confirmation'
    ];

    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'message' => 'حقل غير مسموح']);
        exit();
    }
    
    // التحقق من صحة البيانات
    switch ($field) {
        case 'department':
            if (!in_array($value, ['connections', 'projects'])) {
                echo json_encode(['success' => false, 'message' => 'قسم غير صحيح']);
                exit();
            }
            break;

        case 'current_entity_id':
            if ($value !== '' && $value !== null) {
                $stmt = $db->prepare("SELECT id FROM current_entities WHERE id = ? AND is_active = 1");
                $stmt->execute([$value]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'الجهة الحالية غير موجودة']);
                    exit();
                }
            }
            break;

        case 'location':
            if ($value && strlen($value) > 255) {
                echo json_encode(['success' => false, 'message' => 'الموقع طويل جداً (الحد الأقصى 255 حرف)']);
                exit();
            }
            break;

        case 'assignment_date':
            if ($value && !DateTime::createFromFormat('Y-m-d', $value)) {
                echo json_encode(['success' => false, 'message' => 'تاريخ غير صحيح']);
                exit();
            }
            break;

        case 'actual_value':
            if ($value !== '' && (!is_numeric($value) || $value < 0)) {
                echo json_encode(['success' => false, 'message' => 'قيمة غير صحيحة']);
                exit();
            }
            break;

        case 'disbursement_status':
            $validStatuses = ['none', 'completed', 'disbursement', 'return', 'disbursement_return_completed'];
            if (!in_array($value, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'حالة صرف غير صحيحة']);
                exit();
            }
            break;

        case 'status':
            $validStatuses = ['active', 'completed', 'cancelled', 'inactive'];
            if (!in_array($value, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'حالة غير صحيحة']);
                exit();
            }
            break;

        case 'completion_certificate_status':
            $validStatuses = ['not_attached', 'attached', 'not_applicable'];
            if (!in_array($value, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'حالة شهادة الإنجاز غير صحيحة']);
                exit();
            }
            break;

        case 'completion_certificate_confirmation':
            $validStatuses = ['empty', 'confirmed', 'accepted', 'rejected'];
            if (!in_array($value, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'تأكيد شهادة الإنجاز غير صحيح']);
                exit();
            }
            break;
    }
    
    // تحديث البيانات
    if (in_array($field, ['completion_certificate_status', 'completion_certificate_confirmation'])) {
        // تحديث في جدول المرفقات
        // أولاً، التحقق من وجود سجل شهادة الإنجاز
        $stmt = $db->prepare("SELECT id FROM work_order_attachments WHERE work_order_id = ? AND form_type = 'completion_certificate'");
        $stmt->execute([$workOrderId]);
        $attachment = $stmt->fetch();

        // تحديد اسم الحقل الصحيح في قاعدة البيانات
        $dbField = ($field === 'completion_certificate_status') ? 'status' : 'completion_certificate_confirmation';

        // تحديد حقول التاريخ التي يجب تحديثها
        $dateUpdate = '';
        $dateParams = [];
        if ($field === 'completion_certificate_status') {
            if ($value === 'attached') {
                $dateUpdate = ', certificate_attached_date = COALESCE(certificate_attached_date, CURDATE())';
            } else {
                $dateUpdate = ', certificate_attached_date = NULL';
            }
        } elseif ($field === 'completion_certificate_confirmation') {
            if ($value === 'confirmed') {
                $dateUpdate = ', certificate_confirmed_date = COALESCE(certificate_confirmed_date, CURDATE())';
            } else {
                $dateUpdate = ', certificate_confirmed_date = NULL';
            }
        }

        if ($attachment) {
            // تحديث السجل الموجود
            $sql = "UPDATE work_order_attachments SET {$dbField} = ?, updated_at = NOW(){$dateUpdate} WHERE work_order_id = ? AND form_type = 'completion_certificate'";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$value, $workOrderId]);
        } else {
            // إنشاء سجل جديد
            if ($field === 'completion_certificate_status') {
                $attachedDate = ($value === 'attached') ? date('Y-m-d') : null;
                $sql = "INSERT INTO work_order_attachments (work_order_id, form_type, status, certificate_attached_date, created_at, updated_at) VALUES (?, 'completion_certificate', ?, ?, NOW(), NOW())";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$workOrderId, $value, $attachedDate]);
            } else {
                $confirmedDate = ($value === 'confirmed') ? date('Y-m-d') : null;
                $sql = "INSERT INTO work_order_attachments (work_order_id, form_type, completion_certificate_confirmation, certificate_confirmed_date, status, created_at, updated_at) VALUES (?, 'completion_certificate', ?, ?, 'not_attached', NOW(), NOW())";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$workOrderId, $value, $confirmedDate]);
            }
        }
    } else {
        // تحديث في جدول أوامر العمل
        $sql = "UPDATE work_orders SET {$field} = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);

        if ($value === '' && in_array($field, ['current_entity_id', 'assignment_date'])) {
            $value = null;
        }

        $result = $stmt->execute([$value, $workOrderId]);
    }

    if ($result) {
        // تسجيل نجاح العملية
        error_log("Successfully updated work order field: workOrderId=$workOrderId, field=$field, value=" . ($value ?? 'NULL'));

        // جلب البيانات المحدثة
        $stmt = $db->prepare("
            SELECT wo.*, wot.type_code as work_order_type_code, wot.description as work_order_type_description,
                   b.name as branch_name, b.code as branch_code,
                   ce.name as current_entity_name
            FROM work_orders wo
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            LEFT JOIN branches b ON wo.branch_id = b.id
            LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
            WHERE wo.id = ?
        ");
        $stmt->execute([$workOrderId]);
        $updatedWorkOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        // تحضير الرسالة
        $messages = [
            'department' => 'تم تحديث القسم بنجاح',
            'current_entity_id' => 'تم تحديث الجهة الحالية بنجاح',
            'location' => 'تم تحديث الموقع بنجاح',
            'assignment_date' => 'تم تحديث تاريخ التكليف بنجاح',
            'actual_value' => 'تم تحديث القيمة الفعلية بنجاح',
            'disbursement_status' => 'تم تحديث حالة الصرف بنجاح',
            'status' => 'تم تحديث الحالة بنجاح',
            'completion_certificate_status' => 'تم تحديث حالة شهادة الإنجاز بنجاح',
            'completion_certificate_confirmation' => 'تم تحديث تأكيد شهادة الإنجاز بنجاح'
        ];

        echo json_encode([
            'success' => true,
            'message' => $messages[$field] ?? 'تم تحديث البيانات بنجاح',
            'data' => $updatedWorkOrder
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث البيانات']);
    }
    
} catch (Exception $e) {
    error_log("Error updating work order field: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحديث: ' . $e->getMessage()]);
}
?>
