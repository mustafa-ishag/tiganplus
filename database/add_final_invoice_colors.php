<?php
/**
 * إضافة حقول ألوان الفاتورة النهائية إلى جدول invoice_settings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "إضافة حقول ألوان الفاتورة النهائية...\n";
    
    // التحقق من وجود الحقول
    $checkQuery = "SHOW COLUMNS FROM invoice_settings LIKE 'final_header_color'";
    $result = $db->query($checkQuery)->fetch();
    
    if (!$result) {
        // إضافة الحقول
        $db->exec("
            ALTER TABLE invoice_settings 
            ADD COLUMN final_header_color VARCHAR(7) DEFAULT '2C5AA0' AFTER accent_color,
            ADD COLUMN final_accent_color VARCHAR(7) DEFAULT '4CAF50' AFTER final_header_color
        ");
        echo "✅ تم إضافة حقول ألوان الفاتورة النهائية بنجاح!\n";
    } else {
        echo "ℹ️ الحقول موجودة مسبقاً\n";
    }
    
    echo "\n✅ تم التحديث بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

