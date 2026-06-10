<?php

declare(strict_types=1);

// تحميل التطبيق
require_once __DIR__ . '/../bootstrap/app.php';

use EtganERP\Infrastructure\Database\DatabaseConnection;

try {
    echo "إنشاء جدول النماذج المرفقة لأوامر العمل...\n";

    // إنشاء جدول work_order_attachments
    $sql = "
    CREATE TABLE IF NOT EXISTS work_order_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_id INT NOT NULL COMMENT 'معرف أمر العمل',
        form_type ENUM(
            'excavation_form',
            'precise_drilling_form', 
            'demolition_form',
            'f1_form',
            'completion_certificate'
        ) NOT NULL COMMENT 'نوع النموذج',
        status ENUM(
            'attached',
            'not_attached',
            'not_applicable'
        ) NOT NULL DEFAULT 'not_attached' COMMENT 'حالة النموذج',
        completion_certificate_confirmation ENUM(
            'empty',
            'accepted',
            'rejected',
            'confirmed'
        ) DEFAULT 'empty' COMMENT 'تأكيد شهادة الإنجاز',
        file_path VARCHAR(500) NULL COMMENT 'مسار الملف المرفق',
        original_filename VARCHAR(255) NULL COMMENT 'اسم الملف الأصلي',
        file_size INT NULL COMMENT 'حجم الملف بالبايت',
        file_type VARCHAR(100) NULL COMMENT 'نوع الملف',
        uploaded_by INT NULL COMMENT 'معرف المستخدم الذي رفع الملف',
        uploaded_at TIMESTAMP NULL COMMENT 'تاريخ رفع الملف',
        notes TEXT NULL COMMENT 'ملاحظات',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        -- Foreign Keys
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_work_order (work_order_id),
        INDEX idx_form_type (form_type),
        INDEX idx_status (status),
        INDEX idx_completion_confirmation (completion_certificate_confirmation),
        INDEX idx_uploaded_by (uploaded_by),
        INDEX idx_uploaded_at (uploaded_at),
        
        -- Composite Indexes
        INDEX idx_work_order_form (work_order_id, form_type),
        INDEX idx_work_order_status (work_order_id, status),
        
        -- Unique constraint to prevent duplicate form types per work order
        UNIQUE KEY unique_work_order_form (work_order_id, form_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول النماذج المرفقة لأوامر العمل';
    ";

    DatabaseConnection::execute($sql);
    echo "✅ تم إنشاء جدول work_order_attachments بنجاح\n";

    // إضافة النماذج الافتراضية لأوامر العمل الموجودة
    echo "\nإضافة النماذج الافتراضية لأوامر العمل الموجودة...\n";

    // جلب جميع أوامر العمل
    $workOrders = DatabaseConnection::fetchAll("SELECT id FROM work_orders");

    // أنواع النماذج
    $formTypes = [
        'excavation_form',
        'precise_drilling_form',
        'demolition_form',
        'f1_form',
        'completion_certificate'
    ];

    foreach ($workOrders as $workOrder) {
        foreach ($formTypes as $formType) {
            // تحديد الحالة الافتراضية
            $defaultStatus = 'not_attached';
            $completionConfirmation = null;
            
            // إعداد خاص لشهادة الإنجاز
            if ($formType === 'completion_certificate') {
                $completionConfirmation = 'empty';
            }

            $insertSql = "
            INSERT INTO work_order_attachments (
                work_order_id, form_type, status, completion_certificate_confirmation, created_at
            ) VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
            ";

            DatabaseConnection::execute($insertSql, [
                $workOrder['id'],
                $formType,
                $defaultStatus,
                $completionConfirmation
            ]);
        }
        
        echo "✅ تم إضافة النماذج لأمر العمل ID: {$workOrder['id']}\n";
    }

    // إنشاء مجلد التحميلات إذا لم يكن موجوداً
    $uploadsDir = __DIR__ . '/../public/uploads/work-orders';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
        echo "✅ تم إنشاء مجلد التحميلات: $uploadsDir\n";
    }

    // إنشاء ملف .htaccess لحماية المجلد
    $htaccessContent = "
# منع الوصول المباشر للملفات
Options -Indexes

# السماح فقط بأنواع ملفات محددة
<FilesMatch \"\\.(pdf|doc|docx|jpg|jpeg|png|gif)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# منع تنفيذ ملفات PHP
<FilesMatch \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">
    Order Deny,Allow
    Deny from all
</FilesMatch>
";

    file_put_contents($uploadsDir . '/.htaccess', $htaccessContent);
    echo "✅ تم إنشاء ملف .htaccess للحماية\n";

    echo "\n🎉 تم إنشاء جدول النماذج المرفقة وإعداد النماذج الافتراضية بنجاح!\n";
    echo "📊 تم إضافة " . (count($workOrders) * count($formTypes)) . " نموذج افتراضي\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
