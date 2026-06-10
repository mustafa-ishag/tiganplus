<?php
/**
 * AJAX - إنشاء دور جديد
 * AJAX - Create New Role
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
if (!hasPermission('create_roles')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإنشاء الأدوار']);
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
    $name = trim($_POST['name'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $level = intval($_POST['level'] ?? 10);
    $status = $_POST['status'] ?? 'active';
    $permissions = json_decode($_POST['permissions'] ?? '[]', true);
    
    // التحقق من البيانات المطلوبة
    if (empty($name) || empty($display_name)) {
        echo json_encode(['success' => false, 'message' => 'يرجى ملء جميع الحقول المطلوبة']);
        exit();
    }
    
    // التحقق من صحة اسم الدور
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
        echo json_encode(['success' => false, 'message' => 'اسم الدور يجب أن يكون باللغة الإنجليزية وبدون مسافات']);
        exit();
    }
    
    // التحقق من عدم تكرار اسم الدور
    $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'اسم الدور موجود بالفعل']);
        exit();
    }
    
    // التحقق من عدم تكرار الاسم المعروض
    $stmt = $db->prepare("SELECT id FROM roles WHERE display_name = ?");
    $stmt->execute([$display_name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'الاسم المعروض موجود بالفعل']);
        exit();
    }
    
    // التحقق من صحة المستوى
    if ($level < 1 || $level > 100) {
        echo json_encode(['success' => false, 'message' => 'مستوى الدور يجب أن يكون بين 1 و 100']);
        exit();
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // إدراج الدور الجديد
        $stmt = $db->prepare("
            INSERT INTO roles (name, display_name, description, level, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$name, $display_name, $description, $level, $status]);
        $roleId = $db->lastInsertId();
        
        // إضافة الصلاحيات للدور
        if (!empty($permissions) && is_array($permissions)) {
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
            
            foreach ($permissions as $permissionId) {
                // التحقق من وجود الصلاحية
                $checkStmt = $db->prepare("SELECT id FROM permissions WHERE id = ?");
                $checkStmt->execute([$permissionId]);
                
                if ($checkStmt->fetch()) {
                    $stmt->execute([$roleId, $permissionId]);
                }
            }
        }
        
        // تسجيل النشاط
        logActivity(
            $_SESSION['user_id'], 
            'create_role', 
            "إنشاء دور جديد: {$display_name} ({$name}) مع " . count($permissions) . " صلاحية"
        );
        
        // تأكيد المعاملة
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إنشاء الدور بنجاح',
            'role_id' => $roleId
        ]);
        
    } catch (Exception $e) {
        // التراجع عن المعاملة
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'حدث خطأ أثناء إنشاء الدور: ' . $e->getMessage()
    ]);
}
?>
