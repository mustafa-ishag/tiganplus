<?php
/**
 * أوامر العمل مع إحصائيات الإنتاجية
 * Work Orders with Productivity Statistics
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';
require_once __DIR__ . '/../../../models/ProductivityDailyLog.php';

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

$pageTitle = 'أوامر العمل - نظام الإنتاجية';
$currentPage = 'productivity-work-orders';

// إنشاء كائنات النماذج
$workItemModel = new ProductivityWorkItem();
$dailyLogModel = new ProductivityDailyLog();

// معالجة الفلاتر
$filters = [];
if (!hasPermission('productivity_daily_logs_view_all_branches') && isset($_SESSION['branch_id'])) {
    $filters['branch_id'] = $_SESSION['branch_id'];
}

// إضافة فلاتر البحث
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (!empty($_GET['branch_id']) && hasPermission('productivity_daily_logs_view_all_branches')) {
    $filters['branch_id'] = $_GET['branch_id'];
}

try {
    $db = getDB();
    
    // جلب أوامر العمل مع إحصائيات الإنتاجية (فقط التي لها بنود إنتاجية)
    $sql = "
        SELECT
            wo.*,
            b.name as branch_name,
            b.code as branch_code,
            wot.type_code,
            wot.description as work_order_type_name,
            -- إحصائيات بنود الإنتاجية
            COUNT(DISTINCT pwi.id) as total_work_items,
            COUNT(DISTINCT CASE WHEN pwi.status = 'active' THEN pwi.id END) as active_work_items,
            COUNT(DISTINCT CASE WHEN pwi.status = 'completed' THEN pwi.id END) as completed_work_items,
            SUM(DISTINCT pwi.target_quantity * pwi.unit_price) as total_target_value,
            -- إحصائيات السجلات اليومية
            COUNT(DISTINCT pdl.id) as total_daily_logs,
            COUNT(DISTINCT CASE WHEN pdl.status = 'approved' THEN pdl.id END) as approved_logs,
            COUNT(DISTINCT CASE WHEN pdl.status = 'submitted' THEN pdl.id END) as pending_logs,
            SUM(CASE WHEN pdl.status = 'approved' THEN pdl.quantity_completed * pwi.unit_price ELSE 0 END) as total_approved_value,
            -- حساب نسبة الإنجاز
            CASE
                WHEN SUM(DISTINCT pwi.target_quantity) > 0 THEN
                    (SUM(CASE WHEN pdl.status = 'approved' THEN pdl.quantity_completed ELSE 0 END) / SUM(DISTINCT pwi.target_quantity)) * 100
                ELSE 0
            END as completion_percentage
        FROM work_orders wo
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        INNER JOIN productivity_work_items pwi ON wo.id = pwi.work_order_id
        LEFT JOIN productivity_daily_logs pdl ON pwi.id = pdl.work_item_id
        WHERE wo.status IN ('active', 'completed')
    ";
    
    $params = [];
    
    // تطبيق الفلاتر
    if (!empty($filters['search'])) {
        $sql .= " AND (wo.work_order_number LIKE ? OR wot.type_code LIKE ? OR wot.description LIKE ? OR wo.department LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['branch_id'])) {
        $sql .= " AND wo.branch_id = ?";
        $params[] = $filters['branch_id'];
    }
    
    $sql .= " GROUP BY wo.id ORDER BY wo.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الفروع للفلتر
    $branches = [];
    if (hasPermission('productivity_daily_logs_view_all_branches')) {
        $branchesStmt = $db->prepare("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
        $branchesStmt->execute();
        $branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // حساب الإحصائيات العامة
    $totalWorkOrders = count($workOrders);
    $totalTargetValue = array_sum(array_column($workOrders, 'total_target_value'));
    $totalApprovedValue = array_sum(array_column($workOrders, 'total_approved_value'));
    $avgCompletion = $totalWorkOrders > 0 ? array_sum(array_column($workOrders, 'completion_percentage')) / $totalWorkOrders : 0;
    
} catch (Exception $e) {
    error_log("Error in productivity work orders: " . $e->getMessage());
    $workOrders = [];
    $branches = [];
    $error = "حدث خطأ في جلب البيانات";
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- عنوان الصفحة -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-clipboard-list text-primary"></i>
            أوامر العمل - نظام الإنتاجية
        </h1>
        <p class="text-muted mb-0">عرض أوامر العمل التي تحتوي على بنود إنتاجية مع إحصائيات الأداء</p>
    </div>
    <div>
        <?php if (hasPermission('productivity_work_items_create')): ?>
        <a href="<?= path('productivity/work-items/create.php') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            إضافة بند إنتاجية
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- إحصائيات عامة -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-primary text-uppercase mb-1">
                            إجمالي أوامر العمل
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= number_format($totalWorkOrders) ?>
                        </div>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-success text-uppercase mb-1">
                            إجمالي القيمة المستهدفة
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= number_format($totalTargetValue, 2) ?> ريال
                        </div>
                    </div>
                    <div class="text-success">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-info text-uppercase mb-1">
                            إجمالي القيمة المعتمدة
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= number_format($totalApprovedValue, 2) ?> ريال
                        </div>
                    </div>
                    <div class="text-info">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-warning text-uppercase mb-1">
                            متوسط نسبة الإنجاز
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= number_format($avgCompletion, 1) ?>%
                        </div>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-chart-pie fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- فلاتر البحث -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-filter me-2"></i>
            فلاتر البحث
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">البحث</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       placeholder="رقم أمر العمل، نوع الأمر، أو القسم">
            </div>
            
            <?php if (hasPermission('productivity_daily_logs_view_all_branches')): ?>
            <div class="col-md-4">
                <label for="branch_id" class="form-label">الفرع</label>
                <select class="form-select" id="branch_id" name="branch_id">
                    <option value="">جميع الفروع</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>" 
                                <?= ($_GET['branch_id'] ?? '') == $branch['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-2"></i>
                    بحث
                </button>
                <a href="<?= path('productivity/work-orders/index.php') ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>

<!-- جدول أوامر العمل -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-table me-2"></i>
            أوامر العمل التي تحتوي على بنود إنتاجية
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($workOrders)): ?>
            <div class="text-center py-4">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا توجد أوامر عمل</h5>
                <p class="text-muted">لم يتم العثور على أوامر عمل لها بنود إنتاجية تطابق معايير البحث</p>
                <p class="text-info">
                    <i class="fas fa-info-circle me-2"></i>
                    يتم عرض أوامر العمل التي تحتوي على بنود إنتاجية فقط
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="workOrdersTable">
                    <thead class="table-light">
                        <tr>
                            <th>رقم أمر العمل</th>
                            <th>نوع الأمر</th>
                            <th>القسم</th>
                            <th>الفرع</th>
                            <th>بنود الإنتاجية</th>
                            <th>القيمة المستهدفة</th>
                            <th>القيمة المعتمدة</th>
                            <th>نسبة الإنجاز</th>
                            <th>السجلات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workOrders as $workOrder): ?>
                            <tr>
                                <td>
                                    <strong class="text-primary">
                                        <?= htmlspecialchars($workOrder['work_order_number']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($workOrder['type_code'] ?? 'غير محدد') ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($workOrder['work_order_type_name'] ?? 'غير محدد') ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $deptClass = $workOrder['department'] == 'connections' ? 'bg-primary' : 'bg-success';
                                    $deptText = $workOrder['department'] == 'connections' ? 'التوصيلات' : 'المشاريع';
                                    ?>
                                    <span class="badge <?= $deptClass ?>">
                                        <?= $deptText ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= htmlspecialchars($workOrder['branch_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="text-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            مكتمل: <?= $workOrder['completed_work_items'] ?>
                                        </small>
                                        <small class="text-primary">
                                            <i class="fas fa-play-circle me-1"></i>
                                            نشط: <?= $workOrder['active_work_items'] ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-list me-1"></i>
                                            الإجمالي: <?= $workOrder['total_work_items'] ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-success">
                                        <?= number_format($workOrder['total_target_value'] ?? 0, 2) ?> ريال
                                    </strong>
                                </td>
                                <td>
                                    <strong class="text-info">
                                        <?= number_format($workOrder['total_approved_value'] ?? 0, 2) ?> ريال
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $completion = $workOrder['completion_percentage'] ?? 0;
                                    $progressClass = $completion >= 80 ? 'success' : ($completion >= 50 ? 'warning' : 'danger');
                                    ?>
                                    <div class="progress mb-1" style="height: 20px;">
                                        <div class="progress-bar bg-<?= $progressClass ?>"
                                             role="progressbar"
                                             style="width: <?= $completion ?>%"
                                             aria-valuenow="<?= $completion ?>"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                            <?= number_format($completion, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>
                                            معتمد: <?= $workOrder['approved_logs'] ?>
                                        </small>
                                        <small class="text-warning">
                                            <i class="fas fa-clock me-1"></i>
                                            معلق: <?= $workOrder['pending_logs'] ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-list me-1"></i>
                                            الإجمالي: <?= $workOrder['total_daily_logs'] ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if (hasPermission('productivity_work_items_view')): ?>
                                        <a href="<?= path('productivity/work-items/index.php?work_order_id=' . $workOrder['id']) ?>"
                                           class="btn btn-sm btn-primary"
                                           title="عرض بنود الإنتاجية">
                                            <i class="fas fa-tasks"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (hasPermission('productivity_work_items_create')): ?>
                                        <a href="<?= path('productivity/work-items/create.php?work_order_id=' . $workOrder['id']) ?>"
                                           class="btn btn-sm btn-success"
                                           title="إضافة بند إنتاجية">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// إضافة JavaScript للصفحة
$pageJS = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // التأكد من تحميل jQuery و DataTables
    if (typeof $ !== "undefined" && typeof $.fn.DataTable !== "undefined") {
        initializeDataTable();
    } else {
        // انتظار تحميل jQuery و DataTables
        var checkLibraries = setInterval(function() {
            if (typeof $ !== "undefined" && typeof $.fn.DataTable !== "undefined") {
                clearInterval(checkLibraries);
                initializeDataTable();
            }
        }, 100);
    }
});

function initializeDataTable() {
    // تهيئة DataTable
    $("#workOrdersTable").DataTable({
        "language": {
            "search": "البحث:",
            "lengthMenu": "عرض _MENU_ سجل",
            "info": "عرض _START_ إلى _END_ من _TOTAL_ سجل",
            "infoEmpty": "عرض 0 إلى 0 من 0 سجل",
            "infoFiltered": "(مفلتر من _MAX_ سجل)",
            "paginate": {
                "first": "الأول",
                "last": "الأخير",
                "next": "التالي",
                "previous": "السابق"
            },
            "emptyTable": "لا توجد بيانات متاحة",
            "zeroRecords": "لم يتم العثور على نتائج مطابقة"
        },
        "order": [[ 0, "desc" ]],
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [8] } // عمود الإجراءات غير قابل للترتيب
        ]
    });
}
</script>
';
$content .= $pageJS;

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
