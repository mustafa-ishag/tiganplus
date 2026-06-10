<?php
/**
 * مصدر أوامر العمل إلى Excel
 * Work Order Excel Exporter
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class WorkOrderExcelExporter {
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
     * تصدير أوامر العمل
     */
    public function export() {
        try {
            // بدء تسجيل العملية
            $logId = $this->startExportLog();

            // تنظيف أي output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            $this->spreadsheet = new Spreadsheet();
            $this->sheet = $this->spreadsheet->getActiveSheet();

            $this->setupPage();
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

            // في حالة الخطأ، عرض رسالة واضحة
            error_log("WorkOrderExcelExporter Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            // إذا لم يتم إرسال headers بعد، أعد التوجيه
            if (!headers_sent()) {
                $_SESSION['error'] = 'خطأ في تصدير البيانات: ' . $e->getMessage();
                header('Location: index.php');
                exit();
            } else {
                echo "خطأ في تصدير البيانات: " . $e->getMessage();
                exit();
            }
        }
    }
    
    /**
     * إعداد الصفحة
     */
    private function setupPage() {
        $this->sheet->setTitle('أوامر العمل');
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
     * تحويل رقم العمود إلى حرف (A, B, C, ..., Z, AA, AB, ...)
     */
    private function getColumnLetter($columnIndex) {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
    }
    
    /**
     * إنشاء جدول البيانات
     */
    private function generateDataTable() {
        error_log("WorkOrderExcelExporter: Starting generateDataTable");
        $headerRow = $this->currentRow; // حفظ رقم صف العناوين

        // رأس الجدول - يبدأ من الصف الأول
        $headers = [
            'رقم أمر العمل',
            'كود نوع الأمر',
            'القسم',
            'الجهة الحالية',
            'الفرع',
            'كود الفرع',
            'الموقع',
            'تاريخ التكليف',
            'تاريخ الاستلام',
            'القيمة المقدرة',
            'القيمة الفعلية',
            'حالة الصرف'
        ];

        // إضافة أعمدة النماذج إذا كانت مطلوبة
        if (($this->filters['include_attachments'] ?? '1') === '1') {
            $headers = array_merge($headers, [
                'نموذج الحفر الدقيق',
                'نموذج الكشط',
                'نموذج التخريد',
                'نموذج F1',
                'شهادة الإنجاز',
                'تاريخ ارفاق شهادة الإنجاز',
                'تأكيد شهادة الإنجاز',
                'تاريخ تأكيد شهادة الإنجاز'
            ]);
        }

        // إضافة الحالة والملاحظات
        $headers = array_merge($headers, [
            'الحالة',
            'الملاحظات'
        ]);

        // إضافة أعمدة المستخلصات إذا كانت مطلوبة
        if (($this->filters['include_extracts'] ?? '1') === '1') {
            $headers = array_merge($headers, [
                'رقم المستخلص',
                'قيمة أمر العمل في المستخلص الجزئي',
                'مرحلة اعتماد المستخلص الجزئي',
                'قيمة أمر العمل في المستخلص النهائي العادي',
                'مرحلة اعتماد المستخلص النهائي العادي',
                'قيمة أمر العمل في المستخلص النهائي للجزئية',
                'مرحلة اعتماد المستخلص النهائي للجزئية'
            ]);
        }

        // كتابة العناوين
        error_log("WorkOrderExcelExporter: Writing " . count($headers) . " headers");
        $colIndex = 1;
        foreach ($headers as $header) {
            $col = $this->getColumnLetter($colIndex);
            $this->sheet->setCellValue($col . $this->currentRow, $header);
            $colIndex++;
        }

        // تنسيق رأس الجدول
        $lastCol = $this->getColumnLetter(count($headers));
        error_log("WorkOrderExcelExporter: Last column is " . $lastCol);
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastCol . $this->currentRow)
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
            $this->insertDataRow($row, $index, count($headers));
        }

        // تفعيل الفلتر التلقائي على جميع البيانات (من صف العناوين إلى آخر صف)
        $lastDataRow = $this->currentRow - 1; // آخر صف تم إدراجه
        if ($lastDataRow >= $headerRow) {
            $this->sheet->setAutoFilter('A' . $headerRow . ':' . $lastCol . $lastDataRow);
        }
    }
    
    /**
     * جلب البيانات من قاعدة البيانات
     */
    private function fetchData() {
        try {
            $query = "
                SELECT wo.*,
                       wot.type_code as work_order_type_code,
                       b.name as branch_name,
                       b.code as branch_code,
                       ce.name as current_entity_name,
                       -- النماذج المرفقة
                       woa_exc.status as excavation_form_status,
                       woa_drill.status as precise_drilling_form_status,
                       woa_demo.status as demolition_form_status,
                       woa_f1.status as f1_form_status,
                       woa_comp.status as completion_certificate_status,
                       woa_comp.completion_certificate_confirmation,
                       woa_comp.certificate_attached_date,
                       woa_comp.certificate_confirmed_date,
                       -- المستخلصات (قيمة أمر العمل في المستخلص)
                       COALESCE(pe.extract_number, fre.extract_number, ffpe.extract_number) as extract_number,
                       pe.approval_stage as partial_extract_approval_stage,
                       fre.approval_stage as final_regular_extract_approval_stage,
                       ffpe.approval_stage as final_for_partial_extract_approval_stage,
                       pewo.extract_value as partial_extract_value,
                       frewo.extract_value as final_regular_extract_value,
                       ffpewo.extract_value as final_for_partial_extract_value
                FROM work_orders wo
                LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
                LEFT JOIN branches b ON wo.branch_id = b.id
                LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
                -- ربط مع النماذج المرفقة
                LEFT JOIN work_order_attachments woa_exc ON wo.id = woa_exc.work_order_id AND woa_exc.form_type = 'excavation_form'
                LEFT JOIN work_order_attachments woa_drill ON wo.id = woa_drill.work_order_id AND woa_drill.form_type = 'precise_drilling_form'
                LEFT JOIN work_order_attachments woa_demo ON wo.id = woa_demo.work_order_id AND woa_demo.form_type = 'demolition_form'
                LEFT JOIN work_order_attachments woa_f1 ON wo.id = woa_f1.work_order_id AND woa_f1.form_type = 'f1_form'
                LEFT JOIN work_order_attachments woa_comp ON wo.id = woa_comp.work_order_id AND woa_comp.form_type = 'completion_certificate'
                -- ربط مع المستخلصات الجزئية
                LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
                LEFT JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
                -- ربط مع المستخلصات النهائية العادية
                LEFT JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
                LEFT JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
                -- ربط مع المستخلصات النهائية للجزئية
                LEFT JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
                LEFT JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
            ";

            // إضافة شروط التصفية
            $conditions = [];
            $params = [];

            // فلتر الحالة
            if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
                $conditions[] = "wo.status = ?";
                $params[] = $this->filters['status'];
            }

            // فلتر القسم
            if (!empty($this->filters['department']) && $this->filters['department'] !== 'all') {
                $conditions[] = "wo.department = ?";
                $params[] = $this->filters['department'];
            }

            // فلتر الفرع
            if (!empty($this->filters['branch_id']) && $this->filters['branch_id'] !== 'all') {
                $conditions[] = "wo.branch_id = ?";
                $params[] = $this->filters['branch_id'];
            }

            // فلتر الجهة الحالية
            if (!empty($this->filters['current_entity'])) {
                $conditions[] = "wo.current_entity_id = ?";
                $params[] = $this->filters['current_entity'];
            }

            // فلتر التاريخ من
            if (!empty($this->filters['date_from'])) {
                $conditions[] = "wo.assignment_date >= ?";
                $params[] = $this->filters['date_from'];
            }

            // فلتر التاريخ إلى
            if (!empty($this->filters['date_to'])) {
                $conditions[] = "wo.assignment_date <= ?";
                $params[] = $this->filters['date_to'];
            }

            // فلتر شهادة الإنجاز
            if (!empty($this->filters['completion_certificate']) && is_array($this->filters['completion_certificate'])) {
                $placeholders = str_repeat('?,', count($this->filters['completion_certificate']) - 1) . '?';
                $conditions[] = "woa_comp.status IN ($placeholders)";
                $params = array_merge($params, $this->filters['completion_certificate']);
            }

            // فلتر تأكيد الشهادة
            if (!empty($this->filters['certificate_confirmation']) && is_array($this->filters['certificate_confirmation'])) {
                $placeholders = str_repeat('?,', count($this->filters['certificate_confirmation']) - 1) . '?';
                $conditions[] = "woa_comp.completion_certificate_confirmation IN ($placeholders)";
                $params = array_merge($params, $this->filters['certificate_confirmation']);
            }

            // فلتر حالة الصرف
            if (!empty($this->filters['disbursement_status']) && is_array($this->filters['disbursement_status'])) {
                $placeholders = str_repeat('?,', count($this->filters['disbursement_status']) - 1) . '?';
                $conditions[] = "wo.disbursement_status IN ($placeholders)";
                $params = array_merge($params, $this->filters['disbursement_status']);
            }

            // فلتر الحفر الدقيق
            if (!empty($this->filters['precise_drilling']) && is_array($this->filters['precise_drilling'])) {
                $placeholders = str_repeat('?,', count($this->filters['precise_drilling']) - 1) . '?';
                $conditions[] = "woa_drill.status IN ($placeholders)";
                $params = array_merge($params, $this->filters['precise_drilling']);
            }

            // فلتر الكشط
            if (!empty($this->filters['excavation']) && is_array($this->filters['excavation'])) {
                $placeholders = str_repeat('?,', count($this->filters['excavation']) - 1) . '?';
                $conditions[] = "woa_exc.status IN ($placeholders)";
                $params = array_merge($params, $this->filters['excavation']);
            }

            // فلتر التخريد
            if (!empty($this->filters['demolition']) && is_array($this->filters['demolition'])) {
                $placeholders = str_repeat('?,', count($this->filters['demolition']) - 1) . '?';
                $conditions[] = "woa_demo.status IN ($placeholders)";
                $params = array_merge($params, $this->filters['demolition']);
            }

            // فلتر F1
            if (!empty($this->filters['f1_form']) && is_array($this->filters['f1_form'])) {
                $placeholders = str_repeat('?,', count($this->filters['f1_form']) - 1) . '?';
                $conditions[] = "woa_f1.status IN ($placeholders)";
                $params = array_merge($params, $this->filters['f1_form']);
            }

            // فلتر استلام الأصول
            if (!empty($this->filters['assets_receipt']) && is_array($this->filters['assets_receipt'])) {
                // ملاحظة: قد تحتاج إلى إضافة LEFT JOIN لجدول استلام الأصول إذا لم يكن موجوداً
                // هذا مثال افتراضي
                $placeholders = str_repeat('?,', count($this->filters['assets_receipt']) - 1) . '?';
                $conditions[] = "wo.assets_receipt_status IN ($placeholders)";
                $params = array_merge($params, $this->filters['assets_receipt']);
            }

            // الفلتر السريع
            if (!empty($this->filters['quick_filter'])) {
                switch ($this->filters['quick_filter']) {
                    case 'favorites':
                        $conditions[] = "wo.is_favorite = 1";
                        break;
                    case 'pending_completion':
                        $conditions[] = "(woa_comp.status IS NULL OR woa_comp.status = 'pending')";
                        break;
                    case 'pending_confirmation':
                        $conditions[] = "woa_comp.completion_certificate_confirmation = 'pending'";
                        break;
                    case 'pending_disbursement':
                        $conditions[] = "wo.disbursement_status = 'pending'";
                        break;
                    case 'no_extract':
                        $conditions[] = "pe.id IS NULL AND fre.id IS NULL AND ffpe.id IS NULL";
                        break;
                }
            }

            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            $query .= " ORDER BY wo.id DESC LIMIT 5000";

            error_log("WorkOrderExcelExporter: Executing query with " . count($params) . " parameters");

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("WorkOrderExcelExporter: Fetched " . count($data) . " records");

            return $data;
        } catch (Exception $e) {
            error_log("WorkOrderExcelExporter fetchData Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * إدراج صف بيانات
     */
    private function insertDataRow($row, $index, $totalColumns) {
        // إعداد البيانات الأساسية
        $col = 'A';

        // رقم أمر العمل (رقم صحيح بدون فواصل عشرية)
        if (is_numeric($row['work_order_number'])) {
            $this->sheet->setCellValueExplicit(
                $col . $this->currentRow,
                intval($row['work_order_number']),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
            );
        } else {
            $this->sheet->setCellValue($col . $this->currentRow, $row['work_order_number']);
        }
        $col++;

        // كود نوع الأمر
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['work_order_type_code'] ?? '');

        // القسم
        $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateDepartment($row['department']));

        // الجهة الحالية
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['current_entity_name'] ?? '');

        // الفرع
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['branch_name'] ?? '');

        // كود الفرع
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['branch_code'] ?? '');

        // الموقع
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['location'] ?? '');

        // تاريخ التكليف
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['assignment_date'] ?? '');

        // تاريخ الاستلام
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['receipt_date'] ?? '');

        // القيمة المقدرة (رقم)
        $this->sheet->setCellValueExplicit(
            $col++ . $this->currentRow,
            floatval($row['estimated_value'] ?? 0),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
        );

        // القيمة الفعلية (رقم)
        $this->sheet->setCellValueExplicit(
            $col++ . $this->currentRow,
            floatval($row['actual_value'] ?? 0),
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
        );

        // حالة الصرف
        $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateDisbursementStatus($row['disbursement_status'] ?? 'none'));

        // إضافة بيانات النماذج إذا كانت مطلوبة
        if (($this->filters['include_attachments'] ?? '1') === '1') {
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateAttachmentStatus($row['precise_drilling_form_status'] ?? 'not_attached'));
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateAttachmentStatus($row['excavation_form_status'] ?? 'not_attached'));
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateAttachmentStatus($row['demolition_form_status'] ?? 'not_attached'));
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateAttachmentStatus($row['f1_form_status'] ?? 'not_attached'));
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateAttachmentStatus($row['completion_certificate_status'] ?? 'not_attached'));
            $this->sheet->setCellValue($col++ . $this->currentRow, $row['certificate_attached_date'] ?? '');
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateConfirmationStatus($row['completion_certificate_confirmation'] ?? 'empty'));
            $this->sheet->setCellValue($col++ . $this->currentRow, $row['certificate_confirmed_date'] ?? '');
        }

        // إضافة الحالة والملاحظات
        $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateStatus($row['status'] ?? 'active'));
        $this->sheet->setCellValue($col++ . $this->currentRow, $row['notes'] ?? '');

        // إضافة بيانات المستخلصات إذا كانت مطلوبة
        if (($this->filters['include_extracts'] ?? '1') === '1') {
            // رقم المستخلص (رقم صحيح بدون فواصل عشرية)
            $extractNumberCol = $col;
            if (!empty($row['extract_number']) && is_numeric($row['extract_number'])) {
                $this->sheet->setCellValueExplicit(
                    $col . $this->currentRow,
                    intval($row['extract_number']),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
                // تطبيق تنسيق رقم صحيح بدون فواصل عشرية
                $this->sheet->getStyle($col . $this->currentRow)->getNumberFormat()
                    ->setFormatCode('0');
            } else {
                $this->sheet->setCellValue($col . $this->currentRow, $row['extract_number'] ?? '');
            }
            $col++;

            // قيمة أمر العمل في المستخلص الجزئي (رقم)
            if (!empty($row['partial_extract_value'])) {
                $this->sheet->setCellValueExplicit(
                    $col . $this->currentRow,
                    floatval($row['partial_extract_value']),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
            }
            $col++;

            // مرحلة اعتماد المستخلص الجزئي
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateApprovalStage($row['partial_extract_approval_stage'] ?? ''));

            // قيمة أمر العمل في المستخلص النهائي العادي (رقم)
            if (!empty($row['final_regular_extract_value'])) {
                $this->sheet->setCellValueExplicit(
                    $col . $this->currentRow,
                    floatval($row['final_regular_extract_value']),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
            }
            $col++;

            // مرحلة اعتماد المستخلص النهائي العادي
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateApprovalStage($row['final_regular_extract_approval_stage'] ?? ''));

            // قيمة أمر العمل في المستخلص النهائي للجزئية (رقم)
            if (!empty($row['final_for_partial_extract_value'])) {
                $this->sheet->setCellValueExplicit(
                    $col . $this->currentRow,
                    floatval($row['final_for_partial_extract_value']),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                );
            }
            $col++;

            // مرحلة اعتماد المستخلص النهائي للجزئية
            $this->sheet->setCellValue($col++ . $this->currentRow, $this->translateApprovalStage($row['final_for_partial_extract_approval_stage'] ?? ''));
        }

        // تنسيق الصف
        $fillColor = ($index % 2 == 0) ? 'FFFFFF' : $this->lightGray;
        $lastCol = $this->getColumnLetter($totalColumns);
        $this->sheet->getStyle('A' . $this->currentRow . ':' . $lastCol . $this->currentRow)
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

        // تنسيق رقم أمر العمل (عمود A) - رقم صحيح بدون فواصل
        $this->sheet->getStyle('A' . $this->currentRow)->getNumberFormat()
            ->setFormatCode('0');

        // تنسيق الأعمدة الرقمية (القيمة المقدرة والفعلية)
        $this->sheet->getStyle('J' . $this->currentRow)->getNumberFormat()
            ->setFormatCode('#,##0.00');
        $this->sheet->getStyle('K' . $this->currentRow)->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // تنسيق أعمدة المستخلصات إذا كانت موجودة
        if (($this->filters['include_extracts'] ?? '1') === '1') {
            // حساب عمود رقم المستخلص بناءً على الأعمدة السابقة
            // A-L: 12 عمود أساسي
            // M-R: 6 أعمدة نماذج (إذا كانت مضمنة)
            // S أو M: الحالة
            // T أو N: الملاحظات
            // U أو O: رقم المستخلص
            // V أو P: قيمة المستخلص الجزئي
            // W أو Q: مرحلة اعتماد المستخلص الجزئي
            // X أو R: قيمة المستخلص النهائي العادي
            // Y أو S: مرحلة اعتماد المستخلص النهائي العادي
            // Z أو T: قيمة المستخلص النهائي للجزئية
            // AA أو U: مرحلة اعتماد المستخلص النهائي للجزئية
            $extractNumberCol = ($this->filters['include_attachments'] ?? '1') === '1' ? 'U' : 'O';

            // رقم المستخلص - رقم صحيح بدون فواصل عشرية
            $this->sheet->getStyle($extractNumberCol . $this->currentRow)->getNumberFormat()
                ->setFormatCode('0');  // 0 = رقم صحيح بدون فواصل

            // تنسيق قيم المستخلصات (مبالغ بفواصل عشرية)
            // قيمة المستخلص الجزئي
            $this->sheet->getStyle(chr(ord($extractNumberCol) + 1) . $this->currentRow)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            // مرحلة اعتماد المستخلص الجزئي - نص عادي

            // قيمة المستخلص النهائي العادي
            $this->sheet->getStyle(chr(ord($extractNumberCol) + 3) . $this->currentRow)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            // مرحلة اعتماد المستخلص النهائي العادي - نص عادي

            // قيمة المستخلص النهائي للجزئية
            $this->sheet->getStyle(chr(ord($extractNumberCol) + 5) . $this->currentRow)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            // مرحلة اعتماد المستخلص النهائي للجزئية - نص عادي
        }

        $this->sheet->getRowDimension($this->currentRow)->setRowHeight(20);
        $this->currentRow++;
    }

    /**
     * ضبط عرض الأعمدة
     */
    private function autoSizeColumns() {
        $columnWidths = [
            'A' => 15, // رقم أمر العمل
            'B' => 12, // كود نوع الأمر
            'C' => 12, // القسم
            'D' => 20, // الجهة الحالية
            'E' => 20, // الفرع
            'F' => 12, // كود الفرع
            'G' => 25, // الموقع
            'H' => 12, // تاريخ التكليف
            'I' => 12, // تاريخ الاستلام
            'J' => 15, // القيمة المقدرة
            'K' => 15, // القيمة الفعلية
            'L' => 15  // حالة الصرف
        ];

        $currentCol = 'M';

        // إضافة عرض أعمدة النماذج إذا كانت مطلوبة
        if (($this->filters['include_attachments'] ?? '1') === '1') {
            for ($i = 0; $i < 8; $i++) {
                $columnWidths[$currentCol] = 15;
                $currentCol++;
            }
        }

        // إضافة عرض أعمدة الحالة والملاحظات
        $columnWidths[$currentCol] = 12; // الحالة
        $currentCol++;
        $columnWidths[$currentCol] = 30; // الملاحظات
        $currentCol++;

        // إضافة عرض أعمدة المستخلصات إذا كانت مطلوبة
        if (($this->filters['include_extracts'] ?? '1') === '1') {
            $columnWidths[$currentCol] = 15; // رقم المستخلص
            $currentCol++;

            // المستخلص الجزئي
            $columnWidths[$currentCol] = 18; // قيمة المستخلص الجزئي
            $currentCol++;
            $columnWidths[$currentCol] = 20; // مرحلة اعتماد المستخلص الجزئي
            $currentCol++;

            // المستخلص النهائي العادي
            $columnWidths[$currentCol] = 18; // قيمة المستخلص النهائي العادي
            $currentCol++;
            $columnWidths[$currentCol] = 20; // مرحلة اعتماد المستخلص النهائي العادي
            $currentCol++;

            // المستخلص النهائي للجزئية
            $columnWidths[$currentCol] = 18; // قيمة المستخلص النهائي للجزئية
            $currentCol++;
            $columnWidths[$currentCol] = 20; // مرحلة اعتماد المستخلص النهائي للجزئية
            $currentCol++;
        }

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
     * ترجمة حالة الصرف
     */
    private function translateDisbursementStatus($status) {
        $statuses = [
            'none' => 'لا يوجد',
            'completed' => 'مكتمل',
            'disbursement' => 'صرف',
            'return' => 'إرجاع',
            'disbursement_return_completed' => 'صرف وإرجاع مكتمل'
        ];
        return $statuses[$status] ?? $status;
    }

    /**
     * ترجمة الحالة
     */
    private function translateStatus($status) {
        $statuses = [
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي'
        ];
        return $statuses[$status] ?? $status;
    }

    /**
     * ترجمة حالة المرفق
     */
    private function translateAttachmentStatus($status) {
        $statuses = [
            'attached' => 'مرفق',
            'not_attached' => 'غير مرفق',
            'not_applicable' => 'لا ينطبق'
        ];
        return $statuses[$status] ?? $status;
    }

    /**
     * ترجمة حالة التأكيد
     */
    private function translateConfirmationStatus($status) {
        $statuses = [
            'empty' => 'فارغ',
            'confirmed' => 'مؤكد',
            'accepted' => 'مقبول',
            'rejected' => 'مرفوض'
        ];
        return $statuses[$status] ?? $status;
    }

    /**
     * ترجمة مرحلة الاعتماد من قاعدة البيانات
     */
    private function translateApprovalStage($stage) {
        // إذا كانت المرحلة فارغة
        if (empty($stage)) {
            return '';
        }

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

        // القيم الافتراضية في حالة عدم وجود الجدول أو المرحلة
        $defaultStages = [
            'technical_support' => 'الدعم الفني',
            'construction' => 'الإنشاءات',
            'department_manager' => 'مدير القسم',
            'administration_manager' => 'مدير الإدارة',
            'taif_finance' => 'مالية الطائف',
            'finance' => 'المالية',
            'disbursed' => 'تم الصرف',
            'draft' => 'مسودة',
            'submitted' => 'مقدمة',
            'under_review' => 'قيد المراجعة',
            'approved' => 'معتمدة',
            'rejected' => 'مرفوضة'
        ];

        return $defaultStages[$stage] ?? $stage;
    }

    /**
     * الحصول على اسم المستخدم الحالي
     */
    private function getCurrentUserName() {
        $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['full_name'] ?? 'غير معروف';
    }

    /**
     * بدء تسجيل عملية التصدير
     */
    private function startExportLog() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO work_order_import_export_logs
                (operation_type, file_name, file_format, operation_status, export_filters, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $filename = 'work_orders_export_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filters = json_encode($this->filters, JSON_UNESCAPED_UNICODE);

            $stmt->execute(['export', $filename, 'xlsx', 'processing', $filters, $this->userId]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            // إذا فشل التسجيل، نتجاهل الخطأ ونكمل التصدير
            error_log("Failed to log export operation: " . $e->getMessage());
            return null;
        }
    }

    /**
     * إنهاء تسجيل عملية التصدير
     */
    private function completeExportLog($logId, $success, $errorMessage = null) {
        if ($logId === null) {
            return; // لا يوجد سجل للتحديث
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE work_order_import_export_logs
                SET operation_status = ?,
                    error_message = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");

            $status = $success ? 'completed' : 'failed';
            $stmt->execute([$status, $errorMessage, $logId]);
        } catch (Exception $e) {
            // إذا فشل التحديث، نتجاهل الخطأ
            error_log("Failed to update export log: " . $e->getMessage());
        }
    }

    /**
     * إخراج الملف
     */
    private function outputFile() {
        $filename = 'work_orders_export_' . date('Y-m-d_H-i-s') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');

        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');
        exit();
    }
}
?>

