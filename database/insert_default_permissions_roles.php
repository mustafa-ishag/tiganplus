<?php
/**
 * إدراج الصلاحيات والأدوار الافتراضية
 * Insert Default Permissions and Roles
 */

require_once __DIR__ . '/../config/config.php';

try {
    // الاتصال بقاعدة البيانات
    $host = 'localhost';
    $dbname = 'etgan_erp';
    $username = 'root';
    $password = '';

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    echo "🔄 إدراج الصلاحيات والأدوار الافتراضية...\n";
    
    // 1. إدراج الصلاحيات
    echo "\n1. إدراج الصلاحيات...\n";
    
    $permissions = [
        // صلاحيات الفروع
        ['branches_view', 'عرض الفروع', 'عرض قائمة الفروع', 'branches'],
        ['branches_view_all', 'عرض جميع الفروع', 'عرض جميع الفروع (للمديرين)', 'branches'],
        ['branches_create', 'إضافة فرع', 'إضافة فرع جديد', 'branches'],
        ['branches_edit', 'تعديل الفروع', 'تعديل بيانات الفروع', 'branches'],
        ['branches_delete', 'حذف الفروع', 'حذف الفروع', 'branches'],
        ['branches_export', 'تصدير الفروع', 'تصدير بيانات الفروع', 'branches'],
        ['branches_import', 'استيراد الفروع', 'استيراد بيانات الفروع', 'branches'],
        
        // صلاحيات أوامر العمل
        ['work_orders_view', 'عرض أوامر العمل', 'عرض قائمة أوامر العمل', 'work_orders'],
        ['work_orders_view_all', 'عرض جميع أوامر العمل', 'عرض أوامر العمل لجميع الفروع', 'work_orders'],
        ['work_orders_create', 'إضافة أمر عمل', 'إضافة أمر عمل جديد', 'work_orders'],
        ['work_orders_edit', 'تعديل أوامر العمل', 'تعديل بيانات أوامر العمل', 'work_orders'],
        ['work_orders_delete', 'حذف أوامر العمل', 'حذف أوامر العمل', 'work_orders'],
        ['work_orders_export', 'تصدير أوامر العمل', 'تصدير بيانات أوامر العمل', 'work_orders'],
        ['work_orders_import', 'استيراد أوامر العمل', 'استيراد بيانات أوامر العمل', 'work_orders'],
        ['work_orders_attachments', 'إدارة مرفقات أوامر العمل', 'رفع وإدارة المرفقات', 'work_orders'],
        
        // صلاحيات المستخلصات
        ['extracts_view', 'عرض المستخلصات', 'عرض قائمة المستخلصات', 'extracts'],
        ['extracts_view_all', 'عرض جميع المستخلصات', 'عرض المستخلصات لجميع الفروع', 'extracts'],
        ['extracts_create', 'إنشاء مستخلص', 'إنشاء مستخلص جديد', 'extracts'],
        ['extracts_edit', 'تعديل المستخلصات', 'تعديل بيانات المستخلصات', 'extracts'],
        ['extracts_delete', 'حذف المستخلصات', 'حذف المستخلصات', 'extracts'],
        ['extracts_approve', 'اعتماد المستخلصات', 'اعتماد المستخلصات في مراحل الموافقة', 'extracts'],
        ['extracts_export', 'تصدير المستخلصات', 'تصدير بيانات المستخلصات', 'extracts'],
        
        // صلاحيات المستخدمين
        ['users_view', 'عرض المستخدمين', 'عرض قائمة المستخدمين', 'users'],
        ['users_create', 'إضافة مستخدم', 'إضافة مستخدم جديد', 'users'],
        ['users_edit', 'تعديل المستخدمين', 'تعديل بيانات المستخدمين', 'users'],
        ['users_delete', 'حذف المستخدمين', 'حذف المستخدمين', 'users'],
        ['users_manage_roles', 'إدارة أدوار المستخدمين', 'تعيين وإدارة أدوار المستخدمين', 'users'],
        
        // صلاحيات التقارير
        ['reports_view', 'عرض التقارير', 'عرض التقارير والإحصائيات', 'reports'],
        ['reports_export', 'تصدير التقارير', 'تصدير التقارير بصيغ مختلفة', 'reports'],
        ['reports_print', 'طباعة التقارير', 'طباعة التقارير', 'reports'],
        
        // صلاحيات النظام
        ['system_admin', 'إدارة النظام', 'إدارة إعدادات النظام العامة', 'system'],
        ['system_backup', 'النسخ الاحتياطي', 'إنشاء واستعادة النسخ الاحتياطية', 'system'],
        ['system_logs', 'عرض سجلات النظام', 'عرض سجلات العمليات والأخطاء', 'system']
    ];
    
    // التحقق من هيكل جدول الصلاحيات وتحديثه
    $columns = $pdo->query("SHOW COLUMNS FROM permissions")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('display_name', $columns)) {
        $pdo->exec("ALTER TABLE permissions ADD COLUMN display_name VARCHAR(255) NOT NULL COMMENT 'الاسم المعروض' AFTER name");
        echo "✅ تم إضافة حقل display_name\n";
    }

    if (!in_array('module', $columns)) {
        $pdo->exec("ALTER TABLE permissions ADD COLUMN module VARCHAR(50) NOT NULL COMMENT 'الوحدة التابعة لها' AFTER description");
        echo "✅ تم إضافة حقل module\n";
    }

    $insertPermissionSql = "INSERT IGNORE INTO permissions (name, display_name, description, module) VALUES (?, ?, ?, ?)";
    $permissionCount = 0;

    foreach ($permissions as $permission) {
        $stmt = $pdo->prepare($insertPermissionSql);
        $result = $stmt->execute($permission);
        if ($stmt->rowCount() > 0) {
            $permissionCount++;
            echo "✅ تم إدراج الصلاحية: {$permission[1]}\n";
        }
    }
    
    echo "📊 تم إدراج $permissionCount صلاحية جديدة\n";
    
    // 2. إدراج الأدوار
    echo "\n2. إدراج الأدوار...\n";
    
    $roles = [
        ['super_admin', 'مدير النظام', 'مدير النظام العام - جميع الصلاحيات', 10],
        ['admin_manager', 'مدير الإدارة', 'مدير الإدارة - صلاحيات إدارية عالية', 9],
        ['department_manager', 'مدير الدائرة', 'مدير الدائرة - إدارة العمليات', 8],
        ['branch_manager', 'مدير الفرع', 'مدير الفرع - إدارة فرع واحد', 7],
        ['technical_support', 'موظف المساندة الفنية', 'موظف قسم المساندة الفنية', 6],
        ['construction_employee', 'موظف الإنشاءات', 'موظف قسم الإنشاءات', 5],
        ['finance_employee', 'موظف المالية', 'موظف قسم المالية', 4],
        ['regular_user', 'مستخدم عادي', 'مستخدم عادي - صلاحيات محدودة', 1]
    ];
    
    // التحقق من هيكل جدول الأدوار وتحديثه
    $roleColumns = $pdo->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('display_name', $roleColumns)) {
        $pdo->exec("ALTER TABLE roles ADD COLUMN display_name VARCHAR(255) NOT NULL COMMENT 'الاسم المعروض' AFTER name");
        echo "✅ تم إضافة حقل display_name للأدوار\n";
    }

    if (!in_array('level', $roleColumns)) {
        $pdo->exec("ALTER TABLE roles ADD COLUMN level INT NOT NULL DEFAULT 1 COMMENT 'مستوى الدور' AFTER description");
        echo "✅ تم إضافة حقل level للأدوار\n";
    }

    $insertRoleSql = "INSERT IGNORE INTO roles (name, display_name, description, level) VALUES (?, ?, ?, ?)";
    $roleCount = 0;

    foreach ($roles as $role) {
        $stmt = $pdo->prepare($insertRoleSql);
        $result = $stmt->execute($role);
        if ($stmt->rowCount() > 0) {
            $roleCount++;
            echo "✅ تم إدراج الدور: {$role[1]}\n";
        }
    }
    
    echo "📊 تم إدراج $roleCount دور جديد\n";
    
    echo "\n🎉 تم إدراج الصلاحيات والأدوار الافتراضية بنجاح!\n";
    
    // عرض الإحصائيات
    $totalPermissions = $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    $totalRoles = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    
    echo "\n📊 إحصائيات النظام:\n";
    echo "- إجمالي الصلاحيات: $totalPermissions\n";
    echo "- إجمالي الأدوار: $totalRoles\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
