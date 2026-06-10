<?php
/**
 * إنشاء جدول المعتمدين للإنتاجية
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    
    echo "🔧 إنشاء جدول المعتمدين للإنتاجية\n";
    echo "================================\n\n";
    
    // إنشاء جدول المعتمدين
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS productivity_approvers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        branch_id INT DEFAULT NULL,
        max_amount DECIMAL(15,2) DEFAULT 0.00,
        is_active TINYINT(1) DEFAULT 1,
        status ENUM('active', 'deleted') DEFAULT 'active',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        
        UNIQUE KEY unique_user_branch (user_id, branch_id, status),
        INDEX idx_user_id (user_id),
        INDEX idx_branch_id (branch_id),
        INDEX idx_status (status),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($createTableSQL);
    echo "✅ تم إنشاء جدول productivity_approvers بنجاح\n\n";
    
    // إضافة بيانات تجريبية
    echo "📊 إضافة بيانات تجريبية:\n";
    
    // التحقق من وجود بيانات
    $stmt = $db->query("SELECT COUNT(*) FROM productivity_approvers");
    $existingCount = $stmt->fetchColumn();
    
    if ($existingCount == 0) {
        // الحصول على مستخدمين للاختبار
        $usersStmt = $db->query("SELECT id, username, full_name FROM users WHERE status = 'active' LIMIT 3");
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // الحصول على فروع للاختبار
        $branchesStmt = $db->query("SELECT id, name FROM branches WHERE status = 'active' LIMIT 2");
        $branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($users)) {
            $sampleApprovers = [
                [
                    'user_id' => $users[0]['id'],
                    'branch_id' => !empty($branches) ? $branches[0]['id'] : null,
                    'max_amount' => 50000.00,
                    'is_active' => 1,
                    'created_by' => $users[0]['id']
                ]
            ];
            
            // إضافة معتمد ثاني إذا كان متاحاً
            if (count($users) > 1) {
                $sampleApprovers[] = [
                    'user_id' => $users[1]['id'],
                    'branch_id' => null, // جميع الفروع
                    'max_amount' => 0.00, // بلا حدود
                    'is_active' => 1,
                    'created_by' => $users[0]['id']
                ];
            }
            
            // إضافة معتمد ثالث إذا كان متاحاً
            if (count($users) > 2 && count($branches) > 1) {
                $sampleApprovers[] = [
                    'user_id' => $users[2]['id'],
                    'branch_id' => $branches[1]['id'],
                    'max_amount' => 25000.00,
                    'is_active' => 0, // غير نشط للاختبار
                    'created_by' => $users[0]['id']
                ];
            }
            
            $insertStmt = $db->prepare("
                INSERT INTO productivity_approvers (
                    user_id, branch_id, max_amount, is_active, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($sampleApprovers as $approver) {
                try {
                    $insertStmt->execute([
                        $approver['user_id'],
                        $approver['branch_id'],
                        $approver['max_amount'],
                        $approver['is_active'],
                        $approver['created_by']
                    ]);
                    
                    $userName = '';
                    foreach ($users as $user) {
                        if ($user['id'] == $approver['user_id']) {
                            $userName = $user['full_name'];
                            break;
                        }
                    }
                    
                    $branchName = 'جميع الفروع';
                    if ($approver['branch_id']) {
                        foreach ($branches as $branch) {
                            if ($branch['id'] == $approver['branch_id']) {
                                $branchName = $branch['name'];
                                break;
                            }
                        }
                    }
                    
                    $maxAmountText = $approver['max_amount'] > 0 ? 
                        number_format($approver['max_amount'], 2) . ' ريال' : 'بلا حدود';
                    
                    $statusText = $approver['is_active'] ? 'نشط' : 'غير نشط';
                    
                    echo "   ✅ $userName - $branchName - $maxAmountText - $statusText\n";
                    
                } catch (Exception $e) {
                    echo "   ❌ خطأ في إضافة معتمد: " . $e->getMessage() . "\n";
                }
            }
            
        } else {
            echo "   ⚠️ لا توجد مستخدمين متاحين لإضافة معتمدين تجريبيين\n";
        }
        
    } else {
        echo "   ⚠️ يوجد $existingCount معتمد مسبقاً\n";
    }
    
    // عرض إحصائيات
    echo "\n📊 إحصائيات الجدول:\n";
    
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_approvers,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_approvers,
            SUM(CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END) as all_branches_approvers,
            SUM(CASE WHEN max_amount = 0 THEN 1 ELSE 0 END) as unlimited_approvers
        FROM productivity_approvers 
        WHERE status = 'active'
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   📈 إجمالي المعتمدين: {$stats['total_approvers']}\n";
    echo "   🟢 المعتمدين النشطين: {$stats['active_approvers']}\n";
    echo "   🌐 معتمدين لجميع الفروع: {$stats['all_branches_approvers']}\n";
    echo "   ♾️ معتمدين بلا حدود: {$stats['unlimited_approvers']}\n";
    
    // عرض قائمة المعتمدين
    echo "\n👥 قائمة المعتمدين:\n";
    
    $approversStmt = $db->query("
        SELECT 
            pa.*,
            u.full_name,
            u.username,
            b.name as branch_name
        FROM productivity_approvers pa
        JOIN users u ON pa.user_id = u.id
        LEFT JOIN branches b ON pa.branch_id = b.id
        WHERE pa.status = 'active'
        ORDER BY pa.is_active DESC, u.full_name
    ");
    $approvers = $approversStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($approvers as $approver) {
        $status = $approver['is_active'] ? '🟢' : '🔴';
        $branch = $approver['branch_name'] ?? 'جميع الفروع';
        $maxAmount = $approver['max_amount'] > 0 ? 
            number_format($approver['max_amount'], 2) . ' ريال' : 'بلا حدود';
        
        echo "   $status {$approver['full_name']} ({$approver['username']}) - $branch - $maxAmount\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎉 تم إنشاء نظام المعتمدين بنجاح!\n\n";
    
    echo "🌐 الروابط الجاهزة:\n";
    echo "1. إدارة المعتمدين: http://localhost/etganplus/public/productivity/approvers/index.php\n";
    echo "2. الاعتمادات: http://localhost/etganplus/public/productivity/approvals/index.php\n";
    
    echo "\n💡 الميزات المتاحة:\n";
    echo "• إضافة معتمدين جدد\n";
    echo "• تحديد حد أقصى للاعتماد لكل معتمد\n";
    echo "• ربط المعتمد بفرع محدد أو جميع الفروع\n";
    echo "• تفعيل/إلغاء تفعيل المعتمدين\n";
    echo "• حذف المعتمدين (حذف منطقي)\n";
    echo "• تتبع من أضاف كل معتمد ومتى\n";
    
    echo "\n🔐 الصلاحيات المطلوبة:\n";
    echo "• productivity_approvers_manage: لإدارة المعتمدين\n";
    echo "• productivity_daily_logs_approve: لاعتماد السجلات\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n";
}
?>
