<?php
/**
 * تحديث مرحلة الاعتماد للمستخلص النهائي للجزئي
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
if (!hasPermission('extracts_approve')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لاعتماد المستخلصات']);
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
    if (!isset($_POST['extract_id']) || !isset($_POST['approval_stage'])) {
        throw new Exception('البيانات المطلوبة غير مكتملة');
    }
    
    $extract_id = (int) $_POST['extract_id'];
    $approval_stage = $_POST['approval_stage'];
    
    // التحقق من صحة مرحلة الاعتماد من جدول approval_stages
    $stmt = $db->prepare("SELECT stage_key, stage_name FROM approval_stages WHERE stage_key = ? AND is_active = 1");
    $stmt->execute([$approval_stage]);
    $stage_info = $stmt->fetch();

    if (!$stage_info) {
        throw new Exception('مرحلة الاعتماد غير صحيحة أو غير نشطة');
    }
    
    // التحقق من وجود المستخلص
    $stmt = $db->prepare("SELECT id, approval_stage FROM final_for_partial_extracts WHERE id = ?");
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        throw new Exception('المستخلص غير موجود');
    }
    
    // بدء المعاملة
    $db->beginTransaction();

    try {
        // حفظ المرحلة السابقة للمقارنة
        $previousStage = $extract['approval_stage'];

        // تحديث مرحلة الاعتماد
        $stmt = $db->prepare("
            UPDATE final_for_partial_extracts
            SET approval_stage = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$approval_stage, $extract_id]);

        // إذا تم تحويل المرحلة إلى "مصروف"، تحديث حالة جميع أوامر العمل إلى "مكتمل"
        if ($approval_stage === 'disbursed') {
            // البحث عن معرف الجهة "منتهى"
            $stmt = $db->prepare("SELECT id FROM current_entities WHERE name = 'منتهى' LIMIT 1");
            $stmt->execute();
            $finishedEntity = $stmt->fetch();
            $finishedEntityId = $finishedEntity ? $finishedEntity['id'] : null;

            // جلب جميع أوامر العمل المرتبطة بهذا المستخلص النهائي للجزئية
            $stmt = $db->prepare("
                SELECT DISTINCT wo.id, wo.work_order_number, wo.status, wo.current_entity_id
                FROM final_for_partial_extract_work_orders ffpewo
                INNER JOIN work_orders wo ON ffpewo.work_order_id = wo.id
                WHERE ffpewo.final_for_partial_extract_id = ?
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
        elseif ($previousStage === 'disbursed' && $approval_stage !== 'disbursed') {
            // جلب جميع أوامر العمل المرتبطة بهذا المستخلص النهائي للجزئية
            $stmt = $db->prepare("
                SELECT DISTINCT wo.id, wo.work_order_number, wo.status
                FROM final_for_partial_extract_work_orders ffpewo
                INNER JOIN work_orders wo ON ffpewo.work_order_id = wo.id
                WHERE ffpewo.final_for_partial_extract_id = ?
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

    // تسجيل النشاط
    $activity_description = "تم تحديث مرحلة الاعتماد من '{$extract['approval_stage']}' إلى '{$approval_stage}'";
    
    // تسجيل النشاط (إذا كان الجدول موجود)
    try {
        $stmt = $db->prepare("
            INSERT INTO final_for_partial_extract_activities (
                final_for_partial_extract_id, user_id, activity_type, description, created_at
            ) VALUES (?, ?, 'approval_stage_updated', ?, NOW())
        ");

        $stmt->execute([$extract_id, $user_id, $activity_description]);
    } catch (Exception $e) {
        // تجاهل خطأ جدول الأنشطة إذا لم يكن موجود
        error_log("Activity logging failed: " . $e->getMessage());
    }
    
    // استخدام اسم المرحلة من قاعدة البيانات
    $stage_name = $stage_info['stage_name'];

    // إعداد رسالة النجاح
    $message = "تم تحديث مرحلة الاعتماد إلى: {$stage_name}";

    // إضافة معلومات عن أوامر العمل المحدثة إذا كانت المرحلة "مصروف"
    if ($approval_stage === 'disbursed' && isset($updatedCount) && $updatedCount > 0) {
        $entityPart = isset($finishedEntityId) && $finishedEntityId ? " والجهة الحالية إلى 'منتهى'" : "";
        $message .= "\nتم تحديث حالة {$updatedCount} أمر عمل إلى 'مكتمل'{$entityPart}";
    }
    // إضافة معلومات عن أوامر العمل المرتجعة إذا تم الإرجاع من "مصروف"
    elseif (isset($revertedCount) && $revertedCount > 0) {
        $message .= "\nتم إرجاع حالة {$revertedCount} أمر عمل إلى 'نشط'";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_stage' => $approval_stage,
        'stage_name' => $stage_name,
        'updated_work_orders_count' => $updatedCount ?? 0,
        'reverted_work_orders_count' => $revertedCount ?? 0
    ]);
    
} catch (Exception $e) {
    error_log("Final for Partial Extract Approval Stage Update Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
