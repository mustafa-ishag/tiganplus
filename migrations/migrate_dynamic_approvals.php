<?php
/**
 * Migration: تحويل نظام الاعتمادات إلى نظام ديناميكي
 * يجب تشغيل هذا الملف مرة واحدة فقط
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

echo "=== بدء Migration: نظام الاعتمادات الديناميكي ===\n\n";

try {
    $db->beginTransaction();

    // 1. إنشاء جدول approval_steps
    echo "1. إنشاء جدول approval_steps...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS approval_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            step_order INT NOT NULL,
            step_name VARCHAR(100) NOT NULL,
            step_key VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_final TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_step_order (step_order),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ تم إنشاء جدول approval_steps\n";

    // 2. إدراج الخطوتين الافتراضيتين
    echo "2. إدراج الخطوات الافتراضية...\n";
    $existingSteps = $db->query("SELECT COUNT(*) FROM approval_steps")->fetchColumn();
    if ($existingSteps == 0) {
        $db->exec("
            INSERT INTO approval_steps (step_order, step_name, step_key, description, is_active, is_final) VALUES
            (1, 'اعتماد المستودع', 'warehouse', 'موافقة أمين المستودع على توفر المواد', 1, 0),
            (2, 'اعتماد المشروع', 'project', 'موافقة مدير المشروع النهائية وخصم المخزون', 1, 1)
        ");
        echo "   ✓ تم إدراج خطوتين افتراضيتين\n";
    } else {
        echo "   ⚠ الخطوات موجودة بالفعل، تم تخطيها\n";
    }

    // 3. إنشاء جدول request_approval_logs
    echo "3. إنشاء جدول request_approval_logs...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS request_approval_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            step_id INT NOT NULL,
            action ENUM('approved', 'rejected') NOT NULL,
            approved_by INT NOT NULL,
            notes TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_request_id (request_id),
            INDEX idx_step_id (step_id),
            INDEX idx_approved_by (approved_by),
            FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
            FOREIGN KEY (step_id) REFERENCES approval_steps(id),
            FOREIGN KEY (approved_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ تم إنشاء جدول request_approval_logs\n";

    // 4. تعديل جدول material_requests
    echo "4. تعديل جدول material_requests...\n";

    // تغيير status من ENUM إلى VARCHAR
    $db->exec("
        ALTER TABLE material_requests
        MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'draft'
    ");
    echo "   ✓ تم تغيير status إلى VARCHAR\n";

    // 5. تعديل جدول approval_assignments — إضافة step_id
    echo "5. تعديل جدول approval_assignments...\n";

    // التحقق من وجود العمود
    $columns = $db->query("SHOW COLUMNS FROM approval_assignments LIKE 'step_id'")->fetchAll();
    if (empty($columns)) {
        $db->exec("
            ALTER TABLE approval_assignments
            ADD COLUMN step_id INT NULL AFTER approval_type,
            ADD INDEX idx_step_id (step_id),
            ADD FOREIGN KEY (step_id) REFERENCES approval_steps(id)
        ");
        echo "   ✓ تم إضافة عمود step_id\n";
    } else {
        echo "   ⚠ عمود step_id موجود بالفعل\n";
    }

    // تغيير approval_type من ENUM إلى VARCHAR
    $db->exec("
        ALTER TABLE approval_assignments
        MODIFY COLUMN approval_type VARCHAR(50) NOT NULL
    ");
    echo "   ✓ تم تغيير approval_type إلى VARCHAR\n";

    // 6. ربط التعيينات الحالية بالخطوات الجديدة
    echo "6. ربط التعيينات الحالية بالخطوات...\n";
    $db->exec("
        UPDATE approval_assignments aa
        JOIN approval_steps ast ON aa.approval_type = ast.step_key
        SET aa.step_id = ast.id
        WHERE aa.step_id IS NULL
    ");
    echo "   ✓ تم ربط التعيينات الحالية\n";

    // 7. تنظيف البيانات التجريبية القديمة
    echo "7. تنظيف البيانات التجريبية...\n";
    $db->exec("DELETE FROM material_request_details");
    $db->exec("DELETE FROM material_requests");
    echo "   ✓ تم حذف البيانات التجريبية القديمة\n";

    $db->commit();
    echo "\n=== ✓ تمت Migration بنجاح! ===\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\n=== ✗ فشل Migration: " . $e->getMessage() . " ===\n";
    exit(1);
}
