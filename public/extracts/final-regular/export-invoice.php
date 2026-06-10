<?php
/**
 * تصدير فاتورة المستخلص النهائي العادي إلى Excel
 * Export Final Regular Extract Invoice to Excel
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

    require_once __DIR__ . '/../../../includes/FinalExtractInvoiceExcelExporter.php';

    $db = getDB();
    
    // جلب بيانات المستخلص النهائي العادي
    $extractQuery = "
        SELECT fre.*,
               b.name as branch_name,
               b.code as branch_code
        FROM final_regular_extracts fre
        LEFT JOIN branches b ON fre.branch_id = b.id
        WHERE fre.id = ?
    ";
    
    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();
    
    if (!$extract) {
        die('المستخلص غير موجود');
    }
    
    // جلب أوامر العمل من المستخلص النهائي
    $workOrdersQuery = "
        SELECT frewo.*,
               wo.work_order_number,
               wot.type_code,
               wot.description as work_order_type_description
        FROM final_regular_extract_work_orders frewo
        LEFT JOIN work_orders wo ON frewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE frewo.final_regular_extract_id = ?
        ORDER BY wo.work_order_number ASC
    ";
    
    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$extract_id]);
    $workOrders = $stmt->fetchAll();
    
    // جلب إعدادات الفاتورة
    $settingsQuery = "SELECT * FROM invoice_settings WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->query($settingsQuery);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        die('لم يتم العثور على إعدادات الفاتورة. يرجى إعداد الفاتورة أولاً من صفحة الإعدادات.');
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
    $exporter = new FinalExtractInvoiceExcelExporter($invoiceData, $settings, $workOrders);
    $exporter->export();
    
} catch (Exception $e) {
    die('خطأ في التصدير: ' . $e->getMessage());
}

