<?php

declare(strict_types=1);

/**
 * استيراد أوامر العمل مع النماذج المرفقة (تجاهل المستخلصات فقط)
 * Import Work Orders with Attachments (excluding extracts only)
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'استيراد أوامر العمل';
$currentPage = 'work-orders';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أوامر العمل', 'url' => 'work-orders/index.php'],
    ['title' => 'استيراد البيانات', 'url' => 'work-orders/import.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_import')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$error = '';
$success = '';

// معالجة رفع الملف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    try {
        $uploadedFile = $_FILES['import_file'];
        
        // التحقق من الملف
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطأ في رفع الملف');
        }
        
        $fileName = $uploadedFile['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
            throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف CSV أو Excel');
        }
        
        // قراءة الملف
        $filePath = $uploadedFile['tmp_name'];
        $importData = [];

        if ($fileExtension === 'csv') {
            $importData = readCSVFile($filePath);
        } elseif (in_array($fileExtension, ['xlsx', 'xls'])) {
            $importData = readExcelFile($filePath);
        } else {
            throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف CSV أو Excel');
        }

        if (empty($importData)) {
            throw new Exception('الملف فارغ أو لا يحتوي على بيانات صحيحة');
        }

        // تحليل البيانات وإنشاء معاينة
        $preview = analyzeImportData($importData);

        // حفظ البيانات في الجلسة للمعاينة
        $_SESSION['import_preview'] = $preview;
        $_SESSION['import_filename'] = $fileName;

        // إعادة توجيه لصفحة المعاينة
        header('Location: import-preview.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * قراءة ملف CSV
 */
function readCSVFile(string $filePath): array
{
    $data = [];

    // قراءة الملف مع دعم الترميز العربي
    $content = file_get_contents($filePath);

    // إزالة BOM إذا كان موجود
    $content = str_replace("\xEF\xBB\xBF", '', $content);

    // تحويل الترميز إلى UTF-8 إذا لزم الأمر
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    }

    // تقسيم المحتوى إلى أسطر
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });

    if (empty($lines)) {
        return $data;
    }

    // قراءة العناوين
    $headers = str_getcsv(array_shift($lines));

    // تنظيف العناوين
    $headers = array_map(function($header) {
        return trim($header, " \t\n\r\0\x0B\"");
    }, $headers);

    // قراءة البيانات
    foreach ($lines as $lineNumber => $line) {
        $row = str_getcsv($line);

        // التأكد من وجود بيانات (على الأقل الأعمدة الأساسية)
        if (count($row) > 0 && !empty(trim($row[0]))) {
            $rowData = [];
            // قراءة جميع الأعمدة المتاحة
            for ($i = 0; $i < count($headers); $i++) {
                $rowData[$headers[$i]] = isset($row[$i]) ? trim($row[$i]) : '';
            }
            $rowData['_row_number'] = $lineNumber + 2; // +2 لأن العناوين في الصف الأول والفهرس يبدأ من 0
            $data[] = $rowData;
        }
    }

    return $data;
}

/**
 * قراءة ملف Excel (xlsx, xls)
 */
function readExcelFile(string $filePath): array
{
    require_once __DIR__ . '/../../vendor/autoload.php';

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        // الحصول على أعلى صف وعمود
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        if ($highestRow < 2) {
            throw new Exception('الملف فارغ');
        }

        // قراءة العناوين من الصف الأول
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cellValue = $worksheet->getCell($columnLetter . '1')->getValue();
            $headers[] = trim($cellValue, " \t\n\r\0\x0B\"");
        }

        $data = [];
        // قراءة البيانات من الصف الثاني فصاعداً
        for ($row = 2; $row <= $highestRow; $row++) {
            // قراءة القيمة الأولى للتحقق من وجود بيانات
            $firstCellValue = $worksheet->getCell('A' . $row)->getValue();

            if (!empty($firstCellValue)) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cell = $worksheet->getCell($columnLetter . $row);

                    // الحصول على القيمة الخام (بدون تنسيق)
                    $value = $cell->getCalculatedValue();

                    // معالجة القيم الرقمية
                    if (is_numeric($value)) {
                        // إذا كانت القيمة رقم صحيح (بدون كسور عشرية)
                        if (floor($value) == $value) {
                            $value = (string)intval($value);
                        } else {
                            // رقم عشري - الاحتفاظ بالقيمة كما هي
                            $value = (string)$value;
                        }
                    } else {
                        // تحويل إلى نص
                        $value = (string)$value;
                    }

                    $headerIndex = $col - 1;
                    if (isset($headers[$headerIndex])) {
                        $rowData[$headers[$headerIndex]] = trim($value);
                    }
                }
                $rowData['_row_number'] = $row;
                $data[] = $rowData;
            }
        }

        return $data;

    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        throw new Exception('خطأ في قراءة ملف Excel: ' . $e->getMessage());
    } catch (Exception $e) {
        throw new Exception('خطأ في معالجة ملف Excel: ' . $e->getMessage());
    }
}

