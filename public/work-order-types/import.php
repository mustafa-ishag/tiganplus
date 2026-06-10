<?php

declare(strict_types=1);

/**
 * استيراد أنواع أوامر العمل
 * Work Order Types Import
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'استيراد أنواع أوامر العمل';
$currentPage = 'work-order-types';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أنواع أوامر العمل', 'url' => 'work-order-types/index.php'],
    ['title' => 'استيراد البيانات', 'url' => 'work-order-types/import.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$db = getDB();
$userId = $_SESSION['user_id'];

$error = '';
$success = '';
$importResults = null;

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
        } else {
            throw new Exception('حالياً يتم دعم ملفات CSV فقط. يرجى تحويل ملف Excel إلى CSV');
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
    foreach ($lines as $line) {
        $row = str_getcsv($line);

        if (count($row) >= 2) { // التأكد من وجود البيانات الأساسية (كود النوع والوصف)
            // تنظيف البيانات
            $cleanRow = array_map(function($value) {
                return trim($value, " \t\n\r\0\x0B\"");
            }, $row);

            $data[] = [
                'type_code' => $cleanRow[0] ?? '',
                'description' => $cleanRow[1] ?? '',
                'status' => isset($cleanRow[2]) && strtolower(trim($cleanRow[2])) === 'غير نشط' ? 'inactive' : 'active'
            ];
        }
    }

    return $data;
}

/**
 * تحليل بيانات الاستيراد وإنشاء معاينة
 */
