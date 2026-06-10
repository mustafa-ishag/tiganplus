<?php
/**
 * إضافة حقل الموقع إلى جدول أوامر العمل
 * Add location field to work_orders table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🔄 بدء إضافة حقل الموقع إلى جدول أوامر العمل...\n\n";
    
    // 1. التحقق من وجود العمود أولاً
    echo "1. التحقق من وجود عمود location...\n";
    $checkColumn = $pdo->query("SHOW COLUMNS FROM work_orders LIKE 'location'");
    
    if ($checkColumn->rowCount() == 0) {
        echo "2. إضافة عمود location...\n";
        $pdo->exec("
            ALTER TABLE work_orders 
            ADD COLUMN location VARCHAR(255) NULL COMMENT 'موقع تنفيذ أمر العمل' 
            AFTER branch_id
        ");
        echo "✅ تم إضافة عمود location بنجاح\n";
    } else {
        echo "ℹ️ عمود location موجود بالفعل\n";
    }
    
    // 2. إضافة فهرس للموقع
    echo "\n3. إضافة فهرس للموقع...\n";
    try {
        // التحقق من وجود الفهرس
        $checkIndex = $pdo->query("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'work_orders' 
            AND index_name = 'idx_location'
        ");
        $indexExists = $checkIndex->fetch()['count'] > 0;
        
        if (!$indexExists) {
            $pdo->exec("CREATE INDEX idx_location ON work_orders(location)");
            echo "✅ تم إضافة فهرس idx_location\n";
        } else {
            echo "ℹ️ فهرس idx_location موجود بالفعل\n";
        }
    } catch (PDOException $e) {
        echo "⚠️ خطأ في إضافة الفهرس: " . $e->getMessage() . "\n";
    }
    
    // 3. إضافة فهرس مركب للفرع والموقع
    echo "\n4. إضافة فهرس مركب للفرع والموقع...\n";
    try {
        $checkCompositeIndex = $pdo->query("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE() 
            AND table_name = 'work_orders' 
            AND index_name = 'idx_branch_location'
        ");
        $compositeIndexExists = $checkCompositeIndex->fetch()['count'] > 0;
        
        if (!$compositeIndexExists) {
            $pdo->exec("CREATE INDEX idx_branch_location ON work_orders(branch_id, location)");
            echo "✅ تم إضافة فهرس idx_branch_location\n";
        } else {
            echo "ℹ️ فهرس idx_branch_location موجود بالفعل\n";
        }
    } catch (PDOException $e) {
        echo "⚠️ خطأ في إضافة الفهرس المركب: " . $e->getMessage() . "\n";
    }
    
    // 4. عرض هيكل الجدول المحدث
    echo "\n5. عرض هيكل الجدول المحدث...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM work_orders");
    echo "أعمدة جدول work_orders:\n";
    while ($column = $columns->fetch()) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")" . 
             ($column['Null'] == 'YES' ? ' NULL' : ' NOT NULL') . 
             ($column['Default'] ? ' DEFAULT ' . $column['Default'] : '') . "\n";
    }
    
    echo "\n🎉 تم إضافة حقل الموقع إلى جدول أوامر العمل بنجاح!\n";
    echo "📝 يمكن الآن إضافة وتعديل موقع تنفيذ أوامر العمل\n";
    echo "🔗 سيتم استخدام هذا الحقل في شهادات الإنجاز لتوحيد المواقع\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
