<?php
/**
 * Migration: إضافة عمود PO إلى جدول المستخلصات الجزئية
 *
 * هذا الملف يضيف عمود po_number إلى جدول partial_extracts
 *
 * تاريخ الإنشاء: 2025-01-12
 */

require_once __DIR__ . '/../../config/config.php';

// الاتصال بقاعدة البيانات
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

try {
    echo "بدء إضافة عمود PO إلى جدول المستخلصات الجزئية...\n\n";
    
    // التحقق من وجود العمود
    $checkColumn = $db->query("SHOW COLUMNS FROM partial_extracts LIKE 'po_number'");
    
    if ($checkColumn->rowCount() > 0) {
        echo "⚠️  العمود po_number موجود بالفعل في جدول partial_extracts\n";
    } else {
        // إضافة عمود po_number
        $sql = "ALTER TABLE partial_extracts 
                ADD COLUMN po_number VARCHAR(50) NULL 
                COMMENT 'رقم أمر الشراء (Purchase Order)' 
                AFTER entry_sheet_number";
        
        $db->exec($sql);
        echo "✅ تم إضافة عمود po_number بنجاح\n";
    }
    
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "تم تنفيذ Migration بنجاح!\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ في تنفيذ Migration: " . $e->getMessage() . "\n";
    exit(1);
}

