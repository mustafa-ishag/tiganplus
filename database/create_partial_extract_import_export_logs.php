<?php
/**
 * إنشاء جدول سجل عمليات التصدير والاستيراد للمستخلصات الجزئية
 * Create Partial Extract Import/Export Logs Table
 */

// تعريف الثابت المطلوب
define('TIQAN_SYSTEM', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🗃️ إنشاء جدول سجل عمليات التصدير والاستيراد للمستخلصات الجزئية...\n\n";
    
    // إنشاء جدول سجل العمليات
    $createLogsTable = "
    CREATE TABLE IF NOT EXISTS partial_extract_import_export_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operation_type ENUM('export', 'import') NOT NULL COMMENT 'نوع العملية',
        file_name VARCHAR(255) NOT NULL COMMENT 'اسم الملف',
        file_path VARCHAR(500) NULL COMMENT 'مسار الملف',
        file_size INT NULL COMMENT 'حجم الملف بالبايت',
        
        -- تفاصيل العملية
        total_records INT NOT NULL DEFAULT 0 COMMENT 'إجمالي السجلات',
        successful_records INT NOT NULL DEFAULT 0 COMMENT 'السجلات الناجحة',
        failed_records INT NOT NULL DEFAULT 0 COMMENT 'السجلات الفاشلة',
        
        -- تفاصيل المستخلصات
        extracts_processed INT NOT NULL DEFAULT 0 COMMENT 'عدد المستخلصات المعالجة',
        work_orders_processed INT NOT NULL DEFAULT 0 COMMENT 'عدد أوامر العمل المعالجة',
        duplicates_found INT NOT NULL DEFAULT 0 COMMENT 'عدد التكرارات الموجودة',
        updates_made INT NOT NULL DEFAULT 0 COMMENT 'عدد التحديثات المنجزة',
        
        -- معلومات الحالة
        status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'حالة العملية',
        error_message TEXT NULL COMMENT 'رسالة الخطأ إن وجدت',
        processing_details JSON NULL COMMENT 'تفاصيل المعالجة',
        
        -- معلومات المستخدم والوقت
        user_id INT NOT NULL COMMENT 'معرف المستخدم',
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'وقت بداية العملية',
        completed_at TIMESTAMP NULL COMMENT 'وقت انتهاء العملية',
        
        -- فهارس
        INDEX idx_operation_type (operation_type),
        INDEX idx_status (status),
        INDEX idx_user_id (user_id),
        INDEX idx_started_at (started_at),
        INDEX idx_completed_at (completed_at),
        
        -- مفاتيح خارجية
        FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل عمليات التصدير والاستيراد للمستخلصات الجزئية';
    ";
    
    $db->exec($createLogsTable);
    echo "✅ تم إنشاء جدول partial_extract_import_export_logs بنجاح\n";
    
    // إنشاء جدول تفاصيل الأخطاء
    $createErrorDetailsTable = "
    CREATE TABLE IF NOT EXISTS partial_extract_import_errors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        log_id INT NOT NULL COMMENT 'معرف سجل العملية',
        row_number INT NOT NULL COMMENT 'رقم الصف في الملف',
        extract_number VARCHAR(20) NULL COMMENT 'رقم المستخلص',
        work_order_number VARCHAR(9) NULL COMMENT 'رقم أمر العمل',
        error_type ENUM('validation', 'duplicate', 'database', 'business_rule') NOT NULL COMMENT 'نوع الخطأ',
        error_message TEXT NOT NULL COMMENT 'رسالة الخطأ',
        field_name VARCHAR(100) NULL COMMENT 'اسم الحقل المتسبب في الخطأ',
        field_value TEXT NULL COMMENT 'قيمة الحقل المتسبب في الخطأ',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- فهارس
        INDEX idx_log_id (log_id),
        INDEX idx_error_type (error_type),
        INDEX idx_extract_number (extract_number),
        INDEX idx_work_order_number (work_order_number),
        
        -- مفاتيح خارجية
        FOREIGN KEY (log_id) REFERENCES partial_extract_import_export_logs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تفاصيل أخطاء استيراد المستخلصات الجزئية';
    ";
    
    $db->exec($createErrorDetailsTable);
    echo "✅ تم إنشاء جدول partial_extract_import_errors بنجاح\n";
    
    // إنشاء مجلد التحميلات إذا لم يكن موجوداً
    $uploadsDir = __DIR__ . '/../public/uploads/partial_extracts';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
        echo "✅ تم إنشاء مجلد التحميلات: $uploadsDir\n";
    }
    
    // إنشاء ملف .htaccess لحماية المجلد
    $htaccessContent = "# منع الوصول المباشر للملفات\nDeny from all\n\n# السماح فقط بملفات Excel و CSV\n<FilesMatch \"\\.(xlsx|xls|csv)$\">\n    Allow from all\n</FilesMatch>";
    file_put_contents($uploadsDir . '/.htaccess', $htaccessContent);
    echo "✅ تم إنشاء ملف الحماية .htaccess\n";
    
    echo "\n🎉 تم إنشاء نظام سجل عمليات التصدير والاستيراد بنجاح!\n";
    echo "\n📋 الجداول المنشأة:\n";
    echo "  1. partial_extract_import_export_logs - سجل العمليات الرئيسي\n";
    echo "  2. partial_extract_import_errors - تفاصيل الأخطاء\n";
    echo "\n📁 المجلدات المنشأة:\n";
    echo "  1. public/uploads/partial_extracts - مجلد تحميل الملفات\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
