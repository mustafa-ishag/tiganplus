<?php

declare(strict_types=1);

/**
 * تحميل ملف CSV النموذجي
 * Download Sample CSV File
 */

// إعداد العناوين للتحميل
$filename = 'sample_work_order_types_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// إضافة BOM للدعم العربي في Excel
echo "\xEF\xBB\xBF";

// فتح output stream
$output = fopen('php://output', 'w');

// كتابة العناوين
$headers = [
    'كود النوع',
    'الوصف',
    'الحالة'
];

fputcsv($output, $headers);

// كتابة البيانات النموذجية
$sampleData = [
    ['MNT', 'صيانة دورية', 'نشط'],
    ['EMR', 'إصلاح طارئ', 'نشط'],
    ['DEV', 'تطوير وتحسين', 'نشط'],
    ['CON', 'أعمال إنشائية', 'نشط'],
    ['ELE', 'أعمال كهربائية', 'نشط'],
    ['PLB', 'أعمال صحية', 'نشط'],
    ['HVAC', 'تكييف وتهوية', 'نشط'],
    ['CLN', 'أعمال نظافة', 'غير نشط'],
    ['TEST', 'نوع تجريبي', 'نشط']
];

foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
