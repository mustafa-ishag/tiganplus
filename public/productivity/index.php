<?php
/**
 * لوحة تحكم نظام الإنتاجية
 * Productivity System Dashboard
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/ProductivityWorkItem.php';
require_once __DIR__ . '/../../models/ProductivityDailyLog.php';
require_once __DIR__ . '/../../models/ProductivityApproval.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_dashboard_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'لوحة تحكم الإنتاجية';
$currentPage = 'productivity';

// إنشاء كائنات النماذج
$workItemModel = new ProductivityWorkItem();
$dailyLogModel = new ProductivityDailyLog();
$approvalModel = new ProductivityApproval();

// جلب الإحصائيات العامة
$filters = [];
if (!hasPermission('productivity_daily_logs_view_all_branches') && isset($_SESSION['branch_id'])) {
    $filters['branch_id'] = $_SESSION['branch_id'];
}

$overallStats = $workItemModel->getOverallStatistics($filters);
$dailyStats = $dailyLogModel->getDailyStatistics($filters);

// جلب السجلات المعلقة للاعتماد (إذا كان المستخدم معتمد)
$pendingApprovals = [];
if (hasPermission('productivity_approvals_view')) {
    $pendingApprovals = $approvalModel->getPendingApprovals($_SESSION['user_id'], $filters, 10);
}

// جلب أحدث السجلات اليومية
$recentLogs = $dailyLogModel->getAll($filters, 10);

// جلب بنود الإنتاجية النشطة
$activeWorkItems = $workItemModel->getAll(array_merge($filters, ['status' => 'active']), 10);

// بدء تخزين المحتوى
ob_start();
?>
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-line text-primary"></i>
            لوحة تحكم الإنتاجية
        </h1>
        <div class="btn-group" role="group">
            <?php if (hasPermission('productivity_work_items_create')): ?>
                <a href="work-items/create.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> إضافة بند إنتاجية
                </a>
            <?php endif; ?>
            <?php if (hasPermission('productivity_daily_logs_create')): ?>
                <a href="daily-logs/create.php" class="btn btn-success btn-sm">
                    <i class="fas fa-clipboard-list"></i> تسجيل إنتاجية يومية
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="row">
        <!-- إجمالي بنود الإنتاجية -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي بنود الإنتاجية
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($overallStats['total_items'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- البنود النشطة -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                البنود النشطة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($overallStats['active_items'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- البنود المكتملة -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                البنود المكتملة
                            </div>
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                        <?= number_format($overallStats['completed_items'] ?? 0) ?>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="progress progress-sm mr-2">
                                        <?php 
                                        $completionRate = $overallStats['total_items'] > 0 ? 
                                            (($overallStats['completed_items'] ?? 0) / $overallStats['total_items']) * 100 : 0;
                                        ?>
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?= $completionRate ?>%" 
                                             aria-valuenow="<?= $completionRate ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- إجمالي القيمة -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                إجمالي القيمة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($overallStats['total_value'] ?? 0, 2) ?> ريال
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

    <!-- الصف الثاني من الإحصائيات -->
    <div class="row">
        <!-- السجلات اليومية -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                السجلات اليومية
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($dailyStats['total_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- السجلات المعتمدة -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                السجلات المعتمدة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($dailyStats['approved_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- السجلات المعلقة -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                السجلات المعلقة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($dailyStats['submitted_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- متوسط الكفاءة -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                متوسط الكفاءة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($overallStats['avg_efficiency'] ?? 0, 1) ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tachometer-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- السجلات المعلقة للاعتماد -->
        <?php if (hasPermission('productivity_approvals_view') && !empty($pendingApprovals)): ?>
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-hourglass-half"></i>
                        السجلات المعلقة للاعتماد
                    </h6>
                    <a href="approvals/" class="btn btn-primary btn-sm">عرض الكل</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>أمر العمل</th>
                                    <th>بند العمل</th>
                                    <th>التاريخ</th>
                                    <th>الكمية</th>
                                    <th>القيمة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($pendingApprovals, 0, 5) as $approval): ?>
                                <tr>
                                    <td><?= htmlspecialchars($approval['work_order_number']) ?></td>
                                    <td><?= htmlspecialchars(substr($approval['work_item_description'], 0, 30)) ?>...</td>
                                    <td><?= date('Y-m-d', strtotime($approval['log_date'])) ?></td>
                                    <td><?= number_format($approval['quantity_completed'], 2) ?></td>
                                    <td><?= number_format($approval['calculated_value'], 2) ?></td>
                                    <td>
                                        <a href="approvals/view.php?id=<?= $approval['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- أحدث السجلات اليومية -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i>
                        أحدث السجلات اليومية
                    </h6>
                    <a href="daily-logs/" class="btn btn-primary btn-sm">عرض الكل</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>أمر العمل</th>
                                    <th>التاريخ</th>
                                    <th>الكمية</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recentLogs, 0, 5) as $log): ?>
                                <tr>
                                    <td><?= htmlspecialchars($log['work_order_number']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($log['log_date'])) ?></td>
                                    <td><?= number_format($log['quantity_completed'], 2) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'draft' => 'secondary',
                                            'submitted' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'returned' => 'info'
                                        ];
                                        $statusText = [
                                            'draft' => 'مسودة',
                                            'submitted' => 'مرسل',
                                            'approved' => 'معتمد',
                                            'rejected' => 'مرفوض',
                                            'returned' => 'مرجع'
                                        ];
                                        ?>
                                        <span class="badge badge-<?= $statusClass[$log['status']] ?>">
                                            <?= $statusText[$log['status']] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="daily-logs/view.php?id=<?= $log['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- البنود النشطة -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tasks"></i>
                        البنود النشطة
                    </h6>
                    <a href="work-items/" class="btn btn-primary btn-sm">عرض الكل</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>أمر العمل</th>
                                    <th>بند العمل</th>
                                    <th>الكمية المستهدفة</th>
                                    <th>المنجز</th>
                                    <th>نسبة الإنجاز</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeWorkItems as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['work_order_number']) ?></td>
                                    <td><?= htmlspecialchars(substr($item['work_item_description'], 0, 40)) ?>...</td>
                                    <td><?= number_format($item['target_quantity'], 2) ?> <?= htmlspecialchars($item['unit']) ?></td>
                                    <td><?= number_format($item['total_completed'], 2) ?> <?= htmlspecialchars($item['unit']) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= min(100, $item['completion_percentage']) ?>%"
                                                 aria-valuenow="<?= $item['completion_percentage'] ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                <?= number_format($item['completion_percentage'], 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">نشط</span>
                                    </td>
                                    <td>
                                        <a href="work-items/view.php?id=<?= $item['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasPermission('productivity_daily_logs_create')): ?>
                                        <a href="daily-logs/create.php?work_item_id=<?= $item['id'] ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
