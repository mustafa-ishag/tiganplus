<?php
/**
 * إنشاء نظام المستخلصات الشامل
 * Create Comprehensive Extracts System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "إنشاء نظام المستخلصات الشامل...\n";
    
    // 1. جدول المستخلصات الرئيسي
    $createExtractsTable = "
    CREATE TABLE IF NOT EXISTS extracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extract_number VARCHAR(20) NOT NULL UNIQUE,
        extract_type ENUM('partial', 'final_regular', 'final_for_partial') NOT NULL,
        branch_id INT NOT NULL,
        created_by INT NOT NULL,
        
        -- معلومات المستخلص
        title VARCHAR(255) NOT NULL,
        description TEXT,
        extract_date DATE NOT NULL,
        submission_date DATE,
        
        -- القيم المالية
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        
        -- نسب الضرائب والغرامات
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
        penalty_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        
        -- حالة المستخلص
        status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
        
        -- مراحل الاعتماد (6 مراحل)
        approval_stage ENUM('technical_support', 'construction', 'department_manager', 'administration_manager', 'finance', 'disbursed') DEFAULT NULL,
        
        -- تواريخ مراحل الاعتماد
        technical_support_date TIMESTAMP NULL,
        technical_support_by INT NULL,
        technical_support_notes TEXT NULL,
        
        construction_date TIMESTAMP NULL,
        construction_by INT NULL,
        construction_notes TEXT NULL,
        
        department_manager_date TIMESTAMP NULL,
        department_manager_by INT NULL,
        department_manager_notes TEXT NULL,
        
        administration_manager_date TIMESTAMP NULL,
        administration_manager_by INT NULL,
        administration_manager_notes TEXT NULL,
        
        finance_date TIMESTAMP NULL,
        finance_by INT NULL,
        finance_notes TEXT NULL,
        
        disbursed_date TIMESTAMP NULL,
        disbursed_by INT NULL,
        disbursed_notes TEXT NULL,
        
        -- معلومات إضافية
        notes TEXT,
        attachments_count INT DEFAULT 0,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Foreign Keys
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON UPDATE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (technical_support_by) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (construction_by) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (department_manager_by) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (administration_manager_by) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (finance_by) REFERENCES users(id) ON UPDATE CASCADE,
        FOREIGN KEY (disbursed_by) REFERENCES users(id) ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_extract_number (extract_number),
        INDEX idx_extract_type (extract_type),
        INDEX idx_branch_id (branch_id),
        INDEX idx_status (status),
        INDEX idx_approval_stage (approval_stage),
        INDEX idx_extract_date (extract_date),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($createExtractsTable);
    echo "✅ تم إنشاء جدول المستخلصات الرئيسي\n";
    
    // 2. جدول أوامر العمل المرتبطة بالمستخلص
    $createExtractWorkOrdersTable = "
    CREATE TABLE IF NOT EXISTS extract_work_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extract_id INT NOT NULL,
        work_order_id INT NOT NULL,
        
        -- نوع الاختيار (الجزء الأول أو الثاني)
        selection_part ENUM('part1', 'part2') NOT NULL,
        
        -- القيم المالية لهذا الأمر في المستخلص
        estimated_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        actual_value DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        extract_percentage DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        extract_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        
        -- معلومات إضافية
        notes TEXT,
        added_by INT NOT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Foreign Keys
        FOREIGN KEY (extract_id) REFERENCES extracts(id) ON DELETE CASCADE,
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON UPDATE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(id) ON UPDATE CASCADE,
        
        -- Unique constraint
        UNIQUE KEY unique_extract_work_order (extract_id, work_order_id),
        
        -- Indexes
        INDEX idx_extract_id (extract_id),
        INDEX idx_work_order_id (work_order_id),
        INDEX idx_selection_part (selection_part)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($createExtractWorkOrdersTable);
    echo "✅ تم إنشاء جدول أوامر العمل المرتبطة بالمستخلصات\n";
    
    // 3. جدول مرفقات المستخلصات
    $createExtractAttachmentsTable = "
    CREATE TABLE IF NOT EXISTS extract_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extract_id INT NOT NULL,
        
        -- معلومات الملف
        file_name VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        
        -- نوع المرفق
        attachment_type ENUM('supporting_document', 'invoice', 'receipt', 'contract', 'other') NOT NULL DEFAULT 'supporting_document',
        
        -- معلومات الرفع
        uploaded_by INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- وصف المرفق
        description TEXT,
        
        -- Foreign Keys
        FOREIGN KEY (extract_id) REFERENCES extracts(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_extract_id (extract_id),
        INDEX idx_attachment_type (attachment_type),
        INDEX idx_uploaded_by (uploaded_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($createExtractAttachmentsTable);
    echo "✅ تم إنشاء جدول مرفقات المستخلصات\n";
    
    // 4. جدول سجل الأنشطة للمستخلصات
    $createExtractActivitiesTable = "
    CREATE TABLE IF NOT EXISTS extract_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extract_id INT NOT NULL,
        user_id INT NOT NULL,
        
        -- نوع النشاط
        activity_type ENUM('created', 'updated', 'submitted', 'approved', 'rejected', 'stage_changed', 'work_order_added', 'work_order_removed', 'attachment_added', 'attachment_removed', 'note_added') NOT NULL,
        
        -- تفاصيل النشاط
        activity_description TEXT NOT NULL,
        old_value TEXT NULL,
        new_value TEXT NULL,
        
        -- معلومات إضافية
        ip_address VARCHAR(45),
        user_agent TEXT,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Foreign Keys
        FOREIGN KEY (extract_id) REFERENCES extracts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_extract_id (extract_id),
        INDEX idx_user_id (user_id),
        INDEX idx_activity_type (activity_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($createExtractActivitiesTable);
    echo "✅ تم إنشاء جدول سجل أنشطة المستخلصات\n";
    
    // 5. إنشاء مجلد المرفقات
    $uploadsDir = __DIR__ . '/../public/uploads/extracts';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
        echo "✅ تم إنشاء مجلد مرفقات المستخلصات\n";
    }
    
    echo "\n🎉 تم إنشاء نظام المستخلصات الشامل بنجاح!\n";
    echo "\nالجداول المنشأة:\n";
    echo "- extracts (المستخلصات الرئيسي)\n";
    echo "- extract_work_orders (أوامر العمل المرتبطة)\n";
    echo "- extract_attachments (مرفقات المستخلصات)\n";
    echo "- extract_activities (سجل الأنشطة)\n";
    echo "\nالمميزات:\n";
    echo "- 3 أنواع مستخلصات (جزئية، نهائية عادية، نهائية للجزئية)\n";
    echo "- نظام اعتماد 6 مراحل\n";
    echo "- حسابات الضرائب والغرامات التلقائية\n";
    echo "- نظام الجزئين لاختيار أوامر العمل\n";
    echo "- سجل شامل للأنشطة\n";
    echo "- إدارة المرفقات\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
