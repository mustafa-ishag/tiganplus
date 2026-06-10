<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * تصدير طلبات الصرف
 * Export Material Requests
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';
require_once __DIR__ . '/../../../models/WorkOrder.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_requests_export')) {
    setAlert('ليس لديك صلاحية لتصدير طلبات الصرف', 'error');
    redirect('../../dashboard.php');
}

$materialRequestModel = new MaterialRequest();
$workOrderModel = new WorkOrder();

// معالجة البحث والتصفية (نفس المعايير من صفحة القائمة)
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$workOrderId = $_GET['work_order_id'] ?? '';
$requestedBy = $_GET['requested_by'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// بناء شروط البحث
$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(mr.request_number LIKE ? OR mr.notes LIKE ? OR wo.work_order_number LIKE ?)';
    $searchPattern = "%{$search}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($status)) {
    $whereConditions[] = 'mr.status = ?';
    $params[] = $status;
}

if (!empty($workOrderId)) {
    $whereConditions[] = 'mr.work_order_id = ?';
    $params[] = $workOrderId;
}

if (!empty($requestedBy)) {
    $whereConditions[] = 'mr.requested_by = ?';
    $params[] = $requestedBy;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'mr.request_date >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'mr.request_date <= ?';
    $params[] = $dateTo;
}

