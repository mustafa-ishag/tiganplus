<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * تصدير مواد طلب الصرف
 * Export Material Request Materials
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_requests_export')) {
    setAlert('ليس لديك صلاحية لتصدير طلبات الصرف', 'error');
    redirect('../../dashboard.php');
}

$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    setAlert('معرف طلب الصرف غير صحيح', 'error');
    redirect('index.php');
}

$materialRequestModel = new MaterialRequest();

// الحصول على تفاصيل طلب الصرف
$request = $materialRequestModel->fetchOne(
    "SELECT mr.*, wo.work_order_number, b.name as branch_name
     FROM material_requests mr
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     LEFT JOIN branches b ON wo.branch_id = b.id
     WHERE mr.id = ?",
    [$requestId]
);

if (!$request) {
    setAlert('طلب الصرف غير موجود', 'error');
    redirect('/inventory/material-requests/index.php');
}

// الحصول على تفاصيل المواد
$requestDetails = $materialRequestModel->fetchAll(
    "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
     FROM material_request_details mrd
     JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE mrd.request_id = ?
     ORDER BY m.item_number",
    [$requestId]
);

// تحديد نوع التصدير
$format = $_GET['format'] ?? 'excel';

if ($format === 'excel') {
    // تصدير Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="material_request_' . $request['request_number'] . '_materials.xls"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8
    echo "\xEF\xBB\xBF";
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th colspan="6" style="text-align: center; font-size: 16px; font-weight: bold;">مواد طلب الصرف رقم: ' . htmlspecialchars($request['request_number']) . '</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th colspan="6" style="text-align: center;">أمر العمل: ' . htmlspecialchars($request['work_order_number']) . ' | الفرع: ' . htmlspecialchars($request['branch_name']) . '</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th colspan="6" style="text-align: center;">تاريخ الطلب: ' . formatDate($request['request_date']) . ' | تاريخ الحاجة: ' . formatDate($request['required_date']) . '</th>';
    echo '</tr>';
    echo '<tr><td colspan="6"></td></tr>';
    
    // رأس الجدول
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<th>رقم البند</th>';
    echo '<th>الوصف</th>';
    echo '<th>الكمية المطلوبة</th>';
    echo '<th>الوحدة</th>';
    echo '<th>المخزون الحالي</th>';
    echo '<th>الحالة</th>';
    echo '</tr>';
    
    // البيانات
    $totalQuantity = 0;

    foreach ($requestDetails as $detail) {
        $totalQuantity += $detail['requested_quantity'];

        $status = '';
        if ($detail['current_stock'] >= $detail['requested_quantity']) {
            $status = 'متوفر';
        } elseif ($detail['current_stock'] > 0) {
            $status = 'متوفر جزئياً';
        } else {
            $status = 'غير متوفر';
        }

        echo '<tr>';
        echo '<td>' . htmlspecialchars($detail['item_number']) . '</td>';
        echo '<td>' . htmlspecialchars($detail['description']) . '</td>';
        echo '<td>' . number_format($detail['requested_quantity'], 3) . '</td>';
        echo '<td>' . htmlspecialchars($detail['unit']) . '</td>';
        echo '<td>' . number_format($detail['current_stock'], 3) . '</td>';
        echo '<td>' . $status . '</td>';
        echo '</tr>';
    }
    
    // الإجمالي
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<td colspan="2">الإجمالي</td>';
    echo '<td>' . number_format($totalQuantity, 3) . '</td>';
    echo '<td colspan="3">-</td>';
    echo '</tr>';
    
    echo '</table>';
    
} elseif ($format === 'csv') {
    // تصدير CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="material_request_' . $request['request_number'] . '_materials.csv"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // معلومات الطلب
    fputcsv($output, ['مواد طلب الصرف رقم: ' . $request['request_number']], ';');
    fputcsv($output, ['أمر العمل: ' . $request['work_order_number'] . ' | الفرع: ' . $request['branch_name']], ';');
    fputcsv($output, ['تاريخ الطلب: ' . formatDate($request['request_date']) . ' | تاريخ الحاجة: ' . formatDate($request['required_date'])], ';');
    fputcsv($output, [''], ';');
    
    // رأس الجدول
    fputcsv($output, [
        'رقم البند',
        'الوصف',
        'الكمية المطلوبة',
        'الوحدة',
        'المخزون الحالي',
        'الحالة'
    ], ';');
    
    // البيانات
    $totalQuantity = 0;

    foreach ($requestDetails as $detail) {
        $totalQuantity += $detail['requested_quantity'];

        $status = '';
        if ($detail['current_stock'] >= $detail['requested_quantity']) {
            $status = 'متوفر';
        } elseif ($detail['current_stock'] > 0) {
            $status = 'متوفر جزئياً';
        } else {
            $status = 'غير متوفر';
        }

        fputcsv($output, [
            $detail['item_number'],
            $detail['description'],
            number_format($detail['requested_quantity'], 3),
            $detail['unit'],
            number_format($detail['current_stock'], 3),
            $status
        ], ';');
    }

    // الإجمالي
    fputcsv($output, [
        'الإجمالي',
        '',
        number_format($totalQuantity, 3),
        '-',
        '-',
        '-'
    ], ';');
    
    fclose($output);
    
} else {
    // تصدير PDF (مبسط)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>مواد طلب الصرف - <?= htmlspecialchars($request['request_number']) ?></title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 20px;
                direction: rtl;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            .info-table {
                width: 100%;
                margin-bottom: 20px;
            }
            .info-table td {
                padding: 5px;
                border: 1px solid #ddd;
            }
            .materials-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .materials-table th,
            .materials-table td {
                border: 1px solid #333;
                padding: 8px;
                text-align: center;
            }
            .materials-table th {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            .total-row {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()">طباعة</button>
            <button onclick="window.close()">إغلاق</button>
        </div>
        
        <div class="header">
            <h1>مواد طلب الصرف</h1>
            <h2>رقم الطلب: <?= htmlspecialchars($request['request_number']) ?></h2>
        </div>
        
        <table class="info-table">
            <tr>
                <td><strong>أمر العمل:</strong></td>
                <td><?= htmlspecialchars($request['work_order_number']) ?></td>
                <td><strong>الفرع:</strong></td>
                <td><?= htmlspecialchars($request['branch_name']) ?></td>
            </tr>
            <tr>
                <td><strong>تاريخ الطلب:</strong></td>
                <td><?= formatDate($request['request_date']) ?></td>
                <td><strong>تاريخ الحاجة:</strong></td>
                <td><?= formatDate($request['required_date']) ?></td>
            </tr>
        </table>
        
        <table class="materials-table">
            <thead>
                <tr>
                    <th>رقم البند</th>
                    <th>الوصف</th>
                    <th>الكمية المطلوبة</th>
                    <th>الوحدة</th>
                    <th>المخزون الحالي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


                $totalQuantity = 0;

                foreach ($requestDetails as $detail):
                    $totalQuantity += $detail['requested_quantity'];

                    $status = '';
                    if ($detail['current_stock'] >= $detail['requested_quantity']) {
                        $status = 'متوفر';
                    } elseif ($detail['current_stock'] > 0) {
                        $status = 'متوفر جزئياً';
                    } else {
                        $status = 'غير متوفر';
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($detail['item_number']) ?></td>
                        <td><?= htmlspecialchars($detail['description']) ?></td>
                        <td><?= number_format($detail['requested_quantity'], 3) ?></td>
                        <td><?= htmlspecialchars($detail['unit']) ?></td>
                        <td><?= number_format($detail['current_stock'], 3) ?></td>
                        <td><?= $status ?></td>
                    </tr>
                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2">الإجمالي</td>
                    <td><?= number_format($totalQuantity, 3) ?></td>
                    <td colspan="3">-</td>
                </tr>
            </tfoot>
        </table>
        
        <div style="margin-top: 50px; text-align: center; color: #666;">
            <p>تم إنشاء هذا التقرير في: <?= formatDateTime(date('Y-m-d H:i:s')) ?></p>
        </div>
    </body>
    </html>
    <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


}
exit;
?>
