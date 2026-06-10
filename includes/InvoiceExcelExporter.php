<?php
/**
 * مكتبة تصدير الفواتير الضريبية إلى Excel - تصميم احترافي محسن
 * Professional Invoice Excel Exporter Library using PhpSpreadsheet
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InvoiceExcelExporter {
    private $invoiceData;
    private $settings;
    private $workOrders;
    private $spreadsheet;
    private $sheet;
    private $currentRow = 1;

    // الألوان
    private $headerColor = '2C5AA0';
    private $accentColor = '4CAF50';
    private $lightGray = 'F8F9FA';
    private $darkGray = '6C757D';

    public function __construct($invoiceData, $settings, $workOrders) {
        $this->invoiceData = $invoiceData;
        $this->settings = $settings;
        $this->workOrders = $workOrders;

        // تحويل الألوان من hex إلى RGB
        $this->headerColor = str_replace('#', '', $this->settings['header_color']);
        $this->accentColor = str_replace('#', '', $this->settings['accent_color']);
    }

    /**
     * تصدير الفاتورة
     */
    public function export() {
        // تنظيف أي output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // إنشاء ملف Excel جديد
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();

        // إعداد الصفحة
        $this->setupPage();

        // إنشاء محتوى الفاتورة
        $this->generateHeader();
        $this->generateCompanyInfo();
        $this->generateInvoiceDetails();
        $this->generateWorkOrdersTable();
        $this->generateSummary();
        $this->generateFooter();

        // ضبط عرض الأعمدة
        $this->autoSizeColumns();

        // تصدير الملف
        $filename = 'فاتورة_ضريبة_' . $this->invoiceData['extract_number'] . '_' . date('Y-m-d') . '.xlsx';

        // إرسال headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        // حفظ الملف
        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * إعداد الصفحة
     */
    private function setupPage() {
        // اسم الورقة
        $this->sheet->setTitle('فاتورة ضريبة');

        // اتجاه RTL
        $this->sheet->setRightToLeft(true);

        // إعدادات الطباعة
        $this->sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        // الهوامش - صغيرة جداً
        $this->sheet->getPageMargins()
            ->setTop(0.2)      // 0.2 بوصة من الأعلى
            ->setRight(0.2)    // 0.2 بوصة من اليمين
            ->setLeft(0.2)     // 0.2 بوصة من اليسار
            ->setBottom(0.2)   // 0.2 بوصة من الأسفل
            ->setHeader(0.1)   // هامش الرأس
            ->setFooter(0.1);  // هامش التذييل
    }

    /**
     * إنشاء رأس الفاتورة
     */
    private function generateHeader() {
        // دمج الخلايا للرأس
        $this->sheet->mergeCells('A' . $this->currentRow . ':J' . ($this->currentRow + 2));

        // عنوان الفاتورة - فقط العنوان بدون رقم المستخلص
        $title = $this->settings['invoice_title'];
        $this->sheet->setCellValue('A' . $this->currentRow, $title);

        // تنسيق الرأس
        $this->sheet->getStyle('A' . $this->currentRow . ':J' . ($this->currentRow + 2))
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 20,
                    'color' => ['rgb' => 'FFFFFF'],
                    'name' => 'Arial'
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->headerColor]
                ]
            ]);

        // ارتفاع الصف
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->sheet->getRowDimension($this->currentRow + 1)->setRowHeight(25);
        $this->sheet->getRowDimension($this->currentRow + 2)->setRowHeight(25);

        // إضافة الشعار إذا كان متوفراً
        if (!empty($this->settings['supplier_logo_path'])) {
            $logoPath = __DIR__ . '/../' . $this->settings['supplier_logo_path'];
            if (file_exists($logoPath)) {
                try {
                    $drawing = new Drawing();
                    $drawing->setName('شعار الشركة');
                    $drawing->setDescription('شعار الشركة');
                    $drawing->setPath($logoPath);
                    $drawing->setHeight(70);
                    $drawing->setCoordinates('A' . $this->currentRow);
                    $drawing->setOffsetX(10);
                    $drawing->setOffsetY(20); // زيادة المسافة من الأعلى من 5 إلى 20
                    $drawing->setWorksheet($this->sheet);
                } catch (Exception $e) {
                    // تجاهل خطأ الشعار
                }
            }
        }

        $this->currentRow += 3;

        // سطر فارغ
        $this->currentRow++;
    }

    /**
     * إنشاء بيانات الشركة والعميل
     */
    private function generateCompanyInfo() {
        $startRow = $this->currentRow;

        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '🏢 بيانات المورد');
        $this->sheet->mergeCells('F' . $this->currentRow . ':J' . $this->currentRow);
        $this->sheet->setCellValue('F' . $this->currentRow, '👤 بيانات العميل');

        // تنسيق العناوين
        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => $this->headerColor]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->lightGray]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;

        // بيانات المورد والعميل
        $supplierData = [
            ['الشركة:', $this->settings['supplier_name']],
            ['العنوان:', $this->settings['supplier_address']],
            ['الرقم الضريبي:', $this->settings['supplier_tax_number']]
        ];

        $clientData = [
            ['العميل:', $this->settings['client_name']],
            ['العنوان:', $this->settings['client_address']],
            ['الرقم الضريبي:', $this->settings['client_tax_number']]
        ];

        for ($i = 0; $i < 3; $i++) {
            // بيانات المورد - دمج A و B
            $this->sheet->mergeCells('A' . $this->currentRow . ':B' . $this->currentRow);
            $this->sheet->setCellValue('A' . $this->currentRow, $supplierData[$i][0]);
            $this->sheet->mergeCells('C' . $this->currentRow . ':E' . $this->currentRow);

            // إدراج الرقم الضريبي كرقم بدون فاصلة عشرية
            if ($i == 2) { // الرقم الضريبي
                $this->sheet->setCellValueExplicit('C' . $this->currentRow, $supplierData[$i][1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $this->sheet->setCellValue('C' . $this->currentRow, $supplierData[$i][1]);
            }

            // بيانات العميل - دمج F و G
            $this->sheet->mergeCells('F' . $this->currentRow . ':G' . $this->currentRow);
            $this->sheet->setCellValue('F' . $this->currentRow, $clientData[$i][0]);
            $this->sheet->mergeCells('H' . $this->currentRow . ':J' . $this->currentRow);

            // إدراج الرقم الضريبي كرقم بدون فاصلة عشرية
            if ($i == 2) { // الرقم الضريبي
                $this->sheet->setCellValueExplicit('H' . $this->currentRow, $clientData[$i][1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $this->sheet->setCellValue('H' . $this->currentRow, $clientData[$i][1]);
            }

            // تنسيق الصف
            $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
                ->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

            // تنسيق التسميات (عريض)
            $this->sheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
            $this->sheet->getStyle('F' . $this->currentRow)->getFont()->setBold(true);

            // محاذاة الرقم الضريبي لليمين وتنسيقه كرقم
            if ($i == 2) { // الصف الثالث هو الرقم الضريبي
                $this->sheet->getStyle('C' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->sheet->getStyle('H' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                // تنسيق الرقم الضريبي بدون فاصلة عشرية
                $this->sheet->getStyle('C' . $this->currentRow)->getNumberFormat()->setFormatCode('0');
                $this->sheet->getStyle('H' . $this->currentRow)->getNumberFormat()->setFormatCode('0');
            }

            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
            $this->currentRow++;
        }

        // سطر فارغ
        $this->currentRow++;
    }

    /**
     * إنشاء تفاصيل الفاتورة
     */
    private function generateInvoiceDetails() {
        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':J' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '📋 تفاصيل الفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => $this->headerColor]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->accentColor]
                ]
            ]);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;

        // التفاصيل - صف واحد فقط
        // رقم الفاتورة (A-C)
        $this->sheet->setCellValue('A' . $this->currentRow, 'رقم الفاتورة');
        $this->sheet->mergeCells('B' . $this->currentRow . ':C' . $this->currentRow);
        $this->sheet->setCellValue('B' . $this->currentRow, 'INV-' . $this->invoiceData['extract_number']);

        // تاريخ الفاتورة (D-E)
        $this->sheet->setCellValue('D' . $this->currentRow, 'تاريخ الفاتورة');
        $this->sheet->setCellValue('E' . $this->currentRow, date('Y-m-d'));

        // رقم المستخلص (F-G)
        $this->sheet->setCellValue('F' . $this->currentRow, 'رقم المستخلص');
        $this->sheet->setCellValue('G' . $this->currentRow, $this->invoiceData['extract_number']);

        // رقم العقد (H-J)
        $this->sheet->setCellValue('H' . $this->currentRow, 'رقم العقد');
        $this->sheet->mergeCells('I' . $this->currentRow . ':J' . $this->currentRow);
        $this->sheet->setCellValue('I' . $this->currentRow, $this->settings['contract_number']);

        // تنسيق الصف
        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFFFFF']
                ]
            ]);

        // التسميات بخط عريض
        $this->sheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
        $this->sheet->getStyle('D' . $this->currentRow)->getFont()->setBold(true);
        $this->sheet->getStyle('F' . $this->currentRow)->getFont()->setBold(true);
        $this->sheet->getStyle('H' . $this->currentRow)->getFont()->setBold(true);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;

        // سطر فارغ
        $this->currentRow++;
    }

    /**
     * إنشاء جدول أوامر العمل
     */
    private function generateWorkOrdersTable() {
        // عنوان الجدول
        $this->sheet->mergeCells('A' . $this->currentRow . ':J' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '📊 تفاصيل أوامر العمل');

        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->headerColor]
                ]
            ]);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;

        // رأس الجدول
        $headers = ['م', 'رقم أمر العمل', 'النوع', 'الوصف', 'تاريخ الإنجاز', 'نسبة المصروفة', 'المبلغ الخاضع للضريبة', 'نسبة الضريبة', 'قيمة الضريبة', 'المجموع'];

        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }

        // تنسيق رأس الجدول
        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->headerColor]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'FFFFFF']
                    ]
                ]
            ]);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(30);
        $this->currentRow++;

        // بيانات الجدول
        $index = 1;
        foreach ($this->workOrders as $workOrder) {
            $taxableAmount = $workOrder['extract_value'];
            $taxRate = $this->settings['tax_rate'];
            $taxAmount = $taxableAmount * ($taxRate / 100);
            $total = $taxableAmount + $taxAmount;

            $rowData = [
                $index,
                $workOrder['work_order_number'],
                $workOrder['type_code'],
                $workOrder['work_order_type_description'],
                $workOrder['completion_date'],
                '100%',
                number_format($taxableAmount, 2),
                number_format($taxRate, 1) . '%',
                number_format($taxAmount, 2),
                number_format($total, 2)
            ];

            $col = 'A';
            foreach ($rowData as $value) {
                $this->sheet->setCellValue($col . $this->currentRow, $value);
                $col++;
            }

            // تنسيق الصف
            $fillColor = ($index % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
                ->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $fillColor]
                    ]
                ]);

            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
            $this->currentRow++;
            $index++;
        }

        // تذييل الجدول - حساب المجاميع
        $totalTaxableAmount = 0;
        $totalTaxAmount = 0;
        $totalAmount = 0;

        foreach ($this->workOrders as $workOrder) {
            $taxableAmount = $workOrder['extract_value'];
            $taxAmount = $taxableAmount * ($this->settings['tax_rate'] / 100);

            $totalTaxableAmount += $taxableAmount;
            $totalTaxAmount += $taxAmount;
            $totalAmount += ($taxableAmount + $taxAmount);
        }

        // صف التذييل
        $this->sheet->setCellValue('A' . $this->currentRow, '');
        $this->sheet->mergeCells('A' . $this->currentRow . ':F' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'الإجمالي');
        $this->sheet->setCellValue('G' . $this->currentRow, number_format($totalTaxableAmount, 2));
        $this->sheet->setCellValue('H' . $this->currentRow, '');
        $this->sheet->setCellValue('I' . $this->currentRow, number_format($totalTaxAmount, 2));
        $this->sheet->setCellValue('J' . $this->currentRow, number_format($totalAmount, 2));

        // تنسيق التذييل
        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->accentColor]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'FFFFFF']
                    ]
                ]
            ]);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;

        // سطر فارغ
        $this->currentRow++;
    }

    /**
     * إنشاء ملخص الفاتورة
     */
    private function generateSummary() {
        // حساب المجاميع
        $totalTaxableAmount = 0;
        $totalTaxAmount = 0;
        $totalAmount = 0;

        foreach ($this->workOrders as $workOrder) {
            $taxableAmount = $workOrder['extract_value'];
            $taxAmount = $taxableAmount * ($this->settings['tax_rate'] / 100);

            $totalTaxableAmount += $taxableAmount;
            $totalTaxAmount += $taxAmount;
            $totalAmount += ($taxableAmount + $taxAmount);
        }

        $discounts = 0;

        // عنوان الملخص
        $this->sheet->mergeCells('A' . $this->currentRow . ':J' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '💰 ملخص الفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => $this->headerColor]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->lightGray]
                ]
            ]);

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;

        // بيانات الملخص
        $summaryData = [
            ['إجمالي المبلغ الخاضع للضريبة', number_format($totalTaxableAmount, 2) . ' ' . $this->settings['currency']],
            ['مجموع ضريبة القيمة المضافة ' . $this->settings['tax_rate'] . '%', number_format($totalTaxAmount, 2) . ' ' . $this->settings['currency']],
            ['الحسومات', number_format($discounts, 2) . ' ' . $this->settings['currency']],
            ['المبلغ الإجمالي مع الضريبة', number_format($totalAmount - $discounts, 2) . ' ' . $this->settings['currency']]
        ];

        foreach ($summaryData as $i => $data) {
            $this->sheet->mergeCells('D' . $this->currentRow . ':F' . $this->currentRow);
            $this->sheet->setCellValue('D' . $this->currentRow, $data[0]);
            $this->sheet->mergeCells('G' . $this->currentRow . ':J' . $this->currentRow);
            $this->sheet->setCellValue('G' . $this->currentRow, $data[1]);

            // تنسيق
            $isTotal = ($i == count($summaryData) - 1);
            $bgColor = $isTotal ? $this->headerColor : 'FFFFFF';
            $textColor = $isTotal ? 'FFFFFF' : '000000';

            $this->sheet->getStyle('D' . $this->currentRow . ':J' . $this->currentRow)
                ->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => $isTotal ? 14 : 12,
                        'color' => ['rgb' => $textColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $bgColor]
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ]);

            $this->sheet->getRowDimension($this->currentRow)->setRowHeight($isTotal ? 30 : 25);
            $this->currentRow++;
        }

        // سطر فارغ
        $this->currentRow++;
    }

    /**
     * إنشاء ختام الفاتورة
     */
    private function generateFooter() {
        // إضافة الختم إذا كان موجوداً
        if (!empty($this->settings['stamp_path'])) {
            $stampPath = __DIR__ . '/../' . $this->settings['stamp_path'];

            if (file_exists($stampPath)) {
                try {
                    $drawing = new Drawing();
                    $drawing->setName('ختم الشركة');
                    $drawing->setDescription('ختم الشركة');
                    $drawing->setPath($stampPath);
                    $drawing->setHeight(120); // تكبير الختم من 80 إلى 120
                    $drawing->setCoordinates('E' . $this->currentRow);
                    $drawing->setOffsetX(50);
                    $drawing->setOffsetY(10);
                    $drawing->setWorksheet($this->sheet);

                    $this->sheet->getRowDimension($this->currentRow)->setRowHeight(90); // زيادة ارتفاع الصف
                    $this->currentRow++;
                } catch (Exception $e) {
                    // في حالة فشل إضافة الختم، نتجاهل الخطأ ونكمل
                }
            }
        }

        $this->currentRow++;

        // معلومات النظام
        $this->currentRow += 2;
        $this->sheet->mergeCells('A' . $this->currentRow . ':J' . $this->currentRow);
        $systemInfo = 'تم إنشاء هذه الفاتورة بواسطة نظام تِقان لإدارة المقاولات | تاريخ الإنشاء: ' . date('Y-m-d H:i:s');
        $this->sheet->setCellValue('A' . $this->currentRow, $systemInfo);

        $this->sheet->getStyle('A' . $this->currentRow . ':J' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'size' => 9,
                    'italic' => true,
                    'color' => ['rgb' => $this->darkGray]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
    }

    /**
     * ضبط عرض الأعمدة
     */
    private function autoSizeColumns() {
        // عرض مخصص للأعمدة
        $this->sheet->getColumnDimension('A')->setWidth(15);  // م / رقم الفاتورة - زيادة العرض أكثر
        $this->sheet->getColumnDimension('B')->setWidth(18); // رقم أمر العمل
        $this->sheet->getColumnDimension('C')->setWidth(12); // النوع
        $this->sheet->getColumnDimension('D')->setWidth(35); // الوصف - عريض
        $this->sheet->getColumnDimension('E')->setWidth(15); // تاريخ الإنجاز
        $this->sheet->getColumnDimension('F')->setWidth(15); // نسبة المصروفة
        $this->sheet->getColumnDimension('G')->setWidth(18); // المبلغ الخاضع للضريبة
        $this->sheet->getColumnDimension('H')->setWidth(10); // نسبة الضريبة - صغير
        $this->sheet->getColumnDimension('I')->setWidth(15); // قيمة الضريبة
        $this->sheet->getColumnDimension('J')->setWidth(18); // المجموع
    }
}