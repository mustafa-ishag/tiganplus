<?php
/**
 * معالج تصدير المستخلص الجزئي كفاتورة ضريبة Excel
 * Partial Extract Invoice Export Handler
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
    header('Location: index.php');
    exit();
}

$extract_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    // التحقق من الصلاحيات
    if (!hasPermission('extracts_export')) {
        header('Location: index.php');
        exit();
    }
    require_once __DIR__ . '/../../../includes/InvoiceExcelExporter.php';
    $db = getDB();
} catch (Exception $e) {
    $_SESSION['error_message'] = 'خطأ في الاتصال: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}

try {
    // جلب تفاصيل المستخلص الجزئي
    $extractQuery = "
        SELECT pe.*,
               b.name as branch_name,
               b.code as branch_code,
               u.full_name as created_by_name
        FROM partial_extracts pe
        LEFT JOIN branches b ON pe.branch_id = b.id
        LEFT JOIN users u ON pe.created_by = u.id
        WHERE pe.id = ?
    ";

    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();

    if (!$extract) {
        throw new Exception('المستخلص غير موجود');
    }

    // جلب أوامر العمل المرتبطة بالمستخلص مع تفاصيل أنواع العمل
    $workOrdersQuery = "
        SELECT pewo.*,
               wo.work_order_number,
               wot.type_code,
               wot.description as work_order_type_name,
               wot.description as work_order_type_description
        FROM partial_extract_work_orders pewo
        LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE pewo.partial_extract_id = ?
        ORDER BY wo.work_order_number
    ";

    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$extract_id]);
    $workOrders = $stmt->fetchAll();

    if (empty($workOrders)) {
        throw new Exception('لا توجد أوامر عمل مرتبطة بهذا المستخلص');
    }

    // جلب إعدادات الفواتير
    $settingsQuery = "SELECT * FROM invoice_settings WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1";
    $stmt = $db->query($settingsQuery);
    $settings = $stmt->fetch();

    if (!$settings) {
        throw new Exception('لم يتم العثور على إعدادات الفواتير. يرجى إعداد بيانات الشركة والعميل أولاً.');
    }

    // تحضير بيانات الفاتورة
    $invoiceData = [
        'extract_number' => $extract['extract_number'],
        'invoice_number' => $extract['invoice_number'] ?: 'INV-' . $extract['extract_number'],
        'extract_date' => $extract['extract_date'],
        'branch_name' => $extract['branch_name'],
        'branch_code' => $extract['branch_code'],
        'total_amount' => $extract['total_amount'],
        'tax_amount' => $extract['tax_amount'],
        'net_amount' => $extract['net_amount'],
        'created_by_name' => $extract['created_by_name'],
        'approval_stage' => $extract['approval_stage']
    ];

    // إنشاء مصدر الفاتورة
    $exporter = new InvoiceExcelExporter($invoiceData, $settings, $workOrders);
    
    // تصدير الفاتورة
    $exporter->export();

} catch (Exception $e) {
    // في حالة الخطأ، إعادة توجيه مع رسالة خطأ
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: view.php?id=' . $extract_id);
    exit();
}