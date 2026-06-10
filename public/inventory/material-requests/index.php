<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة قائمة طلبات الصرف
 * Material Requests List Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';
require_once __DIR__ . '/../../../models/WorkOrder.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_requests_view')) {
    setAlert('ليس لديك صلاحية لعرض طلبات الصرف', 'error');
    redirect('../../dashboard.php');
}

$materialRequestModel = new MaterialRequest();
$workOrderModel = new WorkOrder();

// معالجة البحث والتصفية
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$workOrderId = $_GET['work_order_id'] ?? '';
$requestedBy = $_GET['requested_by'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'created_at';
$sortOrder = $_GET['sort_order'] ?? 'DESC';
$page = (int) ($_GET['page'] ?? 1);
$perPage = 20;

// بناء شروط البحث
$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(mr.request_number LIKE ? OR mr.notes LIKE ? OR wo.work_order_number LIKE ?)';
    $searchPattern = "%{$search}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($status)) {
    $whereConditions[] = 'mr.status = ?';
    $params[] = $status;
}

if (!empty($workOrderId)) {
    $whereConditions[] = 'mr.work_order_id = ?';
    $params[] = $workOrderId;
}

if (!empty($requestedBy)) {
    $whereConditions[] = 'mr.requested_by = ?';
    $params[] = $requestedBy;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'mr.request_date >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'mr.request_date <= ?';
    $params[] = $dateTo;
}

