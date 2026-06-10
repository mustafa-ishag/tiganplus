<?php

declare(strict_types=1);

/**
 * إنشاء جدول سجل عمليات التصدير والاستيراد لأنواع أوامر العمل
 * Create Work Order Type Import/Export Logs Table
 */

// تحميل التطبيق
require_once __DIR__ . '/../bootstrap/app.php';

use EtganERP\Infrastructure\Database\DatabaseConnection;

try {
    echo "إنشاء جدول سجل عمليات التصدير والاستيراد لأنواع أوامر العمل...\n";

    // إنشاء جدول work_order_type_import_export_logs
    $sql = "
    CREATE TABLE IF NOT EXISTS work_order_type_import_export_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operation_type ENUM('import', 'export') NOT NULL COMMENT 'نوع العملية: استيراد أو تصدير',
        file_name VARCHAR(255) NULL COMMENT 'اسم الملف',
        file_format ENUM('csv', 'excel', 'xlsx') NULL COMMENT 'صيغة الملف',
        total_records INT DEFAULT 0 COMMENT 'إجمالي عدد السجلات',
        successful_records INT DEFAULT 0 COMMENT 'عدد السجلات الناجحة',
        failed_records INT DEFAULT 0 COMMENT 'عدد السجلات الفاشلة',
        operation_status ENUM('processing', 'completed', 'failed', 'cancelled') DEFAULT 'processing' COMMENT 'حالة العملية',
        error_message TEXT NULL COMMENT 'رسالة الخطأ في حالة الفشل',
        export_filters JSON NULL COMMENT 'مرشحات التصدير المطبقة',
        created_by INT NOT NULL COMMENT 'معرف المستخدم الذي قام بالعملية',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ إنشاء العملية',
        completed_at TIMESTAMP NULL COMMENT 'تاريخ اكتمال العملية',
        
        -- Indexes
        INDEX idx_operation_type (operation_type),
        INDEX idx_operation_status (operation_status),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at),
        INDEX idx_operation_date (operation_type, created_at),
        
        -- Foreign Key
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول سجل عمليات التصدير والاستيراد لأنواع أوامر العمل';
    ";

    DatabaseConnection::execute($sql);
    echo "✅ تم إنشاء جدول work_order_type_import_export_logs بنجاح\n";

    // إضافة بعض البيانات التجريبية (اختيارية)
    echo "\nإضافة بيانات تجريبية...\n";
    
    $sampleData = [
        [
            'operation_type' => 'export',
            'file_name' => 'work_order_types_export_' . date('Y-m-d_H-i-s') . '.xlsx',
            'file_format' => 'excel',
            'total_records' => 8,
            'successful_records' => 8,
            'failed_records' => 0,
            'operation_status' => 'completed',
            'export_filters' => json_encode(['status' => 'active']),
            'created_by' => 1,
            'completed_at' => date('Y-m-d H:i:s')
        ],
        [
            'operation_type' => 'import',
            'file_name' => 'work_order_types_import.csv',
            'file_format' => 'csv',
            'total_records' => 5,
            'successful_records' => 4,
            'failed_records' => 1,
            'operation_status' => 'completed',
            'error_message' => 'سجل واحد يحتوي على كود مكرر',
            'created_by' => 1,
            'completed_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]
    ];

    $insertQuery = "
        INSERT INTO work_order_type_import_export_logs 
        (operation_type, file_name, file_format, total_records, successful_records, failed_records, 
         operation_status, error_message, export_filters, created_by, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    foreach ($sampleData as $data) {
        try {
            DatabaseConnection::execute($insertQuery, [
                $data['operation_type'],
                $data['file_name'],
                $data['file_format'],
                $data['total_records'],
                $data['successful_records'],
                $data['failed_records'],
                $data['operation_status'],
                $data['error_message'] ?? null,
                $data['export_filters'] ?? null,
                $data['created_by'],
                $data['completed_at']
            ]);
            echo "✅ تم إدراج سجل تجريبي: {$data['operation_type']}\n";
        } catch (Exception $e) {
            echo "⚠️ خطأ في إدراج البيانات التجريبية: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== تم الانتهاء بنجاح ===\n";
    echo "تم إنشاء جدول سجل عمليات التصدير والاستيراد لأنواع أوامر العمل\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nتم تنفيذ العملية بنجاح!\n";
?>
