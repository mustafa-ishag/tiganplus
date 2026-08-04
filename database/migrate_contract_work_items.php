<?php
/**
 * ترحيل بنود الأعمال إلى بنود خاصة بالعقود
 * Migrate Work Items to Contract Work Items
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    $db->beginTransaction();

    echo "🔄 بدء ترحيل قاعدة البيانات...\n\n";

    // 1. إنشاء العقد 4400020002 إذا لم يكن موجوداً
    $contractNumber = '4400020002';
    $stmt = $db->prepare("SELECT id FROM contracts WHERE contract_number = ?");
    $stmt->execute([$contractNumber]);
    $contract = $stmt->fetch();

    if (!$contract) {
        echo "إنشاء العقد $contractNumber...\n";
        $insertContract = $db->prepare("
            INSERT INTO contracts (contract_number, start_date, end_date, description, is_active) 
            VALUES (?, '2023-01-01', '2028-12-31', 'عقد تم إنشاؤه تلقائياً لترحيل البنود القديمة', 1)
        ");
        $insertContract->execute([$contractNumber]);
        $contractId = $db->lastInsertId();
    } else {
        $contractId = $contract['id'];
        echo "العقد $contractNumber موجود مسبقاً (ID: $contractId).\n";
    }

    // 2. إنشاء جدول contract_work_items
    echo "إنشاء جدول contract_work_items...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS `contract_work_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `contract_id` int(11) NOT NULL COMMENT 'معرف العقد',
          `item_number` varchar(50) NOT NULL COMMENT 'رقم البند',
          `description` text NOT NULL COMMENT 'وصف البند',
          `unit` varchar(20) NOT NULL COMMENT 'وحدة القياس',
          `price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'السعر الخاص بالعقد',
          `category` varchar(100) DEFAULT NULL COMMENT 'فئة العمل',
          `subcategory` varchar(100) DEFAULT NULL COMMENT 'الفئة الفرعية',
          `notes` text DEFAULT NULL COMMENT 'ملاحظات',
          `is_active` tinyint(1) DEFAULT 1 COMMENT 'نشط/غير نشط',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_contract_item` (`contract_id`, `item_number`),
          KEY `idx_contract` (`contract_id`),
          KEY `idx_item_number` (`item_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='بنود الأعمال الخاصة بالعقود'
    ");

    // 3. نقل البيانات من work_items إلى contract_work_items
    // سنحافظ على نفس الـ ID القديم لكي لا تنكسر العلاقات في الجداول الأخرى
    echo "نقل البيانات من work_items إلى العقد $contractNumber...\n";
    
    // تفريغ الجدول الجديد لضمان عدم وجود تعارض إذا تم تشغيل السكريبت مرتين
    $db->exec("TRUNCATE TABLE contract_work_items");
    
    $db->exec("
        INSERT INTO contract_work_items (
            id, contract_id, item_number, description, unit, 
            price, category, subcategory, notes, is_active, created_at, updated_at
        )
        SELECT 
            id, $contractId, item_number, description, unit, 
            standard_price, category, subcategory, notes, is_active, created_at, updated_at
        FROM work_items
    ");
    
    $migratedCount = $db->query("SELECT COUNT(*) FROM contract_work_items")->fetchColumn();
    echo "تم نقل $migratedCount بند بنجاح.\n";

    // 4. تحديث جدول completion_certificate_works
    echo "تحديث جدول completion_certificate_works...\n";
    $db->exec("
        ALTER TABLE completion_certificate_works 
        CHANGE COLUMN work_item_id contract_work_item_id int(11) NOT NULL COMMENT 'معرف بند العقد'
    ");

    // 5. تحديث جدول productivity_work_items
    echo "تحديث جدول productivity_work_items...\n";
    // إزالة المفاتيح والقيود القديمة إن وجدت
    try {
        $db->exec("ALTER TABLE productivity_work_items DROP INDEX unique_work_order_item");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE productivity_work_items DROP FOREIGN KEY productivity_work_items_ibfk_2");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE productivity_work_items DROP INDEX idx_work_item");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE productivity_work_items DROP INDEX idx_work_item_status");
    } catch (Exception $e) {}
    try {
        $db->exec("ALTER TABLE productivity_work_items DROP INDEX idx_work_item_id");
    } catch (Exception $e) {}
    
    $db->exec("
        ALTER TABLE productivity_work_items 
        CHANGE COLUMN work_item_id contract_work_item_id int(11) NOT NULL COMMENT 'معرف بند العقد'
    ");
    
    // إضافة الـ Unique Key من جديد
    $db->exec("
        ALTER TABLE productivity_work_items 
        ADD UNIQUE KEY unique_work_order_item (work_order_id, contract_work_item_id)
    ");
    $db->exec("
        ALTER TABLE productivity_work_items 
        ADD INDEX idx_contract_work_item (contract_work_item_id)
    ");

    // 6. تحديث جدول material_work_items
    echo "تحديث جدول material_work_items...\n";
    $db->exec("
        ALTER TABLE material_work_items 
        CHANGE COLUMN work_item_id contract_work_item_id int(11) NOT NULL COMMENT 'معرف بند العقد'
    ");

    // 7. مسح جدول work_items القديم
    echo "حذف جدول work_items القديم...\n";
    $db->exec("DROP TABLE IF EXISTS work_items");

    $db->commit();
    echo "\n✅ تمت عملية الترحيل بنجاح!\n";

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo "\n❌ خطأ أثناء الترحيل: " . $e->getMessage() . "\n";
    exit(1);
}
