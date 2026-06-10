<?php
/**
 * حذف النظام القديم للمستخلصات
 * Remove Old Extracts System
 * 
 * يحذف الجداول القديمة:
 * - extracts
 * - extract_work_orders  
 * - extract_attachments
 * - extract_activities
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🗑️ حذف النظام القديم للمستخلصات...\n\n";
    
    // 1. التحقق من وجود الجداول القديمة
    echo "1. التحقق من الجداول القديمة:\n";
    
    $oldTables = [
        'extracts' => 'المستخلصات الرئيسي',
        'extract_work_orders' => 'أوامر العمل المرتبطة',
        'extract_attachments' => 'مرفقات المستخلصات',
        'extract_activities' => 'أنشطة المستخلصات'
    ];
    
    $existingTables = [];
    $tableCounts = [];
    
    foreach ($oldTables as $table => $description) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $existingTables[] = $table;
            $tableCounts[$table] = $count;
            echo "   📋 $description ($table): $count سجل\n";
        } catch (Exception $e) {
            echo "   ❌ $description ($table): غير موجود\n";
        }
    }
    
    if (empty($existingTables)) {
        echo "\n✅ لا توجد جداول قديمة للحذف!\n";
        exit();
    }
    
    // 2. عرض ملخص البيانات التي ستُحذف
    echo "\n2. ملخص البيانات التي ستُحذف:\n";
    $totalRecords = 0;
    foreach ($tableCounts as $table => $count) {
        echo "   🗑️ $table: $count سجل\n";
        $totalRecords += $count;
    }
    echo "   📊 إجمالي السجلات: $totalRecords سجل\n";
    
    // 3. التحقق من النظام الجديد
    echo "\n3. التحقق من النظام الجديد:\n";
    
    $newTables = [
        'partial_extracts' => 'المستخلصات الجزئية',
        'final_regular_extracts' => 'المستخلصات النهائية العادية',
        'final_for_partial_extracts' => 'المستخلصات النهائية للجزئية'
    ];
    
    $newSystemExists = true;
    foreach ($newTables as $table => $description) {
        try {
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "   ✅ $description ($table): $count سجل\n";
        } catch (Exception $e) {
            echo "   ❌ $description ($table): غير موجود\n";
            $newSystemExists = false;
        }
    }
    
    if (!$newSystemExists) {
        echo "\n⚠️ تحذير: النظام الجديد غير مكتمل! لا يُنصح بحذف النظام القديم.\n";
        echo "يرجى التأكد من إنشاء النظام الجديد أولاً.\n";
        exit();
    }
    
    // 4. بدء عملية الحذف
    echo "\n4. بدء عملية حذف الجداول القديمة...\n";
    
    $db->beginTransaction();
    
    // حذف الجداول بالترتيب الصحيح (الجداول التابعة أولاً)
    $deleteOrder = [
        'extract_activities' => 'أنشطة المستخلصات',
        'extract_attachments' => 'مرفقات المستخلصات', 
        'extract_work_orders' => 'أوامر العمل المرتبطة',
        'extracts' => 'المستخلصات الرئيسي'
    ];
    
    foreach ($deleteOrder as $table => $description) {
        if (in_array($table, $existingTables)) {
            try {
                $db->exec("DROP TABLE IF EXISTS $table");
                echo "   ✅ تم حذف $description ($table)\n";
            } catch (Exception $e) {
                echo "   ❌ فشل حذف $description ($table): " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    }
    
    $db->commit();
    
    // 5. التحقق من نجاح الحذف
    echo "\n5. التحقق من نجاح الحذف:\n";
    
    foreach ($oldTables as $table => $description) {
        try {
            $db->query("SELECT 1 FROM $table LIMIT 1");
            echo "   ❌ $description ($table): ما زال موجود!\n";
        } catch (Exception $e) {
            echo "   ✅ $description ($table): تم الحذف بنجاح\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 تم حذف النظام القديم بنجاح!\n\n";
    
    echo "📋 ملخص العملية:\n";
    echo "   🗑️ تم حذف 4 جداول قديمة\n";
    echo "   📊 تم حذف $totalRecords سجل\n";
    echo "   ✅ النظام الجديد محفوظ ويعمل\n\n";
    
    echo "🆕 النظام الجديد المتاح:\n";
    echo "   📋 الصفحة الرئيسية: /extracts/index-new.php\n";
    echo "   🔵 مستخلص جزئي: /extracts/partial/create.php\n";
    echo "   🟢 مستخلص نهائي عادي: /extracts/final-regular/create.php\n";
    echo "   🟡 مستخلص نهائي للجزئية: /extracts/final-for-partial/create.php\n\n";
    
    echo "⚠️ ملاحظة: تأكد من تحديث الروابط في القائمة الرئيسية!\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