/**
 * تحليل بيانات الاستيراد
 */
function analyzeImportData(array $importData): array
{
    $db = getDB();
    
    $newRecords = [];
    $updateRecords = [];
    $errorRecords = [];
    $validRecords = [];

    foreach ($importData as $index => $record) {
        $rowNumber = $record['_row_number'] ?? ($index + 2);
        $errors = [];

        // التحقق من البيانات المطلوبة
        $workOrderNumber = trim($record['رقم أمر العمل'] ?? '');
        if (empty($workOrderNumber)) {
            $errors[] = 'رقم أمر العمل مطلوب';
        } elseif (!preg_match('/^\d{9}$/', $workOrderNumber)) {
            $errors[] = 'رقم أمر العمل يجب أن يكون مكون من 9 أرقام فقط';
        }

        // التحقق من نوع أمر العمل
        $workOrderTypeCode = trim($record['كود نوع الأمر'] ?? '');
        $workOrderTypeId = null;
        if (!empty($workOrderTypeCode)) {
            $stmt = $db->prepare("SELECT id FROM work_order_types WHERE type_code = ? AND status = 'active'");
            $stmt->execute([$workOrderTypeCode]);
            $workOrderTypeId = $stmt->fetchColumn();
            
            if (!$workOrderTypeId) {
                $errors[] = "نوع أمر العمل '{$workOrderTypeCode}' غير موجود أو غير نشط";
            }
        } else {
            $errors[] = 'كود نوع الأمر مطلوب';
        }

        // التحقق من القسم
        $department = trim($record['القسم'] ?? '');
        if (!in_array($department, ['التوصيلات', 'المشاريع'])) {
            $errors[] = 'القسم يجب أن يكون "التوصيلات" أو "المشاريع"';
        }
        $departmentCode = $department === 'التوصيلات' ? 'connections' : 'projects';

        // التحقق من الفرع
        $branchName = trim($record['الفرع'] ?? '');
        $branchId = null;
        if (!empty($branchName)) {
            $stmt = $db->prepare("SELECT id FROM branches WHERE name = ?");
            $stmt->execute([$branchName]);
            $branchId = $stmt->fetchColumn();
            
            if (!$branchId) {
                $errors[] = "الفرع '{$branchName}' غير موجود";
            }
        } else {
            $errors[] = 'الفرع مطلوب';
        }

        // التحقق من الجهة الحالية (اختيارية)
        $currentEntityName = trim($record['الجهة الحالية'] ?? '');
        $currentEntityId = null;
        if (!empty($currentEntityName)) {
            $stmt = $db->prepare("SELECT id FROM current_entities WHERE name = ? AND is_active = 1");
            $stmt->execute([$currentEntityName]);
            $currentEntityId = $stmt->fetchColumn();
            
            if (!$currentEntityId) {
                $errors[] = "الجهة الحالية '{$currentEntityName}' غير موجودة أو غير نشطة";
            }
        }

        // التحقق من القيم المالية
        $estimatedValue = floatval($record['القيمة المقدرة'] ?? 0);
        $actualValue = floatval($record['القيمة الفعلية'] ?? 0);

        if ($estimatedValue < 0) {
            $errors[] = 'القيمة المقدرة لا يمكن أن تكون سالبة';
        }

        if ($actualValue < 0) {
            $errors[] = 'القيمة الفعلية لا يمكن أن تكون سالبة';
        }

        // التحقق من حالة الصرف
        $disbursementStatusText = trim($record['حالة الصرف'] ?? 'لا يوجد');
        $disbursementStatus = translateDisbursementStatusFromArabic($disbursementStatusText);

        // التحقق من الحالة
        $statusText = trim($record['الحالة'] ?? '');
        // إذا كانت الحالة فارغة، استخدم 'نشط' كقيمة افتراضية
        if (empty($statusText)) {
            $statusText = 'نشط';
        }

        // التحقق من أن القيمة ليست من أعمدة أخرى (مثل حالة المرفقات)
        $attachmentStatuses = ['مرفق', 'غير مرفق', 'لا ينطبق', 'attached', 'not_attached', 'not_applicable'];
        if (in_array($statusText, $attachmentStatuses)) {
            $errors[] = "عمود 'الحالة' يحتوي على قيمة خاطئة '{$statusText}'. يبدو أن ترتيب الأعمدة في ملف CSV غير صحيح. يرجى تحميل ملف نموذجي جديد أو التأكد من ترتيب الأعمدة.";
            $statusText = 'نشط'; // استخدام القيمة الافتراضية
        }

        $status = translateStatusFromArabic($statusText);

        // التحقق من صحة الحالة
        if (!in_array($status, ['active', 'inactive', 'completed', 'cancelled'])) {
            $errors[] = "الحالة '{$statusText}' غير صحيحة. القيم المقبولة: نشط، غير نشط، مكتمل، ملغي";
        }

        // التحقق من صحة التواريخ
        $assignmentDateText = trim($record['تاريخ التكليف'] ?? '');
        if (!empty($assignmentDateText)) {
            $parsedAssignmentDate = parseDate($assignmentDateText);
            if ($parsedAssignmentDate === null) {
                $errors[] = "تاريخ التكليف '{$assignmentDateText}' غير صحيح. الصيغ المدعومة: YYYY-MM-DD أو DD/MM/YYYY أو DD-MM-YYYY أو YYYYMMDD أو DDMMYYYY أو أرقام Excel";
            }
        }

        $receiptDateText = trim($record['تاريخ الاستلام'] ?? '');
        if (!empty($receiptDateText)) {
            $parsedReceiptDate = parseDate($receiptDateText);
            if ($parsedReceiptDate === null) {
                $errors[] = "تاريخ الاستلام '{$receiptDateText}' غير صحيح. الصيغ المدعومة: YYYY-MM-DD أو DD/MM/YYYY أو DD-MM-YYYY أو YYYYMMDD أو DDMMYYYY أو أرقام Excel";
            }
        }

        // إذا كانت هناك أخطاء، إضافة السجل للأخطاء
        if (!empty($errors)) {
            $errorRecords[] = [
                'row_number' => $rowNumber,
                'data' => $record,
                'error' => implode('; ', $errors)
            ];
            continue;
        }

        // معالجة النماذج المرفقة
        $attachments = [];
        $formTypes = [
            'نموذج الحفر الدقيق' => 'precise_drilling_form',
            'نموذج الكشط' => 'excavation_form',
            'نموذج التخريد' => 'demolition_form',
            'نموذج F1' => 'f1_form',
            'شهادة الإنجاز' => 'completion_certificate'
        ];

        foreach ($formTypes as $arabicName => $formType) {
            if (isset($record[$arabicName])) {
                $attachmentStatusText = trim($record[$arabicName]);
                $attachmentStatus = translateAttachmentStatusFromArabic($attachmentStatusText);
                if ($attachmentStatus) {
                    $attachments[$formType] = [
                        'status' => $attachmentStatus,
                        'confirmation' => null,
                        'certificate_attached_date' => null,
                        'certificate_confirmed_date' => null
                    ];
                }
            }
        }

        // معالجة تأكيد شهادة الإنجاز
        if (isset($record['تأكيد شهادة الإنجاز'])) {
            $confirmationText = trim($record['تأكيد شهادة الإنجاز']);

            // إذا لم تكن شهادة الإنجاز موجودة، أنشئها
            if (!isset($attachments['completion_certificate'])) {
                // إذا كان هناك تأكيد، نفترض أن الشهادة مرفقة
                $attachments['completion_certificate'] = [
                    'status' => 'attached',
                    'confirmation' => null,
                    'certificate_attached_date' => null,
                    'certificate_confirmed_date' => null
                ];
            }

            $confirmation = translateConfirmationStatusFromArabic($confirmationText);
            if ($confirmation) {
                $attachments['completion_certificate']['confirmation'] = $confirmation;
            } elseif (!empty($confirmationText)) {
                // إضافة تحذير إذا كانت القيمة غير معروفة
                $errors[] = "قيمة تأكيد الشهادة غير معروفة: '{$confirmationText}' (القيم المقبولة: فارغ، مؤكد، مقبول، مرفوض)";
            }
        }

        // معالجة تاريخ ارفاق شهادة الإنجاز
        if (isset($record['تاريخ ارفاق شهادة الإنجاز'])) {
            $attachedDateText = trim($record['تاريخ ارفاق شهادة الإنجاز']);
            if (!empty($attachedDateText)) {
                $parsedDate = parseDate($attachedDateText);
                if ($parsedDate) {
                    if (!isset($attachments['completion_certificate'])) {
                        $attachments['completion_certificate'] = [
                            'status' => 'attached',
                            'confirmation' => null,
                            'certificate_attached_date' => null,
                            'certificate_confirmed_date' => null
                        ];
                    }
                    $attachments['completion_certificate']['certificate_attached_date'] = $parsedDate;
                } else {
                    $errors[] = "تاريخ ارفاق شهادة الإنجاز '{$attachedDateText}' غير صحيح";
                }
            }
        }

        // معالجة تاريخ تأكيد شهادة الإنجاز
        if (isset($record['تاريخ تأكيد شهادة الإنجاز'])) {
            $confirmedDateText = trim($record['تاريخ تأكيد شهادة الإنجاز']);
            if (!empty($confirmedDateText)) {
                $parsedDate = parseDate($confirmedDateText);
                if ($parsedDate) {
                    if (!isset($attachments['completion_certificate'])) {
                        $attachments['completion_certificate'] = [
                            'status' => 'attached',
                            'confirmation' => null,
                            'certificate_attached_date' => null,
                            'certificate_confirmed_date' => null
                        ];
                    }
                    $attachments['completion_certificate']['certificate_confirmed_date'] = $parsedDate;
                } else {
                    $errors[] = "تاريخ تأكيد شهادة الإنجاز '{$confirmedDateText}' غير صحيح";
                }
            }
        }

        // إنشاء السجل النظيف
        $cleanRecord = [
            'work_order_number' => $workOrderNumber,
            'work_order_type_id' => $workOrderTypeId,
            'department' => $departmentCode,
            'current_entity_id' => $currentEntityId,
            'branch_id' => $branchId,
            'location' => trim($record['الموقع'] ?? ''),
            'assignment_date' => parseDate($record['تاريخ التكليف'] ?? ''),
            'receipt_date' => parseDate($record['تاريخ الاستلام'] ?? ''),
            'estimated_value' => $estimatedValue,
            'actual_value' => $actualValue,
            'disbursement_status' => $disbursementStatus,
            'status' => $status,
            'notes' => trim($record['الملاحظات'] ?? ''),
            'attachments' => $attachments
        ];

        // التحقق من وجود رقم أمر العمل مع نوع أمر العمل في قاعدة البيانات
        // المفتاح الفريد هو: رقم أمر العمل + نوع أمر العمل
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE work_order_number = ? AND work_order_type_id = ?");
        $checkStmt->execute([$cleanRecord['work_order_number'], $cleanRecord['work_order_type_id']]);

        if ($checkStmt->fetchColumn() > 0) {
            // سجل موجود (نفس رقم أمر العمل ونفس نوع أمر العمل) - سيتم تحديثه
            $updateRecords[] = $cleanRecord;
        } else {
            // سجل جديد (رقم أمر العمل جديد أو نفس رقم أمر العمل مع نوع مختلف)
            $newRecords[] = $cleanRecord;
        }

        $validRecords[] = $cleanRecord;
    }

    return [
        'total_records' => count($importData),
        'new_records' => $newRecords,
        'update_records' => $updateRecords,
        'error_records' => $errorRecords,
        'valid_records' => $validRecords
    ];
}

