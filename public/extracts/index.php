<?php
/**
 * صفحة إدارة المستخلصات - النظام الجديد
 * Extracts Management Page - New System
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'إدارة المستخلصات';
$currentPage = 'extracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_view')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// جلب إحصائيات المستخلصات الجزئية
$partialStatsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(net_amount) as total_amount
    FROM partial_extracts
";
$partialStats = $db->query($partialStatsQuery)->fetch();

// جلب إحصائيات المستخلصات النهائية العادية
$finalRegularStatsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN approval_stage = 'technical_support' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN approval_stage IN ('construction', 'department_manager') THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN approval_stage IN ('administration_manager', 'taif_finance') THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN 1 ELSE 0 END) as paid,
        SUM(net_amount) as total_amount
    FROM final_regular_extracts
";
$finalRegularStats = $db->query($finalRegularStatsQuery)->fetch();

// جلب إحصائيات المستخلصات النهائية للجزئية
$finalForPartialStatsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN approval_stage = 'technical_support' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN approval_stage IN ('construction', 'department_manager') THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN approval_stage IN ('administration_manager', 'taif_finance') THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN 1 ELSE 0 END) as paid,
        SUM(net_amount) as total_amount
    FROM final_for_partial_extracts
";
$finalForPartialStats = $db->query($finalForPartialStatsQuery)->fetch();

// بدء تخزين المحتوى
ob_start();
?>

<!-- تعريف رمز الريال السعودي SVG -->
<svg style="display: none;">
    <symbol id="sar-symbol" viewBox="0 0 1124.14 1256.39">
        <path d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z"/>
        <path d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z"/>
    </symbol>
</svg>

<style>
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

<div class="container-fluid">
<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">نظام شامل لإدارة المستخلصات الجزئية والنهائية</p>
    </div>
    <div>
        <a href="update-sap-all.php" class="btn btn-warning me-2">
            <i class="fas fa-sync-alt me-1"></i>
            تحديث SAP الشامل
        </a>
        <a href="reports.php" class="btn btn-info me-2">
            <i class="fas fa-chart-line me-1"></i>
            التقارير الشاملة
        </a>
        <div class="dropdown d-inline-block">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-plus me-1"></i>
                مستخلص جديد
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?= path('extracts/partial/create.php') ?>">
                    <i class="fas fa-circle text-primary me-2"></i>مستخلص جزئي
                </a></li>
                <li><a class="dropdown-item" href="<?= path('extracts/final-for-partial/create.php') ?>">
                    <i class="fas fa-circle text-warning me-2"></i>مستخلص نهائي للجزئية
                </a></li>
                <li><a class="dropdown-item" href="<?= path('extracts/final-regular/create.php') ?>">
                    <i class="fas fa-circle text-success me-2"></i>مستخلص نهائي عادي
                </a></li>
            </ul>
        </div>
    </div>
</div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- المستخلصات الجزئية -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                المستخلصات الجزئية
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($partialStats['total']); ?>
                            </div>
                            <div class="text-xs text-muted">
                                المبلغ الإجمالي: <?php echo number_format($partialStats['total_amount'] ?? 0, 2); ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <small class="text-muted">
                                مسودة: <?php echo $partialStats['draft']; ?> |
                                مقدمة: <?php echo $partialStats['submitted']; ?> |
                                معتمدة: <?php echo $partialStats['approved']; ?>
                            </small>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <a href="partial/index.php" class="btn btn-outline-primary btn-sm me-2">
                                <i class="fas fa-list me-1"></i>
                                عرض الكل
                            </a>
                            <a href="partial/create.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                إنشاء جديد
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- المستخلصات النهائية للجزئية -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                المستخلصات النهائية للجزئية
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($finalForPartialStats['total']); ?>
                            </div>
                            <div class="text-xs text-muted">
                                المبلغ الإجمالي: <?php echo number_format($finalForPartialStats['total_amount'] ?? 0, 2); ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-contract fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <small class="text-muted">
                                مسودة: <?php echo $finalForPartialStats['draft']; ?> |
                                مقدمة: <?php echo $finalForPartialStats['submitted']; ?> |
                                معتمدة: <?php echo $finalForPartialStats['approved']; ?>
                            </small>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <a href="final-for-partial/index.php" class="btn btn-outline-warning btn-sm me-2">
                                <i class="fas fa-list me-1"></i>
                                عرض الكل
                            </a>
                            <a href="final-for-partial/create.php" class="btn btn-warning btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                إنشاء جديد
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- المستخلصات النهائية العادية -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                المستخلصات النهائية العادية
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($finalRegularStats['total']); ?>
                            </div>
                            <div class="text-xs text-muted">
                                المبلغ الإجمالي: <?php echo number_format($finalRegularStats['total_amount'] ?? 0, 2); ?>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <small class="text-muted">
                                مسودة: <?php echo $finalRegularStats['draft']; ?> |
                                مقدمة: <?php echo $finalRegularStats['submitted']; ?> |
                                معتمدة: <?php echo $finalRegularStats['approved']; ?>
                            </small>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col">
                            <a href="final-regular/index.php" class="btn btn-outline-success btn-sm me-2">
                                <i class="fas fa-list me-1"></i>
                                عرض الكل
                            </a>
                            <a href="final-regular/create.php" class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                إنشاء جديد
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle me-2"></i>
                        إنشاء مستخلص جديد
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="partial/create.php" class="btn btn-primary btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="fas fa-file-alt fa-3x mb-2"></i>
                                <h5 class="mb-1">مستخلص جزئي</h5>
                                <small>PE-YYYY-XXX</small>
                                <small class="text-light">بدون غرامات</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="final-for-partial/create.php" class="btn btn-warning btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="fas fa-file-contract fa-3x mb-2"></i>
                                <h5 class="mb-1">مستخلص نهائي للجزئية</h5>
                                <small>FFPE-YYYY-XXX</small>
                                <small class="text-dark">مرتبط بجزئي</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="final-regular/create.php" class="btn btn-success btn-lg w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="fas fa-file-invoice fa-3x mb-2"></i>
                                <h5 class="mb-1">مستخلص نهائي عادي</h5>
                                <small>FRE-YYYY-XXX</small>
                                <small class="text-light">مع الغرامات</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
