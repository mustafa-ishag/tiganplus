<?php
/**
 * إضافة عمود رقم الفاتورة لجدول المستخلصات
 * Add Invoice Number Column to Extracts Table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "إضافة عمود رقم الفاتورة لجدول المستخلصات...\n";
    
    // التحقق من وجود العمود
    $checkColumn = $db->query("SHOW COLUMNS FROM extracts LIKE 'invoice_number'");
    if ($checkColumn->rowCount() > 0) {
        echo "⚠️ عمود رقم الفاتورة موجود مسبقاً\n";
    } else {
        // إضافة عمود رقم الفاتورة
        $db->exec("
            ALTER TABLE extracts 
            ADD COLUMN invoice_number VARCHAR(50) NULL AFTER extract_number
        ");
        echo "✅ تم إضافة عمود رقم الفاتورة\n";
    }
    
    // التحقق من وجود عمود title وحذفه إذا كان موجوداً
    $checkTitleColumn = $db->query("SHOW COLUMNS FROM extracts LIKE 'title'");
    if ($checkTitleColumn->rowCount() > 0) {
        $db->exec("ALTER TABLE extracts DROP COLUMN title");
        echo "✅ تم حذف عمود العنوان (title)\n";
    } else {
        echo "ℹ️ عمود العنوان غير موجود\n";
    }
    
    // عرض هيكل الجدول المحدث
    echo "\n📋 هيكل جدول المستخلصات المحدث:\n";
    $columns = $db->query("SHOW COLUMNS FROM extracts")->fetchAll();
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n🎉 تم تحديث جدول المستخلصات بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
