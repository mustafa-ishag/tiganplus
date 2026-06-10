<?php
/**
 * إنشاء نظام المستخلصات المنفصل
 * Create Separated Extracts System
 * 
 * جداول منفصلة لكل نوع من المستخلصات:
 * 1. partial_extracts - المستخلصات الجزئية
 * 2. final_regular_extracts - المستخلصات النهائية العادية  
 * 3. final_for_partial_extracts - المستخلصات النهائية للجزئية
 * 
 * مع جداول علاقات منفصلة لكل نوع
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    $db->beginTransaction();
    
    echo "🚀 إنشاء نظام المستخلصات المنفصل...\n\n";
    
    // 1. إنشاء جدول المستخلصات الجزئية
    echo "1. إنشاء جدول المستخلصات الجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS partial_extracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            extract_number VARCHAR(20) NOT NULL UNIQUE,
            invoice_number VARCHAR(50) NULL,
            branch_id INT NOT NULL,
            created_by INT NOT NULL,
            description TEXT,
            extract_date DATE NOT NULL,
            submission_date DATE NULL,
            
            -- المبالغ المالية
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
            tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            
            -- الحالة ومراحل الاعتماد
            status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid', 'cancelled') DEFAULT 'draft',
            approval_stage ENUM('technical_support', 'construction', 'department_manager', 'administration_manager', 'finance', 'disbursed') DEFAULT NULL,
            
            -- مراحل الاعتماد مع التواريخ
            technical_support_date TIMESTAMP NULL,
            technical_support_by INT NULL,
            technical_support_notes TEXT,
            
            construction_date TIMESTAMP NULL,
            construction_by INT NULL,
            construction_notes TEXT,
            
            department_manager_date TIMESTAMP NULL,
            department_manager_by INT NULL,
            department_manager_notes TEXT,
            
            administration_manager_date TIMESTAMP NULL,
            administration_manager_by INT NULL,
            administration_manager_notes TEXT,
            
            finance_date TIMESTAMP NULL,
            finance_by INT NULL,
            finance_notes TEXT,
            
            disbursed_date TIMESTAMP NULL,
            disbursed_by INT NULL,
            disbursed_notes TEXT,
            
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
            INDEX idx_branch_id (branch_id),
            INDEX idx_status (status),
            INDEX idx_approval_stage (approval_stage),
            INDEX idx_extract_date (extract_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول المستخلصات الجزئية\n";
    
    // 2. إنشاء جدول المستخلصات النهائية العادية
    echo "\n2. إنشاء جدول المستخلصات النهائية العادية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_regular_extracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            extract_number VARCHAR(20) NOT NULL UNIQUE,
            invoice_number VARCHAR(50) NULL,
            branch_id INT NOT NULL,
            created_by INT NOT NULL,
            description TEXT,
            extract_date DATE NOT NULL,
            submission_date DATE NULL,
            
            -- المبالغ المالية (مع الغرامات)
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
            tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            
            -- الحالة ومراحل الاعتماد
            status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid', 'cancelled') DEFAULT 'draft',
            approval_stage ENUM('technical_support', 'construction', 'department_manager', 'administration_manager', 'finance', 'disbursed') DEFAULT NULL,
            
            -- مراحل الاعتماد مع التواريخ
            technical_support_date TIMESTAMP NULL,
            technical_support_by INT NULL,
            technical_support_notes TEXT,
            
            construction_date TIMESTAMP NULL,
            construction_by INT NULL,
            construction_notes TEXT,
            
            department_manager_date TIMESTAMP NULL,
            department_manager_by INT NULL,
            department_manager_notes TEXT,
            
            administration_manager_date TIMESTAMP NULL,
            administration_manager_by INT NULL,
            administration_manager_notes TEXT,
            
            finance_date TIMESTAMP NULL,
            finance_by INT NULL,
            finance_notes TEXT,
            
            disbursed_date TIMESTAMP NULL,
            disbursed_by INT NULL,
            disbursed_notes TEXT,
            
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
            INDEX idx_branch_id (branch_id),
            INDEX idx_status (status),
            INDEX idx_approval_stage (approval_stage),
            INDEX idx_extract_date (extract_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول المستخلصات النهائية العادية\n";
    
    // 3. إنشاء جدول المستخلصات النهائية للجزئية
    echo "\n3. إنشاء جدول المستخلصات النهائية للجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_for_partial_extracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            extract_number VARCHAR(20) NOT NULL UNIQUE,
            invoice_number VARCHAR(50) NULL,
            branch_id INT NOT NULL,
            created_by INT NOT NULL,
            description TEXT,
            extract_date DATE NOT NULL,
            submission_date DATE NULL,
            
            -- ربط بالمستخلص الجزئي الأصلي
            related_partial_extract_id INT NULL,
            
            -- المبالغ المالية (مع الغرامات)
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 15.00,
            tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            net_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            
            -- الحالة ومراحل الاعتماد
            status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'paid', 'cancelled') DEFAULT 'draft',
            approval_stage ENUM('technical_support', 'construction', 'department_manager', 'administration_manager', 'finance', 'disbursed') DEFAULT NULL,
            
            -- مراحل الاعتماد مع التواريخ
            technical_support_date TIMESTAMP NULL,
            technical_support_by INT NULL,
            technical_support_notes TEXT,
            
            construction_date TIMESTAMP NULL,
            construction_by INT NULL,
            construction_notes TEXT,
            
            department_manager_date TIMESTAMP NULL,
            department_manager_by INT NULL,
            department_manager_notes TEXT,
            
            administration_manager_date TIMESTAMP NULL,
            administration_manager_by INT NULL,
            administration_manager_notes TEXT,
            
            finance_date TIMESTAMP NULL,
            finance_by INT NULL,
            finance_notes TEXT,
            
            disbursed_date TIMESTAMP NULL,
            disbursed_by INT NULL,
            disbursed_notes TEXT,
            
            -- معلومات إضافية
            notes TEXT,
            attachments_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            -- Foreign Keys
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON UPDATE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE,
            FOREIGN KEY (related_partial_extract_id) REFERENCES partial_extracts(id) ON UPDATE CASCADE,
            FOREIGN KEY (technical_support_by) REFERENCES users(id) ON UPDATE CASCADE,
            FOREIGN KEY (construction_by) REFERENCES users(id) ON UPDATE CASCADE,
            FOREIGN KEY (department_manager_by) REFERENCES users(id) ON UPDATE CASCADE,
            FOREIGN KEY (administration_manager_by) REFERENCES users(id) ON UPDATE CASCADE,
            FOREIGN KEY (finance_by) REFERENCES users(id) ON UPDATE CASCADE,
            FOREIGN KEY (disbursed_by) REFERENCES users(id) ON UPDATE CASCADE,
            
            -- Indexes
            INDEX idx_extract_number (extract_number),
            INDEX idx_branch_id (branch_id),
            INDEX idx_status (status),
            INDEX idx_approval_stage (approval_stage),
            INDEX idx_extract_date (extract_date),
            INDEX idx_related_partial (related_partial_extract_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول المستخلصات النهائية للجزئية\n";
    
    // 4. إنشاء جدول علاقات المستخلصات الجزئية مع أوامر العمل
    echo "\n4. إنشاء جدول علاقات المستخلصات الجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS partial_extract_work_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partial_extract_id INT NOT NULL,
            work_order_id INT NOT NULL,
            completion_date DATE NOT NULL,
            extract_value DECIMAL(15,2) NOT NULL,
            notes TEXT,
            added_by INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            -- Foreign Keys
            FOREIGN KEY (partial_extract_id) REFERENCES partial_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON UPDATE CASCADE,
            FOREIGN KEY (added_by) REFERENCES users(id) ON UPDATE CASCADE,

            -- Unique constraint
            UNIQUE KEY unique_partial_extract_work_order (partial_extract_id, work_order_id),

            -- Indexes
            INDEX idx_partial_extract_id (partial_extract_id),
            INDEX idx_work_order_id (work_order_id),
            INDEX idx_completion_date (completion_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول علاقات المستخلصات الجزئية\n";

    // 5. إنشاء جدول علاقات المستخلصات النهائية العادية مع أوامر العمل
    echo "\n5. إنشاء جدول علاقات المستخلصات النهائية العادية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_regular_extract_work_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            final_regular_extract_id INT NOT NULL,
            work_order_id INT NOT NULL,
            completion_date DATE NOT NULL,
            extract_value DECIMAL(15,2) NOT NULL,
            penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            notes TEXT,
            added_by INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            -- Foreign Keys
            FOREIGN KEY (final_regular_extract_id) REFERENCES final_regular_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON UPDATE CASCADE,
            FOREIGN KEY (added_by) REFERENCES users(id) ON UPDATE CASCADE,

            -- Unique constraint
            UNIQUE KEY unique_final_regular_extract_work_order (final_regular_extract_id, work_order_id),

            -- Indexes
            INDEX idx_final_regular_extract_id (final_regular_extract_id),
            INDEX idx_work_order_id (work_order_id),
            INDEX idx_completion_date (completion_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول علاقات المستخلصات النهائية العادية\n";

    // 6. إنشاء جدول علاقات المستخلصات النهائية للجزئية مع أوامر العمل
    echo "\n6. إنشاء جدول علاقات المستخلصات النهائية للجزئية...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS final_for_partial_extract_work_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            final_for_partial_extract_id INT NOT NULL,
            work_order_id INT NOT NULL,
            completion_date DATE NOT NULL,
            extract_value DECIMAL(15,2) NOT NULL,
            penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            notes TEXT,
            added_by INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            -- Foreign Keys
            FOREIGN KEY (final_for_partial_extract_id) REFERENCES final_for_partial_extracts(id) ON DELETE CASCADE,
            FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON UPDATE CASCADE,
            FOREIGN KEY (added_by) REFERENCES users(id) ON UPDATE CASCADE,

            -- Unique constraint
            UNIQUE KEY unique_final_for_partial_extract_work_order (final_for_partial_extract_id, work_order_id),

            -- Indexes
            INDEX idx_final_for_partial_extract_id (final_for_partial_extract_id),
            INDEX idx_work_order_id (work_order_id),
            INDEX idx_completion_date (completion_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول علاقات المستخلصات النهائية للجزئية\n";

    $db->commit();
    echo "\n🎉 تم إنشاء نظام المستخلصات المنفصل بنجاح!\n";
    echo "\n📋 الجداول المنشأة:\n";
    echo "  1. partial_extracts - المستخلصات الجزئية\n";
    echo "  2. final_regular_extracts - المستخلصات النهائية العادية\n";
    echo "  3. final_for_partial_extracts - المستخلصات النهائية للجزئية\n";
    echo "  4. partial_extract_work_orders - علاقات المستخلصات الجزئية\n";
    echo "  5. final_regular_extract_work_orders - علاقات المستخلصات النهائية العادية\n";
    echo "  6. final_for_partial_extract_work_orders - علاقات المستخلصات النهائية للجزئية\n";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
