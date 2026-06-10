<?php
/**
 * تصدير نموذج Excel فارغ للمستخلصات النهائية للجزئية
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

try {
    // إنشاء ملف Excel جديد
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // تعيين اتجاه الصفحة من اليمين لليسار
    $sheet->setRightToLeft(true);
    
    // تعيين عنوان الورقة
    $sheet->setTitle('نموذج المستخلصات النهائية');

    // إضافة عنوان التقرير في الصف 1
    $sheet->mergeCells('A1:I1');
    $sheet->setCellValue('A1', 'نموذج استيراد المستخلصات النهائية للجزئية');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2C5AA0']
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // معلومات في الصف 2
    $sheet->mergeCells('A2:I2');
    $sheet->setCellValue('A2', '⭐ المبالغ تُحسب تلقائياً - فقط أدخل قيمة المستخلص والغرامة');
    $sheet->getStyle('A2')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 11,
            'color' => ['rgb' => 'FF6B6B']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ]);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // الصف 3 فارغ

    // تعريف الأعمدة (9 أعمدة فقط - المبالغ تُحسب تلقائياً)
    $headers = [
        'A' => 'رقم المستخلص',
        'B' => 'الفرع',
        'C' => 'تاريخ المستخلص',
        'D' => 'المستخلص الجزئي المرتبط',
        'E' => 'مرحلة الاعتماد',
        'F' => 'رقم أمر العمل',
        'G' => 'نوع أمر العمل',
        'H' => 'قيمة المستخلص',
        'I' => 'الغرامة'
    ];

    // كتابة العناوين في الصف 4
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($col . '4', $header);
    }

    // تنسيق صف العناوين
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4CAF50']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ];

    $sheet->getStyle('A4:I4')->applyFromArray($headerStyle);

    // تعيين ارتفاع صف العناوين
    $sheet->getRowDimension(4)->setRowHeight(25);

    // تعيين عرض الأعمدة
    $columnWidths = [
        'A' => 20,  // رقم المستخلص
        'B' => 25,  // الفرع
        'C' => 15,  // تاريخ المستخلص
        'D' => 25,  // المستخلص الجزئي المرتبط
        'E' => 20,  // مرحلة الاعتماد
        'F' => 20,  // رقم أمر العمل
        'G' => 15,  // نوع أمر العمل
        'H' => 18,  // قيمة المستخلص
        'I' => 15   // الغرامة
    ];
    
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
    
    // إضافة صف مثال في الصف 5
    $sheet->setCellValue('A5', 'مثال: FFPE-2025-001');
    $sheet->setCellValue('B5', 'مثال: الفرع الرئيسي');
    $sheet->setCellValue('C5', '2025-01-15');
    $sheet->setCellValue('D5', 'مثال: PE-2025-001');
    $sheet->setCellValue('E5', 'draft');
    $sheet->setCellValue('F5', 'مثال: 123');
    $sheet->setCellValue('G5', 'CON');
    $sheet->setCellValue('H5', '50000');
    $sheet->setCellValue('I5', '2500');

    // تنسيق صف المثال
    $exampleStyle = [
        'font' => [
            'italic' => true,
            'color' => ['rgb' => '808080'],
            'size' => 10
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ];

    $sheet->getStyle('A5:I5')->applyFromArray($exampleStyle);
    
    // إضافة ورقة تعليمات منفصلة
    $instructionsSheet = $spreadsheet->createSheet();
    $instructionsSheet->setTitle('التعليمات');
    $instructionsSheet->setRightToLeft(true);
    
    // كتابة التعليمات
    $instructions = [
        ['تعليمات استيراد المستخلصات النهائية للجزئية'],
        [''],
        ['⭐ مهم جداً: المبالغ تُحسب تلقائياً!'],
        ['   - لا تُدخل: المبلغ الإجمالي، الضريبة، إجمالي الغرامات، المبلغ الصافي'],
        ['   - فقط أدخل: قيمة المستخلص والغرامة لكل أمر عمل'],
        ['   - النظام سيحسب باقي المبالغ تلقائياً'],
        [''],
        ['1. الأعمدة المطلوبة:'],
        ['   - رقم المستخلص: رقم فريد للمستخلص (مثال: FFPE-2025-001)'],
        ['   - الفرع: اسم الفرع كما هو موجود في النظام'],
        ['   - تاريخ المستخلص: بصيغة YYYY-MM-DD أو DD-MM-YYYY'],
        ['   - المستخلص الجزئي المرتبط: رقم المستخلص الجزئي (إلزامي)'],
        ['   - مرحلة الاعتماد: draft, technical_support, construction, إلخ'],
        ['   - رقم أمر العمل: رقم أمر العمل'],
        ['   - نوع أمر العمل: كود النوع (CON, TO3, TR7, إلخ)'],
        ['   - قيمة المستخلص: قيمة المستخلص لهذا الأمر'],
        ['   - الغرامة: قيمة الغرامة لهذا الأمر'],
        [''],
        ['2. كيفية حساب المبالغ:'],
        ['   ✅ المبلغ الإجمالي = مجموع قيم المستخلصات لجميع أوامر العمل'],
        ['   ✅ الضريبة = المبلغ الإجمالي × 15%'],
        ['   ✅ إجمالي الغرامات = مجموع الغرامات لجميع أوامر العمل'],
        ['   ✅ الصافي = المبلغ الإجمالي + الضريبة - إجمالي الغرامات'],
        ['   ⚠️ جميع هذه المبالغ تُحسب تلقائياً - لا تُدخلها!'],
        [''],
        ['3. ملاحظات هامة:'],
        ['   - المستخلص الجزئي المرتبط يجب أن يكون موجوداً في النظام'],
        ['   - لا حاجة لإدخال تاريخ الإنجاز - سيتم استخدامه من المستخلص الجزئي'],
        ['   - أمر العمل يجب أن يكون موجوداً في المستخلص الجزئي المرتبط'],
        ['   - نوع أمر العمل يجب أن يكون الكود وليس الوصف'],
        [''],
        ['4. مراحل الاعتماد المتاحة:'],
        ['   - draft: مسودة'],
        ['   - technical_support: الدعم الفني'],
        ['   - construction: الإنشاءات'],
        ['   - department_manager: مدير القسم'],
        ['   - administration_manager: مدير الإدارة'],
        ['   - taif_finance: مالية الطائف'],
        ['   - disbursed: مصروفة'],
        [''],
        ['5. أنواع أوامر العمل (الأكواد):'],
        ['   - CON: عقد'],
        ['   - TO3: أمر توريد 3'],
        ['   - TR7: أمر توريد 7'],
        ['   - وغيرها حسب النظام'],
        [''],
        ['6. صيغ التواريخ المدعومة:'],
        ['   - 2025-01-15 (ISO)'],
        ['   - 15-01-2025 (عربي/أوروبي)'],
        ['   - 01-15-2025 (أمريكي)'],
        ['   - تواريخ Excel الرقمية'],
        [''],
        ['7. مثال على البيانات (ما تُدخله أنت):'],
        ['   رقم المستخلص: FFPE-2025-001'],
        ['   الفرع: الفرع الرئيسي'],
        ['   تاريخ المستخلص: 2025-01-15'],
        ['   المستخلص الجزئي المرتبط: PE-2025-001'],
        ['   مرحلة الاعتماد: draft'],
        ['   رقم أمر العمل: 123'],
        ['   نوع أمر العمل: CON'],
        ['   قيمة المستخلص: 50000'],
        ['   الغرامة: 2500'],
        [''],
        ['   ⬇️ النظام سيحسب تلقائياً:'],
        ['   المبلغ الإجمالي: 50000 (مجموع قيم المستخلصات)'],
        ['   الضريبة: 7500 (15% من 50000)'],
        ['   إجمالي الغرامات: 2500 (مجموع الغرامات)'],
        ['   المبلغ الصافي: 55000 (50000 + 7500 - 2500)'],
        [''],
        ['8. التكرارات:'],
        ['   - إذا كان المستخلص موجوداً، سيتم تحديث بياناته'],
        ['   - سيتم حذف أوامر العمل القديمة واستبدالها بالجديدة'],
        [''],
        ['8. الأخطاء الشائعة:'],
        ['   - المستخلص الجزئي غير موجود'],
        ['   - أمر العمل غير موجود في المستخلص الجزئي'],
        ['   - الفرع غير موجود في النظام'],
        ['   - نوع أمر العمل غير صحيح (استخدم الكود وليس الوصف)'],
        ['   - خطأ في حساب الصافي'],
    ];
    
    $row = 1;
    foreach ($instructions as $instruction) {
        $instructionsSheet->setCellValue('A' . $row, $instruction[0]);
        $row++;
    }
    
    // تنسيق ورقة التعليمات
    $instructionsSheet->getColumnDimension('A')->setWidth(80);
    $instructionsSheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 14,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4472C4']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ]);
    
    // تنسيق العناوين الرئيسية
    foreach ([3, 18, 23, 30, 36, 50, 58, 60] as $titleRow) {
        $instructionsSheet->getStyle('A' . $titleRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '4472C4']
            ]
        ]);
    }
    
    // العودة للورقة الأولى
    $spreadsheet->setActiveSheetIndex(0);
    
    // إعداد الملف للتحميل
    $fileName = 'نموذج_المستخلصات_النهائية_للجزئية_' . date('Y-m-d') . '.xlsx';
    
    // تعيين headers للتحميل
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    
    // كتابة الملف
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    exit();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'حدث خطأ أثناء إنشاء النموذج: ' . $e->getMessage();
    header('Location: import.php');
    exit();
}

