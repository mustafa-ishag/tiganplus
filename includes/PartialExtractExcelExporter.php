<?php
/**
 * مصدر المستخلصات الجزئية إلى Excel
 * Partial Extract Excel Exporter
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class PartialExtractExcelExporter {
    private $db;
    private $userId;
    private $filters;
    private $spreadsheet;
    private $sheet;
    private $currentRow = 1;
    
    // الألوان
    private $headerColor = '2C5AA0';
    private $accentColor = '4CAF50';
    private $lightGray = 'F8F9FA';
    private $darkGray = '6C757D';
    
    public function __construct($db, $userId, $filters = []) {
        $this->db = $db;
        $this->userId = $userId;
        $this->filters = $filters;
    }
    
    /**
     * تصدير المستخلصات الجزئية
     */
    public function export() {
        try {
            // بدء تسجيل العملية
            $logId = $this->startExportLog();
            
            // تنظيف أي output buffer
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            $this->spreadsheet = new Spreadsheet();
            $this->sheet = $this->spreadsheet->getActiveSheet();
            
            $this->setupPage();
            $this->generateHeader();
            $this->generateDataTable();
            $this->autoSizeColumns();
            
            // إنهاء تسجيل العملية
            $this->completeExportLog($logId, true);
            
            // تصدير الملف
            $this->outputFile();
            
        } catch (Exception $e) {
            if (isset($logId)) {
                $this->completeExportLog($logId, false, $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * إعداد الصفحة
     */
    private function setupPage() {
        $this->sheet->setTitle('المستخلصات الجزئية');
        $this->sheet->setRightToLeft(true);
        
        // إعدادات الطباعة
        $this->sheet->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        
        // الهوامش
        $this->sheet->getPageMargins()
            ->setTop(0.3)
            ->setRight(0.3)
            ->setLeft(0.3)
            ->setBottom(0.3);
    }
    
    /**
     * إنشاء رأس الملف
     */
    private function generateHeader() {
        // عنوان التقرير
        $this->sheet->mergeCells('A' . $this->currentRow . ':N' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'تقرير المستخلصات الجزئية مع أوامر العمل');

        $this->sheet->getStyle('A' . $this->currentRow . ':N' . $this->currentRow)
            ->applyFromArray([
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
                    'startColor' => ['rgb' => $this->headerColor]
                ]
            ]);
        
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(30);
        $this->currentRow++;
        
        // معلومات التصدير
        $this->sheet->mergeCells('A' . $this->currentRow . ':N' . $this->currentRow);
        $exportInfo = 'تاريخ التصدير: ' . date('Y-m-d H:i:s') . ' | المستخدم: ' . $this->getCurrentUserName();
        $this->sheet->setCellValue('A' . $this->currentRow, $exportInfo);

        $this->sheet->getStyle('A' . $this->currentRow . ':N' . $this->currentRow)
            ->applyFromArray([
                'font' => ['size' => 10, 'color' => ['rgb' => $this->darkGray]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ]);
        
        $this->currentRow += 2;
    }
    
    /**
     * إنشاء جدول البيانات
     */
    private function generateDataTable() {
        // رأس الجدول
        $headers = [
            'رقم المستخلص',
            'رقم الفاتورة',
            'الفرع',
            'القسم',
            'تاريخ المستخلص',
            'المبلغ الإجمالي',
            'الضريبة',
            'المبلغ الصافي',
            'مرحلة الاعتماد',
            'رقم أمر العمل',
            'نوع أمر العمل',
            'تاريخ الإنجاز',
            'قيمة المستخلص',
            'ملاحظات أمر العمل'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }
        
        // تنسيق رأس الجدول
        $this->sheet->getStyle('A' . $this->currentRow . ':N' . $this->currentRow)
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
                    'startColor' => ['rgb' => $this->accentColor]
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'FFFFFF']
                    ]
                ]
            ]);
        
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(35);
        $this->currentRow++;
        
        // جلب البيانات
        $data = $this->fetchData();
        
        // إدراج البيانات
        foreach ($data as $index => $row) {
            $this->insertDataRow($row, $index);
        }
    }
    
    /**
     * جلب البيانات من قاعدة البيانات
     */
    private function fetchData() {
        $whereConditions = ['1=1'];
        $params = [];
        
        // تطبيق المرشحات
        if (!empty($this->filters['branch_id'])) {
            $whereConditions[] = 'pe.branch_id = ?';
            $params[] = $this->filters['branch_id'];
        }
        
        if (!empty($this->filters['department'])) {
            $whereConditions[] = 'pe.department = ?';
            $params[] = $this->filters['department'];
        }
        
        if (!empty($this->filters['approval_stage'])) {
            $whereConditions[] = 'pe.approval_stage = ?';
            $params[] = $this->filters['approval_stage'];
        }
        
        if (!empty($this->filters['date_from'])) {
            $whereConditions[] = 'pe.extract_date >= ?';
            $params[] = $this->filters['date_from'];
        }
        
        if (!empty($this->filters['date_to'])) {
            $whereConditions[] = 'pe.extract_date <= ?';
            $params[] = $this->filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $query = "
            SELECT pe.extract_number,
                   pe.invoice_number,
                   b.name as branch_name,
                   pe.department,
                   pe.extract_date,
                   pe.total_amount,
                   pe.tax_amount,
                   pe.net_amount,
                   pe.approval_stage,
                   wo.work_order_number,
                   wot.type_code as work_order_type_code,
                   wot.description as work_order_type_description,
                   pewo.completion_date,
                   pewo.extract_value,
                   pewo.notes as work_order_notes,
                   pe.created_at
            FROM partial_extracts pe
            LEFT JOIN branches b ON pe.branch_id = b.id
            LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
            LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE $whereClause
            ORDER BY pe.extract_number, wo.work_order_number
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * إدراج صف بيانات
     */
    private function insertDataRow($row, $index) {
        $rowData = [
            $row['extract_number'],
            $row['invoice_number'],
            $row['branch_name'],
            $this->translateDepartment($row['department']),
            $row['extract_date'],
            number_format($row['total_amount'], 2),
            number_format($row['tax_amount'], 2),
            number_format($row['net_amount'], 2),
            $this->translateApprovalStage($row['approval_stage']),
            $row['work_order_number'],
            $row['work_order_type_code'],
            $row['completion_date'],
            number_format($row['extract_value'], 2),
            $row['work_order_notes']
        ];
        
        $col = 'A';
        foreach ($rowData as $value) {
            $this->sheet->setCellValue($col . $this->currentRow, $value);
            $col++;
        }
        
        // تنسيق الصف
        $fillColor = ($index % 2 == 0) ? 'FFFFFF' : $this->lightGray;
        $this->sheet->getStyle('A' . $this->currentRow . ':N' . $this->currentRow)
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor]
                ]
            ]);
        
        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow++;
    }
    
    /**
     * ضبط عرض الأعمدة
     */
    private function autoSizeColumns() {
        $columnWidths = [
            'A' => 15, // رقم المستخلص
            'B' => 15, // رقم الفاتورة
            'C' => 20, // الفرع
            'D' => 12, // القسم
            'E' => 12, // تاريخ المستخلص
            'F' => 15, // المبلغ الإجمالي
            'G' => 12, // الضريبة
            'H' => 15, // المبلغ الصافي
            'I' => 15, // مرحلة الاعتماد
            'J' => 15, // رقم أمر العمل
            'K' => 12, // نوع أمر العمل
            'L' => 12, // تاريخ الإنجاز
            'M' => 15, // قيمة المستخلص
            'N' => 25  // ملاحظات أمر العمل
        ];
        
        foreach ($columnWidths as $column => $width) {
            $this->sheet->getColumnDimension($column)->setWidth($width);
        }
    }
    
    /**
     * ترجمة القسم
     */
    private function translateDepartment($department) {
        $translations = [
            'connections' => 'التوصيلات',
            'projects' => 'المشاريع'
        ];
        return $translations[$department] ?? $department;
    }
    
    /**
     * ترجمة مرحلة الاعتماد من قاعدة البيانات
     */
    private function translateApprovalStage($stage) {
        // محاولة جلب اسم المرحلة من قاعدة البيانات
        try {
            $stmt = $this->db->prepare("
                SELECT stage_name
                FROM approval_stages
                WHERE stage_key = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$stage]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['stage_name'];
            }
        } catch (Exception $e) {
            // في حالة فشل الاستعلام، استخدم القيم الافتراضية
            error_log("Error fetching approval stage name: " . $e->getMessage());
        }

        // القيم الافتراضية كـ fallback
        $translations = [
            'draft' => 'مسودة',
            'pending_supervisor' => 'في انتظار المشرف',
            'pending_manager' => 'في انتظار المدير',
            'pending_finance' => 'في انتظار المالية',
            'disbursed' => 'مصروف',
            'taif_finance' => 'مالية الطائف',
            'rejected' => 'مرفوض',
            'technical_support' => 'المساندة الفنية',
            'construction' => 'الإنشاءات',
            'department_manager' => 'مدير الدائرة',
            'administration_manager' => 'مدير الإدارة'
        ];
        return $translations[$stage] ?? $stage;
    }
    
    /**
     * الحصول على اسم المستخدم الحالي
     */
    private function getCurrentUserName() {
        $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        return $user ? $user['full_name'] : 'غير معروف';
    }
    
    /**
     * بدء تسجيل عملية التصدير
     */
    private function startExportLog() {
        $stmt = $this->db->prepare("
            INSERT INTO partial_extract_import_export_logs 
            (operation_type, file_name, status, user_id) 
            VALUES ('export', ?, 'processing', ?)
        ");
        
        $fileName = 'partial_extracts_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        $stmt->execute([$fileName, $this->userId]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * إنهاء تسجيل عملية التصدير
     */
    private function completeExportLog($logId, $success, $errorMessage = null) {
        $status = $success ? 'completed' : 'failed';

        // حساب عدد السجلات المصدرة
        $data = $this->fetchData();
        $totalRecords = count($data);

        $stmt = $this->db->prepare("
            UPDATE partial_extract_import_export_logs
            SET status = ?, error_message = ?, completed_at = NOW(),
                total_records = ?, successful_records = ?
            WHERE id = ?
        ");

        $successfulRecords = $success ? $totalRecords : 0;
        $stmt->execute([$status, $errorMessage, $totalRecords, $successfulRecords, $logId]);
    }

    /**
     * إخراج الملف
     */
    private function outputFile() {
        $filename = 'partial_extracts_export_' . date('Y-m-d_H-i-s') . '.xlsx';

        // إرسال headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // حفظ الملف
        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
?>
