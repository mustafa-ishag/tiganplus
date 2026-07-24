<?php
/**
 * صفحة معاينة استيراد المستخلصات الجزئية
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_import')) {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/../../../includes/PartialExtractImporter.php';

// إعداد متغيرات الصفحة
$pageTitle = 'معاينة استيراد المستخلصات الجزئية';
$currentPage = 'extracts-partial';
$breadcrumbs = [
    ['title' => 'المستخلصات', 'url' => '../index.php'],
    ['title' => 'المستخلصات الجزئية', 'url' => 'index.php'],
    ['title' => 'استيراد', 'url' => 'import.php'],
    ['title' => 'معاينة', 'url' => '']
];

$previewData = [];
$errors = [];
$warnings = [];
$calculations = [];

// معالجة الملف للمعاينة
if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
    try {
        // إنشاء مستورد للمعاينة
        $db = getDB();
        $importer = new PartialExtractImporter($db, $_SESSION['user_id']);

        // قراءة البيانات للمعاينة فقط
        $previewResult = $importer->previewImport($_SESSION['import_file_path'], $_SESSION['import_file_name']);

        $previewData = $previewResult['data'] ?? [];
        $errors = $previewResult['errors'] ?? [];
        $warnings = $previewResult['warnings'] ?? [];
        $calculations = $previewResult['calculations'] ?? [];

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    // معالجة رفع ملف جديد للمعاينة
    try {
        $uploadedFile = $_FILES['excel_file'];

        // التحقق من الملف
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطأ في رفع الملف');
        }

        $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, ['xls', 'xlsx'])) {
            throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف Excel (.xls أو .xlsx)');
        }

        // إنشاء مستورد للمعاينة
        $db = getDB();
        $importer = new PartialExtractImporter($db, $_SESSION['user_id']);

        // قراءة البيانات للمعاينة فقط
        $previewResult = $importer->previewImport($uploadedFile['tmp_name'], $uploadedFile['name']);

        $previewData = $previewResult['data'] ?? [];
        $errors = $previewResult['errors'] ?? [];
        $warnings = $previewResult['warnings'] ?? [];
        $calculations = $previewResult['calculations'] ?? [];

    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-eye text-primary me-2"></i>
                معاينة استيراد المستخلصات الجزئية
            </h1>
            <p class="text-muted mb-0">معاينة البيانات قبل الاستيراد النهائي</p>
        </div>
        <div>
            <a href="import.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>
                العودة للاستيراد
            </a>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-list me-1"></i>
                قائمة المستخلصات
            </a>
        </div>
    </div>

    <?php if (empty($previewData) && empty($errors)): ?>
    <!-- نموذج رفع الملف للمعاينة -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload me-2"></i>
                        رفع ملف للمعاينة
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="previewForm">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">ملف Excel</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                   accept=".xls,.xlsx" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                يدعم ملفات Excel (.xls, .xlsx) فقط
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-info btn-lg">
                                <i class="fas fa-eye me-2"></i>
                                معاينة البيانات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <!-- عرض الأخطاء -->
    <div class="alert alert-danger">
        <h6 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            أخطاء في الملف
        </h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($warnings)): ?>
    <!-- عرض التحذيرات -->
    <div class="alert alert-warning">
        <h6 class="alert-heading">
            <i class="fas fa-exclamation-circle me-2"></i>
            تحذيرات
        </h6>
        <ul class="mb-0">
            <?php foreach ($warnings as $warning): ?>
            <li><?= htmlspecialchars($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($calculations)): ?>
    <!-- عرض الحسابات التلقائية -->
    <div class="alert alert-info">
        <h6 class="alert-heading">
            <i class="fas fa-calculator me-2"></i>
            الحسابات التلقائية
        </h6>
        <div class="row">
            <div class="col-md-4">
                <strong>المبلغ الإجمالي:</strong> <?= number_format($calculations['total_amount'] ?? 0, 2) ?> ريال
            </div>
            <div class="col-md-4">
                <strong>الضريبة (15%):</strong> <?= number_format($calculations['tax_amount'] ?? 0, 2) ?> ريال
            </div>
            <div class="col-md-4">
                <strong>المبلغ الصافي:</strong> <?= number_format($calculations['net_amount'] ?? 0, 2) ?> ريال
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($previewData)): ?>
    <?php
    // تجميع البيانات حسب المستخلص
    $extractSummary = [];
    foreach ($previewData as $row) {
        $extractNumber = $row['extract_number'];
        if (!isset($extractSummary[$extractNumber])) {
            $extractSummary[$extractNumber] = [
                'extract_number' => $extractNumber,
                'invoice_number' => $row['invoice_number'],
                'branch_name' => $row['branch_name'],
                'department_auto' => $row['department_auto'],
                'extract_date' => $row['extract_date'],
                'work_orders_count' => 0,
                'total_extract_value' => 0,
                'total_amount_calc' => 0,
                'tax_amount_calc' => 0,
                'net_amount_calc' => 0,
                'work_orders' => [],
                'status' => 'success'
            ];
        }

        $extractSummary[$extractNumber]['work_orders_count']++;
        $extractSummary[$extractNumber]['total_extract_value'] += $row['extract_value'];
        $extractSummary[$extractNumber]['work_orders'][] = [
            'number' => $row['work_order_number'],
            'type' => $row['work_order_type'],
            'value' => $row['extract_value']
        ];

        // تحديث الحالة (إذا كان أي صف به خطأ، المستخلص كله يصبح خطأ)
        if ($row['status'] === 'error') {
            $extractSummary[$extractNumber]['status'] = 'error';
        } elseif ($row['status'] === 'warning' && $extractSummary[$extractNumber]['status'] !== 'error') {
            $extractSummary[$extractNumber]['status'] = 'warning';
        }
    }

    // حساب المبالغ لكل مستخلص
    foreach ($extractSummary as &$extract) {
        $calculations = [
            'total_amount' => $extract['total_extract_value'],
            'tax_amount' => $extract['total_extract_value'] * 0.15,
            'net_amount' => $extract['total_extract_value'] // الصافي = المبلغ الإجمالي بدون ضريبة
        ];
        $extract['total_amount_calc'] = $calculations['total_amount'];
        $extract['tax_amount_calc'] = $calculations['tax_amount'];
        $extract['net_amount_calc'] = $calculations['net_amount'];
    }
    ?>

    <!-- إحصائيات سريعة -->
    <div class="summary-stats mb-4">
        <div class="row text-center">
            <div class="col-md-2">
                <h4><?= count($extractSummary) ?></h4>
                <small>عدد المستخلصات</small>
            </div>
            <div class="col-md-2">
                <h4><?= array_sum(array_column($extractSummary, 'work_orders_count')) ?></h4>
                <small>إجمالي أوامر العمل</small>
            </div>
            <div class="col-md-2">
                <h4><?= number_format(array_sum(array_column($extractSummary, 'total_amount_calc')), 0) ?></h4>
                <small>المبلغ الإجمالي (ريال)</small>
            </div>
            <div class="col-md-2">
                <h4><?= number_format(array_sum(array_column($extractSummary, 'tax_amount_calc')), 0) ?></h4>
                <small>إجمالي الضريبة (ريال)</small>
            </div>
            <div class="col-md-2">
                <h4><?= number_format(array_sum(array_column($extractSummary, 'net_amount_calc')), 0) ?></h4>
                <small>المبلغ الصافي (ريال)</small>
            </div>
        </div>
    </div>

    <!-- ملخص المستخلصات -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                ملخص المستخلصات (<?= count($extractSummary) ?> مستخلص)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="summaryTable">
                    <thead class="table-primary">
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم الفاتورة</th>
                            <th>الفرع</th>
                            <th>القسم</th>
                            <th>تاريخ المستخلص</th>
                            <th>عدد أوامر العمل</th>
                            <th>المبلغ الإجمالي</th>
                            <th>الضريبة (15%)</th>
                            <th>المبلغ الصافي (بدون ضريبة)</th>
                            <th>الحالة</th>
                            <th>تفاصيل الأوامر</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extractSummary as $extract): ?>
                        <tr class="<?= $extract['status'] === 'error' ? 'table-danger' : ($extract['status'] === 'warning' ? 'table-warning' : 'table-success') ?>">
                            <td class="extract-number"><?= htmlspecialchars($extract['extract_number']) ?></td>
                            <td><?= htmlspecialchars($extract['invoice_number']) ?></td>
                            <td><?= htmlspecialchars($extract['branch_name']) ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($extract['department_auto']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($extract['extract_date']) ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= $extract['work_orders_count'] ?>
                                </span>
                            </td>
                            <td class="amount-cell"><?= number_format($extract['total_amount_calc'], 2) ?> ريال</td>
                            <td class="amount-cell"><?= number_format($extract['tax_amount_calc'], 2) ?> ريال</td>
                            <td class="amount-cell"><strong><?= number_format($extract['net_amount_calc'], 2) ?> ريال</strong></td>
                            <td>
                                <?php if ($extract['status'] === 'success'): ?>
                                    <span class="badge bg-success">جاهز</span>
                                <?php elseif ($extract['status'] === 'warning'): ?>
                                    <span class="badge bg-warning">تحذير</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">خطأ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info collapse-toggle" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#workOrders<?= md5($extract['extract_number']) ?>"
                                        aria-expanded="false">
                                    <i class="fas fa-eye me-1"></i>
                                    عرض الأوامر
                                </button>
                                <div class="collapse mt-2" id="workOrders<?= md5($extract['extract_number']) ?>">
                                    <div class="card card-body">
                                        <small>
                                            <?php foreach ($extract['work_orders'] as $wo): ?>
                                            <div class="d-flex justify-content-between border-bottom py-1">
                                                <span><strong><?= htmlspecialchars($wo['number']) ?></strong> - <?= htmlspecialchars($wo['type']) ?></span>
                                                <span><?= number_format($wo['value'], 2) ?> ريال</span>
                                            </div>
                                            <?php endforeach; ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="5">الإجمالي</th>
                            <th>
                                <span class="badge bg-dark">
                                    <?= array_sum(array_column($extractSummary, 'work_orders_count')) ?>
                                </span>
                            </th>
                            <th><?= number_format(array_sum(array_column($extractSummary, 'total_amount_calc')), 2) ?> ريال</th>
                            <th><?= number_format(array_sum(array_column($extractSummary, 'tax_amount_calc')), 2) ?> ريال</th>
                            <th><strong><?= number_format(array_sum(array_column($extractSummary, 'net_amount_calc')), 2) ?> ريال</strong></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- عرض البيانات التفصيلية للمعاينة -->
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-table me-2"></i>
                تفاصيل البيانات (<?= count($previewData) ?> صف)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="previewTable">
                    <thead class="table-dark">
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم الفاتورة</th>
                            <th>الفرع</th>
                            <th>القسم</th>
                            <th>تاريخ المستخلص</th>
                            <th>رقم أمر العمل</th>
                            <th>نوع أمر العمل</th>
                            <th>تاريخ الإنجاز</th>
                            <th>قيمة المستخلص</th>
                            <th>المبلغ الإجمالي</th>
                            <th>الضريبة</th>
                            <th>المبلغ الصافي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $row): ?>
                        <tr class="<?= $row['status'] === 'error' ? 'table-danger' : ($row['status'] === 'warning' ? 'table-warning' : 'table-success') ?>">
                            <td><?= htmlspecialchars($row['extract_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['invoice_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['branch_name'] ?? '') ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= htmlspecialchars($row['department_auto'] ?? 'غير محدد') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['extract_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['work_order_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['work_order_type'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['completion_date'] ?? '') ?></td>
                            <td><?= number_format($row['extract_value'] ?? 0, 2) ?></td>
                            <td><?= number_format($row['total_amount_calc'] ?? 0, 2) ?></td>
                            <td><?= number_format($row['tax_amount_calc'] ?? 0, 2) ?></td>
                            <td><?= number_format($row['net_amount_calc'] ?? 0, 2) ?></td>
                            <td>
                                <?php if ($row['status'] === 'success'): ?>
                                    <span class="badge bg-success">جاهز</span>
                                <?php elseif ($row['status'] === 'warning'): ?>
                                    <span class="badge bg-warning">تحذير</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">خطأ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($previewData) && empty($errors)): ?>
            <!-- أزرار التأكيد -->
            <div class="mt-4 text-center">
                <form method="POST" action="import-confirm.php" id="confirmForm">
                    <input type="hidden" name="preview_data" value="<?= htmlspecialchars(json_encode($previewData)) ?>">
                    <button type="submit" class="btn btn-success btn-lg me-3">
                        <i class="fas fa-check me-2"></i>
                        تأكيد الاستيراد
                    </button>
                    <a href="import.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.extract-summary-card {
    border-left: 4px solid #0d6efd;
}

.work-order-details {
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    padding: 0.5rem;
}

.summary-stats {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
    color: white;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.table-hover tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.1) !important;
}

.collapse-toggle {
    transition: all 0.3s ease;
}

.collapse-toggle:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.extract-number {
    font-family: 'Courier New', monospace;
    font-weight: bold;
}

.amount-cell {
    font-family: 'Arial', sans-serif;
    font-weight: 600;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // تفعيل DataTables لجدول الملخص
    if ($('#summaryTable').length) {
        $('#summaryTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            pageLength: 10,
            order: [[0, 'asc']],
            responsive: true,
            columnDefs: [
                { targets: [10], orderable: false } // عمود تفاصيل الأوامر غير قابل للترتيب
            ]
        });
    }

    // تفعيل DataTables لجدول التفاصيل
    if ($('#previewTable').length) {
        $('#previewTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            pageLength: 25,
            order: [[0, 'asc']],
            responsive: true
        });
    }

    // تأكيد الاستيراد
    $('#confirmForm').on('submit', function(e) {
        if (!confirm('هل أنت متأكد من تأكيد استيراد هذه البيانات؟')) {
            e.preventDefault();
        }
    });

    // إضافة تأثيرات بصرية للجداول
    $('.table-hover tbody tr').hover(
        function() {
            $(this).addClass('table-active');
        },
        function() {
            $(this).removeClass('table-active');
        }
    );
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../../public/includes/layout.php';
?>
