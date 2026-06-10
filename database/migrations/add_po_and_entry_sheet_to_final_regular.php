<?php
/**
 * Migration: إضافة عمودي PO ورقم صحيفة الإدخال إلى جدول المستخلصات النهائية العادية
 * Add PO Number and Entry Sheet Number columns to final_regular_extracts table
 * 
 * التاريخ: 2025-01-12
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = getDB();
    
    echo "=== بدء Migration: إضافة عمودي PO ورقم صحيفة الإدخال ===\n\n";
    
    // التحقق من وجود الجدول
    $tableExists = $db->query("SHOW TABLES LIKE 'final_regular_extracts'")->fetch();
    
    if (!$tableExists) {
        throw new Exception("الجدول final_regular_extracts غير موجود!");
    }
    
    echo "✓ الجدول final_regular_extracts موجود\n\n";
    
    // التحقق من وجود عمود po_number
    $poColumnExists = $db->query("SHOW COLUMNS FROM final_regular_extracts LIKE 'po_number'")->fetch();
    
    if ($poColumnExists) {
        echo "⚠ عمود po_number موجود بالفعل - تخطي\n";
    } else {
        echo "إضافة عمود po_number...\n";
        $db->exec("
            ALTER TABLE final_regular_extracts 
            ADD COLUMN po_number VARCHAR(50) NULL 
            COMMENT 'رقم أمر الشراء (Purchase Order)' 
            AFTER invoice_number
        ");
        echo "✓ تم إضافة عمود po_number بنجاح\n";
    }
    
    // التحقق من وجود عمود entry_sheet_number
    $entrySheetColumnExists = $db->query("SHOW COLUMNS FROM final_regular_extracts LIKE 'entry_sheet_number'")->fetch();
    
    if ($entrySheetColumnExists) {
        echo "⚠ عمود entry_sheet_number موجود بالفعل - تخطي\n";
    } else {
        echo "\nإضافة عمود entry_sheet_number...\n";
        $db->exec("
            ALTER TABLE final_regular_extracts 
            ADD COLUMN entry_sheet_number VARCHAR(10) NULL 
            COMMENT 'رقم صحيفة الإدخال من SAP (10 أرقام)' 
            AFTER po_number
        ");
        echo "✓ تم إضافة عمود entry_sheet_number بنجاح\n";
    }
    
    echo "\n=== اكتمل Migration بنجاح ===\n";
    echo "\nهيكل الجدول الحالي:\n";
    
    // عرض هيكل الجدول
    $columns = $db->query("SHOW COLUMNS FROM final_regular_extracts")->fetchAll();
    echo "\n";
    printf("%-30s %-20s %-10s\n", "العمود", "النوع", "NULL");
    echo str_repeat("-", 60) . "\n";
    foreach ($columns as $column) {
        printf("%-30s %-20s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null']
        );
    }
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

