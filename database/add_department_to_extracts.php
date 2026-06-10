<?php
/**
 * إضافة حقل القسم (department) إلى جداول المستخلصات
 * Add department field to extracts tables
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🚀 إضافة حقل القسم إلى جداول المستخلصات...\n\n";
    
    // 1. إضافة حقل department إلى جدول partial_extracts
    echo "1. التحقق من جدول partial_extracts...\n";
    try {
        $db->exec("
            ALTER TABLE partial_extracts 
            ADD COLUMN department ENUM('connections', 'projects') NULL 
            AFTER branch_id
        ");
        echo "✅ تم إضافة حقل department إلى جدول partial_extracts\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️  حقل department موجود بالفعل في جدول partial_extracts\n";
        } else {
            throw $e;
        }
    }
    
    // 2. إضافة حقل department إلى جدول final_regular_extracts
    echo "\n2. التحقق من جدول final_regular_extracts...\n";
    try {
        $db->exec("
            ALTER TABLE final_regular_extracts 
            ADD COLUMN department ENUM('connections', 'projects') NULL 
            AFTER branch_id
        ");
        echo "✅ تم إضافة حقل department إلى جدول final_regular_extracts\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️  حقل department موجود بالفعل في جدول final_regular_extracts\n";
        } else {
            throw $e;
        }
    }
    
    // 3. إضافة حقل department إلى جدول final_for_partial_extracts
    echo "\n3. التحقق من جدول final_for_partial_extracts...\n";
    try {
        $db->exec("
            ALTER TABLE final_for_partial_extracts 
            ADD COLUMN department ENUM('connections', 'projects') NULL 
            AFTER branch_id
        ");
        echo "✅ تم إضافة حقل department إلى جدول final_for_partial_extracts\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️  حقل department موجود بالفعل في جدول final_for_partial_extracts\n";
        } else {
            throw $e;
        }
    }
    
    // 4. تحديث القيم الحالية من أوامر العمل المرتبطة
    echo "\n4. تحديث قيم القسم من أوامر العمل المرتبطة...\n";
    
    // تحديث المستخلصات الجزئية
    $updated = $db->exec("
        UPDATE partial_extracts pe
        INNER JOIN (
            SELECT pewo.partial_extract_id, wo.department
            FROM partial_extract_work_orders pewo
            INNER JOIN work_orders wo ON pewo.work_order_id = wo.id
            WHERE wo.department IS NOT NULL
            GROUP BY pewo.partial_extract_id
        ) AS wo_dept ON pe.id = wo_dept.partial_extract_id
        SET pe.department = wo_dept.department
        WHERE pe.department IS NULL
    ");
    echo "✅ تم تحديث $updated مستخلص جزئي\n";
    
    // تحديث المستخلصات النهائية العادية
    $updated = $db->exec("
        UPDATE final_regular_extracts fre
        INNER JOIN (
            SELECT frewo.final_regular_extract_id, wo.department
            FROM final_regular_extract_work_orders frewo
            INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
            WHERE wo.department IS NOT NULL
            GROUP BY frewo.final_regular_extract_id
        ) AS wo_dept ON fre.id = wo_dept.final_regular_extract_id
        SET fre.department = wo_dept.department
        WHERE fre.department IS NULL
    ");
    echo "✅ تم تحديث $updated مستخلص نهائي عادي\n";
    
    // تحديث المستخلصات النهائية للجزئية
    $updated = $db->exec("
        UPDATE final_for_partial_extracts ffpe
        INNER JOIN (
            SELECT ffpewo.final_for_partial_extract_id, wo.department
            FROM final_for_partial_extract_work_orders ffpewo
            INNER JOIN work_orders wo ON ffpewo.work_order_id = wo.id
            WHERE wo.department IS NOT NULL
            GROUP BY ffpewo.final_for_partial_extract_id
        ) AS wo_dept ON ffpe.id = wo_dept.final_for_partial_extract_id
        SET ffpe.department = wo_dept.department
        WHERE ffpe.department IS NULL
    ");
    echo "✅ تم تحديث $updated مستخلص نهائي للجزئية\n";
    
    echo "\n✅ تم إضافة وتحديث حقل القسم بنجاح في جميع جداول المستخلصات!\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>

