<?php
/**
 * إضافة حقول ألوان المستخلص النهائي العادي إلى جدول invoice_settings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "إضافة حقول ألوان المستخلص النهائي العادي...\n";
    
    // التحقق من وجود الحقول
    $checkQuery = "SHOW COLUMNS FROM invoice_settings LIKE 'final_extract_header_color'";
    $result = $db->query($checkQuery)->fetch();
    
    if (!$result) {
        // إضافة الحقول
        $db->exec("
            ALTER TABLE invoice_settings 
            ADD COLUMN final_extract_header_color VARCHAR(7) DEFAULT '#8E44AD' AFTER final_accent_color,
            ADD COLUMN final_extract_accent_color VARCHAR(7) DEFAULT '#E74C3C' AFTER final_extract_header_color
        ");
        echo "✅ تم إضافة حقول ألوان المستخلص النهائي العادي بنجاح!\n";
    } else {
        echo "ℹ️ الحقول موجودة مسبقاً\n";
    }
    
    echo "\n✅ تم التحديث بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

