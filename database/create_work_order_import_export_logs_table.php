<?php

declare(strict_types=1);

/**
 * إنشاء جدول سجل عمليات الاستيراد والتصدير لأوامر العمل
 * Create Work Order Import/Export Logs Table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🔄 إنشاء جدول سجل عمليات الاستيراد والتصدير لأوامر العمل...\n";
    
    // إنشاء جدول work_order_import_export_logs
    $sql = "
    CREATE TABLE IF NOT EXISTS work_order_import_export_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        operation_type ENUM('import', 'export') NOT NULL COMMENT 'نوع العملية',
        file_name VARCHAR(255) NOT NULL COMMENT 'اسم الملف',
        file_format ENUM('csv', 'xlsx', 'xls') NOT NULL COMMENT 'صيغة الملف',
        total_records INT NOT NULL DEFAULT 0 COMMENT 'إجمالي السجلات',
        successful_records INT NOT NULL DEFAULT 0 COMMENT 'السجلات الناجحة',
        failed_records INT NOT NULL DEFAULT 0 COMMENT 'السجلات الفاشلة',
        operation_status ENUM('processing', 'completed', 'failed') NOT NULL DEFAULT 'processing' COMMENT 'حالة العملية',
        error_message TEXT NULL COMMENT 'رسالة الخطأ',
        export_filters JSON NULL COMMENT 'مرشحات التصدير',
        created_by INT NOT NULL COMMENT 'المستخدم المنفذ',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ البداية',
        completed_at TIMESTAMP NULL COMMENT 'تاريخ الانتهاء',
        
        -- Indexes
        INDEX idx_operation_type (operation_type),
        INDEX idx_operation_status (operation_status),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at),
        INDEX idx_file_format (file_format),
        
        -- Composite Indexes
        INDEX idx_operation_status_type (operation_status, operation_type),
        INDEX idx_created_by_date (created_by, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول سجل عمليات الاستيراد والتصدير لأوامر العمل';
    ";

    $pdo->exec($sql);
    echo "✅ تم إنشاء جدول work_order_import_export_logs بنجاح\n";
    
    // إضافة بيانات تجريبية (اختيارية)
    echo "\n📝 إضافة بيانات تجريبية...\n";
    
    $sampleData = [
        [
            'operation_type' => 'export',
            'file_name' => 'work_orders_sample_export.csv',
            'file_format' => 'csv',
            'total_records' => 150,
            'successful_records' => 150,
            'failed_records' => 0,
            'operation_status' => 'completed',
            'export_filters' => json_encode(['status' => 'all', 'department' => 'all'], JSON_UNESCAPED_UNICODE),
            'created_by' => 1,
            'completed_at' => date('Y-m-d H:i:s')
        ],
        [
            'operation_type' => 'import',
            'file_name' => 'work_orders_sample_import.csv',
            'file_format' => 'csv',
            'total_records' => 25,
            'successful_records' => 23,
            'failed_records' => 2,
            'operation_status' => 'completed',
            'error_message' => 'سجلان يحتويان على أخطاء في التحقق من صحة البيانات',
            'created_by' => 1,
            'completed_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]
    ];
    
    $insertSql = "
        INSERT INTO work_order_import_export_logs 
        (operation_type, file_name, file_format, total_records, successful_records, 
         failed_records, operation_status, error_message, export_filters, created_by, completed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $pdo->prepare($insertSql);
    
    foreach ($sampleData as $data) {
        try {
            $stmt->execute([
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
            echo "✅ تم إدراج سجل تجريبي: {$data['file_name']}\n";
        } catch (PDOException $e) {
            echo "⚠️ خطأ في إدراج البيانات التجريبية: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 تم إنشاء جدول سجل العمليات بنجاح!\n";
    echo "📊 الجدول يحتوي على الحقول التالية:\n";
    echo "   - operation_type: نوع العملية (import/export)\n";
    echo "   - file_name: اسم الملف\n";
    echo "   - file_format: صيغة الملف (csv/xlsx/xls)\n";
    echo "   - total_records: إجمالي السجلات\n";
    echo "   - successful_records: السجلات الناجحة\n";
    echo "   - failed_records: السجلات الفاشلة\n";
    echo "   - operation_status: حالة العملية\n";
    echo "   - error_message: رسالة الخطأ\n";
    echo "   - export_filters: مرشحات التصدير (JSON)\n";
    echo "   - created_by: المستخدم المنفذ\n";
    echo "   - created_at: تاريخ البداية\n";
    echo "   - completed_at: تاريخ الانتهاء\n";
    
} catch (PDOException $e) {
    echo "❌ خطأ في إنشاء الجدول: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ خطأ عام: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ تم الانتهاء من إنشاء جدول سجل العمليات بنجاح!\n";
?>
