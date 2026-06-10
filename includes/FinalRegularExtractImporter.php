<?php
/**
 * مستورد المستخلصات النهائية العادية من Excel
 * Final Regular Extract Excel Importer
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class FinalRegularExtractImporter
{
    private $db;
    private $userId;
    private $logId;
    private $errors = [];
    private $processedExtracts = [];
    private $duplicateWorkOrders = [];
    private $updatedWorkOrders = [];

    public function __construct($db, $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    /**
     * معاينة استيراد المستخلصات من ملف Excel
     */
    public function previewImport($filePath, $fileName)
    {
        try {
            // قراءة الملف
            $data = $this->readExcelFile($filePath);

            if (empty($data)) {
                throw new Exception('الملف فارغ أو لا يحتوي على بيانات صحيحة');
            }

            // تحليل البيانات للمعاينة
            $previewData = $this->preparePreviewData($data);

            return [
                'success' => true,
                'data' => $previewData['data'],
                'errors' => $previewData['errors'],
                'warnings' => $previewData['warnings'],
                'calculations' => $previewData['calculations']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * قراءة ملف Excel
     */
    private function readExcelFile($filePath)
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // البحث عن صف العناوين (يبدأ من الصف 4)
            $headerRowIndex = 3; // الصف 4 (index 3)
            $headers = $rows[$headerRowIndex];

            // تنظيف العناوين
            $headers = array_map('trim', $headers);

            // قراءة البيانات من الصف 5 فصاعداً
            $data = [];
            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // تخطي الصفوف الفارغة
                if (empty(array_filter($row))) {
                    continue;
                }

                // تحويل الصف إلى مصفوفة مفهرسة
                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? '';
                }

                $data[] = $rowData;
            }

            return $data;

        } catch (Exception $e) {
            throw new Exception('خطأ في قراءة الملف: ' . $e->getMessage());
        }
    }

    /**
     * تحضير بيانات المعاينة
     */
    private function preparePreviewData($data)
    {
        $previewData = [];
        $errors = [];
        $warnings = [];
        $rowNumber = 5; // يبدأ من الصف 5

        foreach ($data as $row) {
            // تحضير بيانات الصف
            $previewRow = [
                'row_number' => $rowNumber,
                'extract_number' => trim($row['رقم المستخلص'] ?? ''),
                'branch_name' => trim($row['الفرع'] ?? ''),
                'department' => $this->normalizeDepartment(trim($row['القسم'] ?? '')),
                'extract_date' => $this->parseDate($row['تاريخ المستخلص'] ?? ''),
                'approval_stage' => trim($row['مرحلة الاعتماد'] ?? 'technical_support'),
                'work_order_number' => trim($row['رقم أمر العمل'] ?? ''),
                'work_order_type_code' => trim($row['نوع أمر العمل'] ?? ''),
                'completion_date' => trim($row['تاريخ الإنجاز'] ?? ''),
                'extract_value' => $this->parseNumber($row['قيمة المستخلص'] ?? 0),
                'penalty_amount' => $this->parseNumber($row['الغرامة'] ?? 0),
                'status' => 'success',
                'errors' => []
            ];

            // التحقق من البيانات
            $rowErrors = $this->validateRow($previewRow);

            if (!empty($rowErrors)) {
                $previewRow['status'] = 'error';
                $previewRow['errors'] = $rowErrors;
                $errors = array_merge($errors, $rowErrors);
            }

            // جلب معلومات أمر العمل (الفرع والقسم تلقائياً)
            if (!empty($previewRow['work_order_number']) && !empty($previewRow['work_order_type_code'])) {
                $workOrder = $this->findWorkOrder($previewRow['work_order_number'], $previewRow['work_order_type_code']);
                if (!$workOrder) {
                    $previewRow['status'] = 'error';
                    $previewRow['errors'][] = 'أمر العمل غير موجود';
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'extract_number' => $previewRow['extract_number'],
                        'message' => 'أمر العمل غير موجود: ' . $previewRow['work_order_number']
                    ];
                } else {
                    // جلب الفرع تلقائياً إذا لم يكن محدداً
                    if (empty($previewRow['branch_name']) && !empty($workOrder['branch_id'])) {
                        $branchStmt = $this->db->prepare("SELECT name FROM branches WHERE id = ?");
                        $branchStmt->execute([$workOrder['branch_id']]);
                        $branchData = $branchStmt->fetch(PDO::FETCH_ASSOC);
                        if ($branchData) {
                            $previewRow['branch_name'] = $branchData['name'];
                            $previewRow['branch_auto_filled'] = true;
                        }
                    }

                    // جلب القسم تلقائياً إذا لم يكن محدداً أو غير معروف
                    if (empty($previewRow['department']) && !empty($workOrder['department'])) {
                        $previewRow['department'] = $workOrder['department'];
                        $previewRow['department_auto_filled'] = true;
                    }
                }
            }

            $previewData[] = $previewRow;
            $rowNumber++;
        }

        // حساب الإجماليات لكل مستخلص
        $calculations = $this->calculateExtractTotals($previewData);

        return [
            'data' => $previewData,
            'errors' => $errors,
            'warnings' => $warnings,
            'calculations' => $calculations
        ];
    }

    /**
     * حساب إجماليات المستخلصات
     */
    private function calculateExtractTotals($previewData)
    {
        $extractTotals = [];

        foreach ($previewData as $row) {
            $extractNumber = $row['extract_number'];

            if (!isset($extractTotals[$extractNumber])) {
                $extractTotals[$extractNumber] = [
                    'extract_number' => $extractNumber,
                    'total_amount' => 0,
                    'tax_amount' => 0,
                    'total_penalty_amount' => 0,
                    'net_amount' => 0,
                    'work_orders_count' => 0
                ];
            }

            // جمع قيم أوامر العمل
            $extractTotals[$extractNumber]['total_amount'] += $row['extract_value'];
            $extractTotals[$extractNumber]['total_penalty_amount'] += $row['penalty_amount'];
            $extractTotals[$extractNumber]['work_orders_count']++;
        }

        // حساب الضريبة والصافي لكل مستخلص
        foreach ($extractTotals as &$extract) {
            // الضريبة = المبلغ الإجمالي × 15%
            $extract['tax_amount'] = $extract['total_amount'] * 0.15;

            // الصافي = المبلغ الإجمالي + الضريبة - الغرامات
            $extract['net_amount'] = $extract['total_amount'] + $extract['tax_amount'] - $extract['total_penalty_amount'];
        }

        return $extractTotals;
    }

    /**
     * التحقق من صحة صف البيانات
     */
    private function validateRow($row)
    {
        $errors = [];

        // التحقق من الحقول المطلوبة
        if (empty($row['extract_number'])) {
            $errors[] = 'رقم المستخلص مطلوب';
        }

        // الفرع والقسم سيتم جلبهما تلقائياً من أمر العمل
        if (empty($row['extract_date'])) {
            $errors[] = 'تاريخ المستخلص مطلوب';
        }

        if (empty($row['work_order_number'])) {
            $errors[] = 'رقم أمر العمل مطلوب';
        }

        if (empty($row['work_order_type_code'])) {
            $errors[] = 'نوع أمر العمل مطلوب';
        }

        if (empty($row['completion_date'])) {
            $errors[] = 'تاريخ الإنجاز مطلوب';
        }

        // تم إيقاف شرط قيمة المستخلص الموجبة للسماح بالقيم السالبة والصفرية
        // if ($row['extract_value'] <= 0) {
        //     $errors[] = 'قيمة المستخلص يجب أن تكون أكبر من صفر';
        // }

        if ($row['penalty_amount'] < 0) {
            $errors[] = 'الغرامة لا يمكن أن تكون سالبة';
        }

        return $errors;
    }

    /**
     * البحث عن أمر العمل
     */
    private function findWorkOrder($workOrderNumber, $typeCode)
    {
        $stmt = $this->db->prepare("
            SELECT wo.id, wo.branch_id, wo.department
            FROM work_orders wo
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE wo.work_order_number = ? AND wot.type_code = ?
        ");
        $stmt->execute([$workOrderNumber, $typeCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * تطبيع قيمة القسم إلى مفتاح قاعدة البيانات
     * يحوّل الأسماء العربية والإنجليزية المختلفة إلى المفاتيح الصحيحة
     */
    private function normalizeDepartment($department)
    {
        if (empty($department)) {
            return '';
        }

        // خريطة التحويل من الأسماء المختلفة إلى مفاتيح قاعدة البيانات
        $map = [
            // أسماء عربية
            'التوصيلات' => 'connections',
            'توصيلات' => 'connections',
            'المشاريع' => 'projects',
            'مشاريع' => 'projects',
            // مفاتيح إنجليزية (تُعاد كما هي)
            'connections' => 'connections',
            'projects' => 'projects',
        ];

        $normalized = $map[trim($department)] ?? '';
        return $normalized;
    }

    /**
     * البحث عن الفرع
     */
    private function findBranch($branchName)
    {
        $stmt = $this->db->prepare("
            SELECT id FROM branches WHERE name = ? AND status = 'active'
        ");
        $stmt->execute([$branchName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * تحليل التاريخ من صيغ مختلفة
     */
    private function parseDate($dateValue)
    {
        if (empty($dateValue)) {
            return '';
        }

        // إذا كان رقماً (Excel serial date)
        if (is_numeric($dateValue)) {
            $unixTimestamp = ($dateValue - 25569) * 86400;
            return date('Y-m-d', $unixTimestamp);
        }

        // محاولة تحليل التاريخ
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return $dateValue;
    }

    /**
     * تحليل الأرقام
     */
    private function parseNumber($value)
    {
        if (empty($value)) {
            return 0;
        }

        // إزالة الفواصل والمسافات
        $value = str_replace([',', ' '], '', $value);

        return floatval($value);
    }

    /**
     * تأكيد استيراد البيانات المعاينة
     */
    public function confirmImport($previewData)
    {
        try {
            $this->db->beginTransaction();

            $importedExtracts = [];
            $importedWorkOrders = 0;

            // تجميع البيانات حسب المستخلص
            $extractsData = [];
            foreach ($previewData as $row) {
                $extractNumber = $row['extract_number'];

                if (!isset($extractsData[$extractNumber])) {
                    $extractsData[$extractNumber] = [
                        'extract_number' => $extractNumber,
                        'branch_name' => $row['branch_name'],
                        'department' => $row['department'],
                        'extract_date' => $row['extract_date'],
                        'approval_stage' => $row['approval_stage'],
                        'work_orders' => []
                    ];
                }

                $extractsData[$extractNumber]['work_orders'][] = $row;
            }

            // معالجة كل مستخلص
            foreach ($extractsData as $extractData) {
                // حساب الإجماليات
                $totalAmount = 0;
                $totalPenalty = 0;

                foreach ($extractData['work_orders'] as $wo) {
                    $totalAmount += $wo['extract_value'];
                    $totalPenalty += $wo['penalty_amount'];
                }

                $taxAmount = $totalAmount * 0.15;
                $netAmount = $totalAmount + $taxAmount - $totalPenalty;

                // إنشاء أو تحديث المستخلص
                $extractId = $this->createOrUpdateExtract([
                    'extract_number' => $extractData['extract_number'],
                    'branch_name' => $extractData['branch_name'],
                    'department' => $extractData['department'],
                    'extract_date' => $extractData['extract_date'],
                    'approval_stage' => $extractData['approval_stage'],
                    'total_amount' => $totalAmount,
                    'tax_amount' => $taxAmount,
                    'total_penalty_amount' => $totalPenalty,
                    'net_amount' => $netAmount
                ]);

                // إضافة أوامر العمل
                foreach ($extractData['work_orders'] as $wo) {
                    $this->addWorkOrderToExtract($extractId, $wo);
                    $importedWorkOrders++;
                }

                // تحديث حالة أوامر العمل إذا كانت المرحلة "مصروف"
                $approvalStageKey = $this->getApprovalStageKey($extractData['approval_stage']);
                $this->updateWorkOrdersStatusIfDisbursed($extractId, $approvalStageKey);

                $importedExtracts[] = $extractData['extract_number'];
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'تم استيراد البيانات بنجاح',
                'stats' => [
                    'extracts' => count($importedExtracts),
                    'work_orders' => $importedWorkOrders
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * إنشاء أو تحديث المستخلص
     */
    private function createOrUpdateExtract($row)
    {
        // البحث عن المستخلص
        $stmt = $this->db->prepare("
            SELECT id FROM final_regular_extracts WHERE extract_number = ?
        ");
        $stmt->execute([$row['extract_number']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // جلب معرف الفرع
        $branch = $this->findBranch($row['branch_name']);
        if (!$branch) {
            throw new Exception('الفرع غير موجود: ' . $row['branch_name']);
        }
        $branchId = $branch['id'];

        if ($existing) {
            // تحديث المستخلص الموجود
            $stmt = $this->db->prepare("
                UPDATE final_regular_extracts
                SET branch_id = ?,
                    department = ?,
                    extract_date = ?,
                    total_amount = ?,
                    tax_amount = ?,
                    total_penalty_amount = ?,
                    net_amount = ?,
                    approval_stage = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $branchId,
                $row['department'],
                $row['extract_date'],
                $row['total_amount'],
                $row['tax_amount'],
                $row['total_penalty_amount'],
                $row['net_amount'],
                $this->getApprovalStageKey($row['approval_stage']),
                $existing['id']
            ]);

            // حذف أوامر العمل القديمة
            $stmt = $this->db->prepare("
                DELETE FROM final_regular_extract_work_orders
                WHERE final_regular_extract_id = ?
            ");
            $stmt->execute([$existing['id']]);

            $this->updatedWorkOrders[] = $row['extract_number'];

            return $existing['id'];

        } else {
            // إنشاء مستخلص جديد
            $stmt = $this->db->prepare("
                INSERT INTO final_regular_extracts (
                    extract_number, branch_id, department, extract_date,
                    total_amount, tax_amount, total_penalty_amount, net_amount,
                    approval_stage, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $row['extract_number'],
                $branchId,
                $row['department'],
                $row['extract_date'],
                $row['total_amount'],
                $row['tax_amount'],
                $row['total_penalty_amount'],
                $row['net_amount'],
                $this->getApprovalStageKey($row['approval_stage']),
                $this->userId
            ]);

            $this->processedExtracts[] = $row['extract_number'];

            return $this->db->lastInsertId();
        }
    }

    /**
     * إضافة أمر عمل للمستخلص
     */
    private function addWorkOrderToExtract($extractId, $row)
    {
        // البحث عن أمر العمل
        $workOrder = $this->findWorkOrder($row['work_order_number'], $row['work_order_type_code']);
        if (!$workOrder) {
            throw new Exception('أمر العمل غير موجود: ' . $row['work_order_number']);
        }

        // إضافة أمر العمل للمستخلص
        $stmt = $this->db->prepare("
            INSERT INTO final_regular_extract_work_orders (
                final_regular_extract_id, work_order_id, completion_date, extract_value, penalty_amount, added_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $extractId,
            $workOrder['id'],
            $row['completion_date'],
            $row['extract_value'],
            $row['penalty_amount'],
            $_SESSION['user_id']
        ]);
    }

    /**
     * تحديث حالة أوامر العمل إلى "مكتمل" إذا كانت مرحلة الاعتماد "مصروف"
     */
    private function updateWorkOrdersStatusIfDisbursed($extractId, $approvalStage)
    {
        // التحقق من أن المرحلة هي "مصروف"
        if ($approvalStage !== 'disbursed') {
            return;
        }

        try {
            // البحث عن معرف الجهة "منتهى"
            $stmt = $this->db->prepare("SELECT id FROM current_entities WHERE name = 'منتهى' LIMIT 1");
            $stmt->execute();
            $finishedEntity = $stmt->fetch(PDO::FETCH_ASSOC);
            $finishedEntityId = $finishedEntity ? $finishedEntity['id'] : null;

            // جلب جميع أوامر العمل المرتبطة بهذا المستخلص
            $stmt = $this->db->prepare("
                SELECT DISTINCT wo.id, wo.work_order_number, wo.status, wo.current_entity_id
                FROM final_regular_extract_work_orders frewo
                INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
                WHERE frewo.final_regular_extract_id = ?
            ");
            $stmt->execute([$extractId]);
            $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // تحديث حالة كل أمر عمل إلى "completed" والجهة الحالية إلى "منتهى"
            $updatedCount = 0;
            foreach ($workOrders as $wo) {
                $needsUpdate = false;
                $updates = [];
                $params = [];

                // التحقق من الحالة
                if ($wo['status'] !== 'completed') {
                    $updates[] = "status = 'completed'";
                    $needsUpdate = true;
                }

                // التحقق من الجهة الحالية
                if ($finishedEntityId && $wo['current_entity_id'] != $finishedEntityId) {
                    $updates[] = "current_entity_id = ?";
                    $params[] = $finishedEntityId;
                    $needsUpdate = true;
                }

                // تنفيذ التحديث إذا كان هناك تغييرات
                if ($needsUpdate) {
                    $updates[] = "updated_at = NOW()";
                    $updateQuery = "UPDATE work_orders SET " . implode(', ', $updates) . " WHERE id = ?";
                    $params[] = $wo['id'];

                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->execute($params);
                    $updatedCount++;
                }
            }

            if ($updatedCount > 0) {
                error_log("Import: Updated $updatedCount work orders to 'completed' status for extract $extractId");
            }
        } catch (Exception $e) {
            error_log("Error updating work orders status: " . $e->getMessage());
            // لا نرمي الخطأ لأن هذا ليس حرجاً للاستيراد
        }
    }

    /**
     * تحويل اسم مرحلة الاعتماد إلى مفتاح من قاعدة البيانات
     */
    private function getApprovalStageKey($stageName)
    {
        // محاولة جلب مفتاح المرحلة من قاعدة البيانات
        try {
            $stmt = $this->db->prepare("
                SELECT stage_key
                FROM approval_stages
                WHERE stage_name = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$stageName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['stage_key'];
            }
        } catch (Exception $e) {
            // في حالة فشل الاستعلام، استخدم القيم الافتراضية
            error_log("Error fetching approval stage key: " . $e->getMessage());
        }

        // القيم الافتراضية كـ fallback
        $stages = [
            'المساندة الفنية' => 'technical_support',
            'الإنشاءات' => 'construction',
            'مدير الدائرة' => 'department_manager',
            'مدير الإدارة' => 'administration_manager',
            'مالية الطائف' => 'taif_finance',
            'مصروف' => 'disbursed'
        ];

        return $stages[$stageName] ?? 'technical_support';
    }

    public function getDuplicates()
    {
        return $this->duplicateWorkOrders;
    }
}

