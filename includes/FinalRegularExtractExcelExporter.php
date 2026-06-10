<?php
/**
 * مصدّر المستخلصات النهائية العادية إلى Excel
 * Final Regular Extract Excel Exporter
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FinalRegularExtractExcelExporter {
    private $db;
    private $userId;
    private $filters;
    
    public function __construct($db, $userId, $filters = []) {
        $this->db = $db;
        $this->userId = $userId;
        $this->filters = $filters;
    }
    
    /**
     * تصدير المستخلصات إلى Excel
     */
    public function export() {
        try {
            // جلب البيانات
            $data = $this->fetchData();
            
            if (empty($data)) {
                throw new Exception('لا توجد بيانات للتصدير');
            }
            
            // إنشاء ملف Excel
            $spreadsheet = $this->createSpreadsheet($data);
            
            // حفظ الملف
            $fileName = 'final_regular_extracts_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = __DIR__ . '/../public/uploads/exports/' . $fileName;
            
            // التأكد من وجود المجلد
            $uploadDir = dirname($filePath);
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            
            // تسجيل العملية
            $this->logExport($fileName, count($data));
            
            // تحميل الملف
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            
            readfile($filePath);
            
            // حذف الملف بعد التحميل
            unlink($filePath);
            
        } catch (Exception $e) {
            throw new Exception('خطأ في التصدير: ' . $e->getMessage());
        }
    }
    
    /**
     * جلب البيانات من قاعدة البيانات
     */
    private function fetchData() {
        $query = "
            SELECT
                fre.extract_number,
                b.name as branch_name,
                fre.department,
                fre.extract_date,
                fre.approval_stage,
                wo.work_order_number,
                wot.type_code,
                frewo.completion_date,
                frewo.extract_value,
                frewo.penalty_amount
            FROM final_regular_extracts fre
            LEFT JOIN branches b ON fre.branch_id = b.id
            LEFT JOIN final_regular_extract_work_orders frewo ON fre.id = frewo.final_regular_extract_id
            LEFT JOIN work_orders wo ON frewo.work_order_id = wo.id
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($this->filters['branch_id'])) {
            $query .= " AND fre.branch_id = ?";
            $params[] = $this->filters['branch_id'];
        }
        
        if (!empty($this->filters['department'])) {
            $query .= " AND fre.department = ?";
            $params[] = $this->filters['department'];
        }
        
        if (!empty($this->filters['approval_stage'])) {
            $query .= " AND fre.approval_stage = ?";
            $params[] = $this->filters['approval_stage'];
        }
        
        if (!empty($this->filters['date_from'])) {
            $query .= " AND fre.extract_date >= ?";
            $params[] = $this->filters['date_from'];
        }
        
        if (!empty($this->filters['date_to'])) {
            $query .= " AND fre.extract_date <= ?";
            $params[] = $this->filters['date_to'];
        }
        
        $query .= " ORDER BY fre.extract_number, wo.work_order_number";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * إنشاء ملف Excel
     */
    private function createSpreadsheet($data) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // تعيين اتجاه الصفحة من اليمين لليسار
        $sheet->setRightToLeft(true);
        
        // عنوان الملف
        $sheet->setCellValue('A1', 'نظام إتقان - المستخلصات النهائية العادية');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // تاريخ التصدير
        $sheet->setCellValue('A2', 'تاريخ التصدير: ' . date('Y-m-d H:i:s'));
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
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
        
        // البيانات (تبدأ من الصف 5)
        $row = 5;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['extract_number']);
            $sheet->setCellValue('B' . $row, $item['branch_name']);
            $sheet->setCellValue('C' . $row, $this->getDepartmentName($item['department']));
            $sheet->setCellValue('D' . $row, $item['extract_date']);
            $sheet->setCellValue('E' . $row, $this->getApprovalStageName($item['approval_stage']));
            $sheet->setCellValue('F' . $row, $item['work_order_number']);
            $sheet->setCellValue('G' . $row, $item['type_code']);
            $sheet->setCellValue('H' . $row, $item['completion_date']);
            $sheet->setCellValue('I' . $row, $item['extract_value']);
            $sheet->setCellValue('J' . $row, $item['penalty_amount']);

            // تنسيق الأرقام
            $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            $row++;
        }
        
        // تنسيق البيانات
        $lastRow = $row - 1;
        $sheet->getStyle('A5:J' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // ضبط عرض الأعمدة
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(15);
        
        return $spreadsheet;
    }
    
    /**
     * الحصول على اسم القسم
     */
    private function getDepartmentName($department) {
        $departments = [
            'connections' => 'التوصيلات',
            'projects' => 'المشاريع'
        ];
        
        return $departments[$department] ?? $department;
    }
    
    /**
     * الحصول على اسم مرحلة الاعتماد من قاعدة البيانات
     */
    private function getApprovalStageName($stage) {
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
     * تسجيل عملية التصدير
     */
    private function logExport($fileName, $recordCount) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO final_regular_extract_import_export_logs (
                    user_id, operation_type, file_name, records_count, 
                    status, started_at, completed_at
                ) VALUES (?, 'export', ?, ?, 'success', NOW(), NOW())
            ");
            
            $stmt->execute([$this->userId, $fileName, $recordCount]);
        } catch (Exception $e) {
            // تجاهل أخطاء التسجيل
        }
    }
}

