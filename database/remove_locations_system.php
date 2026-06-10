<?php
/**
 * إزالة نظام مواقع التخزين المتعددة
 * Remove Multiple Storage Locations System
 * 
 * يحول النظام للعمل بمستودع واحد فقط بدلاً من مواقع متعددة
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🔧 إزالة نظام مواقع التخزين المتعددة...\n\n";
    
    // تعطيل فحص الـ foreign keys مؤقتاً
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // 1. حذف الجداول التي تعتمد على inventory_locations
    echo "1. حذف الجداول المرتبطة بمواقع التخزين...\n";
    
    $pdo->exec("DROP TABLE IF EXISTS material_locations");
    echo "  ✅ تم حذف جدول material_locations\n";
    
    $pdo->exec("DROP TABLE IF EXISTS stock_reservations");
    echo "  ✅ تم حذف جدول stock_reservations\n";
    
    $pdo->exec("DROP TABLE IF EXISTS stock_history");
    echo "  ✅ تم حذف جدول stock_history\n";
    
    // 2. حذف جدول inventory_locations
    echo "\n2. حذف جدول inventory_locations...\n";
    $pdo->exec("DROP TABLE IF EXISTS inventory_locations");
    echo "  ✅ تم حذف جدول inventory_locations\n";
    
    // 3. حذف جدول locations القديم
    echo "\n3. حذف جدول locations القديم...\n";
    $pdo->exec("DROP TABLE IF EXISTS locations");
    echo "  ✅ تم حذف جدول locations\n";
    
    // إعادة تفعيل فحص الـ foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 4. حذف أعمدة المواقع من inventory_transactions
    echo "\n4. حذف أعمدة المواقع من inventory_transactions...\n";
    
    $columns = $pdo->query("SHOW COLUMNS FROM inventory_transactions LIKE 'location_id'")->fetchAll();
    if (!empty($columns)) {
        // حذف الـ index أولاً إن وجد
        try { $pdo->exec("ALTER TABLE inventory_transactions DROP INDEX location_id"); } catch(Exception $e) {}
        $pdo->exec("ALTER TABLE inventory_transactions DROP COLUMN location_id");
        echo "  ✅ تم حذف عمود location_id\n";
    } else {
        echo "  ⏭️ عمود location_id غير موجود (محذوف سابقاً)\n";
    }
    
    $columns = $pdo->query("SHOW COLUMNS FROM inventory_transactions LIKE 'destination_location_id'")->fetchAll();
    if (!empty($columns)) {
        try { $pdo->exec("ALTER TABLE inventory_transactions DROP INDEX destination_location_id"); } catch(Exception $e) {}
        $pdo->exec("ALTER TABLE inventory_transactions DROP COLUMN destination_location_id");
        echo "  ✅ تم حذف عمود destination_location_id\n";
    } else {
        echo "  ⏭️ عمود destination_location_id غير موجود (محذوف سابقاً)\n";
    }
    
    // 5. حذف عمود location من materials
    echo "\n5. حذف عمود location من materials...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM materials LIKE 'location'")->fetchAll();
    if (!empty($columns)) {
        $pdo->exec("ALTER TABLE materials DROP COLUMN location");
        echo "  ✅ تم حذف عمود location من materials\n";
    } else {
        echo "  ⏭️ عمود location غير موجود (محذوف سابقاً)\n";
    }
    
    echo "\n🎉 تم إزالة نظام مواقع التخزين المتعددة بنجاح!\n";
    echo "\n📊 ملخص ما تم:\n";
    echo "- حذف جدول material_locations\n";
    echo "- حذف جدول stock_reservations\n";
    echo "- حذف جدول stock_history\n";
    echo "- حذف جدول inventory_locations\n";
    echo "- حذف جدول locations (القديم)\n";
    echo "- حذف عمود location_id من inventory_transactions\n";
    echo "- حذف عمود destination_location_id من inventory_transactions\n";
    echo "- حذف عمود location من materials\n";
    echo "\n✅ النظام يعمل الآن بمستودع واحد!\n";

} catch (Exception $e) {
    // إعادة تفعيل فحص الـ foreign keys في حالة الخطأ
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch(Exception $ex) {}
    
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
