<?php
/**
 * مستورد المستخلصات النهائية للجزئية من Excel
 * Final For Partial Extract Excel Importer
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class FinalForPartialExtractImporter {
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
        $totalPenalty = 0;

        foreach ($data as $index => $row) {
            $rowNumber = $index + 5; // +5 لأن العناوين في الصف 4 والبيانات تبدأ من الصف 5

            // تحضير بيانات الصف
            $previewRow = [
                'row_number' => $rowNumber,
                'extract_number' => trim($row['رقم المستخلص'] ?? ''),
                'branch_name' => trim($row['الفرع'] ?? ''),
                'extract_date' => $this->parseDate($row['تاريخ المستخلص'] ?? ''),
                'related_partial_extract_number' => trim($row['المستخلص الجزئي المرتبط'] ?? ''),
                'approval_stage' => trim($row['مرحلة الاعتماد'] ?? 'draft'),
                'work_order_number' => trim($row['رقم أمر العمل'] ?? ''),
                'work_order_type_code' => trim($row['نوع أمر العمل'] ?? ''),
                'extract_value' => floatval($row['قيمة المستخلص'] ?? 0),
                'penalty_amount' => floatval($row['الغرامة'] ?? 0),
                'status' => 'valid',
                'errors' => [],
                // المبالغ المحسوبة - سيتم حسابها لاحقاً
                'total_amount' => 0,
                'tax_amount' => 0,
                'total_penalty_amount' => 0,
                'net_amount' => 0
            ];

            // التحقق من البيانات
            $rowErrors = $this->validateRow($previewRow);
            
            if (!empty($rowErrors)) {
                $previewRow['status'] = 'error';
                $previewRow['errors'] = $rowErrors;
                $errors = array_merge($errors, $rowErrors);
            }

            // التحقق من وجود المستخلص الجزئي وحفظ معرفه
            if (!empty($previewRow['related_partial_extract_number'])) {
                $partialExtract = $this->findPartialExtract($previewRow['related_partial_extract_number']);
                if (!$partialExtract) {
                    $previewRow['status'] = 'error';
                    $previewRow['errors'][] = 'المستخلص الجزئي غير موجود';
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'extract_number' => $previewRow['extract_number'],
                        'message' => 'المستخلص الجزئي غير موجود: ' . $previewRow['related_partial_extract_number']
                    ];
                } else {
                    // حفظ معرف المستخلص الجزئي لاستخدامه في حساب الضريبة
                    $previewRow['related_partial_extract_id'] = $partialExtract['id'];

                    // إذا لم يكن الفرع محدداً في الملف، استخدم فرع المستخلص الجزئي
                    if (empty($previewRow['branch_name']) && !empty($partialExtract['branch_id'])) {
                        // جلب اسم الفرع من معرف الفرع
                        $branchStmt = $this->db->prepare("SELECT name FROM branches WHERE id = ?");
                        $branchStmt->execute([$partialExtract['branch_id']]);
                        $branchData = $branchStmt->fetch(PDO::FETCH_ASSOC);
                        if ($branchData) {
                            $previewRow['branch_name'] = $branchData['name'];
                            $previewRow['branch_auto_filled'] = true; // علامة للإشارة أن الفرع تم تحديده تلقائياً
                        }
                    }
                }
            }

            // التحقق من وجود أمر العمل
            if (!empty($previewRow['work_order_number']) && !empty($previewRow['work_order_type_code'])) {
                $workOrder = $this->findWorkOrder($previewRow['work_order_number'], $previewRow['work_order_type_code']);
                if (!$workOrder) {
                    $previewRow['status'] = 'error';
                    $previewRow['errors'][] = 'أمر العمل غير موجود';
                    $errors[] = [
                        'row_number' => $rowNumber,
                        'extract_number' => $previewRow['extract_number'],
                        'message' => 'أمر العمل غير موجود: ' . $previewRow['work_order_number'] . ' (' . $previewRow['work_order_type_code'] . ')'
                    ];
                }
            }

            $totalAmount += $previewRow['extract_value'];
            $totalPenalty += $previewRow['penalty_amount'];

            $previewData[] = $previewRow;
        }

        // حساب المبالغ تلقائياً لكل مستخلص
        $previewData = $this->calculateExtractTotals($previewData);

        // إعادة حساب الإجماليات بعد الحساب التلقائي
        // ملاحظة: يجب جمع المبالغ لكل مستخلص مرة واحدة فقط (وليس لكل صف)
        $extractTotals = [];

        foreach ($previewData as $row) {
            if ($row['status'] === 'valid') {
                $extractNumber = $row['extract_number'];

                // حفظ المبالغ لكل مستخلص مرة واحدة فقط
                if (!isset($extractTotals[$extractNumber])) {
                    $extractTotals[$extractNumber] = [
                        'total_amount' => $row['total_amount'],
                        'tax_amount' => $row['tax_amount'],
                        'total_penalty_amount' => $row['total_penalty_amount'],
                        'net_amount' => $row['net_amount'],
                        'partial_extract_tax_amount' => $row['partial_extract_tax_amount'] ?? 0
                    ];
                }
            }
        }

        // جمع المبالغ من جميع المستخلصات
        $totalAmount = 0;
        $totalPenalty = 0;
        $totalTax = 0;
        $totalPartialTax = 0;
        $totalNet = 0;

        foreach ($extractTotals as $totals) {
            $totalAmount += $totals['total_amount'];
            $totalPenalty += $totals['total_penalty_amount'];
            $totalTax += $totals['tax_amount'];
            $totalPartialTax += $totals['partial_extract_tax_amount'];
            $totalNet += $totals['net_amount'];
        }

        // حساب الإحصائيات
        $calculations = [
            'total_rows' => count($data),
            'valid_rows' => count(array_filter($previewData, function($row) { return $row['status'] === 'valid'; })),
            'error_rows' => count(array_filter($previewData, function($row) { return $row['status'] === 'error'; })),
            'total_amount' => $totalAmount,
            'total_penalty' => $totalPenalty,
            'total_tax' => $totalTax,
            'total_partial_tax' => $totalPartialTax,
            'total_net' => $totalNet
        ];

        return [
            'data' => $previewData,
            'errors' => $errors,
            'warnings' => $warnings,
            'calculations' => $calculations
        ];
    }

    /**
     * قراءة ملف Excel
     */
    private function readExcelFile($filePath) {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // قراءة العناوين من الصف 4
            $headers = [];
            $headerRow = 4;
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $headerRow;
                $cellValue = $worksheet->getCell($cell)->getValue();
                if (!empty($cellValue)) {
                    $headers[$col] = trim($cellValue);
                }
            }
            
            // قراءة البيانات من الصف 5 فما فوق
            $data = [];
            $highestRow = $worksheet->getHighestRow();

            for ($row = 5; $row <= $highestRow; $row++) {
                $rowData = [];
                $isEmpty = true;

                foreach ($headers as $col => $header) {
                    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                    $cellValue = $worksheet->getCell($cell)->getValue();
                    $rowData[$header] = $cellValue;

                    if (!empty($cellValue)) {
                        $isEmpty = false;
                    }
                }
                
                // تجاهل الصفوف الفارغة
                if (!$isEmpty) {
                    $data[] = $rowData;
                }
            }
            
            return $data;
            
        } catch (Exception $e) {
            throw new Exception('خطأ في قراءة ملف Excel: ' . $e->getMessage());
        }
    }

    /**
     * حساب المبالغ تلقائياً لكل مستخلص
     */
    private function calculateExtractTotals($previewData) {
        // تجميع البيانات حسب رقم المستخلص
        $extractGroups = [];

        foreach ($previewData as $index => $row) {
            $extractNumber = $row['extract_number'];

            if (!isset($extractGroups[$extractNumber])) {
                $extractGroups[$extractNumber] = [];
            }

            $extractGroups[$extractNumber][] = $index;
        }

        // حساب المبالغ لكل مستخلص
        foreach ($extractGroups as $extractNumber => $indices) {
            $totalExtractValue = 0;
            $totalPenaltyAmount = 0;
            $partialExtractTaxAmount = 0;

            // جمع قيم أوامر العمل والغرامات
            foreach ($indices as $index) {
                if ($previewData[$index]['status'] === 'valid') {
                    $totalExtractValue += $previewData[$index]['extract_value'];
                    $totalPenaltyAmount += $previewData[$index]['penalty_amount'];

                    // جلب ضريبة المستخلص الجزئي المرتبط (مرة واحدة فقط)
                    if ($partialExtractTaxAmount == 0 && !empty($previewData[$index]['related_partial_extract_id'])) {
                        $partialExtractTaxAmount = $this->getPartialExtractTaxAmount($previewData[$index]['related_partial_extract_id']);
                    }
                }
            }

            // حساب المبالغ
            // الصافي = مجموع قيم أوامر العمل + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامة
            $taxAmount = $totalExtractValue * 0.15; // الضريبة 15%
            $netAmount = $totalExtractValue + $taxAmount + $partialExtractTaxAmount - $totalPenaltyAmount;

            // تحديث جميع الصفوف التابعة لهذا المستخلص
            foreach ($indices as $index) {
                $previewData[$index]['total_amount'] = $totalExtractValue;
                $previewData[$index]['tax_amount'] = $taxAmount;
                $previewData[$index]['total_penalty_amount'] = $totalPenaltyAmount;
                $previewData[$index]['net_amount'] = $netAmount;
                $previewData[$index]['partial_extract_tax_amount'] = $partialExtractTaxAmount;
            }
        }

        return $previewData;
    }

    /**
     * جلب ضريبة المستخلص الجزئي المرتبط
     */
    private function getPartialExtractTaxAmount($partialExtractId) {
        try {
            $stmt = $this->db->prepare("
                SELECT tax_amount
                FROM partial_extracts
                WHERE id = ?
            ");
            $stmt->execute([$partialExtractId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? floatval($result['tax_amount']) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * التحقق من صحة صف البيانات
     */
    private function validateRow($row) {
        $errors = [];

        // التحقق من الحقول المطلوبة
        if (empty($row['extract_number'])) {
            $errors[] = 'رقم المستخلص مطلوب';
        }

        // الفرع مطلوب فقط إذا لم يكن هناك مستخلص جزئي (سيتم جلبه تلقائياً من المستخلص الجزئي)
        if (empty($row['branch_name']) && empty($row['related_partial_extract_number'])) {
            $errors[] = 'الفرع أو المستخلص الجزئي المرتبط مطلوب';
        }

        if (empty($row['extract_date'])) {
            $errors[] = 'تاريخ المستخلص مطلوب';
        }

        if (empty($row['related_partial_extract_number'])) {
            $errors[] = 'المستخلص الجزئي المرتبط مطلوب';
        }

        if (empty($row['work_order_number'])) {
            $errors[] = 'رقم أمر العمل مطلوب';
        }

        if (empty($row['work_order_type_code'])) {
            $errors[] = 'نوع أمر العمل مطلوب';
        }

        // التحقق من القيم الرقمية
        // تم إيقاف شرط قيمة المستخلص الموجبة للسماح بالقيم السالبة والصفرية
        // if ($row['extract_value'] < 0) {
        //     $errors[] = 'قيمة المستخلص يجب أن تكون موجبة';
        // }

        if ($row['penalty_amount'] < 0) {
            $errors[] = 'الغرامة يجب أن تكون موجبة أو صفر';
        }

        return $errors;
    }

    /**
     * البحث عن المستخلص الجزئي
     */
    private function findPartialExtract($extractNumber) {
        $stmt = $this->db->prepare("
            SELECT id, branch_id, total_amount, tax_amount, net_amount
            FROM partial_extracts
            WHERE extract_number = ?
        ");
        $stmt->execute([$extractNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * البحث عن أمر العمل
     */
    private function findWorkOrder($workOrderNumber, $typeCode) {
        $stmt = $this->db->prepare("
            SELECT wo.id, wo.actual_value, wo.estimated_value
            FROM work_orders wo
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            WHERE wo.work_order_number = ? AND wot.type_code = ?
        ");
        $stmt->execute([$workOrderNumber, $typeCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * البحث عن الفرع
     */
    private function findBranch($branchName) {
        $stmt = $this->db->prepare("
            SELECT id FROM branches WHERE name = ? AND status = 'active'
        ");
        $stmt->execute([$branchName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * تحليل التاريخ من صيغ مختلفة
     */
    private function parseDate($dateValue) {
        if (empty($dateValue)) {
            return null;
        }

        // إذا كان رقم Excel
        if (is_numeric($dateValue)) {
            $unixDate = ($dateValue - 25569) * 86400;
            return date('Y-m-d', $unixDate);
        }

        // محاولة تحليل التاريخ
        $formats = [
            'Y-m-d', 'Y/m/d', 'Y.m.d',
            'd-m-Y', 'd/m/Y', 'd.m.Y',
            'm-d-Y', 'm/d/Y',
            'd-m-y', 'd/m/y',
            'Y-n-j', 'n/j/Y', 'j/n/Y'
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateValue);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        // محاولة أخيرة باستخدام strtotime
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * معالجة البيانات المؤكدة
     */
    private function processConfirmedData($previewData) {
        $this->db->beginTransaction();

        try {
            $currentExtract = null;
            $currentExtractId = null;
            $currentApprovalStage = null;

            foreach ($previewData as $row) {
                // تجاهل الصفوف التي بها أخطاء
                if ($row['status'] === 'error') {
                    continue;
                }

                // إنشاء أو تحديث المستخلص
                if ($currentExtract !== $row['extract_number']) {
                    // تحديث حالة أوامر العمل للمستخلص السابق إذا كان "مصروف"
                    if ($currentExtractId && $currentApprovalStage) {
                        $this->updateWorkOrdersStatusIfDisbursed($currentExtractId, $currentApprovalStage);
                    }

                    $currentExtract = $row['extract_number'];
                    $currentExtractId = $this->createOrUpdateExtract($row);
                    $currentApprovalStage = $this->getApprovalStageKey($row['approval_stage']);
                }

                // إضافة أمر العمل للمستخلص
                if ($currentExtractId) {
                    $this->addWorkOrderToExtract($currentExtractId, $row);
                }
            }

            // تحديث حالة أوامر العمل للمستخلص الأخير إذا كان "مصروف"
            if ($currentExtractId && $currentApprovalStage) {
                $this->updateWorkOrdersStatusIfDisbursed($currentExtractId, $currentApprovalStage);
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * إنشاء أو تحديث المستخلص
     */
    private function createOrUpdateExtract($row) {
        // البحث عن المستخلص
        $stmt = $this->db->prepare("
            SELECT id FROM final_for_partial_extracts WHERE extract_number = ?
        ");
        $stmt->execute([$row['extract_number']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // البحث عن المستخلص الجزئي أولاً
        $partialExtract = $this->findPartialExtract($row['related_partial_extract_number']);
        if (!$partialExtract) {
            throw new Exception('المستخلص الجزئي غير موجود: ' . $row['related_partial_extract_number']);
        }

        // تحديد الفرع: إما من الملف أو من المستخلص الجزئي
        $branchId = null;
        if (!empty($row['branch_name'])) {
            // إذا كان الفرع محدداً في الملف، استخدمه
            $branch = $this->findBranch($row['branch_name']);
            if (!$branch) {
                throw new Exception('الفرع غير موجود: ' . $row['branch_name']);
            }
            $branchId = $branch['id'];
        } else {
            // إذا لم يكن محدداً، استخدم فرع المستخلص الجزئي
            $branchId = $partialExtract['branch_id'];
        }

        if ($existing) {
            // تحديث المستخلص الموجود مع تحديث القسم من المستخلص الجزئي المرتبط
            $stmt = $this->db->prepare("
                UPDATE final_for_partial_extracts
                SET branch_id = ?,
                    extract_date = ?,
                    related_partial_extract_id = (SELECT id FROM partial_extracts WHERE extract_number = ?),
                    department = (SELECT department FROM partial_extracts WHERE extract_number = ?),
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
                $row['extract_date'],
                $row['related_partial_extract_number'],
                $row['related_partial_extract_number'], // لجلب القسم
                $row['total_amount'],
                $row['tax_amount'],
                $row['total_penalty_amount'],
                $row['net_amount'],
                $this->getApprovalStageKey($row['approval_stage']),
                $existing['id']
            ]);

            // حذف أوامر العمل القديمة
            $stmt = $this->db->prepare("
                DELETE FROM final_for_partial_extract_work_orders
                WHERE final_for_partial_extract_id = ?
            ");
            $stmt->execute([$existing['id']]);

            $this->updatedWorkOrders[] = $row['extract_number'];

            return $existing['id'];

        } else {
            // إنشاء مستخلص جديد مع جلب القسم من المستخلص الجزئي المرتبط
            $stmt = $this->db->prepare("
                INSERT INTO final_for_partial_extracts (
                    extract_number, branch_id, extract_date, related_partial_extract_id,
                    department, total_amount, tax_amount, total_penalty_amount, net_amount,
                    approval_stage, created_by, created_at
                ) VALUES (?, ?, ?,
                    (SELECT id FROM partial_extracts WHERE extract_number = ?),
                    (SELECT department FROM partial_extracts WHERE extract_number = ?),
                    ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $row['extract_number'],
                $branchId,
                $row['extract_date'],
                $row['related_partial_extract_number'],
                $row['related_partial_extract_number'], // لجلب القسم
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
     * ملاحظة: لا نحتاج لتاريخ الإنجاز لأنه موجود في المستخلص الجزئي
     */
    private function addWorkOrderToExtract($extractId, $row) {
        // البحث عن أمر العمل
        $workOrder = $this->findWorkOrder($row['work_order_number'], $row['work_order_type_code']);
        if (!$workOrder) {
            throw new Exception('أمر العمل غير موجود: ' . $row['work_order_number']);
        }

        // الحصول على تاريخ الإنجاز من المستخلص الجزئي
        $stmt = $this->db->prepare("
            SELECT pewo.completion_date
            FROM partial_extract_work_orders pewo
            JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
            WHERE pe.extract_number = ? AND pewo.work_order_id = ?
        ");
        $stmt->execute([$row['related_partial_extract_number'], $workOrder['id']]);
        $partialWorkOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$partialWorkOrder) {
            throw new Exception('أمر العمل غير موجود في المستخلص الجزئي: ' . $row['work_order_number']);
        }

        // إضافة أمر العمل للمستخلص النهائي
        $stmt = $this->db->prepare("
            INSERT INTO final_for_partial_extract_work_orders (
                final_for_partial_extract_id, work_order_id, completion_date,
                extract_value, penalty_amount, added_by, added_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $extractId,
            $workOrder['id'],
            $partialWorkOrder['completion_date'], // استخدام تاريخ الإنجاز من المستخلص الجزئي
            $row['extract_value'],
            $row['penalty_amount'],
            $this->userId
        ]);
    }

    /**
     * تحديث حالة أوامر العمل إلى "مكتمل" إذا كانت مرحلة الاعتماد "مصروف"
     */
    private function updateWorkOrdersStatusIfDisbursed($extractId, $approvalStage) {
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
                FROM final_for_partial_extract_work_orders ffpewo
                INNER JOIN work_orders wo ON ffpewo.work_order_id = wo.id
                WHERE ffpewo.final_for_partial_extract_id = ?
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
                error_log("Import: Updated $updatedCount work orders to 'completed' status for final-for-partial extract $extractId");
            }
        } catch (Exception $e) {
            error_log("Error updating work orders status: " . $e->getMessage());
            // لا نرمي الخطأ لأن هذا ليس حرجاً للاستيراد
        }
    }

    /**
     * معالجة البيانات (الطريقة القديمة)
     */
    private function processData($validatedData) {
        $this->processConfirmedData($validatedData);
    }

    /**
     * التحقق من صحة البيانات (الطريقة القديمة)
     */
    private function validateData($data) {
        $result = $this->preparePreviewData($data);

        // فلترة الصفوف الصحيحة فقط
        return array_filter($result['data'], function($row) {
            return $row['status'] === 'valid';
        });
    }

    /**
     * الحصول على مفتاح مرحلة الاعتماد من قاعدة البيانات
     */
    private function getApprovalStageKey($stageText) {
        // محاولة جلب مفتاح المرحلة من قاعدة البيانات
        try {
            $stmt = $this->db->prepare("
                SELECT stage_key
                FROM approval_stages
                WHERE stage_name = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$stageText]);
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
            'مسودة' => 'draft',
            'المساندة الفنية' => 'technical_support',
            'الإنشاءات' => 'construction',
            'مدير الدائرة' => 'department_manager',
            'مدير الإدارة' => 'administration_manager',
            'مالية الطائف' => 'taif_finance',
            'مصروف' => 'disbursed'
        ];

        return $stages[$stageText] ?? 'draft';
    }

    /**
     * الحصول على إحصائيات الاستيراد
     */
    private function getImportStats() {
        return [
            'total_records' => count($this->processedExtracts) + count($this->updatedWorkOrders),
            'successful_records' => count($this->processedExtracts) + count($this->updatedWorkOrders),
            'failed_records' => count($this->errors),
            'extracts_processed' => count($this->processedExtracts),
            'updates_made' => count($this->updatedWorkOrders),
            'duplicates_found' => count($this->duplicateWorkOrders)
        ];
    }

    /**
     * الحصول على إجمالي أوامر العمل
     */
    private function getTotalWorkOrders() {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM final_for_partial_extract_work_orders
            WHERE final_for_partial_extract_id IN (
                SELECT id FROM final_for_partial_extracts
                WHERE extract_number IN ('" . implode("','", $this->processedExtracts) . "')
            )
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * الحصول على التكرارات
     */
    public function getDuplicates() {
        return $this->duplicateWorkOrders;
    }

    /**
     * بدء تسجيل عملية الاستيراد
     */
    private function startImportLog($fileName) {
        $stmt = $this->db->prepare("
            INSERT INTO final_for_partial_extract_import_export_logs
            (user_id, operation_type, file_name, status, started_at)
            VALUES (?, 'import', ?, 'processing', NOW())
        ");

        $stmt->execute([$this->userId, $fileName]);

        return $this->db->lastInsertId();
    }

    /**
     * إنهاء تسجيل عملية الاستيراد
     */
    private function completeImportLog($success, $errorMessage = null) {
        $status = $success ? 'completed' : 'failed';
        $stats = $this->getImportStats();

        $stmt = $this->db->prepare("
            UPDATE final_for_partial_extract_import_export_logs
            SET status = ?,
                completed_at = NOW(),
                total_records = ?,
                successful_records = ?,
                failed_records = ?,
                duplicates_found = ?,
                error_message = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $stats['total_records'],
            $stats['successful_records'],
            $stats['failed_records'],
            $stats['duplicates_found'],
            $errorMessage,
            $this->logId
        ]);
    }
}
