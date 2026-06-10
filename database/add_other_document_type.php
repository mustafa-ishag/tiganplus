<?php
/**
 * إضافة نوع "مستندات أخرى" إلى جدول work_order_attachments
 * Add "other_document" type to work_order_attachments table
 */

require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();

    echo "=== تحديث جدول work_order_attachments ===\n\n";

    // الخطوة 1: إزالة القيد الفريد أولاً
    echo "الخطوة 1: إزالة القيد الفريد للسماح بعدة مستندات أخرى...\n";

    try {
        $db->exec("ALTER TABLE work_order_attachments DROP INDEX unique_work_order_form");
        echo "✅ تم إزالة القيد الفريد بنجاح\n\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "check that column/key exists") !== false ||
            strpos($e->getMessage(), "Can't DROP") !== false) {
            echo "ℹ️ القيد الفريد غير موجود (تم تجاهله)\n\n";
        } else {
            echo "⚠️ تحذير: " . $e->getMessage() . "\n\n";
        }
    }

    // الخطوة 2: تحديث ENUM لإضافة other_document
    echo "الخطوة 2: إضافة نوع 'other_document' إلى قائمة أنواع النماذج...\n";

    $sql = "
        ALTER TABLE work_order_attachments
        MODIFY COLUMN form_type ENUM(
            'excavation_form',
            'precise_drilling_form',
            'demolition_form',
            'f1_form',
            'completion_certificate',
            'other_document'
        ) NOT NULL COMMENT 'نوع النموذج'
    ";

    $db->exec($sql);

    echo "✅ تم إضافة نوع 'other_document' بنجاح\n\n";
    
    echo "\n✅ اكتمل التحديث بنجاح!\n";
    echo "\nالآن يمكنك:\n";
    echo "1. رفع عدة مستندات أخرى لنفس أمر العمل\n";
    echo "2. كل نموذج آخر (حفر دقيق، تخريد، إلخ) يمكن أن يكون واحد فقط لكل أمر عمل\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