// تصفية حسب الفرع للمستخدمين المحدودين
if (isset($_SESSION['user_branch_id']) && $_SESSION['user_branch_id']) {
    $whereConditions[] = 'wo.branch_id = ?';
    $params[] = $_SESSION['user_branch_id'];
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// الحصول على طلبات الصرف
$orderClause = "ORDER BY mr.{$sortBy} {$sortOrder}";

$materialRequests = $materialRequestModel->fetchAll(
    "SELECT mr.*,
            COUNT(mrd.id) as item_count,
            wo.work_order_number, wo.estimated_value as work_order_value,
            wot.type_code as work_order_type_code,
            b.name as branch_name, b.code as branch_code,
            u1.full_name as requested_by_name,
            u2.full_name as warehouse_approved_by_name,
            u3.full_name as project_approved_by_name
     FROM material_requests mr
     LEFT JOIN material_request_details mrd ON mr.id = mrd.request_id
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
     LEFT JOIN branches b ON wo.branch_id = b.id
     LEFT JOIN users u1 ON mr.requested_by = u1.id
     LEFT JOIN users u2 ON mr.warehouse_approved_by = u2.id
     LEFT JOIN users u3 ON mr.project_approved_by = u3.id
     {$whereClause}
     GROUP BY mr.id
     {$orderClause}",
    $params
);

// تحديد نوع التصدير
$format = $_GET['export'] ?? 'excel';

if ($format === 'excel') {
    // تصدير Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="material_requests_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8
    echo "\xEF\xBB\xBF";
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th colspan="12" style="text-align: center; font-size: 16px; font-weight: bold;">تقرير طلبات الصرف</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th colspan="12" style="text-align: center;">تاريخ التقرير: ' . formatDate(date('Y-m-d')) . '</th>';
    echo '</tr>';
    echo '<tr><td colspan="12"></td></tr>';
    
    // رأس الجدول
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<th>رقم الطلب</th>';
    echo '<th>أمر العمل</th>';
    echo '<th>نوع أمر العمل</th>';
    echo '<th>الفرع</th>';
    echo '<th>تاريخ الطلب</th>';
    echo '<th>تاريخ الحاجة</th>';
    echo '<th>عدد البنود</th>';
    echo '<th>الحالة</th>';
    echo '<th>مقدم الطلب</th>';
    echo '<th>تاريخ الإنشاء</th>';
    echo '<th>ملاحظات</th>';
    echo '</tr>';
    
    // البيانات
    $totalRequests = count($materialRequests);
    
    foreach ($materialRequests as $request) {
        
        $statusLabels = [
            'draft' => 'مسودة',
            'submitted' => 'مرسل',
            'warehouse_approved' => 'موافقة المستودع',
            'approved' => 'معتمد نهائياً',
            'project_approved' => 'معتمد نهائياً',
            'branch_approved' => 'معتمد نهائياً',
            'rejected' => 'مرفوض'
        ];
        $statusText = $statusLabels[$request['status']] ?? 'غير معروف';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($request['request_number']) . '</td>';
        echo '<td>' . htmlspecialchars($request['work_order_number']) . '</td>';
        echo '<td>' . htmlspecialchars($request['work_order_type_code']) . '</td>';
        echo '<td>' . htmlspecialchars($request['branch_code']) . '</td>';
        echo '<td>' . formatDate($request['request_date']) . '</td>';
        echo '<td>' . formatDate($request['required_date']) . '</td>';
        echo '<td>' . number_format($request['item_count']) . '</td>';
        echo '<td>' . $statusText . '</td>';
        echo '<td>' . htmlspecialchars($request['requested_by_name']) . '</td>';
        echo '<td>' . formatDateTime($request['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($request['notes']) . '</td>';
        echo '</tr>';
    }
    
    // الإجمالي
    echo '<tr style="background-color: #f0f0f0; font-weight: bold;">';
    echo '<td colspan="6">الإجمالي</td>';
    echo '<td>' . number_format($totalRequests) . '</td>';
    echo '<td colspan="4">-</td>';
    echo '</tr>';
    
    echo '</table>';
    
} elseif ($format === 'csv') {
    // تصدير CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="material_requests_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    
    // إضافة BOM لدعم UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // معلومات التقرير
    fputcsv($output, ['تقرير طلبات الصرف'], ';');
    fputcsv($output, ['تاريخ التقرير: ' . formatDate(date('Y-m-d'))], ';');
    fputcsv($output, [''], ';');
    
    // رأس الجدول
    fputcsv($output, [
        'رقم الطلب',
        'أمر العمل',
        'نوع أمر العمل',
        'الفرع',
        'تاريخ الطلب',
        'تاريخ الحاجة',
        'عدد البنود',
        'الحالة',
        'مقدم الطلب',
        'تاريخ الإنشاء',
        'ملاحظات'
    ], ';');
    
    // البيانات
    $totalRequests = count($materialRequests);
    
    foreach ($materialRequests as $request) {
        
        $statusLabels = [
            'draft' => 'مسودة',
            'submitted' => 'مرسل',
            'warehouse_approved' => 'موافقة المستودع',
            'approved' => 'معتمد نهائياً',
            'project_approved' => 'معتمد نهائياً',
            'branch_approved' => 'معتمد نهائياً',
            'rejected' => 'مرفوض'
        ];
        $statusText = $statusLabels[$request['status']] ?? 'غير معروف';
        
        fputcsv($output, [
            $request['request_number'],
            $request['work_order_number'],
            $request['work_order_type_code'],
            $request['branch_code'],
            formatDate($request['request_date']),
            formatDate($request['required_date']),
            number_format($request['item_count']),
            $statusText,
            $request['requested_by_name'],
            formatDateTime($request['created_at']),
            $request['notes']
        ], ';');
    }
    
    // الإجمالي
    fputcsv($output, [
        'الإجمالي',
        '',
        '',
        '',
        '',
        '',
        number_format($totalRequests),
        '',
        '',
        '',
        ''
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
        <title>تقرير طلبات الصرف</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                margin: 20px;
                direction: rtl;
                font-size: 12px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            .requests-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 10px;
            }
            .requests-table th,
            .requests-table td {
                border: 1px solid #333;
                padding: 4px;
                text-align: center;
            }
            .requests-table th {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            .total-row {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            @media print {
                body { margin: 0; font-size: 10px; }
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
            <h1>تقرير طلبات الصرف</h1>
            <h3>تاريخ التقرير: <?= formatDate(date('Y-m-d')) ?></h3>
        </div>
        
        <table class="requests-table">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>أمر العمل</th>
                    <th>نوع أمر العمل</th>
                    <th>الفرع</th>
                    <th>تاريخ الطلب</th>
                    <th>تاريخ الحاجة</th>
                    <th>عدد البنود</th>
                    <th>الحالة</th>
                    <th>مقدم الطلب</th>
                </tr>
            </thead>
            <tbody>
                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


                $totalRequests = count($materialRequests);
                
                foreach ($materialRequests as $request):
                    
                    $statusLabels = [
                        'draft' => 'مسودة',
                        'submitted' => 'مرسل',
                        'warehouse_approved' => 'موافقة المستودع',
                        'approved' => 'معتمد نهائياً',
                        'project_approved' => 'معتمد نهائياً',
                        'branch_approved' => 'معتمد نهائياً',
                        'rejected' => 'مرفوض'
                    ];
                    $statusText = $statusLabels[$request['status']] ?? 'غير معروف';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($request['request_number']) ?></td>
                        <td><?= htmlspecialchars($request['work_order_number']) ?></td>
                        <td><?= htmlspecialchars($request['work_order_type_code']) ?></td>
                        <td><?= htmlspecialchars($request['branch_code']) ?></td>
                        <td><?= formatDate($request['request_date']) ?></td>
                        <td><?= formatDate($request['required_date']) ?></td>
                        <td><?= number_format($request['item_count']) ?></td>
                        <td><?= $statusText ?></td>
                        <td><?= htmlspecialchars($request['requested_by_name']) ?></td>
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
                    <td colspan="6">الإجمالي</td>
                    <td><?= number_format($totalRequests) ?></td>
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
