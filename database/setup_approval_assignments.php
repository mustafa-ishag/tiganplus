<?php
/**
 * إعداد جدول تعيين المعتمدين
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "إنشاء جدول approval_assignments...\n";
    
    // إنشاء الجدول
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS approval_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        approval_type ENUM('warehouse', 'project') NOT NULL COMMENT 'نوع الموافقة: مستودع أو مشروع',
        approver_user_id INT NOT NULL COMMENT 'معرف المستخدم المعتمد',
        scope_type ENUM('global', 'branch', 'work_order') NOT NULL DEFAULT 'global' COMMENT 'نطاق الموافقة',
        scope_id INT NULL COMMENT 'معرف النطاق (branch_id أو work_order_id)',
        priority INT NOT NULL DEFAULT 1 COMMENT 'أولوية المعتمد (1 = أعلى أولوية)',
        is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'هل التعيين نشط',
        assigned_by INT NOT NULL COMMENT 'من قام بالتعيين',
        assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ التعيين',
        notes TEXT NULL COMMENT 'ملاحظات حول التعيين',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT,
        INDEX idx_approval_type (approval_type),
        INDEX idx_approver (approver_user_id),
        INDEX idx_scope (scope_type, scope_id),
        INDEX idx_active (is_active),
        INDEX idx_priority (priority),
        INDEX idx_approval_scope (approval_type, scope_type, scope_id, is_active),
        UNIQUE KEY unique_assignment (approval_type, approver_user_id, scope_type, scope_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='تعيين المعتمدين لطلبات الصرف'
    ";
    
    $db->exec($createTableSQL);
    echo "✅ تم إنشاء جدول approval_assignments بنجاح\n";
    
    // التحقق من وجود بيانات
    $stmt = $db->query("SELECT COUNT(*) FROM approval_assignments");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "إدراج البيانات التجريبية...\n";
        
        // إدراج البيانات التجريبية
        $insertSQL = "
        INSERT INTO approval_assignments (approval_type, approver_user_id, scope_type, scope_id, assigned_by, notes) VALUES
        ('warehouse', 1, 'global', NULL, 1, 'معتمد المستودع الرئيسي'),
        ('project', 1, 'global', NULL, 1, 'معتمد المشاريع الرئيسي')
        ";
        
        $db->exec($insertSQL);
        echo "✅ تم إدراج البيانات التجريبية بنجاح\n";
    } else {
        echo "ℹ️ الجدول يحتوي على $count سجل بالفعل\n";
    }
    
    // عرض البيانات الحالية
    echo "\nالبيانات الحالية في الجدول:\n";
    $stmt = $db->query("
        SELECT aa.*, u.full_name as approver_name, u2.full_name as assigned_by_name
        FROM approval_assignments aa
        LEFT JOIN users u ON aa.approver_user_id = u.id
        LEFT JOIN users u2 ON aa.assigned_by = u2.id
        ORDER BY aa.approval_type, aa.priority
    ");
    
    $assignments = $stmt->fetchAll();
    
    if (empty($assignments)) {
        echo "لا توجد تعيينات حالياً\n";
    } else {
        echo "ID\tنوع الموافقة\tالمعتمد\t\tالنطاق\t\tالأولوية\tنشط\n";
        echo "------------------------------------------------------------\n";
        foreach ($assignments as $assignment) {
            $scopeText = $assignment['scope_type'];
            if ($assignment['scope_id']) {
                $scopeText .= " (ID: {$assignment['scope_id']})";
            }
            
            echo sprintf(
                "%d\t%s\t\t%s\t%s\t%d\t\t%s\n",
                $assignment['id'],
                $assignment['approval_type'],
                $assignment['approver_name'] ?? 'غير معروف',
                $scopeText,
                $assignment['priority'],
                $assignment['is_active'] ? 'نعم' : 'لا'
            );
        }
    }
    
    echo "\n✅ تم إعداد نظام تعيين المعتمدين بنجاح!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "تفاصيل الخطأ: " . $e->getTraceAsString() . "\n";
}
?>
