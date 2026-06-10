<?php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'لوحة التحكم';
$currentPage = 'dashboard';

$breadcrumbs = [
    ['title' => 'لوحة التحكم', 'url' => 'dashboard.php']
];

// التحقق من تسجيل الدخول سيتم في layout.php

// جلب معلومات المستخدم الحالي
$user = null;
if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// جلب إحصائيات سريعة
try {
    $db = getDB();

    // إحصائيات أوامر العمل
    $stmt = $db->query("SELECT COUNT(*) as total FROM work_orders");
    $totalWorkOrders = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as active FROM work_orders WHERE status = 'active'");
    $activeWorkOrders = $stmt->fetch()['active'];

    $stmt = $db->query("SELECT COUNT(*) as completed FROM work_orders WHERE status = 'completed'");
    $completedWorkOrders = $stmt->fetch()['completed'];

    // إحصائيات المستخلصات
    $stmt = $db->query("SELECT COUNT(*) as total FROM partial_extracts");
    $totalPartialExtracts = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM final_regular_extracts");
    $totalFinalExtracts = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM final_for_partial_extracts");
    $totalFinalForPartialExtracts = $stmt->fetch()['total'];

    // إحصائيات الفروع
    $stmt = $db->query("SELECT COUNT(*) as total FROM branches WHERE status = 'active'");
    $totalBranches = $stmt->fetch()['total'];

    // إحصائيات المستخدمين
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['total'];

    // القيم المالية - محسوبة بشكل دقيق
    // القيمة المقدرة: مجموع قيم جميع أوامر العمل النشطة
    $stmt = $db->query("SELECT SUM(estimated_value) as total FROM work_orders WHERE status = 'active'");
    $totalEstimatedValue = (float)($stmt->fetch()['total'] ?? 0);

    // القيمة الفعلية: مجموع قيم جميع أوامر العمل المكتملة
    $stmt = $db->query("SELECT SUM(actual_value) as total FROM work_orders WHERE status = 'completed'");
    $totalActualValue = (float)($stmt->fetch()['total'] ?? 0);

    // إحصائيات المخزون
    $stmt = $db->query("SELECT COUNT(*) as total FROM materials WHERE is_active = 1");
    $totalMaterials = $stmt->fetch()['total'] ?? 0;

    // آخر أوامر عمل
    $stmt = $db->query("
        SELECT wo.id, wo.work_order_number, wo.status, b.name as branch_name, wo.created_at
        FROM work_orders wo
        LEFT JOIN branches b ON wo.branch_id = b.id
        ORDER BY wo.created_at DESC
        LIMIT 5
    ");
    $recentWorkOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // آخر مستخلصات
    $stmt = $db->query("
        SELECT id, extract_number, extract_date, net_amount, 'partial' as type
        FROM partial_extracts
        UNION ALL
        SELECT id, extract_number, extract_date, net_amount, 'final_regular' as type
        FROM final_regular_extracts
        ORDER BY extract_date DESC
        LIMIT 5
    ");
    $recentExtracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // بيانات الرسوم البيانية - توزيع أوامر العمل حسب الحالة
    $stmt = $db->query("
        SELECT status, COUNT(*) as count
        FROM work_orders
        GROUP BY status
    ");
    $workOrdersByStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $workOrdersStatusData = json_encode($workOrdersByStatus);

    // بيانات الرسوم البيانية - توزيع المستخلصات
    $extractsData = [
        ['name' => 'جزئية', 'count' => $totalPartialExtracts],
        ['name' => 'نهائية عادية', 'count' => $totalFinalExtracts],
        ['name' => 'نهائية للجزئية', 'count' => $totalFinalForPartialExtracts]
    ];
    $extractsChartData = json_encode($extractsData);

    // بيانات الرسوم البيانية - توزيع أوامر العمل حسب الفرع
    $stmt = $db->query("
        SELECT b.name, COUNT(wo.id) as count
        FROM work_orders wo
        LEFT JOIN branches b ON wo.branch_id = b.id
        GROUP BY wo.branch_id, b.name
    ");
    $workOrdersByBranch = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $branchesChartData = json_encode($workOrdersByBranch);

} catch (Exception $e) {
    $totalWorkOrders = 0;
    $activeWorkOrders = 0;
    $completedWorkOrders = 0;
    $totalPartialExtracts = 0;
    $totalFinalExtracts = 0;
    $totalFinalForPartialExtracts = 0;
    $totalBranches = 0;
    $totalUsers = 0;
    $totalEstimatedValue = 0;
    $totalActualValue = 0;
    $totalMaterials = 0;
    $recentWorkOrders = [];
    $recentExtracts = [];
    $workOrdersStatusData = json_encode([]);
    $extractsChartData = json_encode([]);
    $branchesChartData = json_encode([]);
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- ترحيب بسيط -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-radius: 15px;">
            <div class="card-body text-white p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">مرحباً، <?php echo htmlspecialchars($user['full_name'] ?? 'مستخدم'); ?>! 👋</h2>
                        <p class="mb-0 opacity-75">مرحباً بك في نظام تِقان لإدارة أعمال المقاولات</p>
                        <small class="opacity-50">آخر تسجيل دخول: <?= date('Y-m-d H:i') ?></small>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-tachometer-alt" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- الإحصائيات -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-primary text-uppercase mb-1">
                            أوامر العمل
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalWorkOrders ?>">
                            0
                        </div>
                        <div class="small text-success">
                            <i class="fas fa-check-circle me-1"></i>
                            <span id="active-work-orders" data-animate-number="<?= $activeWorkOrders ?>">0</span> نشط
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-success text-uppercase mb-1">
                            الفروع
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalBranches ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            مواقع متعددة
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-building fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-warning text-uppercase mb-1">
                            المستخلصات الجزئية
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalPartialExtracts ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-file-alt me-1"></i>
                            مستخلصات جزئية
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-info text-uppercase mb-1">
                            المستخلصات النهائية
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalFinalExtracts ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-file-check me-1"></i>
                            مستخلصات نهائية
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- إحصائيات إضافية -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-danger text-uppercase mb-1">
                            المستخدمين
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalUsers ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-user-check me-1"></i>
                            مستخدمين نشطين
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-secondary border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-secondary text-uppercase mb-1">
                            المواد الكهربائية
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalMaterials ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-boxes me-1"></i>
                            مواد نشطة
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-warehouse fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-success text-uppercase mb-1">
                            أوامر مكتملة
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $completedWorkOrders ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-check-double me-1"></i>
                            مكتملة بنجاح
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-tasks fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-start border-dark border-4 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-dark text-uppercase mb-1">
                            المستخلصات للجزئية
                        </div>
                        <div class="h4 mb-0 fw-bold text-gray-800" data-animate-number="<?= $totalFinalForPartialExtracts ?>">
                            0
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-file-pdf me-1"></i>
                            مستخلصات نهائية
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- الملخص المالي -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line text-success me-2"></i>
                    الملخص المالي
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <h6 class="text-muted mb-2">القيمة المقدرة</h6>
                            <h4 class="text-success fw-bold" data-animate-number="<?= $totalEstimatedValue ?>">
                                0
                            </h4>
                            <small class="text-muted">ريال سعودي</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-center">
                            <h6 class="text-muted mb-2">القيمة الفعلية</h6>
                            <h4 class="text-primary fw-bold" data-animate-number="<?= $totalActualValue ?>">
                                0
                            </h4>
                            <small class="text-muted">ريال سعودي</small>
                        </div>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar"
                         style="width: <?= $totalEstimatedValue > 0 ? ($totalActualValue / $totalEstimatedValue * 100) : 0 ?>%"
                         aria-valuenow="<?= $totalActualValue ?>" aria-valuemin="0" aria-valuemax="<?= $totalEstimatedValue ?>">
                        <?= $totalEstimatedValue > 0 ? round(($totalActualValue / $totalEstimatedValue * 100), 1) : 0 ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history text-info me-2"></i>
                    آخر الأنشطة
                </h5>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($recentWorkOrders)): ?>
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">آخر أوامر عمل</h6>
                        <?php foreach ($recentWorkOrders as $wo): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div>
                                    <small class="fw-bold"><?= htmlspecialchars($wo['work_order_number']) ?></small>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($wo['branch_name'] ?? 'بدون فرع') ?></small>
                                </div>
                                <span class="badge bg-<?= $wo['status'] === 'active' ? 'success' : ($wo['status'] === 'completed' ? 'info' : 'secondary') ?>">
                                    <?= $wo['status'] === 'active' ? 'نشط' : ($wo['status'] === 'completed' ? 'مكتمل' : 'غير نشط') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        لا توجد أوامر عمل حديثة
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- الإجراءات السريعة -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt text-primary me-2"></i>
                    الإجراءات السريعة
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= path('work-orders/index.php') ?>" class="btn btn-outline-primary text-start">
                        <i class="fas fa-clipboard-list me-2"></i>
                        إدارة أوامر العمل
                    </a>
                    <a href="<?= path('extracts/index.php') ?>" class="btn btn-outline-success text-start">
                        <i class="fas fa-file-invoice me-2"></i>
                        إدارة المستخلصات
                    </a>
                    <a href="<?= path('branches/index.php') ?>" class="btn btn-outline-info text-start">
                        <i class="fas fa-building me-2"></i>
                        إدارة الفروع
                    </a>
                    <a href="<?= path('users/index.php') ?>" class="btn btn-outline-warning text-start">
                        <i class="fas fa-users me-2"></i>
                        إدارة المستخدمين
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clock text-info me-2"></i>
                    معلومات النظام
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h6 class="text-muted mb-1">إصدار النظام</h6>
                            <span class="badge bg-primary">v1.0.0</span>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-muted mb-1">التاريخ والوقت</h6>
                        <span class="text-dark"><?= date('Y-m-d H:i') ?></span>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>نظام تِقان</strong> - نظام إدارة المقاولات المتكامل
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- الرسوم البيانية -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie text-primary me-2"></i>
                    توزيع أوامر العمل حسب الحالة
                </h5>
            </div>
            <div class="card-body">
                <canvas id="workOrdersStatusChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-doughnut text-success me-2"></i>
                    توزيع المستخلصات
                </h5>
            </div>
            <div class="card-body">
                <canvas id="extractsChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-12 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar text-info me-2"></i>
                    توزيع أوامر العمل حسب الفرع
                </h5>
            </div>
            <div class="card-body">
                <canvas id="branchesChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/includes/layout.php';
?>
