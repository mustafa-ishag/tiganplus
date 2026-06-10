<?php
/**
 * إضافة حقل المفضلة إلى جدول أوامر العمل
 * Add favorite field to work_orders table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🔄 إضافة حقل المفضلة إلى جدول أوامر العمل...\n\n";
    
    // التحقق من وجود الحقل
    $stmt = $db->query("SHOW COLUMNS FROM work_orders LIKE 'is_favorite'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // إضافة حقل is_favorite
        $sql = "ALTER TABLE work_orders 
                ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'هل أمر العمل مفضل' 
                AFTER status";
        
        $db->exec($sql);
        echo "✅ تم إضافة حقل is_favorite بنجاح\n";
        
        // إضافة فهرس للحقل
        $db->exec("CREATE INDEX idx_is_favorite ON work_orders (is_favorite)");
        echo "✅ تم إضافة فهرس للحقل is_favorite\n";
    } else {
        echo "⚠️ حقل is_favorite موجود مسبقاً\n";
    }
    
    echo "\n✅ تم تحديث جدول work_orders بنجاح!\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

