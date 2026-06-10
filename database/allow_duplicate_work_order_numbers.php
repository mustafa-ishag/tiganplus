<?php
/**
 * تعديل قاعدة البيانات للسماح بتكرار رقم أمر العمل مع أنواع مختلفة
 * Allow duplicate work order numbers with different types
 */

// تعريف النظام
define('ETGAN_SYSTEM', true);

require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🔄 تعديل قاعدة البيانات للسماح بتكرار رقم أمر العمل مع أنواع مختلفة...\n";
    
    // 1. إزالة القيد الفريد الحالي من work_order_number
    echo "\n1. إزالة القيد الفريد من work_order_number...\n";
    try {
        // التحقق من وجود القيد الفريد
        $constraints = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'work_orders' 
            AND CONSTRAINT_TYPE = 'UNIQUE'
            AND CONSTRAINT_NAME LIKE '%work_order_number%'
        ")->fetchAll();
        
        foreach ($constraints as $constraint) {
            $constraintName = $constraint['CONSTRAINT_NAME'];
            echo "إزالة القيد: {$constraintName}\n";
            $db->exec("ALTER TABLE work_orders DROP INDEX {$constraintName}");
            echo "✅ تم إزالة القيد {$constraintName}\n";
        }
        
        if (empty($constraints)) {
            echo "⚠️ لم يتم العثور على قيد فريد لـ work_order_number\n";
        }
        
    } catch (PDOException $e) {
        echo "⚠️ خطأ في إزالة القيد الفريد: " . $e->getMessage() . "\n";
    }
    
    // 2. إضافة قيد فريد مركب (work_order_number + work_order_type_id)
    echo "\n2. إضافة قيد فريد مركب (work_order_number, work_order_type_id)...\n";
    try {
        $db->exec("
            ALTER TABLE work_orders 
            ADD CONSTRAINT unique_work_order_number_type 
            UNIQUE (work_order_number, work_order_type_id)
        ");
        echo "✅ تم إضافة القيد الفريد المركب بنجاح\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️ القيد الفريد المركب موجود مسبقاً\n";
        } else {
            echo "❌ خطأ في إضافة القيد الفريد المركب: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. إضافة فهرس للبحث السريع
    echo "\n3. إضافة فهرس للبحث السريع...\n";
    try {
        $db->exec("
            CREATE INDEX IF NOT EXISTS idx_work_order_number_search 
            ON work_orders (work_order_number)
        ");
        echo "✅ تم إضافة فهرس البحث\n";
    } catch (PDOException $e) {
        echo "⚠️ خطأ في إضافة الفهرس: " . $e->getMessage() . "\n";
    }
    
    // 4. عرض بنية الجدول المحدثة
    echo "\n4. عرض بنية الجدول المحدثة...\n";
    echo "📋 القيود الحالية على جدول work_orders:\n";
    $constraints = $db->query("
        SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE, COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'work_orders'
        ORDER BY CONSTRAINT_NAME
    ")->fetchAll();
    
    foreach ($constraints as $constraint) {
        echo "- {$constraint['CONSTRAINT_NAME']}: {$constraint['CONSTRAINT_TYPE']} على {$constraint['COLUMN_NAME']}\n";
    }
    
    // 5. عرض الفهارس
    echo "\n📋 الفهارس الحالية على جدول work_orders:\n";
    $indexes = $db->query("SHOW INDEX FROM work_orders")->fetchAll();
    foreach ($indexes as $index) {
        echo "- {$index['Key_name']}: {$index['Column_name']}\n";
    }
    
    echo "\n🎉 تم تعديل قاعدة البيانات بنجاح!\n";
    echo "✅ يمكن الآن إنشاء أوامر عمل بنفس الرقم ولكن أنواع مختلفة\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
