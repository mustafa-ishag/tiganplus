<?php
/**
 * إنشاء نظام الجرد مع دعم الباركود
 * Create Stocktaking System with Barcode Support
 * 
 * يقوم هذا السكريبت بـ:
 * 1. إنشاء جدول جلسات الجرد (stocktaking_sessions)
 * 2. إنشاء جدول بنود الجرد (stocktaking_items)
 * 3. تعديل ENUM لـ transaction_type لإضافة stocktake_adjustment
 * 4. إضافة صلاحيات نظام الجرد
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🏗️ إنشاء نظام الجرد مع دعم الباركود...\n\n";
    
    $pdo->beginTransaction();
    
    // 1. جدول جلسات الجرد
    echo "1. إنشاء جدول جلسات الجرد...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS stocktaking_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_number VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم جلسة الجرد',
        title VARCHAR(255) NOT NULL COMMENT 'عنوان الجلسة',
        session_type ENUM('full', 'partial') NOT NULL DEFAULT 'full' COMMENT 'نوع الجرد (كامل/جزئي)',
        
        start_date DATE NOT NULL COMMENT 'تاريخ بدء الجرد',
        end_date DATE COMMENT 'تاريخ انتهاء الجرد',
        
        status ENUM('draft', 'in_progress', 'completed', 'approved', 'cancelled') 
            DEFAULT 'draft' COMMENT 'حالة الجلسة',
        
        total_items INT DEFAULT 0 COMMENT 'عدد المواد المجرودة',
        matched_items INT DEFAULT 0 COMMENT 'مواد متطابقة',
        surplus_items INT DEFAULT 0 COMMENT 'مواد بها فائض',
        deficit_items INT DEFAULT 0 COMMENT 'مواد بها عجز',
        not_counted_items INT DEFAULT 0 COMMENT 'مواد لم تُعد بعد',
        
        created_by INT NOT NULL COMMENT 'منشئ الجلسة',
        approved_by INT COMMENT 'معتمد الجلسة',
        approved_at TIMESTAMP NULL COMMENT 'تاريخ الاعتماد',
        
        adjustment_transaction_id INT COMMENT 'معرف معاملة التسوية',
        
        notes TEXT COMMENT 'ملاحظات',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        
        INDEX idx_session_number (session_number),
        INDEX idx_status (status),
        INDEX idx_start_date (start_date),
        INDEX idx_type (session_type),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جلسات الجرد';
    ");
    echo "✅ تم إنشاء جدول stocktaking_sessions\n";
    
    // 2. جدول بنود الجرد
    echo "\n2. إنشاء جدول بنود الجرد...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS stocktaking_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL COMMENT 'معرف جلسة الجرد',
        material_id INT NOT NULL COMMENT 'معرف المادة',
        
        system_quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT 'الكمية في النظام عند بدء الجرد',
        counted_quantity DECIMAL(10,3) DEFAULT NULL COMMENT 'الكمية المحصاة فعلياً',
        difference DECIMAL(10,3) GENERATED ALWAYS AS (COALESCE(counted_quantity, 0) - system_quantity) STORED 
            COMMENT 'الفرق (موجب=فائض، سالب=عجز)',
        
        status ENUM('pending', 'counted', 'verified') DEFAULT 'pending' COMMENT 'حالة العد',
        input_method ENUM('manual', 'barcode_scan') DEFAULT 'manual' COMMENT 'طريقة الإدخال',
        
        counted_by INT COMMENT 'من قام بالعد',
        counted_at TIMESTAMP NULL COMMENT 'وقت العد',
        notes TEXT COMMENT 'ملاحظات',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (session_id) REFERENCES stocktaking_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
        FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL,
        
        UNIQUE KEY unique_session_material (session_id, material_id),
        INDEX idx_session (session_id),
        INDEX idx_material (material_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بنود الجرد';
    ");
    echo "✅ تم إنشاء جدول stocktaking_items\n";
    
    // 3. تعديل ENUM لـ transaction_type
    echo "\n3. تعديل أنواع المعاملات لإضافة تسوية الجرد...\n";
    try {
        $pdo->exec("
        ALTER TABLE inventory_transactions 
        MODIFY COLUMN transaction_type 
        ENUM('incoming','outgoing','transfer','return','initial_balance',
             'loan_out','loan_in','loan_return','stocktake_adjustment') 
        COMMENT 'نوع العملية'
        ");
        echo "✅ تم إضافة نوع stocktake_adjustment\n";
    } catch (Exception $e) {
        echo "⚠️ تم تخطي تعديل ENUM (ربما موجود بالفعل): " . $e->getMessage() . "\n";
    }
    
    // 4. إضافة صلاحيات نظام الجرد
    echo "\n4. إضافة صلاحيات نظام الجرد...\n";
    
    $stocktakingPermissions = [
        ['name' => 'inventory_stocktaking_view', 'display_name' => 'عرض جلسات الجرد', 'description' => 'عرض قائمة جلسات الجرد وتفاصيلها', 'module' => 'inventory'],
        ['name' => 'inventory_stocktaking_create', 'display_name' => 'إنشاء جلسة جرد', 'description' => 'إنشاء جلسة جرد جديدة', 'module' => 'inventory'],
        ['name' => 'inventory_stocktaking_count', 'display_name' => 'تنفيذ العد', 'description' => 'تنفيذ العد الفعلي للمواد في الجرد', 'module' => 'inventory'],
        ['name' => 'inventory_stocktaking_approve', 'display_name' => 'اعتماد الجرد', 'description' => 'اعتماد نتائج الجرد وتسوية المخزون', 'module' => 'inventory'],
        ['name' => 'inventory_stocktaking_export', 'display_name' => 'تصدير تقارير الجرد', 'description' => 'تصدير تقارير الجرد إلى Excel/PDF', 'module' => 'inventory'],
        ['name' => 'inventory_barcode_print', 'display_name' => 'طباعة ملصقات الباركود', 'description' => 'طباعة ملصقات الباركود للمواد', 'module' => 'inventory'],
        ['name' => 'menu_inventory_stocktaking', 'display_name' => 'المخزون: الجرد', 'description' => 'عرض قائمة الجرد في الشريط الجانبي', 'module' => 'inventory'],
    ];
    
    $insertPermStmt = $pdo->prepare("
        INSERT IGNORE INTO permissions (name, display_name, description, module) 
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($stocktakingPermissions as $perm) {
        $insertPermStmt->execute([
            $perm['name'], 
            $perm['display_name'], 
            $perm['description'], 
            $perm['module']
        ]);
    }
    echo "✅ تم إضافة " . count($stocktakingPermissions) . " صلاحية جديدة\n";
    
    // 5. منح الصلاحيات للمستخدم الأول (admin)
    echo "\n5. منح صلاحيات الجرد للمسؤول...\n";
    $adminUser = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($adminUser) {
        $permIds = $pdo->query("SELECT id FROM permissions WHERE name LIKE 'inventory_stocktaking_%' OR name LIKE 'inventory_barcode_%' OR name = 'menu_inventory_stocktaking'")->fetchAll(PDO::FETCH_COLUMN);
        $assignStmt = $pdo->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
        foreach ($permIds as $permId) {
            $assignStmt->execute([$adminUser['id'], $permId]);
        }
        echo "✅ تم منح الصلاحيات للمستخدم ID: {$adminUser['id']}\n";
    }
    
    $pdo->commit();
    
    echo "\n🎉 تم إنشاء نظام الجرد بنجاح!\n";
    echo "\n📊 ملخص:\n";
    echo "- 2 جدول جديد (stocktaking_sessions, stocktaking_items)\n";
    echo "- نوع معاملة جديد: stocktake_adjustment\n";
    echo "- 7 صلاحيات جديدة\n";
    echo "\n✅ النظام جاهز للاستخدام!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
