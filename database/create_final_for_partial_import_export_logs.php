<?php
/**
 * إنشاء جدول سجلات الاستيراد والتصدير للمستخلصات النهائية للجزئية
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "إنشاء جدول سجلات الاستيراد والتصدير للمستخلصات النهائية للجزئية...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_for_partial_extract_import_export_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            operation_type ENUM('import', 'export') NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            total_records INT DEFAULT 0,
            successful_records INT DEFAULT 0,
            failed_records INT DEFAULT 0,
            duplicates_found INT DEFAULT 0,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_operation_type (operation_type),
            INDEX idx_status (status),
            INDEX idx_started_at (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ تم إنشاء جدول final_for_partial_extract_import_export_logs بنجاح\n";
    
} catch (Exception $e) {
    echo "✗ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}

