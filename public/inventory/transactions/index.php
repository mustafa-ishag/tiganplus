<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة قائمة معاملات المخزون
 * Inventory Transactions List Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryTransaction.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_transactions_view')) {
    setAlert('ليس لديك صلاحية لعرض معاملات المخزون', 'error');
    redirect('/dashboard.php');
}

$transactionModel = new InventoryTransaction();
$materialModel = new Material();

// معالجة البحث والتصفية
$search = $_GET['search'] ?? '';
$transactionType = $_GET['transaction_type'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$materialId = $_GET['material_id'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'transaction_date';
$sortOrder = $_GET['sort_order'] ?? 'DESC';
$page = (int) ($_GET['page'] ?? 1);
$perPage = 20;

// بناء شروط البحث
$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(it.transaction_number LIKE ? OR it.notes LIKE ?)';
    $searchPattern = "%{$search}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($transactionType)) {
    $whereConditions[] = 'it.transaction_type = ?';
    $params[] = $transactionType;
}

if (!empty($status)) {
    $whereConditions[] = 'it.status = ?';
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'it.transaction_date >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'it.transaction_date <= ?';
    $params[] = $dateTo;
}

if (!empty($materialId)) {
    $whereConditions[] = 'EXISTS (SELECT 1 FROM transaction_details td WHERE td.transaction_id = it.id AND td.material_id = ?)';
    $params[] = $materialId;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// حساب عدد المعاملات للعنوان
$totalTransactions = $transactionModel->fetchColumn(
    "SELECT COUNT(*) FROM inventory_transactions it {$whereClause}",
    $params
) ?: 0;

// الحصول على إحصائيات المعاملات
$stats = $transactionModel->getTransactionStats();

// الحصول على قائمة المواد للتصفية
$materials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.is_active = 1 ORDER BY m.item_number"
);

$pageTitle = 'معاملات المخزون';
$currentPage = 'inventory-transactions';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="icon-circle bg-primary text-white shadow-sm me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-1">معاملات المخزون</h4>
                <p class="text-muted mb-0 small">عرض وإدارة جميع معاملات الوارد والصادر والتحويل</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission('inventory_transactions_create')): ?>
                <div class="btn-group shadow-sm hover-elevate rounded-pill" role="group">
                    <button type="button" class="btn btn-primary fw-bold px-4 rounded-pill dropdown-toggle border-0" data-bs-toggle="dropdown" aria-expanded="false" style="padding-top: 0.6rem; padding-bottom: 0.6rem;">
                        <i class="fas fa-plus-circle me-2"></i>إضافة معاملة
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 16px; margin-top: 8px;">
                        <li><a class="dropdown-item py-2 fw-bold text-success" href="create.php?type=incoming"><i class="fas fa-arrow-down me-2"></i>معاملة وارد</a></li>
                        <li><a class="dropdown-item py-2 fw-bold text-danger" href="create.php?type=outgoing"><i class="fas fa-arrow-up me-2"></i>معاملة صادر</a></li>
                        <li><a class="dropdown-item py-2 fw-bold text-info" href="create.php?type=transfer"><i class="fas fa-exchange-alt me-2"></i>معاملة تحويل</a></li>
                        <li><a class="dropdown-item py-2 fw-bold text-warning" href="create.php?type=return"><i class="fas fa-undo me-2"></i>معاملة مرتجع</a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-bold d-block mb-1">إجمالي المعاملات</span>
                            <h3 class="mb-0 fw-bold text-dark"><?= number_format($stats['total_transactions']) ?></h3>
                        </div>
                        <div class="icon-circle bg-primary-soft text-primary" style="width: 56px; height: 56px; font-size: 1.5rem;">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-bold d-block mb-1">معاملات معتمدة</span>
                            <h3 class="mb-0 fw-bold text-success"><?= number_format($stats['approved_transactions']) ?></h3>
                        </div>
                        <div class="icon-circle bg-success-soft text-success" style="width: 56px; height: 56px; font-size: 1.5rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small fw-bold d-block mb-1">معاملات معلقة</span>
                            <h3 class="mb-0 fw-bold text-warning"><?= number_format($stats['pending_transactions']) ?></h3>
                        </div>
                        <div class="icon-circle bg-warning-soft text-warning" style="width: 56px; height: 56px; font-size: 1.5rem;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- أدوات البحث والتصفية -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="card-header bg-white border-0 p-4 pb-0 d-flex align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-filter text-muted me-2"></i>البحث والتصفية</h6>
        </div>
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label small fw-bold text-muted">البحث</label>
                    <input type="text" class="form-control bg-light border-0" id="search" name="search"
                        value="<?= htmlspecialchars($search) ?>" placeholder="رقم المعاملة أو الملاحظات">
                </div>
                <div class="col-md-2">
                    <label for="transaction_type" class="form-label small fw-bold text-muted">نوع المعاملة</label>
                    <select class="form-select bg-light border-0" id="transaction_type" name="transaction_type">
                        <option value="">الكل</option>
                        <option value="incoming" <?= $transactionType === 'incoming' ? 'selected' : '' ?>>وارد</option>
                        <option value="outgoing" <?= $transactionType === 'outgoing' ? 'selected' : '' ?>>صادر</option>
                        <option value="transfer" <?= $transactionType === 'transfer' ? 'selected' : '' ?>>تحويل</option>
                        <option value="return" <?= $transactionType === 'return' ? 'selected' : '' ?>>مرتجع</option>
                        <option value="initial_balance" <?= $transactionType === 'initial_balance' ? 'selected' : '' ?>>رصيد افتتاحي</option>
                        <option value="loan_out" <?= $transactionType === 'loan_out' ? 'selected' : '' ?>>سلفة صادرة</option>
                        <option value="loan_in" <?= $transactionType === 'loan_in' ? 'selected' : '' ?>>سلفة واردة</option>
                        <option value="loan_return" <?= $transactionType === 'loan_return' ? 'selected' : '' ?>>إرجاع سلفة</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label small fw-bold text-muted">الحالة</label>
                    <select class="form-select bg-light border-0" id="status" name="status">
                        <option value="">الكل</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>معلق</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>معتمد</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label small fw-bold text-muted">من تاريخ</label>
                    <input type="date" class="form-control bg-light border-0" id="date_from" name="date_from"
                        value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label small fw-bold text-muted">إلى تاريخ</label>
                    <input type="date" class="form-control bg-light border-0" id="date_to" name="date_to"
                        value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 rounded-3 shadow-sm" style="padding-top: 0.6rem; padding-bottom: 0.6rem;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>

                <!-- تصفية متقدمة -->
                <div class="col-md-4 mt-3">
                    <label for="material_id" class="form-label small fw-bold text-muted">تصفية حسب المادة</label>
                    <select class="form-select bg-light border-0" id="material_id" name="material_id" onchange="this.form.submit()">
                        <option value="">جميع المواد</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?= $material['id'] ?>" <?= $materialId == $material['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($material['item_number']) ?> -
                                <?= htmlspecialchars(mb_substr($material['description'], 0, 50)) . '...' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mt-3">
                    <label for="sort_by" class="form-label small fw-bold text-muted">ترتيب حسب</label>
                    <select class="form-select bg-light border-0" id="sort_by" name="sort_by" onchange="this.form.submit()">
                        <option value="transaction_date" <?= $sortBy === 'transaction_date' ? 'selected' : '' ?>>التاريخ</option>
                        <option value="transaction_number" <?= $sortBy === 'transaction_number' ? 'selected' : '' ?>>رقم المعاملة</option>
                        <option value="transaction_type" <?= $sortBy === 'transaction_type' ? 'selected' : '' ?>>النوع</option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>الحالة</option>
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>تاريخ الإنشاء</option>
                    </select>
                </div>
                <div class="col-md-2 mt-3">
                    <label for="sort_order" class="form-label small fw-bold text-muted">الاتجاه</label>
                    <select class="form-select bg-light border-0" id="sort_order" name="sort_order" onchange="this.form.submit()">
                        <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>تنازلي</option>
                        <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>تصاعدي</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end mt-3">
                    <a href="index.php" class="btn btn-light w-100 fw-bold text-muted rounded-3 shadow-sm border-0" style="padding-top: 0.6rem; padding-bottom: 0.6rem;">
                        <i class="fas fa-undo me-2"></i>إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول المعاملات -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
        <div class="card-header bg-white border-0 p-4 pb-3 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid rgba(0,0,0,0.03) !important;">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="fas fa-list text-primary me-2"></i>قائمة المعاملات 
                <span class="badge bg-primary-soft text-primary ms-2 rounded-pill px-3 py-2"><?= number_format($totalTransactions) ?></span>
            </h6>
            <div class="btn-group shadow-sm rounded-pill" role="group">
                <button type="button" class="btn btn-light btn-sm fw-bold text-success border-0 px-3 py-2" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i>تصدير
                </button>
                <button type="button" class="btn btn-light btn-sm fw-bold text-secondary border-0 px-3 py-2" onclick="printTable()">
                    <i class="fas fa-print me-1"></i>طباعة
                </button>
            </div>
        </div>
        <div class="card-body p-4 pt-0">
            <div class="table-responsive mt-3">
                <table id="transactionsTable" class="table table-hover align-middle mb-0" style="width:100%; color: #475569;">
                    <thead style="background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #64748b;">
                        <tr>
                            <th class="border-0 fw-bold ps-4 py-3">رقم المعاملة</th>
                            <th class="border-0 fw-bold py-3">النوع</th>
                            <th class="border-0 fw-bold py-3">رقم أمر العمل</th>
                            <th class="border-0 fw-bold py-3">التاريخ</th>
                            <th class="border-0 fw-bold py-3">عدد البنود</th>
                            <th class="border-0 fw-bold py-3">الحالة</th>
                            <th class="border-0 fw-bold py-3">المنشئ</th>
                            <th class="border-0 fw-bold pe-4 py-3 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // اعتماد المعاملة
    function approveTransaction(transactionId) {
        if (confirm('هل أنت متأكد من اعتماد هذه المعاملة؟\nسيتم تحديث المخزون وفقاً لهذه المعاملة.')) {
            updateTransactionStatus(transactionId, 'approved');
        }
    }

    // رفض المعاملة
    function rejectTransaction(transactionId) {
        const reason = prompt('يرجى إدخال سبب الرفض:');
        if (reason !== null && reason.trim() !== '') {
            updateTransactionStatus(transactionId, 'rejected', reason);
        }
    }

    function updateTransactionStatus(transactionId, status, reason = '') {
        fetch('update-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                transaction_id: transactionId,
                status: status,
                reason: reason
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ في الاتصال');
            });
    }

    // تصدير إلى Excel
    function exportToExcel() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        window.location.href = 'export.php?' + params.toString();
    }

    // طباعة الجدول
    function printTable() {
        window.print();
    }
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        var table = $('#transactionsTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "api-data.php",
                "data": function(d) {
                    d.transaction_type = '<?= addslashes($transactionType) ?>';
                    d.status = '<?= addslashes($status) ?>';
                    d.date_from = '<?= addslashes($dateFrom) ?>';
                    d.date_to = '<?= addslashes($dateTo) ?>';
                }
            },
            "language": {
                "sProcessing": "<div class='spinner-border spinner-border-sm text-primary me-2'></div> جارٍ التحميل...",
                "sLengthMenu": "أظهر _MENU_ مدخلات",
                "sZeroRecords": "لم يعثر على أية سجلات",
                "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
                "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
                "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
                "sSearch": "ابحث:",
                "oPaginate": {
                    "sFirst": "الأول",
                    "sPrevious": "السابق",
                    "sNext": "التالي",
                    "sLast": "الأخير"
                }
            },
            "responsive": true,
            "pageLength": 25,
            "order": [[3, 'desc']],
            "columnDefs": [
                { "orderable": false, "targets": [7] }
            ]
        });
    });

    // دوال إدارة المعاملات
    function viewTransaction(id) {
        window.location.href = `view.php?id=${id}`;
    }

    function editTransaction(id) {
        window.location.href = `edit.php?id=${id}`;
    }
</script>