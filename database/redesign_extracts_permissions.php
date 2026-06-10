<?php
/**
 * إعادة تصميم وتحديث صلاحيات المستخلصات
 * Redesign and Update Extracts Permissions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║         إعادة تصميم صلاحيات المستخلصات                        ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // 1. حذف الصلاحيات القديمة والمكررة
    echo "📋 الخطوة 1: حذف الصلاحيات القديمة والمكررة...\n";
    
    $oldPermissions = [
        'view_extracts',
        'add_extracts',
        'edit_extracts',
        'delete_extracts',
        'approve_extracts',
        'create_extracts'
    ];
    
    foreach ($oldPermissions as $perm) {
        $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$perm]);
        $permission = $stmt->fetch();
        
        if ($permission) {
            // حذف من role_permissions
            $db->prepare("DELETE FROM role_permissions WHERE permission_id = ?")->execute([$permission['id']]);
            // حذف من user_permissions
            $db->prepare("DELETE FROM user_permissions WHERE permission_id = ?")->execute([$permission['id']]);
            // حذف الصلاحية
            $db->prepare("DELETE FROM permissions WHERE id = ?")->execute([$permission['id']]);
            echo "   ✅ تم حذف: $perm\n";
        }
    }
    
    // 2. إنشاء الصلاحيات الجديدة
    echo "\n📋 الخطوة 2: إنشاء الصلاحيات الجديدة...\n";
    
    $newPermissions = [
        // المستخلصات الجزئية
        ['extracts_partial_view', 'عرض المستخلصات الجزئية', 'عرض قائمة المستخلصات الجزئية', 'extracts', 'partial'],
        ['extracts_partial_view_all', 'عرض جميع المستخلصات الجزئية', 'عرض المستخلصات الجزئية لجميع الفروع', 'extracts', 'partial'],
        ['extracts_partial_create', 'إنشاء مستخلص جزئي', 'إنشاء مستخلص جزئي جديد', 'extracts', 'partial'],
        ['extracts_partial_edit', 'تعديل المستخلصات الجزئية', 'تعديل بيانات المستخلصات الجزئية', 'extracts', 'partial'],
        ['extracts_partial_delete', 'حذف المستخلصات الجزئية', 'حذف المستخلصات الجزئية', 'extracts', 'partial'],
        ['extracts_partial_approve', 'اعتماد المستخلصات الجزئية', 'اعتماد المستخلصات الجزئية في مراحل الموافقة', 'extracts', 'partial'],
        ['extracts_partial_export', 'تصدير المستخلصات الجزئية', 'تصدير بيانات المستخلصات الجزئية', 'extracts', 'partial'],
        ['extracts_partial_import', 'استيراد المستخلصات الجزئية', 'استيراد بيانات المستخلصات الجزئية', 'extracts', 'partial'],
        
        // المستخلصات النهائية العادية
        ['extracts_final_regular_view', 'عرض المستخلصات النهائية العادية', 'عرض قائمة المستخلصات النهائية العادية', 'extracts', 'final_regular'],
        ['extracts_final_regular_view_all', 'عرض جميع المستخلصات النهائية العادية', 'عرض المستخلصات النهائية العادية لجميع الفروع', 'extracts', 'final_regular'],
        ['extracts_final_regular_create', 'إنشاء مستخلص نهائي عادي', 'إنشاء مستخلص نهائي عادي جديد', 'extracts', 'final_regular'],
        ['extracts_final_regular_edit', 'تعديل المستخلصات النهائية العادية', 'تعديل بيانات المستخلصات النهائية العادية', 'extracts', 'final_regular'],
        ['extracts_final_regular_delete', 'حذف المستخلصات النهائية العادية', 'حذف المستخلصات النهائية العادية', 'extracts', 'final_regular'],
        ['extracts_final_regular_approve', 'اعتماد المستخلصات النهائية العادية', 'اعتماد المستخلصات النهائية العادية', 'extracts', 'final_regular'],
        ['extracts_final_regular_export', 'تصدير المستخلصات النهائية العادية', 'تصدير بيانات المستخلصات النهائية العادية', 'extracts', 'final_regular'],
        
        // المستخلصات النهائية للجزئية
        ['extracts_final_for_partial_view', 'عرض المستخلصات النهائية للجزئية', 'عرض قائمة المستخلصات النهائية للجزئية', 'extracts', 'final_for_partial'],
        ['extracts_final_for_partial_view_all', 'عرض جميع المستخلصات النهائية للجزئية', 'عرض المستخلصات النهائية للجزئية لجميع الفروع', 'extracts', 'final_for_partial'],
        ['extracts_final_for_partial_create', 'إنشاء مستخلص نهائي للجزئية', 'إنشاء مستخلص نهائي للجزئية جديد', 'extracts', 'final_for_partial'],
        ['extracts_final_for_partial_edit', 'تعديل المستخلصات النهائية للجزئية', 'تعديل بيانات المستخلصات النهائية للجزئية', 'extracts', 'final_for_partial'],
        ['extracts_final_for_partial_delete', 'حذف المستخلصات النهائية للجزئية', 'حذف المستخلصات النهائية للجزئية', 'extracts', 'final_for_partial'],
        ['extracts_final_for_partial_approve', 'اعتماد المستخلصات النهائية للجزئية', 'اعتماد المستخلصات النهائية للجزئية', 'extracts', 'final_for_partial'],
        ['extracts_final_for_partial_export', 'تصدير المستخلصات النهائية للجزئية', 'تصدير بيانات المستخلصات النهائية للجزئية', 'extracts', 'final_for_partial'],
        
        // صلاحيات عامة للمستخلصات
        ['extracts_reports', 'عرض تقارير المستخلصات', 'عرض تقارير وإحصائيات المستخلصات', 'extracts', 'reports'],
        ['extracts_sap_sync', 'مزامنة SAP', 'مزامنة بيانات المستخلصات مع SAP', 'extracts', 'integration'],
    ];
    
    $insertStmt = $db->prepare("
        INSERT INTO permissions (name, display_name, description, module, category) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($newPermissions as $perm) {
        $insertStmt->execute($perm);
        echo "   ✅ تم إنشاء: {$perm[0]}\n";
    }
    
    echo "\n✅ تم إعادة تصميم الصلاحيات بنجاح!\n";
    echo "📊 إجمالي الصلاحيات الجديدة: " . count($newPermissions) . "\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

