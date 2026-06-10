<?php
/**
 * تعيين الصلاحيات للأدوار
 * Assign Permissions to Roles
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║         تعيين صلاحيات المستخلصات للأدوار                      ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // الحصول على جميع الأدوار
    $roles = $db->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
    
    // تعريف الصلاحيات لكل دور - جميع الصلاحيات
    $allExtractsPermissions = [
        'extracts_partial_view', 'extracts_partial_view_all', 'extracts_partial_create',
        'extracts_partial_edit', 'extracts_partial_delete', 'extracts_partial_approve',
        'extracts_partial_export', 'extracts_partial_import',
        'extracts_final_regular_view', 'extracts_final_regular_view_all', 'extracts_final_regular_create',
        'extracts_final_regular_edit', 'extracts_final_regular_delete', 'extracts_final_regular_approve',
        'extracts_final_regular_export',
        'extracts_final_for_partial_view', 'extracts_final_for_partial_view_all', 'extracts_final_for_partial_create',
        'extracts_final_for_partial_edit', 'extracts_final_for_partial_delete', 'extracts_final_for_partial_approve',
        'extracts_final_for_partial_export',
        'extracts_reports', 'extracts_sap_sync'
    ];

    // تعريف الصلاحيات لكل دور
    $rolePermissions = [
        // مدير النظام - جميع الصلاحيات
        'super_admin' => $allExtractsPermissions,
        'admin_manager' => $allExtractsPermissions,
        'admin' => $allExtractsPermissions,

        // مدير الإدارة - جميع الصلاحيات
        'department_manager' => $allExtractsPermissions,

        // مدير الفرع - إدارة المستخلصات في فرعه
        'branch_manager' => [
            'extracts_partial_view', 'extracts_partial_create', 'extracts_partial_edit',
            'extracts_partial_export', 'extracts_partial_import',
            'extracts_final_regular_view', 'extracts_final_regular_create', 'extracts_final_regular_edit',
            'extracts_final_regular_export',
            'extracts_final_for_partial_view', 'extracts_final_for_partial_create', 'extracts_final_for_partial_edit',
            'extracts_final_for_partial_export',
            'extracts_reports'
        ],

        // الدعم الفني - عرض فقط
        'technical_support' => [
            'extracts_partial_view', 'extracts_final_regular_view', 'extracts_final_for_partial_view',
            'extracts_reports'
        ],

        // موظف البناء - عرض المستخلصات
        'construction_employee' => [
            'extracts_partial_view', 'extracts_final_regular_view', 'extracts_final_for_partial_view'
        ],

        // موظف المالية - عرض وتصدير المستخلصات
        'finance_employee' => [
            'extracts_partial_view', 'extracts_partial_export', 'extracts_partial_import',
            'extracts_final_regular_view', 'extracts_final_regular_export',
            'extracts_final_for_partial_view', 'extracts_final_for_partial_export',
            'extracts_reports'
        ],

        // مستخدم عادي - عرض فقط
        'regular_user' => [
            'extracts_partial_view', 'extracts_final_regular_view', 'extracts_final_for_partial_view'
        ],
    ];
    
    // تعيين الصلاحيات
    foreach ($roles as $role) {
        $roleName = $role['name'];

        // البحث عن الدور في قائمة الصلاحيات
        $permissions = $rolePermissions[$roleName] ?? null;

        if ($permissions) {
            // حذف الصلاحيات الحالية
            $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$role['id']]);

            // إضافة الصلاحيات الجديدة
            $stmt = $db->prepare("
                INSERT INTO role_permissions (role_id, permission_id, created_at)
                SELECT ?, id, NOW() FROM permissions WHERE name = ?
            ");

            foreach ($permissions as $permName) {
                $stmt->execute([$role['id'], $permName]);
            }

            echo "✅ تم تعيين " . count($permissions) . " صلاحية للدور: {$role['name']}\n";
        } else {
            echo "⚠️  لم يتم العثور على تعريف صلاحيات للدور: {$role['name']}\n";
        }
    }
    
    echo "\n✅ تم تعيين الصلاحيات للأدوار بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

