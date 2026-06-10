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

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-exchange-alt text-primary me-2"></i>
                معاملات المخزون
            </h2>
            <p class="text-muted mb-0">عرض وإدارة جميع معاملات الوارد والصادر والتحويل</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if (hasPermission('inventory_transactions_create')): ?>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-plus me-1"></i>
                        إضافة معاملة
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="create.php?type=incoming">
                                <i class="fas fa-arrow-down text-success me-2"></i>معاملة وارد
                            </a></li>
                        <li><a class="dropdown-item" href="create.php?type=outgoing">
                                <i class="fas fa-arrow-up text-danger me-2"></i>معاملة صادر
                            </a></li>
                        <li><a class="dropdown-item" href="create.php?type=transfer">
                                <i class="fas fa-exchange-alt text-info me-2"></i>معاملة تحويل
                            </a></li>
                        <li><a class="dropdown-item" href="create.php?type=return">
                                <i class="fas fa-undo text-warning me-2"></i>معاملة مرتجع
                            </a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">إجمالي المعاملات</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_transactions']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exchange-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">معاملات معتمدة</h6>
                            <h3 class="mb-0"><?= number_format($stats['approved_transactions']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">معاملات معلقة</h6>
                            <h3 class="mb-0"><?= number_format($stats['pending_transactions']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- أدوات البحث والتصفية -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">البحث</label>
                    <input type="text" class="form-control" id="search" name="search"
                        value="<?= htmlspecialchars($search) ?>" placeholder="رقم المعاملة أو الملاحظات">
                </div>
                <div class="col-md-2">
                    <label for="transaction_type" class="form-label">نوع المعاملة</label>
                    <select class="form-select" id="transaction_type" name="transaction_type">
                        <option value="">جميع الأنواع</option>
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
                    <label for="status" class="form-label">الحالة</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">جميع الحالات</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>معلق</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>معتمد</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">من تاريخ</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                        value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">إلى تاريخ</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                        value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <!-- تصفية متقدمة -->
            <div class="row mt-3">
                <div class="col-md-4">
                    <label for="material_id" class="form-label">تصفية حسب المادة</label>
                    <select class="form-select" id="material_id" name="material_id" onchange="this.form.submit()">
                        <option value="">جميع المواد</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?= $material['id'] ?>" <?= $materialId == $material['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($material['item_number']) ?> -
                                <?= htmlspecialchars($material['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort_by" class="form-label">ترتيب حسب</label>
                    <select class="form-select" id="sort_by" name="sort_by" onchange="this.form.submit()">
                        <option value="transaction_date" <?= $sortBy === 'transaction_date' ? 'selected' : '' ?>>التاريخ
                        </option>
                        <option value="transaction_number" <?= $sortBy === 'transaction_number' ? 'selected' : '' ?>>رقم
                            المعاملة</option>
                        <option value="transaction_type" <?= $sortBy === 'transaction_type' ? 'selected' : '' ?>>النوع
                        </option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>الحالة</option>
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>تاريخ الإنشاء</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort_order" class="form-label">الاتجاه</label>
                    <select class="form-select" id="sort_order" name="sort_order" onchange="this.form.submit()">
                        <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>تنازلي</option>
                        <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>تصاعدي</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>
                        إعادة تعيين
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول المعاملات -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة المعاملات (<?= number_format($totalTransactions) ?> معاملة)</h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i>
                    تصدير Excel
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printTable()">
                    <i class="fas fa-print me-1"></i>
                    طباعة
                </button>
            </div>
        </div>
        <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="transactionsTable" class="table table-hover mb-0" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>رقم المعاملة</th>
                                <th>النوع</th>
                                <th>رقم أمر العمل</th>
                                <th>التاريخ</th>
                                <th>عدد البنود</th>
                                <th>الحالة</th>
                                <th>المنشئ</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
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