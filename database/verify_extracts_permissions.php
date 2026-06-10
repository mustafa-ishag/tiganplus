<?php
/**
 * التحقق من صلاحيات المستخلصات
 * Verify Extracts Permissions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║         التحقق من صلاحيات المستخلصات                          ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // 1. التحقق من الصلاحيات
    echo "📋 1. التحقق من الصلاحيات المتاحة:\n";
    $stmt = $db->query("SELECT COUNT(*) as count FROM permissions WHERE module = 'extracts'");
    $result = $stmt->fetch();
    echo "   ✅ إجمالي صلاحيات المستخلصات: " . $result['count'] . "\n";
    
    // 2. التحقق من الفئات
    echo "\n📋 2. توزيع الصلاحيات حسب الفئة:\n";
    $stmt = $db->query("
        SELECT category, COUNT(*) as count 
        FROM permissions 
        WHERE module = 'extracts' 
        GROUP BY category 
        ORDER BY category
    ");
    $categories = $stmt->fetchAll();
    foreach ($categories as $cat) {
        echo "   • {$cat['category']}: {$cat['count']} صلاحية\n";
    }
    
    // 3. التحقق من تعيين الصلاحيات للأدوار
    echo "\n📋 3. تعيين الصلاحيات للأدوار:\n";
    $stmt = $db->query("
        SELECT r.name, COUNT(rp.permission_id) as permission_count 
        FROM roles r 
        LEFT JOIN role_permissions rp ON r.id = rp.role_id 
        WHERE rp.permission_id IN (SELECT id FROM permissions WHERE module = 'extracts') 
        GROUP BY r.id, r.name 
        ORDER BY r.name
    ");
    $rolePerms = $stmt->fetchAll();
    foreach ($rolePerms as $role) {
        echo "   ✅ {$role['name']}: {$role['permission_count']} صلاحية\n";
    }
    
    // 4. التحقق من عدم وجود صلاحيات قديمة
    echo "\n📋 4. التحقق من عدم وجود صلاحيات قديمة:\n";
    $oldPerms = ['view_extracts', 'add_extracts', 'edit_extracts', 'delete_extracts', 'approve_extracts', 'create_extracts'];
    $found = false;
    foreach ($oldPerms as $perm) {
        $stmt = $db->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmt->execute([$perm]);
        if ($stmt->fetch()) {
            echo "   ❌ وجدت صلاحية قديمة: $perm\n";
            $found = true;
        }
    }
    if (!$found) {
        echo "   ✅ لا توجد صلاحيات قديمة\n";
    }
    
    // 5. التحقق من الترميز
    echo "\n📋 5. التحقق من الترميز الصحيح:\n";
    $stmt = $db->query("
        SELECT name, display_name 
        FROM permissions 
        WHERE module = 'extracts' 
        LIMIT 3
    ");
    $samples = $stmt->fetchAll();
    foreach ($samples as $sample) {
        echo "   • {$sample['name']}: {$sample['display_name']}\n";
    }
    
    echo "\n✅ تم التحقق من صلاحيات المستخلصات بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

