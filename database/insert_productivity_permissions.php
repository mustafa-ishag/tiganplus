<?php
/**
 * إدراج صلاحيات نظام الإنتاجية
 * Insert Productivity System Permissions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    echo "🔐 بدء إدراج صلاحيات نظام الإنتاجية...\n";
    echo "=====================================\n\n";
    
    $db = getDB();
    
    // صلاحيات نظام الإنتاجية
    $productivityPermissions = [
        // بنود الإنتاجية
        [
            'name' => 'productivity_work_items_view',
            'display_name' => 'عرض بنود الإنتاجية',
            'description' => 'عرض قائمة بنود الإنتاجية وتفاصيلها',
            'module' => 'productivity',
            'category' => 'work_items'
        ],
        [
            'name' => 'productivity_work_items_create',
            'display_name' => 'إضافة بنود الإنتاجية',
            'description' => 'إضافة بنود إنتاجية جديدة لأوامر العمل',
            'module' => 'productivity',
            'category' => 'work_items'
        ],
        [
            'name' => 'productivity_work_items_edit',
            'display_name' => 'تعديل بنود الإنتاجية',
            'description' => 'تعديل بيانات بنود الإنتاجية الموجودة',
            'module' => 'productivity',
            'category' => 'work_items'
        ],
        [
            'name' => 'productivity_work_items_delete',
            'display_name' => 'حذف بنود الإنتاجية',
            'description' => 'حذف بنود الإنتاجية',
            'module' => 'productivity',
            'category' => 'work_items'
        ],
        [
            'name' => 'productivity_work_items_view_statistics',
            'display_name' => 'عرض إحصائيات بنود الإنتاجية',
            'description' => 'عرض الإحصائيات والتقارير لبنود الإنتاجية',
            'module' => 'productivity',
            'category' => 'work_items'
        ],
        
        // السجلات اليومية
        [
            'name' => 'productivity_daily_logs_view',
            'display_name' => 'عرض السجلات اليومية',
            'description' => 'عرض السجلات اليومية للإنتاجية',
            'module' => 'productivity',
            'category' => 'daily_logs'
        ],
        [
            'name' => 'productivity_daily_logs_create',
            'display_name' => 'إضافة السجلات اليومية',
            'description' => 'تسجيل الإنتاجية اليومية',
            'module' => 'productivity',
            'category' => 'daily_logs'
        ],
        [
            'name' => 'productivity_daily_logs_edit',
            'display_name' => 'تعديل السجلات اليومية',
            'description' => 'تعديل السجلات اليومية للإنتاجية',
            'module' => 'productivity',
            'category' => 'daily_logs'
        ],
        [
            'name' => 'productivity_daily_logs_delete',
            'display_name' => 'حذف السجلات اليومية',
            'description' => 'حذف السجلات اليومية للإنتاجية',
            'module' => 'productivity',
            'category' => 'daily_logs'
        ],
        [
            'name' => 'productivity_daily_logs_submit',
            'display_name' => 'إرسال السجلات للاعتماد',
            'description' => 'إرسال السجلات اليومية للاعتماد',
            'module' => 'productivity',
            'category' => 'daily_logs'
        ],
        [
            'name' => 'productivity_daily_logs_view_all_branches',
            'display_name' => 'عرض سجلات جميع الفروع',
            'description' => 'عرض السجلات اليومية لجميع الفروع',
            'module' => 'productivity',
            'category' => 'daily_logs'
        ],
        
        // الاعتمادات
        [
            'name' => 'productivity_approvals_view',
            'display_name' => 'عرض الاعتمادات',
            'description' => 'عرض قائمة الاعتمادات والسجلات المعلقة',
            'module' => 'productivity',
            'category' => 'approvals'
        ],
        [
            'name' => 'productivity_approvals_approve',
            'display_name' => 'اعتماد السجلات',
            'description' => 'اعتماد السجلات اليومية للإنتاجية',
            'module' => 'productivity',
            'category' => 'approvals'
        ],
        [
            'name' => 'productivity_approvals_reject',
            'display_name' => 'رفض السجلات',
            'description' => 'رفض السجلات اليومية للإنتاجية',
            'module' => 'productivity',
            'category' => 'approvals'
        ],
        [
            'name' => 'productivity_approvals_return',
            'display_name' => 'إرجاع السجلات للتعديل',
            'description' => 'إرجاع السجلات اليومية للتعديل',
            'module' => 'productivity',
            'category' => 'approvals'
        ],
        [
            'name' => 'productivity_approvals_view_history',
            'display_name' => 'عرض تاريخ الاعتمادات',
            'description' => 'عرض تاريخ الاعتمادات والرفض',
            'module' => 'productivity',
            'category' => 'approvals'
        ],
        
        // إدارة المعتمدين
        [
            'name' => 'productivity_approvers_view',
            'display_name' => 'عرض المعتمدين',
            'description' => 'عرض قائمة المعتمدين وصلاحياتهم',
            'module' => 'productivity',
            'category' => 'approvers'
        ],
        [
            'name' => 'productivity_approvers_create',
            'display_name' => 'إضافة المعتمدين',
            'description' => 'إضافة معتمدين جدد للنظام',
            'module' => 'productivity',
            'category' => 'approvers'
        ],
        [
            'name' => 'productivity_approvers_edit',
            'display_name' => 'تعديل المعتمدين',
            'description' => 'تعديل صلاحيات المعتمدين',
            'module' => 'productivity',
            'category' => 'approvers'
        ],
        [
            'name' => 'productivity_approvers_delete',
            'display_name' => 'حذف المعتمدين',
            'description' => 'حذف المعتمدين من النظام',
            'module' => 'productivity',
            'category' => 'approvers'
        ],
        [
            'name' => 'productivity_approvers_toggle_status',
            'display_name' => 'تفعيل/إلغاء تفعيل المعتمدين',
            'description' => 'تفعيل أو إلغاء تفعيل المعتمدين',
            'module' => 'productivity',
            'category' => 'approvers'
        ],
        
        // التقارير والإحصائيات
        [
            'name' => 'productivity_reports_view',
            'display_name' => 'عرض تقارير الإنتاجية',
            'description' => 'عرض التقارير والإحصائيات العامة',
            'module' => 'productivity',
            'category' => 'reports'
        ],
        [
            'name' => 'productivity_reports_daily',
            'display_name' => 'تقرير الإنتاجية اليومية',
            'description' => 'عرض تقرير الإنتاجية اليومية',
            'module' => 'productivity',
            'category' => 'reports'
        ],
        [
            'name' => 'productivity_reports_monthly',
            'display_name' => 'تقرير الإنتاجية الشهرية',
            'description' => 'عرض تقرير الإنتاجية الشهرية',
            'module' => 'productivity',
            'category' => 'reports'
        ],
        [
            'name' => 'productivity_reports_performance',
            'display_name' => 'تقرير الأداء والكفاءة',
            'description' => 'عرض تقارير الأداء والكفاءة',
            'module' => 'productivity',
            'category' => 'reports'
        ],
        [
            'name' => 'productivity_reports_comparison',
            'display_name' => 'تقرير مقارنة الأداء',
            'description' => 'مقارنة الأداء بين الفروع والفترات',
            'module' => 'productivity',
            'category' => 'reports'
        ],
        [
            'name' => 'productivity_reports_export',
            'display_name' => 'تصدير التقارير',
            'description' => 'تصدير تقارير الإنتاجية إلى Excel/PDF',
            'module' => 'productivity',
            'category' => 'reports'
        ],
        
        // لوحة التحكم
        [
            'name' => 'productivity_dashboard_view',
            'display_name' => 'عرض لوحة تحكم الإنتاجية',
            'description' => 'عرض لوحة التحكم الرئيسية للإنتاجية',
            'module' => 'productivity',
            'category' => 'dashboard'
        ],
        [
            'name' => 'productivity_dashboard_statistics',
            'display_name' => 'عرض إحصائيات لوحة التحكم',
            'description' => 'عرض الإحصائيات في لوحة التحكم',
            'module' => 'productivity',
            'category' => 'dashboard'
        ],
        
        // إدارة النظام
        [
            'name' => 'productivity_system_settings',
            'display_name' => 'إعدادات نظام الإنتاجية',
            'description' => 'إدارة إعدادات نظام الإنتاجية',
            'module' => 'productivity',
            'category' => 'system'
        ],
        [
            'name' => 'productivity_audit_logs_view',
            'display_name' => 'عرض سجل العمليات',
            'description' => 'عرض سجل جميع العمليات في النظام',
            'module' => 'productivity',
            'category' => 'system'
        ]
    ];
    
    echo "1. إدراج صلاحيات نظام الإنتاجية...\n";
    
    $insertedCount = 0;
    $skippedCount = 0;
    
    foreach ($productivityPermissions as $permission) {
        // التحقق من وجود الصلاحية
        $checkSql = "SELECT COUNT(*) FROM permissions WHERE name = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$permission['name']]);
        
        if ($checkStmt->fetchColumn() == 0) {
            // إدراج الصلاحية الجديدة
            $insertSql = "
                INSERT INTO permissions (name, display_name, description, module, category)
                VALUES (?, ?, ?, ?, ?)
            ";
            $insertStmt = $db->prepare($insertSql);
            $insertStmt->execute([
                $permission['name'],
                $permission['display_name'],
                $permission['description'],
                $permission['module'],
                $permission['category'] ?? ''
            ]);
            
            $insertedCount++;
            echo "   ✅ {$permission['display_name']}\n";
        } else {
            $skippedCount++;
            echo "   ⚠️ {$permission['display_name']} (موجودة مسبقاً)\n";
        }
    }
    
    echo "\n2. إضافة الصلاحيات للأدوار الافتراضية...\n";
    
    // إضافة جميع الصلاحيات لدور المدير العام
    $adminRoleId = 1; // افتراض أن دور المدير العام له ID = 1
    
    $adminPermissionsSql = "
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT ?, p.id 
        FROM permissions p 
        WHERE p.module = 'productivity'
    ";
    $adminPermissionsStmt = $db->prepare($adminPermissionsSql);
    $adminPermissionsStmt->execute([$adminRoleId]);
    
    echo "   ✅ تم إضافة جميع صلاحيات الإنتاجية لدور المدير العام\n";
    
    echo "\n🎉 تم إدراج صلاحيات نظام الإنتاجية بنجاح!\n";
    echo "=====================================\n";
    echo "📊 الإحصائيات:\n";
    echo "   - صلاحيات جديدة: {$insertedCount}\n";
    echo "   - صلاحيات موجودة: {$skippedCount}\n";
    echo "   - إجمالي الصلاحيات: " . count($productivityPermissions) . "\n";
    echo "\n✅ النظام جاهز للاستخدام!\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "📍 الملف: " . $e->getFile() . "\n";
    echo "📍 السطر: " . $e->getLine() . "\n";
    exit(1);
}
?>
