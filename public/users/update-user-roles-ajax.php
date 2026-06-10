<?php
/**
 * تحديث أدوار المستخدم - AJAX
 * Update User Roles - AJAX
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإدارة أدوار المستخدمين']);
    exit();
}

// التحقق من صحة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

$inTransaction = false;

try {
    $db = getDB();

    // استلام البيانات
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $selectedRoles = isset($_POST['roles']) ? $_POST['roles'] : [];

    // التحقق من صحة معرف المستخدم
    if (!$userId) {
        throw new Exception('معرف المستخدم غير صحيح');
    }

    // التحقق من وجود المستخدم
    $stmt = $db->prepare("SELECT id, username, full_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('المستخدم غير موجود');
    }

    // منع المستخدم من تعديل أدواره الخاصة
    if ($_SESSION['user_id'] == $userId) {
        throw new Exception('لا يمكنك تعديل أدوارك الخاصة');
    }

    // بدء المعاملة
    $db->beginTransaction();
    $inTransaction = true;
    
    // تحديد الدور الأساسي (أول دور محدد أو الدور الحالي)
    $primaryRoleId = null;
    if (!empty($selectedRoles)) {
        $firstRole = (int)$selectedRoles[0];
        if ($firstRole > 0) {
            // التحقق من صحة الدور
            $checkStmt = $db->prepare("SELECT id FROM roles WHERE id = ? AND status = 'active'");
            $checkStmt->execute([$firstRole]);
            if ($checkStmt->fetch()) {
                $primaryRoleId = $firstRole;
            }
        }
    }
    
    // تحديث الدور الأساسي في جدول users إذا تم تحديد دور جديد
    if ($primaryRoleId) {
        $updateRoleStmt = $db->prepare("UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?");
        $updateRoleStmt->execute([$primaryRoleId, $userId]);
    } else {
        // إذا لم يتم تحديد أي دور، استخدم دور افتراضي
        $updateRoleStmt = $db->prepare("UPDATE users SET role_id = 1, updated_at = NOW() WHERE id = ?");
        $updateRoleStmt->execute([$userId]);
    }
    
    // حذف الأدوار الحالية
    $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // إضافة الأدوار الجديدة
    $addedRoles = [];
    if (!empty($selectedRoles)) {
        $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by, assigned_at) VALUES (?, ?, ?, NOW())");
        
        foreach ($selectedRoles as $roleId) {
            $roleId = (int)$roleId;
            if ($roleId > 0) {
                // التحقق من وجود الدور
                $checkStmt = $db->prepare("SELECT id, display_name FROM roles WHERE id = ? AND status = 'active'");
                $checkStmt->execute([$roleId]);
                $role = $checkStmt->fetch();
                
                if ($role) {
                    $stmt->execute([$userId, $roleId, $_SESSION['user_id']]);
                    $addedRoles[] = $role['display_name'];
                }
            }
        }
    }
    
    // تسجيل العملية
    $rolesText = !empty($addedRoles) ? implode(', ', $addedRoles) : 'لا توجد أدوار';
    logActivity(
        $_SESSION['user_id'], 
        'update_user_roles', 
        "تم تحديث أدوار المستخدم: {$user['username']} - الأدوار الجديدة: $rolesText"
    );
    
    // تأكيد المعاملة
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث أدوار المستخدم بنجاح',
        'user_id' => $userId,
        'roles_count' => count($addedRoles),
        'roles' => $addedRoles
    ]);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if ($inTransaction && isset($db)) {
        try {
            $db->rollBack();
        } catch (Exception $rollbackError) {
            error_log("خطأ في التراجع عن المعاملة: " . $rollbackError->getMessage());
        }
    }

    error_log("خطأ في تحديث أدوار المستخدم: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
