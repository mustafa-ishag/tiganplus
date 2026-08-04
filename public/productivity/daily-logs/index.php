<?php
/**
 * إدارة السجلات اليومية للإنتاجية
 * Daily Productivity Logs Management
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityDailyLog.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_daily_logs_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'السجلات اليومية للإنتاجية';
$currentPage = 'productivity-daily-logs';

// إنشاء كائن النموذج
$dailyLogModel = new ProductivityDailyLog();

// معالجة الفلاتر
$filters = [];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$work_item_id = $_GET['work_item_id'] ?? '';
$work_order_id = $_GET['work_order_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';

if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($status)) {
    $filters['status'] = $status;
}
if (!empty($work_item_id)) {
    $filters['work_item_id'] = $work_item_id;
}
if (!empty($work_order_id)) {
    $filters['work_order_id'] = $work_order_id;
}
if (!empty($date_from)) {
    $filters['date_from'] = $date_from;
}
if (!empty($date_to)) {
    $filters['date_to'] = $date_to;
}

// تطبيق فلتر الفرع حسب الصلاحيات
if (!hasPermission('productivity_daily_logs_view_all_branches') && isset($_SESSION['branch_id'])) {
    $filters['branch_id'] = $_SESSION['branch_id'];
} elseif (!empty($branch_id)) {
    $filters['branch_id'] = $branch_id;
}

// الترقيم
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// جلب البيانات
$dailyLogs = $dailyLogModel->getAll($filters, $limit, $offset);
$totalCount = $dailyLogModel->getTotalCount($filters);
$totalPages = ceil($totalCount / $limit);

// جلب الإحصائيات
$statistics = $dailyLogModel->getDailyStatistics($filters);

// جلب قوائم الفلاتر
$db = getDB();

// جلب بنود الإنتاجية النشطة
$workItemsQuery = "
    SELECT pwi.id, wo.work_order_number, wi.item_number, wi.description, b.name as branch_name
    FROM productivity_work_items pwi
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
    JOIN branches b ON wo.branch_id = b.id
    WHERE pwi.status = 'active'
";

$workItemsParams = [];
if (!hasPermission('productivity_daily_logs_view_all_branches') && isset($_SESSION['branch_id'])) {
    $workItemsQuery .= " AND wo.branch_id = ?";
    $workItemsParams[] = $_SESSION['branch_id'];
}

$workItemsQuery .= " ORDER BY wo.work_order_number, wi.item_number";

$workItemsStmt = $db->prepare($workItemsQuery);
$workItemsStmt->execute($workItemsParams);
$workItems = $workItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الفروع (إذا كان لديه صلاحية عرض جميع الفروع)
$branches = [];
if (hasPermission('productivity_daily_logs_view_all_branches')) {
    $branchesStmt = $db->prepare("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branchesStmt->execute();
    $branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// جلب معلومات أمر العمل المحدد (إذا تم تمريره)
$selectedWorkOrder = null;
if ($work_order_id) {
    $workOrderStmt = $db->prepare("
        SELECT wo.*, b.name as branch_name
        FROM work_orders wo
        JOIN branches b ON wo.branch_id = b.id
        WHERE wo.id = ?
    ");
    $workOrderStmt->execute([$work_order_id]);
    $selectedWorkOrder = $workOrderStmt->fetch(PDO::FETCH_ASSOC);
}

// بدء تخزين المحتوى
ob_start();
?>
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-clipboard-list text-primary"></i>
                <?php if ($selectedWorkOrder): ?>
                    السجلات اليومية - أمر العمل رقم <?= htmlspecialchars($selectedWorkOrder['work_order_number']) ?>
                <?php else: ?>
                    السجلات اليومية للإنتاجية
                <?php endif; ?>
            </h1>
            <?php if ($selectedWorkOrder): ?>
                <p class="text-muted mb-0">
                    <i class="fas fa-building me-2"></i>
                    <?= htmlspecialchars($selectedWorkOrder['branch_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-info-circle me-2"></i>
                    <?= htmlspecialchars($selectedWorkOrder['notes']) ?>
                </p>
                <div class="mt-2">
                    <a href="<?= path('work-orders/index.php') ?>" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-right me-2"></i>
                        العودة لأوامر العمل
                    </a>
                    <a href="<?= path('productivity/work-items/index.php?work_order_id=' . $selectedWorkOrder['id']) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-tasks me-2"></i>
                        بنود الإنتاجية
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <?php if (hasPermission('productivity_daily_logs_create')): ?>
            <a href="create.php<?= $selectedWorkOrder ? '?work_order_id=' . $selectedWorkOrder['id'] : '' ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> تسجيل إنتاجية يومية
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي السجلات
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                السجلات المعتمدة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['approved_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                السجلات المعلقة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['submitted_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                المسودات
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['draft_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- إحصائيات إضافية -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                السجلات المرفوضة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['rejected_logs'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                إجمالي الساعات
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_hours'] ?? 0, 1) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-business-time fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card border-left-dark shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">
                                إجمالي الكمية
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($statistics['total_quantity'] ?? 0, 2) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- بطاقة الفلاتر -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i>
                البحث والفلاتر
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3 mb-3">
                    <label for="search" class="form-label">البحث</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="رقم أمر العمل أو وصف البند">
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="status" class="form-label">الحالة</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">جميع الحالات</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>مسودة</option>
                        <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>مرسل</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>معتمد</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                        <option value="returned" <?= $status === 'returned' ? 'selected' : '' ?>>مرجع</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="date_from" class="form-label">من تاريخ</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?= htmlspecialchars($date_from) ?>">
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="date_to" class="form-label">إلى تاريخ</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?= htmlspecialchars($date_to) ?>">
                </div>
                
                <?php if (!empty($branches)): ?>
                <div class="col-md-2 mb-3">
                    <label for="branch_id" class="form-label">الفرع</label>
                    <select class="form-control" id="branch_id" name="branch_id">
                        <option value="">جميع الفروع</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['id'] ?>" <?= $branch_id == $branch['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 mb-3">
                    <label for="work_item_id" class="form-label">بند الإنتاجية</label>
                    <select class="form-control" id="work_item_id" name="work_item_id">
                        <option value="">جميع البنود</option>
                        <?php foreach ($workItems as $workItem): ?>
                        <option value="<?= $workItem['id'] ?>" <?= $work_item_id == $workItem['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($workItem['work_order_number']) ?> - 
                            <?= htmlspecialchars($workItem['item_number']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- بطاقة النتائج -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                النتائج (<?= number_format($totalCount) ?> سجل)
            </h6>
            <div class="btn-group" role="group">
                <?php if (hasPermission('productivity_reports_export')): ?>
                <a href="export.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> تصدير Excel
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($dailyLogs)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                <p class="text-gray-500">لا توجد سجلات يومية مطابقة للبحث</p>
                <?php if (hasPermission('productivity_daily_logs_create')): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> تسجيل إنتاجية يومية جديدة
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>التاريخ</th>
                            <th>أمر العمل</th>
                            <th>بند العمل</th>
                            <th>الكمية المنجزة</th>
                            <th>عدد العمال</th>
                            <th>ساعات العمل</th>
                            <th>القيمة المحسوبة</th>
                            <th>الحالة</th>
                            <th>المسجل بواسطة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailyLogs as $log): ?>
                        <tr>
                            <td>
                                <strong><?= date('Y-m-d', strtotime($log['log_date'])) ?></strong>
                                <br>
                                <small class="text-muted"><?= date('l', strtotime($log['log_date'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['work_order_number']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($log['branch_name']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['item_number']) ?></strong>
                                <br>
                                <small><?= htmlspecialchars(substr($log['work_item_description'], 0, 40)) ?>...</small>
                            </td>
                            <td>
                                <?= number_format($log['quantity_completed'], 2) ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($log['unit']) ?></small>
                            </td>
                            <td>
                                <?= number_format($log['workers_count'] ?? 0) ?> عامل
                            </td>
                            <td>
                                <?php $workHours = $log['work_hours'] ?? 0; ?>
                                <?= number_format($workHours, 1) ?> ساعة
                            </td>
                            <td>
                                <?php
                                // حساب القيمة المحسوبة = الكمية المنجزة × سعر الوحدة
                                $calculatedValue = ($log['quantity_completed'] ?? 0) * ($log['unit_price'] ?? 0);
                                ?>
                                <?= number_format($calculatedValue, 2) ?> ريال
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'draft' => 'light',
                                    'submitted' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'returned' => 'info',
                                    '' => 'secondary',
                                    null => 'secondary'
                                ];
                                $statusText = [
                                    'draft' => 'مسودة',
                                    'submitted' => 'مرسل',
                                    'approved' => 'معتمد',
                                    'rejected' => 'مرفوض',
                                    'returned' => 'مرجع',
                                    '' => 'غير محدد',
                                    null => 'غير محدد'
                                ];

                                // التأكد من وجود الحالة
                                $currentStatus = $log['status'] ?? '';
                                $badgeClass = $statusClass[$currentStatus] ?? 'secondary';
                                $statusLabel = $statusText[$currentStatus] ?? 'غير محدد';
                                ?>
                                <span class="badge badge-<?= $badgeClass ?>">
                                    <?= $statusLabel ?>
                                </span>
                                <?php if ($log['status'] === 'approved' && isset($log['approved_at']) && $log['approved_at']): ?>
                                <br>
                                <small class="text-muted">
                                    <?= date('Y-m-d H:i', strtotime($log['approved_at'])) ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['created_by_name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?= $log['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('productivity_daily_logs_edit') && 
                                              in_array($log['status'], ['draft', 'returned', 'rejected'])): ?>
                                    <a href="edit.php?id=<?= $log['id'] ?>" 
                                       class="btn btn-sm btn-outline-warning" 
                                       title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('productivity_daily_logs_submit') &&
                                              in_array($log['status'], ['draft', 'rejected', 'returned'])): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-success" 
                                            title="إرسال للاعتماد"
                                            onclick="submitForApproval(<?= $log['id'] ?>)">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('productivity_daily_logs_delete') && 
                                              in_array($log['status'], ['draft', 'returned'])): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="حذف"
                                            onclick="confirmDelete(<?= $log['id'] ?>, '<?= date('Y-m-d', strtotime($log['log_date'])) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- الترقيم -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="ترقيم الصفحات">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
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
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            التالي
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// إرسال السجل للاعتماد
function submitForApproval(id) {
    if (confirm('هل أنت متأكد من إرسال هذا السجل للاعتماد؟\n\nلن تتمكن من تعديله بعد الإرسال.')) {
        fetch('submit-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('تم إرسال السجل للاعتماد بنجاح');
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء الإرسال');
        });
    }
}

// حذف السجل
function confirmDelete(id, date) {
    if (confirm('هل أنت متأكد من حذف سجل الإنتاجية لتاريخ: ' + date + '؟\n\nهذا الإجراء لا يمكن التراجع عنه.')) {
        fetch('delete-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('تم حذف السجل بنجاح');
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء الحذف');
        });
    }
}
</script>

<style>
/* تحسين وضوح النصوص في badges الحالة */
.badge-light {
    background-color: #f8f9fa !important;
    color: #212529 !important;
    border: 1px solid #6c757d !important;
    font-weight: 600 !important;
}

.badge-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
    font-weight: 600 !important;
}

.badge-success {
    background-color: #28a745 !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}

.badge-danger {
    background-color: #dc3545 !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}

.badge-primary {
    background-color: #007bff !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}

.badge-info {
    background-color: #17a2b8 !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}

.badge-secondary {
    background-color: #6c757d !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}

/* تحسين عرض الجدول */
.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.875rem !important;
    padding: 0.375rem 0.75rem !important;
    border-radius: 0.375rem !important;
    display: inline-block !important;
    text-align: center !important;
    white-space: nowrap !important;
}

/* تحسين الويدجت */
.card.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.card.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.card.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.card.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.card.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}

.card.border-left-secondary {
    border-left: 0.25rem solid #858796 !important;
}

.card.border-left-dark {
    border-left: 0.25rem solid #5a5c69 !important;
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>


