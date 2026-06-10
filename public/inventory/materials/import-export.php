<?php
/**
 * صفحة استيراد وتصدير المواد
 * Materials Import/Export Page
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'استيراد وتصدير المواد';
$currentPage = 'inventory';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export_materials':
                exportMaterials();
                break;

            case 'preview_import':
                previewImport();
                break;

            case 'import_materials':
                importMaterials();
                break;
        }
    }
}

function exportMaterials()
{
    global $db;

    $format = $_POST['export_format'] ?? 'excel';
    $include_inactive = isset($_POST['include_inactive']);

    // بناء الاستعلام
    $whereClause = $include_inactive ? '' : 'WHERE m.is_active = 1';

    $query = "
        SELECT
            m.item_number as 'رقم البند',
            mc.group_number as 'رقم المجموعة',
            mc.description as 'الوصف',
            mc.unit as 'الوحدة',
            m.current_stock as 'المخزون الحالي',
            COALESCE((
                SELECT SUM(td.quantity)
                FROM transaction_details td
                JOIN inventory_transactions it ON td.transaction_id = it.id
                WHERE td.material_id = m.id AND it.transaction_type = 'initial_balance' AND it.status = 'approved'
            ), 0) as 'الرصيد الافتتاحي',
            m.minimum_stock as 'الحد الأدنى',
            m.maximum_stock as 'الحد الأقصى',
            CASE WHEN m.is_active = 1 THEN 'نشط' ELSE 'غير نشط' END as 'الحالة',
            m.created_at as 'تاريخ الإنشاء'
        FROM materials m
        LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        $whereClause
        ORDER BY m.item_number
    ";

    $materials = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'excel') {
        exportToExcel($materials, 'materials_export_' . date('Y-m-d_H-i-s') . '.xlsx');
    } else {
        exportToCSV($materials, 'materials_export_' . date('Y-m-d_H-i-s') . '.csv');
    }
}

function exportToExcel($data, $filename)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // العناوين
        fputcsv($output, array_keys($data[0]));

        // البيانات
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit();
}

function exportToCSV($data, $filename)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // العناوين
        fputcsv($output, array_keys($data[0]));

        // البيانات
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }

    fclose($output);
    exit();
}

function previewImport()
{
    global $db;

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'يرجى اختيار ملف صحيح للاستيراد';
        return;
    }

    $file = $_FILES['import_file'];
    $filename = $file['name'];
    $tmpPath = $file['tmp_name'];

    // التحقق من نوع الملف
    $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ['csv'])) {
        $_SESSION['error_message'] = 'نوع الملف غير مدعوم. يرجى استخدام ملفات CSV فقط حالياً';
        return;
    }

    try {
        // قراءة الملف CSV
        $data = readCSVFile($tmpPath);

        if (empty($data)) {
            $_SESSION['error_message'] = 'الملف فارغ أو لا يحتوي على بيانات صحيحة';
            return;
        }

        // تحليل البيانات وإنشاء معاينة
        $preview = analyzeImportData($data);

        // حفظ البيانات في الجلسة للاستخدام لاحقاً
        $_SESSION['import_preview'] = $preview;
        $_SESSION['import_filename'] = $filename;

        // إعادة توجيه لصفحة المعاينة
        header('Location: import-preview.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = 'خطأ في قراءة الملف: ' . $e->getMessage();
        return;
    }
}

function analyzeImportData($data)
{
    global $db;

    $preview = [
        'total_rows' => count($data),
        'new_materials' => [],
        'update_materials' => [],
        'error_materials' => [],
        'summary' => [
            'new_count' => 0,
            'update_count' => 0,
            'error_count' => 0
        ],
        'debug_info' => [] // للتشخيص
    ];

    // إضافة معلومات التشخيص
    if (!empty($data)) {
        $preview['debug_info']['first_row_keys'] = array_keys($data[0]);
        $preview['debug_info']['first_row_values'] = array_values($data[0]);
    }

    // تحميل جميع المواد الموجودة في استعلام واحد (بدلاً من استعلام لكل صف)
    $allMaterials = $db->query("SELECT m.id, m.item_number, mc.description, mc.unit, m.current_stock, m.minimum_stock, m.maximum_stock, m.is_active FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number")->fetchAll(PDO::FETCH_ASSOC);
    $existingMaterialsMap = [];
    foreach ($allMaterials as $mat) {
        $existingMaterialsMap[$mat['item_number']] = $mat;
    }
    unset($allMaterials);

    foreach ($data as $index => $row) {
        $rowNumber = $index + 2; // +2 لأن الصف الأول عناوين والفهرس يبدأ من 0
        $errors = [];

        // التحقق من البيانات المطلوبة مع تشخيص مفصل
        $itemNumberKey = null;
        $descriptionKey = null;

        // البحث عن المفاتيح الصحيحة
        foreach (array_keys($row) as $key) {
            if (trim($key) === 'رقم البند' || strpos($key, 'رقم البند') !== false) {
                $itemNumberKey = $key;
            }
            if (trim($key) === 'الوصف' || strpos($key, 'الوصف') !== false) {
                $descriptionKey = $key;
            }
        }

        if (!$itemNumberKey || empty(trim($row[$itemNumberKey] ?? ''))) {
            $errors[] = 'رقم البند مطلوب - المفتاح المتاح: "' . $itemNumberKey . '" - القيمة: "' . ($row[$itemNumberKey] ?? 'غير موجود') . '"';
        }
        if (!$descriptionKey || empty(trim($row[$descriptionKey] ?? ''))) {
            $errors[] = 'وصف المادة مطلوب - المفتاح المتاح: "' . $descriptionKey . '" - القيمة: "' . ($row[$descriptionKey] ?? 'غير موجود') . '"';
        }

        // إعداد البيانات باستخدام المفاتيح المكتشفة
        $itemNumber = $itemNumberKey ? trim($row[$itemNumberKey] ?? '') : '';
        $description = $descriptionKey ? trim($row[$descriptionKey] ?? '') : '';

        // البحث عن باقي المفاتيح
        $groupNumber = '';
        $unit = 'قطعة';
        $currentStock = 0;
        $initialBalance = 0;
        $minimumStock = 0;
        $maximumStock = 0;
        $isActive = 1;

        foreach (array_keys($row) as $key) {
            $cleanKey = trim($key);

            if (strpos($cleanKey, 'رقم المجموعة') !== false || $cleanKey === 'group_number') {
                $groupNumber = trim($row[$key] ?? '');
            } elseif ($cleanKey === 'الوحدة' || $cleanKey === 'وحدة القياس' || $cleanKey === 'وحدة' || $cleanKey === 'unit') {
                $unitValue = trim($row[$key] ?? '');
                $unit = !empty($unitValue) ? $unitValue : 'قطعة';
            } elseif (strpos($cleanKey, 'المخزون الحالي') !== false || $cleanKey === 'current_stock') {
                $currentStock = floatval(str_replace(',', '', $row[$key] ?? 0));
            } elseif (strpos($cleanKey, 'الرصيد الافتتاحي') !== false || $cleanKey === 'initial_balance') {
                $initialBalance = floatval(str_replace(',', '', $row[$key] ?? 0));
            } elseif (strpos($cleanKey, 'الحد الأدنى') !== false || $cleanKey === 'minimum_stock') {
                $minimumStock = floatval(str_replace(',', '', $row[$key] ?? 0));
            } elseif (strpos($cleanKey, 'الحد الأقصى') !== false || $cleanKey === 'maximum_stock') {
                $maximumStock = floatval(str_replace(',', '', $row[$key] ?? 0));
            } elseif (strpos($cleanKey, 'الحالة') !== false || $cleanKey === 'is_active' || $cleanKey === 'status') {
                $statusValue = trim($row[$key] ?? 'نشط');
                $statusLower = strtolower($statusValue);
                $isActive = (in_array($statusLower, ['نشط', 'active', '1', 'true', 'نشطة'])) ? 1 : 0;
            }
        }

        // التحقق من صحة البيانات
        if (!empty($itemNumber) && strlen($itemNumber) > 20) {
            $errors[] = 'رقم البند لا يجب أن يتجاوز 20 حرف';
        }
        if (!empty($groupNumber) && strlen($groupNumber) > 10) {
            $errors[] = 'رقم المجموعة لا يجب أن يتجاوز 10 أرقام';
        }
        if ($currentStock < 0) {
            $errors[] = 'المخزون الحالي لا يمكن أن يكون سالب';
        }

        $materialData = [
            'row_number' => $rowNumber,
            'item_number' => $itemNumber,
            'group_number' => $groupNumber,
            'description' => $description,
            'unit' => $unit,
            'current_stock' => $currentStock,
            'initial_balance' => $initialBalance,
            'minimum_stock' => $minimumStock,
            'maximum_stock' => $maximumStock,
            'is_active' => $isActive,
            'status_text' => $isActive ? 'نشط' : 'غير نشط',
            'debug_unit' => $unit // للتشخيص
        ];

        if (!empty($errors)) {
            // مادة بها أخطاء
            $materialData['errors'] = $errors;
            $preview['error_materials'][] = $materialData;
            $preview['summary']['error_count']++;
        } else {
            // التحقق من وجود المادة (من الخريطة المحمّلة مسبقاً)
            $existingMaterial = $existingMaterialsMap[$itemNumber] ?? null;

            if ($existingMaterial) {
                // مادة موجودة - سيتم تحديثها
                $materialData['existing_data'] = $existingMaterial;
                $materialData['action'] = 'update';
                $preview['update_materials'][] = $materialData;
                $preview['summary']['update_count']++;
            } else {
                // مادة جديدة - سيتم إضافتها
                $materialData['action'] = 'insert';
                $preview['new_materials'][] = $materialData;
                $preview['summary']['new_count']++;
            }
        }
    }

    return $preview;
}

function importMaterials()
{
    global $db, $user_id;

    // التحقق من وجود بيانات المعاينة المؤكدة
    if (!isset($_POST['confirmed']) || !isset($_SESSION['import_preview'])) {
        $_SESSION['error_message'] = 'لم يتم العثور على بيانات مؤكدة للاستيراد';
        return;
    }

    $preview = $_SESSION['import_preview'];
    $filename = $_SESSION['import_filename'] ?? 'ملف غير معروف';

    try {
        // جمع المواد الصالحة للاستيراد من المعاينة
        $validMaterials = array_merge($preview['new_materials'], $preview['update_materials']);

        if (empty($validMaterials)) {
            $_SESSION['error_message'] = 'لا توجد مواد صالحة للاستيراد';
            return;
        }

        // تسجيل عملية الاستيراد
        $logStmt = $db->prepare("
            INSERT INTO material_import_export_logs
            (operation_type, file_name, total_records, operation_status, created_by)
            VALUES ('import', ?, ?, 'processing', ?)
        ");
        $logStmt->execute([$filename, count($validMaterials), $user_id]);
        $logId = $db->lastInsertId();

        // معالجة البيانات
        $successful = 0;
        $failed = 0;
        $errors = [];

        $db->beginTransaction();

        foreach ($validMaterials as $material) {
            try {
                if ($material['action'] === 'update') {
                    // تحديد قيمة المخزون: إذا كان الرصيد الافتتاحي محدد يُستخدم كمخزون
                    $stockValue = $material['current_stock'];
                    if (!empty($material['initial_balance']) && $material['initial_balance'] > 0) {
                        $stockValue = $material['initial_balance'];
                    }

                    // تحديث المادة الموجودة
                    $updateStmt = $db->prepare("
                        UPDATE materials SET
                            current_stock = ?, minimum_stock = ?, maximum_stock = ?,
                            is_active = ?, updated_at = NOW()
                        WHERE item_number = ?
                    ");
                    $updateStmt->execute([
                        $stockValue,
                        $material['minimum_stock'],
                        $material['maximum_stock'],
                        $material['is_active'],
                        $material['item_number']
                    ]);
                } else {
                    // تحديد قيمة المخزون للإدراج
                    $stockValue = $material['current_stock'];
                    if (!empty($material['initial_balance']) && $material['initial_balance'] > 0) {
                        $stockValue = $material['initial_balance'];
                    }

                    // إضافة مادة جديدة
                    $insertStmt = $db->prepare("
                        INSERT INTO materials
                        (item_number, current_stock,
                        minimum_stock, maximum_stock,
                         is_active)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([
                        $material['item_number'],
                        $stockValue,
                        $material['minimum_stock'],
                        $material['maximum_stock'],
                        $material['is_active']
                    ]);
                }

                // إنشاء معاملة رصيد افتتاحي إذا كان الرصيد الافتتاحي > 0
                if (!empty($material['initial_balance']) && $material['initial_balance'] > 0) {
                    createInitialBalanceTransaction($db, $material, $user_id);
                }

                $successful++;

            } catch (Exception $e) {
                $failed++;
                $errors[] = "الصف {$material['row_number']}: " . $e->getMessage();
            }
        }

        $db->commit();

        // تحديث سجل العملية
        $updateLogStmt = $db->prepare("
            UPDATE material_import_export_logs 
            SET successful_records = ?, failed_records = ?, error_details = ?, 
                operation_status = 'completed', completed_at = NOW()
            WHERE id = ?
        ");
        $updateLogStmt->execute([$successful, $failed, implode("\n", $errors), $logId]);

        if ($failed > 0) {
            $_SESSION['warning_message'] = "تم استيراد $successful مادة بنجاح، فشل في استيراد $failed مادة. تحقق من السجل للتفاصيل.";
        } else {
            $_SESSION['success_message'] = "تم استيراد $successful مادة بنجاح!";
        }

        // تنظيف بيانات المعاينة من الجلسة
        unset($_SESSION['import_preview']);
        unset($_SESSION['import_filename']);

    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error_message'] = 'حدث خطأ أثناء الاستيراد: ' . $e->getMessage();

        // تنظيف بيانات المعاينة في حالة الخطأ أيضاً
        unset($_SESSION['import_preview']);
        unset($_SESSION['import_filename']);
    }
}

/**
 * إنشاء معاملة رصيد افتتاحي للمادة المستوردة
 */
