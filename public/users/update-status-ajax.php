<?php
/**
 * تحديث حالة المستخدم
 * Update User Status
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

if (!$input || !isset($input['user_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

$userId = (int)$input['user_id'];
$status = $input['status'];

// التحقق من صحة البيانات
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف المستخدم غير صحيح']);
    exit;
}

if (!in_array($status, ['active', 'inactive', 'suspended'])) {
    echo json_encode(['success' => false, 'message' => 'حالة غير صحيحة']);
    exit;
}

// التأكد من أن المستخدم لا يحاول تعديل حالة حسابه الخاص
if ($userId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'لا يمكنك تعديل حالة حسابك الخاص']);
    exit;
}

try {
    $db = getDB();
    
    // التحقق من وجود المستخدم
    $stmt = $db->prepare("SELECT username, full_name, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
        exit;
    }
    
    // تحديث حالة المستخدم
    $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$status, $userId]);
    
    if ($result) {
        // تسجيل العملية
        $statusText = match($status) {
            'active' => 'تفعيل',
            'inactive' => 'إلغاء تفعيل',
            'suspended' => 'تعليق',
            default => 'تحديث حالة'
        };
        
        logActivity($_SESSION['user_id'], 'update_user_status', 
            "تم {$statusText} المستخدم: {$user['username']} ({$user['full_name']})");
        
        echo json_encode([
            'success' => true,
            'message' => "تم تحديث حالة المستخدم بنجاح",
            'old_status' => $user['status'],
            'new_status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة المستخدم']);
    }
    
} catch (Exception $e) {
    error_log("Error updating user status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ]);
}
?>