// دوال مساعدة للترجمة من العربية
function translateDisbursementStatusFromArabic($status) {
    $statuses = [
        'لا يوجد' => 'none',
        'مكتمل' => 'completed',
        'صرف' => 'disbursement',
        'إرجاع' => 'return',
        'صرف وإرجاع' => 'disbursement_return_completed'
    ];
    return $statuses[$status] ?? 'none';
}

function translateStatusFromArabic($status) {
    $statuses = [
        'نشط' => 'active',
        'غير نشط' => 'inactive',
        'مكتمل' => 'completed',
        'ملغي' => 'cancelled'
    ];
    return $statuses[$status] ?? 'active';
}

function translateAttachmentStatusFromArabic($status) {
    $statuses = [
        'مرفق' => 'attached',
        'غير مرفق' => 'not_attached',
        'لا ينطبق' => 'not_applicable'
    ];

    return $statuses[$status] ?? null;
}

function translateConfirmationStatusFromArabic($status) {
    // تنظيف النص من المسافات الزائدة والأحرف الخاصة
    $status = trim($status);
    $status = preg_replace('/\s+/', ' ', $status); // توحيد المسافات

    $statuses = [
        'فارغ' => 'empty',
        'مؤكد' => 'confirmed',
        'مقبول' => 'accepted',
        'مرفوض' => 'rejected'
    ];

    // البحث المباشر
    if (isset($statuses[$status])) {
        return $statuses[$status];
    }

    // البحث غير الحساس لحالة الأحرف والمسافات
    $statusLower = mb_strtolower($status, 'UTF-8');
    foreach ($statuses as $arabicStatus => $englishStatus) {
        if (mb_strtolower($arabicStatus, 'UTF-8') === $statusLower) {
            return $englishStatus;
        }

        // البحث الجزئي (يحتوي على)
        if (mb_strpos($statusLower, mb_strtolower($arabicStatus, 'UTF-8')) !== false) {
            return $englishStatus;
        }
    }

    return null;
}

