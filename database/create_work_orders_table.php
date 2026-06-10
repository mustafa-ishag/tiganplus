<?php

declare(strict_types=1);

// تحميل التطبيق
require_once __DIR__ . '/../bootstrap/app.php';

use EtganERP\Infrastructure\Database\DatabaseConnection;

try {
    echo "إنشاء جدول أوامر العمل...\n";

    // إنشاء جدول work_orders
    $sql = "
    CREATE TABLE IF NOT EXISTS work_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_number VARCHAR(9) NOT NULL UNIQUE COMMENT 'رقم أمر العمل (مثل TAF240001)',
        work_order_type_id INT NOT NULL COMMENT 'معرف نوع أمر العمل',
        department ENUM('connections', 'projects') NOT NULL COMMENT 'القسم',
        current_entity_id INT NULL COMMENT 'معرف الجهة الحالية',
        branch_id INT NOT NULL COMMENT 'معرف الفرع',
        assignment_date DATE NULL COMMENT 'تاريخ التكليف',
        receipt_date DATE NULL COMMENT 'تاريخ الاستلام',
        estimated_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'القيمة المقدرة',
        actual_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'القيمة الفعلية',
        disbursement_status ENUM(
            'none', 
            'completed', 
            'disbursement', 
            'return', 
            'partial_disbursement', 
            'pending_disbursement', 
            'cancelled_disbursement'
        ) NOT NULL DEFAULT 'none' COMMENT 'حالة الصرف',
        notes TEXT NULL COMMENT 'ملاحظات',
        extract_id INT NULL COMMENT 'معرف المستخلص المرتبط',
        status ENUM('active', 'inactive', 'completed', 'cancelled') NOT NULL DEFAULT 'active' COMMENT 'حالة أمر العمل',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'تاريخ الإنشاء',
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'تاريخ آخر تحديث',
        
        -- Foreign Keys
        FOREIGN KEY (work_order_type_id) REFERENCES work_order_types(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT ON UPDATE CASCADE,
        
        -- Indexes
        INDEX idx_work_order_number (work_order_number),
        INDEX idx_work_order_type (work_order_type_id),
        INDEX idx_department (department),
        INDEX idx_branch (branch_id),
        INDEX idx_assignment_date (assignment_date),
        INDEX idx_receipt_date (receipt_date),
        INDEX idx_disbursement_status (disbursement_status),
        INDEX idx_extract (extract_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        
        -- Composite Indexes
        INDEX idx_branch_department (branch_id, department),
        INDEX idx_status_department (status, department),
        INDEX idx_dates_range (assignment_date, receipt_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول أوامر العمل';
    ";

    DatabaseConnection::execute($sql);
    echo "✅ تم إنشاء جدول work_orders بنجاح\n";

    // إضافة بيانات تجريبية
    echo "\nإضافة بيانات تجريبية...\n";

    // جلب معرفات الفروع وأنواع أوامر العمل
    $branches = DatabaseConnection::fetchAll("SELECT id, code FROM branches WHERE status = 'active'");
    $workOrderTypes = DatabaseConnection::fetchAll("SELECT id, type_code FROM work_order_types WHERE status = 'active'");

    if (empty($branches)) {
        echo "⚠️ لا توجد فروع نشطة في قاعدة البيانات\n";
        exit;
    }

    if (empty($workOrderTypes)) {
        echo "⚠️ لا توجد أنواع أوامر عمل نشطة في قاعدة البيانات\n";
        exit;
    }

    // إعداد البيانات التجريبية
    $sampleWorkOrders = [
        [
            'branch_code' => 'TAF',
            'work_order_type_code' => 'CON',
            'department' => 'connections',
            'assignment_date' => '2024-01-15',
            'receipt_date' => '2024-01-20',
            'estimated_value' => 15000.00,
            'actual_value' => 14500.00,
            'disbursement_status' => 'completed',
            'notes' => 'توصيل كهرباء لمنزل سكني - حي النسيم',
            'status' => 'completed'
        ],
        [
            'branch_code' => 'TAF',
            'work_order_type_code' => 'DEV',
            'department' => 'projects',
            'assignment_date' => '2024-02-01',
            'receipt_date' => null,
            'estimated_value' => 250000.00,
            'actual_value' => 0.00,
            'disbursement_status' => 'pending_disbursement',
            'notes' => 'مشروع إنارة شارع الملك فهد',
            'status' => 'active'
        ],
        [
            'branch_code' => 'RAN',
            'work_order_type_code' => 'CON',
            'department' => 'connections',
            'assignment_date' => '2024-02-10',
            'receipt_date' => '2024-02-15',
            'estimated_value' => 8500.00,
            'actual_value' => 8200.00,
            'disbursement_status' => 'disbursement',
            'notes' => 'توصيل كهرباء لمحل تجاري',
            'status' => 'active'
        ],
        [
            'branch_code' => 'TUR',
            'work_order_type_code' => 'MNT',
            'department' => 'connections',
            'assignment_date' => '2024-01-25',
            'receipt_date' => '2024-02-05',
            'estimated_value' => 45000.00,
            'actual_value' => 43500.00,
            'disbursement_status' => 'completed',
            'notes' => 'صيانة محول كهربائي رئيسي',
            'status' => 'completed'
        ],
        [
            'branch_code' => 'KHU',
            'work_order_type_code' => 'DEV',
            'department' => 'projects',
            'assignment_date' => '2024-02-20',
            'receipt_date' => null,
            'estimated_value' => 180000.00,
            'actual_value' => 0.00,
            'disbursement_status' => 'none',
            'notes' => 'مشروع تطوير شبكة الكهرباء - المرحلة الأولى',
            'status' => 'active'
        ]
    ];

    // إدراج البيانات التجريبية
    foreach ($sampleWorkOrders as $index => $workOrderData) {
        // البحث عن معرف الفرع
        $branchId = null;
        foreach ($branches as $branch) {
            if ($branch['code'] === $workOrderData['branch_code']) {
                $branchId = $branch['id'];
                break;
            }
        }

        // البحث عن معرف نوع أمر العمل
        $workOrderTypeId = null;
        foreach ($workOrderTypes as $type) {
            if ($type['type_code'] === $workOrderData['work_order_type_code']) {
                $workOrderTypeId = $type['id'];
                break;
            }
        }

        if (!$branchId || !$workOrderTypeId) {
            echo "⚠️ تخطي أمر العمل " . ($index + 1) . " - بيانات مرجعية مفقودة\n";
            continue;
        }

        // توليد رقم أمر العمل
        $currentYear = date('y');
        $workOrderNumber = $workOrderData['branch_code'] . $currentYear . str_pad((string)($index + 1), 4, '0', STR_PAD_LEFT);

        $insertSql = "
        INSERT INTO work_orders (
            work_order_number, work_order_type_id, department, branch_id,
            assignment_date, receipt_date, estimated_value, actual_value,
            disbursement_status, notes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        DatabaseConnection::execute($insertSql, [
            $workOrderNumber,
            $workOrderTypeId,
            $workOrderData['department'],
            $branchId,
            $workOrderData['assignment_date'],
            $workOrderData['receipt_date'],
            $workOrderData['estimated_value'],
            $workOrderData['actual_value'],
            $workOrderData['disbursement_status'],
            $workOrderData['notes'],
            $workOrderData['status']
        ]);

        echo "✅ تم إدراج أمر العمل: {$workOrderNumber}\n";
    }

    echo "\n🎉 تم إنشاء جدول أوامر العمل وإضافة البيانات التجريبية بنجاح!\n";
    echo "📊 تم إدراج " . count($sampleWorkOrders) . " أوامر عمل تجريبية\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    exit(1);
}
?>
