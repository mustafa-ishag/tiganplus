<?php
/**
 * إضافة عمود رقم صحيفة الإدخال للمستخلصات الجزئية
 * Add entry_sheet_number column to partial_extracts table
 */

require_once __DIR__ . '/../../config/config.php';

try {
    $db = getDB();
    
    echo "بدء إضافة عمود رقم صحيفة الإدخال...\n\n";
    
    // التحقق من وجود العمود
    $checkColumn = $db->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'partial_extracts' 
        AND COLUMN_NAME = 'entry_sheet_number'
    ")->fetch();
    
    if ($checkColumn) {
        echo "⚠️  العمود entry_sheet_number موجود بالفعل في جدول partial_extracts\n";
    } else {
        // إضافة العمود
        $db->exec("
            ALTER TABLE partial_extracts 
            ADD COLUMN entry_sheet_number VARCHAR(10) NULL UNIQUE COMMENT 'رقم صحيفة الإدخال (10 أرقام)' 
            AFTER extract_number
        ");
        
        echo "✅ تم إضافة عمود entry_sheet_number بنجاح\n";
        
        // إضافة فهرس للعمود
        $db->exec("
            CREATE INDEX idx_entry_sheet_number ON partial_extracts(entry_sheet_number)
        ");
        
        echo "✅ تم إضافة فهرس للعمود entry_sheet_number\n";
    }
    
    echo "\n✅ اكتملت العملية بنجاح!\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}

