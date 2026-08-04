<?php
/**
 * إضافة أعمدة قيود إلى جداول المستخلصات
 * Add Qoyod columns to extracts tables
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🚀 إضافة أعمدة قيود إلى جداول المستخلصات...\n\n";
    
    $tables = ['partial_extracts', 'final_regular_extracts', 'final_for_partial_extracts'];
    
    foreach ($tables as $table) {
        echo "التحقق من جدول $table...\n";
        
        $columns = [
            'qoyod_invoice_id' => "INT NULL",
            'qoyod_invoice_reference' => "VARCHAR(100) NULL",
            'qoyod_status' => "ENUM('not_synced', 'synced', 'error') DEFAULT 'not_synced'"
        ];
        
        foreach ($columns as $column => $definition) {
            try {
                $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
                echo "✅ تم إضافة حقل $column إلى جدول $table\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "ℹ️  حقل $column موجود بالفعل في جدول $table\n";
                } else {
                    throw $e;
                }
            }
        }
        echo "\n";
    }
    
    echo "✅ تم إضافة وتحديث كافة حقول قيود بنجاح!\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
