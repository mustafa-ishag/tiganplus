<?php
/**
 * صفحة عرض عمليات المواد المزالة
 * Removed Materials Transactions Index
 */

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/RemovedMaterial.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('removed_materials_view') && !hasPermission('inventory_access')) {
    setAlert('ليس لديك صلاحية لعرض المواد المزالة', 'error');
    redirect('/dashboard.php');
}

$pageTitle = 'المواد المزالة';
$currentPage = 'removed-materials';

$removedMaterial = new RemovedMaterial();
$stats = $removedMaterial->getTransactionStats();

// فلترة
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$workOrderId = $_GET['work_order_id'] ?? '';
$search = $_GET['search'] ?? '';

$whereConditions = [];
$params = [];

if ($statusFilter) {
    $whereConditions[] = 'rmt.status = ?';
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $whereConditions[] = 'rmt.transaction_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = 'rmt.transaction_date <= ?';
    $params[] = $dateTo;
}

if ($workOrderId) {
    $whereConditions[] = 'rmt.work_order_id = ?';
    $params[] = $workOrderId;
}

if ($search) {
    $whereConditions[] = '(rmt.transaction_number LIKE ? OR wo.work_order_number LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "SELECT rmt.*,
               wo.work_order_number,
               wo.location,
               wo.department,
               wot.type_code as wo_type,
               u.full_name as created_by_name,
               (SELECT COUNT(*) FROM removed_material_transaction_details rmtd WHERE rmtd.transaction_id = rmt.id) as items_count
        FROM removed_material_transactions rmt
        LEFT JOIN work_orders wo ON rmt.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN users u ON rmt.created_by = u.id
        {$whereClause}
        ORDER BY rmt.created_at DESC";

$transactions = $removedMaterial->fetchAll($sql, $params);

// جلب أوامر العمل للفلترة
$db = getDB();
$workOrders = $db->query("SELECT id, work_order_number FROM work_orders ORDER BY work_order_number")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
    .stat-card {
        border-radius: 12px;
        padding: 1.25rem;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        transition: transform 0.2s;
    }

    .stat-card:hover { transform: translateY(-2px); }

    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .stat-card .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a1a2e;
    }

    .stat-card .stat-label {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .status-badge {
        padding: 0.35rem 0.75rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-pending { background: #fff3cd; color: #856404; }
    .badge-approved { background: #d4edda; color: #155724; }
    .badge-rejected { background: #f8d7da; color: #721c24; }

    .filter-card {
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
    }
</style>

<!-- بطاقات الإحصائيات -->
<div class="row mb-4">
    <div class="col-md-4 col-12 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(13, 110, 253, 0.1); color: #0d6efd;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <div class="stat-value"><?= number_format($stats['total_transactions']) ?></div>
                    <div class="stat-label">إجمالي العمليات</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?= number_format($stats['pending_transactions']) ?></div>
                    <div class="stat-label">في الانتظار</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6 mb-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(25, 135, 84, 0.1); color: #198754;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?= number_format($stats['approved_transactions']) ?></div>
                    <div class="stat-label">معتمدة</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- أزرار الإجراءات -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php if (hasPermission('removed_materials_create') || hasPermission('inventory_access')): ?>
        <a href="<?= path('inventory/removed-materials/create.php') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> تسجيل مواد مزالة
        </a>
    <?php endif; ?>
    <a href="<?= path('inventory/removed-materials/analysis.php') ?>" class="btn btn-outline-info">
        <i class="fas fa-chart-bar me-1"></i> تحليل المواد المزالة
    </a>
</div>

<!-- الفلاتر -->
<div class="filter-card">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">أمر العمل</label>
            <select name="work_order_id" class="form-select form-select-sm">
                <option value="">الكل</option>
                <?php foreach ($workOrders as $wo): ?>
                    <option value="<?= $wo['id'] ?>" <?= $workOrderId == $wo['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wo['work_order_number']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">الحالة</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">الكل</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>في الانتظار</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>معتمد</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">بحث</label>
            <input type="text" name="search" class="form-control form-control-sm"
                value="<?= htmlspecialchars($search) ?>" placeholder="رقم أمر العمل...">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-secondary btn-sm w-100">
                <i class="fas fa-search me-1"></i> تصفية
            </button>
        </div>
        <div class="col-md-2">
            <a href="<?= path('inventory/removed-materials/index.php') ?>" class="btn btn-outline-secondary btn-sm w-100">
                إلغاء الفلتر
            </a>
        </div>
    </form>
</div>

<!-- جدول العمليات -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>أمر العمل</th>
                        <th>النوع</th>
                        <th>القسم</th>
                        <th>الموقع</th>
                        <th>عدد المواد</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                        <th>بواسطة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                <h5>لا توجد سجلات مواد مزالة</h5>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td>
                                    <a href="<?= path('inventory/removed-materials/view.php?id=' . $t['id']) ?>"
                                        class="fw-bold text-decoration-none">
                                        <?= htmlspecialchars($t['work_order_number'] ?? '-') ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($t['wo_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['department'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['location'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill px-3 py-2">
                                        <?= $t['items_count'] ?> مواد
                                    </span>
                                </td>
                                <td>
                                    <?= date('Y/m/d', strtotime($t['transaction_date'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'pending' => ['في الانتظار', 'badge-pending'],
                                        'approved' => ['معتمد', 'badge-approved'],
                                        'rejected' => ['مرفوض', 'badge-rejected'],
                                    ];
                                    $s = $statusLabels[$t['status']] ?? ['غير معروف', 'badge-secondary'];
                                    ?>
                                    <span class="status-badge <?= $s[1] ?>">
                                        <?= $s[0] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($t['created_by_name'] ?? '-') ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= path('inventory/removed-materials/view.php?id=' . $t['id']) ?>"
                                            class="btn btn-outline-primary" title="عرض التفاصيل والتصدير">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                        <?php if ($t['status'] === 'pending' && (hasPermission('removed_materials_create') || hasPermission('inventory_access'))): ?>
                                            <a href="<?= path('inventory/removed-materials/create.php?edit=' . $t['id']) ?>"
                                                class="btn btn-outline-warning" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../includes/layout.php';
?>