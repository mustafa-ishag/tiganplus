<?php
/**
 * إضافة عمود رقم صحيفة الإدخال للمستخلصات الجزئية
 * Add Entry Sheet Number column to Partial Extracts
 */

try {
    // الاتصال بقاعدة البيانات
    $db = new PDO(
        'mysql:host=localhost;dbname=etgan_erp;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    echo "بدء إضافة عمود رقم صحيفة الإدخال...\n";
    echo "Starting to add entry sheet number column...\n\n";
    
    // التحقق من وجود العمود
    $checkColumn = $db->query("
        SELECT COUNT(*) as count 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'partial_extracts' 
        AND COLUMN_NAME = 'entry_sheet_number'
    ")->fetch();
    
    if ($checkColumn['count'] > 0) {
        echo "⚠️ العمود 'entry_sheet_number' موجود بالفعل في جدول partial_extracts\n";
        echo "⚠️ Column 'entry_sheet_number' already exists in partial_extracts table\n";
        exit;
    }
    
    // إضافة العمود
    echo "1. إضافة عمود entry_sheet_number...\n";
    $db->exec("
        ALTER TABLE partial_extracts 
        ADD COLUMN entry_sheet_number VARCHAR(10) NULL UNIQUE COMMENT 'رقم صحيفة الإدخال (10 أرقام، اختياري، فريد)' 
        AFTER extract_number
    ");
    echo "✅ تم إضافة عمود entry_sheet_number بنجاح\n\n";
    
    // إضافة فهرس للعمود
    echo "2. إضافة فهرس للعمود...\n";
    $db->exec("
        ALTER TABLE partial_extracts 
        ADD INDEX idx_entry_sheet_number (entry_sheet_number)
    ");
    echo "✅ تم إضافة الفهرس بنجاح\n\n";
    
    // عرض معلومات العمود
    echo "3. التحقق من العمود الجديد...\n";
    $columnInfo = $db->query("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_COMMENT
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'partial_extracts' 
        AND COLUMN_NAME = 'entry_sheet_number'
    ")->fetch();
    
    echo "✅ معلومات العمود:\n";
    echo "   - الاسم: {$columnInfo['COLUMN_NAME']}\n";
    echo "   - النوع: {$columnInfo['COLUMN_TYPE']}\n";
    echo "   - يقبل NULL: {$columnInfo['IS_NULLABLE']}\n";
    echo "   - المفتاح: {$columnInfo['COLUMN_KEY']}\n";
    echo "   - التعليق: {$columnInfo['COLUMN_COMMENT']}\n\n";
    
    echo "✅ تم إضافة عمود رقم صحيفة الإدخال بنجاح!\n";
    echo "✅ Entry sheet number column added successfully!\n\n";
    
    echo "📝 ملاحظات:\n";
    echo "   - العمود اختياري (NULL)\n";
    echo "   - يجب أن يكون الرقم مكون من 10 أرقام\n";
    echo "   - الرقم فريد (لا يتكرر)\n";
    echo "   - تم إضافة فهرس لتحسين الأداء\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

