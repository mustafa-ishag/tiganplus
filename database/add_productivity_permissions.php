<?php
/**
 * إضافة صلاحيات نظام الإنتاجية
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🔐 إضافة صلاحيات نظام الإنتاجية\n";
    echo "==============================\n\n";
    
    // قائمة الصلاحيات المطلوبة
    $permissions = [
        [
            'name' => 'productivity_work_orders_view',
            'description' => 'عرض أوامر العمل للإنتاجية',
            'category' => 'productivity'
        ],
        [
            'name' => 'productivity_work_items_view',
            'description' => 'عرض بنود الإنتاجية',
            'category' => 'productivity'
        ],
        [
            'name' => 'productivity_work_items_manage',
            'description' => 'إدارة بنود الإنتاجية',
            'category' => 'productivity'
        ],
        [
            'name' => 'productivity_daily_logs_view',
            'description' => 'عرض السجلات اليومية للإنتاجية',
            'category' => 'productivity'
        ],
        [
            'name' => 'productivity_daily_logs_create',
            'description' => 'تسجيل السجلات اليومية للإنتاجية',
            'category' => 'productivity'
        ],
        [
            'name' => 'productivity_daily_logs_approve',
            'description' => 'اعتماد السجلات اليومية للإنتاجية',
            'category' => 'productivity'
        ],
        [
            'name' => 'productivity_approvers_manage',
            'description' => 'إدارة المعتمدين للإنتاجية',
            'category' => 'productivity'
        ]
    ];
    
    // التحقق من وجود جدول الصلاحيات
    $stmt = $db->query("SHOW TABLES LIKE 'permissions'");
    if ($stmt->rowCount() > 0) {
        echo "✅ جدول permissions موجود\n";

        // فحص بنية الجدول
        $descStmt = $db->query("DESCRIBE permissions");
        $columns = $descStmt->fetchAll(PDO::FETCH_COLUMN);

        // التحقق من وجود عمود category
        if (!in_array('category', $columns)) {
            echo "⚠️ إضافة عمود category...\n";
            $db->exec("ALTER TABLE permissions ADD COLUMN category VARCHAR(50) DEFAULT 'general'");
            echo "✅ تم إضافة عمود category\n";
        }

    } else {
        echo "⚠️ جدول الصلاحيات غير موجود، سيتم إنشاؤه...\n";

        $createPermissionsTable = "
        CREATE TABLE permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            category VARCHAR(50) DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $db->exec($createPermissionsTable);
        echo "✅ تم إنشاء جدول الصلاحيات\n";
    }
    
    // التحقق من وجود جدول صلاحيات المستخدمين
    $stmt = $db->query("SHOW TABLES LIKE 'user_permissions'");
    if ($stmt->rowCount() == 0) {
        echo "⚠️ جدول صلاحيات المستخدمين غير موجود، سيتم إنشاؤه...\n";
        
        $createUserPermissionsTable = "
        CREATE TABLE user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            granted_by INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_user_permission (user_id, permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($createUserPermissionsTable);
        echo "✅ تم إنشاء جدول صلاحيات المستخدمين\n";
    }
    
    echo "\n📋 إضافة الصلاحيات:\n";
    
    $insertPermissionStmt = $db->prepare("
        INSERT IGNORE INTO permissions (name, description, category) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($permissions as $permission) {
        $insertPermissionStmt->execute([
            $permission['name'],
            $permission['description'],
            $permission['category']
        ]);
        
        if ($insertPermissionStmt->rowCount() > 0) {
            echo "   ✅ {$permission['description']} ({$permission['name']})\n";
        } else {
            echo "   ⚠️ {$permission['description']} (موجودة مسبقاً)\n";
        }
    }
    
    // منح جميع الصلاحيات للمستخدم الأول (المدير العام)
    echo "\n👤 منح الصلاحيات للمستخدمين:\n";
    
    $adminUsersStmt = $db->query("
        SELECT id, username, full_name 
        FROM users 
        WHERE status = 'active' 
        ORDER BY id 
        LIMIT 3
    ");
    $adminUsers = $adminUsersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($adminUsers)) {
        $grantPermissionStmt = $db->prepare("
            INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) 
            SELECT ?, p.id, ? 
            FROM permissions p 
            WHERE p.category = 'productivity'
        ");
        
        foreach ($adminUsers as $user) {
            $grantPermissionStmt->execute([$user['id'], $user['id']]);
            $grantedCount = $grantPermissionStmt->rowCount();
            
            echo "   ✅ {$user['full_name']} ({$user['username']}) - $grantedCount صلاحية جديدة\n";
        }
    } else {
        echo "   ⚠️ لا توجد مستخدمين متاحين\n";
    }
    
    // عرض إحصائيات
    echo "\n📊 إحصائيات الصلاحيات:\n";
    
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_permissions,
            SUM(CASE WHEN category = 'productivity' THEN 1 ELSE 0 END) as productivity_permissions
        FROM permissions
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   📈 إجمالي الصلاحيات: {$stats['total_permissions']}\n";
    echo "   🏭 صلاحيات الإنتاجية: {$stats['productivity_permissions']}\n";
    
    $userStatsStmt = $db->query("
        SELECT 
            u.full_name,
            u.username,
            COUNT(up.permission_id) as permissions_count
        FROM users u
        LEFT JOIN user_permissions up ON u.id = up.user_id
        LEFT JOIN permissions p ON up.permission_id = p.id AND p.category = 'productivity'
        WHERE u.status = 'active'
        GROUP BY u.id, u.full_name, u.username
        ORDER BY permissions_count DESC
    ");
    $userStats = $userStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n👥 صلاحيات المستخدمين:\n";
    foreach ($userStats as $userStat) {
        echo "   • {$userStat['full_name']} ({$userStat['username']}): {$userStat['permissions_count']} صلاحية\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 تم إعداد صلاحيات نظام الإنتاجية بنجاح!\n\n";
    
    echo "🔐 الصلاحيات المضافة:\n";
    foreach ($permissions as $permission) {
        echo "• {$permission['description']}\n";
    }
    
    echo "\n💡 للوصول إلى صفحات النظام:\n";
    echo "1. تسجيل الدخول بحساب له الصلاحيات المطلوبة\n";
    echo "2. التأكد من منح الصلاحيات للمستخدمين المناسبين\n";
    echo "3. اختبار الوصول للصفحات المختلفة\n";
    
    echo "\n🌐 الروابط الجاهزة:\n";
    echo "• أوامر العمل: http://localhost/etganplus/public/productivity/work-orders/index.php\n";
    echo "• إدارة المعتمدين: http://localhost/etganplus/public/productivity/approvers/index.php\n";
    echo "• الاعتمادات: http://localhost/etganplus/public/productivity/approvals/index.php\n";
    
    echo "\n🔧 إذا استمرت مشكلة الصلاحيات:\n";
    echo "1. تحقق من دالة hasPermission() في includes/functions.php\n";
    echo "2. تأكد من ربط المستخدم بالصلاحيات في جدول user_permissions\n";
    echo "3. تحقق من حالة المستخدم (active)\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n";
}
?>
