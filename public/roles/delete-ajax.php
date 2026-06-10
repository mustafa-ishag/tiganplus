<?php
/**
 * AJAX - حذف الدور
 * AJAX - Delete Role
 */

session_start();

require_once '../../config/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مسموح بالوصول']);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('delete_roles')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لحذف الأدوار']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

try {
    $db = getDB();
    
    // استلام البيانات
    $input = json_decode(file_get_contents('php://input'), true);
    $roleId = intval($input['role_id'] ?? 0);
    
    // التحقق من معرف الدور
    if (!$roleId) {
        echo json_encode(['success' => false, 'message' => 'معرف الدور غير صحيح']);
        exit();
    }
    
    // التحقق من وجود الدور
    $stmt = $db->prepare("SELECT id, name, display_name FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'الدور غير موجود']);
        exit();
    }
    
    // التحقق من عدم وجود مستخدمين مرتبطين بالدور
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $usersCount = $stmt->fetchColumn();
    
    if ($usersCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "لا يمكن حذف الدور لأنه مخصص لـ {$usersCount} مستخدم. يرجى إلغاء تخصيص الدور أولاً."
        ]);
        exit();
    }
    
    // التحقق من عدم حذف الأدوار الأساسية
    $protectedRoles = ['super_admin', 'admin', 'user'];
    if (in_array($role['name'], $protectedRoles)) {
        echo json_encode([
            'success' => false, 
            'message' => 'لا يمكن حذف هذا الدور لأنه من الأدوار الأساسية في النظام'
        ]);
        exit();
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // حذف صلاحيات الدور
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        
        // حذف الدور
        $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$roleId]);
        
        // تسجيل النشاط
        logActivity(
            $_SESSION['user_id'], 
            'delete_role', 
            "حذف الدور: {$role['display_name']} ({$role['name']})"
        );
        
        // تأكيد المعاملة
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم حذف الدور بنجاح'
        ]);
        
    } catch (Exception $e) {
        // التراجع عن المعاملة
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء حذف الدور: ' . $e->getMessage()
    ]);
}
?>
