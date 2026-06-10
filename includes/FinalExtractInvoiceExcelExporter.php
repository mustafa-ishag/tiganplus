<?php
/**
 * Final Extract Invoice Excel Exporter
 * مصدر فواتير المستخلصات النهائية العادية إلى Excel
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class FinalExtractInvoiceExcelExporter {
    private $invoiceData;
    private $settings;
    private $workOrders;
    private $spreadsheet;
    private $sheet;
    private $currentRow = 1;
    
    private $headerColor;
    private $accentColor;
    private $lightGray = 'F8F9FA';
    private $darkGray = '6C757D';
    
    public function __construct($invoiceData, $settings, $workOrders) {
        $this->invoiceData = $invoiceData;
        $this->settings = $settings;
        $this->workOrders = $workOrders;
        
        // استخدام ألوان المستخلص النهائي من الإعدادات
        $this->headerColor = !empty($settings['final_extract_header_color']) ? str_replace('#', '', $settings['final_extract_header_color']) : '8E44AD';
        $this->accentColor = !empty($settings['final_extract_accent_color']) ? str_replace('#', '', $settings['final_extract_accent_color']) : 'E74C3C';
    }
    
    public function export() {
        // تنظيف أي output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        $this->sheet->setTitle('فاتورة ضريبية');
        
        // إعداد الصفحة
        $this->setupPage();
        
        // إنشاء محتوى الفاتورة (نفس ترتيب الفاتورة الجزئية)
        $this->generateHeader();
        $this->generateCompanyInfo();
        $this->generateInvoiceDetails();
        $this->generateWorkOrdersTable();
        $this->generateSummary();
        $this->generateFooter();
        
        // ضبط عرض الأعمدة
        $this->autoSizeColumns();
        
        // تصدير الملف
        $this->outputFile();
    }
    
    private function setupPage() {
        // اسم الورقة
        $this->sheet->setTitle('فاتورة ضريبية');
        
        // اتجاه RTL
        $this->sheet->setRightToLeft(true);
        
        // إعدادات الطباعة
        $this->sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        
        // الهوامش - صغيرة جداً
        $this->sheet->getPageMargins()
            ->setTop(0.2)
            ->setRight(0.2)
            ->setLeft(0.2)
            ->setBottom(0.2)
            ->setHeader(0.1)
            ->setFooter(0.1);
    }
    
    private function generateHeader() {
        // دمج الخلايا للرأس
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . ($this->currentRow + 2));
        
        // عنوان الفاتورة
        $title = $this->settings['invoice_title'];
        $this->sheet->setCellValue('A' . $this->currentRow, $title);
        
        // تنسيق الرأس
        $this->sheet->getStyle('A' . $this->currentRow . ':K' . ($this->currentRow + 2))
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
                    $drawing->setOffsetY(20);
                    $drawing->setWorksheet($this->sheet);
                } catch (Exception $e) {
                    // في حالة فشل إضافة الشعار، نتجاهل الخطأ ونكمل
                }
            }
        }
        
        $this->currentRow += 3;
        $this->currentRow++; // سطر فارغ
    }
    
    private function generateCompanyInfo() {
        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '🏢 بيانات المورد');
        $this->sheet->mergeCells('F' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->setCellValue('F' . $this->currentRow, '👤 بيانات العميل');

        // تنسيق العناوين
        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
            $this->sheet->mergeCells('H' . $this->currentRow . ':K' . $this->currentRow);

            // إدراج الرقم الضريبي كرقم بدون فاصلة عشرية
            if ($i == 2) { // الرقم الضريبي
                $this->sheet->setCellValueExplicit('H' . $this->currentRow, $clientData[$i][1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $this->sheet->setCellValue('H' . $this->currentRow, $clientData[$i][1]);
            }

            // تنسيق الصف
            $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
            if ($i == 2) {
                $this->sheet->getStyle('C' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->sheet->getStyle('H' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                // تنسيق الرقم الضريبي بدون فاصلة عشرية
                $this->sheet->getStyle('C' . $this->currentRow)->getNumberFormat()->setFormatCode('0');
                $this->sheet->getStyle('H' . $this->currentRow)->getNumberFormat()->setFormatCode('0');
            }

            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
            $this->currentRow++;
        }

        $this->currentRow++; // سطر فارغ
    }
    
    private function generateInvoiceDetails() {
        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '📋 تفاصيل الفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
        // رقم الفاتورة (A-B)
        $this->sheet->mergeCells('A' . $this->currentRow . ':B' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'رقم الفاتورة');
        $this->sheet->setCellValue('C' . $this->currentRow, 'INV-' . $this->invoiceData['extract_number']);

        // تاريخ الفاتورة (D-E)
        $this->sheet->setCellValue('D' . $this->currentRow, 'تاريخ الفاتورة');
        $this->sheet->setCellValue('E' . $this->currentRow, date('Y-m-d'));

        // رقم المستخلص (F-G)
        $this->sheet->setCellValue('F' . $this->currentRow, 'رقم المستخلص');
        $this->sheet->setCellValue('G' . $this->currentRow, $this->invoiceData['extract_number']);

        // رقم العقد (H-K)
        $this->sheet->setCellValue('H' . $this->currentRow, 'رقم العقد');
        $this->sheet->mergeCells('I' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->setCellValue('I' . $this->currentRow, $this->invoiceData['contract_number'] ?? '');

        // تنسيق الصف
        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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

        $this->currentRow++; // سطر فارغ
    }

    private function generateWorkOrdersTable() {
        // عنوان الجدول
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '📊 تفاصيل أوامر العمل');

        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
        $headers = ['م', 'رقم أمر العمل', 'النوع', 'الوصف', 'تاريخ الإنجاز', 'نسبة المصروفة', 'المبلغ الخاضع للضريبة', 'نسبة الضريبة', 'قيمة الضريبة', 'الغرامة', 'المجموع'];

        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }

        // تنسيق رأس الجدول
        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
            $penalty = $workOrder['penalty_amount'];
            $total = $taxableAmount + $taxAmount - $penalty; // خصم الغرامة من الإجمالي

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
                number_format($penalty, 2),
                number_format($total, 2)
            ];

            $col = 'A';
            foreach ($rowData as $value) {
                $this->sheet->setCellValue($col . $this->currentRow, $value);
                $col++;
            }

            // تنسيق الصف
            $fillColor = ($index % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
        $totalPenalty = 0;
        $totalAmount = 0;

        foreach ($this->workOrders as $workOrder) {
            $taxableAmount = $workOrder['extract_value'];
            $taxAmount = $taxableAmount * ($this->settings['tax_rate'] / 100);
            $penalty = $workOrder['penalty_amount'];

            $totalTaxableAmount += $taxableAmount;
            $totalTaxAmount += $taxAmount;
            $totalPenalty += $penalty;
            $totalAmount += ($taxableAmount + $taxAmount - $penalty); // خصم الغرامة من الإجمالي
        }

        // صف التذييل
        $this->sheet->mergeCells('A' . $this->currentRow . ':F' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'الإجمالي');
        $this->sheet->setCellValue('G' . $this->currentRow, number_format($totalTaxableAmount, 2));
        $this->sheet->setCellValue('H' . $this->currentRow, '');
        $this->sheet->setCellValue('I' . $this->currentRow, number_format($totalTaxAmount, 2));
        $this->sheet->setCellValue('J' . $this->currentRow, number_format($totalPenalty, 2));
        $this->sheet->setCellValue('K' . $this->currentRow, number_format($totalAmount, 2));

        // تنسيق التذييل
        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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

        $this->currentRow++; // سطر فارغ
    }

    private function generateSummary() {
        // حساب المجاميع
        $totalTaxableAmount = 0;
        $totalTaxAmount = 0;
        $totalPenalty = 0;
        $totalAmount = 0;

        foreach ($this->workOrders as $workOrder) {
            $taxableAmount = $workOrder['extract_value'];
            $taxAmount = $taxableAmount * ($this->settings['tax_rate'] / 100);
            $penalty = $workOrder['penalty_amount'];

            $totalTaxableAmount += $taxableAmount;
            $totalTaxAmount += $taxAmount;
            $totalPenalty += $penalty;
            $totalAmount += ($taxableAmount + $taxAmount - $penalty); // خصم الغرامة من الإجمالي
        }

        $discounts = 0;

        // عنوان الملخص
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '💰 ملخص الفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
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
            ['مبلغ الغرامة', number_format($totalPenalty, 2) . ' ' . $this->settings['currency']],
            ['الحسومات', number_format($discounts, 2) . ' ' . $this->settings['currency']],
            ['المبلغ الإجمالي مع الضريبة', number_format($totalAmount - $discounts, 2) . ' ' . $this->settings['currency']]
        ];

        foreach ($summaryData as $i => $data) {
            $this->sheet->mergeCells('D' . $this->currentRow . ':F' . $this->currentRow);
            $this->sheet->setCellValue('D' . $this->currentRow, $data[0]);
            $this->sheet->mergeCells('G' . $this->currentRow . ':K' . $this->currentRow);
            $this->sheet->setCellValue('G' . $this->currentRow, $data[1]);

            // تنسيق
            $isTotal = ($i == count($summaryData) - 1);
            $bgColor = $isTotal ? $this->headerColor : 'FFFFFF';
            $textColor = $isTotal ? 'FFFFFF' : '000000';

            $this->sheet->getStyle('D' . $this->currentRow . ':K' . $this->currentRow)
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

        $this->currentRow++; // سطر فارغ
    }

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
                    $drawing->setHeight(120);
                    $drawing->setCoordinates('E' . $this->currentRow);
                    $drawing->setOffsetX(50);
                    $drawing->setOffsetY(10);
                    $drawing->setWorksheet($this->sheet);

                    $this->sheet->getRowDimension($this->currentRow)->setRowHeight(90);
                    $this->currentRow++;
                } catch (Exception $e) {
                    // في حالة فشل إضافة الختم، نتجاهل الخطأ ونكمل
                }
            }
        }

        $this->currentRow++;

        // معلومات النظام
        $this->currentRow += 2;
        $this->sheet->mergeCells('A' . $this->currentRow . ':K' . $this->currentRow);
        $systemInfo = 'تم إنشاء هذه الفاتورة بواسطة نظام إتقان لإدارة المقاولات | تاريخ الإنشاء: ' . date('Y-m-d H:i:s');
        $this->sheet->setCellValue('A' . $this->currentRow, $systemInfo);

        $this->sheet->getStyle('A' . $this->currentRow . ':K' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'size' => 9,
                    'color' => ['rgb' => $this->darkGray]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);
    }

    private function autoSizeColumns() {
        // عرض مخصص للأعمدة
        $this->sheet->getColumnDimension('A')->setWidth(8);   // م
        $this->sheet->getColumnDimension('B')->setWidth(16);  // رقم أمر العمل
        $this->sheet->getColumnDimension('C')->setWidth(10);  // النوع
        $this->sheet->getColumnDimension('D')->setWidth(30);  // الوصف
        $this->sheet->getColumnDimension('E')->setWidth(14);  // تاريخ الإنجاز
        $this->sheet->getColumnDimension('F')->setWidth(12);  // نسبة المصروفة
        $this->sheet->getColumnDimension('G')->setWidth(18);  // المبلغ الخاضع للضريبة
        $this->sheet->getColumnDimension('H')->setWidth(12);  // نسبة الضريبة
        $this->sheet->getColumnDimension('I')->setWidth(14);  // قيمة الضريبة
        $this->sheet->getColumnDimension('J')->setWidth(12);  // الغرامة
        $this->sheet->getColumnDimension('K')->setWidth(16);  // المجموع
    }

    private function outputFile() {
        // تنظيف أي output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'فاتورة_ضريبية_' . $this->invoiceData['extract_number'] . '_' . date('Y-m-d') . '.xlsx';

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
}
