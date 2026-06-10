<?php
/**
 * تحميل ملف نموذجي لاستيراد المواد
 * Download Template for Materials Import
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// إنشاء ملف CSV نموذجي
$filename = 'materials_import_template_' . date('Y-m-d') . '.csv';

// تحديد headers للتحميل
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// إضافة BOM للدعم العربي في Excel
echo "\xEF\xBB\xBF";

// إنشاء ملف CSV
$output = fopen('php://output', 'w');

// العناوين
$headers = [
    'رقم البند',
    'رقم المجموعة',
    'الوصف',
    'الوحدة',
    'المخزون الحالي',
    'الرصيد الافتتاحي',
    'الحد الأدنى',
    'الحد الأقصى',
    'الحالة'
];

fputcsv($output, $headers);

// بيانات نموذجية
$sampleData = [
    ['CABLE-001', '1000000000', 'كابل كهربائي 4×16 مم²', 'متر', '1000.000', '1000.000', '100.000', '5000.000', 'نشط'],
    ['CABLE-002', '1000000000', 'كابل كهربائي 4×25 مم²', 'متر', '800.000', '800.000', '100.000', '3000.000', 'نشط'],
    ['POLE-001', '2000000000', 'عمود إنارة معدني 8 متر', 'قطعة', '50.000', '50.000', '10.000', '200.000', 'نشط'],
    ['TRANS-001', '3000000000', 'محول توزيع 100 كيلو فولت أمبير', 'قطعة', '5.000', '5.000', '2.000', '20.000', 'نشط'],
    ['PANEL-001', '4000000000', 'لوحة توزيع رئيسية 400 أمبير', 'قطعة', '10.000', '10.000', '3.000', '50.000', 'نشط'],
];

// كتابة البيانات النموذجية
foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
