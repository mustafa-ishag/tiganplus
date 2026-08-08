<?php
/**
 * إدارة بنود الإنتاجية
 * Productivity Work Items Management
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_work_items_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'إدارة بنود الإنتاجية';
$currentPage = 'productivity-work-items';

// إنشاء كائن النموذج
$workItemModel = new ProductivityWorkItem();

// معالجة الفلاتر
$filters = [];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';
$work_order_id = $_GET['work_order_id'] ?? '';

if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($status)) {
    $filters['status'] = $status;
}
if (!empty($priority)) {
    $filters['priority'] = $priority;
}
if (!empty($work_order_id)) {
    $filters['work_order_id'] = $work_order_id;
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
$workItems = $workItemModel->getAll($filters, $limit, $offset);
$totalCount = $workItemModel->getTotalCount($filters);
$totalPages = ceil($totalCount / $limit);

// جلب قوائم الفلاتر
$db = getDB();

// جلب أوامر العمل
$workOrdersStmt = $db->prepare("
    SELECT wo.id, wo.work_order_number, b.name as branch_name
    FROM work_orders wo
    JOIN branches b ON wo.branch_id = b.id
    WHERE wo.status = 'active'
    ORDER BY wo.work_order_number
");
$workOrdersStmt->execute();
$workOrders = $workOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

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
        LEFT JOIN branches b ON wo.branch_id = b.id
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
                <i class="fas fa-tasks text-primary"></i>
                <?php if ($selectedWorkOrder): ?>
                    بنود الإنتاجية - أمر العمل رقم <?= htmlspecialchars($selectedWorkOrder['work_order_number']) ?>
                <?php else: ?>
                    إدارة بنود الإنتاجية
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
                    <a href="<?= path('productivity/work-orders/index.php') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-right me-2"></i>
                        العودة لأوامر العمل
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <?php if (hasPermission('productivity_work_items_create') && $selectedWorkOrder): ?>
            <a href="create.php?work_order_id=<?= $selectedWorkOrder['id'] ?>&t=<?= time() ?>" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> إضافة بند إنتاجية
            </a>
            <?php endif; ?>
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
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>نشط</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                        <option value="paused" <?= $status === 'paused' ? 'selected' : '' ?>>متوقف</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                    </select>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="priority" class="form-label">الأولوية</label>
                    <select class="form-control" id="priority" name="priority">
                        <option value="">جميع الأولويات</option>
                        <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>عاجل</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>عالي</option>
                        <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>متوسط</option>
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>منخفض</option>
                    </select>
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
                    <label for="work_order_id" class="form-label">أمر العمل</label>
                    <select class="form-control" id="work_order_id" name="work_order_id">
                        <option value="">جميع أوامر العمل</option>
                        <?php foreach ($workOrders as $workOrder): ?>
                        <option value="<?= $workOrder['id'] ?>" <?= $work_order_id == $workOrder['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($workOrder['work_order_number']) ?> - <?= htmlspecialchars($workOrder['branch_name']) ?>
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
                النتائج (<?= number_format($totalCount) ?> بند)
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
            <?php if (empty($workItems)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                <p class="text-gray-500">لا توجد بنود إنتاجية مطابقة للبحث</p>
                <?php if (hasPermission('productivity_work_items_create')): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> إضافة بند إنتاجية جديد
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>أمر العمل</th>
                            <th>بند العمل</th>
                            <th>الكمية المستهدفة</th>
                            <th>المنجز</th>
                            <th>نسبة الإنجاز</th>
                            <th>القيمة الإجمالية</th>
                            <th>الحالة</th>
                            <th>الأولوية</th>
                            <th>تاريخ الانتهاء المستهدف</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workItems as $item): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($item['work_order_number']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($item['branch_name']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($item['item_number']) ?></strong>
                                <br>
                                <small><?= htmlspecialchars(substr($item['work_item_description'], 0, 50)) ?>...</small>
                            </td>
                            <td>
                                <?= number_format($item['target_quantity'], 2) ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($item['unit']) ?></small>
                            </td>
                            <td>
                                <?= number_format($item['total_completed'], 2) ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($item['unit']) ?></small>
                            </td>
                            <td>
                                <div class="progress mb-1" style="height: 20px;">
                                    <?php 
                                    $percentage = min(100, $item['completion_percentage']);
                                    $progressClass = $percentage >= 100 ? 'bg-success' : 
                                                   ($percentage >= 75 ? 'bg-info' : 
                                                   ($percentage >= 50 ? 'bg-warning' : 'bg-danger'));
                                    ?>
                                    <div class="progress-bar <?= $progressClass ?>" role="progressbar" 
                                         style="width: <?= $percentage ?>%"
                                         aria-valuenow="<?= $percentage ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?= number_format($percentage, 1) ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?= number_format($item['total_value'], 2) ?> ريال
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'completed' => 'info',
                                    'paused' => 'warning',
                                    'cancelled' => 'danger'
                                ];
                                $statusText = [
                                    'active' => 'نشط',
                                    'completed' => 'مكتمل',
                                    'paused' => 'متوقف',
                                    'cancelled' => 'ملغي'
                                ];
                                ?>
                                <span class="badge badge-<?= $statusClass[$item['status']] ?>">
                                    <?= $statusText[$item['status']] ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $priorityClass = [
                                    'urgent' => 'danger',
                                    'high' => 'warning',
                                    'medium' => 'primary',
                                    'low' => 'dark'
                                ];
                                $priorityText = [
                                    'urgent' => 'عاجل',
                                    'high' => 'عالي',
                                    'medium' => 'متوسط',
                                    'low' => 'منخفض'
                                ];
                                ?>
                                <span class="badge badge-<?= $priorityClass[$item['priority']] ?>">
                                    <?= $priorityText[$item['priority']] ?>
                                </span>
                            </td>
                            <td>
                                <?= $item['target_end_date'] ? date('Y-m-d', strtotime($item['target_end_date'])) : '-' ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="view.php?id=<?= $item['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('productivity_work_items_edit')): ?>
                                    <a href="edit.php?id=<?= $item['id'] ?>" 
                                       class="btn btn-sm btn-outline-warning" 
                                       title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('productivity_daily_logs_create')): ?>
                                    <a href="../daily-logs/create.php?work_item_id=<?= $item['id'] ?>" 
                                       class="btn btn-sm btn-success" 
                                       title="تسجيل إنتاجية يومية">
                                        <i class="fas fa-plus me-1"></i> تسجيل إنتاجية
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('productivity_work_items_delete')): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="حذف"
                                            onclick="confirmDelete(<?= $item['id'] ?>, '<?= htmlspecialchars($item['work_order_number']) ?>')">
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

<?php if (hasPermission('productivity_work_items_delete')): ?>
<script>
function confirmDelete(id, workOrderNumber) {
    if (confirm('هل أنت متأكد من حذف بند الإنتاجية لأمر العمل: ' + workOrderNumber + '؟\n\nهذا الإجراء لا يمكن التراجع عنه.')) {
        // إرسال طلب الحذف
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
                alert('تم حذف البند بنجاح');
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
<?php endif; ?>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

