<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * الصفحة الرئيسية لنظام إدارة المخزون
 * Inventory Management Dashboard
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Material.php';

require_once __DIR__ . '/../../models/InventoryTransaction.php';
require_once __DIR__ . '/../../models/MaterialRequest.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_access')) {
    setAlert('ليس لديك صلاحية لعرض نظام المخزون', 'error');
    redirect('/dashboard.php');
}

$materialModel = new Material();

$transactionModel = new InventoryTransaction();
$materialRequestModel = new MaterialRequest();

// الحصول على الإحصائيات
$materialStats = $materialModel->getMaterialStats();

$transactionStats = $transactionModel->getTransactionStats();
$requestStats = $materialRequestModel->getMaterialRequestStats($_SESSION['user_branch_id'] ?? null);

// الحصول على المواد منخفضة المخزون
$lowStockMaterials = $materialModel->fetchAll(
    "SELECT m.*, mc.description, mc.unit FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE m.current_stock <= m.minimum_stock AND m.is_active = 1 
     ORDER BY (m.current_stock / m.minimum_stock) ASC 
     LIMIT 10"
);

// إحصائيات إضافية
$todayTransactions = $transactionModel->fetchColumn(
    "SELECT COUNT(*) FROM inventory_transactions WHERE DATE(created_at) = CURDATE()"
) ?: 0;

$activeLoansCount = $materialModel->fetchColumn(
    "SELECT COUNT(*) FROM inventory_loans WHERE status = 'active'"
) ?: 0;

// أكثر 5 مواد صرفاً (آخر 30 يوم)
$topConsumedMaterials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description, mc.unit, SUM(td.quantity) as total_consumed
     FROM transaction_details td
     JOIN inventory_transactions it ON td.transaction_id = it.id
     JOIN materials m ON td.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE it.transaction_type = 'outgoing' AND it.status = 'approved'
       AND it.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY m.id ORDER BY total_consumed DESC LIMIT 5"
);

// حركة المخزون آخر 7 أيام (للرسم البياني)
$stockMovement = $materialModel->fetchAll(
    "SELECT DATE(it.transaction_date) as day,
            SUM(CASE WHEN it.transaction_type IN ('incoming','return','initial_balance','loan_in') THEN td.quantity ELSE 0 END) as total_in,
            SUM(CASE WHEN it.transaction_type IN ('outgoing','loan_out') THEN td.quantity ELSE 0 END) as total_out
     FROM inventory_transactions it
     JOIN transaction_details td ON it.id = td.transaction_id
     WHERE it.status = 'approved' AND it.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(it.transaction_date) ORDER BY day ASC"
);

// الحصول على آخر المعاملات
$recentTransactions = $transactionModel->fetchAll(
    "SELECT it.*, COUNT(itd.id) as item_count
     FROM inventory_transactions it
     LEFT JOIN transaction_details itd ON it.id = itd.transaction_id
     GROUP BY it.id
     ORDER BY it.created_at DESC
     LIMIT 5"
);

// الحصول على آخر طلبات الصرف
$recentRequests = $materialRequestModel->fetchAll(
    "SELECT mr.*, wo.work_order_number, u.full_name as requested_by_name
     FROM material_requests mr
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     LEFT JOIN users u ON mr.requested_by = u.id
     ORDER BY mr.created_at DESC
     LIMIT 5"
);

