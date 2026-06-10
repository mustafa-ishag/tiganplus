<?php
/**
 * حذف المستخلص النهائي العادي
 * Delete Final Regular Extract
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
if (!hasPermission('extracts_delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لحذف المستخلصات']);
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
    
    $extractId = (int) $_POST['extract_id'];
    $userId = $_SESSION['user_id'];
    
    // جلب بيانات المستخلص
    $query = "
        SELECT fre.*, b.name as branch_name
        FROM final_regular_extracts fre
        LEFT JOIN branches b ON fre.branch_id = b.id
        WHERE fre.id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$extractId]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        throw new InvalidArgumentException('المستخلص غير موجود');
    }
    
    // التحقق من إمكانية الحذف (فقط المسودات)
    // يمكن حذف المستخلص إذا كان في مرحلة المسودة (null أو 'draft')
    if ($extract['approval_stage'] !== null && $extract['approval_stage'] !== 'draft') {
        throw new InvalidArgumentException('لا يمكن حذف المستخلص بعد تقديمه للاعتماد');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // حذف أوامر العمل المرتبطة
        $deleteWorkOrdersQuery = "DELETE FROM final_regular_extract_work_orders WHERE final_regular_extract_id = ?";
        $stmt = $db->prepare($deleteWorkOrdersQuery);
        $stmt->execute([$extractId]);
        $deletedWorkOrders = $stmt->rowCount();
        
        // حذف المرفقات المرتبطة (إن وجدت)
        $deleteAttachmentsQuery = "DELETE FROM final_regular_extract_attachments WHERE final_regular_extract_id = ?";
        $stmt = $db->prepare($deleteAttachmentsQuery);
        $stmt->execute([$extractId]);
        $deletedAttachments = $stmt->rowCount();
        
        // حذف الأنشطة المرتبطة (إن وجدت)
        $deleteActivitiesQuery = "DELETE FROM final_regular_extract_activities WHERE final_regular_extract_id = ?";
        $stmt = $db->prepare($deleteActivitiesQuery);
        $stmt->execute([$extractId]);
        $deletedActivities = $stmt->rowCount();
        
        // حذف المستخلص نفسه
        $deleteExtractQuery = "DELETE FROM final_regular_extracts WHERE id = ?";
        $stmt = $db->prepare($deleteExtractQuery);
        $stmt->execute([$extractId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('فشل في حذف المستخلص');
        }
        
        // تأكيد المعاملة
        $db->commit();
        
        // تسجيل العملية في السجل
        error_log("Deleted final regular extract {$extract['extract_number']} (ID: {$extractId}) by user {$userId}");
        error_log("- Deleted {$deletedWorkOrders} work orders");
        error_log("- Deleted {$deletedAttachments} attachments");
        error_log("- Deleted {$deletedActivities} activities");
        
        echo json_encode([
            'success' => true,
            'message' => "تم حذف المستخلص النهائي العادي '{$extract['extract_number']}' بنجاح",
            'extract_number' => $extract['extract_number'],
            'deleted_items' => [
                'work_orders' => $deletedWorkOrders,
                'attachments' => $deletedAttachments,
                'activities' => $deletedActivities
            ]
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
