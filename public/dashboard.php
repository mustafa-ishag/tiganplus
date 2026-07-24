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

<style>
/* Premium Dashboard Styles */
.dash-card {
    border: none;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    overflow: hidden;
}
.dash-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}
.icon-circle {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.bg-primary-soft { background-color: rgba(67, 56, 202, 0.1); color: #4338ca; }
.bg-success-soft { background-color: rgba(34, 197, 94, 0.1); color: #22c55e; }
.bg-warning-soft { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.bg-info-soft { background-color: rgba(6, 182, 212, 0.1); color: #06b6d4; }
.bg-danger-soft { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
.bg-secondary-soft { background-color: rgba(107, 114, 128, 0.1); color: #6b7280; }
.bg-dark-soft { background-color: rgba(31, 41, 55, 0.1); color: #1f2937; }

/* Welcome Banner */
.welcome-banner {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border-radius: 24px;
    position: relative;
    overflow: hidden;
    color: white;
    padding: 2.5rem;
    box-shadow: 0 10px 30px rgba(67, 56, 202, 0.2);
}
.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
}
.welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
}
.welcome-title { font-weight: 800; font-size: 2rem; position: relative; z-index: 1; }
.welcome-subtitle { font-size: 1.1rem; opacity: 0.9; position: relative; z-index: 1; }

/* Timeline */
.timeline {
    position: relative;
    padding-right: 1.5rem;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    height: 100%;
    width: 2px;
    background: #e5e7eb;
}
.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}
.timeline-dot {
    position: absolute;
    right: -1.5rem;
    top: 0.25rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transform: translateX(50%);
    background: var(--primary-color);
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px var(--primary-color);
}
.timeline-content { padding-right: 1.5rem; }

/* Quick Actions Grid */
.quick-action-card {
    border: 1px solid #f3f4f6;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s;
    background: #fff;
    display: block;
    text-decoration: none;
    color: #4b5563;
}
.quick-action-card:hover {
    background: var(--primary-color);
    color: #fff;
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(67, 56, 202, 0.2);
    border-color: var(--primary-color);
}
.quick-action-card .action-icon {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
    transition: color 0.3s;
}
.quick-action-card:hover .action-icon { color: #fff; }
.quick-action-title { font-weight: 600; font-size: 1rem; }
</style>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="welcome-title mb-2">مرحباً بك مجدداً، <?php echo htmlspecialchars($user['full_name'] ?? 'مستخدم'); ?></h2>
                    <p class="welcome-subtitle mb-0">نظام تِقان يوفر لك تجربة استثنائية لإدارة أعمالك بفعالية واحترافية.</p>
                </div>
                <div class="col-md-4 text-center d-none d-md-block">
                    <i class="fas fa-chart-line" style="font-size: 4rem; opacity: 0.15; position: relative; z-index: 1;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards Row 1 -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">أوامر العمل</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalWorkOrders ?>">0</div>
                </div>
                <div class="icon-circle bg-primary-soft">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
            <div class="mt-3 text-sm">
                <span class="text-success fw-bold"><i class="fas fa-arrow-up me-1"></i><?= $activeWorkOrders ?></span>
                <span class="text-muted">نشط حالياً</span>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">الفروع</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalBranches ?>">0</div>
                </div>
                <div class="icon-circle bg-success-soft">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-map-marker-alt text-success me-1"></i> تغطية للمشاريع
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">المستخلصات الجزئية</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalPartialExtracts ?>">0</div>
                </div>
                <div class="icon-circle bg-warning-soft">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-clock text-warning me-1"></i> قيد الإنجاز
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">المستخلصات النهائية</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalFinalExtracts ?>">0</div>
                </div>
                <div class="icon-circle bg-info-soft">
                    <i class="fas fa-file-check"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-check-circle text-info me-1"></i> منتهية وموثقة
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards Row 2 -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">المستخدمين</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalUsers ?>">0</div>
                </div>
                <div class="icon-circle bg-danger-soft">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-user-check text-danger me-1"></i> مستخدمين نشطين
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">المواد الكهربائية</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalMaterials ?>">0</div>
                </div>
                <div class="icon-circle bg-secondary-soft">
                    <i class="fas fa-box-open"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-warehouse text-secondary me-1"></i> متوفرة في المخزون
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">أوامر مكتملة</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $completedWorkOrders ?>">0</div>
                </div>
                <div class="icon-circle bg-success-soft">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-chart-line text-success me-1"></i> تم التنفيذ
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="dash-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold mb-1">مستخلصات للجزئية</div>
                    <div class="h3 mb-0 fw-bold text-dark" data-animate-number="<?= $totalFinalForPartialExtracts ?>">0</div>
                </div>
                <div class="icon-circle bg-dark-soft">
                    <i class="fas fa-file-pdf"></i>
                </div>
            </div>
            <div class="mt-3 text-sm text-muted">
                <i class="fas fa-file-signature text-dark me-1"></i> مسجلة ومعتمدة
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary & Recent Activities -->
<div class="row mb-4">
    <!-- الملخص المالي -->
    <div class="col-lg-6 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">الملخص المالي للمشاريع</h5>
            <div class="row mb-4">
                <div class="col-6">
                    <div class="p-3 bg-light rounded-4 text-center">
                        <div class="text-muted mb-2 small">القيمة المقدرة</div>
                        <div class="h4 fw-bold text-success" data-animate-number="<?= $totalEstimatedValue ?>">0</div>
                        <small class="text-muted">ر.س</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-3 bg-light rounded-4 text-center">
                        <div class="text-muted mb-2 small">القيمة الفعلية</div>
                        <div class="h4 fw-bold text-primary" data-animate-number="<?= $totalActualValue ?>">0</div>
                        <small class="text-muted">ر.س</small>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted small fw-bold">نسبة التنفيذ المالي</span>
                <span class="text-success small fw-bold"><?= $totalEstimatedValue > 0 ? round(($totalActualValue / $totalEstimatedValue * 100), 1) : 0 ?>%</span>
            </div>
            <div class="progress rounded-pill" style="height: 10px; background: #e5e7eb;">
                <div class="progress-bar bg-primary rounded-pill" role="progressbar"
                     style="width: <?= $totalEstimatedValue > 0 ? ($totalActualValue / $totalEstimatedValue * 100) : 0 ?>%">
                </div>
            </div>
        </div>
    </div>

    <!-- آخر الأنشطة -->
    <div class="col-lg-6 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">أحدث أوامر العمل</h5>
            <div class="timeline ps-3" style="max-height: 250px; overflow-y: auto;">
                <?php if (!empty($recentWorkOrders)): ?>
                    <?php foreach ($recentWorkOrders as $wo): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot <?= $wo['status'] === 'active' ? 'bg-success' : ($wo['status'] === 'completed' ? 'bg-info' : 'bg-secondary') ?>" 
                                 style="box-shadow: 0 0 0 2px <?= $wo['status'] === 'active' ? '#22c55e' : ($wo['status'] === 'completed' ? '#06b6d4' : '#6b7280') ?>;"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($wo['work_order_number']) ?></span>
                                    <span class="badge rounded-pill bg-<?= $wo['status'] === 'active' ? 'success' : ($wo['status'] === 'completed' ? 'info' : 'secondary') ?> bg-opacity-10 text-<?= $wo['status'] === 'active' ? 'success' : ($wo['status'] === 'completed' ? 'info' : 'secondary') ?>">
                                        <?= $wo['status'] === 'active' ? 'نشط' : ($wo['status'] === 'completed' ? 'مكتمل' : 'غير نشط') ?>
                                    </span>
                                </div>
                                <div class="text-muted small"><?= htmlspecialchars($wo['branch_name'] ?? 'بدون فرع') ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;"><i class="far fa-clock me-1"></i><?= date('Y-m-d H:i', strtotime($wo['created_at'] ?? 'now')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted p-3">
                        <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                        <p>لا توجد أوامر عمل حديثة</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & System Info -->
<div class="row mb-4">
    <!-- الإجراءات السريعة -->
    <div class="col-lg-6 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">الوصول السريع</h5>
            <div class="row g-3">
                <div class="col-6">
                    <a href="<?= path('work-orders/index.php') ?>" class="quick-action-card">
                        <i class="fas fa-clipboard-list action-icon"></i>
                        <div class="quick-action-title">أوامر العمل</div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="<?= path('extracts/index.php') ?>" class="quick-action-card">
                        <i class="fas fa-file-invoice action-icon"></i>
                        <div class="quick-action-title">المستخلصات</div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="<?= path('branches/index.php') ?>" class="quick-action-card">
                        <i class="fas fa-building action-icon"></i>
                        <div class="quick-action-title">الفروع</div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="<?= path('users/index.php') ?>" class="quick-action-card">
                        <i class="fas fa-users action-icon"></i>
                        <div class="quick-action-title">المستخدمين</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- معلومات النظام -->
    <div class="col-lg-6 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">حالة النظام</h5>
            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-4 mb-3">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-primary-soft me-3" style="width: 45px; height: 45px;">
                        <i class="fas fa-server"></i>
                    </div>
                    <div>
                        <div class="fw-bold">إصدار النظام</div>
                        <div class="text-muted small">نظام إدارة متكامل</div>
                    </div>
                </div>
                <span class="badge bg-primary rounded-pill px-3 py-2">v1.0.0</span>
            </div>
            
            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-4">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-success-soft me-3" style="width: 45px; height: 45px;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="fw-bold">وقت الخادم</div>
                        <div class="text-muted small">توقيت متزامن</div>
                    </div>
                </div>
                <span class="fw-bold text-dark"><?= date('H:i A') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">توزيع أوامر العمل حسب الحالة</h5>
            <canvas id="workOrdersStatusChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">توزيع المستخلصات</h5>
            <canvas id="extractsChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row mb-4">
    <div class="col-lg-12 mb-4">
        <div class="dash-card h-100 p-4">
            <h5 class="fw-bold mb-4">توزيع أوامر العمل حسب الفرع</h5>
            <canvas id="branchesChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/includes/layout.php';
?>
