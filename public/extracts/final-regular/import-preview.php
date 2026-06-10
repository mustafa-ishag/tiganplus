<?php
/**
 * صفحة معاينة استيراد المستخلصات النهائية العادية
 * Final Regular Extracts Import Preview Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_import')) {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/../../../includes/FinalRegularExtractImporter.php';

$pageTitle = 'معاينة استيراد المستخلصات النهائية العادية';
$user_id = $_SESSION['user_id'];

$previewData = [];
$errors = [];
$warnings = [];
$calculations = [];

// معالجة الملف للمعاينة
if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
    try {
        $db = getDB();
        $importer = new FinalRegularExtractImporter($db, $user_id);

        // قراءة البيانات للمعاينة فقط
        $previewResult = $importer->previewImport($_SESSION['import_file_path'], $_SESSION['import_file_name']);

        $previewData = $previewResult['data'] ?? [];
        $errors = $previewResult['errors'] ?? [];
        $warnings = $previewResult['warnings'] ?? [];
        $calculations = $previewResult['calculations'] ?? [];

    } catch (Exception $e) {
        $errors[] = ['message' => $e->getMessage()];
    }
} else {
    header('Location: import.php');
    exit();
}

// معالجة تأكيد الاستيراد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    try {
        $db = getDB();
        $importer = new FinalRegularExtractImporter($db, $user_id);
        
        // تأكيد الاستيراد
        $result = $importer->confirmImport($previewData);
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            $_SESSION['import_stats'] = $result['stats'];
            
            // حذف الملف المؤقت
            if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
                unlink($_SESSION['import_file_path']);
            }
            unset($_SESSION['import_file_path']);
            unset($_SESSION['import_file_name']);
            
            header('Location: index.php');
            exit();
        }
        
    } catch (Exception $e) {
        $errors[] = ['message' => $e->getMessage()];
    }
}

// حساب الإحصائيات
$totalExtracts = count($calculations);
$totalWorkOrders = count($previewData);
$totalAmount = array_sum(array_column($calculations, 'total_amount'));
$totalTax = array_sum(array_column($calculations, 'tax_amount'));
$totalPenalty = array_sum(array_column($calculations, 'total_penalty_amount'));
$totalNet = array_sum(array_column($calculations, 'net_amount'));

// بدء تخزين المحتوى
ob_start();
?>

<!-- رمز الريال السعودي SVG -->
<svg style="display: none;">
    <symbol id="sar-icon" viewBox="0 0 1124.14 1256.39">
        <path class="cls-1" d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z"/>
        <path class="cls-1" d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z"/>
    </symbol>
</svg>

<style>
.sar-icon {
    width: 14px;
    height: 14px;
    fill: currentColor;
    display: inline-block;
    vertical-align: middle;
    margin-left: 4px;
}
.sar-icon-lg {
    width: 18px;
    height: 18px;
}
</style>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/etganplus/public/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="index.php">المستخلصات النهائية العادية</a></li>
                <li class="breadcrumb-item"><a href="import.php">استيراد</a></li>
                <li class="breadcrumb-item active">معاينة</li>
            </ol>
        </nav>
    </div>

    <!-- الأخطاء -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-triangle me-2"></i>
                تم العثور على أخطاء (<?php echo count($errors); ?>)
            </h5>
            <hr>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?php if (isset($error['row_number'])): ?>
                            <strong>الصف <?php echo $error['row_number']; ?>:</strong>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($error['message'] ?? $error); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- التحذيرات -->
    <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-circle me-2"></i>
                تحذيرات (<?php echo count($warnings); ?>)
            </h5>
            <hr>
            <ul class="mb-0">
                <?php foreach ($warnings as $warning): ?>
                    <li><?php echo htmlspecialchars($warning); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ملخص مالي -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-calculator me-2"></i>
                        الملخص المالي
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2">
                            <div class="border-end">
                                <h6 class="text-muted mb-2">عدد المستخلصات</h6>
                                <h4 class="text-primary mb-0"><?php echo number_format($totalExtracts); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border-end">
                                <h6 class="text-muted mb-2">عدد أوامر العمل</h6>
                                <h4 class="text-info mb-0"><?php echo number_format($totalWorkOrders); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border-end">
                                <h6 class="text-muted mb-2">المبلغ الإجمالي</h6>
                                <h4 class="text-success mb-0">
                                    <?php echo number_format($totalAmount, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border-end">
                                <h6 class="text-muted mb-2">الضريبة (15%)</h6>
                                <h4 class="text-warning mb-0">
                                    <?php echo number_format($totalTax, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border-end">
                                <h6 class="text-muted mb-2">الغرامات</h6>
                                <h4 class="text-danger mb-0">
                                    <?php echo number_format($totalPenalty, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div>
                                <h6 class="text-muted mb-2">الصافي</h6>
                                <h4 class="text-dark mb-0">
                                    <?php echo number_format($totalNet, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول ملخص المستخلصات -->
    <?php if (!empty($calculations)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-info text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-list me-2"></i>
                    ملخص المستخلصات (<?php echo count($calculations); ?> مستخلص)
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>رقم المستخلص</th>
                                <th>عدد أوامر العمل</th>
                                <th>المبلغ الإجمالي</th>
                                <th>الضريبة (15%)</th>
                                <th>الغرامات</th>
                                <th>الصافي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calculations as $calc): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($calc['extract_number']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $calc['work_orders_count']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($calc['total_amount'], 2); ?>
                                        <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($calc['tax_amount'], 2); ?>
                                        <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($calc['total_penalty_amount'], 2); ?>
                                        <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                    </td>
                                    <td class="text-end">
                                        <strong>
                                            <?php echo number_format($calc['net_amount'], 2); ?>
                                            <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th>الإجمالي</th>
                                <th class="text-center"><?php echo number_format($totalWorkOrders); ?></th>
                                <th class="text-end">
                                    <?php echo number_format($totalAmount, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </th>
                                <th class="text-end">
                                    <?php echo number_format($totalTax, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </th>
                                <th class="text-end">
                                    <?php echo number_format($totalPenalty, 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </th>
                                <th class="text-end">
                                    <strong>
                                        <?php echo number_format($totalNet, 2); ?>
                                        <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                    </strong>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- جدول تفاصيل الصفوف -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                تفاصيل الصفوف (<?php echo count($previewData); ?> صف)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover" id="previewTable">
                    <thead class="table-dark">
                        <tr>
                            <th>الصف</th>
                            <th>رقم المستخلص</th>
                            <th>الفرع</th>
                            <th>القسم</th>
                            <th>التاريخ</th>
                            <th>المرحلة</th>
                            <th>رقم أمر العمل</th>
                            <th>النوع</th>
                            <th>تاريخ الإنجاز</th>
                            <th>القيمة</th>
                            <th>الغرامة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $row): ?>
                            <tr class="<?php echo $row['status'] === 'error' ? 'table-danger' : ''; ?>">
                                <td><?php echo $row['row_number']; ?></td>
                                <td><?php echo htmlspecialchars($row['extract_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['branch_name']); ?>
                                    <?php if (!empty($row['branch_auto_filled'])): ?>
                                        <span class="badge bg-info" title="تم جلبه تلقائياً من أمر العمل">
                                            <i class="fas fa-magic"></i> تلقائي
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $deptNames = [
                                        'connections' => 'التوصيلات',
                                        'projects' => 'المشاريع'
                                    ];
                                    echo $deptNames[$row['department']] ?? $row['department'];
                                    ?>
                                    <?php if (!empty($row['department_auto_filled'])): ?>
                                        <span class="badge bg-info" title="تم جلبه تلقائياً من أمر العمل">
                                            <i class="fas fa-magic"></i> تلقائي
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['extract_date']; ?></td>
                                <td>
                                    <?php
                                    $stageNames = [
                                        'technical_support' => 'الدعم الفني',
                                        'construction' => 'الإنشاءات',
                                        'department_manager' => 'مدير القسم',
                                        'administration_manager' => 'مدير الإدارة',
                                        'taif_finance' => 'مالية الطائف',
                                        'disbursed' => 'تم الصرف'
                                    ];
                                    echo $stageNames[$row['approval_stage']] ?? $row['approval_stage'];
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['work_order_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['work_order_type_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['completion_date']); ?></td>
                                <td class="text-end">
                                    <?php echo number_format($row['extract_value'], 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($row['penalty_amount'], 2); ?>
                                    <svg class="sar-icon"><use href="#sar-icon"></use></svg>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'success'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> جاهز
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times"></i> خطأ
                                        </span>
                                        <?php if (!empty($row['errors'])): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="popover"
                                                    data-bs-trigger="hover"
                                                    data-bs-placement="left"
                                                    data-bs-html="true"
                                                    data-bs-content="<?php echo htmlspecialchars(implode('<br>', $row['errors'])); ?>">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- أزرار التحكم -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <a href="import.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>
                        إلغاء والعودة
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <?php if (empty($errors)): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit"
                                    name="confirm_import"
                                    class="btn btn-success btn-lg"
                                    onclick="return confirm('هل أنت متأكد من تأكيد الاستيراد؟');">
                                <i class="fas fa-check me-2"></i>
                                تأكيد الاستيراد
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-danger btn-lg" disabled>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            لا يمكن الاستيراد - يوجد أخطاء
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تفعيل Popovers
document.addEventListener('DOMContentLoaded', function() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>

