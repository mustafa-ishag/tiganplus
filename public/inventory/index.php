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

<!-- Page Header - نفس نمط أوامر العمل تماماً -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">لوحة تحكم المخزون</h4>
        <p class="text-muted mb-0 small">نظرة تحليلية شاملة لحركة ومستويات المخزون</p>
    </div>
    <div>
        <a href="transactions/create.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-plus me-2"></i> معاملة جديدة
        </a>
        <a href="materials/index.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-primary fw-bold ms-2">
            <i class="fas fa-boxes me-2"></i> دليل المواد
        </a>
    </div>
</div>

<!-- Statistics Cards - نفس نمط أوامر العمل بالضبط: dash-card h-100 p-3 -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">المواد النشطة بالشبكة</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($materialStats['active_materials']) ?></div>
                    <div class="text-muted" style="font-size: 0.65rem;">إجمالي <?= number_format($materialStats['total_materials']) ?> مادة</div>
                </div>
                <div class="icon-circle bg-primary-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-cubes text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">تحت الحد الأدنى</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($materialStats['low_stock_materials']) ?></div>
                </div>
                <div class="icon-circle bg-warning-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">مخزون نافد</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($materialStats['out_of_stock_materials']) ?></div>
                </div>
                <div class="icon-circle bg-danger-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-times-circle text-danger"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">حركة اليوم</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($todayTransactions) ?></div>
                </div>
                <div class="icon-circle bg-success-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-bolt text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">طلبات صرف معلقة</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($requestStats['pending_requests'] ?? 0) ?></div>
                </div>
                <div class="icon-circle bg-info-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-clipboard-list text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="dash-card h-100 p-3">
            <div class="d-flex justify-content-between align-items-center h-100">
                <div class="me-2">
                    <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">سلف غير مخالصة</div>
                    <div class="h5 mb-0 fw-bold text-dark"><?= number_format($activeLoansCount) ?></div>
                </div>
                <div class="icon-circle bg-secondary-soft" style="width: 35px; height: 35px; font-size: 1rem; flex-shrink: 0;">
                    <i class="fas fa-handshake text-secondary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- قسم الرسم البياني + التنبيهات -->
