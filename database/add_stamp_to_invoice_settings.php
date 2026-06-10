<?php
/**
 * إضافة حقل الختم إلى جدول إعدادات الفواتير
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🔄 بدء إضافة حقل الختم إلى جدول إعدادات الفواتير...\n\n";
    
    // التحقق من وجود العمود
    $checkColumn = $db->query("SHOW COLUMNS FROM invoice_settings LIKE 'stamp_path'");
    
    if ($checkColumn->rowCount() == 0) {
        // إضافة عمود الختم
        $db->exec("
            ALTER TABLE invoice_settings
            ADD COLUMN stamp_path VARCHAR(500) NULL AFTER supplier_logo_path
        ");

        echo "✅ تم إضافة حقل الختم بنجاح\n\n";
    } else {
        echo "ℹ️ حقل الختم موجود مسبقاً\n\n";
    }
    
    // عرض هيكل الجدول المحدث
    echo "📋 هيكل جدول invoice_settings المحدث:\n";
    $columns = $db->query("SHOW COLUMNS FROM invoice_settings");
    while ($col = $columns->fetch()) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n🎉 تم تحديث جدول إعدادات الفواتير بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}

