<?php
/**
 * تصدير نموذج استيراد المستخلصات النهائية العادية
 * Export Final Regular Extract Import Template
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_export')) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // تعيين اتجاه الصفحة من اليمين لليسار
    $sheet->setRightToLeft(true);
    
    // عنوان الملف
    $sheet->setCellValue('A1', 'نموذج استيراد المستخلصات النهائية العادية');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // التعليمات
    $sheet->setCellValue('A2', 'تعليمات: قم بملء البيانات بدءاً من الصف 5. الحقول المطلوبة: رقم المستخلص، تاريخ المستخلص، رقم أمر العمل، نوع أمر العمل، تاريخ الإنجاز، قيمة المستخلص');
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
    $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');

    // صف فارغ
    $sheet->setCellValue('A3', '');

    // العناوين (الصف 4)
    $headers = [
        'A4' => 'رقم المستخلص',
        'B4' => 'الفرع',
        'C4' => 'القسم',
        'D4' => 'تاريخ المستخلص',
        'E4' => 'مرحلة الاعتماد',
        'F4' => 'رقم أمر العمل',
        'G4' => 'نوع أمر العمل',
        'H4' => 'تاريخ الإنجاز',
        'I4' => 'قيمة المستخلص',
        'J4' => 'الغرامة'
    ];
    
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // تنسيق العناوين
    $sheet->getStyle('A4:J4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    // أمثلة (الصف 5)
    $sheet->setCellValue('A5', 'FRE-2025-001');
    $sheet->setCellValue('B5', ''); // سيتم جلبه تلقائياً
    $sheet->setCellValue('C5', ''); // سيتم جلبه تلقائياً
    $sheet->setCellValue('D5', '2025-01-15');
    $sheet->setCellValue('E5', 'الدعم الفني');
    $sheet->setCellValue('F5', '242033090');
    $sheet->setCellValue('G5', '802');
    $sheet->setCellValue('H5', '2025-01-10');
    $sheet->setCellValue('I5', '10000.00');
    $sheet->setCellValue('J5', '500.00');

    // تنسيق صف المثال
    $sheet->getStyle('A5:J5')->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E7E6E6']
        ],
        'font' => ['italic' => true],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);
    
    // إضافة صفوف فارغة للإدخال (من 6 إلى 100)
    for ($row = 6; $row <= 100; $row++) {
        $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD']
                ]
            ]
        ]);
    }

    // ضبط عرض الأعمدة
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(18);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(18);
    $sheet->getColumnDimension('I')->setWidth(18);
    $sheet->getColumnDimension('J')->setWidth(15);
    
    // إضافة ملاحظات في ورقة منفصلة
    $notesSheet = $spreadsheet->createSheet();
    $notesSheet->setTitle('ملاحظات');
    $notesSheet->setRightToLeft(true);
    
    $notesSheet->setCellValue('A1', 'ملاحظات مهمة');
    $notesSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    $notes = [
        'A3' => '1. الحقول المطلوبة:',
        'A4' => '   - رقم المستخلص (مثال: FRE-2025-001)',
        'A5' => '   - تاريخ المستخلص (بصيغة: YYYY-MM-DD)',
        'A6' => '   - رقم أمر العمل',
        'A7' => '   - نوع أمر العمل (رمز النوع)',
        'A8' => '   - تاريخ الإنجاز (بصيغة: YYYY-MM-DD)',
        'A9' => '   - قيمة المستخلص (رقم موجب)',
        'A10' => '',
        'A11' => '2. الحقول الاختيارية (سيتم جلبها تلقائياً من أمر العمل):',
        'A12' => '   - الفرع',
        'A13' => '   - القسم',
        'A14' => '',
        'A15' => '3. الغرامة:',
        'A16' => '   - اختيارية (افتراضياً = 0)',
        'A17' => '   - يجب أن تكون رقم موجب أو صفر',
        'A18' => '',
        'A19' => '4. مرحلة الاعتماد:',
        'A20' => '   - الدعم الفني (افتراضي)',
        'A21' => '   - الإنشاءات',
        'A22' => '   - مدير القسم',
        'A23' => '   - مدير الإدارة',
        'A24' => '   - مالية الطائف',
        'A25' => '   - تم الصرف',
        'A26' => '',
        'A27' => '5. الحسابات التلقائية:',
        'A28' => '   - المبلغ الإجمالي = مجموع قيم أوامر العمل',
        'A29' => '   - الضريبة = المبلغ الإجمالي × 15%',
        'A30' => '   - إجمالي الغرامات = مجموع الغرامات',
        'A31' => '   - الصافي = المبلغ الإجمالي + الضريبة - الغرامات',
    ];
    
    foreach ($notes as $cell => $value) {
        $notesSheet->setCellValue($cell, $value);
    }
    
    $notesSheet->getColumnDimension('A')->setWidth(80);
    
    // العودة للورقة الأولى
    $spreadsheet->setActiveSheetIndex(0);
    
    // حفظ الملف
    $fileName = 'نموذج_استيراد_المستخلصات_النهائية_العادية.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
    
} catch (Exception $e) {
    die('خطأ في إنشاء النموذج: ' . $e->getMessage());
}

