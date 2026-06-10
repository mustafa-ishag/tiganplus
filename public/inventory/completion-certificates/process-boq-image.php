<?php
/**
 * API لمعالجة صور طلبات صرف المواد (YSMS070) واستخراج البيانات باستخدام Tesseract OCR
 * Process Material Request Image using Local Tesseract OCR
 * 
 * يستخرج:
 * - رقم أمر العمل ونوعه تلقائياً من الصورة
 * - أرقام الأصناف (رقم البند)
 * - الكمية الفعلية (كمية المقايسة)
 * - يجلب الأوصاف تلقائياً من قاعدة البيانات بناءً على رقم الصنف
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في تحميل الملفات المطلوبة'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالوصول'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_FILES['boq_image']) || $_FILES['boq_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'لم يتم رفع صورة صالحة'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من نوع الملف
$allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/bmp', 'image/tiff', 'image/webp'];
$fileType = mime_content_type($_FILES['boq_image']['tmp_name']);
if (!in_array($fileType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'نوع الملف غير مدعوم. يرجى رفع صورة PNG, JPG, BMP, TIFF, أو WebP'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من وجود Tesseract
$tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
if (!file_exists($tesseractPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Tesseract OCR غير مثبت. يرجى تثبيته من https://github.com/UB-Mannheim/tesseract'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $uploadedFile = $_FILES['boq_image']['tmp_name'];
    $originalName = $_FILES['boq_image']['name'];

    // === 1) معالجة الصورة بـ Tesseract OCR محلياً ===
    $ocrResult = callTesseractOcr($uploadedFile);
    $ocrText = $ocrResult['text'];
    $ocrWords = $ocrResult['words'];

    if (empty(trim($ocrText))) {
        throw new Exception('لم يتم استخراج أي نص من الصورة. تأكد من جودة الصورة.');
    }

    // === 2) استخراج رقم أمر العمل ونوعه من النص ===
    $workOrderInfo = extractWorkOrderInfo($ocrText);

    // === 3) استخراج أرقام الأصناف والكميات من مواقع الكلمات ===
    $rawItems = extractItemsFromWords($ocrWords);

    // === 4) جلب الأوصاف من قاعدة البيانات ===
    $db = getDB();
    $materials = enrichMaterialsFromDb($db, $rawItems);

    // === 5) البحث عن أمر العمل في قاعدة البيانات ===
    $matchedWorkOrder = null;
    if (!empty($workOrderInfo['work_order_number'])) {
        $matchedWorkOrder = findWorkOrder($db, $workOrderInfo['work_order_number']);
    }

    // === 6) إرسال النتيجة ===
    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'raw_text' => $ocrText,
        'image_name' => $originalName,
        'materials_count' => count($materials),
        'work_order_info' => $workOrderInfo,
        'matched_work_order' => $matchedWorkOrder,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Tesseract OCR Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================
// الدوال المساعدة
// =============================================

/**
 * معالجة الصورة باستخدام Tesseract OCR المحلي
 * يخرج TSV الذي يحتوي على مواقع الكلمات (left, top, width, height)
 */
function callTesseractOcr($filePath)
{
    $tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    $outputBase = sys_get_temp_dir() . '\\ocr_output_' . uniqid();

    // تشغيل Tesseract مع خرج TSV للحصول على مواقع الكلمات
    // --psm 6 = Assume a single uniform block of text (مناسب للجداول)
    $cmd = '"' . $tesseractPath . '" "' . $filePath . '" "' . $outputBase . '" -l eng+ara --psm 6 tsv 2>&1';
    exec($cmd, $output, $returnCode);

    $tsvFile = $outputBase . '.tsv';
    if (!file_exists($tsvFile)) {
        throw new Exception('فشل Tesseract في معالجة الصورة. رمز الخروج: ' . $returnCode . '. الخرج: ' . implode(' ', $output));
    }

    $tsvContent = file_get_contents($tsvFile);
    @unlink($tsvFile); // حذف الملف المؤقت

    // تشغيل مرة أخرى للحصول على النص العادي
    $txtOutputBase = sys_get_temp_dir() . '\\ocr_text_' . uniqid();
    $cmdTxt = '"' . $tesseractPath . '" "' . $filePath . '" "' . $txtOutputBase . '" -l eng+ara --psm 6 2>&1';
    exec($cmdTxt, $outputTxt, $returnCodeTxt);

    $txtFile = $txtOutputBase . '.txt';
    $ocrText = '';
    if (file_exists($txtFile)) {
        $ocrText = file_get_contents($txtFile);
        @unlink($txtFile);
    }

    // تحليل TSV لاستخراج الكلمات مع مواقعها
    // أعمدة TSV: level, page_num, block_num, par_num, line_num, word_num, left, top, width, height, conf, text
    $words = [];
    $lines = explode("\n", $tsvContent);
    $header = true;

    foreach ($lines as $line) {
        if ($header) {
            $header = false;
            continue;
        }

        $cols = explode("\t", $line);
        if (count($cols) < 12)
            continue;

        $conf = (int) $cols[10];
        $text = trim($cols[11]);

        // تخطي الكلمات الفارغة أو ذات الثقة المنخفضة جداً
        if (empty($text) || $conf < 10)
            continue;

        $words[] = [
            'text' => $text,
            'left' => (float) $cols[6],
            'top' => (float) $cols[7],
            'width' => (float) $cols[8],
            'height' => (float) $cols[9],
        ];
    }

    return [
        'text' => trim($ocrText),
        'words' => $words,
    ];
}

