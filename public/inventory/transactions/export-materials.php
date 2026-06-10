<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * تصدير مواد المعاملة
 * Export Transaction Materials
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryTransaction.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_reports_export')) {
    setAlert('ليس لديك صلاحية لتصدير تقارير المخزون', 'error');
    redirect('/dashboard.php');
}

$transactionId = (int)($_GET['transaction_id'] ?? 0);

if ($transactionId <= 0) {
    setAlert('معرف المعاملة غير صحيح', 'error');
    redirect('/inventory/transactions/index.php');
}

$transactionModel = new InventoryTransaction();

// الحصول على بيانات المعاملة مع التفاصيل
$transaction = $transactionModel->getTransactionWithDetails($transactionId);

if (!$transaction) {
    setAlert('المعاملة غير موجودة', 'error');
    redirect('/inventory/transactions/index.php');
}

// تحديد نوع التصدير
$exportType = $_GET['format'] ?? 'excel';

// إعداد اسم الملف
$filename = "transaction_materials_{$transaction['transaction_number']}_" . date('Y-m-d');

if ($exportType === 'excel') {
    // تصدير Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8 في Excel
    echo "\xEF\xBB\xBF";
    
    // بداية جدول HTML للتصدير
    echo '<table border="1">';
    
    // معلومات المعاملة
    echo '<tr><td colspan="6" style="font-weight: bold; text-align: center; font-size: 16px;">تفاصيل مواد المعاملة</td></tr>';
    echo '<tr><td colspan="6"></td></tr>';
    echo '<tr><td style="font-weight: bold;">رقم المعاملة:</td><td>' . htmlspecialchars($transaction['transaction_number']) . '</td><td></td><td></td><td></td><td></td></tr>';
    echo '<tr><td style="font-weight: bold;">نوع المعاملة:</td><td>' . getTransactionTypeLabel($transaction['transaction_type']) . '</td><td></td><td></td><td></td><td></td></tr>';
    echo '<tr><td style="font-weight: bold;">تاريخ المعاملة:</td><td>' . formatDate($transaction['transaction_date']) . '</td><td></td><td></td><td></td><td></td></tr>';

    echo '<tr><td style="font-weight: bold;">الحالة:</td><td>' . getTransactionStatusLabel($transaction['status']) . '</td><td></td><td></td><td></td><td></td></tr>';
    echo '<tr><td colspan="6"></td></tr>';
    
    // رأس الجدول
    echo '<tr style="background-color: #f8f9fa; font-weight: bold;">';
    echo '<td>رقم البند</td>';
    echo '<td>وصف المادة</td>';
    echo '<td>الوحدة</td>';
    echo '<td>الكمية</td>';
    echo '</tr>';
    
    // بيانات المواد
    $totalQuantity = 0;
    
    foreach ($transaction['details'] as $detail) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($detail['item_number']) . '</td>';
        echo '<td>' . htmlspecialchars($detail['description']) . '</td>';
        echo '<td>' . htmlspecialchars($detail['unit']) . '</td>';
        echo '<td>' . formatNumber($detail['quantity'], 3) . '</td>';
        echo '</tr>';
        
        $totalQuantity += $detail['quantity'];
    }
    
    // صف الإجمالي
    echo '<tr style="background-color: #e9ecef; font-weight: bold;">';
    echo '<td colspan="3">الإجمالي</td>';
    echo '<td>' . formatNumber($totalQuantity, 3) . '</td>';
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
    
    // معلومات المعاملة
    fputcsv($output, ['تفاصيل مواد المعاملة'], ',');
    fputcsv($output, [''], ',');
    fputcsv($output, ['رقم المعاملة', $transaction['transaction_number']], ',');
    fputcsv($output, ['نوع المعاملة', getTransactionTypeLabel($transaction['transaction_type'])], ',');
    fputcsv($output, ['تاريخ المعاملة', formatDate($transaction['transaction_date'])], ',');

    fputcsv($output, ['الحالة', getTransactionStatusLabel($transaction['status'])], ',');
    fputcsv($output, [''], ',');
    
    // رأس الجدول
    fputcsv($output, [
        'رقم البند',
        'وصف المادة',
        'الوحدة',
        'الكمية'
    ], ',');
    
    // بيانات المواد
    $totalQuantity = 0;
    
    foreach ($transaction['details'] as $detail) {
        fputcsv($output, [
            $detail['item_number'],
            $detail['description'],
            $detail['unit'],
            formatNumber($detail['quantity'], 3)
        ], ',');
        
        $totalQuantity += $detail['quantity'];
    }
    
    // صف الإجمالي
    fputcsv($output, [
        'الإجمالي',
        '',
        '',
        formatNumber($totalQuantity, 3)
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
