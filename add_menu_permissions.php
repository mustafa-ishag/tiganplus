<?php
require 'config/config.php';
require 'includes/functions.php';

$db = getDB();
$category = 'إظهار القوائم (التبويبات)';

$permissions = [
    // Main Tabs
    ['name' => 'menu_dashboard', 'display_name' => 'لوحة التحكم', 'description' => 'إظهار تبويب لوحة التحكم الرئيسية'],
    ['name' => 'menu_work_orders_main', 'display_name' => 'أوامر العمل (رئيسي)', 'description' => 'إظهار قائمة أوامر العمل بالكامل'],
    ['name' => 'menu_extracts_main', 'display_name' => 'المستخلصات (رئيسي)', 'description' => 'إظهار قائمة المستخلصات بالكامل'],
    ['name' => 'menu_inventory_main', 'display_name' => 'إدارة المخزون (رئيسي)', 'description' => 'إظهار قائمة إدارة المخزون بالكامل'],
    ['name' => 'menu_certificates_main', 'display_name' => 'شهادات الإنجاز (رئيسي)', 'description' => 'إظهار قائمة شهادات الإنجاز بالكامل'],
    ['name' => 'menu_productivity_main', 'display_name' => 'نظام الإنتاجية (رئيسي)', 'description' => 'إظهار قائمة نظام الإنتاجية بالكامل'],
    ['name' => 'menu_site_management_main', 'display_name' => 'إدارة الموقع (رئيسي)', 'description' => 'إظهار قائمة إدارة الموقع بالكامل'],

    // Work Orders Sub
    ['name' => 'menu_work_orders_list', 'display_name' => 'أوامر العمل: عرض', 'description' => 'إظهار تبويب عرض أوامر العمل'],
    ['name' => 'menu_work_orders_create', 'display_name' => 'أوامر العمل: إنشاء', 'description' => 'إظهار تبويب إنشاء أمر عمل جديد'],
    ['name' => 'menu_work_orders_types', 'display_name' => 'أوامر العمل: أنواع', 'description' => 'إظهار تبويب أنواع أوامر العمل'],
    ['name' => 'menu_work_orders_reports', 'display_name' => 'أوامر العمل: تقارير', 'description' => 'إظهار تبويب تقارير أوامر العمل'],

    // Extracts Sub
    ['name' => 'menu_extracts_all', 'display_name' => 'المستخلصات: عرض جميع المستخلصات', 'description' => 'إظهار تبويب عرض جميع المستخلصات'],
    ['name' => 'menu_extracts_partial', 'display_name' => 'المستخلصات: المستخلصات الجزئية', 'description' => 'إظهار تبويب المستخلصات الجزئية'],
    ['name' => 'menu_extracts_final_regular', 'display_name' => 'المستخلصات: النهائية العادية', 'description' => 'إظهار تبويب المستخلصات النهائية العادية'],
    ['name' => 'menu_extracts_final_partial', 'display_name' => 'المستخلصات: النهائية للجزئية', 'description' => 'إظهار تبويب المستخلصات النهائية للجزئية'],
    ['name' => 'menu_extracts_create_partial', 'display_name' => 'المستخلصات: إنشاء جزئي', 'description' => 'إظهار تبويب إنشاء مستخلص جزئي'],
    ['name' => 'menu_extracts_create_final_reg', 'display_name' => 'المستخلصات: إنشاء نهائي عادي', 'description' => 'إظهار تبويب إنشاء مستخلص نهائي عادي'],
    ['name' => 'menu_extracts_create_final_part', 'display_name' => 'المستخلصات: إنشاء نهائي للجزئية', 'description' => 'إظهار تبويب إنشاء مستخلص نهائي للجزئية'],

    // Inventory Sub
    ['name' => 'menu_inventory_dashboard', 'display_name' => 'المخزون: لوحة التحكم', 'description' => 'إظهار تبويب لوحة تحكم المخزون'],
    ['name' => 'menu_inventory_materials', 'display_name' => 'المخزون: المواد', 'description' => 'إظهار تبويب عرض المواد'],
    ['name' => 'menu_inventory_transactions', 'display_name' => 'المخزون: معاملات', 'description' => 'إظهار تبويب معاملات المخزون'],
    ['name' => 'menu_inventory_requests', 'display_name' => 'المخزون: طلبات الصرف', 'description' => 'إظهار تبويب طلبات الصرف'],
    ['name' => 'menu_inventory_inactive', 'display_name' => 'المخزون: المواد غير النشطة', 'description' => 'إظهار تبويب المواد غير النشطة'],
    ['name' => 'menu_inventory_import_export', 'display_name' => 'المخزون: استيراد وتصدير', 'description' => 'إظهار تبويب استيراد وتصدير المواد'],
    ['name' => 'menu_inventory_catalog', 'display_name' => 'المخزون: كتالوج المواد', 'description' => 'إظهار تبويب كتالوج المواد'],
    ['name' => 'menu_inventory_work_items', 'display_name' => 'المخزون: ربط ببنود الأعمال', 'description' => 'إظهار تبويب ربط المواد ببنود الأعمال'],
    ['name' => 'menu_inventory_analysis', 'display_name' => 'المخزون: تحليل المواد', 'description' => 'إظهار تبويب تحليل المواد'],
    ['name' => 'menu_inventory_removed', 'display_name' => 'المخزون: المواد المزالة', 'description' => 'إظهار تبويب المواد المزالة'],
    ['name' => 'menu_inventory_removed_analysis', 'display_name' => 'المخزون: تحليل المزالة', 'description' => 'إظهار تبويب تحليل المواد المزالة'],
    ['name' => 'menu_inventory_clients', 'display_name' => 'المخزون: العملاء والمقاولين', 'description' => 'إظهار تبويب العملاء والمقاولين'],
    ['name' => 'menu_inventory_loans', 'display_name' => 'المخزون: إدارة السلف', 'description' => 'إظهار تبويب إدارة السلف'],

    // Certificates Sub
    ['name' => 'menu_cert_list', 'display_name' => 'الشهادات: عرض الشهادات', 'description' => 'إظهار تبويب عرض الشهادات'],
    ['name' => 'menu_cert_create', 'display_name' => 'الشهادات: إنشاء شهادة', 'description' => 'إظهار تبويب إنشاء شهادة جديدة'],
    ['name' => 'menu_cert_reports', 'display_name' => 'الشهادات: تقارير', 'description' => 'إظهار تبويب تقارير الشهادات'],
    ['name' => 'menu_cert_import', 'display_name' => 'الشهادات: استيراد (OCR)', 'description' => 'إظهار تبويب استيراد مقايسة'],

    // Productivity Sub
    ['name' => 'menu_prod_dashboard', 'display_name' => 'الإنتاجية: لوحة التحكم', 'description' => 'إظهار تبويب لوحة تحكم الإنتاجية'],
    ['name' => 'menu_prod_work_orders', 'display_name' => 'الإنتاجية: أوامر العمل', 'description' => 'إظهار تبويب أوامر العمل في الإنتاجية'],
    ['name' => 'menu_prod_work_items', 'display_name' => 'الإنتاجية: بنود الإنتاجية', 'description' => 'إظهار تبويب بنود الإنتاجية'],
    ['name' => 'menu_prod_daily_logs', 'display_name' => 'الإنتاجية: السجلات اليومية', 'description' => 'إظهار تبويب السجلات اليومية'],
    ['name' => 'menu_prod_approvals', 'display_name' => 'الإنتاجية: الاعتمادات', 'description' => 'إظهار تبويب الاعتمادات'],
    ['name' => 'menu_prod_approvers', 'display_name' => 'الإنتاجية: إدارة المعتمدين', 'description' => 'إظهار تبويب إدارة المعتمدين'],
    ['name' => 'menu_prod_reports', 'display_name' => 'الإنتاجية: التقارير', 'description' => 'إظهار تبويب تقارير الإنتاجية'],

    // Site Management Sub
    ['name' => 'menu_site_users', 'display_name' => 'الموقع: المستخدمين', 'description' => 'إظهار تبويب إدارة المستخدمين'],
    ['name' => 'menu_site_roles', 'display_name' => 'الموقع: الأدوار والصلاحيات', 'description' => 'إظهار تبويب الأدوار والصلاحيات'],
    ['name' => 'menu_site_branches', 'display_name' => 'الموقع: الفروع', 'description' => 'إظهار تبويب الفروع'],
    ['name' => 'menu_site_reference', 'display_name' => 'الموقع: البيانات المرجعية', 'description' => 'إظهار تبويب البيانات المرجعية'],
    ['name' => 'menu_site_work_items', 'display_name' => 'الموقع: بنود الأعمال', 'description' => 'إظهار تبويب إدارة بنود الأعمال'],
    ['name' => 'menu_site_admin', 'display_name' => 'الموقع: الإدارة العامة', 'description' => 'إظهار تبويب الإدارة العامة'],
    ['name' => 'menu_site_settings', 'display_name' => 'الموقع: إعدادات النظام', 'description' => 'إظهار تبويب إعدادات النظام'],
    ['name' => 'menu_site_invoice', 'display_name' => 'الموقع: إعدادات الفواتير', 'description' => 'إظهار تبويب إعدادات الفواتير الضريبية'],
    ['name' => 'menu_site_notifications', 'display_name' => 'الموقع: إدارة الإشعارات', 'description' => 'إظهار تبويب إدارة إشعارات النظام'],
];

try {
    $db->beginTransaction();

    $stmtInsert = $db->prepare("INSERT INTO permissions (name, display_name, description, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), description=VALUES(description), category=VALUES(category)");
    $stmtRolePerm = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");

    $insertedIds = [];

    foreach ($permissions as $p) {
        $stmtInsert->execute([$p['name'], $p['display_name'], $p['description'], $category]);
        
        // Get the ID
        $stmtGet = $db->prepare("SELECT id FROM permissions WHERE name = ?");
        $stmtGet->execute([$p['name']]);
        $permId = $stmtGet->fetchColumn();
        
        if ($permId) {
            $insertedIds[] = $permId;
        }
    }

    // Assign all to role 1 (Admin)
    foreach ($insertedIds as $permId) {
        $stmtRolePerm->execute([1, $permId]);
    }

    $db->commit();
    echo "Successfully inserted " . count($insertedIds) . " menu permissions and assigned to Admin role.";

} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage();
}
