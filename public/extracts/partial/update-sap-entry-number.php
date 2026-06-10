<?php
/**
 * صفحة تحديث رقم صحيفة الإدخال للمستخلصات الجزئية
 * Update SAP Entry Sheet Number for Partial Extracts
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_update_fields')) {
    header('Location: index.php');
    exit();
}

use PhpOffice\PhpSpreadsheet\IOFactory;

$pageTitle = 'تحديث رقم صحيفة الإدخال (SAP)';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$updateResults = null;

try {
    $db = getDB();
    
    // معالجة رفع الملف
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sap_file'])) {
        $uploadedFile = $_FILES['sap_file'];

        // التحقق من الملف
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطأ في رفع الملف');
        }

        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, ['xls', 'xlsx', 'mht', 'mhtml'])) {
            throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف Excel (.xls أو .xlsx) أو MHTML (.mht)');
        }

        // دالة لتحليل ملف MHTML واستخراج البيانات
        function parseMHTMLToArray($filePath) {
            $content = file_get_contents($filePath);

            // إزالة BOM إذا كان موجوداً
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            // MHTML يحتوي على أجزاء متعددة مفصولة بـ boundary
            // نحتاج لاستخراج الجزء الذي يحتوي على HTML
            $htmlContent = '';

            // البحث عن Content-Type: text/html
            if (preg_match('/Content-Type:\s*text\/html.*?\r?\n\r?\n(.*?)(?=\r?\n--)/is', $content, $htmlMatch)) {
                $htmlContent = $htmlMatch[1];
            } else {
                // إذا لم نجد، نستخدم المحتوى كاملاً
                $htmlContent = $content;
            }

            // إزالة encoding headers إذا كانت موجودة
            $htmlContent = preg_replace('/^Content-Transfer-Encoding:.*?\r?\n/im', '', $htmlContent);
            $htmlContent = preg_replace('/^Content-Location:.*?\r?\n/im', '', $htmlContent);

            // البحث عن جميع الجداول في المحتوى
            preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $htmlContent, $tableMatches, PREG_SET_ORDER);

            if (empty($tableMatches)) {
                // محاولة أخرى: البحث عن أي محتوى جدولي
                // بعض ملفات SAP تستخدم تنسيقات مختلفة

                // حفظ المحتوى في ملف مؤقت للتشخيص
                $debugFile = sys_get_temp_dir() . '/mhtml_debug_' . time() . '.html';
                file_put_contents($debugFile, $htmlContent);

                throw new Exception('لم يتم العثور على جدول في ملف MHTML.<br><br>' .
                    '<strong>معلومات التشخيص:</strong><br>' .
                    'حجم المحتوى: ' . strlen($htmlContent) . ' بايت<br>' .
                    'ملف التشخيص: ' . $debugFile . '<br><br>' .
                    '<strong>الحل:</strong><br>' .
                    '1. تأكد من أن الملف يحتوي على بيانات جدولية<br>' .
                    '2. حاول تصدير الملف من SAP بصيغة Excel (.xls) بدلاً من MHTML<br>' .
                    '3. أو افتح الملف في Excel واحفظه بصيغة .xlsx');
            }

            // استخدام أكبر جدول (عادة يحتوي على البيانات الرئيسية)
            $largestTable = '';
            $largestSize = 0;

            foreach ($tableMatches as $match) {
                $size = strlen($match[0]);
                if ($size > $largestSize) {
                    $largestSize = $size;
                    $largestTable = $match[0];
                }
            }

            // استخراج الصفوف
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $largestTable, $rowMatches);

            $data = [];
            foreach ($rowMatches[1] as $rowHTML) {
                // استخراج الخلايا (td أو th)
                preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $rowHTML, $cellMatches);

                $row = [];
                foreach ($cellMatches[1] as $cellHTML) {
                    // إزالة علامات HTML والحصول على النص فقط
                    $cellText = strip_tags($cellHTML);
                    // فك ترميز HTML entities
                    $cellText = html_entity_decode($cellText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    // إزالة المسافات الزائدة والأحرف الخاصة
                    $cellText = trim($cellText);
                    // تحويل &nbsp; إلى مسافة عادية
                    $cellText = str_replace("\xC2\xA0", ' ', $cellText);
                    $cellText = str_replace('&nbsp;', ' ', $cellText);
                    $cellText = preg_replace('/\s+/', ' ', $cellText);
                    $cellText = trim($cellText);

                    $row[] = $cellText;
                }

                // إضافة الصف حتى لو كان فارغاً (للحفاظ على ترتيب الصفوف)
                $data[] = $row;
            }

            if (empty($data)) {
                throw new Exception('الجدول المستخرج لا يحتوي على بيانات');
            }

            return $data;
        }

        // قراءة الملف حسب نوعه
        $data = null;
        $isMHTML = false;

        // التحقق إذا كان الملف MHTML
        $fileContent = file_get_contents($uploadedFile['tmp_name']);
        if (stripos($fileContent, 'MIME-Version') !== false && stripos($fileContent, 'Content-Type: text/html') !== false) {
            $isMHTML = true;
        }

        if ($isMHTML || in_array($fileExtension, ['mht', 'mhtml'])) {
            // معالجة ملف MHTML
            try {
                $data = parseMHTMLToArray($uploadedFile['tmp_name']);
                if (empty($data)) {
                    throw new Exception('الملف فارغ أو لا يحتوي على بيانات');
                }
            } catch (Exception $e) {
                throw new Exception('فشل قراءة ملف MHTML: ' . $e->getMessage());
            }
        } else {
            // معالجة ملف Excel العادي
            try {
                $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $data = $worksheet->toArray();
            } catch (Exception $e) {
                throw new Exception('فشل قراءة ملف Excel: ' . $e->getMessage());
            }
        }

        // البحث عن أعمدة البيانات
        $headerRow = $data[0];
        $entrySheetCol = null;
        $extractNumberCol = null;
        $taxAmountCol = null;
        $poNumberCol = null;        // العمود F - رقم PO
        $paymentDateCol = null;     // العمود N - تاريخ الصرف

        // دالة مساعدة لتحويل رقم العمود من حرف إلى رقم (مثل AC = 28)
        function columnLetterToNumber($letter) {
            $letter = strtoupper($letter);
            $number = 0;
            $length = strlen($letter);
            for ($i = 0; $i < $length; $i++) {
                $number = $number * 26 + (ord($letter[$i]) - ord('A') + 1);
            }
            return $number - 1; // نطرح 1 لأن المصفوفة تبدأ من 0
        }

        // البحث عن الأعمدة المطلوبة بالاسم أولاً
        foreach ($headerRow as $index => $header) {
            // التحقق من أن القيمة ليست null قبل استخدام trim
            $header = $header !== null ? trim($header) : '';
            $headerLower = mb_strtolower($header);

            // العمود AC - رقم صحيفة الإدخال
            // البحث بأسماء مختلفة
            if (
                $header === 'رقم صحيفة الادخال' ||
                $header === 'رقم صحيفة الإدخال' ||
                $headerLower === 'رقم صحيفة الادخال' ||
                $headerLower === 'رقم صحيفة الإدخال' ||
                mb_strpos($headerLower, 'صحيفة') !== false && mb_strpos($headerLower, 'ادخال') !== false ||
                mb_strpos($headerLower, 'entry') !== false && mb_strpos($headerLower, 'sheet') !== false
            ) {
                $entrySheetCol = $index;
            }

            // العمود AD - رقم المستخلص (نص قصير)
            if (
                $header === 'نص قصير' ||
                $headerLower === 'نص قصير' ||
                mb_strpos($headerLower, 'نص') !== false && mb_strpos($headerLower, 'قصير') !== false ||
                $headerLower === 'short text' ||
                mb_strpos($headerLower, 'extract') !== false && mb_strpos($headerLower, 'number') !== false ||
                $headerLower === 'رقم المستخلص'
            ) {
                $extractNumberCol = $index;
            }

            // العمود AA - مبلغ الضريبة
            if (
                $header === 'مبلغ الضريبة' ||
                $headerLower === 'مبلغ الضريبة' ||
                mb_strpos($headerLower, 'ضريبة') !== false ||
                mb_strpos($headerLower, 'tax') !== false && mb_strpos($headerLower, 'amount') !== false ||
                $headerLower === 'tax'
            ) {
                $taxAmountCol = $index;
            }

            // العمود F - رقم PO
            if (
                $header === 'PO' ||
                $header === 'رقم PO' ||
                $header === 'رقم أمر الشراء' ||
                $headerLower === 'po' ||
                $headerLower === 'رقم po' ||
                mb_strpos($headerLower, 'purchase') !== false && mb_strpos($headerLower, 'order') !== false ||
                mb_strpos($headerLower, 'أمر') !== false && mb_strpos($headerLower, 'شراء') !== false
            ) {
                $poNumberCol = $index;
            }

            // العمود N - تاريخ الصرف
            if (
                $header === 'تاريخ الصرف' ||
                $header === 'تاريخ صرف المستخلص' ||
                $headerLower === 'تاريخ الصرف' ||
                $headerLower === 'تاريخ صرف المستخلص' ||
                mb_strpos($headerLower, 'تاريخ') !== false && mb_strpos($headerLower, 'صرف') !== false ||
                mb_strpos($headerLower, 'payment') !== false && mb_strpos($headerLower, 'date') !== false
            ) {
                $paymentDateCol = $index;
            }
        }

        // إذا لم يتم العثور على الأعمدة بالاسم، جرب البحث بالموقع الافتراضي
        if ($entrySheetCol === null) {
            // العمود AC = 28 (0-based index)
            $acIndex = columnLetterToNumber('AC');
            if (isset($headerRow[$acIndex])) {
                $entrySheetCol = $acIndex;
            }
        }

        if ($extractNumberCol === null) {
            // العمود AD = 29 (0-based index)
            $adIndex = columnLetterToNumber('AD');
            if (isset($headerRow[$adIndex])) {
                $extractNumberCol = $adIndex;
            }
        }

        if ($taxAmountCol === null) {
            // العمود AA = 26 (0-based index)
            $aaIndex = columnLetterToNumber('AA');
            if (isset($headerRow[$aaIndex])) {
                $taxAmountCol = $aaIndex;
            }
        }

        if ($poNumberCol === null) {
            // العمود F = 5 (0-based index)
            $fIndex = columnLetterToNumber('F');
            if (isset($headerRow[$fIndex])) {
                $poNumberCol = $fIndex;
            }
        }

        if ($paymentDateCol === null) {
            // العمود N = 13 (0-based index)
            $nIndex = columnLetterToNumber('N');
            if (isset($headerRow[$nIndex])) {
                $paymentDateCol = $nIndex;
            }
        }

        // التحقق من وجود الأعمدة المطلوبة
        if ($entrySheetCol === null || $extractNumberCol === null || $taxAmountCol === null || $poNumberCol === null || $paymentDateCol === null) {
            // إنشاء رسالة خطأ تفصيلية
            $errorMsg = 'الملف لا يحتوي على الأعمدة المطلوبة.<br><br>';
            $errorMsg .= '<strong>الأعمدة الموجودة في الملف:</strong><br>';
            $errorMsg .= '<ul>';
            foreach ($headerRow as $index => $header) {
                $header = $header !== null ? trim($header) : '';
                if (!empty($header)) {
                    // تحويل رقم العمود إلى حرف
                    $colLetter = '';
                    $num = $index + 1;
                    while ($num > 0) {
                        $num--;
                        $colLetter = chr(65 + ($num % 26)) . $colLetter;
                        $num = intval($num / 26);
                    }
                    $errorMsg .= "<li>العمود {$colLetter} (رقم {$index}): {$header}</li>";
                }
            }
            $errorMsg .= '</ul><br>';
            $errorMsg .= '<strong>الأعمدة المطلوبة:</strong><br>';
            $errorMsg .= '<ul>';
            if ($entrySheetCol === null) {
                $errorMsg .= '<li>❌ رقم صحيفة الإدخال (العمود AC أو أي عمود يحتوي على "رقم صحيفة الادخال")</li>';
            } else {
                $errorMsg .= '<li>✅ رقم صحيفة الإدخال (تم العثور عليه)</li>';
            }
            if ($extractNumberCol === null) {
                $errorMsg .= '<li>❌ نص قصير / رقم المستخلص (العمود AD أو أي عمود يحتوي على "نص قصير")</li>';
            } else {
                $errorMsg .= '<li>✅ نص قصير / رقم المستخلص (تم العثور عليه)</li>';
            }
            if ($taxAmountCol === null) {
                $errorMsg .= '<li>❌ مبلغ الضريبة (العمود AA أو أي عمود يحتوي على "مبلغ الضريبة")</li>';
            } else {
                $errorMsg .= '<li>✅ مبلغ الضريبة (تم العثور عليه)</li>';
            }
            if ($poNumberCol === null) {
                $errorMsg .= '<li>❌ رقم PO (العمود F أو أي عمود يحتوي على "PO" أو "رقم أمر الشراء")</li>';
            } else {
                $errorMsg .= '<li>✅ رقم PO (تم العثور عليه)</li>';
            }
            if ($paymentDateCol === null) {
                $errorMsg .= '<li>❌ تاريخ الصرف (العمود N أو أي عمود يحتوي على "تاريخ الصرف")</li>';
            } else {
                $errorMsg .= '<li>✅ تاريخ الصرف (تم العثور عليه)</li>';
            }
            $errorMsg .= '</ul>';

            throw new Exception($errorMsg);
        }

        // معالجة البيانات - تجهيز للمعاينة
        $validRecords = [];
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];

        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];

            // تخطي الصفوف الفارغة
            if (empty($row[$extractNumberCol]) && empty($row[$entrySheetCol])) {
                continue;
            }

            $extractNumber = isset($row[$extractNumberCol]) && $row[$extractNumberCol] !== null
                ? trim($row[$extractNumberCol])
                : '';
            $entrySheetNumber = isset($row[$entrySheetCol]) && $row[$entrySheetCol] !== null
                ? trim($row[$entrySheetCol])
                : '';
            $taxAmount = floatval($row[$taxAmountCol] ?? 0);
            $poNumber = isset($row[$poNumberCol]) && $row[$poNumberCol] !== null
                ? trim($row[$poNumberCol])
                : '';
            $paymentDate = isset($row[$paymentDateCol]) && $row[$paymentDateCol] !== null
                ? trim($row[$paymentDateCol])
                : '';

                // التحقق من أن المستخلص جزئي (مبلغ الضريبة = 0)
                if ($taxAmount != 0) {
                    $skippedCount++;
                    continue; // تخطي المستخلصات النهائية
                }

                // التحقق من أن رقم المستخلص يبدأ بحرف A
                if (!preg_match('/^A/', $extractNumber)) {
                    $skippedCount++;
                    continue;
                }

                // إزالة حرف A من رقم المستخلص للبحث في قاعدة البيانات
                // في قاعدة البيانات، أرقام المستخلصات مخزنة بدون حرف A
                $extractNumberForDB = preg_replace('/^A/', '', $extractNumber);

                // التحقق من صحة رقم صحيفة الإدخال (10 أرقام)
                if (!empty($entrySheetNumber)) {
                    // إزالة أي مسافات أو أحرف غير رقمية
                    $entrySheetNumber = preg_replace('/[^0-9]/', '', $entrySheetNumber);

                    if (strlen($entrySheetNumber) != 10) {
                        $errors[] = [
                            'row' => $i + 1,
                            'extract_number' => $extractNumber,
                            'entry_sheet_number' => $entrySheetNumber,
                            'error' => 'رقم صحيفة الإدخال يجب أن يكون 10 أرقام'
                        ];
                        $errorCount++;
                        continue;
                    }
                } else {
                    $skippedCount++;
                    continue; // تخطي إذا لم يكن هناك رقم صحيفة إدخال
                }

                // البحث عن المستخلص في قاعدة البيانات (بدون حرف A)
                $stmt = $db->prepare("SELECT id, entry_sheet_number FROM partial_extracts WHERE extract_number = ?");
                $stmt->execute([$extractNumberForDB]);
                $extract = $stmt->fetch();

                if (!$extract) {
                    $errors[] = [
                        'row' => $i + 1,
                        'extract_number' => $extractNumber,
                        'entry_sheet_number' => $entrySheetNumber,
                        'error' => 'المستخلص غير موجود في قاعدة البيانات (تم البحث عن: ' . $extractNumberForDB . ')'
                    ];
                    $errorCount++;
                    continue;
                }

                // معالجة تاريخ الصرف
                $formattedDisbursementDate = null;
                if (!empty($paymentDate)) {
                    // محاولة تحويل التاريخ إلى صيغة قياسية
                    try {
                        $dateObj = new DateTime($paymentDate);
                        $formattedDisbursementDate = $dateObj->format('Y-m-d');
                    } catch (Exception $e) {
                        // إذا فشل التحويل، حاول صيغ أخرى
                        $formattedDisbursementDate = $paymentDate;
                    }
                }

                // التحقق من عدم تكرار رقم صحيفة الإدخال
                $checkDuplicate = $db->prepare("SELECT extract_number FROM partial_extracts WHERE entry_sheet_number = ? AND id != ?");
                $checkDuplicate->execute([$entrySheetNumber, $extract['id']]);
                $duplicate = $checkDuplicate->fetch();

                if ($duplicate) {
                    $errors[] = [
                        'row' => $i + 1,
                        'extract_number' => $extractNumber,
                        'po_number' => $poNumber,
                        'entry_sheet_number' => $entrySheetNumber,
                        'error' => "رقم صحيفة الإدخال مستخدم مسبقاً في المستخلص: {$duplicate['extract_number']}"
                    ];
                    $errorCount++;
                    continue;
                }

                // إضافة السجل للمعاينة
                $validRecords[] = [
                    'extract_id' => $extract['id'],
                    'extract_number_sap' => $extractNumber,
                    'extract_number_db' => $extractNumberForDB,
                    'po_number' => $poNumber,
                    'entry_sheet_number' => $entrySheetNumber,
                    'disbursement_date' => $formattedDisbursementDate,
                    'old_entry_sheet_number' => $extract['entry_sheet_number']
                ];
            }

            // حفظ بيانات المعاينة في الجلسة
            $_SESSION['sap_preview_data'] = [
                'valid_records' => $validRecords,
                'errors' => $errors,
                'skipped_count' => $skippedCount
            ];

            // إعادة التوجيه إلى صفحة المعاينة
            header('Location: preview-sap-update.php');
            exit();
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-import me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/etganplus/public/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="index.php">المستخلصات الجزئية</a></li>
                <li class="breadcrumb-item active">تحديث SAP</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($_GET['success']) && isset($_SESSION['sap_update_result'])): ?>
        <?php $result = $_SESSION['sap_update_result']; ?>
        <?php if ($result['success']): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <h5><i class="fas fa-check-circle me-2"></i>تم التحديث بنجاح!</h5>
                <hr>
                <p class="mb-0">
                    <strong>تم تحديث:</strong> <?php echo $result['updated_count']; ?> مستخلص<br>
                    <?php if ($result['skipped_count'] > 0): ?>
                        <strong>تم التخطي:</strong> <?php echo $result['skipped_count']; ?> سجل<br>
                    <?php endif; ?>
                    <?php if (!empty($result['errors'])): ?>
                        <strong>أخطاء:</strong> <?php echo count($result['errors']); ?> سجل
                    <?php endif; ?>
                </p>
            </div>
            <?php unset($_SESSION['sap_update_result']); ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && isset($_SESSION['sap_update_result'])): ?>
        <?php $result = $_SESSION['sap_update_result']; ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <h5><i class="fas fa-times-circle me-2"></i>فشل التحديث</h5>
            <hr>
            <p class="mb-0"><?php echo htmlspecialchars($result['error']); ?></p>
        </div>
        <?php unset($_SESSION['sap_update_result']); ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>

            <?php if (stripos($error, 'MHTML') !== false): ?>
            <hr>
            <div class="mt-2">
                <a href="test-mhtml.php" class="btn btn-sm btn-warning" target="_blank">
                    <i class="fas fa-bug me-1"></i>
                    اختبار وتشخيص ملف MHTML
                </a>
                <small class="d-block mt-2 text-muted">
                    استخدم هذه الأداة لمعرفة محتوى الملف وتشخيص المشكلة
                </small>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج رفع الملف -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-file-upload me-2"></i>
                رفع ملف تحديث SAP
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="sap_file" class="form-label">
                                <i class="fas fa-file-excel me-2 text-success"></i>
                                ملف Excel أو MHTML من نظام SAP
                            </label>
                            <input type="file" class="form-control" id="sap_file" name="sap_file"
                                   accept=".xls,.xlsx,.mht,.mhtml" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                الصيغ المدعومة: <strong>.xls</strong>, <strong>.xlsx</strong>, <strong>.mht</strong>, <strong>.mhtml</strong>
                                <br>
                                <i class="fas fa-eye me-1 text-primary"></i>
                                سيتم عرض معاينة للبيانات قبل التأكيد النهائي
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-eye me-2"></i>
                                معاينة البيانات
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                العودة للقائمة
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-left-info">
                        <div class="card-body">
                            <h6 class="card-title text-info">
                                <i class="fas fa-info-circle me-2"></i>
                                متطلبات الملف
                            </h6>
                            <div class="small mb-3">
                                <p class="mb-2"><strong>الأعمدة المطلوبة:</strong></p>
                                <ul class="mb-0">
                                    <li><strong>رقم PO</strong> (العمود F)</li>
                                    <li><strong>تاريخ الصرف</strong> (العمود N)</li>
                                    <li><strong>رقم صحيفة الإدخال</strong> (العمود AC)</li>
                                    <li><strong>نص قصير</strong> (العمود AD - رقم المستخلص)</li>
                                    <li><strong>مبلغ الضريبة</strong> (العمود AA)</li>
                                </ul>
                            </div>

                            <div class="alert alert-info py-2 px-2 mb-3" style="font-size: 0.8rem;">
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>ملاحظة:</strong> النظام يبحث عن الأعمدة تلقائياً بالاسم أو بالموقع (AC, AD, AA)
                            </div>

                            <div class="alert alert-success py-2 px-2 mb-3" style="font-size: 0.8rem;">
                                <i class="fas fa-check-circle me-1"></i>
                                <strong>جديد:</strong> النظام يدعم الآن ملفات <strong>MHTML</strong> من SAP مباشرة!
                            </div>

                            <hr>

                            <h6 class="card-title text-success mb-2">
                                <i class="fas fa-download me-2"></i>
                                الصيغ المدعومة
                            </h6>
                            <ul class="small mb-3" style="font-size: 0.85rem;">
                                <li>✅ <strong>Excel 97-2003</strong> (.xls)</li>
                                <li>✅ <strong>Excel 2007+</strong> (.xlsx)</li>
                                <li>✅ <strong>MHTML</strong> (.mht, .mhtml)</li>
                            </ul>

                            <div class="small text-muted" style="font-size: 0.75rem;">
                                <i class="fas fa-lightbulb me-1"></i>
                                يمكنك تصدير الملف من SAP بأي صيغة من الصيغ أعلاه
                            </div>

                            <hr>

                            <h6 class="card-title text-warning">
                                <i class="fas fa-filter me-2"></i>
                                شروط التحديث
                            </h6>
                            <ul class="small mb-3">
                                <li>مبلغ الضريبة = 0 (مستخلص جزئي)</li>
                                <li>رقم المستخلص يبدأ بحرف A</li>
                                <li>رقم صحيفة الإدخال مكون من 10 أرقام</li>
                                <li>رقم صحيفة الإدخال غير مكرر</li>
                            </ul>

                            <div class="alert alert-warning py-2 px-2 mb-0" style="font-size: 0.75rem;">
                                <i class="fas fa-exclamation-circle me-1"></i>
                                <strong>ملاحظة مهمة:</strong><br>
                                حرف <strong>A</strong> في ملف SAP للدلالة فقط على أن المستخلص جزئي.<br>
                                عند البحث في قاعدة البيانات، يتم <strong>إزالة حرف A</strong> تلقائياً.<br>
                                <small class="text-muted">مثال: A2501000001 → يبحث عن 2501000001</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نتائج التحديث -->
    <?php if ($updateResults): ?>
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                تم التحديث
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $updateResults['updated_count']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                تم التخطي
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $updateResults['skipped_count']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-forward fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                أخطاء
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $updateResults['error_count']; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول المستخلصات المحدثة -->
    <?php if (!empty($updateResults['updated'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-success text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-check-circle me-2"></i>
                المستخلصات التي تم تحديثها
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>رقم المستخلص</th>
                            <th>رقم صحيفة الإدخال الجديد</th>
                            <th>رقم صحيفة الإدخال القديم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($updateResults['updated'] as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($item['extract_number']); ?></td>
                            <td><strong class="text-success"><?php echo htmlspecialchars($item['entry_sheet_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['old_entry_sheet_number'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- جدول الأخطاء -->
    <?php if (!empty($updateResults['errors'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-danger text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-exclamation-triangle me-2"></i>
                الأخطاء
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>رقم الصف</th>
                            <th>رقم المستخلص</th>
                            <th>رقم صحيفة الإدخال</th>
                            <th>الخطأ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($updateResults['errors'] as $error): ?>
                        <tr>
                            <td><?php echo $error['row']; ?></td>
                            <td><?php echo htmlspecialchars($error['extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($error['entry_sheet_number']); ?></td>
                            <td><?php echo htmlspecialchars($error['error']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين التخطيط
include __DIR__ . '/../../includes/layout.php';
?>

