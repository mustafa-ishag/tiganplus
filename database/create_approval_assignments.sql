-- إنشاء جدول تعيين المعتمدين لطلبات الصرف
-- Approval Assignments Table for Material Requests

CREATE TABLE IF NOT EXISTS approval_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- نوع الموافقة
    approval_type ENUM('warehouse', 'project') NOT NULL COMMENT 'نوع الموافقة: مستودع أو مشروع',
    
    -- المعتمد
    approver_user_id INT NOT NULL COMMENT 'معرف المستخدم المعتمد',
    
    -- النطاق (يمكن أن يكون فرع أو مشروع محدد أو عام)
    scope_type ENUM('global', 'branch', 'work_order') NOT NULL DEFAULT 'global' COMMENT 'نطاق الموافقة',
    scope_id INT NULL COMMENT 'معرف النطاق (branch_id أو work_order_id)',
    
    -- معلومات إضافية
    priority INT NOT NULL DEFAULT 1 COMMENT 'أولوية المعتمد (1 = أعلى أولوية)',
    is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'هل التعيين نشط',
    
    -- معلومات التعيين
    assigned_by INT NOT NULL COMMENT 'من قام بالتعيين',
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التعيين',
    
    -- ملاحظات
    notes TEXT NULL COMMENT 'ملاحظات حول التعيين',
    
    -- تواريخ النظام
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- المفاتيح الخارجية
    FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
    
    -- الفهارس
    INDEX idx_approval_type (approval_type),
    INDEX idx_approver (approver_user_id),
    INDEX idx_scope (scope_type, scope_id),
    INDEX idx_active (is_active),
    INDEX idx_priority (priority),
    
    -- فهرس مركب للبحث السريع
    INDEX idx_approval_scope (approval_type, scope_type, scope_id, is_active),
    
    -- قيد فريد لمنع التكرار
    UNIQUE KEY unique_assignment (approval_type, approver_user_id, scope_type, scope_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='تعيين المعتمدين لطلبات الصرف';

-- إدراج بيانات تجريبية
INSERT INTO approval_assignments (approval_type, approver_user_id, scope_type, scope_id, assigned_by, notes) VALUES
-- معتمد مستودع عام
('warehouse', 1, 'global', NULL, 1, 'معتمد المستودع الرئيسي'),

-- معتمد مشروع عام  
('project', 1, 'global', NULL, 1, 'معتمد المشاريع الرئيسي');

-- أمثلة لتعيينات محددة (يمكن إضافتها حسب الحاجة):
-- معتمد مستودع لفرع محدد
-- INSERT INTO approval_assignments (approval_type, approver_user_id, scope_type, scope_id, assigned_by, notes) VALUES
-- ('warehouse', 2, 'branch', 1, 1, 'معتمد مستودع الفرع الأول');

-- معتمد مشروع لأمر عمل محدد
-- INSERT INTO approval_assignments (approval_type, approver_user_id, scope_type, scope_id, assigned_by, notes) VALUES
-- ('project', 3, 'work_order', 1, 1, 'معتمد مشروع أمر العمل رقم 1');
