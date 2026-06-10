<?php
/**
 * صفحة استيراد وتصدير كتالوج المواد
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// إنشاء الجدول إذا لم يكن موجوداً
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS material_catalog (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_number VARCHAR(50) NOT NULL UNIQUE,
            group_number VARCHAR(20) DEFAULT NULL,
            description TEXT NOT NULL,
            unit VARCHAR(50) DEFAULT 'قطعة',
            unit_price DECIMAL(12,4) DEFAULT 0.0000,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item_number (item_number),
            INDEX idx_group_number (group_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
}

// التصدير المباشر
if (isset($_GET['action']) && $_GET['action'] === 'export_direct') {
    $items = $db->query("
        SELECT
            item_number as 'رقم البند',
            group_number as 'رقم المجموعة',
            description as 'الوصف',
            unit as 'الوحدة',
            unit_price as 'سعر الوحدة',
            created_at as 'تاريخ الإضافة'
        FROM material_catalog
        ORDER BY item_number
    ")->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="material_catalog_' . date('Y-m-d_H-i-s') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    if (!empty($items)) {
        fputcsv($output, array_keys($items[0]));
        foreach ($items as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit();
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export_catalog':
                exportCatalog();
                break;
            case 'preview_import':
                previewImport();
                break;
            case 'import_catalog':
                importCatalog();
                break;
        }
    }
}

function exportCatalog()
{
    global $db;

    $items = $db->query("
        SELECT
            item_number as 'رقم البند',
            group_number as 'رقم المجموعة',
            description as 'الوصف',
            unit as 'الوحدة',
            unit_price as 'سعر الوحدة'
        FROM material_catalog
        ORDER BY item_number
    ")->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="material_catalog_' . date('Y-m-d_H-i-s') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    if (!empty($items)) {
        fputcsv($output, array_keys($items[0]));
        foreach ($items as $row) {
            fputcsv($output, $row);
        }
    } else {
        // تصدير هيكل فارغ مع الأعمدة
        fputcsv($output, ['رقم البند', 'رقم المجموعة', 'الوصف', 'الوحدة', 'سعر الوحدة']);
    }
    fclose($output);
    exit();
}

function readCSVFile($filename)
{
    $data = [];
    $content = file_get_contents($filename);
    $content = str_replace("\xEF\xBB\xBF", '', $content);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
    }
    $lines = explode("\n", $content);
    $lines = array_filter($lines, fn($l) => trim($l) !== '');
    if (empty($lines))
        return $data;

    $headers = str_getcsv(array_shift($lines));
    $headers = array_map(fn($h) => trim($h, " \t\n\r\0\x0B\""), $headers);

    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) >= 2) {
            // تكميل الأعمدة الناقصة
            while (count($row) < count($headers)) {
                $row[] = '';
            }
            $data[] = array_combine($headers, array_slice($row, 0, count($headers)));
        }
    }
    return $data;
}

function previewImport()
{
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'يرجى اختيار ملف CSV صحيح';
        return;
    }

    $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        $_SESSION['error_message'] = 'يُدعم نوع CSV فقط';
        return;
    }

    try {
        $data = readCSVFile($_FILES['import_file']['tmp_name']);
        if (empty($data)) {
            $_SESSION['error_message'] = 'الملف فارغ أو لا يحتوي على بيانات';
            return;
        }
        $_SESSION['catalog_import_preview'] = analyzeData($data);
        $_SESSION['catalog_import_filename'] = $_FILES['import_file']['name'];
        header('Location: import-preview.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'خطأ في قراءة الملف: ' . $e->getMessage();
    }
}

function analyzeData($data)
{
    global $db;

    $preview = [
        'total_rows' => count($data),
        'new_items' => [],
        'update_items' => [],
        'error_items' => [],
        'summary' => ['new_count' => 0, 'update_count' => 0, 'error_count' => 0]
    ];

    foreach ($data as $index => $row) {
        $rowNumber = $index + 2;
        $errors = [];
        $itemNumber = '';
        $groupNumber = '';
        $description = '';
        $unit = 'قطعة';
        $unitPrice = 0;

        foreach (array_keys($row) as $key) {
            $ck = trim($key);
            if (strpos($ck, 'رقم البند') !== false || $ck === 'item_number') {
                $itemNumber = trim($row[$key] ?? '');
            } elseif (strpos($ck, 'رقم المجموعة') !== false || $ck === 'group_number') {
                $groupNumber = trim($row[$key] ?? '');
            } elseif (strpos($ck, 'الوصف') !== false || $ck === 'description') {
                $description = trim($row[$key] ?? '');
            } elseif ($ck === 'الوحدة' || $ck === 'وحدة' || $ck === 'unit') {
                $v = trim($row[$key] ?? '');
                $unit = !empty($v) ? $v : 'قطعة';
            } elseif (strpos($ck, 'سعر') !== false || $ck === 'unit_price') {
                $unitPrice = floatval(str_replace(',', '', $row[$key] ?? 0));
            }
        }

        if (empty($itemNumber))
            $errors[] = 'رقم البند مطلوب';
        if (empty($description))
            $errors[] = 'الوصف مطلوب';
        if ($unitPrice < 0)
            $errors[] = 'سعر الوحدة لا يمكن أن يكون سالباً';

        $itemData = [
            'row_number' => $rowNumber,
            'item_number' => $itemNumber,
            'group_number' => $groupNumber,
            'description' => $description,
            'unit' => $unit,
            'unit_price' => $unitPrice,
        ];

        if (!empty($errors)) {
            $itemData['errors'] = $errors;
            $preview['error_items'][] = $itemData;
            $preview['summary']['error_count']++;
        } else {
            $check = $db->prepare("SELECT id FROM material_catalog WHERE item_number = ?");
            $check->execute([$itemNumber]);
            if ($check->fetch()) {
                $itemData['action'] = 'update';
                $preview['update_items'][] = $itemData;
                $preview['summary']['update_count']++;
            } else {
                $itemData['action'] = 'insert';
                $preview['new_items'][] = $itemData;
                $preview['summary']['new_count']++;
            }
        }
    }

    return $preview;
}

function importCatalog()
{
    global $db, $user_id;

    if (!isset($_POST['confirmed']) || !isset($_SESSION['catalog_import_preview'])) {
        $_SESSION['error_message'] = 'لم يتم العثور على بيانات مؤكدة للاستيراد';
        return;
    }

    $preview = $_SESSION['catalog_import_preview'];
    $filename = $_SESSION['catalog_import_filename'] ?? 'ملف غير معروف';
    $allItems = array_merge($preview['new_items'], $preview['update_items']);

    if (empty($allItems)) {
        $_SESSION['error_message'] = 'لا توجد عناصر صالحة للاستيراد';
        return;
    }

    try {
        $db->beginTransaction();
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($allItems as $item) {
            try {
                if ($item['action'] === 'update') {
                    $stmt = $db->prepare("
                        UPDATE material_catalog
                        SET group_number=?, description=?, unit=?, unit_price=?, updated_at=NOW()
                        WHERE item_number=?
                    ");
                    $stmt->execute([$item['group_number'], $item['description'], $item['unit'], $item['unit_price'], $item['item_number']]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO material_catalog (item_number, group_number, description, unit, unit_price, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$item['item_number'], $item['group_number'], $item['description'], $item['unit'], $item['unit_price'], $user_id]);
                }
                $successful++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = "الصف {$item['row_number']}: " . $e->getMessage();
            }
        }

        $db->commit();

        unset($_SESSION['catalog_import_preview'], $_SESSION['catalog_import_filename']);

        if ($failed > 0) {
            $_SESSION['warning_message'] = "تم استيراد $successful عنصر بنجاح، فشل في استيراد $failed عنصر.";
        } else {
            $_SESSION['success_message'] = "تم استيراد $successful عنصر بنجاح!";
        }

        header('Location: index.php');
        exit();

    } catch (Exception $e) {
        $db->rollback();
        unset($_SESSION['catalog_import_preview'], $_SESSION['catalog_import_filename']);
        $_SESSION['error_message'] = 'خطأ أثناء الاستيراد: ' . $e->getMessage();
    }
}

// جلب سجل العمليات الأخيرة (من جدول materials)
$pageTitle = 'استيراد وتصدير كتالوج المواد';
$currentPage = 'material-catalog';

ob_start();
?>

<div class="container-fluid px-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['warning_message'])): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['warning_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['warning_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- رأس الصفحة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-exchange-alt text-primary me-2"></i>
                استيراد وتصدير كتالوج المواد
            </h1>
            <p class="text-muted mb-0">استيراد وتصدير بيانات كتالوج المواد من/إلى ملفات CSV</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            العودة للكتالوج
        </a>
    </div>

    <div class="row">
        <!-- تصدير -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-download me-2"></i>
                        تصدير الكتالوج
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">تصدير جميع مواد الكتالوج إلى ملف CSV</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="export_catalog">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download me-1"></i>
                            تصدير الكتالوج (CSV)
                        </button>
                    </form>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        سيتم تصدير الأعمدة: رقم البند، رقم المجموعة، الوصف، الوحدة، سعر الوحدة.
                    </div>
                </div>
            </div>
        </div>

        <!-- استيراد -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload me-2"></i>
                        استيراد الكتالوج
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">استيراد مواد الكتالوج من ملف CSV</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="preview_import">
                        <div class="mb-3">
                            <label for="import_file" class="form-label">اختر ملف CSV</label>
                            <input type="file" class="form-control" id="import_file" name="import_file" accept=".csv"
                                required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-eye me-1"></i>
                            معاينة الاستيراد
                        </button>
                    </form>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>تنسيق الملف المطلوب:</strong>
                        <ul class="mb-0 mt-1">
                            <li>الملف يجب أن يكون CSV</li>
                            <li>الأعمدة المطلوبة: <code>رقم البند</code>، <code>الوصف</code></li>
                            <li>الأعمدة الاختيارية: رقم المجموعة، الوحدة، سعر الوحدة</li>
                            <li>إذا كان رقم البند موجوداً سيتم تحديثه، وإلا سيضاف جديداً</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نموذج CSV -->
    <div class="card shadow mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-file-csv me-2"></i>
                مثال على محتوى ملف CSV
            </h6>
        </div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded mb-0" dir="ltr" style="font-size: 0.85rem">رقم البند,رقم المجموعة,الوصف,الوحدة,سعر الوحدة
E-001-001,GRP-001,كابل كهربائي 3×10mm²,متر,45.00
E-001-002,GRP-001,كابل كهربائي 3×16mm²,متر,65.00
E-002-001,GRP-002,قاطع تلقائي 63A,قطعة,180.00</pre>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>