function parseDate($dateString) {
    if (empty($dateString) || trim($dateString) === '') {
        return null;
    }

    $dateString = trim($dateString);

    // التحقق من أن المدخل هو رقم تسلصلي من Excel
    if (is_numeric($dateString)) {
        $excelDate = (int)$dateString;
        // أرقام Excel التسلسلية عادة تكون بين 1 و 60000 تقريباً
        if ($excelDate > 0 && $excelDate < 100000) {
            // تحويل رقم Excel إلى تاريخ
            // Excel يبدأ من 1 يناير 1900
            $excelEpoch = new DateTime('1899-12-30'); // اليوم 0 في Excel
            $date = clone $excelEpoch;
            $date->add(new DateInterval('P' . $excelDate . 'D'));
            $year = (int)$date->format('Y');

            if ($year >= 1900 && $year <= 2100) {
                return $date->format('Y-m-d');
            }
        }
    }

    // محاولة تحليل التاريخ بأنماط مختلفة مع التحقق من صحة التاريخ
    $formats = [
        // الصيغ الأساسية
        'Y-m-d',        // 2024-01-15
        'd/m/Y',        // 15/01/2024
        'd-m-Y',        // 15-01-2024
        'Y/m/d',        // 2024/01/15
        'm/d/Y',        // 01/15/2024 (US format)
        'd.m.Y',        // 15.01.2024
        'Y.m.d',        // 2024.01.15
        // صيغ إضافية
        'd/m/y',        // 15/01/24
        'd-m-y',        // 15-01-24
        'm/d/y',        // 01/15/24
        'Y-m-d H:i:s',  // 2024-01-15 10:30:45
        'd/m/Y H:i:s',  // 15/01/2024 10:30:45
        'd-m-Y H:i:s',  // 15-01-2024 10:30:45
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);

        // التحقق من أن التاريخ صحيح وأن التحليل تم بنجاح
        if ($date && $date->format($format) === $dateString) {
            // التحقق من أن التاريخ منطقي (ليس قبل 1900 أو بعد 2100)
            $year = (int)$date->format('Y');
            if ($year >= 1900 && $year <= 2100) {
                return $date->format('Y-m-d');
            }
        }
    }

    // محاولة أخيرة مع التحقق من صحة التاريخ باستخدام regex
    try {
        // صيغة YYYY-MM-DD (مع فواصل مختلفة)
        if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/', $dateString, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة DD/MM/YYYY أو DD-MM-YYYY أو DD.MM.YYYY
        if (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة MM/DD/YYYY أو MM-DD-YYYY
        if (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/', $dateString, $matches)) {
            // محاولة أولاً كـ DD/MM/YYYY
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            // إذا فشلت، جرب كـ MM/DD/YYYY
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة DD/MM/YY أو DD-MM-YY (سنة بحرفين)
        if (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{2})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            // تحويل السنة ذات الحرفين إلى 4 أحرف
            if ($year < 50) {
                $year += 2000;
            } else {
                $year += 1900;
            }

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة بدون فواصل: YYYYMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateString, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة بدون فواصل: DDMMYYYY
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة YYYY-MM-DD HH:MM:SS
        if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $dateString, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة DD/MM/YYYY HH:MM:SS
        if (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة بدون فواصل: YYYYMMDD
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateString, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // صيغة بدون فواصل: DDMMYYYY
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $dateString, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year) && $year >= 1900 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

    } catch (Exception $e) {
        // تجاهل الخطأ
    }

    // إذا فشل كل شيء، إرجاع null
    return null;
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-upload me-2"></i>
            استيراد أوامر العمل
        </h1>
        <p class="text-muted mb-0">رفع ملف CSV لاستيراد أوامر العمل مع النماذج المرفقة (يتم تجاهل المستخلصات فقط)</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            العودة للقائمة
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Import Instructions -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-info-circle me-2"></i>
            تعليمات الاستيراد
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary">متطلبات الملف:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>ملف CSV بترميز UTF-8</li>
                    <li><i class="fas fa-check text-success me-2"></i>الحد الأقصى لحجم الملف: 10MB</li>
                    <li><i class="fas fa-check text-success me-2"></i>يجب أن يحتوي على العناوين في الصف الأول</li>
                </ul>

                <h6 class="text-primary mt-3">الحقول المطلوبة:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-asterisk text-danger me-2" style="font-size: 0.6em;"></i>رقم أمر العمل</li>
                    <li><i class="fas fa-asterisk text-danger me-2" style="font-size: 0.6em;"></i>كود نوع الأمر</li>
                    <li><i class="fas fa-asterisk text-danger me-2" style="font-size: 0.6em;"></i>القسم</li>
                    <li><i class="fas fa-asterisk text-danger me-2" style="font-size: 0.6em;"></i>الفرع</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="text-warning">ملاحظات مهمة:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-exclamation-triangle text-warning me-2"></i>سيتم تجاهل أعمدة المستخلصات عند الاستيراد</li>
                    <li><i class="fas fa-check text-success me-2"></i>سيتم استيراد حالة النماذج المرفقة</li>
                    <li><i class="fas fa-info-circle text-info me-2"></i>يمكن تحديث أوامر العمل الموجودة</li>
                    <li><i class="fas fa-info-circle text-info me-2"></i>ستتم معاينة البيانات قبل الاستيراد</li>
                </ul>

                <div class="alert alert-warning mt-3 mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>تنبيه:</strong> يجب استخدام ملف نموذجي حديث أو ملف مُصدّر حديثاً لضمان توافق ترتيب الأعمدة.
                    عمود "الحالة" يجب أن يكون في الموقع الصحيح (قبل أعمدة المستخلصات).
                </div>

                <h6 class="text-info mt-3">القيم المقبولة:</h6>
                <ul class="list-unstyled small">
                    <li><strong>القسم:</strong> التوصيلات، المشاريع</li>
                    <li><strong>الحالة:</strong> نشط، غير نشط، مكتمل، ملغي</li>
                    <li><strong>حالة الصرف:</strong> لا يوجد، مكتمل، صرف، إرجاع</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Upload Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-upload me-2"></i>
            رفع ملف الاستيراد
        </h5>
    </div>
    <div class="card-body">
        <form id="importForm" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="import_file" class="form-label">
                            اختر ملف CSV أو اسحبه هنا <span class="text-danger">*</span>
                        </label>

                        <!-- منطقة السحب والإفلات -->
                        <div id="dropZone" class="drop-zone border-2 border-dashed border-secondary rounded p-4 text-center mb-3">
                            <div class="drop-zone-content">
                                <div class="upload-icon-container mb-3">
                                    <i class="fas fa-cloud-upload-alt fa-4x text-primary"></i>
                                    <div class="upload-animation d-none">
                                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                                    </div>
                                </div>
                                <h4 class="text-dark mb-2">اسحب ملف CSV هنا</h4>
                                <p class="text-muted mb-3">أو انقر للاختيار من جهازك</p>
                                <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('import_file').click()">
                                    <i class="fas fa-folder-open me-2"></i>
                                    اختر ملف من الجهاز
                                </button>
                                <div class="mt-4">
                                    <div class="row justify-content-center">
                                        <div class="col-auto">
                                            <small class="text-muted d-flex align-items-center">
                                                <i class="fas fa-file-csv me-2 text-success"></i>
                                                CSV
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <small class="text-muted d-flex align-items-center">
                                                <i class="fas fa-file-excel me-2 text-success"></i>
                                                Excel
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <small class="text-muted d-flex align-items-center">
                                                <i class="fas fa-weight-hanging me-2 text-info"></i>
                                                حتى 10MB
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- معلومات الملف المحدد -->
                            <div id="fileInfo" class="file-info d-none">
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-file-check me-2"></i>
                                    <span id="fileName"></span>
                                    <span id="fileSize" class="text-muted ms-2"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFile()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <input type="file" class="d-none" id="import_file" name="import_file"
                               accept=".csv,.xlsx,.xls" required>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-upload me-2"></i>
                        بدء الاستيراد
                    </button>
                </div>
            </div>
        </form>

        <hr>

        <div class="text-center">
            <a href="download-sample.php" class="btn btn-outline-primary btn-sm me-2">
                <i class="fas fa-download me-2"></i>
                تحميل نموذج CSV
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list me-2"></i>
                عرض أوامر العمل
            </a>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<script>
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('import_file');
    const file = fileInput.files[0];

    if (!file) {
        e.preventDefault();
        alert('يرجى اختيار ملف للاستيراد');
        return;
    }

    // التحقق من حجم الملف (10MB)
    if (file.size > 10 * 1024 * 1024) {
        e.preventDefault();
        alert('حجم الملف كبير جداً. الحد الأقصى 10MB');
        return;
    }

    // التحقق من نوع الملف
    const allowedTypes = ['.csv', '.xlsx', '.xls'];
    const fileName = file.name.toLowerCase();
    const isValidType = allowedTypes.some(type => fileName.endsWith(type));

    if (!isValidType) {
        e.preventDefault();
        alert('نوع الملف غير مدعوم. يرجى رفع ملف CSV أو Excel');
        return;
    }

    // إظهار مؤشر التحميل
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جارٍ الرفع...';

    // إعادة تفعيل الزر بعد فترة (في حالة عدم إعادة التوجيه)
    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 30000);
});

