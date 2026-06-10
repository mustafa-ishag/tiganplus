<?php
/**
 * إنشاء نظام الإنتاجية الجديد
 * Create New Productivity System
 * 
 * هذا الملف ينشئ جميع الجداول المطلوبة لنظام تتبع الإنتاجية
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    echo "🚀 بدء إنشاء نظام الإنتاجية الجديد...\n";
    echo "====================================\n\n";
    
    $db = getDB();
    
    // 1. إنشاء جدول بنود أوامر العمل للإنتاجية
    echo "1. إنشاء جدول بنود أوامر العمل للإنتاجية...\n";
    $db->exec("
    CREATE TABLE IF NOT EXISTS productivity_work_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_id INT NOT NULL COMMENT 'معرف أمر العمل',
        work_item_id INT NOT NULL COMMENT 'معرف بند العمل',
        target_quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000 COMMENT 'الكمية المستهدفة',
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'سعر الوحدة',
        total_value DECIMAL(15,2) GENERATED ALWAYS AS (target_quantity * unit_price) STORED COMMENT 'القيمة الإجمالية',
        start_date DATE NULL COMMENT 'تاريخ البداية',
        target_end_date DATE NULL COMMENT 'تاريخ الانتهاء المستهدف',
        actual_end_date DATE NULL COMMENT 'تاريخ الانتهاء الفعلي',
        status ENUM('active', 'completed', 'paused', 'cancelled') NOT NULL DEFAULT 'active' COMMENT 'حالة البند',
        priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium' COMMENT 'الأولوية',
        notes TEXT NULL COMMENT 'ملاحظات',
        created_by INT NOT NULL COMMENT 'منشئ السجل',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        -- Foreign Keys
        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (work_item_id) REFERENCES work_items(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_work_order (work_order_id),
        INDEX idx_work_item (work_item_id),
        INDEX idx_status (status),
        INDEX idx_priority (priority),
        INDEX idx_dates (start_date, target_end_date),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at),
        
        -- Composite Indexes
        INDEX idx_work_order_status (work_order_id, status),
        INDEX idx_work_item_status (work_item_id, status),
        INDEX idx_status_priority (status, priority),
        
        -- Unique constraint
        UNIQUE KEY unique_work_order_item (work_order_id, work_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول بنود أوامر العمل للإنتاجية';
    ");
    echo "✅ تم إنشاء جدول productivity_work_items\n\n";
    
    // 2. إنشاء جدول السجلات اليومية
    echo "2. إنشاء جدول السجلات اليومية...\n";
    $db->exec("
    CREATE TABLE IF NOT EXISTS productivity_daily_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_item_id INT NOT NULL COMMENT 'معرف بند الإنتاجية',
        log_date DATE NOT NULL COMMENT 'تاريخ التسجيل',
        quantity_completed DECIMAL(12,3) NOT NULL DEFAULT 0.000 COMMENT 'الكمية المنجزة',
        work_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'ساعات العمل',
        workers_count INT NOT NULL DEFAULT 0 COMMENT 'عدد العمال',
        equipment_used TEXT NULL COMMENT 'المعدات المستخدمة',
        weather_condition ENUM('excellent', 'good', 'fair', 'poor', 'bad') NULL COMMENT 'حالة الطقس',
        work_quality ENUM('excellent', 'good', 'acceptable', 'poor') NOT NULL DEFAULT 'good' COMMENT 'جودة العمل',
        obstacles TEXT NULL COMMENT 'العوائق والمشاكل',
        notes TEXT NULL COMMENT 'ملاحظات',
        attachments JSON NULL COMMENT 'المرفقات والصور',
        location_coordinates VARCHAR(100) NULL COMMENT 'إحداثيات الموقع',
        status ENUM('draft', 'submitted', 'approved', 'rejected', 'returned') NOT NULL DEFAULT 'draft' COMMENT 'حالة السجل',
        submitted_at TIMESTAMP NULL COMMENT 'تاريخ الإرسال للاعتماد',
        created_by INT NOT NULL COMMENT 'منشئ السجل',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        -- Foreign Keys
        FOREIGN KEY (work_item_id) REFERENCES productivity_work_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_work_item (work_item_id),
        INDEX idx_log_date (log_date),
        INDEX idx_status (status),
        INDEX idx_created_by (created_by),
        INDEX idx_submitted_at (submitted_at),
        INDEX idx_created_at (created_at),
        
        -- Composite Indexes
        INDEX idx_work_item_date (work_item_id, log_date),
        INDEX idx_work_item_status (work_item_id, status),
        INDEX idx_date_status (log_date, status),
        INDEX idx_status_submitted (status, submitted_at),
        
        -- Unique constraint for one log per item per day
        UNIQUE KEY unique_work_item_date (work_item_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول السجلات اليومية للإنتاجية';
    ");
    echo "✅ تم إنشاء جدول productivity_daily_logs\n\n";
    
    // 3. إنشاء جدول الاعتمادات
    echo "3. إنشاء جدول الاعتمادات...\n";
    $db->exec("
    CREATE TABLE IF NOT EXISTS productivity_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        daily_log_id INT NOT NULL COMMENT 'معرف السجل اليومي',
        approver_id INT NOT NULL COMMENT 'معرف المعتمد',
        action ENUM('approved', 'rejected', 'returned') NOT NULL COMMENT 'إجراء الاعتماد',
        comments TEXT NULL COMMENT 'تعليقات المعتمد',
        approval_level ENUM('supervisor', 'manager', 'director', 'general_manager') NOT NULL COMMENT 'مستوى الاعتماد',
        approval_value DECIMAL(15,2) NULL COMMENT 'قيمة العمل المعتمد',
        approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الاعتماد',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        
        -- Foreign Keys
        FOREIGN KEY (daily_log_id) REFERENCES productivity_daily_logs(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_daily_log (daily_log_id),
        INDEX idx_approver (approver_id),
        INDEX idx_action (action),
        INDEX idx_approval_level (approval_level),
        INDEX idx_approved_at (approved_at),
        INDEX idx_created_at (created_at),
        
        -- Composite Indexes
        INDEX idx_log_approver (daily_log_id, approver_id),
        INDEX idx_action_level (action, approval_level),
        INDEX idx_approver_date (approver_id, approved_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول اعتمادات الإنتاجية';
    ");
    echo "✅ تم إنشاء جدول productivity_approvals\n\n";

    // 4. إنشاء جدول المعتمدين
    echo "4. إنشاء جدول المعتمدين...\n";
    $db->exec("
    CREATE TABLE IF NOT EXISTS productivity_approvers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'معرف المستخدم',
        branch_id INT NULL COMMENT 'معرف الفرع (NULL = جميع الفروع)',
        department ENUM('connections', 'projects', 'all') NOT NULL DEFAULT 'all' COMMENT 'القسم',
        approval_level ENUM('supervisor', 'manager', 'director', 'general_manager') NOT NULL COMMENT 'مستوى الاعتماد',
        max_amount_limit DECIMAL(15,2) NULL COMMENT 'الحد الأقصى للقيمة',
        can_approve_own_branch BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'يمكن اعتماد فرعه',
        can_approve_other_branches BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'يمكن اعتماد فروع أخرى',
        is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'حالة التفعيل',
        effective_from DATE NOT NULL COMMENT 'تاريخ بداية الصلاحية',
        effective_to DATE NULL COMMENT 'تاريخ انتهاء الصلاحية',
        created_by INT NOT NULL COMMENT 'منشئ السجل',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',

        -- Foreign Keys
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE ON UPDATE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,

        -- Indexes
        INDEX idx_user (user_id),
        INDEX idx_branch (branch_id),
        INDEX idx_department (department),
        INDEX idx_approval_level (approval_level),
        INDEX idx_is_active (is_active),
        INDEX idx_effective_dates (effective_from, effective_to),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at),

        -- Composite Indexes
        INDEX idx_user_active (user_id, is_active),
        INDEX idx_branch_level (branch_id, approval_level),
        INDEX idx_department_level (department, approval_level),
        INDEX idx_active_dates (is_active, effective_from, effective_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول المعتمدين للإنتاجية';
    ");
    echo "✅ تم إنشاء جدول productivity_approvers\n\n";

    // 5. إنشاء جدول الإحصائيات
    echo "5. إنشاء جدول الإحصائيات...\n";
    $db->exec("
    CREATE TABLE IF NOT EXISTS productivity_statistics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_item_id INT NOT NULL COMMENT 'معرف بند الإنتاجية',
        calculation_date DATE NOT NULL COMMENT 'تاريخ الحساب',
        total_completed DECIMAL(12,3) NOT NULL DEFAULT 0.000 COMMENT 'إجمالي المنجز',
        completion_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'نسبة الإنجاز',
        average_daily_rate DECIMAL(12,3) NOT NULL DEFAULT 0.000 COMMENT 'متوسط الإنجاز اليومي',
        working_days_count INT NOT NULL DEFAULT 0 COMMENT 'عدد أيام العمل',
        estimated_completion_date DATE NULL COMMENT 'تاريخ الانتهاء المتوقع',
        efficiency_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'درجة الكفاءة',
        quality_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'درجة الجودة',
        delay_days INT NOT NULL DEFAULT 0 COMMENT 'أيام التأخير',
        cost_variance DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'انحراف التكلفة',
        schedule_variance DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'انحراف الجدولة',
        last_activity_date DATE NULL COMMENT 'تاريخ آخر نشاط',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',

        -- Foreign Keys
        FOREIGN KEY (work_item_id) REFERENCES productivity_work_items(id) ON DELETE CASCADE ON UPDATE CASCADE,

        -- Indexes
        INDEX idx_work_item (work_item_id),
        INDEX idx_calculation_date (calculation_date),
        INDEX idx_completion_percentage (completion_percentage),
        INDEX idx_efficiency_score (efficiency_score),
        INDEX idx_quality_score (quality_score),
        INDEX idx_updated_at (updated_at),

        -- Composite Indexes
        INDEX idx_work_item_date (work_item_id, calculation_date),
        INDEX idx_completion_efficiency (completion_percentage, efficiency_score),

        -- Unique constraint
        UNIQUE KEY unique_work_item_date (work_item_id, calculation_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول إحصائيات الإنتاجية';
    ");
    echo "✅ تم إنشاء جدول productivity_statistics\n\n";

    // 6. إنشاء جدول تاريخ العمليات (Audit Trail)
    echo "6. إنشاء جدول تاريخ العمليات...\n";
    $db->exec("
    CREATE TABLE IF NOT EXISTS productivity_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        table_name VARCHAR(50) NOT NULL COMMENT 'اسم الجدول',
        record_id INT NOT NULL COMMENT 'معرف السجل',
        action ENUM('create', 'update', 'delete', 'approve', 'reject') NOT NULL COMMENT 'نوع العملية',
        old_values JSON NULL COMMENT 'القيم القديمة',
        new_values JSON NULL COMMENT 'القيم الجديدة',
        user_id INT NOT NULL COMMENT 'معرف المستخدم',
        ip_address VARCHAR(45) NULL COMMENT 'عنوان IP',
        user_agent TEXT NULL COMMENT 'معلومات المتصفح',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ العملية',

        -- Foreign Keys
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,

        -- Indexes
        INDEX idx_table_record (table_name, record_id),
        INDEX idx_action (action),
        INDEX idx_user (user_id),
        INDEX idx_created_at (created_at),

        -- Composite Indexes
        INDEX idx_table_action (table_name, action),
        INDEX idx_user_action (user_id, action),
        INDEX idx_record_date (record_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول تاريخ عمليات الإنتاجية';
    ");
    echo "✅ تم إنشاء جدول productivity_audit_logs\n\n";

    // 7. إدراج بيانات تجريبية
    echo "7. إدراج بيانات تجريبية...\n";

    // إدراج معتمدين افتراضيين
    $db->exec("
    INSERT IGNORE INTO productivity_approvers (user_id, branch_id, department, approval_level, max_amount_limit, effective_from, created_by) VALUES
    (1, NULL, 'all', 'general_manager', NULL, CURDATE(), 1),
    (1, 1, 'all', 'director', 200000.00, CURDATE(), 1),
    (1, 1, 'connections', 'manager', 50000.00, CURDATE(), 1),
    (1, 1, 'projects', 'manager', 50000.00, CURDATE(), 1),
    (1, 1, 'all', 'supervisor', 10000.00, CURDATE(), 1)
    ");
    echo "✅ تم إدراج المعتمدين الافتراضيين\n";

    echo "\n🎉 تم إنشاء نظام الإنتاجية بنجاح!\n";
    echo "====================================\n";
    echo "📊 الجداول المنشأة:\n";
    echo "   - productivity_work_items (بنود أوامر العمل)\n";
    echo "   - productivity_daily_logs (السجلات اليومية)\n";
    echo "   - productivity_approvals (الاعتمادات)\n";
    echo "   - productivity_approvers (المعتمدين)\n";
    echo "   - productivity_statistics (الإحصائيات)\n";
    echo "   - productivity_audit_logs (تاريخ العمليات)\n";
    echo "\n✅ النظام جاهز للاستخدام!\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n";
    exit(1);
}
?>
