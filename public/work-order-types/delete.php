<?php

declare(strict_types=1);

/**
 * حذف نوع أمر العمل
 * Delete Work Order Type
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

try {
    // قراءة البيانات JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id']) || !is_numeric($input['id'])) {
        throw new Exception('معرف نوع أمر العمل غير صحيح');
    }
    
    $typeId = (int) $input['id'];
    $db = getDB();
    
    // التحقق من وجود نوع أمر العمل
    $checkStmt = $db->prepare("SELECT type_code FROM work_order_types WHERE id = ?");
    $checkStmt->execute([$typeId]);
    $workOrderType = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$workOrderType) {
        throw new Exception('نوع أمر العمل غير موجود');
    }
    
    // التحقق من عدم استخدام نوع أمر العمل في أوامر عمل أخرى
    $usageCheckStmt = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE work_order_type_id = ?");
    $usageCheckStmt->execute([$typeId]);
    $usageCount = $usageCheckStmt->fetchColumn();
    
    if ($usageCount > 0) {
        throw new Exception("لا يمكن حذف نوع أمر العمل لأنه مستخدم في {$usageCount} أمر عمل");
    }
    
    // حذف نوع أمر العمل
    $deleteStmt = $db->prepare("DELETE FROM work_order_types WHERE id = ?");
    
    if ($deleteStmt->execute([$typeId])) {
        echo json_encode([
            'success' => true,
            'message' => "تم حذف نوع أمر العمل '{$workOrderType['type_code']}' بنجاح"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('فشل في حذف نوع أمر العمل');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