// وظائف السحب والإفلات
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('import_file');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');

// جعل منطقة السحب قابلة للنقر بالكامل
dropZone.addEventListener('click', function(e) {
    // تجنب النقر المزدوج على الزر
    if (!e.target.closest('button')) {
        fileInput.click();
    }
});

// منع السلوك الافتراضي للمتصفح
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// إضافة تأثيرات بصرية عند السحب
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('border-primary');
    dropZone.classList.remove('border-secondary');

    // إضافة تأثير بصري للأيقونة
    const uploadIcon = dropZone.querySelector('.fa-cloud-upload-alt');
    if (uploadIcon) {
        uploadIcon.style.color = '#0d6efd';
    }
}

function unhighlight(e) {
    dropZone.classList.remove('border-primary');
    dropZone.classList.add('border-secondary');

    // إعادة تعيين لون الأيقونة
    const uploadIcon = dropZone.querySelector('.fa-cloud-upload-alt');
    if (uploadIcon) {
        uploadIcon.style.color = '';
    }
}

// معالجة إفلات الملف
dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;

    if (files.length > 0) {
        handleFile(files[0]);
    }
}

// معالجة اختيار الملف من المتصفح
fileInput.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        handleFile(this.files[0]);
    }
});

// معالجة الملف المحدد
function handleFile(file) {
    // إظهار تأثير التحميل
    showLoadingState();

    // تأخير قصير لإظهار التأثير
    setTimeout(() => {
        // التحقق من نوع الملف
        const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        const allowedExtensions = ['.csv', '.xlsx', '.xls'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
            hideLoadingState();
            showError('نوع الملف غير مدعوم. يرجى رفع ملف CSV أو Excel');
            return;
        }

        // التحقق من حجم الملف (10MB)
        if (file.size > 10 * 1024 * 1024) {
            hideLoadingState();
            showError('حجم الملف كبير جداً. الحد الأقصى 10MB');
            return;
        }

        // تحديث input الملف
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;

        // عرض معلومات الملف
        hideLoadingState();
        displayFileInfo(file);
    }, 500);
}

