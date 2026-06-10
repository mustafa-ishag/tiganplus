<?php
/**
 * تصدير فاتورة المستخلص النهائي للجزئي إلى Excel
 * Export Final For Partial Extract Invoice to Excel
 */

// تعطيل عرض الأخطاء لتجنب أي output قبل headers
error_reporting(0);
ini_set('display_errors', 0);

// تنظيف أي output buffer
if (ob_get_level()) {
    ob_end_clean();
}

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

// التحقق من وجود معرف المستخلص
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('معرف المستخلص غير صحيح');
}

$extract_id = (int) $_GET['id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // التحقق من الصلاحيات
    if (!hasPermission('extracts_export')) {
        header('Location: index.php');
        exit();
    }

    require_once __DIR__ . '/../../../includes/FinalForPartialInvoiceExcelExporter.php';

    $db = getDB();
    
    // جلب بيانات المستخلص النهائي للجزئي
    $extractQuery = "
        SELECT ffpe.*,
               b.name as branch_name,
               b.code as branch_code
        FROM final_for_partial_extracts ffpe
        LEFT JOIN branches b ON ffpe.branch_id = b.id
        WHERE ffpe.id = ?
    ";
    
    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        die('المستخلص غير موجود');
    }
    
    // جلب أوامر العمل من المستخلص النهائي للجزئي
    $workOrdersQuery = "
        SELECT ffpewo.*,
               wo.work_order_number,
               wot.type_code,
               wot.description as work_order_type_description
        FROM final_for_partial_extract_work_orders ffpewo
        LEFT JOIN work_orders wo ON ffpewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE ffpewo.final_for_partial_extract_id = ?
        ORDER BY wo.work_order_number ASC
    ";
    
    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$extract_id]);
    $workOrders = $stmt->fetchAll();
    
    // جلب أوامر العمل من المستخلص الجزئي المرتبط
    $partialWorkOrders = [];
    $partialExtractData = null;
    if (!empty($extract['related_partial_extract_id'])) {
        // جلب بيانات المستخلص الجزئي (للحصول على الضريبة الفعلية)
        $partialExtractQuery = "
            SELECT pe.*
            FROM partial_extracts pe
            WHERE pe.id = ?
        ";

        $stmt = $db->prepare($partialExtractQuery);
        $stmt->execute([$extract['related_partial_extract_id']]);
        $partialExtractData = $stmt->fetch();

        // جلب أوامر العمل من المستخلص الجزئي
        $partialWorkOrdersQuery = "
            SELECT pewo.*,
                   wo.work_order_number
            FROM partial_extract_work_orders pewo
            LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
            WHERE pewo.partial_extract_id = ?
            ORDER BY wo.work_order_number ASC
        ";

        $stmt = $db->prepare($partialWorkOrdersQuery);
        $stmt->execute([$extract['related_partial_extract_id']]);
        $partialWorkOrders = $stmt->fetchAll();
    }
    
    // جلب إعدادات الفاتورة
    $settingsQuery = "SELECT * FROM invoice_settings ORDER BY id DESC LIMIT 1";
    $settings = $db->query($settingsQuery)->fetch();
    
    if (!$settings) {
        // إعدادات افتراضية
        $settings = [
            'supplier_name' => 'اسم الشركة',
            'supplier_address' => 'عنوان الشركة',
            'supplier_tax_number' => '000000000000000',
            'supplier_logo_path' => '',
            'stamp_path' => '',
            'client_name' => 'اسم العميل',
            'client_address' => 'عنوان العميل',
            'client_tax_number' => '000000000000000',
            'contract_number' => 'رقم العقد',
            'contract_date' => date('Y-m-d'),
            'invoice_title' => 'فاتورة ضريبية نهائية',
            'tax_rate' => 15.00,
            'currency' => 'ريال',
            'header_color' => '2C5AA0',
            'accent_color' => '4CAF50'
        ];
    }
    
    // تحضير بيانات الفاتورة
    $invoiceData = [
        'extract_number' => $extract['extract_number'],
        'extract_date' => $extract['extract_date'],
        'branch_name' => $extract['branch_name'],
        'total_amount' => $extract['total_amount'],
        'tax_amount' => $extract['tax_amount'],
        'net_amount' => $extract['net_amount'],
        'total_penalty_amount' => $extract['total_penalty_amount']
    ];
    
    // إنشاء المصدر وتصدير الفاتورة
    $exporter = new FinalForPartialInvoiceExcelExporter($invoiceData, $settings, $workOrders, $partialWorkOrders, $partialExtractData);
    $exporter->export();
    
} catch (Exception $e) {
    die('خطأ في التصدير: ' . $e->getMessage());
}