// تصفية حسب الفرع للمستخدمين المحدودين
if (isset($_SESSION['user_branch_id']) && $_SESSION['user_branch_id']) {
    $whereConditions[] = 'wo.branch_id = ?';
    $params[] = $_SESSION['user_branch_id'];
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// حساب إجمالي السجلات
$totalRecords = $materialRequestModel->fetchColumn(
    "SELECT COUNT(*) 
     FROM material_requests mr
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     {$whereClause}",
    $params
);

$totalPages = ceil($totalRecords / $perPage);
$offset = ($page - 1) * $perPage;

// Whitelist للترتيب لمنع SQL injection
$allowedSortBy = ['created_at', 'request_date', 'request_number', 'status'];
$safeSortBy = in_array($sortBy, $allowedSortBy) ? $sortBy : 'created_at';
$safeSortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// الحصول على طلبات الصرف
$orderClause = "ORDER BY mr.{$safeSortBy} {$safeSortOrder}";
$limitClause = "LIMIT {$perPage} OFFSET {$offset}";

$materialRequests = $materialRequestModel->fetchAll(
    "SELECT mr.*,
            COALESCE(mr.status, 'draft') as status,
            COUNT(mrd.id) as item_count,
            SUM(mrd.requested_quantity) as total_quantity,
            wo.work_order_number,
            wot.type_code as work_order_type_code,
            b.name as branch_name, b.code as branch_code,
            u1.full_name as requested_by_name,
            u2.full_name as warehouse_approved_by_name,
            u3.full_name as project_approved_by_name
     FROM material_requests mr
     LEFT JOIN material_request_details mrd ON mr.id = mrd.request_id
     LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
     LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
     LEFT JOIN branches b ON wo.branch_id = b.id
     LEFT JOIN users u1 ON mr.requested_by = u1.id
     LEFT JOIN users u2 ON mr.warehouse_approved_by = u2.id
     LEFT JOIN users u3 ON mr.project_approved_by = u3.id
     {$whereClause}
     GROUP BY mr.id
     {$orderClause} {$limitClause}",
    $params
);

// الحصول على إحصائيات طلبات الصرف
$stats = $materialRequestModel->getMaterialRequestStats();

// الحصول على قائمة أوامر العمل للتصفية
$workOrders = $workOrderModel->getActiveWorkOrders($_SESSION['user_branch_id'] ?? null);

$pageTitle = 'طلبات الصرف';
$currentPage = 'material-requests';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-clipboard-list text-primary me-2"></i>
                طلبات الصرف
            </h2>
            <p class="text-muted mb-0">عرض وإدارة جميع طلبات صرف المواد</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if (hasPermission('inventory_requests_create')): ?>
                <a href="create.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>
                    إضافة طلب صرف
                </a>
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
                            <h6 class="card-title">إجمالي الطلبات</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_requests']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clipboard-list fa-2x opacity-75"></i>
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
                            <h6 class="card-title">في الانتظار</h6>
                            <h3 class="mb-0"><?= number_format($stats['pending_requests']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x opacity-75"></i>
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
                            <h6 class="card-title">معتمدة</h6>
                            <h3 class="mb-0"><?= number_format($stats['approved_requests']) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">إجمالي الكمية</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_quantity'], 0) ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-boxes fa-2x opacity-75"></i>
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
                        value="<?= htmlspecialchars($search) ?>" placeholder="رقم الطلب أو رقم أمر العمل">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">الحالة</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">جميع الحالات</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>مسودة</option>
                        <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>مرسل</option>
                        <option value="warehouse_approved" <?= $status === 'warehouse_approved' ? 'selected' : '' ?>>موافقة
                            المستودع</option>
                        <option value="project_approved" <?= $status === 'project_approved' ? 'selected' : '' ?>>موافقة
                            المشروع</option>
                        <option value="branch_approved" <?= $status === 'branch_approved' ? 'selected' : '' ?>>موافقة الفرع
                        </option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                        <option value="revision_requested" <?= $status === 'revision_requested' ? 'selected' : '' ?>>طلب تعديل</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="work_order_id" class="form-label">أمر العمل</label>
                    <select class="form-select" id="work_order_id" name="work_order_id">
                        <option value="">جميع أوامر العمل</option>
                        <?php foreach ($workOrders as $workOrder): ?>
                            <option value="<?= $workOrder['id'] ?>" <?= $workOrderId == $workOrder['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($workOrder['work_order_number']) ?>
                            </option>
                        <?php endforeach; ?>
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
                <div class="col-md-2">
                    <label for="sort_by" class="form-label">ترتيب حسب</label>
                    <select class="form-select" id="sort_by" name="sort_by" onchange="this.form.submit()">
                        <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>تاريخ الإنشاء</option>
                        <option value="request_date" <?= $sortBy === 'request_date' ? 'selected' : '' ?>>تاريخ الطلب
                        </option>
                        <option value="request_number" <?= $sortBy === 'request_number' ? 'selected' : '' ?>>رقم الطلب
                        </option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>الحالة</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort_order" class="form-label">الاتجاه</label>
                    <select class="form-select" id="sort_order" name="sort_order" onchange="this.form.submit()">
                        <option value="DESC" <?= $sortOrder === 'DESC' ? 'selected' : '' ?>>تنازلي</option>
                        <option value="ASC" <?= $sortOrder === 'ASC' ? 'selected' : '' ?>>تصاعدي</option>
                    </select>
                </div>
                <div class="col-md-8 d-flex align-items-end">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>
                        إعادة تعيين
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول طلبات الصرف -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة طلبات الصرف (<?= number_format($totalRecords) ?> طلب)</h5>
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
            <?php if (empty($materialRequests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">لا توجد طلبات صرف</h5>
                    <p class="text-muted">لم يتم العثور على طلبات تطابق معايير البحث</p>
                    <?php if (hasPermission('inventory_requests_create')): ?>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            إضافة أول طلب صرف
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="materialRequestsTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم الطلب</th>
                                <th>أمر العمل</th>
                                <th>تاريخ الطلب</th>
                                <th>عدد البنود</th>
                                <th>الحالة</th>
                                <th>مقدم الطلب</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materialRequests as $request): ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?= $request['id'] ?>" class="text-decoration-none fw-bold">
                                            <?= htmlspecialchars($request['request_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="../work-orders/view.php?id=<?= $request['work_order_id'] ?>"
                                            class="text-decoration-none">
                                            <?= htmlspecialchars($request['work_order_number'] ?? '-') ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($request['work_order_type_code'] ?? '-') ?> -
                                            <?= htmlspecialchars($request['branch_code'] ?? '-') ?>
                                        </small>
                                    </td>
                                    <td><?= formatDate($request['request_date']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= number_format($request['item_count']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $currentStatus = $request['status'] ?? 'draft';
                                        if (empty($currentStatus)) {
                                            $currentStatus = 'draft';
                                        }
                                        $statusInfo = getStatusLabel($currentStatus);
                                        ?>
                                        <span class="badge bg-<?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($request['requested_by_name'] ?? 'غير معروف') ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary"
                                                title="عرض التفاصيل">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission('inventory_requests_edit') && in_array($request['status'], ['draft', 'revision_requested'])): ?>
                                                <a href="edit.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-warning"
                                                    title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php
                                            // نظام الموافقة الديناميكي
                                            $currentStep = $materialRequestModel->getCurrentStepForRequest($request);
                                            $canApproveCurrentStep = false;
                                            if ($currentStep) {
                                                $canApproveCurrentStep = canApproveRequestByStep($currentStep['id'], $request['branch_id'] ?? null, $request['work_order_id']);
                                            }
                                            ?>
                                            <?php if ($currentStep && $canApproveCurrentStep): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success"
                                                    onclick="approveRequest(<?= $request['id'] ?>, <?= $currentStep['id'] ?>, '<?= htmlspecialchars($currentStep['step_name']) ?>')"
                                                    title="<?= htmlspecialchars($currentStep['step_name']) ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                    onclick="requestRevision(<?= $request['id'] ?>)" title="طلب تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="rejectRequest(<?= $request['id'] ?>)" title="رفض">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- التنقل بين الصفحات -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="تنقل الصفحات">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            السابق
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link"
                                            href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            التالي
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                عرض <?= ($page - 1) * $perPage + 1 ?> إلى <?= min($page * $perPage, $totalRecords) ?>
                                من أصل <?= number_format($totalRecords) ?> طلب
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // موافقة على الطلب
    function approveRequest(requestId, stepId, stepName) {
        const notes = prompt(`ملاحظات ${stepName} (اختياري):`);
        if (notes !== null) {
            updateRequestStatusNew(requestId, 'approve', stepId, notes);
        }
    }

    // رفض الطلب
    function rejectRequest(requestId) {
        const reason = prompt('يرجى إدخال سبب الرفض:');
        if (reason !== null && reason.trim() !== '') {
            updateRequestStatusNew(requestId, 'reject', null, reason);
        }
    }

    // طلب تعديل
    function requestRevision(requestId) {
        const notes = prompt('يرجى إدخال ملاحظات التعديل المطلوب:');
        if (notes !== null && notes.trim() !== '') {
            updateRequestStatusNew(requestId, 'request_revision', null, notes);
        }
    }

    function updateRequestStatus(requestId, action, level = null, reason = '') {
        fetch('update-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                request_id: requestId,
                action: action,
                level: level,
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
        // تهيئة DataTable للجدول
        if ($('#materialRequestsTable').length) {
            $('#materialRequestsTable').DataTable({
                "language": {
                    "sProcessing": "جارٍ التحميل...",
                    "sLengthMenu": "أظهر _MENU_ مدخلات",
                    "sZeroRecords": "لم يعثر على أية سجلات",
                    "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
                    "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
                    "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
                    "sInfoPostFix": "",
                    "sSearch": "ابحث:",
                    "sUrl": "",
                    "oPaginate": {
                        "sFirst": "الأول",
                        "sPrevious": "السابق",
                        "sNext": "التالي",
                        "sLast": "الأخير"
                    }
                },
                "responsive": true,
                "pageLength": 25,
                "order": [[0, 'desc']],
                "columnDefs": [
                    { "orderable": false, "targets": -1 }
                ]
            });
        }
    });

    // دوال إدارة طلبات الصرف
    function viewMaterialRequest(id) {
        window.location.href = `view.php?id=${id}`;
    }

    function editMaterialRequest(id) {
        window.location.href = `edit.php?id=${id}`;
    }

    // دالة الموافقة والرفض باستخدام النظام الديناميكي
    function updateRequestStatusNew(requestId, action, stepId = null, reason = '') {
        fetch('update-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                request_id: requestId,
                action: action,
                step_id: stepId,
                reason: reason
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(error => {
                alert('خطأ في الاتصال: ' + error.message);
                console.error('Error:', error);
            });
    }

    function updateRequestStatus(id, status) {
        let action, confirmText;

        switch (status) {
            case 'approved':
                action = 'الموافقة على';
                confirmText = 'سيتم الموافقة على الطلب وتحديث المخزون';
                break;
            case 'rejected':
                action = 'رفض';
                confirmText = 'سيتم رفض الطلب';
                break;
            case 'completed':
                action = 'إكمال';
                confirmText = 'سيتم إكمال الطلب وصرف المواد';
                break;
            default:
                action = 'تحديث';
                confirmText = 'سيتم تحديث حالة الطلب';
        }

        Swal.fire({
            title: 'تأكيد العملية',
            text: `هل أنت متأكد من ${action} هذا الطلب؟`,
            html: `<p>هل أنت متأكد من ${action} هذا الطلب؟</p><small class="text-muted">${confirmText}</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'نعم، متأكد',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار loading
                Swal.fire({
                    title: 'جاري المعالجة...',
                    text: `جاري ${action} الطلب`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // إرسال الطلب
                fetch('update-status-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        status: status
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم بنجاح',
                                text: data.message,
                                confirmButtonText: 'موافق'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'خطأ',
                                text: data.message,
                                confirmButtonText: 'موافق'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الاتصال',
                            text: 'حدث خطأ أثناء الاتصال بالخادم',
                            confirmButtonText: 'موافق'
                        });
                        console.error('Error:', error);
                    });
            }
        });
    }
</script>