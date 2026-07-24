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
        SUM(CASE WHEN approval_stage = 'disbursed' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN net_amount ELSE 0 END) as paid_amount,
        SUM(net_amount) as total_amount
    FROM partial_extracts
";
$partialStats = $db->query($partialStatsQuery)->fetch();

// جلب إحصائيات المستخلصات النهائية العادية
$finalRegularStatsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN net_amount ELSE 0 END) as paid_amount,
        SUM(net_amount) as total_amount
    FROM final_regular_extracts
";
$finalRegularStats = $db->query($finalRegularStatsQuery)->fetch();

// جلب إحصائيات المستخلصات النهائية للجزئية
$finalForPartialStatsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN approval_stage = 'disbursed' THEN net_amount ELSE 0 END) as paid_amount,
        SUM(net_amount) as total_amount
    FROM final_for_partial_extracts
";
$finalForPartialStats = $db->query($finalForPartialStatsQuery)->fetch();

// بدء تخزين المحتوى
ob_start();
?>



<div class="container-fluid py-4">
<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-7 col-lg-6">
                    <h2 class="welcome-title mb-2">إدارة المستخلصات</h2>
                    <p class="welcome-subtitle mb-0">نظام شامل لإدارة المستخلصات الجزئية والنهائية بفعالية واحترافية.</p>
                </div>
                <div class="col-md-5 col-lg-6 mt-4 mt-md-0" style="position: relative; z-index: 1;">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2">
                        <a href="update-sap-all.php" class="btn bg-white text-primary fw-bold rounded-pill px-4 shadow-sm border-0">
                            <i class="fas fa-sync-alt me-1"></i> تحديث SAP
                        </a>
                        <a href="reports.php" class="btn bg-white text-primary fw-bold rounded-pill px-4 shadow-sm border-0">
                            <i class="fas fa-chart-line me-1"></i> التقارير
                        </a>
                        <div class="dropdown">
                            <button class="btn bg-white text-primary fw-bold rounded-pill px-4 shadow-sm border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-plus me-1"></i> مستخلص جديد
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2" style="border-radius: 1rem; min-width: 240px;">
                                <li><a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center gap-3 mb-1" href="<?= path('extracts/partial/create.php') ?>">
                                    <div class="bg-primary-soft rounded p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="fas fa-file-alt"></i></div>
                                    <span class="fw-bold">مستخلص جزئي</span>
                                </a></li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center gap-3 mb-1" href="<?= path('extracts/final-for-partial/create.php') ?>">
                                    <div class="bg-warning-soft rounded p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="fas fa-file-contract"></i></div>
                                    <span class="fw-bold">مستخلص نهائي للجزئية</span>
                                </a></li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3 d-flex align-items-center gap-3" href="<?= path('extracts/final-regular/create.php') ?>">
                                    <div class="bg-success-soft rounded p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;"><i class="fas fa-file-invoice"></i></div>
                                    <span class="fw-bold">مستخلص نهائي عادي</span>
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- المستخلصات الجزئية -->
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div class="text-muted small fw-bold mb-1">المستخلصات الجزئية</div>
                        <div class="h3 mb-0 fw-bold text-dark"><?php echo number_format($partialStats['total']); ?></div>
                    </div>
                    <div class="icon-circle bg-primary-soft">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-end border-bottom pb-3 mb-3">
                    <span class="text-muted small fw-bold">إجمالي المبلغ:</span>
                    <span class="text-primary fw-bolder fs-5"><?php echo number_format($partialStats['total_amount'] ?? 0, 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></span>
                </div>
                <div class="d-flex flex-column gap-2 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold"><i class="fas fa-circle text-success me-2 micro-dot"></i> مصروف</span>
                        <span class="fw-bold text-dark"><?php echo number_format($partialStats['paid_amount'] ?? 0, 2); ?> <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold"><i class="fas fa-circle text-warning me-2 micro-dot"></i> غير مصروف</span>
                        <span class="fw-bold text-dark"><?php echo number_format(($partialStats['total_amount'] ?? 0) - ($partialStats['paid_amount'] ?? 0), 2); ?> <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span></span>
                    </div>
                </div>
                <a href="partial/index.php" class="btn bg-primary-soft fw-bold w-100 rounded-pill">عرض المستخلصات</a>
            </div>
        </div>

        <!-- المستخلصات النهائية للجزئية -->
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div class="text-muted small fw-bold mb-1">النهائية للجزئية</div>
                        <div class="h3 mb-0 fw-bold text-dark"><?php echo number_format($finalForPartialStats['total']); ?></div>
                    </div>
                    <div class="icon-circle bg-warning-soft">
                        <i class="fas fa-file-contract"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-end border-bottom pb-3 mb-3">
                    <span class="text-muted small fw-bold">إجمالي المبلغ:</span>
                    <span class="text-warning fw-bolder fs-5"><?php echo number_format($finalForPartialStats['total_amount'] ?? 0, 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></span>
                </div>
                <div class="d-flex flex-column gap-2 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold"><i class="fas fa-circle text-success me-2 micro-dot"></i> مصروف</span>
                        <span class="fw-bold text-dark"><?php echo number_format($finalForPartialStats['paid_amount'] ?? 0, 2); ?> <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold"><i class="fas fa-circle text-warning me-2 micro-dot"></i> غير مصروف</span>
                        <span class="fw-bold text-dark"><?php echo number_format(($finalForPartialStats['total_amount'] ?? 0) - ($finalForPartialStats['paid_amount'] ?? 0), 2); ?> <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span></span>
                    </div>
                </div>
                <a href="final-for-partial/index.php" class="btn bg-warning-soft fw-bold w-100 rounded-pill">عرض المستخلصات</a>
            </div>
        </div>

        <!-- المستخلصات النهائية العادية -->
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div class="text-muted small fw-bold mb-1">النهائية العادية</div>
                        <div class="h3 mb-0 fw-bold text-dark"><?php echo number_format($finalRegularStats['total']); ?></div>
                    </div>
                    <div class="icon-circle bg-success-soft">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-end border-bottom pb-3 mb-3">
                    <span class="text-muted small fw-bold">إجمالي المبلغ:</span>
                    <span class="text-success fw-bolder fs-5"><?php echo number_format($finalRegularStats['total_amount'] ?? 0, 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></span>
                </div>
                <div class="d-flex flex-column gap-2 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold"><i class="fas fa-circle text-success me-2 micro-dot"></i> مصروف</span>
                        <span class="fw-bold text-dark"><?php echo number_format($finalRegularStats['paid_amount'] ?? 0, 2); ?> <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-bold"><i class="fas fa-circle text-warning me-2 micro-dot"></i> غير مصروف</span>
                        <span class="fw-bold text-dark"><?php echo number_format(($finalRegularStats['total_amount'] ?? 0) - ($finalRegularStats['paid_amount'] ?? 0), 2); ?> <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span></span>
                    </div>
                </div>
                <a href="final-regular/index.php" class="btn bg-success-soft fw-bold w-100 rounded-pill">عرض المستخلصات</a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-4">
            <i class="fas fa-bolt text-warning me-2"></i> إجراءات سريعة
        </h5>
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="partial/create.php" class="quick-action-card h-100 d-flex flex-column justify-content-center">
                    <i class="fas fa-file-alt action-icon mt-auto"></i>
                    <div class="quick-action-title">مستخلص جزئي</div>
                    <div class="quick-action-desc mb-auto">إنشاء مستخلص جديد (بدون غرامات)</div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="final-for-partial/create.php" class="quick-action-card h-100 d-flex flex-column justify-content-center">
                    <i class="fas fa-file-contract action-icon mt-auto"></i>
                    <div class="quick-action-title">نهائي للجزئية</div>
                    <div class="quick-action-desc mb-auto">مستخلص نهائي مرتبط بآخر جزئي</div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="final-regular/create.php" class="quick-action-card h-100 d-flex flex-column justify-content-center">
                    <i class="fas fa-file-invoice action-icon mt-auto"></i>
                    <div class="quick-action-title">نهائي عادي</div>
                    <div class="quick-action-desc mb-auto">إنشاء مستخلص نهائي جديد بالكامل</div>
                </a>
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
