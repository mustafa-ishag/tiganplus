<?php
/**
 * إضافة نموذج استلام الأصول (إجراء 211) إلى جدول work_order_attachments
 * Add assets receipt form (procedure 211) to work_order_attachments table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();

    echo "=== إضافة نموذج استلام الأصول (إجراء 211) ===\n\n";

    // الخطوة 1: تحديث ENUM لإضافة assets_receipt_form
    echo "الخطوة 1: إضافة نوع 'assets_receipt_form' إلى قائمة أنواع النماذج...\n";

    $sql = "
        ALTER TABLE work_order_attachments
        MODIFY COLUMN form_type ENUM(
            'excavation_form',
            'precise_drilling_form',
            'demolition_form',
            'f1_form',
            'completion_certificate',
            'other_document',
            'assets_receipt_form'
        ) NOT NULL COMMENT 'نوع النموذج'
    ";

    $db->exec($sql);
    echo "✅ تم إضافة نوع 'assets_receipt_form' بنجاح\n\n";

    // الخطوة 2: إضافة النموذج لجميع أوامر العمل الموجودة
    echo "الخطوة 2: إضافة نموذج استلام الأصول لجميع أوامر العمل الموجودة...\n";

    // جلب جميع أوامر العمل
    $stmt = $db->query("SELECT id FROM work_orders");
    $workOrders = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $addedCount = 0;
    foreach ($workOrders as $workOrderId) {
        // التحقق من عدم وجود النموذج مسبقاً
        $checkStmt = $db->prepare("
            SELECT COUNT(*) FROM work_order_attachments 
            WHERE work_order_id = ? AND form_type = 'assets_receipt_form'
        ");
        $checkStmt->execute([$workOrderId]);

        if ($checkStmt->fetchColumn() == 0) {
            // إضافة النموذج بحالة افتراضية "لا ينطبق"
            $insertStmt = $db->prepare("
                INSERT INTO work_order_attachments
                (work_order_id, form_type, status, created_at, updated_at)
                VALUES (?, 'assets_receipt_form', 'not_applicable', NOW(), NOW())
            ");
            $insertStmt->execute([$workOrderId]);
            $addedCount++;
        }
    }

    echo "✅ تم إضافة نموذج استلام الأصول لـ $addedCount أمر عمل\n\n";

    echo "\n✅ اكتمل التحديث بنجاح!\n";
    echo "\nتم إضافة نموذج استلام الأصول (إجراء 211) بنجاح\n";
    echo "الحالات المتاحة: مرفق، غير مرفق، لا ينطبق\n";

} catch (PDOException $e) {
    echo "❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>

