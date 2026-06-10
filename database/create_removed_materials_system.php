<?php
/**
 * إنشاء نظام المواد المزالة
 * Create Removed Materials System
 * 
 * هذا الملف ينشئ الجداول المطلوبة لنظام إدارة المواد المزالة
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();

    echo "🏗️ إنشاء نظام المواد المزالة...\n\n";

    $pdo->beginTransaction();

    // 1. جدول عمليات المواد المزالة
    echo "1. إنشاء جدول عمليات المواد المزالة...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS removed_material_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_number VARCHAR(30) NOT NULL UNIQUE COMMENT 'رقم العملية',
        transaction_type ENUM('incoming', 'outgoing') NOT NULL COMMENT 'نوع العملية: وارد أو صادر',
        material_category ENUM('scrap', 'return') NOT NULL COMMENT 'تصنيف المادة: تخريد أو إرجاع',
        work_order_id INT NOT NULL COMMENT 'معرف أمر العمل',
        branch_id INT NOT NULL COMMENT 'معرف الفرع',
        transaction_date DATE NOT NULL COMMENT 'تاريخ العملية',
        destination VARCHAR(255) DEFAULT NULL COMMENT 'جهة التسليم (للصادر)',
        total_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي القيمة',
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'حالة العملية',
        notes TEXT COMMENT 'ملاحظات',
        
        created_by INT NOT NULL COMMENT 'منشئ العملية',
        approved_by INT COMMENT 'معتمد العملية',
        approved_at TIMESTAMP NULL COMMENT 'تاريخ الاعتماد',
        rejected_by INT COMMENT 'رافض العملية',
        rejected_at TIMESTAMP NULL COMMENT 'تاريخ الرفض',
        rejection_reason TEXT COMMENT 'سبب الرفض',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE RESTRICT,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
        
        INDEX idx_transaction_number (transaction_number),
        INDEX idx_type (transaction_type),
        INDEX idx_category (material_category),
        INDEX idx_work_order (work_order_id),
        INDEX idx_branch (branch_id),
        INDEX idx_date (transaction_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عمليات المواد المزالة';
    ");
    echo "✅ تم إنشاء جدول removed_material_transactions\n";

    // 2. جدول تفاصيل عمليات المواد المزالة
    echo "\n2. إنشاء جدول تفاصيل عمليات المواد المزالة...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS removed_material_transaction_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL COMMENT 'معرف العملية',
        material_id INT NOT NULL COMMENT 'معرف المادة',
        quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية',
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'سعر الوحدة',
        total_price DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي السعر',
        notes TEXT COMMENT 'ملاحظات خاصة بالمادة',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (transaction_id) REFERENCES removed_material_transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
        
        INDEX idx_transaction (transaction_id),
        INDEX idx_material (material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تفاصيل عمليات المواد المزالة';
    ");
    echo "✅ تم إنشاء جدول removed_material_transaction_details\n";

    // 3. إضافة صلاحيات المواد المزالة
    echo "\n3. إنشاء صلاحيات المواد المزالة...\n";
    $permissions = [
        ['name' => 'removed_materials_view', 'display_name' => 'عرض المواد المزالة', 'description' => 'عرض عمليات المواد المزالة', 'module' => 'inventory'],
        ['name' => 'removed_materials_create', 'display_name' => 'إنشاء عملية مواد مزالة', 'description' => 'إنشاء عمليات وارد وصادر للمواد المزالة', 'module' => 'inventory'],
        ['name' => 'removed_materials_approve', 'display_name' => 'اعتماد عملية مواد مزالة', 'description' => 'اعتماد أو رفض عمليات المواد المزالة', 'module' => 'inventory'],
    ];

    foreach ($permissions as $permission) {
        $pdo->exec("
        INSERT IGNORE INTO permissions (name, display_name, description, module)
        VALUES ('{$permission['name']}', '{$permission['display_name']}', '{$permission['description']}', '{$permission['module']}')
        ");
    }
    echo "✅ تم إنشاء صلاحيات المواد المزالة\n";

    // 4. إضافة الصلاحيات لدور المدير (admin)
    echo "\n4. إضافة الصلاحيات لدور المدير...\n";
    $adminRoleId = $pdo->query("SELECT id FROM roles WHERE name = 'admin' LIMIT 1")->fetchColumn();
    if ($adminRoleId) {
        $permIds = $pdo->query("SELECT id FROM permissions WHERE name IN ('removed_materials_view', 'removed_materials_create', 'removed_materials_approve')")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($permIds as $permId) {
            $pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES ({$adminRoleId}, {$permId})");
        }
        echo "✅ تم إضافة الصلاحيات لدور المدير\n";
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo "\n🎉 تم إنشاء نظام المواد المزالة بنجاح!\n";
    echo "\n📊 ملخص:\n";
    echo "- جدول removed_material_transactions\n";
    echo "- جدول removed_material_transaction_details\n";
    echo "- 3 صلاحيات جديدة\n";
    echo "\n✅ النظام جاهز للاستخدام!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>