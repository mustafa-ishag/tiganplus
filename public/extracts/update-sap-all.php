<?php
/**
 * صفحة تحديث SAP الشاملة - تحديث جميع أنواع المستخلصات مرة واحدة
 * Unified SAP Update - Update All Extract Types at Once
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_update_fields')) {
    header('Location: index.php');
    exit();
}

use PhpOffice\PhpSpreadsheet\IOFactory;

$pageTitle = 'تحديث SAP الشامل - جميع المستخلصات';
$currentPage = 'extracts';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'تحديث SAP الشامل', 'url' => 'extracts/update-sap-all.php']
];

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
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $htmlContent = '';

            if (preg_match('/Content-Type:\s*text\/html.*?\r?\n\r?\n(.*?)(?=\r?\n--)/is', $content, $htmlMatch)) {
                $htmlContent = $htmlMatch[1];
            } else {
                $htmlContent = $content;
            }

            $htmlContent = preg_replace('/^Content-Transfer-Encoding:.*?\r?\n/im', '', $htmlContent);
            $htmlContent = preg_replace('/^Content-Location:.*?\r?\n/im', '', $htmlContent);

            preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $htmlContent, $tableMatches, PREG_SET_ORDER);

            if (empty($tableMatches)) {
                throw new Exception('لم يتم العثور على جدول في ملف MHTML.');
            }

            $largestTable = '';
            $largestSize = 0;
            foreach ($tableMatches as $match) {
                $size = strlen($match[0]);
                if ($size > $largestSize) {
                    $largestSize = $size;
                    $largestTable = $match[0];
                }
            }

            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $largestTable, $rowMatches);

            $data = [];
            foreach ($rowMatches[1] as $rowHTML) {
                preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $rowHTML, $cellMatches);
                $row = [];
                foreach ($cellMatches[1] as $cellHTML) {
                    $cellText = strip_tags($cellHTML);
                    $cellText = html_entity_decode($cellText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $cellText = trim($cellText);
                    $cellText = str_replace("\xC2\xA0", ' ', $cellText);
                    $cellText = str_replace('&nbsp;', ' ', $cellText);
                    $cellText = preg_replace('/\s+/', ' ', $cellText);
                    $cellText = trim($cellText);
                    $row[] = $cellText;
                }
                $data[] = $row;
            }

            if (empty($data)) {
                throw new Exception('الجدول المستخرج لا يحتوي على بيانات');
            }
            return $data;
        }

        // دالة مساعدة لتحويل رقم العمود من حرف إلى رقم
        function columnLetterToNumber($letter) {
            $letter = strtoupper($letter);
            $number = 0;
            $length = strlen($letter);
            for ($i = 0; $i < $length; $i++) {
                $number = $number * 26 + (ord($letter[$i]) - ord('A') + 1);
            }
            return $number - 1;
        }

        // قراءة الملف حسب نوعه
        $data = null;
        $isMHTML = false;

        $fileContent = file_get_contents($uploadedFile['tmp_name']);
        if (stripos($fileContent, 'MIME-Version') !== false && stripos($fileContent, 'Content-Type: text/html') !== false) {
            $isMHTML = true;
        }

        if ($isMHTML || in_array($fileExtension, ['mht', 'mhtml'])) {
            $data = parseMHTMLToArray($uploadedFile['tmp_name']);
            if (empty($data)) {
                throw new Exception('الملف فارغ أو لا يحتوي على بيانات');
            }
        } else {
            $spreadsheet = IOFactory::load($uploadedFile['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
        }

        // البحث عن أعمدة البيانات
        $headerRow = $data[0];
        $entrySheetCol = null;
        $extractNumberCol = null;
        $taxAmountCol = null;
        $poNumberCol = null;
        $paymentDateCol = null;

        foreach ($headerRow as $index => $header) {
            $header = $header !== null ? trim($header) : '';
            $headerLower = mb_strtolower($header);

            if (
                $header === 'رقم صحيفة الادخال' || $header === 'رقم صحيفة الإدخال' ||
                $headerLower === 'رقم صحيفة الادخال' || $headerLower === 'رقم صحيفة الإدخال' ||
                mb_strpos($headerLower, 'صحيفة') !== false && mb_strpos($headerLower, 'ادخال') !== false ||
                mb_strpos($headerLower, 'entry') !== false && mb_strpos($headerLower, 'sheet') !== false
            ) {
                $entrySheetCol = $index;
            }

            if (
                $header === 'نص قصير' || $headerLower === 'نص قصير' ||
                mb_strpos($headerLower, 'نص') !== false && mb_strpos($headerLower, 'قصير') !== false ||
                $headerLower === 'short text' ||
                mb_strpos($headerLower, 'extract') !== false && mb_strpos($headerLower, 'number') !== false ||
                $headerLower === 'رقم المستخلص'
            ) {
                $extractNumberCol = $index;
            }

            if (
                $header === 'مبلغ الضريبة' || $headerLower === 'مبلغ الضريبة' ||
                mb_strpos($headerLower, 'ضريبة') !== false ||
                mb_strpos($headerLower, 'tax') !== false && mb_strpos($headerLower, 'amount') !== false ||
                $headerLower === 'tax'
            ) {
                $taxAmountCol = $index;
            }

            if (
                $header === 'PO' || $header === 'رقم PO' || $header === 'رقم أمر الشراء' ||
                $headerLower === 'po' || $headerLower === 'رقم po' ||
                mb_strpos($headerLower, 'purchase') !== false && mb_strpos($headerLower, 'order') !== false ||
                mb_strpos($headerLower, 'أمر') !== false && mb_strpos($headerLower, 'شراء') !== false
            ) {
                $poNumberCol = $index;
            }

            if (
                $header === 'تاريخ الصرف' || $header === 'تاريخ صرف المستخلص' ||
                $headerLower === 'تاريخ الصرف' || $headerLower === 'تاريخ صرف المستخلص' ||
                mb_strpos($headerLower, 'تاريخ') !== false && mb_strpos($headerLower, 'صرف') !== false ||
                mb_strpos($headerLower, 'payment') !== false && mb_strpos($headerLower, 'date') !== false
            ) {
                $paymentDateCol = $index;
            }
        }

        // Fallback to default column positions
        if ($entrySheetCol === null) { $acIndex = columnLetterToNumber('AC'); if (isset($headerRow[$acIndex])) $entrySheetCol = $acIndex; }
        if ($extractNumberCol === null) { $adIndex = columnLetterToNumber('AD'); if (isset($headerRow[$adIndex])) $extractNumberCol = $adIndex; }
        if ($taxAmountCol === null) { $aaIndex = columnLetterToNumber('AA'); if (isset($headerRow[$aaIndex])) $taxAmountCol = $aaIndex; }
        if ($poNumberCol === null) { $fIndex = columnLetterToNumber('F'); if (isset($headerRow[$fIndex])) $poNumberCol = $fIndex; }
        if ($paymentDateCol === null) { $nIndex = columnLetterToNumber('N'); if (isset($headerRow[$nIndex])) $paymentDateCol = $nIndex; }

        // التحقق من وجود الأعمدة المطلوبة
        if ($entrySheetCol === null || $extractNumberCol === null || $taxAmountCol === null || $poNumberCol === null || $paymentDateCol === null) {
            $errorMsg = 'الملف لا يحتوي على الأعمدة المطلوبة.<br><br>';
            $errorMsg .= '<strong>الأعمدة المطلوبة:</strong><br><ul>';
            $errorMsg .= ($entrySheetCol === null ? '<li>❌' : '<li>✅') . ' رقم صحيفة الإدخال (العمود AC)</li>';
            $errorMsg .= ($extractNumberCol === null ? '<li>❌' : '<li>✅') . ' نص قصير / رقم المستخلص (العمود AD)</li>';
            $errorMsg .= ($taxAmountCol === null ? '<li>❌' : '<li>✅') . ' مبلغ الضريبة (العمود AA)</li>';
            $errorMsg .= ($poNumberCol === null ? '<li>❌' : '<li>✅') . ' رقم PO (العمود F)</li>';
            $errorMsg .= ($paymentDateCol === null ? '<li>❌' : '<li>✅') . ' تاريخ الصرف (العمود N)</li>';
            $errorMsg .= '</ul>';
            throw new Exception($errorMsg);
        }

        // معالجة البيانات - تصنيف لكل نوع مستخلص
        $partialRecords = [];
        $finalRegularRecords = [];
        $finalForPartialRecords = [];
        $skippedCount = 0;
        $errors = [];

        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];

            if (empty($row[$extractNumberCol]) && empty($row[$entrySheetCol])) {
                continue;
            }

            $extractNumber = isset($row[$extractNumberCol]) && $row[$extractNumberCol] !== null ? trim($row[$extractNumberCol]) : '';
            $entrySheetNumber = isset($row[$entrySheetCol]) && $row[$entrySheetCol] !== null ? trim($row[$entrySheetCol]) : '';
            $taxAmount = floatval($row[$taxAmountCol] ?? 0);
            $poNumber = isset($row[$poNumberCol]) && $row[$poNumberCol] !== null ? trim($row[$poNumberCol]) : '';
            $paymentDate = isset($row[$paymentDateCol]) && $row[$paymentDateCol] !== null ? trim($row[$paymentDateCol]) : '';

            // تخطي إذا لم يكن هناك رقم صحيفة إدخال
            if (empty($entrySheetNumber)) {
                $skippedCount++;
                continue;
            }

            // التحقق من صحة رقم صحيفة الإدخال (10 أرقام)
            $entrySheetNumber = preg_replace('/[^0-9]/', '', $entrySheetNumber);
            if (strlen($entrySheetNumber) != 10) {
                $errors[] = [
                    'row' => $i + 1,
                    'extract_number' => $extractNumber,
                    'entry_sheet_number' => $entrySheetNumber,
                    'error' => 'رقم صحيفة الإدخال يجب أن يكون 10 أرقام'
                ];
                continue;
            }

            // معالجة تاريخ الصرف
            $formattedDate = null;
            if (!empty($paymentDate)) {
                try {
                    $dateObj = new DateTime($paymentDate);
                    $formattedDate = $dateObj->format('Y-m-d');
                } catch (Exception $e) {
                    $formattedDate = $paymentDate;
                }
            }

            // تصنيف المستخلص حسب النوع
            $extractNumberForDB = '';
            $extractType = '';

            if ($taxAmount == 0 && preg_match('/^A/', $extractNumber)) {
                // مستخلص جزئي: ضريبة = 0، يبدأ بـ A
                $extractType = 'partial';
                $extractNumberForDB = preg_replace('/^A/', '', $extractNumber);

                $stmt = $db->prepare("SELECT id, entry_sheet_number FROM partial_extracts WHERE extract_number = ?");
                $stmt->execute([$extractNumberForDB]);
                $extract = $stmt->fetch();

                if (!$extract) {
                    $errors[] = ['row' => $i + 1, 'extract_number' => $extractNumber, 'entry_sheet_number' => $entrySheetNumber, 'error' => 'المستخلص الجزئي غير موجود (بحث عن: ' . $extractNumberForDB . ')'];
                    continue;
                }

                // التحقق من عدم تكرار رقم صحيفة الإدخال
                $checkDup = $db->prepare("SELECT extract_number FROM partial_extracts WHERE entry_sheet_number = ? AND id != ?");
                $checkDup->execute([$entrySheetNumber, $extract['id']]);
                if ($checkDup->fetch()) {
                    $errors[] = ['row' => $i + 1, 'extract_number' => $extractNumber, 'entry_sheet_number' => $entrySheetNumber, 'error' => 'رقم صحيفة الإدخال مستخدم مسبقاً في المستخلصات الجزئية'];
                    continue;
                }

                $partialRecords[] = [
                    'extract_id' => $extract['id'],
                    'extract_number_sap' => $extractNumber,
                    'extract_number_db' => $extractNumberForDB,
                    'po_number' => $poNumber,
                    'entry_sheet_number' => $entrySheetNumber,
                    'disbursement_date' => $formattedDate,
                    'old_entry_sheet_number' => $extract['entry_sheet_number']
                ];

            } elseif ($taxAmount > 0 && preg_match('/^F/i', $extractNumber)) {
                // مستخلص نهائي عادي: ضريبة > 0، يبدأ بـ F
                $extractType = 'final_regular';
                $extractNumberForDB = preg_replace('/^F/i', '', $extractNumber);

                $stmt = $db->prepare("SELECT id, entry_sheet_number FROM final_regular_extracts WHERE extract_number = ?");
                $stmt->execute([$extractNumberForDB]);
                $extract = $stmt->fetch();

                if (!$extract) {
                    $errors[] = ['row' => $i + 1, 'extract_number' => $extractNumber, 'entry_sheet_number' => $entrySheetNumber, 'error' => 'المستخلص النهائي العادي غير موجود (بحث عن: ' . $extractNumberForDB . ')'];
                    continue;
                }

                $checkDup = $db->prepare("SELECT extract_number FROM final_regular_extracts WHERE entry_sheet_number = ? AND id != ?");
                $checkDup->execute([$entrySheetNumber, $extract['id']]);
                if ($checkDup->fetch()) {
                    $errors[] = ['row' => $i + 1, 'extract_number' => $extractNumber, 'entry_sheet_number' => $entrySheetNumber, 'error' => 'رقم صحيفة الإدخال مستخدم مسبقاً في المستخلصات النهائية العادية'];
                    continue;
                }

                $finalRegularRecords[] = [
                    'extract_id' => $extract['id'],
                    'extract_number_sap' => $extractNumber,
                    'extract_number_db' => $extractNumberForDB,
                    'po_number' => $poNumber,
                    'entry_sheet_number' => $entrySheetNumber,
                    'disbursed_date' => $formattedDate,
                    'old_entry_sheet_number' => $extract['entry_sheet_number']
                ];

            } elseif ($taxAmount > 0 && preg_match('/^A/', $extractNumber)) {
                // مستخلص نهائي للجزئية: ضريبة > 0، يبدأ بـ A
                $extractType = 'final_for_partial';
                $extractNumberForDB = preg_replace('/^A/', '', $extractNumber);

                $stmt = $db->prepare("SELECT id, entry_sheet_number FROM final_for_partial_extracts WHERE extract_number = ?");
                $stmt->execute([$extractNumberForDB]);
                $extract = $stmt->fetch();

                if (!$extract) {
                    $errors[] = ['row' => $i + 1, 'extract_number' => $extractNumber, 'entry_sheet_number' => $entrySheetNumber, 'error' => 'المستخلص النهائي للجزئية غير موجود (بحث عن: ' . $extractNumberForDB . ')'];
                    continue;
                }

                $checkDup = $db->prepare("SELECT extract_number FROM final_for_partial_extracts WHERE entry_sheet_number = ? AND id != ?");
                $checkDup->execute([$entrySheetNumber, $extract['id']]);
                if ($checkDup->fetch()) {
                    $errors[] = ['row' => $i + 1, 'extract_number' => $extractNumber, 'entry_sheet_number' => $entrySheetNumber, 'error' => 'رقم صحيفة الإدخال مستخدم مسبقاً في المستخلصات النهائية للجزئية'];
                    continue;
                }

                $finalForPartialRecords[] = [
                    'extract_id' => $extract['id'],
                    'extract_number_sap' => $extractNumber,
                    'extract_number_db' => $extractNumberForDB,
                    'po_number' => $poNumber,
                    'entry_sheet_number' => $entrySheetNumber,
                    'disbursement_date' => $formattedDate,
                    'old_entry_sheet_number' => $extract['entry_sheet_number']
                ];

            } else {
                $skippedCount++;
                continue;
            }
        }

        // حفظ بيانات المعاينة في الجلسة
        $_SESSION['sap_all_preview_data'] = [
            'partial_records' => $partialRecords,
            'final_regular_records' => $finalRegularRecords,
            'final_for_partial_records' => $finalForPartialRecords,
            'errors' => $errors,
            'skipped_count' => $skippedCount
        ];

        // إعادة التوجيه إلى صفحة المعاينة
        header('Location: preview-sap-all.php');
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
            <i class="fas fa-sync-alt me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
    </div>

    <?php if (isset($_GET['success']) && isset($_SESSION['sap_all_update_result'])): ?>
        <?php $result = $_SESSION['sap_all_update_result']; ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <h5><i class="fas fa-check-circle me-2"></i>تم التحديث بنجاح!</h5>
            <hr>
            <div class="row">
                <?php if ($result['partial_count'] > 0): ?>
                <div class="col-md-4">
                    <span class="badge bg-primary fs-6 mb-1">المستخلصات الجزئية</span>
                    <p class="mb-0"><strong><?php echo $result['partial_count']; ?></strong> مستخلص</p>
                </div>
                <?php endif; ?>
                <?php if ($result['final_regular_count'] > 0): ?>
                <div class="col-md-4">
                    <span class="badge bg-success fs-6 mb-1">النهائية العادية</span>
                    <p class="mb-0"><strong><?php echo $result['final_regular_count']; ?></strong> مستخلص</p>
                </div>
                <?php endif; ?>
                <?php if ($result['final_for_partial_count'] > 0): ?>
                <div class="col-md-4">
                    <span class="badge bg-warning fs-6 mb-1">النهائية للجزئية</span>
                    <p class="mb-0"><strong><?php echo $result['final_for_partial_count']; ?></strong> مستخلص</p>
                </div>
                <?php endif; ?>
            </div>
            <hr>
            <p class="mb-0">
                <strong>إجمالي التحديث:</strong> <?php echo $result['total_updated']; ?> مستخلص
                <?php if ($result['skipped_count'] > 0): ?>
                    | <strong>تم التخطي:</strong> <?php echo $result['skipped_count']; ?> سجل
                <?php endif; ?>
                <?php if (!empty($result['errors'])): ?>
                    | <strong>أخطاء:</strong> <?php echo count($result['errors']); ?> سجل
                <?php endif; ?>
            </p>
        </div>
        <?php unset($_SESSION['sap_all_update_result']); ?>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && isset($_SESSION['sap_all_update_result'])): ?>
        <?php $result = $_SESSION['sap_all_update_result']; ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <h5><i class="fas fa-times-circle me-2"></i>فشل التحديث</h5>
            <hr>
            <p class="mb-0"><?php echo htmlspecialchars($result['error']); ?></p>
        </div>
        <?php unset($_SESSION['sap_all_update_result']); ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج رفع الملف -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <h6 class="m-0 font-weight-bold text-white">
                <i class="fas fa-file-upload me-2"></i>
                رفع ملف SAP واحد لتحديث جميع أنواع المستخلصات
            </h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <i class="fas fa-magic me-2"></i>
                <strong>ميزة التحديث الشامل:</strong> ارفع ملف SAP واحد وسيقوم النظام تلقائياً بتصنيف المستخلصات وتحديثها في الجداول الصحيحة:
                <ul class="mb-0 mt-2">
                    <li><span class="badge bg-primary">جزئية</span> يبدأ بحرف <strong>A</strong> + ضريبة = 0</li>
                    <li><span class="badge bg-warning text-dark">نهائية للجزئية</span> يبدأ بحرف <strong>A</strong> + ضريبة > 0</li>
                    <li><span class="badge bg-success">نهائية عادية</span> يبدأ بحرف <strong>F</strong> + ضريبة > 0</li>
                </ul>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="sap_file" class="form-label">
                                <i class="fas fa-file-excel me-2 text-success"></i>
                                ملف Excel أو MHTML من نظام SAP
                            </label>
                            <input type="file" class="form-control form-control-lg" id="sap_file" name="sap_file"
                                   accept=".xls,.xlsx,.mht,.mhtml" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                الصيغ المدعومة: <strong>.xls</strong>, <strong>.xlsx</strong>, <strong>.mht</strong>, <strong>.mhtml</strong>
                                <br>
                                <i class="fas fa-eye me-1 text-primary"></i>
                                سيتم عرض معاينة مفصّلة للبيانات مقسمة حسب النوع قبل التأكيد النهائي
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-lg btn-primary">
                                <i class="fas fa-eye me-2"></i>
                                معاينة وتصنيف البيانات
                            </button>
                            <a href="index.php" class="btn btn-lg btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                العودة
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
                        <div class="card-body">
                            <h6 class="card-title text-dark">
                                <i class="fas fa-columns me-2"></i>
                                الأعمدة المطلوبة
                            </h6>
                            <ul class="small mb-3">
                                <li><strong>العمود F</strong> - رقم PO</li>
                                <li><strong>العمود N</strong> - تاريخ الصرف</li>
                                <li><strong>العمود AA</strong> - مبلغ الضريبة</li>
                                <li><strong>العمود AC</strong> - رقم صحيفة الإدخال</li>
                                <li><strong>العمود AD</strong> - نص قصير (رقم المستخلص)</li>
                            </ul>
                            
                            <hr>

                            <h6 class="card-title text-dark">
                                <i class="fas fa-filter me-2"></i>
                                آلية التصنيف
                            </h6>
                            <table class="table table-sm table-bordered small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>النوع</th>
                                        <th>البادئة</th>
                                        <th>الضريبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-primary">جزئي</span></td>
                                        <td>A</td>
                                        <td>= 0</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-warning text-dark">نهائي للجزئية</span></td>
                                        <td>A</td>
                                        <td>> 0</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-success">نهائي عادي</span></td>
                                        <td>F</td>
                                        <td>> 0</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
?>