function createInitialBalanceTransaction($db, $material, $userId)
{
    // جلب ID المادة
    $stmt = $db->prepare("SELECT id FROM materials WHERE item_number = ?");
    $stmt->execute([$material['item_number']]);
    $mat = $stmt->fetch();
    if (!$mat) return;

    $materialId = $mat['id'];

    // التحقق من وجود رصيد افتتاحي سابق
    $checkStmt = $db->prepare("
        SELECT it.id FROM inventory_transactions it
        JOIN transaction_details td ON it.id = td.transaction_id
        WHERE it.transaction_type = 'initial_balance' AND td.material_id = ?
        LIMIT 1
    ");
    $checkStmt->execute([$materialId]);
    $existingTx = $checkStmt->fetch();

    if ($existingTx) {
        // تحديث المعاملة الموجودة فقط بالكمية الجديدة
        $updateDetail = $db->prepare("
            UPDATE transaction_details 
            SET quantity = ?, notes = ?
            WHERE transaction_id = ? AND material_id = ?
        ");
        $updateDetail->execute([
            $material['initial_balance'],
            'رصيد افتتاحي (محدّث): ' . $material['initial_balance'] . ' ' . $material['unit'],
            $existingTx['id'],
            $materialId
        ]);
        return; // المخزون تم تحديثه مسبقاً في UPDATE/INSERT أعلاه
    }

    // توليد رقم معاملة فريد
    $prefix = 'INIT' . date('Ymd');
    $lastStmt = $db->prepare("SELECT transaction_number FROM inventory_transactions WHERE transaction_number LIKE ? ORDER BY id DESC LIMIT 1");
    $lastStmt->execute([$prefix . '%']);
    $last = $lastStmt->fetch();
    $seq = $last ? ((int)substr($last['transaction_number'], -4)) + 1 : 1;
    $transactionNumber = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

    // إنشاء المعاملة
    $insertTx = $db->prepare("
        INSERT INTO inventory_transactions
        (transaction_number, transaction_type, branch_id, reference_number, transaction_date, notes, status, created_by, created_at, updated_at)
        VALUES (?, 'initial_balance', 1, ?, CURDATE(), ?, 'approved', ?, NOW(), NOW())
    ");
    $insertTx->execute([
        $transactionNumber,
        'INIT-' . $material['item_number'],
        'رصيد افتتاحي (استيراد): ' . $material['item_number'],
        $userId
    ]);
    $txId = $db->lastInsertId();

    // إضافة التفاصيل
    $insertDetail = $db->prepare("
        INSERT INTO transaction_details (transaction_id, material_id, quantity, notes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $insertDetail->execute([
        $txId,
        $materialId,
        $material['initial_balance'],
        'رصيد افتتاحي: ' . $material['initial_balance'] . ' ' . $material['unit']
    ]);
}

function readCSVFile($filename)
{
    $data = [];

    // قراءة الملف مع دعم الترميز العربي
    $content = file_get_contents($filename);

    // إزالة BOM إذا كان موجود
    $content = str_replace("\xEF\xBB\xBF", '', $content);

    // تحويل الترميز إلى UTF-8 إذا لزم الأمر
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    }

    // تقسيم المحتوى إلى أسطر
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function ($line) {
        return trim($line) !== '';
    });

    if (empty($lines)) {
        return $data;
    }

    // قراءة العناوين
    $headers = str_getcsv(array_shift($lines));

    // تنظيف العناوين
    $headers = array_map(function ($header) {
        return trim($header, " \t\n\r\0\x0B\"");
    }, $headers);

    // قراءة البيانات
    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }

    return $data;
}

function readExcelFile($filename)
{
    // للتبسيط، سنطلب من المستخدم تحويل Excel إلى CSV
    // أو يمكن تثبيت مكتبة PhpSpreadsheet لاحقاً
    throw new Exception('ملفات Excel غير مدعومة حالياً. يرجى تحويل الملف إلى CSV أولاً.');
}

// جلب سجل العمليات الأخيرة
$recentLogs = $db->query("
    SELECT * FROM material_import_export_logs 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
            <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning_message'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['warning_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
            <?php unset($_SESSION['warning_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-exchange-alt text-primary me-2"></i>
                استيراد وتصدير المواد
            </h1>
            <p class="text-muted mb-0">استيراد وتصدير بيانات المواد من وإلى ملفات Excel و CSV</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                العودة لقائمة المواد
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Export Section -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-download me-2"></i>
                        تصدير المواد
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">تصدير بيانات المواد إلى ملف Excel أو CSV</p>

                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="export_materials">

                        <div class="mb-3">
                            <label for="export_format" class="form-label">تنسيق الملف</label>
                            <select class="form-select" id="export_format" name="export_format">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV (.csv)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_inactive"
                                    name="include_inactive">
                                <label class="form-check-label" for="include_inactive">
                                    تضمين المواد غير النشطة
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download me-1"></i>
                            تصدير المواد
                        </button>
                    </form>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> سيتم تصدير جميع بيانات المواد بما في ذلك الأرقام والأوصاف والكميات
                        والأسعار.
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload me-2"></i>
                        استيراد المواد
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">استيراد بيانات المواد من ملف Excel أو CSV</p>

                    <!-- تحميل ملف نموذجي -->
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-download text-primary me-2"></i>
                            <div class="flex-grow-1">
                                <strong>تحميل ملف نموذجي:</strong>
                                <p class="mb-0 text-muted">احصل على ملف نموذجي يحتوي على التنسيق الصحيح للاستيراد</p>
                            </div>
                            <a href="download-template.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download me-1"></i>
                                تحميل النموذج
                            </a>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="mb-3">
                        <input type="hidden" name="action" value="preview_import">

                        <div class="mb-3">
                            <label for="import_file" class="form-label">اختر الملف</label>
                            <input type="file" class="form-control" id="import_file" name="import_file" accept=".csv"
                                required>
                            <div class="form-text">الملفات المدعومة: CSV فقط حالياً</div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-eye me-1"></i>
                            معاينة الاستيراد
                        </button>
                    </form>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>كيفية الاستيراد:</strong>
                        <ul class="mb-2 mt-2">
                            <li><strong>الخطوة 1:</strong> اختر الملف واضغط "معاينة الاستيراد"</li>
                            <li><strong>الخطوة 2:</strong> راجع البيانات والأخطاء في صفحة المعاينة</li>
                            <li><strong>الخطوة 3:</strong> أكد الاستيراد إذا كانت البيانات صحيحة</li>
                            <li><strong>متطلبات:</strong> يجب أن يحتوي الملف على عمود "رقم البند" و "الوصف" كحد أدنى
                            </li>
                        </ul>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>ملاحظة مهمة حول الوحدات:</strong> يمكنك استيراد أي وحدة قياس تريدها (مثل: متر، قطعة،
                            كيلو، لتر، متر طولي، كيلو متر، عدد، كيس، علبة، إلخ). النظام لا يقيدك بوحدات محددة مسبقاً.
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal"
                            data-bs-target="#templateModal">
                            <i class="fas fa-file-download me-1"></i>
                            تحميل قالب الاستيراد
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Operations Log -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-history me-2"></i>
                سجل العمليات الأخيرة
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($recentLogs)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>لا توجد عمليات سابقة</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>نوع العملية</th>
                                <th>اسم الملف</th>
                                <th>إجمالي السجلات</th>
                                <th>نجح</th>
                                <th>فشل</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
                                <th>المستخدم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td>
                                   <?php if ($log['operation_type'] === 'import'): ?>
                                            <span class="badge bg-primary"><i class="fas fa-upload me-1"></i>استيراد</span>
                                      <?php else: ?>
                                            <span class="badge bg-success"><i class="fas fa-download me-1"></i>تصدير</span>
                                      <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['file_name']); ?></td>
                                    <td><?php echo number_format($log['total_records']); ?></td>
                                    <td>
                                   <?php if ($log['successful_records'] > 0): ?>
                                            <span
                                                class="badge bg-success"><?php echo number_format($log['successful_records']); ?></span>
                                      <?php else: ?>
                                            <span class="text-muted">0</span>
                                      <?php endif; ?>
                                    </td>
                                    <td>
                                   <?php if ($log['failed_records'] > 0): ?>
                                            <span
                                                class="badge bg-danger"><?php echo number_format($log['failed_records']); ?></span>
                                      <?php else: ?>
                                            <span class="text-muted">0</span>
                                      <?php endif; ?>
                                    </td>
                                    <td>
                                                <?php
                                                $statusClasses = [
                                                    'pending' => 'bg-secondary',
                                                    'processing' => 'bg-warning',
                                                    'completed' => 'bg-success',
                                                    'failed' => 'bg-danger'
                                                ];
                                                $statusNames = [
                                                    'pending' => 'في الانتظار',
                                                    'processing' => 'قيد المعالجة',
                                                    'completed' => 'مكتمل',
                                                    'failed' => 'فشل'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $statusClasses[$log['operation_status']]; ?>">
                                         <?php echo $statusNames[$log['operation_status']]; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['created_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-download me-2"></i>
                    قالب استيراد المواد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>تعليمات استخدام القالب:</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>اسم العمود</th>
                                <th>مطلوب</th>
                                <th>نوع البيانات</th>
                                <th>مثال</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>رقم البند</strong></td>
                                <td><span class="badge bg-danger">مطلوب</span></td>
                                <td>نص</td>
                                <td>MAT001</td>
                                <td>رقم فريد للمادة</td>
                            </tr>
                            <tr>
                                <td><strong>رقم المجموعة</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>نص</td>
                                <td>GRP001</td>
                                <td>رقم مجموعة المادة</td>
                            </tr>
                            <tr>
                                <td><strong>الوصف</strong></td>
                                <td><span class="badge bg-danger">مطلوب</span></td>
                                <td>نص</td>
                                <td>كابل كهربائي 2.5 مم</td>
                                <td>وصف المادة</td>
                            </tr>
                            <tr>
                                <td><strong>الوحدة</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>نص</td>
                                <td>متر</td>
                                <td>وحدة القياس (افتراضي: قطعة)</td>
                            </tr>
                            <tr>
                                <td><strong>الكمية الحالية</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>رقم</td>
                                <td>100.50</td>
                                <td>الكمية المتوفرة حالياً</td>
                            </tr>
                            <tr>
                                <td><strong>الرصيد الافتتاحي</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>رقم</td>
                                <td>100</td>
                                <td>الرصيد الافتتاحي (يُنشئ معاملة initial_balance تلقائياً)</td>
                            </tr>
                            <tr>
                                <td><strong>الحد الأدنى</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>رقم</td>
                                <td>10</td>
                                <td>الحد الأدنى للكمية</td>
                            </tr>
                            <tr>
                                <td><strong>الحد الأقصى</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>رقم</td>
                                <td>1000</td>
                                <td>الحد الأقصى للكمية</td>
                            </tr>


                            <tr>
                                <td><strong>ملاحظات</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>نص</td>
                                <td>مادة عالية الجودة</td>
                                <td>ملاحظات إضافية</td>
                            </tr>
                            <tr>
                                <td><strong>الحالة</strong></td>
                                <td><span class="badge bg-secondary">اختياري</span></td>
                                <td>نص</td>
                                <td>نشط / غير نشط</td>
                                <td>حالة المادة (افتراضي: نشط)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>ملاحظات مهمة:</strong>
                    <ul class="mb-0 mt-2">
                        <li>يجب أن يكون الصف الأول يحتوي على أسماء الأعمدة بالضبط كما هو موضح أعلاه</li>
                        <li>رقم البند يجب أن يكون فريد لكل مادة</li>
                        <li>إذا كان رقم البند موجود، سيتم تحديث المادة الموجودة</li>
                        <li>إذا كان رقم البند غير موجود، سيتم إضافة مادة جديدة</li>
                        <li>استخدم ترميز UTF-8 للملفات التي تحتوي على نصوص عربية</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="btn btn-primary" onclick="downloadTemplate()">
                    <i class="fas fa-download me-1"></i>
                    تحميل القالب
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function downloadTemplate() {
        // إنشاء قالب CSV للتحميل
        const headers = [
            'رقم البند',
            'رقم المجموعة',
            'الوصف',
            'الوحدة',
            'الكمية الحالية',
            'الرصيد الافتتاحي',
            'الحد الأدنى',
            'الحد الأقصى',
            'ملاحظات',
            'الحالة'
        ];

        const sampleData = [
            'MAT001',
            'GRP001',
            'كابل كهربائي 2.5 مم',
            'متر',
            '100',
            '100',
            '10',
            '1000',
            'مادة عالية الجودة',
            'نشط'
        ];

        let csvContent = '\uFEFF'; // UTF-8 BOM
        csvContent += headers.join(',') + '\n';
        csvContent += sampleData.join(',') + '\n';

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'materials_import_template.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // إغلاق النموذج
        const modal = bootstrap.Modal.getInstance(document.getElementById('templateModal'));
        modal.hide();
    }

    $(document).ready(function () {
        console.log('صفحة استيراد وتصدير المواد جاهزة');
    });
</script>