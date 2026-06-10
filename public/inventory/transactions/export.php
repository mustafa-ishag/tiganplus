<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * تصدير معاملات المخزون
 * Export Inventory Transactions
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryTransaction.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_reports_export')) {
    setAlert('ليس لديك صلاحية لتصدير تقارير المخزون', 'error');
    redirect('/dashboard.php');
}

$transactionModel = new InventoryTransaction();

// معالجة البحث والتصفية (نفس المعايير من صفحة القائمة)
$search = $_GET['search'] ?? '';
$transactionType = $_GET['transaction_type'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$materialId = $_GET['material_id'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'transaction_date';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// بناء شروط البحث
$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(it.transaction_number LIKE ? OR it.notes LIKE ?)';
    $searchPattern = "%{$search}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($transactionType)) {
    $whereConditions[] = 'it.transaction_type = ?';
    $params[] = $transactionType;
}

if (!empty($status)) {
    $whereConditions[] = 'it.status = ?';
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'it.transaction_date >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'it.transaction_date <= ?';
    $params[] = $dateTo;
}

if (!empty($materialId)) {
    $whereConditions[] = 'EXISTS (SELECT 1 FROM transaction_details itd WHERE itd.transaction_id = it.id AND itd.material_id = ?)';
    $params[] = $materialId;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// الحصول على المعاملات
$orderClause = "ORDER BY it.{$sortBy} {$sortOrder}";

$transactions = $transactionModel->fetchAll(
    "SELECT it.*, 
            COUNT(itd.id) as item_count,
            u.full_name as created_by_name
     FROM inventory_transactions it
     LEFT JOIN transaction_details itd ON it.id = itd.transaction_id
     LEFT JOIN users u ON it.created_by = u.id
     {$whereClause}
     GROUP BY it.id
     {$orderClause}",
    $params
);

// تحديد نوع التصدير
$exportType = $_GET['export'] ?? 'excel';

// إعداد اسم الملف
$filename = "inventory_transactions_" . date('Y-m-d_H-i-s');

if ($exportType === 'excel') {
    // تصدير Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8 في Excel
    echo "\xEF\xBB\xBF";
    
    // بداية جدول HTML للتصدير
    echo '<table border="1">';
    
    // عنوان التقرير
    echo '<tr><td colspan="10" style="font-weight: bold; text-align: center; font-size: 16px;">تقرير معاملات المخزون</td></tr>';
    echo '<tr><td colspan="10" style="text-align: center;">تاريخ التصدير: ' . date('Y-m-d H:i:s') . '</td></tr>';
    echo '<tr><td colspan="10"></td></tr>';
    
    // معايير التصفية
    if (!empty($search) || !empty($transactionType) || !empty($status) || !empty($dateFrom) || !empty($dateTo)) {
        echo '<tr><td colspan="10" style="font-weight: bold;">معايير التصفية:</td></tr>';
        
        if (!empty($search)) {
            echo '<tr><td style="font-weight: bold;">البحث:</td><td colspan="9">' . htmlspecialchars($search) . '</td></tr>';
        }
        if (!empty($transactionType)) {
            echo '<tr><td style="font-weight: bold;">نوع المعاملة:</td><td colspan="9">' . getTransactionTypeLabel($transactionType) . '</td></tr>';
        }
        if (!empty($status)) {
            echo '<tr><td style="font-weight: bold;">الحالة:</td><td colspan="9">' . getTransactionStatusLabel($status) . '</td></tr>';
        }
        if (!empty($dateFrom)) {
            echo '<tr><td style="font-weight: bold;">من تاريخ:</td><td colspan="9">' . $dateFrom . '</td></tr>';
        }
        if (!empty($dateTo)) {
            echo '<tr><td style="font-weight: bold;">إلى تاريخ:</td><td colspan="9">' . $dateTo . '</td></tr>';
        }
        
        echo '<tr><td colspan="10"></td></tr>';
    }
    
    // رأس الجدول
    echo '<tr style="background-color: #f8f9fa; font-weight: bold;">';
    echo '<td>رقم المعاملة</td>';
    echo '<td>النوع</td>';
    echo '<td>التاريخ</td>';

    echo '<td>عدد البنود</td>';
    echo '<td>الحالة</td>';
    echo '<td>المنشئ</td>';
    echo '<td>تاريخ الإنشاء</td>';
    echo '<td>الملاحظات</td>';
    echo '</tr>';
    
    // بيانات المعاملات
    $totalTransactions = count($transactions);
    
    foreach ($transactions as $transaction) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($transaction['transaction_number']) . '</td>';
        echo '<td>' . getTransactionTypeLabel($transaction['transaction_type']) . '</td>';
        echo '<td>' . formatDate($transaction['transaction_date']) . '</td>';
        echo '<td>' . number_format($transaction['item_count']) . '</td>';
        echo '<td>' . getTransactionStatusLabel($transaction['status']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['created_by_name'] ?? 'غير معروف') . '</td>';
        echo '<td>' . formatDateTime($transaction['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['notes']) . '</td>';
        echo '</tr>';
    }
    
    // صف الإجمالي
    echo '<tr style="background-color: #e9ecef; font-weight: bold;">';
    echo '<td colspan="3">الإجمالي (' . number_format($totalTransactions) . ' معاملة)</td>';
    echo '<td colspan="5"></td>';
    echo '</tr>';
    
    echo '</table>';
    
} else {
    // تصدير CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // عنوان التقرير
    fputcsv($output, ['تقرير معاملات المخزون'], ',');
    fputcsv($output, ['تاريخ التصدير: ' . date('Y-m-d H:i:s')], ',');
    fputcsv($output, [''], ',');
    
    // معايير التصفية
    if (!empty($search) || !empty($transactionType) || !empty($status) || !empty($dateFrom) || !empty($dateTo)) {
        fputcsv($output, ['معايير التصفية:'], ',');
        
        if (!empty($search)) {
            fputcsv($output, ['البحث', $search], ',');
        }
        if (!empty($transactionType)) {
            fputcsv($output, ['نوع المعاملة', getTransactionTypeLabel($transactionType)], ',');
        }
        if (!empty($status)) {
            fputcsv($output, ['الحالة', getTransactionStatusLabel($status)], ',');
        }
        if (!empty($dateFrom)) {
            fputcsv($output, ['من تاريخ', $dateFrom], ',');
        }
        if (!empty($dateTo)) {
            fputcsv($output, ['إلى تاريخ', $dateTo], ',');
        }
        
        fputcsv($output, [''], ',');
    }
    
    // رأس الجدول
    fputcsv($output, [
        'رقم المعاملة',
        'النوع',
        'التاريخ',

        'عدد البنود',
        'الحالة',
        'المنشئ',
        'تاريخ الإنشاء',
        'الملاحظات'
    ], ',');
    
    // بيانات المعاملات
    $totalTransactions = count($transactions);
    
    foreach ($transactions as $transaction) {
        fputcsv($output, [
            $transaction['transaction_number'],
            getTransactionTypeLabel($transaction['transaction_type']),
            formatDate($transaction['transaction_date']),
            number_format($transaction['item_count']),
            getTransactionStatusLabel($transaction['status']),
            $transaction['created_by_name'] ?? 'غير معروف',
            formatDateTime($transaction['created_at']),
            $transaction['notes']
        ], ',');
    }
    
    // صف الإجمالي
    fputcsv($output, [
        'الإجمالي (' . number_format($totalTransactions) . ' معاملة)',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    ], ',');
    
    fclose($output);
}

// دوال مساعدة
function getTransactionTypeLabel($type) {
    $labels = [
        'incoming' => 'وارد',
        'outgoing' => 'صادر',
        'transfer' => 'تحويل',
        'return' => 'مرتجع',
        'initial_balance' => 'رصيد افتتاحي',
        'loan_out' => 'سلفة صادرة',
        'loan_in' => 'سلفة واردة',
        'loan_return' => 'إرجاع سلفة'
    ];
    return $labels[$type] ?? 'غير معروف';
}

function getTransactionStatusLabel($status) {
    $labels = [
        'pending' => 'معلق',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض'
    ];
    return $labels[$status] ?? 'غير معروف';
}

exit;
?>
