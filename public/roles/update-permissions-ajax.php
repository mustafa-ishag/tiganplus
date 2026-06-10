<?php
/**
 * AJAX - تحديث صلاحيات الدور
 * AJAX - Update Role Permissions
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
if (!hasPermission('manage_roles')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة الأدوار']);
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
    $permissions = $input['permissions'] ?? [];
    
    // التحقق من معرف الدور
    if (!$roleId) {
        echo json_encode(['success' => false, 'message' => 'معرف الدور غير صحيح']);
        exit();
    }
    
    // التحقق من وجود الدور
    $stmt = $db->prepare("SELECT id, display_name FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'الدور غير موجود']);
        exit();
    }
    
    // التحقق من صحة الصلاحيات
    if (!is_array($permissions)) {
        echo json_encode(['success' => false, 'message' => 'بيانات الصلاحيات غير صحيحة']);
        exit();
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // حذف جميع الصلاحيات الحالية للدور
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        
        // إضافة الصلاحيات الجديدة
        if (!empty($permissions)) {
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
            
            foreach ($permissions as $permissionId) {
                $permissionId = intval($permissionId);
                
                // التحقق من وجود الصلاحية
                $checkStmt = $db->prepare("SELECT id FROM permissions WHERE id = ?");
                $checkStmt->execute([$permissionId]);
                
                if ($checkStmt->fetch()) {
                    $stmt->execute([$roleId, $permissionId]);
                }
            }
        }
        
        // تحديث تاريخ آخر تعديل للدور
        $stmt = $db->prepare("UPDATE roles SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$roleId]);
        
        // تسجيل النشاط
        logActivity(
            $_SESSION['user_id'], 
            'update_role_permissions', 
            "تحديث صلاحيات الدور: {$role['display_name']} - تم تخصيص " . count($permissions) . " صلاحية"
        );
        
        // تأكيد المعاملة
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم تحديث صلاحيات الدور بنجاح',
            'permissions_count' => count($permissions)
        ]);
        
    } catch (Exception $e) {
        // التراجع عن المعاملة
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء تحديث الصلاحيات: ' . $e->getMessage()
    ]);
}
?>