$pageTitle = 'لوحة تحكم المخزون';
$currentPage = 'inventory';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-warehouse text-primary me-2"></i>
                لوحة تحكم نظام إدارة المخزون
            </h2>
            <p class="text-muted mb-0">نظرة شاملة على حالة المخزون والمواد</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="materials/index.php" class="btn btn-primary">
                    <i class="fas fa-boxes me-1"></i>
                    إدارة المواد
                </a>
            </div>
        </div>
    </div>

    <!-- الإحصائيات الرئيسية -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">المواد النشطة</h6>
                            <h3 class="mb-0"><?= number_format($materialStats['active_materials']) ?></h3>
                            <small class="opacity-75">من أصل <?= number_format($materialStats['total_materials']) ?>
                                مادة</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-boxes fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-primary border-0">
                    <a href="materials/index.php" class="text-white text-decoration-none">
                        <small>عرض جميع المواد <i class="fas fa-arrow-left ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">مخزون منخفض</h6>
                            <h3 class="mb-0"><?= number_format($materialStats['low_stock_materials']) ?></h3>
                            <small class="opacity-75">مادة تحتاج تجديد</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-warning border-0">
                    <a href="materials/index.php?status=low_stock" class="text-white text-decoration-none">
                        <small>عرض المواد <i class="fas fa-arrow-left ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">نفد المخزون</h6>
                            <h3 class="mb-0"><?= number_format($materialStats['out_of_stock_materials']) ?></h3>
                            <small class="opacity-75">مادة غير متوفرة</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-danger border-0">
                    <a href="materials/index.php?status=out_of_stock" class="text-white text-decoration-none">
                        <small>عرض المواد <i class="fas fa-arrow-left ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- بطاقات إضافية -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">معاملات اليوم</h6>
                            <h3 class="mb-0"><?= number_format($todayTransactions) ?></h3>
                            <small class="opacity-75">معاملة مخزنية</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-info border-0">
                    <a href="transactions/index.php" class="text-white text-decoration-none">
                        <small>عرض المعاملات <i class="fas fa-arrow-left ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">طلبات صرف معلقة</h6>
                            <h3 class="mb-0"><?= number_format($requestStats['pending_requests'] ?? 0) ?></h3>
                            <small class="opacity-75">تنتظر الموافقة</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clipboard-list fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-secondary border-0">
                    <a href="material-requests/index.php" class="text-white text-decoration-none">
                        <small>عرض الطلبات <i class="fas fa-arrow-left ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">سلف نشطة</h6>
                            <h3 class="mb-0"><?= number_format($activeLoansCount) ?></h3>
                            <small class="opacity-75">لم تتم مخالصتها</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-handshake fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-dark border-0">
                    <a href="loans/index.php" class="text-white text-decoration-none">
                        <small>عرض السلف <i class="fas fa-arrow-left ms-1"></i></small>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- رسم بياني: حركة المخزون -->
    <?php if (!empty($stockMovement)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area text-success me-1"></i>
                            حركة المخزون (آخر 7 أيام)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="stockMovementChart" height="80"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- المواد منخفضة المخزون -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                        المواد منخفضة المخزون
                    </h5>
                    <a href="materials/index.php?status=low_stock" class="btn btn-sm btn-outline-warning">
                        عرض الكل
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($lowStockMaterials)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted mb-0">جميع المواد في مستوى آمن</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>رقم البند</th>
                                        <th>الوصف</th>
                                        <th>المخزون</th>
                                        <th>الحد الأدنى</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockMaterials as $material): ?>
                                        <tr>
                                            <td>
                                                <a href="materials/view.php?id=<?= $material['id'] ?>"
                                                    class="text-decoration-none">
                                                    <?= htmlspecialchars($material['item_number']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 150px;"
                                                    title="<?= htmlspecialchars($material['description'] ?? '') ?>">
                                                    <?= htmlspecialchars($material['description'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="<?= $material['current_stock'] == 0 ? 'text-danger' : 'text-warning' ?>">
                                                    <?= formatNumber($material['current_stock'], 3) ?>
                                                </span>
                                            </td>
                                            <td><?= formatNumber($material['minimum_stock'], 3) ?></td>
                                            <td>
                                                <?php if ($material['current_stock'] == 0): ?>
                                                    <span class="badge bg-danger">نفد</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">منخفض</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-fire text-danger me-1"></i>
                        أكثر المواد صرفاً (30 يوم)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($topConsumedMaterials)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">لا توجد حركة صرف</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>رقم البند</th>
                                        <th>الوصف</th>
                                        <th>الكمية المصروفة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topConsumedMaterials as $mat): ?>
                                        <tr>
                                            <td><a href="materials/view.php?id=<?= $mat['id'] ?>"
                                                    class="text-decoration-none"><?= htmlspecialchars($mat['item_number']) ?></a>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width:150px"
                                                    title="<?= htmlspecialchars($mat['description']) ?>">
                                                    <?= htmlspecialchars($mat['description']) ?></div>
                                            </td>
                                            <td><span class="badge bg-danger"><?= number_format($mat['total_consumed']) ?>
                                                    <?= htmlspecialchars($mat['unit']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- آخر المعاملات -->
    <?php if (!empty($recentTransactions)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-info me-1"></i>
                            آخر المعاملات
                        </h5>
                        <a href="transactions/index.php" class="btn btn-sm btn-outline-info">
                            عرض جميع المعاملات
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>رقم المعاملة</th>
                                        <th>النوع</th>
                                        <th>التاريخ</th>
                                        <th>عدد البنود</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <span
                                                    class="font-monospace"><?= htmlspecialchars($transaction['transaction_number']) ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $typeLabels = [
                                                    'incoming' => ['وارد', 'success'],
                                                    'outgoing' => ['صادر', 'danger'],
                                                    'transfer' => ['تحويل', 'info'],
                                                    'return' => ['مرتجع', 'warning'],
                                                    'initial_balance' => ['رصيد افتتاحي', 'primary'],
                                                    'loan_out' => ['سلفة صادرة', 'dark'],
                                                    'loan_in' => ['سلفة واردة', 'secondary'],
                                                    'loan_return' => ['إرجاع سلفة', 'light'],
                                                    'stocktake_adjustment' => ['تسوية جرد', 'dark']
                                                ];
                                                $typeInfo = $typeLabels[$transaction['transaction_type']] ?? ['غير معروف', 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $typeInfo[1] ?>"><?= $typeInfo[0] ?></span>
                                            </td>
                                            <td><?= formatDate($transaction['transaction_date']) ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-secondary"><?= number_format($transaction['item_count']) ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusLabels = [
                                                    'pending' => ['معلق', 'warning'],
                                                    'approved' => ['معتمد', 'success'],
                                                    'rejected' => ['مرفوض', 'danger']
                                                ];
                                                $statusInfo = $statusLabels[$transaction['status']] ?? ['غير معروف', 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span>
                                            </td>
                                            <td>
                                                <a href="transactions/view.php?number=<?= urlencode($transaction['transaction_number']) ?>"
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
    <?php endif; ?>

    <!-- روابط سريعة -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-link text-primary me-1"></i>
                        روابط سريعة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="materials/index.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-boxes d-block mb-2"></i>
                                إدارة المواد
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="transactions/index.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-exchange-alt d-block mb-2"></i>
                                معاملات المخزون
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="material-requests/index.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-clipboard-list d-block mb-2"></i>
                                طلبات الصرف
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="reports/index.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-chart-bar d-block mb-2"></i>
                                التقارير
                            </a>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
    <?php if (!empty($stockMovement)): ?>
        // رسم بياني لحركة المخزون
        const ctx = document.getElementById('stockMovementChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_map(fn($r) => date('m/d', strtotime($r['day'])), $stockMovement)) ?>,
                    datasets: [
                        {
                            label: 'وارد',
                            data: <?= json_encode(array_map(fn($r) => (float) $r['total_in'], $stockMovement)) ?>,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25,135,84,0.1)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'صادر',
                            data: <?= json_encode(array_map(fn($r) => (float) $r['total_out'], $stockMovement)) ?>,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220,53,69,0.1)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'الكمية' } }
                    }
                }
            });
        }
    <?php endif; ?>
</script>