// عرض معلومات الملف
function displayFileInfo(file) {
    fileName.textContent = file.name;
    fileSize.textContent = `(${formatFileSize(file.size)})`;

    // إخفاء محتوى منطقة السحب وإظهار معلومات الملف
    dropZone.querySelector('.drop-zone-content').classList.add('d-none');
    fileInfo.classList.remove('d-none');

    // تغيير مظهر منطقة السحب
    dropZone.classList.remove('border-dashed', 'border-secondary');
    dropZone.classList.add('border-success');
}

// مسح الملف المحدد
function clearFile() {
    fileInput.value = '';

    // إظهار محتوى منطقة السحب وإخفاء معلومات الملف
    dropZone.querySelector('.drop-zone-content').classList.remove('d-none');
    fileInfo.classList.add('d-none');

    // إعادة تعيين مظهر منطقة السحب
    dropZone.classList.remove('border-success');
    dropZone.classList.add('border-dashed', 'border-secondary');
}

// تنسيق حجم الملف
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// إظهار حالة التحميل
function showLoadingState() {
    const uploadIcon = dropZone.querySelector('.fa-cloud-upload-alt');
    const uploadAnimation = dropZone.querySelector('.upload-animation');

    if (uploadIcon && uploadAnimation) {
        uploadIcon.style.display = 'none';
        uploadAnimation.classList.remove('d-none');
    }

    // تغيير النص
    const heading = dropZone.querySelector('h4');
    if (heading) {
        heading.textContent = 'جارٍ معالجة الملف...';
    }
}