/**
 * استخراج رقم أمر العمل ونوعه من النص
 * يبحث عن نمط "رقم أمر العمل" ونوعية الخدمة/المشروع في الهيدر
 */
function extractWorkOrderInfo($text)
{
    $info = [
        'work_order_number' => '',
        'work_order_type' => '',
        'contractor' => '',
        'request_date' => '',
    ];

    // تحويل الأرقام العربية
    $text = convertArabicNumbers($text);

    // استخراج رقم أمر العمل - بحث عن 9 أرقام متتالية بالقرب من "رقم أمر العمل" أو "امر العمل"
    // النمط: رقم أمر العمل     262002540   341
    if (preg_match('/(?:رق[مـ]\s*[اأ]مر\s*العمل|امر\s*العمل)\s*(\d{6,12})/u', $text, $m)) {
        $info['work_order_number'] = trim($m[1]);
    }
    // بحث بديل: أي رقم من 9 أرقام (أرقام أوامر العمل عادة 9 أرقام)
    if (empty($info['work_order_number'])) {
        if (preg_match('/\b(\d{9})\b/', $text, $m)) {
            $info['work_order_number'] = $m[1];
        }
    }

    // استخراج نوع أمر العمل
    if (preg_match('/نوعي[ةه]\s*(خدمة|مشروع|صيانة|توصيل|تمديد)/u', $text, $m)) {
        $info['work_order_type'] = trim($m[1]);
    }

    // استخراج التاريخ
    if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $text, $m)) {
        $info['request_date'] = $m[1];
    }

    // استخراج اسم المقاول
    if (preg_match('/المقاول\s*الحالي\s*(\d+)\s*[-ـ]\s*(.+?)(?:\n|$)/u', $text, $m)) {
        $info['contractor'] = trim($m[2]);
    }

    return $info;
}


/**
 * استخراج أرقام الأصناف والكميات الفعلية باستخدام مواقع الكلمات (TSV overlay)
 * 
 * ترتيب الأعمدة من اليسار لليمين في YSMS070:
 * MR LINE | كمية تقديرية | كمية فعلية | كمية منصرفة | G/M | حالة | ... | رقم الصنف
 * 
 * الاستراتيجية: لكل صف، نجمع الأرقام ونرتبها حسب X
 * كمية فعلية = العمود الثالث من اليسار (index 2)
 */
