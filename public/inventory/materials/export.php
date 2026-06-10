<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/**
 * تصدير بيانات المواد
 * Export Materials Data
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_materials_view')) {
    http_response_code(403);
    die('ليس لديك صلاحية لتصدير المواد');
}

$materialModel = new Material();

// معالجة البحث والتصفية (نفس المعايير من صفحة القائمة)
$search = $_GET['search'] ?? '';
$groupNumber = $_GET['group_number'] ?? '';
$status = $_GET['status'] ?? 'active';
$sortBy = $_GET['sort_by'] ?? 'description';
$sortOrder = $_GET['sort_order'] ?? 'ASC';

// بناء شروط البحث
$whereConditions = [];
$params = [];

if ($status === 'active') {
    $whereConditions[] = 'm.is_active = 1';
} elseif ($status === 'inactive') {
    $whereConditions[] = 'm.is_active = 0';
}

if (!empty($search)) {
    $whereConditions[] = '(m.item_number LIKE ? OR mc.description LIKE ?)';
    $searchPattern = "%{$search}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($groupNumber)) {
    $whereConditions[] = 'mc.group_number = ?';
    $params[] = $groupNumber;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Whitelist للترتيب
$allowedSortBy = ['m.item_number', 'mc.description', 'mc.group_number', 'mc.unit', 'm.current_stock'];
$safeSortBy = in_array($sortBy, $allowedSortBy) ? $sortBy : 'mc.description';
$safeSortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
$orderClause = "ORDER BY {$safeSortBy} {$safeSortOrder}";

// الحصول على جميع المواد
$materials = $materialModel->fetchAll(
    "SELECT m.*, mc.description, mc.group_number, mc.unit
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     {$whereClause} {$orderClause}",
    $params
);

// تحديد نوع التصدير
$exportType = $_GET['export'] ?? 'excel';

if ($exportType === 'excel') {
    // تصدير Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="materials_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // إخراج BOM لدعم UTF-8 في Excel
    echo "\xEF\xBB\xBF";
    
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #f8f9fa; font-weight: bold;">
                <th>رقم البند</th>
                <th>رقم المجموعة</th>
                <th>وصف المادة</th>
                <th>وحدة القياس</th>
                <th>المخزون الحالي</th>
                <th>الحد الأدنى</th>
                <th>الحد الأقصى</th>
                <th>الحالة</th>
                <th>تاريخ الإنشاء</th>
                <th>آخر تحديث</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($materials as $material): ?>
                <tr>
                    <td><?= htmlspecialchars($material['item_number']) ?></td>
                    <td><?= htmlspecialchars($material['group_number']) ?></td>
                    <td><?= htmlspecialchars($material['description']) ?></td>
                    <td><?= htmlspecialchars($material['unit']) ?></td>
                    <td><?= number_format($material['current_stock'], 3) ?></td>
                    <td><?= number_format($material['minimum_stock'], 3) ?></td>
                    <td><?= $material['maximum_stock'] > 0 ? number_format($material['maximum_stock'], 3) : '' ?></td>
                    <td><?= $material['is_active'] ? 'نشط' : 'غير نشط' ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($material['created_at'])) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($material['updated_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #e9ecef; font-weight: bold;">
                <td colspan="4">الإجمالي</td>
                <td><?= number_format(array_sum(array_column($materials, 'current_stock')), 3) ?></td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>
    <?php

    
} elseif ($exportType === 'csv') {
    // تصدير CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="materials_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // إخراج BOM لدعم UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // رأس الجدول
    fputcsv($output, [
        'رقم البند',
        'رقم المجموعة', 
        'وصف المادة',
        'وحدة القياس',
        'المخزون الحالي',
        'الحد الأدنى',
        'الحد الأقصى',
        'الحالة',
        'تاريخ الإنشاء',
        'آخر تحديث'
    ]);
    
    // البيانات
    foreach ($materials as $material) {
        fputcsv($output, [
            $material['item_number'],
            $material['group_number'],
            $material['description'],
            $material['unit'],
            number_format($material['current_stock'], 3),
            number_format($material['minimum_stock'], 3),
            $material['maximum_stock'] > 0 ? number_format($material['maximum_stock'], 3) : '',
            $material['is_active'] ? 'نشط' : 'غير نشط',
            date('Y-m-d H:i', strtotime($material['created_at'])),
            date('Y-m-d H:i', strtotime($material['updated_at']))
        ]);
    }
    
    fclose($output);
    
} elseif ($exportType === 'pdf') {
    // تصدير PDF (يتطلب مكتبة PDF)
    // يمكن استخدام TCPDF أو mPDF
    
    // للآن سنعرض رسالة أن PDF غير متاح
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="text-align: center; margin-top: 50px; font-family: Arial;">';
    echo '<h2>تصدير PDF</h2>';
    echo '<p>تصدير PDF غير متاح حالياً. يرجى استخدام Excel أو CSV.</p>';
    echo '<a href="index.php">العودة إلى قائمة المواد</a>';
    echo '</div>';
    
} else {
    // نوع تصدير غير مدعوم
    http_response_code(400);
    die('نوع التصدير غير مدعوم');
}

// تسجيل العملية
logActivity('export_materials', "تم تصدير قائمة المواد بصيغة {$exportType}");
?>
