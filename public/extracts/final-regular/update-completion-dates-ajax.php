<?php
/**
 * تحديث تواريخ الإنجاز في أوامر العمل المرتبطة بالمستخلص النهائي العادي
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit();
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_update_fields')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتحديث حقول المستخلصات']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير مدعومة']);
    exit();
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit();
}

// التحقق من البيانات المطلوبة
if (!isset($input['extract_id']) || !isset($input['completion_dates'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات مطلوبة مفقودة']);
    exit();
}

$extract_id = (int) $input['extract_id'];
$completion_dates = $input['completion_dates'];
$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();

    // التحقق من وجود المستخلص
    $stmt = $db->prepare("SELECT id FROM final_regular_extracts WHERE id = ?");
    $stmt->execute([$extract_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المستخلص غير موجود']);
        exit();
    }

    // بدء المعاملة
    $db->beginTransaction();

    $updatedCount = 0;
    $errors = [];

    foreach ($completion_dates as $work_order_id => $new_completion_date) {
        $work_order_id = (int) $work_order_id;
        
        // التحقق من صحة التاريخ
        if (!$new_completion_date || !strtotime($new_completion_date)) {
            $errors[] = "تاريخ غير صحيح لأمر العمل {$work_order_id}";
            continue;
        }

        try {
            // التحقق من وجود العلاقة بين المستخلص وأمر العمل
            $checkStmt = $db->prepare("
                SELECT frewo.id, frewo.completion_date, wo.receipt_date, wo.work_order_number
                FROM final_regular_extract_work_orders frewo
                JOIN work_orders wo ON frewo.work_order_id = wo.id
                WHERE frewo.final_regular_extract_id = ? AND frewo.work_order_id = ?
            ");
            $checkStmt->execute([$extract_id, $work_order_id]);
            $relation = $checkStmt->fetch();

            if (!$relation) {
                $errors[] = "أمر العمل {$work_order_id} غير مرتبط بهذا المستخلص";
                continue;
            }

            // تحديث تاريخ الإنجاز في جدول العلاقات
            $updateExtractStmt = $db->prepare("
                UPDATE final_regular_extract_work_orders 
                SET completion_date = ?, updated_at = NOW() 
                WHERE final_regular_extract_id = ? AND work_order_id = ?
            ");
            $updateExtractStmt->execute([$new_completion_date, $extract_id, $work_order_id]);

            // تحديث تاريخ الاستلام في جدول أوامر العمل
            $updateWorkOrderStmt = $db->prepare("
                UPDATE work_orders 
                SET receipt_date = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateWorkOrderStmt->execute([$new_completion_date, $work_order_id]);

            $updatedCount++;

            // تسجيل التحديث
            error_log("Updated completion date for work_order {$work_order_id} ({$relation['work_order_number']}) from '{$relation['completion_date']}' to '{$new_completion_date}' in extract {$extract_id}");
            error_log("Updated receipt_date for work_order {$work_order_id} from '{$relation['receipt_date']}' to '{$new_completion_date}'");

        } catch (Exception $e) {
            $errors[] = "خطأ في تحديث أمر العمل {$work_order_id}: " . $e->getMessage();
            error_log("Error updating work_order {$work_order_id}: " . $e->getMessage());
        }
    }

    // تأكيد المعاملة إذا لم تكن هناك أخطاء حرجة
    if (empty($errors) || $updatedCount > 0) {
        $db->commit();
        
        $response = [
            'success' => true,
            'message' => "تم تحديث {$updatedCount} من تواريخ الإنجاز بنجاح",
            'updated_count' => $updatedCount
        ];

        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'فشل في تحديث تواريخ الإنجاز',
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // إلغاء المعاملة في حالة الخطأ
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Error updating completion dates: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث تواريخ الإنجاز: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
