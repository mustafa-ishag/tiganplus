<?php
/**
 * إنشاء جدول إعدادات قيود
 * Create Qoyod Settings Table
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🚀 إنشاء جدول إعدادات قيود...\n\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS qoyod_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_key VARCHAR(255) NULL,
            default_contact_id INT NULL,
            connections_product_id INT NULL,
            projects_product_id INT NULL,
            connections_project_id INT NULL,
            projects_project_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // إدخال صف افتراضي إذا كان الجدول فارغاً
    $stmt = $db->query("SELECT COUNT(*) FROM qoyod_settings");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO qoyod_settings (created_at) VALUES (CURRENT_TIMESTAMP)");
        echo "ℹ️  تم إضافة صف إعدادات افتراضي.\n";
    }
    
    echo "✅ تم إنشاء جدول إعدادات قيود (qoyod_settings) بنجاح!\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
