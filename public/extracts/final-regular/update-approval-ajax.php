<?php
/**
 * تحديث مرحلة الاعتماد للمستخلصات النهائية العادية عبر AJAX
 */

session_start();

// تعيين Content-Type للاستجابة
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح بالوصول']);
    exit();
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_approve')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لاعتماد المستخلصات']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// قراءة البيانات من JSON
$input = json_decode(file_get_contents('php://input'), true);

// تسجيل البيانات المستلمة للتشخيص
error_log("Received data: " . print_r($input, true));

// التحقق من البيانات المطلوبة
if (!isset($input['extract_id'])) {
    error_log("Missing extract_id in input");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المستخلص مطلوب']);
    exit();
}

$extract_id = (int) $input['extract_id'];
$new_stage = $input['approval_stage'] ?? null;

// تحويل القيم الفارغة إلى null (للمسودة)
if ($new_stage === '' || $new_stage === 'null' || empty($new_stage)) {
    $new_stage = null;
}

// تسجيل القيم للتشخيص
error_log("Processing approval stage update - Original: " . var_export($input['approval_stage'], true) . ", Processed: " . var_export($new_stage, true));

$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();

    // التحقق من وجود المستخلص
    $stmt = $db->prepare("SELECT id, approval_stage, submission_date FROM final_regular_extracts WHERE id = ?");
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();

    if (!$extract) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المستخلص غير موجود']);
        exit();
    }

    // بدء المعاملة
    $db->beginTransaction();
    error_log("Starting transaction for extract_id: $extract_id, new_stage: $new_stage");

    try {
        // حفظ المرحلة السابقة للمقارنة
        $previousStage = $extract['approval_stage'];

        // تحديث مرحلة الاعتماد
        $updateQuery = "UPDATE final_regular_extracts SET approval_stage = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($updateQuery);
        $result = $stmt->execute([$new_stage, $extract_id]);
        error_log("Update query result: " . ($result ? 'SUCCESS' : 'FAILED') . ", affected rows: " . $stmt->rowCount());

        // إذا تم تحديد تاريخ التقديم وكانت المرحلة ليست مسودة
        if ($new_stage !== null && $extract['submission_date'] === null) {
            $submissionUpdateQuery = "UPDATE final_regular_extracts SET submission_date = NOW() WHERE id = ?";
            $stmt = $db->prepare($submissionUpdateQuery);
            $stmt->execute([$extract_id]);
        }

        // إذا تم إلغاء المستخلص (إعادته لمسودة)، إزالة تاريخ التقديم
        if ($new_stage === null) {
            $submissionUpdateQuery = "UPDATE final_regular_extracts SET submission_date = NULL WHERE id = ?";
            $stmt = $db->prepare($submissionUpdateQuery);
            $stmt->execute([$extract_id]);
        }

        // إذا تم تحويل المرحلة إلى "مصروف"، تحديث حالة جميع أوامر العمل إلى "مكتمل"
        if ($new_stage === 'disbursed') {
            // البحث عن معرف الجهة "منتهى"
            $stmt = $db->prepare("SELECT id FROM current_entities WHERE name = 'منتهى' LIMIT 1");
            $stmt->execute();
            $finishedEntity = $stmt->fetch();
            $finishedEntityId = $finishedEntity ? $finishedEntity['id'] : null;

            // جلب جميع أوامر العمل المرتبطة بهذا المستخلص النهائي العادي
            $stmt = $db->prepare("
                SELECT DISTINCT wo.id, wo.work_order_number, wo.status, wo.current_entity_id
                FROM final_regular_extract_work_orders frewo
                INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
                WHERE frewo.final_regular_extract_id = ?
            ");
            $stmt->execute([$extract_id]);
            $workOrders = $stmt->fetchAll();

            // تحديث حالة كل أمر عمل إلى "completed" والجهة الحالية إلى "منتهى"
            $updatedWorkOrders = [];
            foreach ($workOrders as $wo) {
                $needsUpdate = false;
                $updates = [];
                $params = [];

                // التحقق من الحالة
                if ($wo['status'] !== 'completed') {
                    $updates[] = "status = 'completed'";
                    $needsUpdate = true;
                }

                // التحقق من الجهة الحالية
                if ($finishedEntityId && $wo['current_entity_id'] != $finishedEntityId) {
                    $updates[] = "current_entity_id = ?";
                    $params[] = $finishedEntityId;
                    $needsUpdate = true;
                }

                // تنفيذ التحديث إذا كان هناك تغييرات
                if ($needsUpdate) {
                    $updates[] = "updated_at = NOW()";
                    $updateQuery = "UPDATE work_orders SET " . implode(', ', $updates) . " WHERE id = ?";
                    $params[] = $wo['id'];

                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->execute($params);
                    $updatedWorkOrders[] = $wo['work_order_number'];
                }
            }

            // تسجيل عدد أوامر العمل المحدثة
            $updatedCount = count($updatedWorkOrders);
            if ($updatedCount > 0) {
                $entityInfo = $finishedEntityId ? " والجهة الحالية إلى 'منتهى'" : "";
                error_log("Updated $updatedCount work orders to 'completed' status{$entityInfo} for extract $extract_id");
            }
        }
        // إذا تم الإرجاع من "مصروف" إلى مرحلة سابقة، إرجاع حالة أوامر العمل إلى "نشط"
        elseif ($previousStage === 'disbursed' && $new_stage !== 'disbursed') {
            // جلب جميع أوامر العمل المرتبطة بهذا المستخلص النهائي العادي
            $stmt = $db->prepare("
                SELECT DISTINCT wo.id, wo.work_order_number, wo.status
                FROM final_regular_extract_work_orders frewo
                INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
                WHERE frewo.final_regular_extract_id = ?
            ");
            $stmt->execute([$extract_id]);
            $workOrders = $stmt->fetchAll();

            // إرجاع حالة كل أمر عمل إلى "active"
            $revertedWorkOrders = [];
            foreach ($workOrders as $wo) {
                if ($wo['status'] === 'completed') {
                    $updateStmt = $db->prepare("
                        UPDATE work_orders
                        SET status = 'active', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$wo['id']]);
                    $revertedWorkOrders[] = $wo['work_order_number'];
                }
            }

            // تسجيل عدد أوامر العمل المرتجعة
            $revertedCount = count($revertedWorkOrders);
            if ($revertedCount > 0) {
                error_log("Reverted $revertedCount work orders to 'active' status for extract $extract_id");
            }
        }

        // تأكيد المعاملة
        $db->commit();

    } catch (Exception $e) {
        // إلغاء المعاملة في حالة الخطأ
        $db->rollBack();
        throw $e;
    }

    // جلب اسم المرحلة الجديدة
    $stageNames = [
        null => "مسودة",
        "technical_support" => "الدعم الفني",
        "construction" => "الإنشاءات",
        "department_manager" => "مدير القسم",
        "administration_manager" => "مدير الإدارة",
        "taif_finance" => "مالية الطائف",
        "disbursed" => "تم الصرف"
    ];

    $stageName = $stageNames[$new_stage] ?? "غير محدد";

    // إعداد رسالة النجاح
    $message = "تم تحديث مرحلة الاعتماد إلى: {$stageName}";

    // إضافة معلومات عن أوامر العمل المحدثة إذا كانت المرحلة "مصروف"
    if ($new_stage === 'disbursed' && isset($updatedCount) && $updatedCount > 0) {
        $entityPart = isset($finishedEntityId) && $finishedEntityId ? " والجهة الحالية إلى 'منتهى'" : "";
        $message .= "\nتم تحديث حالة {$updatedCount} أمر عمل إلى 'مكتمل'{$entityPart}";
    }
    // إضافة معلومات عن أوامر العمل المرتجعة إذا تم الإرجاع من "مصروف"
    elseif (isset($revertedCount) && $revertedCount > 0) {
        $message .= "\nتم إرجاع حالة {$revertedCount} أمر عمل إلى 'نشط'";
    }

    $response = [
        'success' => true,
        'message' => $message,
        'new_stage' => $new_stage,
        'stage_name' => $stageName,
        'updated_work_orders_count' => $updatedCount ?? 0,
        'reverted_work_orders_count' => $revertedCount ?? 0
    ];

    error_log("Sending success response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    // إلغاء المعاملة في حالة الخطأ
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Error updating approval stage: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث مرحلة الاعتماد: ' . $e->getMessage()
    ]);
}
?>
