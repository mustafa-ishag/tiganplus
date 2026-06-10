<?php
/**
 * Migration: إنشاء جدول العقود وإضافة عمود contract_id لأوامر العمل
 * 
 * تشغيل: php migrations/add_contracts_table.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "=== بدء Migration: إضافة جدول العقود ===\n\n";
    
    // 1. إنشاء جدول العقود
    echo "1. إنشاء جدول contracts...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS contracts (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            contract_number VARCHAR(10) NOT NULL UNIQUE COMMENT 'رقم العقد - 10 أرقام',
            start_date DATE NOT NULL COMMENT 'تاريخ بداية العقد',
            end_date DATE NOT NULL COMMENT 'تاريخ نهاية العقد',
            description TEXT NULL COMMENT 'وصف العقد',
            is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'نشط/غير نشط',
            created_by INT(11) NULL COMMENT 'المستخدم المنشئ',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_contract_dates (start_date, end_date),
            INDEX idx_contract_number (contract_number),
            INDEX idx_contract_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول العقود'
    ");
    echo "   ✅ تم إنشاء جدول contracts\n";
    
    // 2. إضافة عمود contract_id إلى جدول work_orders
    echo "\n2. إضافة عمود contract_id إلى work_orders...\n";
    
    // التحقق من وجود العمود
    $columns = $db->query("SHOW COLUMNS FROM work_orders LIKE 'contract_id'")->fetchAll();
    if (empty($columns)) {
        $db->exec("
            ALTER TABLE work_orders 
            ADD COLUMN contract_id INT(11) NULL COMMENT 'معرف العقد المرتبط' AFTER status,
            ADD INDEX idx_work_order_contract (contract_id)
        ");
        echo "   ✅ تم إضافة عمود contract_id إلى work_orders\n";
    } else {
        echo "   ⏭️ العمود contract_id موجود مسبقاً\n";
    }
    
    echo "\n=== ✅ تمت Migration بنجاح ===\n";
    echo "\nملاحظة: بعد إضافة العقود من صفحة إدارة العقود، سيتم ربط أوامر العمل تلقائياً.\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
