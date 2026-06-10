<?php
/**
 * إنشاء نظام إدارة المخزن الشامل
 * Create Comprehensive Inventory Management System
 * 
 * هذا الملف ينشئ جميع الجداول المطلوبة لنظام إدارة المخزن
 * بما في ذلك المواد، العمليات، الطلبات، الموافقات، وشهادات الإنجاز
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = getDB();
    
    echo "🏗️ إنشاء نظام إدارة المخزن الشامل...\n\n";
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // 1. جدول المواد الرئيسي
    echo "1. إنشاء جدول المواد الرئيسي...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_number VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم البند (حتى 20 حرف)',
        group_number VARCHAR(10) NOT NULL COMMENT 'رقم مجاميع المواد (10 أرقام)',
        description TEXT NOT NULL COMMENT 'وصف المادة الكهربائية',
        unit VARCHAR(50) NOT NULL COMMENT 'وحدة القياس (متر، قطعة، كيلو، إلخ)',
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'سعر الوحدة',
        current_stock DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT 'المخزون الحالي',
        minimum_stock DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT 'الحد الأدنى للمخزون',
        maximum_stock DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT 'الحد الأقصى للمخزون',
        location VARCHAR(100) COMMENT 'موقع التخزين',
        is_active BOOLEAN DEFAULT TRUE COMMENT 'حالة النشاط',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_item_number (item_number),
        INDEX idx_group_number (group_number),
        INDEX idx_description (description(100)),
        INDEX idx_stock_level (current_stock),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='المواد الكهربائية';
    ");
    echo "✅ تم إنشاء جدول materials\n";
    
    // 3. جدول مواقع التخزين
    echo "\n3. إنشاء جدول مواقع التخزين...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS inventory_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        location_code VARCHAR(20) NOT NULL UNIQUE COMMENT 'كود الموقع',
        location_name VARCHAR(255) NOT NULL COMMENT 'اسم الموقع',
        branch_id INT NOT NULL COMMENT 'معرف الفرع',
        description TEXT COMMENT 'وصف الموقع',
        is_active BOOLEAN DEFAULT TRUE COMMENT 'حالة النشاط',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        INDEX idx_location_code (location_code),
        INDEX idx_branch (branch_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مواقع التخزين';
    ");
    echo "✅ تم إنشاء جدول inventory_locations\n";
    
    // 4. جدول العمليات الأساسية
    echo "\n4. إنشاء جدول العمليات الأساسية...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS inventory_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_number VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم العملية',
        transaction_type ENUM('incoming', 'outgoing', 'transfer', 'return') NOT NULL COMMENT 'نوع العملية',
        branch_id INT NOT NULL COMMENT 'معرف الفرع',
        location_id INT COMMENT 'معرف الموقع',
        reference_number VARCHAR(50) COMMENT 'رقم المستند المرجعي',
        transaction_date DATE NOT NULL COMMENT 'تاريخ العملية',
        
        -- معلومات المصدر (للوارد)
        source_entity VARCHAR(255) DEFAULT 'الشركة السعودية للكهرباء' COMMENT 'مصدر المواد',
        
        -- معلومات الوجهة (للتحويل)
        destination_branch_id INT COMMENT 'فرع الوجهة للتحويل',
        destination_location_id INT COMMENT 'موقع الوجهة للتحويل',
        
        -- معلومات الطلب (للصادر)
        material_request_id INT COMMENT 'معرف طلب الصرف',
        work_order_id INT COMMENT 'معرف أمر العمل',
        
        total_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي القيمة',
        status ENUM('draft', 'confirmed', 'completed', 'cancelled') DEFAULT 'draft' COMMENT 'حالة العملية',
        notes TEXT COMMENT 'ملاحظات',
        
        created_by INT NOT NULL COMMENT 'منشئ العملية',
        approved_by INT COMMENT 'معتمد العملية',
        approved_at TIMESTAMP NULL COMMENT 'تاريخ الاعتماد',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
        FOREIGN KEY (destination_branch_id) REFERENCES branches(id) ON DELETE SET NULL,
        FOREIGN KEY (destination_location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        
        INDEX idx_transaction_number (transaction_number),
        INDEX idx_type (transaction_type),
        INDEX idx_date (transaction_date),
        INDEX idx_branch (branch_id),
        INDEX idx_status (status),
        INDEX idx_request (material_request_id),
        INDEX idx_work_order (work_order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='العمليات الأساسية للمخزون';
    ");
    echo "✅ تم إنشاء جدول inventory_transactions\n";

    // 5. جدول تفاصيل العمليات
    echo "\n5. إنشاء جدول تفاصيل العمليات...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS inventory_transaction_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL COMMENT 'معرف العملية',
        material_id INT NOT NULL COMMENT 'معرف المادة',
        quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية',
        unit_price DECIMAL(10,2) NOT NULL COMMENT 'سعر الوحدة',
        total_price DECIMAL(15,2) NOT NULL COMMENT 'إجمالي السعر',
        notes TEXT COMMENT 'ملاحظات خاصة بالمادة',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (transaction_id) REFERENCES inventory_transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,

        INDEX idx_transaction (transaction_id),
        INDEX idx_material (material_id),
        INDEX idx_quantity (quantity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تفاصيل العمليات';
    ");
    echo "✅ تم إنشاء جدول inventory_transaction_details\n";

    // 6. جدول طلبات الصرف
    echo "\n6. إنشاء جدول طلبات الصرف...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS material_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_number VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم طلب الصرف',
        work_order_id INT NOT NULL COMMENT 'معرف أمر العمل',
        branch_id INT NOT NULL COMMENT 'معرف الفرع',
        requested_by INT NOT NULL COMMENT 'طالب الصرف (المهندس)',
        request_date DATE NOT NULL COMMENT 'تاريخ الطلب',
        required_date DATE COMMENT 'تاريخ الحاجة للمواد',

        purpose TEXT NOT NULL COMMENT 'سبب الصرف ووصف العمل',
        total_estimated_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي القيمة المقدرة',

        -- حالة الطلب
        status ENUM('draft', 'submitted', 'warehouse_approved', 'project_approved', 'branch_approved', 'completed', 'rejected', 'cancelled') DEFAULT 'draft' COMMENT 'حالة الطلب',

        -- مراحل الموافقة
        warehouse_approved_by INT COMMENT 'معتمد من مدير المستودع',
        warehouse_approved_at TIMESTAMP NULL COMMENT 'تاريخ موافقة المستودع',
        warehouse_notes TEXT COMMENT 'ملاحظات مدير المستودع',

        project_approved_by INT COMMENT 'معتمد من مدير المشروع',
        project_approved_at TIMESTAMP NULL COMMENT 'تاريخ موافقة المشروع',
        project_notes TEXT COMMENT 'ملاحظات مدير المشروع',

        branch_approved_by INT COMMENT 'معتمد من مدير الفرع',
        branch_approved_at TIMESTAMP NULL COMMENT 'تاريخ موافقة الفرع',
        branch_notes TEXT COMMENT 'ملاحظات مدير الفرع',

        -- معلومات الرفض
        rejected_by INT COMMENT 'رافض الطلب',
        rejected_at TIMESTAMP NULL COMMENT 'تاريخ الرفض',
        rejection_reason TEXT COMMENT 'سبب الرفض',

        -- معلومات الإكمال
        completed_by INT COMMENT 'منفذ الطلب',
        completed_at TIMESTAMP NULL COMMENT 'تاريخ الإكمال',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE RESTRICT,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (warehouse_approved_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (project_approved_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (branch_approved_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,

        INDEX idx_request_number (request_number),
        INDEX idx_work_order (work_order_id),
        INDEX idx_branch (branch_id),
        INDEX idx_requested_by (requested_by),
        INDEX idx_status (status),
        INDEX idx_request_date (request_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='طلبات صرف المواد';
    ");
    echo "✅ تم إنشاء جدول material_requests\n";

    // 7. جدول تفاصيل طلبات الصرف
    echo "\n7. إنشاء جدول تفاصيل طلبات الصرف...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS material_request_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL COMMENT 'معرف طلب الصرف',
        material_id INT NOT NULL COMMENT 'معرف المادة',
        requested_quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية المطلوبة',
        approved_quantity DECIMAL(10,3) DEFAULT 0.000 COMMENT 'الكمية المعتمدة',
        issued_quantity DECIMAL(10,3) DEFAULT 0.000 COMMENT 'الكمية المصروفة فعلياً',
        unit_price DECIMAL(10,2) NOT NULL COMMENT 'سعر الوحدة',
        total_price DECIMAL(15,2) NOT NULL COMMENT 'إجمالي السعر',
        notes TEXT COMMENT 'ملاحظات خاصة بالمادة',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (request_id) REFERENCES material_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,

        INDEX idx_request (request_id),
        INDEX idx_material (material_id),
        INDEX idx_quantities (requested_quantity, approved_quantity, issued_quantity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تفاصيل طلبات الصرف';
    ");
    echo "✅ تم إنشاء جدول material_request_details\n";

    // 8. جدول شهادات الإنجاز
    echo "\n8. إنشاء جدول شهادات الإنجاز...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS completion_certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        certificate_number VARCHAR(20) NOT NULL UNIQUE COMMENT 'رقم شهادة الإنجاز',
        work_order_id INT NOT NULL COMMENT 'معرف أمر العمل',
        branch_id INT NOT NULL COMMENT 'معرف الفرع',
        certificate_date DATE NOT NULL COMMENT 'تاريخ الشهادة',

        -- معلومات الشهادة
        title VARCHAR(255) NOT NULL COMMENT 'عنوان الشهادة',
        description TEXT COMMENT 'وصف الأعمال المنجزة',

        -- القيم المالية
        total_materials_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي قيمة المواد',
        total_works_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي قيمة الأعمال',
        total_certificate_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'إجمالي قيمة الشهادة',

        -- حالة الشهادة
        status ENUM('draft', 'pending_review', 'approved', 'completed', 'cancelled') DEFAULT 'draft' COMMENT 'حالة الشهادة',

        -- معلومات المسؤول
        certificate_officer_id INT COMMENT 'معرف موظف شهادات الإنجاز',
        reviewed_by INT COMMENT 'مراجع الشهادة',
        reviewed_at TIMESTAMP NULL COMMENT 'تاريخ المراجعة',
        approved_by INT COMMENT 'معتمد الشهادة',
        approved_at TIMESTAMP NULL COMMENT 'تاريخ الاعتماد',

        notes TEXT COMMENT 'ملاحظات',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE RESTRICT,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
        FOREIGN KEY (certificate_officer_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,

        INDEX idx_certificate_number (certificate_number),
        INDEX idx_work_order (work_order_id),
        INDEX idx_branch (branch_id),
        INDEX idx_status (status),
        INDEX idx_date (certificate_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='شهادات الإنجاز';
    ");
    echo "✅ تم إنشاء جدول completion_certificates\n";

    // 9. جدول مواد شهادات الإنجاز
    echo "\n9. إنشاء جدول مواد شهادات الإنجاز...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS completion_certificate_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        certificate_id INT NOT NULL COMMENT 'معرف شهادة الإنجاز',
        material_id INT NOT NULL COMMENT 'معرف المادة',
        material_request_id INT COMMENT 'معرف طلب الصرف الأصلي',
        quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية المستخدمة',
        unit_price DECIMAL(10,2) NOT NULL COMMENT 'سعر الوحدة',
        total_value DECIMAL(15,2) NOT NULL COMMENT 'إجمالي القيمة',

        -- معلومات الربط التلقائي
        auto_added BOOLEAN DEFAULT FALSE COMMENT 'تمت الإضافة تلقائياً',
        added_from_request BOOLEAN DEFAULT FALSE COMMENT 'أضيفت من طلب صرف',

        notes TEXT COMMENT 'ملاحظات',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (certificate_id) REFERENCES completion_certificates(id) ON DELETE CASCADE,
        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
        FOREIGN KEY (material_request_id) REFERENCES material_requests(id) ON DELETE SET NULL,

        INDEX idx_certificate (certificate_id),
        INDEX idx_material (material_id),
        INDEX idx_request (material_request_id),
        INDEX idx_auto_added (auto_added)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مواد شهادات الإنجاز';
    ");
    echo "✅ تم إنشاء جدول completion_certificate_materials\n";

    // 10. جدول أعمال شهادات الإنجاز
    echo "\n10. إنشاء جدول أعمال شهادات الإنجاز...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS completion_certificate_works (
        id INT AUTO_INCREMENT PRIMARY KEY,
        certificate_id INT NOT NULL COMMENT 'معرف شهادة الإنجاز',
        work_item_code VARCHAR(50) NOT NULL COMMENT 'كود بند العمل',
        work_description TEXT NOT NULL COMMENT 'وصف العمل',
        unit VARCHAR(50) NOT NULL COMMENT 'وحدة القياس',
        quantity DECIMAL(10,3) NOT NULL COMMENT 'الكمية المنجزة',
        unit_price DECIMAL(10,2) NOT NULL COMMENT 'سعر الوحدة',
        total_value DECIMAL(15,2) NOT NULL COMMENT 'إجمالي القيمة',

        -- معلومات الربط
        related_material_id INT COMMENT 'معرف المادة المرتبطة',
        auto_calculated BOOLEAN DEFAULT FALSE COMMENT 'محسوبة تلقائياً من المواد',

        notes TEXT COMMENT 'ملاحظات',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (certificate_id) REFERENCES completion_certificates(id) ON DELETE CASCADE,
        FOREIGN KEY (related_material_id) REFERENCES materials(id) ON DELETE SET NULL,

        INDEX idx_certificate (certificate_id),
        INDEX idx_work_code (work_item_code),
        INDEX idx_related_material (related_material_id),
        INDEX idx_auto_calculated (auto_calculated)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='أعمال شهادات الإنجاز';
    ");
    echo "✅ تم إنشاء جدول completion_certificate_works\n";

    // 11. جدول علاقات المواد والأعمال
    echo "\n11. إنشاء جدول علاقات المواد والأعمال...\n";
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS material_work_relations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL COMMENT 'معرف المادة',
        work_item_code VARCHAR(50) NOT NULL COMMENT 'كود بند العمل',
        work_description TEXT NOT NULL COMMENT 'وصف العمل',
        conversion_factor DECIMAL(10,4) NOT NULL DEFAULT 1.0000 COMMENT 'معامل التحويل (كمية العمل لكل وحدة مادة)',
        work_unit VARCHAR(50) NOT NULL COMMENT 'وحدة قياس العمل',
        work_unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'سعر وحدة العمل',
        is_active BOOLEAN DEFAULT TRUE COMMENT 'حالة النشاط',

        notes TEXT COMMENT 'ملاحظات',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,

        INDEX idx_material (material_id),
        INDEX idx_work_code (work_item_code),
        INDEX idx_active (is_active),

        UNIQUE KEY unique_material_work (material_id, work_item_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='علاقات المواد والأعمال';
    ");
    echo "✅ تم إنشاء جدول material_work_relations\n";

    // 12. إدراج البيانات الافتراضية
    echo "\n12. إدراج البيانات الافتراضية...\n";

    // إدراج مواد تجريبية
    $pdo->exec("
    INSERT IGNORE INTO materials (item_number, group_number, description, unit, unit_price, current_stock, minimum_stock, maximum_stock, location) VALUES
    ('CABLE-001', '1000000000', 'كابل كهربائي 4×16 مم²', 'متر', 25.50, 1000.000, 100.000, 5000.000, 'TAF-MAIN'),
    ('CABLE-002', '1000000000', 'كابل كهربائي 4×25 مم²', 'متر', 35.75, 800.000, 100.000, 3000.000, 'TAF-MAIN'),
    ('POLE-001', '2000000000', 'عمود إنارة معدني 8 متر', 'قطعة', 850.00, 50.000, 10.000, 200.000, 'TAF-MAIN'),
    ('TRANS-001', '3000000000', 'محول توزيع 100 كيلو فولت أمبير', 'قطعة', 15000.00, 5.000, 2.000, 20.000, 'TAF-MAIN'),
    ('PANEL-001', '4000000000', 'لوحة توزيع رئيسية 400 أمبير', 'قطعة', 2500.00, 10.000, 3.000, 50.000, 'TAF-MAIN'),
    ('INSUL-001', '5000000000', 'شريط عزل كهربائي', 'لفة', 12.50, 200.000, 50.000, 1000.000, 'TAF-SEC'),
    ('TOOL-001', '6000000000', 'مفك كهربائي عازل', 'قطعة', 45.00, 30.000, 10.000, 100.000, 'TAF-SEC'),
    ('METER-001', '7000000000', 'جهاز قياس الجهد الرقمي', 'قطعة', 350.00, 15.000, 5.000, 50.000, 'TAF-MAIN'),
    ('MAINT-001', '8000000000', 'زيت تشحيم للمحولات', 'لتر', 85.00, 100.000, 20.000, 500.000, 'TAF-MAIN'),
    ('MISC-001', '9000000000', 'صندوق توصيل كهربائي', 'قطعة', 25.00, 75.000, 20.000, 300.000, 'TAF-SEC');
    ");

    // إدراج مواقع التخزين الافتراضية
    $pdo->exec("
    INSERT IGNORE INTO inventory_locations (location_code, location_name, branch_id, description) VALUES
    ('TAF-MAIN', 'المستودع الرئيسي - الطائف', 1, 'المستودع الرئيسي لفرع الطائف'),
    ('TAF-SEC', 'المستودع الثانوي - الطائف', 1, 'المستودع الثانوي لفرع الطائف'),
    ('RAN-MAIN', 'المستودع الرئيسي - رنية', 2, 'المستودع الرئيسي لفرع رنية'),
    ('RAN-SEC', 'المستودع الثانوي - رنية', 2, 'المستودع الثانوي لفرع رنية');
    ");

    echo "✅ تم إدراج البيانات الافتراضية\n";

    // 13. إنشاء الصلاحيات الجديدة
    echo "\n13. إنشاء صلاحيات نظام المخزون...\n";

    $inventoryPermissions = [
        // إدارة المواد
        ['name' => 'manage_materials', 'display_name' => 'إدارة المواد', 'description' => 'إضافة وتعديل وحذف المواد', 'module' => 'inventory'],
        ['name' => 'view_materials', 'display_name' => 'عرض المواد', 'description' => 'عرض قائمة المواد والتفاصيل', 'module' => 'inventory'],
        ['name' => 'manage_material_groups', 'display_name' => 'إدارة أرقام مجاميع المواد', 'description' => 'إدارة أرقام تصنيفات المواد', 'module' => 'inventory'],

        // العمليات الأساسية
        ['name' => 'manage_incoming_transactions', 'display_name' => 'إدارة عمليات الوارد', 'description' => 'تسجيل وإدارة عمليات الوارد', 'module' => 'inventory'],
        ['name' => 'manage_outgoing_transactions', 'display_name' => 'إدارة عمليات الصادر', 'description' => 'تسجيل وإدارة عمليات الصادر', 'module' => 'inventory'],
        ['name' => 'manage_transfer_transactions', 'display_name' => 'إدارة عمليات التحويل', 'description' => 'تسجيل وإدارة عمليات التحويل', 'module' => 'inventory'],
        ['name' => 'view_inventory_transactions', 'display_name' => 'عرض عمليات المخزون', 'description' => 'عرض جميع عمليات المخزون', 'module' => 'inventory'],

        // طلبات الصرف
        ['name' => 'create_material_requests', 'display_name' => 'إنشاء طلبات الصرف', 'description' => 'إنشاء طلبات صرف المواد', 'module' => 'inventory'],
        ['name' => 'view_material_requests', 'display_name' => 'عرض طلبات الصرف', 'description' => 'عرض طلبات صرف المواد', 'module' => 'inventory'],
        ['name' => 'approve_warehouse_requests', 'display_name' => 'موافقة مدير المستودع', 'description' => 'الموافقة على طلبات الصرف كمدير مستودع', 'module' => 'inventory'],
        ['name' => 'approve_project_requests', 'display_name' => 'موافقة مدير المشروع', 'description' => 'الموافقة على طلبات الصرف كمدير مشروع', 'module' => 'inventory'],
        ['name' => 'approve_branch_requests', 'display_name' => 'موافقة مدير الفرع', 'description' => 'الموافقة النهائية على طلبات الصرف', 'module' => 'inventory'],

        // شهادات الإنجاز
        ['name' => 'manage_completion_certificates', 'display_name' => 'إدارة شهادات الإنجاز', 'description' => 'إنشاء وإدارة شهادات الإنجاز', 'module' => 'inventory'],
        ['name' => 'view_completion_certificates', 'display_name' => 'عرض شهادات الإنجاز', 'description' => 'عرض شهادات الإنجاز', 'module' => 'inventory'],

        // التقارير
        ['name' => 'view_inventory_reports', 'display_name' => 'عرض تقارير المخزون', 'description' => 'عرض تقارير المخزون والعمليات', 'module' => 'inventory'],
        ['name' => 'export_inventory_reports', 'display_name' => 'تصدير تقارير المخزون', 'description' => 'تصدير تقارير المخزون', 'module' => 'inventory']
    ];

    foreach ($inventoryPermissions as $permission) {
        $pdo->exec("
        INSERT IGNORE INTO permissions (name, display_name, description, module)
        VALUES ('{$permission['name']}', '{$permission['display_name']}', '{$permission['description']}', '{$permission['module']}')
        ");
    }

    echo "✅ تم إنشاء صلاحيات نظام المخزون\n";

    // تأكيد المعاملة
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo "\n🎉 تم إنشاء نظام إدارة المخزن بنجاح!\n";
    echo "\n📊 ملخص ما تم إنشاؤه:\n";
    echo "- 11 جدول رئيسي لنظام المخزون\n";
    echo "- 9 مجاميع افتراضية للمواد\n";
    echo "- 4 مواقع تخزين افتراضية\n";
    echo "- 17 صلاحية جديدة لنظام المخزون\n";
    echo "\n✅ النظام جاهز للاستخدام!\n";

} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "❌ خطأ في إنشاء نظام المخزون: " . $e->getMessage() . "\n";
    echo "تم التراجع عن جميع التغييرات.\n";
    exit(1);
}
?>
