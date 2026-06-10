<?php
/**
 * تحديث تواريخ الإنجاز للمستخلص النهائي للجزئي
 * Update Completion Dates for Final For Partial Extract
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
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();
    
    // التحقق من البيانات المطلوبة
    if (!isset($_POST['extract_id']) || !is_numeric($_POST['extract_id'])) {
        throw new InvalidArgumentException('معرف المستخلص مطلوب');
    }
    
    if (!isset($_POST['completion_dates']) || !is_array($_POST['completion_dates'])) {
        throw new InvalidArgumentException('تواريخ الإنجاز مطلوبة');
    }
    
    $extractId = (int) $_POST['extract_id'];
    $completionDates = $_POST['completion_dates'];
    $userId = $_SESSION['user_id'];
    
    // التحقق من وجود المستخلص وإمكانية تعديله
    $stmt = $db->prepare("
        SELECT ffpe.*, b.name as branch_name
        FROM final_for_partial_extracts ffpe
        LEFT JOIN branches b ON ffpe.branch_id = b.id
        WHERE ffpe.id = ? AND (ffpe.approval_stage IS NULL OR ffpe.approval_stage = 'draft')
    ");
    $stmt->execute([$extractId]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        throw new InvalidArgumentException('المستخلص غير موجود أو لا يمكن تعديله');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        $updatedCount = 0;
        $workOrderUpdates = [];
        
        foreach ($completionDates as $workOrderId => $newDate) {
            if (empty($newDate)) {
                continue;
            }
            
            // التحقق من صحة التاريخ
            $dateTime = DateTime::createFromFormat('Y-m-d', $newDate);
            if (!$dateTime || $dateTime->format('Y-m-d') !== $newDate) {
                throw new InvalidArgumentException("تاريخ غير صحيح: {$newDate}");
            }
            
            // تحديث تاريخ الإنجاز في المستخلص
            $updateExtractQuery = "
                UPDATE final_for_partial_extract_work_orders 
                SET completion_date = ?
                WHERE final_for_partial_extract_id = ? AND work_order_id = ?
            ";
            $stmt = $db->prepare($updateExtractQuery);
            $stmt->execute([$newDate, $extractId, $workOrderId]);
            
            if ($stmt->rowCount() > 0) {
                $updatedCount++;
                
                // تحديث تاريخ الاستلام في أمر العمل إذا كان مختلفاً
                $checkWorkOrderQuery = "SELECT receipt_date FROM work_orders WHERE id = ?";
                $checkStmt = $db->prepare($checkWorkOrderQuery);
                $checkStmt->execute([$workOrderId]);
                $currentReceiptDate = $checkStmt->fetchColumn();
                
                if ($currentReceiptDate !== $newDate) {
                    $updateWorkOrderQuery = "
                        UPDATE work_orders 
                        SET receipt_date = ?
                        WHERE id = ?
                    ";
                    $updateStmt = $db->prepare($updateWorkOrderQuery);
                    $updateStmt->execute([$newDate, $workOrderId]);
                    
                    $workOrderUpdates[] = [
                        'work_order_id' => $workOrderId,
                        'old_date' => $currentReceiptDate,
                        'new_date' => $newDate
                    ];
                }
            }
        }
        
        if ($updatedCount === 0) {
            throw new Exception('لم يتم تحديث أي تواريخ');
        }
        
        // تحديث تاريخ التعديل للمستخلص
        $updateExtractTimestampQuery = "UPDATE final_for_partial_extracts SET updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($updateExtractTimestampQuery);
        $stmt->execute([$extractId]);
        
        // إضافة نشاط (اختياري)
        try {
            $activity_description = "تم تحديث تواريخ الإنجاز لـ {$updatedCount} أمر عمل";

            $insertActivityQuery = "
                INSERT INTO final_for_partial_extract_activities (
                    final_for_partial_extract_id, activity_type, description, performed_by, performed_at
                ) VALUES (?, ?, ?, ?, NOW())
            ";

            $insertActivityStmt = $db->prepare($insertActivityQuery);
            $insertActivityStmt->execute([
                $extractId,
                'completion_dates_updated',
                $activity_description,
                $userId
            ]);
        } catch (Exception $activityError) {
            // تجاهل خطأ جدول الأنشطة إذا لم يكن موجوداً
            error_log("Activity log error: " . $activityError->getMessage());
        }
        
        // تأكيد المعاملة
        $db->commit();
        
        // تسجيل العملية في السجل
        error_log("Updated completion dates for final for partial extract {$extract['extract_number']} (ID: {$extractId}) by user {$userId}");
        error_log("- Updated {$updatedCount} completion dates");
        if (!empty($workOrderUpdates)) {
            error_log("- Updated work order receipt dates: " . json_encode($workOrderUpdates));
        }
        
        echo json_encode([
            'success' => true,
            'message' => "تم تحديث {$updatedCount} تاريخ إنجاز بنجاح",
            'extract_number' => $extract['extract_number'],
            'updated_count' => $updatedCount,
            'work_order_updates' => $workOrderUpdates
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // التراجع عن المعاملة
        $db->rollBack();
        throw $e;
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
