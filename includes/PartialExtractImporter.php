<?php
/**
 * مستورد المستخلصات الجزئية من Excel
 * Partial Extract Excel Importer
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class PartialExtractImporter {
    private $db;
    private $userId;
    private $logId;
    private $errors = [];
    private $processedExtracts = [];
    private $duplicateWorkOrders = [];
    private $updatedWorkOrders = [];
    
    public function __construct($db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
    }
    
    /**
     * معاينة استيراد المستخلصات من ملف Excel
     */
    public function previewImport($filePath, $fileName) {
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
     * تأكيد استيراد البيانات المعاينة
     */
    public function confirmImport($previewData) {
        try {
            // بدء تسجيل العملية
            $this->logId = $this->startImportLog('confirmed_import');

            // معالجة البيانات المؤكدة
            $this->processConfirmedData($previewData);

            // إنهاء تسجيل العملية
            $this->completeImportLog(true);

            return [
                'success' => true,
                'message' => 'تم استيراد البيانات بنجاح',
                'stats' => [
                    'new_extracts' => count($this->processedExtracts),
                    'updated_extracts' => count($this->updatedWorkOrders),
                    'work_orders' => $this->getTotalWorkOrders(),
                    'duplicates_handled' => count($this->duplicateWorkOrders)
                ],
                'processed_extracts' => $this->processedExtracts,
                'duplicates' => $this->duplicateWorkOrders
            ];

        } catch (Exception $e) {
            $this->completeImportLog(false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * استيراد المستخلصات من ملف Excel (الطريقة القديمة)
     */
    public function import($filePath, $fileName) {
        try {
            // بدء تسجيل العملية
            $this->logId = $this->startImportLog($fileName);

            // قراءة الملف
            $data = $this->readExcelFile($filePath);

            if (empty($data)) {
                throw new Exception('الملف فارغ أو لا يحتوي على بيانات صحيحة');
            }

            // تحليل البيانات والتحقق من صحتها
            $validatedData = $this->validateData($data);

            // معالجة البيانات
            $this->processData($validatedData);

            // إنهاء تسجيل العملية
            $this->completeImportLog(true);

            return [
                'success' => true,
                'message' => 'تم الاستيراد بنجاح',
                'stats' => $this->getImportStats()
            ];
            
        } catch (Exception $e) {
            if ($this->logId) {
                $this->completeImportLog(false, $e->getMessage());
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $this->errors
            ];
        }
    }

    /**
     * إعداد بيانات المعاينة
     */
    private function preparePreviewData($data) {
        $previewData = [];
        $errors = [];
        $warnings = [];
        $totalAmount = 0;

        foreach ($data as $index => $row) {
            $rowNumber = $index + 5; // +5 لأن العناوين في الصف 4 والبيانات تبدأ من الصف 5

            // تحضير بيانات الصف (استخدام أسماء الأعمدة كمفاتيح)
            $previewRow = [
                'row_number' => $rowNumber,
                'extract_number' => trim($row['رقم المستخلص'] ?? ''),
                'invoice_number' => trim($row['رقم الفاتورة'] ?? ''),
                'branch_name' => trim($row['الفرع'] ?? ''),
                'department' => trim($row['القسم'] ?? ''),
                'extract_date' => trim($row['تاريخ المستخلص'] ?? ''),
                'approval_stage' => trim($row['مرحلة الاعتماد'] ?? 'مسودة'),
                'work_order_number' => trim($row['رقم أمر العمل'] ?? ''),
                'work_order_type' => trim($row['نوع أمر العمل'] ?? ''),
                'completion_date' => trim($row['تاريخ الإنجاز'] ?? ''),
                'extract_value' => floatval($row['قيمة المستخلص'] ?? 0),
                'work_order_notes' => trim($row['ملاحظات أمر العمل'] ?? ''),
                'status' => 'success'
            ];

            // تحديد القسم: أولاً من قاعدة البيانات، ثم من عمود القسم في Excel، ثم من نوع أمر العمل
            $departmentEnglish = null;

            // 1. محاولة قراءة القسم من قاعدة البيانات (الأولوية الأولى)
            if (!empty($previewRow['work_order_number']) && !empty($previewRow['work_order_type'])) {
                try {
                    // البحث عن نوع أمر العمل
                    $typeStmt = $this->db->prepare("SELECT id FROM work_order_types WHERE type_code = ? OR description LIKE ?");
                    $typeStmt->execute([$previewRow['work_order_type'], '%' . $previewRow['work_order_type'] . '%']);
                    $typeResult = $typeStmt->fetch();

                    if ($typeResult) {
                        // البحث عن أمر العمل بالرقم والنوع
                        $woStmt = $this->db->prepare("
                            SELECT department FROM work_orders
                            WHERE work_order_number = ? AND work_order_type_id = ?
                        ");
                        $woStmt->execute([$previewRow['work_order_number'], $typeResult['id']]);
                        $woResult = $woStmt->fetch();

                        if ($woResult && !empty($woResult['department'])) {
                            $departmentEnglish = $woResult['department'];
                        }
                    }
                } catch (Exception $e) {
                    // في حالة الخطأ، نتجاهل ونستمر
                }
            }

            // 2. إذا لم يُعثر على القسم في قاعدة البيانات، استخدم عمود القسم من Excel
            if (empty($departmentEnglish)) {
                $departmentArabic = $previewRow['department'];
                $departmentEnglish = $this->translateDepartmentToEnglish($departmentArabic);
            }

            // 3. إذا كان القسم لا يزال فارغاً، حدده من نوع أمر العمل
            if (empty($departmentEnglish)) {
                $departmentEnglish = $this->determineDepartment($previewRow['work_order_type']);
            }

            $previewRow['department_auto'] = $departmentEnglish;

            // حساب المبالغ تلقائياً
            $calculations = $this->calculateAmounts($previewRow['extract_value']);
            $previewRow['total_amount_calc'] = $calculations['total_amount'];
            $previewRow['tax_amount_calc'] = $calculations['tax_amount'];
            $previewRow['net_amount_calc'] = $calculations['net_amount'];

            $totalAmount += $previewRow['extract_value'];

            // التحقق من صحة البيانات
            $validation = $this->validatePreviewRow($previewRow);
            if (!$validation['valid']) {
                $previewRow['status'] = 'error';
                $errors = array_merge($errors, $validation['errors']);
            } elseif (!empty($validation['warnings'])) {
                $previewRow['status'] = 'warning';
                $warnings = array_merge($warnings, $validation['warnings']);
            }

            $previewData[] = $previewRow;
        }

        // حساب الإجماليات
        $totalCalculations = $this->calculateAmounts($totalAmount);

        return [
            'data' => $previewData,
            'errors' => $errors,
            'warnings' => $warnings,
            'calculations' => $totalCalculations
        ];
    }

    /**
     * تحديد القسم تلقائياً بناءً على نوع أمر العمل
     */
    private function determineDepartment($workOrderType) {
        return $this->determineDepartmentFromWorkOrderType($workOrderType);
    }

    /**
     * تحديد القسم من نوع أمر العمل
     */
    private function determineDepartmentFromWorkOrderType($workOrderType) {
        $workOrderType = strtolower(trim($workOrderType));

        // قواعد تحديد القسم
        $connectionTypes = ['توصيل', 'connection', 'conn', 'تركيب عداد', 'meter', 'عداد'];
        $projectTypes = ['مشروع', 'project', 'proj', 'إنشاء', 'construction'];

        foreach ($connectionTypes as $type) {
            if (mb_strpos($workOrderType, strtolower($type)) !== false) {
                return 'connections'; // التوصيلات
            }
        }

        foreach ($projectTypes as $type) {
            if (mb_strpos($workOrderType, strtolower($type)) !== false) {
                return 'projects'; // المشاريع
            }
        }

        return 'connections'; // افتراضي: التوصيلات
    }

    /**
     * حساب المبالغ تلقائياً
     */
    private function calculateAmounts($extractValue) {
        $taxRate = 0.15; // ضريبة 15%

        $totalAmount = $extractValue;
        $taxAmount = $totalAmount * $taxRate;
        $netAmount = $totalAmount; // الصافي = المبلغ الإجمالي بدون ضريبة

        return [
            'total_amount' => $totalAmount,
            'tax_amount' => $taxAmount,
            'net_amount' => $netAmount
        ];
    }

    /**
     * التحقق من صحة صف المعاينة
     */
    private function validatePreviewRow($row) {
        $errors = [];
        $warnings = [];

        // التحقق من الحقول المطلوبة
        if (empty($row['extract_number'])) {
            $errors[] = "الصف {$row['row_number']}: رقم المستخلص مطلوب";
        }

        if (empty($row['work_order_number'])) {
            $errors[] = "الصف {$row['row_number']}: رقم أمر العمل مطلوب";
        }

        if (empty($row['work_order_type'])) {
            $errors[] = "الصف {$row['row_number']}: نوع أمر العمل مطلوب";
        }

        if ($row['extract_value'] <= 0) {
            $errors[] = "الصف {$row['row_number']}: قيمة المستخلص يجب أن تكون أكبر من صفر";
        }

        // التحقق من التواريخ (فقط التحقق من الصحة، بدون تحويل)
        if (!empty($row['extract_date']) && !$this->isValidDate($row['extract_date'])) {
            $errors[] = "الصف {$row['row_number']}: تاريخ المستخلص '{$row['extract_date']}' غير صحيح";
        }

        if (!empty($row['completion_date']) && !$this->isValidDate($row['completion_date'])) {
            $errors[] = "الصف {$row['row_number']}: تاريخ الإنجاز '{$row['completion_date']}' غير صحيح";
        }

        // التحقق من أمر العمل مع نوعه (بالكود)
        if (!empty($row['work_order_number']) && !empty($row['work_order_type'])) {
            $workOrderInfo = $this->checkWorkOrderWithType($row['work_order_number'], $row['work_order_type']);

            if ($workOrderInfo === false) {
                // أمر العمل غير موجود - سيتم إنشاؤه
                $warnings[] = "الصف {$row['row_number']}: أمر العمل '{$row['work_order_number']}' بكود نوع '{$row['work_order_type']}' غير موجود، سيتم إنشاؤه تلقائياً";
            } elseif ($workOrderInfo['type_mismatch']) {
                // أمر العمل موجود بنفس الرقم لكن بأنواع أخرى
                $existingTypesStr = implode(', ', $workOrderInfo['existing_types']);
                $errors[] = "الصف {$row['row_number']}: أمر العمل '{$row['work_order_number']}' موجود بالأنواع التالية: [{$existingTypesStr}] لكن الملف يطلب كود '{$workOrderInfo['requested_code']}' - يجب استخدام أحد الأنواع الموجودة أو تغيير رقم أمر العمل";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * التحقق من أمر العمل مع نوعه
     * البحث عن أمر العمل بالرقم والكود معاً
     */
    private function checkWorkOrderWithType($workOrderNumber, $workOrderTypeCode) {
        // أولاً: الحصول على معرف نوع أمر العمل من الكود (يشمل جميع الحالات)
        $stmt = $this->db->prepare("
            SELECT id, type_code, description, status FROM work_order_types
            WHERE type_code = ?
        ");
        $stmt->execute([$workOrderTypeCode]);
        $workOrderType = $stmt->fetch();

        if (!$workOrderType) {
            // نوع أمر العمل غير موجود - سيتم إنشاؤه
            return false;
        }

        // إذا كان النوع غير نشط، نفعّله
        if ($workOrderType['status'] !== 'active') {
            $updateStmt = $this->db->prepare("UPDATE work_order_types SET status = 'active' WHERE id = ?");
            $updateStmt->execute([$workOrderType['id']]);
        }

        // ثانياً: البحث عن أمر العمل بالرقم والنوع معاً
        $stmt = $this->db->prepare("
            SELECT wo.id, wo.work_order_number, wo.work_order_type_id,
                   wot.type_code, wot.description as type_description
            FROM work_orders wo
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE wo.work_order_number = ? AND wo.work_order_type_id = ?
        ");
        $stmt->execute([$workOrderNumber, $workOrderType['id']]);
        $workOrder = $stmt->fetch();

        if ($workOrder) {
            // أمر العمل موجود بنفس الرقم والنوع
            return [
                'type_mismatch' => false,
                'work_order_id' => $workOrder['id'],
                'type_code' => $workOrder['type_code'],
                'type_description' => $workOrder['type_description']
            ];
        }

        // ثالثاً: التحقق من وجود أمر عمل بنفس الرقم لكن بنوع مختلف
        $stmt = $this->db->prepare("
            SELECT wo.id, wo.work_order_number, wo.work_order_type_id,
                   wot.type_code, wot.description as type_description
            FROM work_orders wo
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE wo.work_order_number = ?
        ");
        $stmt->execute([$workOrderNumber]);
        $existingWorkOrders = $stmt->fetchAll();

        if (!empty($existingWorkOrders)) {
            // أمر العمل موجود بنفس الرقم لكن بأنواع أخرى
            $existingTypes = [];
            foreach ($existingWorkOrders as $wo) {
                $existingTypes[] = $wo['type_code'] . ' (' . $wo['type_description'] . ')';
            }

            return [
                'type_mismatch' => true,
                'existing_types' => $existingTypes,
                'requested_code' => $workOrderTypeCode
            ];
        }

        // أمر العمل غير موجود بتاتاً
        return false;
    }

    /**
     * معالجة البيانات المؤكدة
     */
    private function processConfirmedData($previewData) {
        $this->db->beginTransaction();

        try {
            // تجميع البيانات حسب المستخلص
            $extractsData = [];
            foreach ($previewData as $row) {
                if ($row['status'] === 'success' || $row['status'] === 'warning') {
                    $extractKey = $row['extract_number'] . '_' . $row['branch_name'];

                    if (!isset($extractsData[$extractKey])) {
                        $extractsData[$extractKey] = [
                            'extract_number' => $row['extract_number'],
                            'invoice_number' => $row['invoice_number'],
                            'branch_name' => $row['branch_name'],
                            'department' => null, // سيتم تحديده لاحقاً
                            'extract_date' => $row['extract_date'],
                            'approval_stage' => $row['approval_stage'] ?? 'مسودة',
                            'work_orders' => []
                        ];
                    }

                    // إضافة أمر العمل
                    $extractsData[$extractKey]['work_orders'][] = [
                        'work_order_number' => $row['work_order_number'],
                        'work_order_type' => $row['work_order_type'],
                        'completion_date' => $row['completion_date'],
                        'extract_value' => $row['extract_value']
                    ];
                }
            }

            // معالجة كل مستخلص مع جميع أوامر العمل
            foreach ($extractsData as $extractData) {
                $this->processExtractWithWorkOrders($extractData);
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * معالجة مستخلص مع جميع أوامر العمل
     */
    private function processExtractWithWorkOrders($extractData) {
        // البحث عن الفرع
        $branchId = $this->getBranchIdByName($extractData['branch_name']);
        if (!$branchId) {
            throw new Exception("الفرع '{$extractData['branch_name']}' غير موجود");
        }

        // قراءة أقسام أوامر العمل من قاعدة البيانات
        $departments = [];
        foreach ($extractData['work_orders'] as &$workOrder) {
            // الحصول على معرف نوع أمر العمل
            $workOrderTypeId = $this->getOrCreateWorkOrderType($workOrder['work_order_type']);

            // البحث عن أمر العمل في قاعدة البيانات بالرقم والنوع معاً
            $stmt = $this->db->prepare("
                SELECT department FROM work_orders
                WHERE work_order_number = ? AND work_order_type_id = ?
            ");
            $stmt->execute([$workOrder['work_order_number'], $workOrderTypeId]);
            $existing = $stmt->fetch();

            if ($existing && !empty($existing['department'])) {
                // أمر العمل موجود - استخدم القسم من قاعدة البيانات
                $workOrder['department'] = $existing['department'];
                $departments[] = $existing['department'];
            } else {
                // أمر العمل جديد أو ليس له قسم - حدد من نوع أمر العمل
                $dept = $this->determineDepartmentFromWorkOrderType($workOrder['work_order_type']);
                $workOrder['department'] = $dept;
                $departments[] = $dept;
            }
        }
        unset($workOrder);

        // تحديد قسم المستخلص بناءً على أغلبية أقسام أوامر العمل
        if (!empty($departments)) {
            $departmentCounts = array_count_values($departments);
            arsort($departmentCounts);
            $extractData['department'] = key($departmentCounts);
        } else {
            // افتراضي
            $extractData['department'] = 'connections';
        }

        // حساب المبالغ الإجمالية من جميع أوامر العمل
        $totalExtractValue = 0;
        foreach ($extractData['work_orders'] as $wo) {
            $totalExtractValue += $wo['extract_value'];
        }

        $taxRate = 0.15;
        $totalAmount = $totalExtractValue;
        $taxAmount = $totalAmount * $taxRate;
        $netAmount = $totalAmount; // الصافي = المبلغ الإجمالي بدون ضريبة

        // تحويل تاريخ المستخلص إلى الصيغة الموحدة
        $extractDate = $this->normalizeDate($extractData['extract_date']);
        if (!$extractDate) {
            $extractDate = date('Y-m-d'); // استخدام التاريخ الحالي كقيمة افتراضية
        }

        // تحويل مرحلة الاعتماد من العربية إلى الإنجليزية
        $approvalStage = $this->translateApprovalStageToEnglish($extractData['approval_stage'] ?? 'مسودة');

        // البحث عن أو إنشاء المستخلص
        $extractId = $this->findOrCreateExtract([
            'extract_number' => $extractData['extract_number'],
            'invoice_number' => $extractData['invoice_number'],
            'branch_id' => $branchId,
            'department' => $extractData['department'],
            'extract_date' => $extractDate,
            'total_amount' => $totalAmount,
            'tax_amount' => $taxAmount,
            'net_amount' => $netAmount,
            'approval_stage' => $approvalStage
        ]);

        // إضافة جميع أوامر العمل
        foreach ($extractData['work_orders'] as $workOrder) {
            // تحويل تاريخ الإنجاز إلى الصيغة الموحدة
            $completionDate = $this->normalizeDate($workOrder['completion_date']);

            $this->addWorkOrderToExtract($extractId, [
                'work_order_number' => $workOrder['work_order_number'],
                'work_order_type' => $workOrder['work_order_type'],
                'completion_date' => $completionDate,
                'extract_value' => $workOrder['extract_value'],
                'branch_id' => $branchId,
                'department' => $workOrder['department']
            ]);
        }
    }

    /**
     * الحصول على العدد الإجمالي لأوامر العمل
     */
    private function getTotalWorkOrders() {
        $total = 0;
        foreach ($this->processedExtracts as $extract) {
            $total += $extract['work_orders_count'] ?? 0;
        }
        return $total;
    }

    /**
     * قراءة ملف Excel
     */
    private function readExcelFile($filePath) {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = [];
            
            // قراءة البيانات من الصف الثالث (تجاهل العنوان والمعلومات)
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            // التحقق من وجود العناوين المطلوبة (العناوين الجديدة بعد إزالة الأعمدة غير المرغوبة)
            $expectedHeaders = [
                'رقم المستخلص', 'رقم الفاتورة', 'الفرع', 'القسم', 'تاريخ المستخلص',
                'المبلغ الإجمالي', 'الضريبة', 'المبلغ الصافي', 'مرحلة الاعتماد',
                'رقم أمر العمل', 'نوع أمر العمل', 'تاريخ الإنجاز',
                'قيمة المستخلص', 'ملاحظات أمر العمل'
            ];
            
            // قراءة رأس الجدول (الصف 4 - بعد العنوان ومعلومات التصدير)
            $headerRow = 4;
            $headers = [];
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $headers[] = $worksheet->getCell($col . $headerRow)->getValue();
            }
            
            // التحقق من العناوين
            $missingHeaders = array_diff($expectedHeaders, $headers);
            if (!empty($missingHeaders)) {
                $errorMessage = "العناوين التالية مفقودة في الملف: " . implode(', ', $missingHeaders);
                $errorMessage .= "\n\nالعناوين الموجودة في الصف $headerRow: " . implode(', ', array_filter($headers));
                $errorMessage .= "\n\nتأكد من أن الملف مصدر من نظام تِقان وأن العناوين في الصف الرابع.";
                throw new Exception($errorMessage);
            }
            
            // قراءة البيانات
            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $rowData = [];
                $isEmpty = true;

                $colIndex = 0;
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cell = $worksheet->getCell($col . $row);
                    $value = $cell->getValue();

                    // التحقق من نوع الخلية - إذا كانت تاريخ، نحولها
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        // تحويل التاريخ من Excel إلى صيغة قابلة للقراءة
                        $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                        $value = $dateValue->format('Y-m-d');
                    }

                    if (!empty($value)) {
                        $isEmpty = false;
                    }
                    $rowData[] = $value;
                    $colIndex++;
                }

                // تجاهل الصفوف الفارغة
                if (!$isEmpty) {
                    $data[] = array_combine($headers, $rowData);
                }
            }
            
            return $data;
            
        } catch (Exception $e) {
            throw new Exception('خطأ في قراءة الملف: ' . $e->getMessage());
        }
    }
    
    /**
     * التحقق من صحة البيانات
     */
    private function validateData($data) {
        $validatedData = [];
        $rowNumber = 5; // بداية البيانات في الملف (بعد العناوين في الصف 4)
        
        foreach ($data as $row) {
            $errors = [];
            
            // التحقق من الحقول المطلوبة
            if (empty($row['رقم المستخلص'])) {
                $errors[] = 'رقم المستخلص مطلوب';
            }
            
            if (empty($row['رقم أمر العمل'])) {
                $errors[] = 'رقم أمر العمل مطلوب';
            }
            
            if (empty($row['تاريخ المستخلص'])) {
                $errors[] = 'تاريخ المستخلص مطلوب';
            }
            
            if (empty($row['قيمة المستخلص']) || !is_numeric($row['قيمة المستخلص'])) {
                $errors[] = 'قيمة المستخلص يجب أن تكون رقماً';
            }
            
            // التحقق من وجود أمر العمل في النظام
            if (!empty($row['رقم أمر العمل'])) {
                $workOrderExists = $this->checkWorkOrderExists($row['رقم أمر العمل']);
                if (!$workOrderExists) {
                    $errors[] = 'أمر العمل غير موجود في النظام';
                }
            }
            
            // التحقق من تكرار أمر العمل في مستخلص آخر
            if (!empty($row['رقم أمر العمل'])) {
                $duplicateInfo = $this->checkWorkOrderDuplicate($row['رقم أمر العمل'], $row['رقم المستخلص']);
                if ($duplicateInfo) {
                    $this->duplicateWorkOrders[] = [
                        'work_order_number' => $row['رقم أمر العمل'],
                        'current_extract' => $row['رقم المستخلص'],
                        'existing_extract' => $duplicateInfo['extract_number'],
                        'existing_extract_id' => $duplicateInfo['extract_id'],
                        'row_number' => $rowNumber
                    ];
                }
            }
            
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addError($rowNumber, $row['رقم المستخلص'], $row['رقم أمر العمل'], 'validation', $error);
                }
            } else {
                $validatedData[] = array_merge($row, ['row_number' => $rowNumber]);
            }
            
            $rowNumber++;
        }
        
        return $validatedData;
    }
    
    /**
     * التحقق من وجود أمر العمل
     */
    private function checkWorkOrderExists($workOrderNumber) {
        $stmt = $this->db->prepare("SELECT id FROM work_orders WHERE work_order_number = ?");
        $stmt->execute([$workOrderNumber]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * التحقق من تكرار أمر العمل في مستخلص آخر
     */
    private function checkWorkOrderDuplicate($workOrderNumber, $currentExtractNumber) {
        $stmt = $this->db->prepare("
            SELECT pe.extract_number, pe.id as extract_id
            FROM partial_extract_work_orders pewo
            INNER JOIN work_orders wo ON pewo.work_order_id = wo.id
            INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
            WHERE wo.work_order_number = ? AND pe.extract_number != ?
        ");
        
        $stmt->execute([$workOrderNumber, $currentExtractNumber]);
        return $stmt->fetch();
    }
    
    /**
     * معالجة البيانات
     */
    private function processData($data) {
        $this->db->beginTransaction();
        
        try {
            $extractsData = $this->groupDataByExtract($data);
            
            foreach ($extractsData as $extractNumber => $extractInfo) {
                $this->processExtract($extractNumber, $extractInfo);
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * تجميع البيانات حسب المستخلص
     */
    private function groupDataByExtract($data) {
        $extractsData = [];
        
        foreach ($data as $row) {
            $extractNumber = $row['رقم المستخلص'];
            
            if (!isset($extractsData[$extractNumber])) {
                $extractsData[$extractNumber] = [
                    'extract_info' => $row,
                    'work_orders' => []
                ];
            }
            
            $extractsData[$extractNumber]['work_orders'][] = $row;
        }
        
        return $extractsData;
    }
    
    /**
     * معالجة مستخلص واحد
     */
    private function processExtract($extractNumber, $extractInfo) {
        // البحث عن المستخلص الموجود
        $existingExtract = $this->findExistingExtract($extractNumber);
        
        if ($existingExtract) {
            // تحديث المستخلص الموجود
            $this->updateExtract($existingExtract['id'], $extractInfo);
        } else {
            // إنشاء مستخلص جديد
            $this->createExtract($extractNumber, $extractInfo);
        }
        
        $this->processedExtracts[] = $extractNumber;
    }
    
    /**
     * البحث عن مستخلص موجود
     */
    private function findExistingExtract($extractNumber) {
        $stmt = $this->db->prepare("SELECT * FROM partial_extracts WHERE extract_number = ?");
        $stmt->execute([$extractNumber]);
        return $stmt->fetch();
    }
    
    /**
     * إنشاء مستخلص جديد
     */
    private function createExtract($extractNumber, $extractInfo) {
        $extractData = $extractInfo['extract_info'];
        
        // تحويل القسم
        $department = $this->translateDepartmentToEnglish($extractData['القسم']);
        
        // تحويل مرحلة الاعتماد
        $approvalStage = $this->translateApprovalStageToEnglish($extractData['مرحلة الاعتماد']);
        
        // الحصول على معرف الفرع
        $branchId = $this->getBranchIdByName($extractData['الفرع']);
        
        if (!$branchId) {
            throw new Exception("الفرع '{$extractData['الفرع']}' غير موجود في النظام");
        }
        
        // إدراج المستخلص
        $stmt = $this->db->prepare("
            INSERT INTO partial_extracts (
                extract_number, invoice_number, branch_id, department, 
                extract_date, total_amount, tax_amount, net_amount, 
                approval_stage, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $extractNumber,
            $extractData['رقم الفاتورة'],
            $branchId,
            $department,
            $extractData['تاريخ المستخلص'],
            $extractData['المبلغ الإجمالي'],
            $extractData['الضريبة'],
            $extractData['المبلغ الصافي'],
            $approvalStage,
            $this->userId
        ]);
        
        $extractId = $this->db->lastInsertId();
        
        // إضافة أوامر العمل
        $this->addWorkOrdersToExtract($extractId, $extractInfo['work_orders']);
    }
    
    /**
     * تحديث مستخلص موجود
     */
    private function updateExtract($extractId, $extractInfo) {
        // تحديث أوامر العمل فقط (لا نحدث بيانات المستخلص الأساسية)
        $this->updateWorkOrdersInExtract($extractId, $extractInfo['work_orders']);
    }
    
    /**
     * إضافة أوامر العمل للمستخلص
     */
    private function addWorkOrdersToExtract($extractId, $workOrders) {
        foreach ($workOrders as $workOrderData) {
            $workOrderId = $this->getWorkOrderIdByNumber($workOrderData['رقم أمر العمل']);
            
            if ($workOrderId) {
                // التحقق من عدم وجود أمر العمل في المستخلص مسبقاً
                $exists = $this->checkWorkOrderInExtract($extractId, $workOrderId);
                
                if (!$exists) {
                    $stmt = $this->db->prepare("
                        INSERT INTO partial_extract_work_orders (
                            partial_extract_id, work_order_id, completion_date, 
                            extract_value, notes, added_by, added_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $extractId,
                        $workOrderId,
                        $workOrderData['تاريخ الإنجاز'],
                        $workOrderData['قيمة المستخلص'],
                        $workOrderData['ملاحظات أمر العمل'],
                        $this->userId
                    ]);
                }
            }
        }
    }
    
    /**
     * تحديث أوامر العمل في المستخلص
     */
    private function updateWorkOrdersInExtract($extractId, $workOrders) {
        foreach ($workOrders as $workOrderData) {
            $workOrderId = $this->getWorkOrderIdByNumber($workOrderData['رقم أمر العمل']);
            
            if ($workOrderId) {
                // التحقق من وجود أمر العمل في المستخلص
                $existingRecord = $this->getWorkOrderInExtract($extractId, $workOrderId);
                
                if ($existingRecord) {
                    // تحديث السجل الموجود
                    $stmt = $this->db->prepare("
                        UPDATE partial_extract_work_orders 
                        SET completion_date = ?, extract_value = ?, notes = ?, updated_at = NOW()
                        WHERE partial_extract_id = ? AND work_order_id = ?
                    ");
                    
                    $stmt->execute([
                        $workOrderData['تاريخ الإنجاز'],
                        $workOrderData['قيمة المستخلص'],
                        $workOrderData['ملاحظات أمر العمل'],
                        $extractId,
                        $workOrderId
                    ]);
                    
                    $this->updatedWorkOrders[] = $workOrderData['رقم أمر العمل'];
                } else {
                    // إضافة سجل جديد
                    $this->addWorkOrdersToExtract($extractId, [$workOrderData]);
                }
            }
        }
    }
    
    /**
     * الحصول على معرف أمر العمل
     */
    private function getWorkOrderIdByNumber($workOrderNumber) {
        $stmt = $this->db->prepare("SELECT id FROM work_orders WHERE work_order_number = ?");
        $stmt->execute([$workOrderNumber]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * التحقق من وجود أمر العمل في المستخلص
     */
    private function checkWorkOrderInExtract($extractId, $workOrderId) {
        $stmt = $this->db->prepare("
            SELECT id FROM partial_extract_work_orders 
            WHERE partial_extract_id = ? AND work_order_id = ?
        ");
        $stmt->execute([$extractId, $workOrderId]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * الحصول على سجل أمر العمل في المستخلص
     */
    private function getWorkOrderInExtract($extractId, $workOrderId) {
        $stmt = $this->db->prepare("
            SELECT * FROM partial_extract_work_orders 
            WHERE partial_extract_id = ? AND work_order_id = ?
        ");
        $stmt->execute([$extractId, $workOrderId]);
        return $stmt->fetch();
    }
    
    /**
     * الحصول على معرف الفرع بالاسم
     */
    private function getBranchIdByName($branchName) {
        $stmt = $this->db->prepare("SELECT id FROM branches WHERE name = ?");
        $stmt->execute([$branchName]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * ترجمة القسم إلى الإنجليزية
     */
    private function translateDepartmentToEnglish($department) {
        $translations = [
            'التوصيلات' => 'connections',
            'المشاريع' => 'projects'
        ];
        return $translations[$department] ?? $department;
    }
    
    /**
     * ترجمة مرحلة الاعتماد إلى الإنجليزية من قاعدة البيانات
     */
    private function translateApprovalStageToEnglish($stage) {
        // محاولة جلب مفتاح المرحلة من قاعدة البيانات
        try {
            $stmt = $this->db->prepare("
                SELECT stage_key
                FROM approval_stages
                WHERE stage_name = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$stage]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result['stage_key'];
            }
        } catch (Exception $e) {
            // في حالة فشل الاستعلام، استخدم القيم الافتراضية
            error_log("Error fetching approval stage key: " . $e->getMessage());
        }

        // القيم الافتراضية كـ fallback
        $translations = [
            'مسودة' => 'draft',
            'في انتظار المشرف' => 'pending_supervisor',
            'في انتظار المدير' => 'pending_manager',
            'في انتظار المالية' => 'pending_finance',
            'مصروف' => 'disbursed',
            'مالية الطائف' => 'taif_finance',
            'مرفوض' => 'rejected',
            'المساندة الفنية' => 'technical_support',
            'الإنشاءات' => 'construction',
            'مدير الدائرة' => 'department_manager',
            'مدير الإدارة' => 'administration_manager'
        ];
        return $translations[$stage] ?? 'draft';
    }
    
    /**
     * إضافة خطأ
     */
    private function addError($rowNumber, $extractNumber, $workOrderNumber, $errorType, $message, $fieldName = null, $fieldValue = null) {
        $this->errors[] = [
            'row_number' => $rowNumber,
            'extract_number' => $extractNumber,
            'work_order_number' => $workOrderNumber,
            'error_type' => $errorType,
            'message' => $message,
            'field_name' => $fieldName,
            'field_value' => $fieldValue
        ];
        
        // حفظ الخطأ في قاعدة البيانات
        if ($this->logId) {
            $stmt = $this->db->prepare("
                INSERT INTO partial_extract_import_errors 
                (log_id, row_number, extract_number, work_order_number, error_type, error_message, field_name, field_value)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->logId, $rowNumber, $extractNumber, $workOrderNumber, 
                $errorType, $message, $fieldName, $fieldValue
            ]);
        }
    }
    
    /**
     * بدء تسجيل عملية الاستيراد
     */
    private function startImportLog($fileName) {
        $stmt = $this->db->prepare("
            INSERT INTO partial_extract_import_export_logs 
            (operation_type, file_name, status, user_id) 
            VALUES ('import', ?, 'processing', ?)
        ");
        
        $stmt->execute([$fileName, $this->userId]);
        return $this->db->lastInsertId();
    }
    
    /**
     * إنهاء تسجيل عملية الاستيراد
     */
    private function completeImportLog($success, $errorMessage = null) {
        $status = $success ? 'completed' : 'failed';
        $stats = $this->getImportStats();
        
        $stmt = $this->db->prepare("
            UPDATE partial_extract_import_export_logs 
            SET status = ?, error_message = ?, completed_at = NOW(),
                total_records = ?, successful_records = ?, failed_records = ?,
                extracts_processed = ?, work_orders_processed = ?, 
                duplicates_found = ?, updates_made = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $status, $errorMessage, $stats['total_records'], $stats['successful_records'],
            $stats['failed_records'], $stats['extracts_processed'], $stats['work_orders_processed'],
            $stats['duplicates_found'], $stats['updates_made'], $this->logId
        ]);
    }
    
    /**
     * الحصول على إحصائيات الاستيراد
     */
    private function getImportStats() {
        return [
            'total_records' => count($this->processedExtracts) + count($this->errors),
            'successful_records' => count($this->processedExtracts),
            'failed_records' => count($this->errors),
            'extracts_processed' => count($this->processedExtracts),
            'work_orders_processed' => count($this->updatedWorkOrders),
            'duplicates_found' => count($this->duplicateWorkOrders),
            'updates_made' => count($this->updatedWorkOrders)
        ];
    }
    
    /**
     * الحصول على التكرارات الموجودة
     */
    public function getDuplicates() {
        return $this->duplicateWorkOrders;
    }
    
    /**
     * الحصول على الأخطاء
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * البحث عن أو إنشاء مستخلص
     */
    private function findOrCreateExtract($extractData) {
        // البحث عن مستخلص موجود
        $stmt = $this->db->prepare("
            SELECT id FROM partial_extracts
            WHERE extract_number = ? AND branch_id = ?
        ");
        $stmt->execute([$extractData['extract_number'], $extractData['branch_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // تحديث المستخلص الموجود (بما في ذلك القسم ومرحلة الاعتماد ورقم الفاتورة)
            $stmt = $this->db->prepare("
                UPDATE partial_extracts
                SET invoice_number = ?, department = ?, total_amount = ?, tax_amount = ?, net_amount = ?, approval_stage = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $extractData['invoice_number'],
                $extractData['department'],
                $extractData['total_amount'],
                $extractData['tax_amount'],
                $extractData['net_amount'],
                $extractData['approval_stage'] ?? 'draft',
                $existing['id']
            ]);

            // إضافة للقائمة فقط إذا لم يكن موجود
            if (!$this->isExtractInProcessedList($existing['id'])) {
                $this->processedExtracts[] = [
                    'id' => $existing['id'],
                    'extract_number' => $extractData['extract_number'],
                    'invoice_number' => $extractData['invoice_number'],
                    'branch_name' => $this->getBranchName($extractData['branch_id']),
                    'department' => $extractData['department'],
                    'total_amount' => $extractData['total_amount'],
                    'action' => 'updated',
                    'work_orders_count' => 0
                ];
            }

            return $existing['id'];
        } else {
            // إنشاء مستخلص جديد
            $stmt = $this->db->prepare("
                INSERT INTO partial_extracts (
                    extract_number, invoice_number, branch_id, department,
                    extract_date, total_amount, tax_amount, net_amount,
                    approval_stage, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $extractData['extract_number'],
                $extractData['invoice_number'],
                $extractData['branch_id'],
                $extractData['department'],
                $extractData['extract_date'],
                $extractData['total_amount'],
                $extractData['tax_amount'],
                $extractData['net_amount'],
                $extractData['approval_stage'] ?? 'draft',
                $this->userId
            ]);

            $extractId = $this->db->lastInsertId();

            // إضافة للقائمة فقط إذا لم يكن موجود
            if (!$this->isExtractInProcessedList($extractId)) {
                $this->processedExtracts[] = [
                    'id' => $extractId,
                    'extract_number' => $extractData['extract_number'],
                    'invoice_number' => $extractData['invoice_number'],
                    'branch_name' => $this->getBranchName($extractData['branch_id']),
                    'department' => $extractData['department'],
                    'total_amount' => $extractData['total_amount'],
                    'action' => 'created',
                    'work_orders_count' => 0
                ];
            }

            return $extractId;
        }
    }

    /**
     * إضافة أمر عمل للمستخلص
     */
    private function addWorkOrderToExtract($extractId, $workOrderData) {
        // البحث عن نوع أمر العمل أو إنشاؤه
        $workOrderTypeId = $this->getOrCreateWorkOrderType($workOrderData['work_order_type']);

        // البحث عن أمر العمل بالرقم والنوع معاً
        $stmt = $this->db->prepare("
            SELECT id FROM work_orders
            WHERE work_order_number = ? AND work_order_type_id = ?
        ");
        $stmt->execute([$workOrderData['work_order_number'], $workOrderTypeId]);
        $workOrder = $stmt->fetch();

        if ($workOrder) {
            // أمر العمل موجود - استخدمه كما هو
            $workOrderId = $workOrder['id'];
        } else {
            // أمر العمل غير موجود - إنشاء جديد
            // استخدام القسم المحدد مسبقاً من processExtractWithWorkOrders
            $stmt = $this->db->prepare("
                INSERT INTO work_orders (
                    work_order_number, work_order_type_id, department, branch_id, status
                ) VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $workOrderData['work_order_number'],
                $workOrderTypeId,
                $workOrderData['department'],
                $workOrderData['branch_id']
            ]);
            $workOrderId = $this->db->lastInsertId();
        }

        // إضافة أمر العمل للمستخلص
        $stmt = $this->db->prepare("
            INSERT INTO partial_extract_work_orders (
                partial_extract_id, work_order_id, extract_value, completion_date, added_by
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            extract_value = VALUES(extract_value), updated_at = NOW()
        ");
        $stmt->execute([
            $extractId,
            $workOrderId,
            $workOrderData['extract_value'],
            $workOrderData['completion_date'] ?? null,
            $this->userId
        ]);

        // تحديث عداد أوامر العمل
        foreach ($this->processedExtracts as &$extract) {
            if ($extract['id'] == $extractId) {
                $extract['work_orders_count']++;
                break;
            }
        }
    }

    /**
     * الحصول على اسم الفرع
     */
    private function getBranchName($branchId) {
        $stmt = $this->db->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branchId]);
        $result = $stmt->fetch();
        return $result ? $result['name'] : 'غير محدد';
    }

    /**
     * التحقق من صحة التاريخ (بدون تحويل)
     */
    private function isValidDate($date) {
        if (empty($date)) {
            return true; // التاريخ اختياري
        }

        // محاولة تحويل التاريخ للتحقق من صحته
        $normalized = $this->normalizeDate($date);
        return $normalized !== null;
    }

    /**
     * تحويل التاريخ إلى صيغة موحدة (Y-m-d) للحفظ في قاعدة البيانات
     */
    private function normalizeDate($date) {
        if (empty($date)) {
            return null;
        }

        // إذا كان التاريخ رقم (Excel serial date)
        if (is_numeric($date)) {
            return $this->excelDateToPhp($date);
        }

        // تنظيف التاريخ من المسافات الزائدة
        $date = trim($date);

        // إذا كان التاريخ بالفعل بصيغة Y-m-d، نرجعه مباشرة
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
            return $date;
        }

        // قائمة الصيغ المدعومة (مرتبة حسب الأكثر شيوعاً)
        $formats = [
            'd/m/Y',           // 15/01/2025 (الأكثر شيوعاً في العالم العربي)
            'd-m-Y',           // 15-01-2025
            'Y/m/d',           // 2025/01/15
            'd.m.Y',           // 15.01.2025
            'm/d/Y',           // 01/15/2025 (أمريكي)
            'm-d-Y',           // 01-15-2025
            'j/n/Y',           // 5/1/2025 (بدون أصفار)
            'j-n-Y',           // 5-1-2025
            'd/m/y',           // 15/01/25 (سنة قصيرة)
            'd-m-y',           // 15-01-25
            'Y-m-d H:i:s',     // 2025-01-15 14:30:00 (مع وقت)
            'd/m/Y H:i:s',     // 15/01/2025 14:30:00
        ];

        foreach ($formats as $format) {
            $d = DateTime::createFromFormat($format, $date);
            if ($d !== false) {
                // التحقق من أن التاريخ صحيح
                $errors = DateTime::getLastErrors();
                if ($errors !== false && is_array($errors)) {
                    if ($errors['warning_count'] == 0 && $errors['error_count'] == 0) {
                        return $d->format('Y-m-d');
                    }
                } else {
                    return $d->format('Y-m-d');
                }
            }
        }

        // محاولة استخدام strtotime كحل أخير
        $timestamp = strtotime($date);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * تحويل تاريخ Excel إلى تاريخ PHP
     */
    private function excelDateToPhp($excelDate) {
        // Excel يبدأ من 1900-01-01 (مع خطأ في السنة الكبيسة)
        // الرقم التسلسلي 1 = 1900-01-01

        if ($excelDate < 1) {
            return null;
        }

        // تصحيح خطأ Excel في السنة الكبيسة 1900
        if ($excelDate > 59) {
            $excelDate--;
        }

        // تحويل إلى timestamp Unix
        $unixTimestamp = ($excelDate - 25569) * 86400;

        // التحقق من صحة التاريخ
        if ($unixTimestamp < 0) {
            return null;
        }

        return date('Y-m-d', $unixTimestamp);
    }

    /**
     * التحقق من وجود المستخلص في قائمة المعالجة
     */
    private function isExtractInProcessedList($extractId) {
        foreach ($this->processedExtracts as $extract) {
            if ($extract['id'] == $extractId) {
                return true;
            }
        }
        return false;
    }

    /**
     * الحصول على معرف نوع أمر العمل أو إنشاؤه
     * يبحث بالكود أولاً، ثم بالوصف (يشمل النشطة وغير النشطة)
     */
    private function getOrCreateWorkOrderType($typeCodeOrName) {
        // تنظيف المدخل
        $typeCodeOrName = trim($typeCodeOrName);

        // البحث عن نوع أمر العمل بالكود أولاً (الأولوية) - يشمل جميع الحالات
        $stmt = $this->db->prepare("
            SELECT id, type_code, description, status FROM work_order_types
            WHERE type_code = ?
        ");
        $stmt->execute([$typeCodeOrName]);
        $type = $stmt->fetch();

        if ($type) {
            // إذا كان غير نشط، نفعّله
            if ($type['status'] !== 'active') {
                $updateStmt = $this->db->prepare("UPDATE work_order_types SET status = 'active' WHERE id = ?");
                $updateStmt->execute([$type['id']]);
            }
            return $type['id'];
        }

        // إذا لم يوجد بالكود، ابحث بالوصف - يشمل جميع الحالات
        $stmt = $this->db->prepare("
            SELECT id, type_code, description, status FROM work_order_types
            WHERE description = ?
        ");
        $stmt->execute([$typeCodeOrName]);
        $type = $stmt->fetch();

        if ($type) {
            // إذا كان غير نشط، نفعّله
            if ($type['status'] !== 'active') {
                $updateStmt = $this->db->prepare("UPDATE work_order_types SET status = 'active' WHERE id = ?");
                $updateStmt->execute([$type['id']]);
            }
            return $type['id'];
        }

        // إنشاء نوع جديد
        // إذا كان المدخل يبدو ككود (3-4 أحرف كبيرة/أرقام)، استخدمه ككود
        if (preg_match('/^[A-Z0-9]{2,4}$/', $typeCodeOrName)) {
            $typeCode = $typeCodeOrName;
            $description = $typeCodeOrName; // استخدام الكود كوصف مؤقت
        } else {
            // المدخل هو وصف، نولد كود
            $typeCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $typeCodeOrName), 0, 2)) . rand(0, 9);
            $description = $typeCodeOrName;
        }

        // التحقق من عدم تكرار الكود
        $checkStmt = $this->db->prepare("SELECT id FROM work_order_types WHERE type_code = ?");
        $checkStmt->execute([$typeCode]);

        // إذا كان الكود موجود، أضف رقم عشوائي آخر
        while ($checkStmt->fetch()) {
            $typeCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $typeCodeOrName), 0, 2)) . rand(10, 99);
            $checkStmt->execute([$typeCode]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO work_order_types (type_code, description, status)
            VALUES (?, ?, 'active')
        ");
        $stmt->execute([$typeCode, $description]);

        return $this->db->lastInsertId();
    }
}
?>