// إخفاء حالة التحميل
function hideLoadingState() {
    const uploadIcon = dropZone.querySelector('.fa-cloud-upload-alt');
    const uploadAnimation = dropZone.querySelector('.upload-animation');

    if (uploadIcon && uploadAnimation) {
        uploadIcon.style.display = 'block';
        uploadAnimation.classList.add('d-none');
    }

    // إعادة تعيين النص
    const heading = dropZone.querySelector('h4');
    if (heading) {
        heading.textContent = 'اسحب ملف CSV هنا';
    }
}

// إظهار رسالة خطأ
function showError(message) {
    // إنشاء تنبيه مؤقت
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
    alertDiv.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // إضافة التنبيه بعد منطقة السحب
    dropZone.parentNode.insertBefore(alertDiv, dropZone.nextSibling);

    // إزالة التنبيه تلقائياً بعد 5 ثوان
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<style>
.drop-zone {
    transition: all 0.3s ease;
    min-height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.drop-zone:hover {
    border-color: #0d6efd !important;
    background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(13, 110, 253, 0.15);
}

.drop-zone.border-primary {
    border-color: #0d6efd !important;
    background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
    transform: scale(1.02);
    box-shadow: 0 10px 30px rgba(13, 110, 253, 0.2);
}

.drop-zone.border-success {
    border-color: #198754 !important;
    background: linear-gradient(135deg, #f0f9f4 0%, #d4edda 100%);
    box-shadow: 0 8px 25px rgba(25, 135, 84, 0.15);
}

.file-info {
    width: 100%;
}

.drop-zone-content {
    width: 100%;
}

.upload-icon-container {
    position: relative;
    display: inline-block;
}

.upload-icon-container i.fa-cloud-upload-alt {
    transition: all 0.3s ease;
    filter: drop-shadow(0 4px 8px rgba(13, 110, 253, 0.2));
}

.drop-zone:hover .upload-icon-container i.fa-cloud-upload-alt {
    transform: scale(1.1) translateY(-5px);
    filter: drop-shadow(0 6px 12px rgba(13, 110, 253, 0.3));
}

.upload-animation {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(13, 110, 253, 0.3);
}

.file-info .alert {
    border-radius: 10px;
    border: none;
    box-shadow: 0 4px 15px rgba(25, 135, 84, 0.1);
}

/* تأثيرات إضافية للتفاعل */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.drop-zone.border-primary .upload-icon-container i {
    animation: pulse 1.5s infinite;
}

/* تحسين المظهر على الشاشات الصغيرة */
@media (max-width: 768px) {
    .drop-zone {
        min-height: 220px;
        padding: 2rem 1rem !important;
    }

    .upload-icon-container i.fa-cloud-upload-alt {
        font-size: 3rem !important;
    }

    .drop-zone h4 {
        font-size: 1.2rem;
    }
}
</style>
