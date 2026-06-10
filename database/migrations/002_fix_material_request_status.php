<?php
/**
 * Migration: Fix Material Request Status
 * الهدف: إصلاح حالات طلبات الصرف
 * 
 * تحديث statusMap لاستخدام الحالات الصحيحة من الجدول
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🔄 بدء الترحيل: إصلاح حالات طلبات الصرف...\n\n";
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // 1. التحقق من حالات الطلب الحالية
    echo "1. التحقق من حالات الطلب الحالية...\n";
    $statusCheck = $pdo->query("
        SELECT DISTINCT status FROM material_requests
    ");
    $statuses = $statusCheck->fetchAll(PDO::FETCH_COLUMN);
    echo "   الحالات الموجودة: " . implode(', ', $statuses) . "\n";
    
    // 2. تحديث أي طلبات بحالة 'approved' إلى 'branch_approved'
    echo "\n2. تحديث الطلبات بحالة 'approved' إلى 'branch_approved'...\n";
    $updateStmt = $pdo->prepare("
        UPDATE material_requests 
        SET status = 'branch_approved'
        WHERE status = 'approved'
    ");
    $updateStmt->execute();
    $updatedCount = $updateStmt->rowCount();
    echo "   تم تحديث $updatedCount طلب\n";
    
    // 3. إضافة عمود branch_approved_by إذا لم يكن موجوداً
    echo "\n3. التحقق من أعمدة الموافقة...\n";
    $checkColumns = $pdo->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'material_requests'
        AND COLUMN_NAME IN ('branch_approved_by', 'branch_approved_at', 'branch_notes')
    ");
    $existingColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('branch_approved_by', $existingColumns)) {
        echo "   إضافة عمود branch_approved_by...\n";
        $pdo->exec("
            ALTER TABLE material_requests 
            ADD COLUMN branch_approved_by INT COMMENT 'معرف معتمد الفرع'
            AFTER project_approved_at
        ");
    }
    
    if (!in_array('branch_approved_at', $existingColumns)) {
        echo "   إضافة عمود branch_approved_at...\n";
        $pdo->exec("
            ALTER TABLE material_requests 
            ADD COLUMN branch_approved_at TIMESTAMP NULL COMMENT 'تاريخ موافقة الفرع'
            AFTER branch_approved_by
        ");
    }
    
    if (!in_array('branch_notes', $existingColumns)) {
        echo "   إضافة عمود branch_notes...\n";
        $pdo->exec("
            ALTER TABLE material_requests 
            ADD COLUMN branch_notes TEXT COMMENT 'ملاحظات معتمد الفرع'
            AFTER branch_approved_at
        ");
    }
    
    // 4. إضافة جدول approval_workflow إذا لم يكن موجوداً
    echo "\n4. إنشاء جدول approval_workflow...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS approval_workflow (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_request_id INT NOT NULL COMMENT 'معرف طلب الصرف',
        approval_level ENUM('warehouse', 'project', 'branch') NOT NULL COMMENT 'مستوى الموافقة',
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'حالة الموافقة',
        approved_by INT COMMENT 'معرف المعتمد',
        approved_at TIMESTAMP NULL COMMENT 'تاريخ الموافقة',
        notes TEXT COMMENT 'ملاحظات',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
        INDEX idx_request (material_request_id),
        INDEX idx_level (approval_level),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سير العمل للموافقات';
    ");
    echo "✅ تم إنشاء جدول approval_workflow\n";
    
    // 5. إنشاء جدول request_status_history
    echo "\n5. إنشاء جدول request_status_history...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS request_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_request_id INT NOT NULL COMMENT 'معرف طلب الصرف',
        old_status VARCHAR(50) NOT NULL COMMENT 'الحالة السابقة',
        new_status VARCHAR(50) NOT NULL COMMENT 'الحالة الجديدة',
        changed_by INT NOT NULL COMMENT 'من قام بالتغيير',
        reason TEXT COMMENT 'السبب',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
        INDEX idx_request (material_request_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تاريخ تغييرات حالة الطلب';
    ");
    echo "✅ تم إنشاء جدول request_status_history\n";
    
    // تأكيد المعاملة
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    echo "\n✅ تم إكمال الترحيل بنجاح!\n";
    echo "📊 الملخص:\n";
    echo "  - تم تحديث $updatedCount طلب من 'approved' إلى 'branch_approved'\n";
    echo "  - تم إضافة أعمدة الموافقة للفرع\n";
    echo "  - تم إنشاء جدول approval_workflow\n";
    echo "  - تم إنشاء جدول request_status_history\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ خطأ في الترحيل: " . $e->getMessage() . "\n";
    exit(1);
}
?>

