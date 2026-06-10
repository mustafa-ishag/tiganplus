<?php
/**
 * إعادة تصميم صلاحيات المستخلصات - النسخة الثانية
 * Redesign Extracts Permissions - Version 2
 * 
 * يقوم بـ:
 * 1. حذف جميع الصلاحيات القديمة والمكررة
 * 2. إنشاء صلاحيات جديدة موحدة بنفس نمط أوامر العمل
 * 3. تعيين الصلاحيات للأدوار
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "=== إعادة تصميم صلاحيات المستخلصات ===\n\n";
    
    // ============================================
    // الخطوة 1: حذف الصلاحيات القديمة والمكررة
    // ============================================
    echo "الخطوة 1: حذف الصلاحيات القديمة والمكررة...\n";
    
    $oldPermissions = [
        'view_extracts',
        'create_extracts',
        'edit_extracts',
        'delete_extracts',
        'approve_extracts',
        'extracts_view',
        'extracts_view_all',
        'extracts_create',
        'extracts_edit',
        'extracts_delete',
        'extracts_approve',
        'extracts_export'
    ];
    
    foreach ($oldPermissions as $permName) {
        // جلب معرف الصلاحية
        $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$permName]);
        $permission = $stmt->fetch();
        
        if ($permission) {
            $permId = $permission['id'];
            
            // حذف من role_permissions
            $db->prepare("DELETE FROM role_permissions WHERE permission_id = ?")->execute([$permId]);
            
            // حذف من user_permissions
            $db->prepare("DELETE FROM user_permissions WHERE permission_id = ?")->execute([$permId]);
            
            // حذف الصلاحية نفسها
            $db->prepare("DELETE FROM permissions WHERE id = ?")->execute([$permId]);
            
            echo "✅ تم حذف: $permName\n";
        }
    }
    
    echo "\n";
    
    // ============================================
    // الخطوة 2: إنشاء صلاحيات جديدة موحدة
    // ============================================
    echo "الخطوة 2: إنشاء صلاحيات جديدة موحدة...\n";
    
    $newPermissions = [
        // صلاحيات أساسية
        ['extracts_view', 'عرض المستخلصات', 'عرض قائمة المستخلصات', 'extracts', 'basic'],
        ['extracts_view_all', 'عرض جميع المستخلصات', 'عرض المستخلصات من جميع الفروع', 'extracts', 'basic'],
        ['extracts_create', 'إنشاء مستخلص', 'إنشاء مستخلص جديد', 'extracts', 'basic'],
        ['extracts_edit', 'تعديل المستخلصات', 'تعديل بيانات المستخلصات', 'extracts', 'basic'],
        ['extracts_delete', 'حذف المستخلصات', 'حذف المستخلصات', 'extracts', 'basic'],
        
        // صلاحيات الاعتماد والمراجعة
        ['extracts_approve', 'اعتماد المستخلصات', 'اعتماد ومراجعة المستخلصات', 'extracts', 'approval'],
        ['extracts_update_fields', 'تحديث حقول المستخلصات', 'تحديث الحقول المختلفة', 'extracts', 'approval'],
        
        // صلاحيات الاستيراد والتصدير
        ['extracts_export', 'تصدير المستخلصات', 'تصدير بيانات المستخلصات', 'extracts', 'import_export'],
        ['extracts_import', 'استيراد المستخلصات', 'استيراد بيانات المستخلصات', 'extracts', 'import_export'],
        
        // صلاحيات إضافية
        ['extracts_print', 'طباعة المستخلصات', 'طباعة بيانات المستخلصات', 'extracts', 'additional'],
        ['extracts_view_details', 'عرض تفاصيل المستخلص', 'عرض التفاصيل الكاملة', 'extracts', 'additional'],
        ['extracts_attachments', 'إدارة مرفقات المستخلصات', 'رفع وإدارة المرفقات', 'extracts', 'additional'],
        ['extracts_reports', 'عرض تقارير المستخلصات', 'عرض التقارير والإحصائيات', 'extracts', 'reports'],
    ];
    
    $insertStmt = $db->prepare("
        INSERT INTO permissions (name, display_name, description, module, category) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($newPermissions as $perm) {
        $insertStmt->execute($perm);
        echo "✅ تم إنشاء: {$perm[0]}\n";
    }
    
    echo "\n✅ تم إنشاء " . count($newPermissions) . " صلاحية جديدة\n\n";
    
    // ============================================
    // الخطوة 3: تعيين الصلاحيات للأدوار
    // ============================================
    echo "الخطوة 3: تعيين الصلاحيات للأدوار...\n";
    
    // جلب معرفات الصلاحيات الجديدة
    $stmt = $db->query("SELECT id, name FROM permissions WHERE module = 'extracts'");
    $permissionsMap = [];
    foreach ($stmt->fetchAll() as $perm) {
        $permissionsMap[$perm['name']] = $perm['id'];
    }
    
    // تعريف الصلاحيات لكل دور
    $rolePermissions = [
        'super_admin' => array_values($permissionsMap),
        'admin_manager' => array_values($permissionsMap),
        'admin' => array_values($permissionsMap),
        'department_manager' => array_values($permissionsMap),
        'branch_manager' => [
            $permissionsMap['extracts_view'],
            $permissionsMap['extracts_create'],
            $permissionsMap['extracts_edit'],
            $permissionsMap['extracts_delete'],
            $permissionsMap['extracts_approve'],
            $permissionsMap['extracts_export'],
            $permissionsMap['extracts_import'],
            $permissionsMap['extracts_view_details'],
        ],
        'finance_employee' => [
            $permissionsMap['extracts_view'],
            $permissionsMap['extracts_export'],
            $permissionsMap['extracts_import'],
            $permissionsMap['extracts_reports'],
        ],
        'technical_support' => [
            $permissionsMap['extracts_view'],
            $permissionsMap['extracts_reports'],
        ],
        'construction_employee' => [
            $permissionsMap['extracts_view'],
        ],
        'regular_user' => [
            $permissionsMap['extracts_view'],
        ],
    ];
    
    // حذف الصلاحيات القديمة للأدوار
    $db->exec("DELETE FROM role_permissions WHERE permission_id IN (
        SELECT id FROM permissions WHERE module = 'extracts'
    )");
    
    // إضافة الصلاحيات الجديدة
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
    $insertRolePermStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    
    foreach ($rolePermissions as $roleName => $permIds) {
        $roleStmt->execute([$roleName]);
        $role = $roleStmt->fetch();
        
        if ($role) {
            foreach ($permIds as $permId) {
                $insertRolePermStmt->execute([$role['id'], $permId]);
            }
            echo "✅ تم تعيين الصلاحيات للدور: $roleName\n";
        }
    }
    
    echo "\n✅ اكتمل التحديث بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

