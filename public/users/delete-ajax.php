<?php
/**
 * حذف مستخدم - AJAX
 * Delete User - AJAX
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit();
}

// التحقق من صحة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

try {
    $db = getDB();
    
    // استلام البيانات
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = (int)($input['user_id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('معرف المستخدم غير صحيح');
    }
    
    // منع المستخدم من حذف نفسه
    if ($userId == $_SESSION['user_id']) {
        throw new Exception('لا يمكنك حذف حسابك الخاص');
    }
    
    // التحقق من وجود المستخدم
    $checkUser = $db->prepare("SELECT username FROM users WHERE id = ?");
    $checkUser->execute([$userId]);
    $user = $checkUser->fetch();
    
    if (!$user) {
        throw new Exception('المستخدم غير موجود');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // حذف أدوار المستخدم أولاً
        $deleteRoles = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $deleteRoles->execute([$userId]);
        
        // حذف المستخدم
        $deleteUser = $db->prepare("DELETE FROM users WHERE id = ?");
        $deleteUser->execute([$userId]);
        
        // تأكيد المعاملة
        $db->commit();
        
        // تسجيل العملية
        logActivity('حذف مستخدم', "تم حذف المستخدم: {$user['username']} (ID: $userId)");
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم حذف المستخدم بنجاح'
        ]);
        
    } catch (Exception $e) {
        // إلغاء المعاملة في حالة الخطأ
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