<div class="row g-4 mb-4">
    <!-- الرسم البياني -->
    <div class="col-lg-8">
        <?php if (!empty($stockMovement)): ?>
        <div class="dash-card h-100">
            <div class="card-header bg-transparent border-0 p-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-chart-area text-primary me-2"></i>تدفق المخزون</h6>
                    <p class="text-muted mb-0 small">تحليل الوارد والصادر لآخر 7 أيام</p>
                </div>
            </div>
            <div class="card-body px-4 pb-4 pt-2">
                <div style="height: 280px;">
                    <canvas id="stockMovementChart"></canvas>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="dash-card h-100 d-flex align-items-center justify-content-center" style="min-height: 350px;">
            <div class="text-center">
                <div class="icon-circle bg-primary-soft mx-auto mb-3" style="width: 55px; height: 55px;">
                    <i class="fas fa-chart-line text-primary"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">لا توجد بيانات حركة</h6>
                <p class="text-muted mb-0 small">لم يتم تسجيل حركات مخزون خلال آخر 7 أيام</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- المواد منخفضة المخزون -->
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="card-header bg-transparent border-0 p-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-exclamation-triangle text-warning me-2"></i>تنبيهات المخزون</h6>
                    <p class="text-muted mb-0 small">مواد وصلت أو تجاوزت حد إعادة الطلب</p>
                </div>
                <a href="materials/index.php?status=low_stock" class="btn btn-sm btn-light rounded-pill px-3 fw-bold text-muted border-0">
                    الكل <i class="fas fa-arrow-left ms-1"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockMaterials)): ?>
                    <div class="text-center py-5">
                        <div class="icon-circle bg-success-soft mx-auto mb-3">
                            <i class="fas fa-shield-alt text-success"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">المخزون آمن</h6>
                        <p class="text-muted mb-0 small">جميع المستويات مستقرة</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                            <thead style="background: #f8f9fc;">
                                <tr>
                                    <th class="ps-4 border-0 text-muted fw-bold" style="font-size: 0.7rem;">رقم البند</th>
                                    <th class="border-0 text-muted fw-bold" style="font-size: 0.7rem;">المخزون الحالي</th>
                                    <th class="pe-4 border-0 text-muted fw-bold text-center" style="font-size: 0.7rem;">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockMaterials as $material): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="materials/view.php?id=<?= $material['id'] ?>"
                                                class="text-decoration-none fw-bold text-primary" style="font-size: 0.8rem;">
                                                <?= htmlspecialchars($material['item_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?= $material['current_stock'] == 0 ? 'text-danger' : 'text-warning' ?>">
                                                <?= formatNumber($material['current_stock'], 3) ?>
                                            </span>
                                            <small class="text-muted d-block" style="font-size: 0.65rem;">الحد: <?= formatNumber($material['minimum_stock'], 3) ?></small>
                                        </td>
                                        <td class="pe-4 text-center">
                                            <?php if ($material['current_stock'] == 0): ?>
                                                <span class="badge bg-danger-soft text-danger rounded-pill px-2" style="font-size: 0.7rem;">نافد</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-soft text-warning rounded-pill px-2" style="font-size: 0.7rem;">منخفض</span>
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
</div>

<!-- قسم آخر المعاملات + الأكثر استهلاكاً -->
<div class="row g-4 mb-4">
    <!-- آخر المعاملات (الجدول الأعرض على اليسار) -->
    <div class="col-lg-8">
        <div class="dash-card h-100">
            <div class="card-header bg-transparent border-0 p-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-history text-info me-2"></i>سجل الحركات الأحدث</h6>
                    <p class="text-muted mb-0 small">آخر المعاملات المسجلة في النظام</p>
                </div>
                <a href="transactions/index.php" class="btn btn-sm btn-light rounded-pill px-3 fw-bold text-info border-0">
                    عرض السجل الكامل
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentTransactions)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                        <thead style="background: #f8f9fc;">
                            <tr>
                                <th class="ps-4 border-0 text-muted fw-bold" style="font-size: 0.7rem;">رقم المعاملة</th>
                                <th class="border-0 text-muted fw-bold" style="font-size: 0.7rem;">النوع</th>
                                <th class="border-0 text-muted fw-bold" style="font-size: 0.7rem;">التاريخ</th>
                                <th class="border-0 text-muted fw-bold text-center" style="font-size: 0.7rem;">عدد البنود</th>
                                <th class="border-0 text-muted fw-bold text-center" style="font-size: 0.7rem;">الحالة</th>
                                <th class="pe-4 border-0 text-muted fw-bold text-center" style="font-size: 0.7rem;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <tr>
                                    <td class="ps-4">
                                        <a href="transactions/view.php?number=<?= urlencode($transaction['transaction_number']) ?>" class="fw-bold text-primary text-decoration-none" style="font-size: 0.8rem;">
                                            <?= htmlspecialchars($transaction['transaction_number']) ?>
                                        </a>
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
                                            'loan_return' => ['إرجاع سلفة', 'secondary'],
                                            'stocktake_adjustment' => ['تسوية جرد', 'dark']
                                        ];
                                        $typeInfo = $typeLabels[$transaction['transaction_type']] ?? ['غير معروف', 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $typeInfo[1] ?>-soft text-<?= $typeInfo[1] ?> rounded-pill px-2" style="font-size: 0.7rem;"><?= $typeInfo[0] ?></span>
                                    </td>
                                    <td><span class="text-muted"><?= formatDate($transaction['transaction_date']) ?></span></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-soft text-secondary rounded-pill px-2" style="font-size: 0.7rem;"><?= number_format($transaction['item_count']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $statusLabels = [
                                            'pending' => ['معلق', 'warning'],
                                            'approved' => ['معتمد', 'success'],
                                            'rejected' => ['مرفوض', 'danger']
                                        ];
                                        $statusInfo = $statusLabels[$transaction['status']] ?? ['غير معروف', 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $statusInfo[1] ?>-soft text-<?= $statusInfo[1] ?> rounded-pill px-2" style="font-size: 0.7rem;"><?= $statusInfo[0] ?></span>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <a href="transactions/view.php?number=<?= urlencode($transaction['transaction_number']) ?>"
                                            class="btn btn-sm btn-light rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-eye text-primary" style="font-size: 0.75rem;"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="icon-circle bg-info-soft mx-auto mb-3">
                            <i class="fas fa-history text-info"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">لا توجد معاملات</h6>
                        <p class="text-muted mb-0 small">لم يتم تسجيل أي معاملات بعد</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- الأكثر استهلاكاً (على اليمين) -->
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <div class="card-header bg-transparent border-0 p-4 pb-2">
                <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-fire-alt text-danger me-2"></i>الأكثر استهلاكاً</h6>
                <p class="text-muted mb-0 small">المواد الأكثر صرفاً خلال 30 يوماً</p>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topConsumedMaterials)): ?>
                    <div class="text-center py-5">
                        <div class="icon-circle bg-secondary-soft mx-auto mb-3">
                            <i class="fas fa-inbox text-secondary"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">لا توجد بيانات</h6>
                        <p class="text-muted mb-0 small">لم يتم صرف مواد خلال 30 يوماً</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($topConsumedMaterials as $index => $mat): ?>
                        <div class="d-flex justify-content-between align-items-center px-4 py-3 <?= $index < count($topConsumedMaterials) - 1 ? 'border-bottom' : '' ?>">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-circle bg-danger-soft" style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0;">
                                    <i class="fas fa-box-open text-danger"></i>
                                </div>
                                <div>
                                    <a href="materials/view.php?id=<?= $mat['id'] ?>" class="text-decoration-none fw-bold text-dark d-block" style="font-size: 0.85rem;">
                                        <?= htmlspecialchars($mat['item_number']) ?>
                                    </a>
                                    <small class="text-muted text-truncate d-block" style="max-width: 140px; font-size: 0.7rem;"><?= htmlspecialchars($mat['description']) ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-danger" style="font-size: 1rem;"><?= number_format($mat['total_consumed']) ?></span>
                                <small class="text-muted d-block" style="font-size: 0.65rem;"><?= htmlspecialchars($mat['unit']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- روابط سريعة - نفس نمط quick-action-card من layout.php -->
<div class="row g-4">
    <div class="col-12">
        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-th-large text-primary me-2"></i>الوصول السريع</h6>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="materials/index.php" class="quick-action-card h-100">
            <div class="action-icon"><i class="fas fa-boxes"></i></div>
            <div class="quick-action-title">دليل المواد</div>
            <div class="quick-action-desc">تصفح وإدارة كل الأصناف</div>
        </a>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="transactions/index.php" class="quick-action-card h-100">
            <div class="action-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="quick-action-title">حركة المخزون</div>
            <div class="quick-action-desc">عرض الوارد والمنصرف</div>
        </a>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="material-requests/index.php" class="quick-action-card h-100">
            <div class="action-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="quick-action-title">طلبات الصرف</div>
            <div class="quick-action-desc">إدارة طلبات الفنيين</div>
        </a>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="reports/index.php" class="quick-action-card h-100">
            <div class="action-icon"><i class="fas fa-chart-pie"></i></div>
            <div class="quick-action-title">التقارير</div>
            <div class="quick-action-desc">إحصائيات وتقارير جرد</div>
        </a>
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
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        },
                        {
                            label: 'صادر',
                            data: <?= json_encode(array_map(fn($r) => (float) $r['total_out'], $stockMovement)) ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'الكمية' } }
                    }
                }
            });
        }
    <?php endif; ?>
</script>