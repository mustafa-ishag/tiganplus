<?php
/**
 * صفحة معاينة استيراد المستخلصات النهائية للجزئية
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
require_once __DIR__ . '/../../../includes/FinalForPartialExtractImporter.php';

// إعداد متغيرات الصفحة
$pageTitle = 'معاينة استيراد المستخلصات النهائية للجزئية';
$currentPage = 'extracts-final-for-partial';
$breadcrumbs = [
    ['title' => 'المستخلصات', 'url' => '../index.php'],
    ['title' => 'المستخلصات النهائية للجزئية', 'url' => 'index.php'],
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
        $importer = new FinalForPartialExtractImporter($db, $_SESSION['user_id']);

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
        $importer = new FinalForPartialExtractImporter($db, $_SESSION['user_id']);

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

// معالجة تأكيد الاستيراد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    try {
        $db = getDB();
        $importer = new FinalForPartialExtractImporter($db, $_SESSION['user_id']);
        
        // تأكيد الاستيراد
        $result = $importer->confirmImport($previewData);
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            $_SESSION['import_stats'] = $result['stats'];
            
            // حذف الملف المؤقت
            if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
                unlink($_SESSION['import_file_path']);
            }
            
            // تنظيف متغيرات الجلسة
            unset($_SESSION['import_file_path']);
            unset($_SESSION['import_file_name']);
            
            // إعادة التوجيه لصفحة الاستيراد
            header('Location: import.php');
            exit();
        } else {
            $errors[] = $result['message'];
        }
        
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
                معاينة استيراد المستخلصات النهائية للجزئية
            </h1>
            <p class="text-muted mb-0">معاينة البيانات قبل الاستيراد النهائي</p>
        </div>
        <div>
            <a href="import.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>
                العودة
            </a>
        </div>
    </div>

    <!-- عرض الأخطاء -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h5 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            تم العثور على أخطاء في البيانات
        </h5>
        <hr>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo is_array($error) ? htmlspecialchars($error['message']) : htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- عرض التحذيرات -->
    <?php if (!empty($warnings)): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading">
            <i class="fas fa-exclamation-circle me-2"></i>
            تحذيرات
        </h5>
        <hr>
        <ul class="mb-0">
            <?php foreach ($warnings as $warning): ?>
                <li><?php echo htmlspecialchars($warning); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ملخص الإحصائيات -->
    <?php if (!empty($calculations)): ?>
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي الصفوف
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['total_rows']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                الصفوف الصحيحة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['valid_rows']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                الصفوف بها أخطاء
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['error_rows']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                الصافي المتوقع
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['total_net'], 2); ?> ر.س
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ملخص مالي إضافي -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-info text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-calculator me-2"></i>
                الملخص المالي
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2">
                    <p class="mb-1"><strong>المبلغ الإجمالي:</strong></p>
                    <p class="h5 text-primary">
                        <?php echo number_format($calculations['total_amount'], 2); ?>
                        <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                    </p>
                </div>
                <div class="col-md-2">
                    <p class="mb-1"><strong>الضريبة (15%):</strong></p>
                    <p class="h5 text-success">
                        <?php echo number_format($calculations['total_tax'], 2); ?>
                        <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="mb-1"><strong>ضريبة المستخلص الجزئي:</strong></p>
                    <p class="h5 text-info">
                        <?php echo number_format($calculations['total_partial_tax'] ?? 0, 2); ?>
                        <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                    </p>
                </div>
                <div class="col-md-2">
                    <p class="mb-1"><strong>إجمالي الغرامات:</strong></p>
                    <p class="h5 text-danger">
                        <?php echo number_format($calculations['total_penalty'], 2); ?>
                        <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p class="mb-1"><strong>الصافي:</strong></p>
                    <p class="h5 text-warning">
                        <?php echo number_format($calculations['total_net'], 2); ?>
                        <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                    </p>
                </div>
            </div>
            <hr>
            <p class="mb-0 small text-muted">
                <i class="fas fa-info-circle me-1"></i>
                الصافي = المبلغ الإجمالي + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامات
            </p>
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
                'branch_name' => $row['branch_name'],
                'branch_auto_filled' => !empty($row['branch_auto_filled']),
                'related_partial_extract_number' => $row['related_partial_extract_number'],
                'extract_date' => $row['extract_date'],
                'approval_stage' => $row['approval_stage'],
                'work_orders_count' => 0,
                'total_extract_value' => 0,
                'total_amount_calc' => 0,
                'tax_amount_calc' => 0,
                'partial_extract_tax_amount' => 0,
                'total_penalty_amount' => 0,
                'net_amount_calc' => 0,
                'work_orders' => [],
                'status' => 'success'
            ];
        }

        $extractSummary[$extractNumber]['work_orders_count']++;
        $extractSummary[$extractNumber]['total_extract_value'] += $row['extract_value'];
        $extractSummary[$extractNumber]['work_orders'][] = [
            'number' => $row['work_order_number'],
            'type' => $row['work_order_type_code'],
            'value' => $row['extract_value'],
            'penalty' => $row['penalty_amount']
        ];

        // تحديث الحالة (إذا كان أي صف به خطأ، المستخلص كله يصبح خطأ)
        if ($row['status'] === 'error') {
            $extractSummary[$extractNumber]['status'] = 'error';
        } elseif ($row['status'] === 'warning' && $extractSummary[$extractNumber]['status'] !== 'error') {
            $extractSummary[$extractNumber]['status'] = 'warning';
        }

        // حفظ المبالغ المحسوبة (نفس القيم لجميع صفوف المستخلص)
        $extractSummary[$extractNumber]['total_amount_calc'] = $row['total_amount'];
        $extractSummary[$extractNumber]['tax_amount_calc'] = $row['tax_amount'];
        $extractSummary[$extractNumber]['partial_extract_tax_amount'] = $row['partial_extract_tax_amount'] ?? 0;
        $extractSummary[$extractNumber]['total_penalty_amount'] = $row['total_penalty_amount'];
        $extractSummary[$extractNumber]['net_amount_calc'] = $row['net_amount'];
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
                <h4>
                    <?= number_format(array_sum(array_column($extractSummary, 'total_amount_calc')), 0) ?>
                    <span class="sar-icon-lg" style="filter: brightness(0) invert(1);"><svg><use href="#sar-symbol"/></svg></span>
                </h4>
                <small>المبلغ الإجمالي</small>
            </div>
            <div class="col-md-2">
                <h4>
                    <?= number_format(array_sum(array_column($extractSummary, 'tax_amount_calc')), 0) ?>
                    <span class="sar-icon-lg" style="filter: brightness(0) invert(1);"><svg><use href="#sar-symbol"/></svg></span>
                </h4>
                <small>الضريبة</small>
            </div>
            <div class="col-md-2">
                <h4>
                    <?= number_format(array_sum(array_column($extractSummary, 'total_penalty_amount')), 0) ?>
                    <span class="sar-icon-lg" style="filter: brightness(0) invert(1);"><svg><use href="#sar-symbol"/></svg></span>
                </h4>
                <small>الغرامات</small>
            </div>
            <div class="col-md-2">
                <h4>
                    <?= number_format(array_sum(array_column($extractSummary, 'net_amount_calc')), 0) ?>
                    <span class="sar-icon-lg" style="filter: brightness(0) invert(1);"><svg><use href="#sar-symbol"/></svg></span>
                </h4>
                <small>المبلغ الصافي</small>
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
                            <th>الفرع</th>
                            <th>المستخلص الجزئي</th>
                            <th>تاريخ المستخلص</th>
                            <th>عدد أوامر العمل</th>
                            <th>المبلغ الإجمالي</th>
                            <th>الضريبة (15%)</th>
                            <th>ضريبة المستخلص الجزئي</th>
                            <th>الغرامات</th>
                            <th>الصافي</th>
                            <th>الحالة</th>
                            <th>تفاصيل الأوامر</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extractSummary as $extract): ?>
                        <tr class="<?= $extract['status'] === 'error' ? 'table-danger' : ($extract['status'] === 'warning' ? 'table-warning' : 'table-success') ?>">
                            <td class="extract-number"><?= htmlspecialchars($extract['extract_number']) ?></td>
                            <td>
                                <?= htmlspecialchars($extract['branch_name']) ?>
                                <?php if (!empty($extract['branch_auto_filled'])): ?>
                                    <span class="badge bg-info ms-1" title="تم تحديد الفرع تلقائياً من المستخلص الجزئي">
                                        <i class="fas fa-magic"></i> تلقائي
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($extract['related_partial_extract_number']) ?></td>
                            <td><?= htmlspecialchars($extract['extract_date']) ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= $extract['work_orders_count'] ?>
                                </span>
                            </td>
                            <td class="amount-cell">
                                <?= number_format($extract['total_amount_calc'], 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td class="amount-cell">
                                <?= number_format($extract['tax_amount_calc'], 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td class="amount-cell text-info">
                                <?= number_format($extract['partial_extract_tax_amount'], 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td class="amount-cell text-danger">
                                <?= number_format($extract['total_penalty_amount'], 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td class="amount-cell">
                                <strong>
                                    <?= number_format($extract['net_amount_calc'], 2) ?>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </strong>
                            </td>
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
                                <button type="button" class="btn btn-sm btn-info"
                                        data-bs-toggle="modal"
                                        data-bs-target="#workOrdersModal<?= md5($extract['extract_number']) ?>">
                                    <i class="fas fa-eye"></i> عرض
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                        <tr>
                            <th colspan="5" class="text-end">الإجمالي:</th>
                            <th class="amount-cell">
                                <?= number_format(array_sum(array_column($extractSummary, 'total_amount_calc')), 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </th>
                            <th class="amount-cell">
                                <?= number_format(array_sum(array_column($extractSummary, 'tax_amount_calc')), 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </th>
                            <th class="amount-cell text-info">
                                <?= number_format(array_sum(array_column($extractSummary, 'partial_extract_tax_amount')), 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </th>
                            <th class="amount-cell text-danger">
                                <?= number_format(array_sum(array_column($extractSummary, 'total_penalty_amount')), 2) ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </th>
                            <th class="amount-cell">
                                <strong>
                                    <?= number_format(array_sum(array_column($extractSummary, 'net_amount_calc')), 2) ?>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </strong>
                            </th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Modals لعرض تفاصيل أوامر العمل -->
    <?php foreach ($extractSummary as $extract): ?>
    <div class="modal fade" id="workOrdersModal<?= md5($extract['extract_number']) ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-list me-2"></i>
                        أوامر العمل - المستخلص <?= htmlspecialchars($extract['extract_number']) ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>رقم أمر العمل</th>
                                <th>النوع</th>
                                <th>القيمة</th>
                                <th>الغرامة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($extract['work_orders'] as $index => $wo): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($wo['number']) ?></td>
                                <td><?= htmlspecialchars($wo['type']) ?></td>
                                <td>
                                    <?= number_format($wo['value'], 2) ?>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </td>
                                <td class="text-danger">
                                    <?= number_format($wo['penalty'], 2) ?>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="3" class="text-end">الإجمالي:</th>
                                <th>
                                    <?= number_format(array_sum(array_column($extract['work_orders'], 'value')), 2) ?>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </th>
                                <th class="text-danger">
                                    <?= number_format(array_sum(array_column($extract['work_orders'], 'penalty')), 2) ?>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- جدول معاينة البيانات -->
    <?php endif; ?>

    <?php if (!empty($previewData)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                معاينة البيانات (<?php echo count($previewData); ?> صف)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="previewTable">
                    <thead class="table-light">
                        <tr>
                            <th>الصف</th>
                            <th>الحالة</th>
                            <th>رقم المستخلص</th>
                            <th>الفرع</th>
                            <th>المستخلص الجزئي</th>
                            <th>رقم أمر العمل</th>
                            <th>النوع</th>
                            <th>القيمة</th>
                            <th>الغرامة</th>
                            <th>الأخطاء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $row): ?>
                        <tr class="<?php echo $row['status'] === 'error' ? 'table-danger' : ''; ?>">
                            <td><?php echo $row['row_number']; ?></td>
                            <td>
                                <?php if ($row['status'] === 'valid'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>صحيح
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times me-1"></i>خطأ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['extract_number']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['branch_name']); ?>
                                <?php if (!empty($row['branch_auto_filled'])): ?>
                                    <span class="badge bg-info" title="تم تحديد الفرع تلقائياً من المستخلص الجزئي">
                                        <i class="fas fa-magic"></i> تلقائي
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['related_partial_extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['work_order_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['work_order_type_code']); ?></td>
                            <td><?php echo number_format($row['extract_value'], 2); ?></td>
                            <td><?php echo number_format($row['penalty_amount'], 2); ?></td>
                            <td>
                                <?php if (!empty($row['errors'])): ?>
                                    <ul class="mb-0 small text-danger">
                                        <?php foreach ($row['errors'] as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="mt-4">
                <form method="POST" action="">
                    <?php if ($calculations['error_rows'] == 0): ?>
                        <button type="submit" name="confirm_import" class="btn btn-success btn-lg">
                            <i class="fas fa-check me-2"></i>
                            تأكيد الاستيراد
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-times me-2"></i>
                            لا يمكن الاستيراد (يوجد أخطاء)
                        </button>
                    <?php endif; ?>
                    
                    <a href="import.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين التخطيط
include __DIR__ . '/../../includes/layout.php';
?>

<style>
.summary-stats {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
    padding: 30px;
    border-radius: 15px;
    color: white;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.summary-stats h4 {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.summary-stats small {
    font-size: 0.85rem;
    opacity: 0.9;
}

.amount-cell {
    text-align: right;
    font-family: 'Courier New', monospace;
    font-weight: 500;
}

#summaryTable thead th {
    background-color: #4e73df;
    color: white;
    font-weight: 600;
    text-align: center;
    vertical-align: middle;
    border: none;
}

#summaryTable tbody tr:hover {
    background-color: #f8f9fc;
    transform: scale(1.01);
    transition: all 0.2s ease;
}

.extract-number {
    font-weight: bold;
    color: #4e73df;
}

/* رمز الريال السعودي */
.sar-icon {
    display: inline-block;
    width: 14px;
    height: 14px;
    margin-left: 3px;
    vertical-align: middle;
}

.sar-icon-lg {
    display: inline-block;
    width: 18px;
    height: 18px;
    margin-left: 4px;
    vertical-align: middle;
}

.sar-icon svg,
.sar-icon-lg svg {
    width: 100%;
    height: 100%;
}
</style>

<!-- تعريف رمز الريال السعودي SVG -->
<svg style="display: none;">
    <symbol id="sar-symbol" viewBox="0 0 1124.14 1256.39">
        <path d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z"/>
        <path d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z"/>
    </symbol>
</svg>

<script>
$(document).ready(function() {
    // تفعيل DataTables لجدول الملخص
    $('#summaryTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json'
        },
        pageLength: 10,
        order: [[0, 'asc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> تصدير Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> تصدير PDF',
                className: 'btn btn-danger btn-sm'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> طباعة',
                className: 'btn btn-info btn-sm'
            }
        ]
    });

    // تفعيل DataTables لجدول التفاصيل
    $('#previewTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json'
        },
        pageLength: 25,
        order: [[0, 'asc']]
    });
});
</script>

