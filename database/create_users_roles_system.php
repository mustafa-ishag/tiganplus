<?php
/**
 * إنشاء نظام المستخدمين والأدوار والصلاحيات
 * Create Users, Roles and Permissions System
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
    
    echo "🔄 إنشاء نظام المستخدمين والأدوار والصلاحيات...\n";
    
    // 1. إنشاء جدول الصلاحيات
    echo "\n1. إنشاء جدول الصلاحيات...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE COMMENT 'اسم الصلاحية',
        display_name VARCHAR(255) NOT NULL COMMENT 'الاسم المعروض',
        description TEXT NULL COMMENT 'وصف الصلاحية',
        module VARCHAR(50) NOT NULL COMMENT 'الوحدة التابعة لها',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_name (name),
        INDEX idx_module (module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول الصلاحيات';
    ");
    echo "✅ تم إنشاء جدول permissions\n";
    
    // 2. تحديث جدول الأدوار
    echo "\n2. تحديث جدول الأدوار...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE COMMENT 'اسم الدور',
        display_name VARCHAR(255) NOT NULL COMMENT 'الاسم المعروض',
        description TEXT NULL COMMENT 'وصف الدور',
        level INT NOT NULL DEFAULT 1 COMMENT 'مستوى الدور (للتسلسل الهرمي)',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_name (name),
        INDEX idx_level (level),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول الأدوار';
    ");
    echo "✅ تم تحديث جدول roles\n";
    
    // 3. إنشاء جدول ربط الأدوار بالصلاحيات
    echo "\n3. إنشاء جدول ربط الأدوار بالصلاحيات...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_role_permission (role_id, permission_id),
        INDEX idx_role (role_id),
        INDEX idx_permission (permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ربط الأدوار بالصلاحيات';
    ");
    echo "✅ تم إنشاء جدول role_permissions\n";
    
    // 4. تحديث جدول المستخدمين
    echo "\n4. تحديث جدول المستخدمين...\n";
    
    // التحقق من وجود الحقول وإضافتها إذا لم تكن موجودة
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    
    $newColumns = [
        'full_name' => "ADD COLUMN full_name VARCHAR(255) NOT NULL COMMENT 'الاسم الكامل' AFTER username",
        'phone' => "ADD COLUMN phone VARCHAR(20) NULL COMMENT 'رقم الهاتف' AFTER email",
        'department' => "ADD COLUMN department VARCHAR(100) NULL COMMENT 'القسم' AFTER phone",
        'branch_id' => "ADD COLUMN branch_id INT NULL COMMENT 'الفرع التابع له' AFTER department",
        'position' => "ADD COLUMN position VARCHAR(100) NULL COMMENT 'المنصب' AFTER branch_id",
        'last_login' => "ADD COLUMN last_login TIMESTAMP NULL COMMENT 'آخر تسجيل دخول' AFTER status"
    ];
    
    foreach ($newColumns as $columnName => $sql) {
        if (!in_array($columnName, $columns)) {
            try {
                $pdo->exec("ALTER TABLE users $sql");
                echo "✅ تم إضافة حقل $columnName\n";
            } catch (PDOException $e) {
                echo "⚠️ خطأ في إضافة $columnName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "⚠️ حقل $columnName موجود مسبقاً\n";
        }
    }
    
    // إضافة المفتاح الخارجي للفرع
    try {
        $pdo->exec("
            ALTER TABLE users 
            ADD CONSTRAINT fk_users_branch 
            FOREIGN KEY (branch_id) REFERENCES branches(id) 
            ON DELETE SET NULL ON UPDATE CASCADE
        ");
        echo "✅ تم إضافة المفتاح الخارجي للفرع\n";
    } catch (PDOException $e) {
        echo "⚠️ المفتاح الخارجي للفرع موجود مسبقاً\n";
    }
    
    // 5. إنشاء جدول ربط المستخدمين بالأدوار
    echo "\n5. إنشاء جدول ربط المستخدمين بالأدوار...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role_id INT NOT NULL,
        assigned_by INT NULL COMMENT 'من قام بالتعيين',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
        UNIQUE KEY unique_user_role (user_id, role_id),
        INDEX idx_user (user_id),
        INDEX idx_role (role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ربط المستخدمين بالأدوار';
    ");
    echo "✅ تم إنشاء جدول user_roles\n";
    
    echo "\n🎉 تم إنشاء نظام المستخدمين والأدوار والصلاحيات بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
