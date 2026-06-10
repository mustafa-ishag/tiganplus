<?php
/**
 * مصدر فواتير المستخلصات النهائية للجزئية إلى Excel
 * Final For Partial Invoice Excel Exporter
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

class FinalForPartialInvoiceExcelExporter {
    private $invoiceData;
    private $settings;
    private $workOrders;
    private $partialWorkOrders; // أوامر العمل من المستخلص الجزئي
    private $partialExtractData; // بيانات المستخلص الجزئي
    private $spreadsheet;
    private $sheet;
    private $currentRow = 1;

    private $headerColor;
    private $accentColor;
    private $lightGray = 'F8F9FA';
    private $darkGray = '6C757D';

    public function __construct($invoiceData, $settings, $workOrders, $partialWorkOrders = [], $partialExtractData = null) {
        $this->invoiceData = $invoiceData;
        $this->settings = $settings;
        $this->workOrders = $workOrders;
        $this->partialWorkOrders = $partialWorkOrders;
        $this->partialExtractData = $partialExtractData;

        // استخدام ألوان الفاتورة النهائية من الإعدادات
        $this->headerColor = !empty($settings['final_header_color']) ? str_replace('#', '', $settings['final_header_color']) : '2C5AA0';
        $this->accentColor = !empty($settings['final_accent_color']) ? str_replace('#', '', $settings['final_accent_color']) : '4CAF50';
    }
    
    /**
     * تصدير الفاتورة
     */
    public function export() {
        // تنظيف أي output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $this->spreadsheet = new Spreadsheet();
        $this->sheet = $this->spreadsheet->getActiveSheet();
        
        $this->setupPage();
        $this->generateHeader();
        $this->generateInvoiceDetails();
        $this->generateCompanyInfo();
        $this->generateWorkOrdersTable();
        $this->generateSummary();
        $this->generateFooter();
        
        // ضبط عرض الأعمدة
        $this->autoSizeColumns();
        
        // تصدير الملف
        $filename = 'فاتورة_ضريبية_نهائية_' . $this->invoiceData['extract_number'] . '_' . date('Y-m-d') . '.xlsx';
        
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
        $this->sheet->setTitle('فاتورة ضريبية نهائية');
        
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
            ->setTop(0.2)
            ->setRight(0.2)
            ->setLeft(0.2)
            ->setBottom(0.2)
            ->setHeader(0.1)
            ->setFooter(0.1);
    }
    
    /**
     * إنشاء رأس الفاتورة
     */
    private function generateHeader() {
        // دمج الخلايا للرأس
        $this->sheet->mergeCells('A' . $this->currentRow . ':L' . ($this->currentRow + 2));

        // عنوان الفاتورة - TAX INVOICE + فاتورة ضريبية نهائية
        $title = "TAX INVOICE\nفاتورة ضريبية نهائية";
        $this->sheet->setCellValue('A' . $this->currentRow, $title);

        // تنسيق الرأس
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . ($this->currentRow + 2))
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 18,
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
        
        // إضافة الشعار إذا كان متوفراً (يسار الفاتورة)
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
                    // تجاهل خطأ الشعار
                }
            }
        }
        
        $this->currentRow += 3;
        
        // سطر فارغ
        $this->currentRow++;
    }
    
    /**
     * إنشاء تفاصيل الفاتورة الأساسية
     */
    private function generateInvoiceDetails() {
        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':L' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '📋 التفاصيل الأساسية للفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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
                    'startColor' => ['rgb' => $this->accentColor]
                ]
            ]);
        
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(25);
        $this->currentRow++;
        
        // الصف الأول: رقم الفاتورة | تاريخ الفاتورة | رقم المستخلص
        // دمج A و B لرقم الفاتورة
        $this->sheet->mergeCells('A' . $this->currentRow . ':B' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'رقم الفاتورة');
        $this->sheet->mergeCells('C' . $this->currentRow . ':D' . $this->currentRow);
        $this->sheet->setCellValue('C' . $this->currentRow, 'INV-' . $this->invoiceData['extract_number']);

        $this->sheet->setCellValue('E' . $this->currentRow, 'تاريخ الفاتورة');
        $this->sheet->mergeCells('F' . $this->currentRow . ':G' . $this->currentRow);
        $this->sheet->setCellValue('F' . $this->currentRow, date('Y-m-d'));

        $this->sheet->setCellValue('H' . $this->currentRow, 'رقم المستخلص');
        $this->sheet->mergeCells('I' . $this->currentRow . ':L' . $this->currentRow);
        $this->sheet->setCellValue('I' . $this->currentRow, $this->invoiceData['extract_number']);

        $this->applyDetailsRowStyle($this->currentRow);
        $this->currentRow++;

        // الصف الثاني: الرقم المرجعي | رقم العقد
        // دمج A و B للرقم المرجعي
        $this->sheet->mergeCells('A' . $this->currentRow . ':B' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'الرقم المرجعي');
        $this->sheet->mergeCells('C' . $this->currentRow . ':G' . $this->currentRow);
        $this->sheet->setCellValue('C' . $this->currentRow, 'REF-' . date('Ymd') . '-' . $this->invoiceData['extract_number']);

        $this->sheet->setCellValue('H' . $this->currentRow, 'رقم العقد');
        $this->sheet->mergeCells('I' . $this->currentRow . ':L' . $this->currentRow);
        $this->sheet->setCellValue('I' . $this->currentRow, $this->invoiceData['contract_number'] ?? '');

        $this->applyDetailsRowStyle($this->currentRow);
        $this->currentRow++;
        
        // سطر فارغ
        $this->currentRow++;
    }
    
    /**
     * تطبيق تنسيق صف التفاصيل
     */
    private function applyDetailsRowStyle($row) {
        $this->sheet->getStyle('A' . $row . ':L' . $row)
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
        $this->sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $this->sheet->getStyle('E' . $row)->getFont()->setBold(true);
        $this->sheet->getStyle('H' . $row)->getFont()->setBold(true);
        
        $this->sheet->getRowDimension($row)->setRowHeight(25);
    }

    /**
     * إنشاء بيانات الشركة والعميل
     */
    private function generateCompanyInfo() {
        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':F' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '🏢 بيانات المورد');

        $this->sheet->mergeCells('G' . $this->currentRow . ':L' . $this->currentRow);
        $this->sheet->setCellValue('G' . $this->currentRow, '👤 بيانات العميل');

        // تنسيق العناوين
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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
            $this->sheet->mergeCells('C' . $this->currentRow . ':F' . $this->currentRow);

            // إدراج الرقم الضريبي كرقم بدون فاصلة عشرية
            if ($i == 2) { // الرقم الضريبي
                $this->sheet->setCellValueExplicit('C' . $this->currentRow, $supplierData[$i][1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $this->sheet->setCellValue('C' . $this->currentRow, $supplierData[$i][1]);
            }

            // بيانات العميل - دمج G و H
            $this->sheet->mergeCells('G' . $this->currentRow . ':H' . $this->currentRow);
            $this->sheet->setCellValue('G' . $this->currentRow, $clientData[$i][0]);
            $this->sheet->mergeCells('I' . $this->currentRow . ':L' . $this->currentRow);

            // إدراج الرقم الضريبي كرقم بدون فاصلة عشرية
            if ($i == 2) { // الرقم الضريبي
                $this->sheet->setCellValueExplicit('I' . $this->currentRow, $clientData[$i][1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $this->sheet->setCellValue('I' . $this->currentRow, $clientData[$i][1]);
            }

            // تنسيق الصف
            $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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
            $this->sheet->getStyle('G' . $this->currentRow)->getFont()->setBold(true);

            // محاذاة الرقم الضريبي لليمين وتنسيقه كرقم
            if ($i == 2) {
                $this->sheet->getStyle('C' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->sheet->getStyle('I' . $this->currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                // تنسيق الرقم الضريبي بدون فاصلة عشرية
                $this->sheet->getStyle('C' . $this->currentRow)->getNumberFormat()->setFormatCode('0');
                $this->sheet->getStyle('I' . $this->currentRow)->getNumberFormat()->setFormatCode('0');
            }

            $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
            $this->currentRow++;
        }

        // سطر فارغ
        $this->currentRow++;
    }

    /**
     * إنشاء جدول تفاصيل أوامر العمل
     */
    private function generateWorkOrdersTable() {
        // عنوان الجدول
        $this->sheet->mergeCells('A' . $this->currentRow . ':L' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '📊 تفاصيل الفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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
        $headers = ['م', 'رقم أمر العمل', 'النوع', 'الوصف', 'تاريخ الإنجاز', 'القيمة الإجمالية', 'القيمة الجزئية المفوترة سابقاً', 'القيمة النهائية الخاضعة للضريبة', 'نسبة الضريبة', 'قيمة الضريبة', 'الغرامة', 'المجموع'];

        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }

        // تنسيق رأس الجدول
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 10,
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

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(40);
        $this->currentRow++;

        // بيانات الجدول
        $index = 1;
        $totalGrandTotal = 0;
        $totalPartialValue = 0;
        $totalFinalValue = 0;
        $totalTaxAmount = 0;
        $totalPenalty = 0;
        $totalAmount = 0;

        foreach ($this->workOrders as $workOrder) {
            // القيمة النهائية الخاضعة للضريبة (من المستخلص النهائي)
            $finalValue = $workOrder['extract_value'];

            // القيمة الجزئية المفوترة سابقاً (من المستخلص الجزئي)
            $partialValue = 0;
            foreach ($this->partialWorkOrders as $pwo) {
                if ($pwo['work_order_id'] == $workOrder['work_order_id']) {
                    $partialValue = $pwo['extract_value'];
                    break;
                }
            }

            // القيمة الإجمالية
            $grandTotal = $partialValue + $finalValue;

            // حساب الضريبة والغرامة
            $taxRate = $this->settings['tax_rate'];
            $taxAmount = $finalValue * ($taxRate / 100);
            $penalty = $workOrder['penalty_amount'];
            $total = $finalValue + $taxAmount - $penalty; // خصم الغرامة من الإجمالي

            $rowData = [
                $index,
                $workOrder['work_order_number'],
                $workOrder['type_code'],
                $workOrder['work_order_type_description'],
                $workOrder['completion_date'],
                number_format($grandTotal, 2),
                number_format($partialValue, 2),
                number_format($finalValue, 2),
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
            $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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

            // تجميع المجاميع
            $totalGrandTotal += $grandTotal;
            $totalPartialValue += $partialValue;
            $totalFinalValue += $finalValue;
            $totalTaxAmount += $taxAmount;
            $totalPenalty += $penalty;
            $totalAmount += $total;
        }

        // تذييل الجدول - الإجمالي
        $this->sheet->mergeCells('A' . $this->currentRow . ':E' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'الإجمالي');
        $this->sheet->setCellValue('F' . $this->currentRow, number_format($totalGrandTotal, 2));
        $this->sheet->setCellValue('G' . $this->currentRow, number_format($totalPartialValue, 2));
        $this->sheet->setCellValue('H' . $this->currentRow, number_format($totalFinalValue, 2));
        $this->sheet->setCellValue('I' . $this->currentRow, '');
        $this->sheet->setCellValue('J' . $this->currentRow, number_format($totalTaxAmount, 2));
        $this->sheet->setCellValue('K' . $this->currentRow, number_format($totalPenalty, 2));
        $this->sheet->setCellValue('L' . $this->currentRow, number_format($totalAmount, 2));

        // تنسيق التذييل
        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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
        $totalTaxableAmount = 0; // المبلغ الخاضع للضريبة (القيمة النهائية فقط)
        $totalTaxAmount = 0;
        $totalPenalty = 0;

        foreach ($this->workOrders as $workOrder) {
            $finalValue = $workOrder['extract_value'];
            $taxAmount = $finalValue * ($this->settings['tax_rate'] / 100);
            $penalty = $workOrder['penalty_amount'];

            $totalTaxableAmount += $finalValue;
            $totalTaxAmount += $taxAmount;
            $totalPenalty += $penalty;
        }

        // جلب ضريبة المستخلص الجزئي الفعلية من بيانات المستخلص
        $totalPartialTax = 0;
        if ($this->partialExtractData && isset($this->partialExtractData['tax_amount'])) {
            $totalPartialTax = $this->partialExtractData['tax_amount'];
        }

        $discounts = 0; // الحسومات
        $totalBeforeTax = $totalTaxableAmount - $discounts; // الإجمالي قبل الضريبة بعد الحسومات
        $totalWithTax = $totalBeforeTax + $totalTaxAmount; // الإجمالي شامل الضريبة
        $finalPayable = $totalWithTax - $totalPenalty + $totalPartialTax; // القيمة النهائية = الإجمالي - الغرامة + ضريبة المستخلص الجزئي

        // عنوان القسم
        $this->sheet->mergeCells('A' . $this->currentRow . ':L' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, '💰 ملخص الفاتورة');

        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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

        // بيانات الملخص
        $summaryData = [
            ['إجمالي بدون ضريبة القيمة المضافة بالريال السعودي', number_format($totalTaxableAmount, 2) . ' ' . $this->settings['currency']],
            ['ضريبة القيمة المضافة بالريال السعودي', number_format($totalTaxAmount, 2) . ' ' . $this->settings['currency']],
            ['مبلغ الغرامة بالريال السعودي', number_format($totalPenalty, 2) . ' ' . $this->settings['currency']],
            ['إجمالي الحسومات بالريال السعودي', number_format($discounts, 2) . ' ' . $this->settings['currency']],
            ['الإجمالي غير شامل ضريبة القيمة المضافة بعد الحسومات بالريال السعودي', number_format($totalBeforeTax, 2) . ' ' . $this->settings['currency']],
            ['الإجمالي شامل ضريبة القيمة المضافة بالريال السعودي', number_format($totalWithTax, 2) . ' ' . $this->settings['currency']],
            ['مبلغ ضريبة القيمة المضافة المستحقة عن الفاتورة الجزئية لهذه الفاتورة بالريال السعودي', number_format($totalPartialTax, 2) . ' ' . $this->settings['currency']],
            ['القيمة الإجمالية النهائية المستحقة دفعها لهذه الفاتورة بالريال السعودي', number_format($finalPayable, 2) . ' ' . $this->settings['currency']]
        ];

        foreach ($summaryData as $i => $item) {
            $this->sheet->mergeCells('E' . $this->currentRow . ':I' . $this->currentRow);
            $this->sheet->setCellValue('E' . $this->currentRow, $item[0]);
            $this->sheet->mergeCells('J' . $this->currentRow . ':L' . $this->currentRow);
            $this->sheet->setCellValue('J' . $this->currentRow, $item[1]);

            // تنسيق خاص للصف الأخير (القيمة النهائية)
            $isLastRow = ($i == count($summaryData) - 1);
            $fillColor = $isLastRow ? $this->accentColor : 'FFFFFF';
            $fontColor = $isLastRow ? 'FFFFFF' : '000000';

            // تنسيق التسمية (E-I)
            $this->sheet->getStyle('E' . $this->currentRow . ':I' . $this->currentRow)
                ->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => $isLastRow ? 13 : 11,
                        'color' => ['rgb' => $fontColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $fillColor]
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ]);

            // تنسيق القيمة (J-L)
            $this->sheet->getStyle('J' . $this->currentRow . ':L' . $this->currentRow)
                ->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => $isLastRow ? 13 : 11,
                        'color' => ['rgb' => $fontColor]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $fillColor]
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC']
                        ]
                    ]
                ]);

            $this->sheet->getRowDimension($this->currentRow)->setRowHeight($isLastRow ? 30 : 22);
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
        $this->sheet->mergeCells('A' . $this->currentRow . ':L' . $this->currentRow);
        $systemInfo = 'تم إنشاء هذه الفاتورة بواسطة نظام إتقان لإدارة المقاولات | تاريخ الإنشاء: ' . date('Y-m-d H:i:s');
        $this->sheet->setCellValue('A' . $this->currentRow, $systemInfo);

        $this->sheet->getStyle('A' . $this->currentRow . ':L' . $this->currentRow)
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

    /**
     * ضبط عرض الأعمدة
     */
    private function autoSizeColumns() {
        // عرض مخصص للأعمدة
        $this->sheet->getColumnDimension('A')->setWidth(8);   // م
        $this->sheet->getColumnDimension('B')->setWidth(16);  // رقم أمر العمل
        $this->sheet->getColumnDimension('C')->setWidth(10);  // النوع
        $this->sheet->getColumnDimension('D')->setWidth(30);  // الوصف
        $this->sheet->getColumnDimension('E')->setWidth(14);  // تاريخ الإنجاز
        $this->sheet->getColumnDimension('F')->setWidth(16);  // القيمة الإجمالية
        $this->sheet->getColumnDimension('G')->setWidth(18);  // القيمة الجزئية المفوترة سابقاً
        $this->sheet->getColumnDimension('H')->setWidth(20);  // القيمة النهائية الخاضعة للضريبة
        $this->sheet->getColumnDimension('I')->setWidth(10);  // نسبة الضريبة
        $this->sheet->getColumnDimension('J')->setWidth(14);  // قيمة الضريبة
        $this->sheet->getColumnDimension('K')->setWidth(12);  // الغرامة
        $this->sheet->getColumnDimension('L')->setWidth(16);  // المجموع
    }
}