function analyzeImportData(array $importData): array
{
    global $db;

    $newRecords = [];
    $updateRecords = [];
    $errorRecords = [];
    $validRecords = [];

    foreach ($importData as $index => $item) {
        $rowNumber = $index + 2; // +2 لأن الصف الأول عناوين والفهرس يبدأ من 0

        // التحقق من صحة البيانات
        $errors = [];

        if (empty(trim($item['type_code']))) {
            $errors[] = 'كود النوع مطلوب';
        }

        if (empty(trim($item['description']))) {
            $errors[] = 'وصف النوع مطلوب';
        }

        // إذا كان هناك أخطاء، أضف للسجلات الخاطئة
        if (!empty($errors)) {
            $errorRecords[] = [
                'row_number' => $rowNumber,
                'data' => $item,
                'error' => implode(', ', $errors)
            ];
            continue;
        }

        $cleanRecord = [
            'type_code' => trim($item['type_code']),
            'description' => trim($item['description']),
            'status' => $item['status'],
            'row_number' => $rowNumber
        ];

        // التحقق من وجود الكود في قاعدة البيانات
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM work_order_types WHERE type_code = ?");
        $checkStmt->execute([$cleanRecord['type_code']]);

        if ($checkStmt->fetchColumn() > 0) {
            // سجل موجود - سيتم تحديثه
            $updateRecords[] = $cleanRecord;
        } else {
            // سجل جديد
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

// جلب سجل العمليات الأخيرة
$recentLogs = $db->query("
    SELECT l.*, u.username 
    FROM work_order_type_import_export_logs l
    LEFT JOIN users u ON l.created_by = u.id
    WHERE l.operation_type = 'import'
    ORDER BY l.created_at DESC 
    LIMIT 10
")->fetchAll();

// بدء تخزين المحتوى
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-import me-2"></i>
            استيراد أنواع أوامر العمل
        </h1>
        <p class="text-muted mb-0">استيراد بيانات أنواع أوامر العمل من ملف CSV</p>
    </div>
    <div>
        <a href="<?= path('work-order-types/index.php') ?>" class="btn btn-secondary">
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

<!-- Import Results -->
<?php if ($importResults): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                نتائج الاستيراد
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h4 text-primary"><?= $importResults['total'] ?></div>
                        <div class="text-muted">إجمالي السجلات</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h4 text-success"><?= $importResults['success'] ?></div>
                        <div class="text-muted">نجح</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h4 text-danger"><?= $importResults['errors'] ?></div>
                        <div class="text-muted">فشل</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div class="h4 text-info">
                            <?= $importResults['total'] > 0 ? round(($importResults['success'] / $importResults['total']) * 100, 1) : 0 ?>%
                        </div>
                        <div class="text-muted">معدل النجاح</div>
                    </div>
                </div>
            </div>

            <?php if (!empty($importResults['error_details'])): ?>
                <hr>
                <h6 class="text-danger">تفاصيل الأخطاء:</h6>
                <div class="alert alert-warning">
                    <ul class="mb-0">
                        <?php foreach (array_slice($importResults['error_details'], 0, 10) as $errorDetail): ?>
                            <li><?= htmlspecialchars($errorDetail) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($importResults['error_details']) > 10): ?>
                            <li class="text-muted">... و <?= count($importResults['error_details']) - 10 ?> أخطاء أخرى</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Import Form -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-upload me-2"></i>
                    رفع ملف الاستيراد
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-4">
                        <label for="import_file" class="form-label">
                            <i class="fas fa-file-csv me-2"></i>
                            ملف البيانات *
                        </label>
                        <input type="file" class="form-control" id="import_file" name="import_file"
                               accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">
                            الصيغ المدعومة: CSV, Excel (.xlsx, .xls) - الحد الأقصى: 10MB
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>
                            بدء الاستيراد
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Instructions -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    تعليمات الاستيراد
                </h5>
            </div>
            <div class="card-body">
                <h6>تنسيق الملف المطلوب:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success me-2"></i>كود النوع (مطلوب)</li>
                    <li><i class="fas fa-check text-success me-2"></i>الوصف (مطلوب)</li>
                    <li><i class="fas fa-check text-success me-2"></i>الحالة (اختياري)</li>
                </ul>

                <hr>

                <h6>ملاحظات مهمة:</h6>
                <ul class="small text-muted">
                    <li>الصف الأول يجب أن يحتوي على العناوين</li>
                    <li>كود النوع يجب أن يكون فريد</li>
                    <li>الحالة: "نشط" أو "غير نشط"</li>
                    <li>في حالة وجود كود مكرر، سيتم تحديث البيانات</li>
                    <li>احفظ الملف بترميز UTF-8 بدون BOM</li>
                    <li>تجنب الأعمدة الإضافية غير المطلوبة</li>
                </ul>

                <hr>

                <div class="text-center">
                    <a href="download-sample.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-download me-2"></i>
                        تحميل نموذج CSV
                    </a>
                    <a href="debug-import.php" class="btn btn-outline-info btn-sm me-2">
                        <i class="fas fa-bug me-2"></i>
                        أداة التشخيص
                    </a>
                    <a href="encoding-test.php" class="btn btn-outline-warning btn-sm me-2">
                        <i class="fas fa-code me-2"></i>
                        اختبار الترميز
                    </a>
                    <a href="test-csv-parsing.php" class="btn btn-outline-success btn-sm me-2">
                        <i class="fas fa-file-csv me-2"></i>
                        اختبار CSV
                    </a>
                    <a href="fix-encoding.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-tools me-2"></i>
                        إصلاح الترميز
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Import Logs -->
<?php if (!empty($recentLogs)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-history me-2"></i>
                سجل عمليات الاستيراد الأخيرة
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>إجمالي السجلات</th>
                            <th>نجح</th>
                            <th>فشل</th>
                            <th>الحالة</th>
                            <th>المستخدم</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['file_name']) ?></td>
                                <td><?= $log['total_records'] ?></td>
                                <td><span class="text-success"><?= $log['successful_records'] ?></span></td>
                                <td><span class="text-danger"><?= $log['failed_records'] ?></span></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'processing' => 'warning'
                                    ];
                                    $statusText = [
                                        'completed' => 'مكتمل',
                                        'failed' => 'فشل',
                                        'processing' => 'قيد المعالجة'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $statusClass[$log['operation_status']] ?? 'secondary' ?>">
                                        <?= $statusText[$log['operation_status']] ?? $log['operation_status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['username'] ?? 'غير معروف') ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

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

    // إظهار loading
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الاستيراد...';
    submitBtn.disabled = true;

    // إعادة تفعيل الزر بعد 30 ثانية كحد أقصى
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 30000);
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
