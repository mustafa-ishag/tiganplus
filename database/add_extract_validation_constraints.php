<?php
/**
 * إضافة قيود التحقق لمنع الحالات الخاطئة في المستخلصات
 * Add Validation Constraints to Prevent Invalid Extract Cases
 * 
 * الهدف:
 * - منع دخول أمر عمل في جزئي ونهائي عادي معاً
 * - منع دخول أمر عمل في نهائي عادي ونهائي للجزئية معاً
 * - منع دخول أمر عمل في نفس نوع المستخلص أكثر من مرة
 * 
 * تاريخ الإنشاء: 2025-10-16
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "=== إضافة قيود التحقق للمستخلصات ===\n\n";
    
    // 1. إنشاء دالة للتحقق من عدم تكرار أمر العمل في مستخلصات متعارضة
    echo "1. إنشاء دالة التحقق من تعارض المستخلصات...\n";
    
    $db->exec("DROP FUNCTION IF EXISTS check_work_order_extract_conflict");
    
    $db->exec("
        CREATE FUNCTION check_work_order_extract_conflict(
            p_work_order_id INT,
            p_extract_type ENUM('partial', 'final_regular', 'final_for_partial')
        )
        RETURNS BOOLEAN
        DETERMINISTIC
        READS SQL DATA
        BEGIN
            DECLARE v_in_partial INT DEFAULT 0;
            DECLARE v_in_final_regular INT DEFAULT 0;
            DECLARE v_in_final_for_partial INT DEFAULT 0;
            
            -- التحقق من وجود أمر العمل في المستخلصات الجزئية
            SELECT COUNT(*) INTO v_in_partial
            FROM partial_extract_work_orders
            WHERE work_order_id = p_work_order_id;
            
            -- التحقق من وجود أمر العمل في المستخلصات النهائية العادية
            SELECT COUNT(*) INTO v_in_final_regular
            FROM final_regular_extract_work_orders
            WHERE work_order_id = p_work_order_id;
            
            -- التحقق من وجود أمر العمل في المستخلصات النهائية للجزئية
            SELECT COUNT(*) INTO v_in_final_for_partial
            FROM final_for_partial_extract_work_orders
            WHERE work_order_id = p_work_order_id;
            
            -- التحقق من التعارضات
            IF p_extract_type = 'partial' THEN
                -- يمكن إضافة أمر عمل للجزئي فقط إذا لم يكن في نهائي عادي
                IF v_in_final_regular > 0 THEN
                    RETURN FALSE;
                END IF;
            ELSEIF p_extract_type = 'final_regular' THEN
                -- يمكن إضافة أمر عمل للنهائي العادي فقط إذا لم يكن في جزئي أو نهائي للجزئية
                IF v_in_partial > 0 OR v_in_final_for_partial > 0 THEN
                    RETURN FALSE;
                END IF;
            ELSEIF p_extract_type = 'final_for_partial' THEN
                -- يمكن إضافة أمر عمل للنهائي للجزئية فقط إذا كان في جزئي ولم يكن في نهائي عادي
                IF v_in_partial = 0 OR v_in_final_regular > 0 THEN
                    RETURN FALSE;
                END IF;
            END IF;
            
            RETURN TRUE;
        END
    ");
    
    echo "   ✅ تم إنشاء دالة التحقق بنجاح\n\n";
    
    // 2. إنشاء Triggers للتحقق قبل الإدراج
    echo "2. إنشاء Triggers للتحقق التلقائي...\n";
    
    // Trigger للمستخلصات الجزئية
    echo "   - Trigger للمستخلصات الجزئية...\n";
    $db->exec("DROP TRIGGER IF EXISTS before_insert_partial_extract_work_order");
    $db->exec("
        CREATE TRIGGER before_insert_partial_extract_work_order
        BEFORE INSERT ON partial_extract_work_orders
        FOR EACH ROW
        BEGIN
            DECLARE v_conflict BOOLEAN;
            
            -- التحقق من عدم وجود تعارض
            SET v_conflict = check_work_order_extract_conflict(NEW.work_order_id, 'partial');
            
            IF NOT v_conflict THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'لا يمكن إضافة أمر العمل للمستخلص الجزئي: الأمر موجود في مستخلص نهائي عادي';
            END IF;
        END
    ");
    
    // Trigger للمستخلصات النهائية العادية
    echo "   - Trigger للمستخلصات النهائية العادية...\n";
    $db->exec("DROP TRIGGER IF EXISTS before_insert_final_regular_extract_work_order");
    $db->exec("
        CREATE TRIGGER before_insert_final_regular_extract_work_order
        BEFORE INSERT ON final_regular_extract_work_orders
        FOR EACH ROW
        BEGIN
            DECLARE v_conflict BOOLEAN;
            
            -- التحقق من عدم وجود تعارض
            SET v_conflict = check_work_order_extract_conflict(NEW.work_order_id, 'final_regular');
            
            IF NOT v_conflict THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'لا يمكن إضافة أمر العمل للمستخلص النهائي العادي: الأمر موجود في مستخلص جزئي أو نهائي للجزئية';
            END IF;
        END
    ");
    
    // Trigger للمستخلصات النهائية للجزئية
    echo "   - Trigger للمستخلصات النهائية للجزئية...\n";
    $db->exec("DROP TRIGGER IF EXISTS before_insert_final_for_partial_extract_work_order");
    $db->exec("
        CREATE TRIGGER before_insert_final_for_partial_extract_work_order
        BEFORE INSERT ON final_for_partial_extract_work_orders
        FOR EACH ROW
        BEGIN
            DECLARE v_conflict BOOLEAN;
            
            -- التحقق من عدم وجود تعارض
            SET v_conflict = check_work_order_extract_conflict(NEW.work_order_id, 'final_for_partial');
            
            IF NOT v_conflict THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'لا يمكن إضافة أمر العمل للمستخلص النهائي للجزئية: الأمر غير موجود في مستخلص جزئي أو موجود في مستخلص نهائي عادي';
            END IF;
        END
    ");
    
    echo "   ✅ تم إنشاء جميع Triggers بنجاح\n\n";
    
    // 3. إنشاء دالة للتحقق من صحة البيانات الحالية
    echo "3. التحقق من صحة البيانات الحالية...\n";
    
    // التحقق من أوامر العمل في جزئي ونهائي عادي معاً
    $conflictQuery1 = "
        SELECT wo.id, wo.work_order_number,
               pe.extract_number as partial_extract,
               fre.extract_number as final_regular_extract
        FROM work_orders wo
        INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
        INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
        INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        LIMIT 10
    ";
    
    $conflicts1 = $db->query($conflictQuery1)->fetchAll();
    
    if (count($conflicts1) > 0) {
        echo "   ⚠️ تحذير: وجدت " . count($conflicts1) . " أوامر عمل في جزئي ونهائي عادي معاً:\n";
        foreach ($conflicts1 as $conflict) {
            echo "      - أمر عمل: {$conflict['work_order_number']}\n";
            echo "        المستخلص الجزئي: {$conflict['partial_extract']}\n";
            echo "        المستخلص النهائي العادي: {$conflict['final_regular_extract']}\n";
        }
        echo "\n";
    } else {
        echo "   ✅ لا توجد تعارضات: جزئي + نهائي عادي\n";
    }
    
    // التحقق من أوامر العمل في نهائي عادي ونهائي للجزئية معاً
    $conflictQuery2 = "
        SELECT wo.id, wo.work_order_number,
               fre.extract_number as final_regular_extract,
               ffpe.extract_number as final_for_partial_extract
        FROM work_orders wo
        INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LIMIT 10
    ";
    
    $conflicts2 = $db->query($conflictQuery2)->fetchAll();
    
    if (count($conflicts2) > 0) {
        echo "   ⚠️ تحذير: وجدت " . count($conflicts2) . " أوامر عمل في نهائي عادي ونهائي للجزئية معاً:\n";
        foreach ($conflicts2 as $conflict) {
            echo "      - أمر عمل: {$conflict['work_order_number']}\n";
            echo "        المستخلص النهائي العادي: {$conflict['final_regular_extract']}\n";
            echo "        المستخلص النهائي للجزئية: {$conflict['final_for_partial_extract']}\n";
        }
        echo "\n";
    } else {
        echo "   ✅ لا توجد تعارضات: نهائي عادي + نهائي للجزئية\n";
    }
    
    // التحقق من أوامر العمل في نهائي للجزئية بدون جزئي
    $conflictQuery3 = "
        SELECT wo.id, wo.work_order_number,
               ffpe.extract_number as final_for_partial_extract
        FROM work_orders wo
        INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
        WHERE pewo.id IS NULL
        LIMIT 10
    ";
    
    $conflicts3 = $db->query($conflictQuery3)->fetchAll();
    
    if (count($conflicts3) > 0) {
        echo "   ⚠️ تحذير: وجدت " . count($conflicts3) . " أوامر عمل في نهائي للجزئية بدون جزئي:\n";
        foreach ($conflicts3 as $conflict) {
            echo "      - أمر عمل: {$conflict['work_order_number']}\n";
            echo "        المستخلص النهائي للجزئية: {$conflict['final_for_partial_extract']}\n";
        }
        echo "\n";
    } else {
        echo "   ✅ لا توجد تعارضات: نهائي للجزئية بدون جزئي\n";
    }
    
    echo "\n";
    
    // 4. إنشاء View لعرض حالة أوامر العمل في المستخلصات
    echo "4. إنشاء View لعرض حالة أوامر العمل...\n";
    
    $db->exec("DROP VIEW IF EXISTS work_order_extract_status");
    $db->exec("
        CREATE VIEW work_order_extract_status AS
        SELECT 
            wo.id,
            wo.work_order_number,
            wo.estimated_value,
            wo.actual_value,
            CASE 
                WHEN pewo.id IS NOT NULL THEN 1 
                ELSE 0 
            END as in_partial,
            CASE 
                WHEN frewo.id IS NOT NULL THEN 1 
                ELSE 0 
            END as in_final_regular,
            CASE 
                WHEN ffpewo.id IS NOT NULL THEN 1 
                ELSE 0 
            END as in_final_for_partial,
            COALESCE(SUM(pewo.extract_value), 0) as partial_total,
            COALESCE(SUM(frewo.extract_value), 0) as final_regular_total,
            COALESCE(SUM(ffpewo.extract_value), 0) as final_for_partial_total,
            COALESCE(SUM(pewo.extract_value), 0) + 
            COALESCE(SUM(frewo.extract_value), 0) + 
            COALESCE(SUM(ffpewo.extract_value), 0) as total_extracted
        FROM work_orders wo
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
        LEFT JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        LEFT JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        GROUP BY wo.id
    ");
    
    echo "   ✅ تم إنشاء View بنجاح\n\n";
    
    echo "=== اكتمل التنفيذ بنجاح ===\n\n";
    
    echo "📊 الملخص:\n";
    echo "✅ تم إنشاء دالة التحقق من التعارضات\n";
    echo "✅ تم إنشاء 3 Triggers للتحقق التلقائي\n";
    echo "✅ تم إنشاء View لعرض حالة أوامر العمل\n";
    echo "✅ تم التحقق من البيانات الحالية\n\n";
    
    echo "📝 ملاحظات:\n";
    echo "- الآن لن يمكن إضافة أمر عمل في مستخلصات متعارضة\n";
    echo "- يمكن استخدام View 'work_order_extract_status' لعرض حالة أي أمر عمل\n";
    echo "- إذا وجدت تعارضات في البيانات الحالية، يجب تصحيحها يدوياً\n\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
    exit(1);
}

