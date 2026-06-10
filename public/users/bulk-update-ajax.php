<?php
/**
 * تحديث حالة المستخدمين بشكل جماعي
 * Bulk Update Users Status
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة المستخدمين']);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_ids']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

$userIds = $input['user_ids'];
$status = $input['status'];

// التحقق من صحة البيانات
if (!is_array($userIds) || empty($userIds)) {
    echo json_encode(['success' => false, 'message' => 'لم يتم تحديد أي مستخدمين']);
    exit;
}

if (!in_array($status, ['active', 'inactive', 'suspended'])) {
    echo json_encode(['success' => false, 'message' => 'حالة غير صحيحة']);
    exit;
}

// التأكد من أن المستخدم الحالي ليس ضمن القائمة
if (in_array($_SESSION['user_id'], $userIds)) {
    echo json_encode(['success' => false, 'message' => 'لا يمكنك تعديل حالة حسابك الخاص']);
    exit;
}

try {
    $db = getDB();
    
    // بدء المعاملة
    $db->beginTransaction();
    
    // تحديث حالة المستخدمين
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
    
    $params = array_merge([$status], $userIds);
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        $affectedRows = $stmt->rowCount();
        
        // تسجيل العملية
        $statusText = match($status) {
            'active' => 'تفعيل',
            'inactive' => 'إلغاء تفعيل',
            'suspended' => 'تعليق',
            default => 'تحديث'
        };
        
        logActivity($_SESSION['user_id'], 'bulk_update_users', 
            "تم {$statusText} {$affectedRows} مستخدم بشكل جماعي");
        
        // تأكيد المعاملة
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "تم تحديث حالة {$affectedRows} مستخدم بنجاح",
            'affected_rows' => $affectedRows
        ]);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة المستخدمين']);
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    error_log("Error in bulk update users: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ]);
}
?>
