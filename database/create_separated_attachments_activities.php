<?php
/**
 * إنشاء جداول المرفقات والأنشطة المنفصلة لكل نوع من المستخلصات
 * Create Separated Attachments and Activities Tables for Each Extract Type
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    $db->beginTransaction();
    
    echo "🚀 إنشاء جداول المرفقات والأنشطة المنفصلة...\n\n";
    
    // 1. جدول مرفقات المستخلصات الجزئية
    echo "1. إنشاء جدول مرفقات المستخلصات الجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS partial_extract_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partial_extract_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (partial_extract_id) REFERENCES partial_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_partial_extract_id (partial_extract_id),
            INDEX idx_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول مرفقات المستخلصات الجزئية\n";
    
    // 2. جدول مرفقات المستخلصات النهائية العادية
    echo "\n2. إنشاء جدول مرفقات المستخلصات النهائية العادية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_regular_extract_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            final_regular_extract_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (final_regular_extract_id) REFERENCES final_regular_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_final_regular_extract_id (final_regular_extract_id),
            INDEX idx_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول مرفقات المستخلصات النهائية العادية\n";
    
    // 3. جدول مرفقات المستخلصات النهائية للجزئية
    echo "\n3. إنشاء جدول مرفقات المستخلصات النهائية للجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_for_partial_extract_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            final_for_partial_extract_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (final_for_partial_extract_id) REFERENCES final_for_partial_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_final_for_partial_extract_id (final_for_partial_extract_id),
            INDEX idx_uploaded_at (uploaded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول مرفقات المستخلصات النهائية للجزئية\n";
    
    // 4. جدول أنشطة المستخلصات الجزئية
    echo "\n4. إنشاء جدول أنشطة المستخلصات الجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS partial_extract_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partial_extract_id INT NOT NULL,
            activity_type ENUM('created', 'updated', 'submitted', 'approved', 'rejected', 'status_changed', 'attachment_added', 'attachment_removed', 'note_added') NOT NULL,
            description TEXT NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (partial_extract_id) REFERENCES partial_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_partial_extract_id (partial_extract_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_performed_at (performed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول أنشطة المستخلصات الجزئية\n";
    
    // 5. جدول أنشطة المستخلصات النهائية العادية
    echo "\n5. إنشاء جدول أنشطة المستخلصات النهائية العادية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_regular_extract_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            final_regular_extract_id INT NOT NULL,
            activity_type ENUM('created', 'updated', 'submitted', 'approved', 'rejected', 'status_changed', 'attachment_added', 'attachment_removed', 'note_added') NOT NULL,
            description TEXT NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (final_regular_extract_id) REFERENCES final_regular_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_final_regular_extract_id (final_regular_extract_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_performed_at (performed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول أنشطة المستخلصات النهائية العادية\n";
    
    // 6. جدول أنشطة المستخلصات النهائية للجزئية
    echo "\n6. إنشاء جدول أنشطة المستخلصات النهائية للجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_for_partial_extract_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            final_for_partial_extract_id INT NOT NULL,
            activity_type ENUM('created', 'updated', 'submitted', 'approved', 'rejected', 'status_changed', 'attachment_added', 'attachment_removed', 'note_added') NOT NULL,
            description TEXT NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            performed_by INT NOT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (final_for_partial_extract_id) REFERENCES final_for_partial_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (performed_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_final_for_partial_extract_id (final_for_partial_extract_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_performed_at (performed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول أنشطة المستخلصات النهائية للجزئية\n";
    
    $db->commit();
    echo "\n🎉 تم إنشاء جداول المرفقات والأنشطة المنفصلة بنجاح!\n";
    echo "\n📋 الجداول المنشأة:\n";
    echo "  المرفقات:\n";
    echo "  - partial_extract_attachments\n";
    echo "  - final_regular_extract_attachments\n";
    echo "  - final_for_partial_extract_attachments\n";
    echo "\n  الأنشطة:\n";
    echo "  - partial_extract_activities\n";
    echo "  - final_regular_extract_activities\n";
    echo "  - final_for_partial_extract_activities\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