function extractItemsFromWords($words)
{
    if (empty($words))
        return [];

    // تحويل الأرقام العربية في النصوص وحساب المراكز
    foreach ($words as &$w) {
        $w['text'] = convertArabicNumbers($w['text']);
        $w['center_x'] = $w['left'] + ($w['width'] / 2);
        $w['center_y'] = $w['top'] + ($w['height'] / 2);
    }
    unset($w);

    // === 1) البحث عن أرقام الأصناف (0XXXXXXX) ===
    $itemWords = [];
    foreach ($words as $w) {
        if (preg_match('/^0\d{7}$/', $w['text'])) {
            $itemWords[] = $w;
        }
    }

    if (empty($itemWords))
        return [];

    // حساب متوسط X لعمود رقم الصنف (لاستخدامه كمرجع)
    $avgItemX = 0;
    foreach ($itemWords as $iw) {
        $avgItemX += $iw['left'];
    }
    $avgItemX /= count($itemWords);

    // === 2) لكل صنف، جمع الأرقام في نفس الصف والبحث في عمود كمية فعلية ===
    $items = [];
    $rowTolerance = 15;

    foreach ($itemWords as $itemWord) {
        $itemNumber = $itemWord['text'];
        $itemY = $itemWord['center_y'];
        $itemX = $itemWord['left'];

        // جمع كل الأرقام الصغيرة في نفس الصف (يسار رقم الصنف)
        $rowNumbers = [];
        foreach ($words as $w) {
            if (abs($w['center_y'] - $itemY) > $rowTolerance)
                continue;
            // يجب أن يكون يسار رقم الصنف بنسبة كافية (أقل من 80% من موقع الصنف)
            if ($w['left'] >= $itemX * 0.80)
                continue;
            if (!preg_match('/^\d{1,4}$/', $w['text']))
                continue;

            $rowNumbers[] = [
                'value' => (int) $w['text'],
                'left' => $w['left'],
                'ratio' => $w['left'] / $avgItemX, // النسبة من عمود الصنف
            ];
        }

        // ترتيب حسب X من اليسار لليمين
        usort($rowNumbers, function ($a, $b) {
            return $a['left'] - $b['left'];
        });

        // كمية فعلية = العمود الثالث من اليسار (index 2)
        // ترتيب: [0]=MR LINE, [1]=كمية تقديرية, [2]=كمية فعلية, [3]=كمية منصرفة
        $actualQuantity = 0;

        // أولاً: البحث بالنسبة (كمية فعلية عند ~38-48% من موقع الصنف)
        foreach ($rowNumbers as $rn) {
            if ($rn['ratio'] >= 0.33 && $rn['ratio'] <= 0.52) {
                $actualQuantity = $rn['value'];
                break;
            }
        }

        // fallback: إذا لم نجد بالنسبة، نستخدم الترتيب
        if ($actualQuantity === 0 && count($rowNumbers) >= 3) {
            $actualQuantity = $rowNumbers[2]['value']; // العمود الثالث
        } elseif ($actualQuantity === 0 && count($rowNumbers) >= 2) {
            $actualQuantity = $rowNumbers[1]['value'];
        } elseif ($actualQuantity === 0 && count($rowNumbers) === 1) {
            $actualQuantity = $rowNumbers[0]['value'];
        }

        $itemNumber = '9' . $itemNumber;

        $items[] = [
            'item_number' => $itemNumber,
            'actual_quantity' => $actualQuantity,
        ];
    }

    // إزالة التكرارات
    $unique = [];
    foreach ($items as $item) {
        $key = $item['item_number'];
        if (!isset($unique[$key])) {
            $unique[$key] = $item;
        }
    }

    return array_values($unique);
}




/**
 * إثراء بيانات المواد من قاعدة البيانات
 * يجلب الوصف والوحدة وسعر الوحدة بناءً على رقم الصنف
 */
function enrichMaterialsFromDb($db, $rawItems)
{
    if (empty($rawItems))
        return [];

    $materials = [];

    // تحضير استعلام البحث عن المادة
    $searchStmt = $db->prepare("SELECT m.id, m.item_number, mc.description, mc.unit FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.item_number = ? LIMIT 1");

    foreach ($rawItems as $item) {
        $itemNumber = $item['item_number'];
        $description = '';
        $unit = '';
        $unitPrice = 0;
        $materialId = 0;
        $foundInDb = false;

        // البحث في قاعدة البيانات
        $searchStmt->execute([$itemNumber]);
        $dbMaterial = $searchStmt->fetch(PDO::FETCH_ASSOC);

        if ($dbMaterial) {
            $materialId = $dbMaterial['id'];
            $description = $dbMaterial['description'];
            $unit = $dbMaterial['unit'];
            $foundInDb = true;
        }

        $materials[] = [
            'item_number' => $itemNumber,
            'description' => $description,
            'unit' => $unit,
            'estimated_quantity' => (float) $item['actual_quantity'],
            'material_id' => $materialId,
            'found_in_db' => $foundInDb,
        ];
    }

    return $materials;
}

/**
 * البحث عن أمر العمل في قاعدة البيانات
 */
function findWorkOrder($db, $workOrderNumber)
{
    $stmt = $db->prepare("
        SELECT wo.id, wo.work_order_number, wot.type_code, wot.description as type_description,
               wo.branch_id, b.name as branch_name
        FROM work_orders wo
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        WHERE wo.work_order_number = ?
        LIMIT 1
    ");
    $stmt->execute([$workOrderNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        return [
            'id' => (int) $result['id'],
            'work_order_number' => $result['work_order_number'],
            'type_code' => $result['type_code'],
            'type_description' => $result['type_description'],
            'branch_name' => $result['branch_name'],
        ];
    }

    return null;
}

/**
 * تحويل الأرقام العربية إلى إنجليزية
 */
function convertArabicNumbers($str)
{
    $arabicNums = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $englishNums = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($arabicNums, $englishNums, $str);
}
?>