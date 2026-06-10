<?php
/**
 * مصدر المستخلصات النهائية للجزئية إلى Excel
 * Final For Partial Extract Excel Exporter
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class FinalForPartialExtractExcelExporter {
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
     * تصدير المستخلصات النهائية للجزئية
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
        $this->sheet->setTitle('المستخلصات النهائية للجزئية');
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
        $this->sheet->mergeCells('A' . $this->currentRow . ':I' . $this->currentRow);
        $this->sheet->setCellValue('A' . $this->currentRow, 'تقرير المستخلصات النهائية للجزئية مع أوامر العمل');

        $this->sheet->getStyle('A' . $this->currentRow . ':I' . $this->currentRow)
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
        $this->sheet->mergeCells('A' . $this->currentRow . ':M' . $this->currentRow);
        $exportInfo = 'تاريخ التصدير: ' . date('Y-m-d H:i:s') . ' | المستخدم: ' . $this->getCurrentUserName();
        $this->sheet->setCellValue('A' . $this->currentRow, $exportInfo);

        $this->sheet->getStyle('A' . $this->currentRow . ':M' . $this->currentRow)
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
        // رأس الجدول (7 أعمدة فقط - تم إزالة الأعمدة المحسوبة)
        $headers = [
            'رقم المستخلص',
            'الفرع',
            'تاريخ المستخلص',
            'المستخلص الجزئي المرتبط',
            'مرحلة الاعتماد',
            'رقم أمر العمل',
            'نوع أمر العمل',
            'قيمة المستخلص',
            'الغرامة'
        ];
        
        $col = 'A';
        foreach ($headers as $header) {
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $col++;
        }
        
        // تنسيق رأس الجدول
        $this->sheet->getStyle('A' . $this->currentRow . ':I' . $this->currentRow)
            ->applyFromArray([
                'font' => [
                    'bold' => true,
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
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
        
        $this->currentRow++;
        
        // جلب البيانات
        $data = $this->fetchData();
        
        // إضافة البيانات
        foreach ($data as $row) {
            $this->addDataRow($row);
        }
    }
    
    /**
     * جلب البيانات من قاعدة البيانات
     */
    private function fetchData() {
        $query = "
            SELECT
                ffpe.extract_number,
                b.name as branch_name,
                ffpe.extract_date,
                pe.extract_number as related_partial_extract_number,
                ffpe.approval_stage,
                wo.work_order_number,
                wot.type_code as work_order_type_code,
                ffpewo.extract_value,
                ffpewo.penalty_amount
            FROM final_for_partial_extracts ffpe
            LEFT JOIN branches b ON ffpe.branch_id = b.id
            LEFT JOIN partial_extracts pe ON ffpe.related_partial_extract_id = pe.id
            LEFT JOIN final_for_partial_extract_work_orders ffpewo ON ffpe.id = ffpewo.final_for_partial_extract_id
            LEFT JOIN work_orders wo ON ffpewo.work_order_id = wo.id
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($this->filters['branch_id'])) {
            $query .= " AND ffpe.branch_id = ?";
            $params[] = $this->filters['branch_id'];
        }
        
        if (!empty($this->filters['approval_stage'])) {
            $query .= " AND ffpe.approval_stage = ?";
            $params[] = $this->filters['approval_stage'];
        }
        
        if (!empty($this->filters['date_from'])) {
            $query .= " AND ffpe.extract_date >= ?";
            $params[] = $this->filters['date_from'];
        }
        
        if (!empty($this->filters['date_to'])) {
            $query .= " AND ffpe.extract_date <= ?";
            $params[] = $this->filters['date_to'];
        }
        
        $query .= " ORDER BY ffpe.extract_date DESC, ffpe.extract_number, wo.work_order_number";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * إضافة صف بيانات
     */
    private function addDataRow($row) {
        $this->sheet->setCellValue('A' . $this->currentRow, $row['extract_number']);
        $this->sheet->setCellValue('B' . $this->currentRow, $row['branch_name']);
        $this->sheet->setCellValue('C' . $this->currentRow, $row['extract_date']);
        $this->sheet->setCellValue('D' . $this->currentRow, $row['related_partial_extract_number']);
        $this->sheet->setCellValue('E' . $this->currentRow, $this->getApprovalStageText($row['approval_stage']));
        $this->sheet->setCellValue('F' . $this->currentRow, $row['work_order_number']);
        $this->sheet->setCellValue('G' . $this->currentRow, $row['work_order_type_code']);
        $this->sheet->setCellValue('H' . $this->currentRow, $row['extract_value']);
        $this->sheet->setCellValue('I' . $this->currentRow, $row['penalty_amount']);

        // تنسيق الصف
        $this->sheet->getStyle('A' . $this->currentRow . ':I' . $this->currentRow)
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ]);

        // تنسيق الأرقام
        $this->sheet->getStyle('H' . $this->currentRow . ':I' . $this->currentRow)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $this->currentRow++;
    }

    /**
     * ضبط عرض الأعمدة تلقائياً
     */
    private function autoSizeColumns() {
        foreach (range('A', 'M') as $col) {
            $this->sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * إخراج الملف
     */
    private function outputFile() {
        $filename = 'المستخلصات_النهائية_للجزئية_' . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * الحصول على اسم المستخدم الحالي
     */
    private function getCurrentUserName() {
        $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['full_name'] : 'غير معروف';
    }

    /**
     * الحصول على نص مرحلة الاعتماد من قاعدة البيانات
     */
    private function getApprovalStageText($stage) {
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
        $stages = [
            'draft' => 'مسودة',
            'technical_support' => 'المساندة الفنية',
            'construction' => 'الإنشاءات',
            'department_manager' => 'مدير الدائرة',
            'administration_manager' => 'مدير الإدارة',
            'taif_finance' => 'مالية الطائف',
            'disbursed' => 'مصروف'
        ];

        return $stages[$stage] ?? $stage;
    }

    /**
     * بدء تسجيل عملية التصدير
     */
    private function startExportLog() {
        $stmt = $this->db->prepare("
            INSERT INTO final_for_partial_extract_import_export_logs
            (user_id, operation_type, file_name, status, started_at)
            VALUES (?, 'export', ?, 'processing', NOW())
        ");

        $fileName = 'المستخلصات_النهائية_للجزئية_' . date('Y-m-d_H-i-s') . '.xlsx';
        $stmt->execute([$this->userId, $fileName]);

        return $this->db->lastInsertId();
    }

    /**
     * إنهاء تسجيل عملية التصدير
     */
    private function completeExportLog($logId, $success, $errorMessage = null) {
        $status = $success ? 'completed' : 'failed';

        // حساب عدد السجلات
        $data = $this->fetchData();
        $totalRecords = count($data);

        $stmt = $this->db->prepare("
            UPDATE final_for_partial_extract_import_export_logs
            SET status = ?,
                completed_at = NOW(),
                total_records = ?,
                error_message = ?
            WHERE id = ?
        ");

        $stmt->execute([$status, $totalRecords, $errorMessage, $logId]);
    }
}

