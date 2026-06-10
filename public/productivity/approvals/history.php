<?php
/**
 * تاريخ اعتمادات الإنتاجية
 * Productivity Approvals History
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityApproval.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_approvals_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تاريخ اعتمادات الإنتاجية';
$currentPage = 'productivity-approvals';

// إنشاء كائن النموذج
$approvalModel = new ProductivityApproval();

// معالجة الفلاتر
$filters = [];
$search = $_GET['search'] ?? '';
$action = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$approver_id = $_GET['approver_id'] ?? '';

if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($action)) {
    $filters['action'] = $action;
}
if (!empty($date_from)) {
    $filters['date_from'] = $date_from;
}
if (!empty($date_to)) {
    $filters['date_to'] = $date_to;
}
if (!empty($approver_id)) {
    $filters['approver_id'] = $approver_id;
}

// الترقيم
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// جلب تاريخ الاعتمادات
$approvalHistory = $approvalModel->getAllApprovalHistory($filters, $limit, $offset);
$totalCount = $approvalModel->getApprovalHistoryCount($filters);
$totalPages = ceil($totalCount / $limit);

// جلب قائمة المعتمدين
$db = getDB();
$approversStmt = $db->prepare("
    SELECT DISTINCT u.id, u.full_name 
    FROM users u 
    JOIN productivity_approvals pa ON u.id = pa.approver_id 
    ORDER BY u.full_name
");
$approversStmt->execute();
$approvers = $approversStmt->fetchAll(PDO::FETCH_ASSOC);

// بدء تخزين المحتوى
ob_start();
?>
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-history text-primary"></i>
            تاريخ اعتمادات الإنتاجية
        </h1>
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left"></i> العودة للاعتمادات
            </a>
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
                    <label for="action" class="form-label">الإجراء</label>
                    <select class="form-control" id="action" name="action">
                        <option value="">جميع الإجراءات</option>
                        <option value="approved" <?= $action === 'approved' ? 'selected' : '' ?>>معتمد</option>
                        <option value="rejected" <?= $action === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                        <option value="returned" <?= $action === 'returned' ? 'selected' : '' ?>>مرجع</option>
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
                
                <div class="col-md-3 mb-3">
                    <label for="approver_id" class="form-label">المعتمد</label>
                    <select class="form-control" id="approver_id" name="approver_id">
                        <option value="">جميع المعتمدين</option>
                        <?php foreach ($approvers as $approver): ?>
                        <option value="<?= $approver['id'] ?>" <?= $approver_id == $approver['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($approver['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <a href="history.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- بطاقة النتائج -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                تاريخ الاعتمادات (<?= number_format($totalCount) ?> سجل)
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($approvalHistory)): ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
                <p class="text-gray-500">لا يوجد تاريخ اعتمادات</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>التاريخ</th>
                            <th>أمر العمل</th>
                            <th>بند العمل</th>
                            <th>الكمية</th>
                            <th>القيمة</th>
                            <th>الإجراء</th>
                            <th>المعتمد</th>
                            <th>التعليقات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvalHistory as $approval): ?>
                        <tr>
                            <td>
                                <strong><?= date('Y-m-d', strtotime($approval['approved_at'])) ?></strong>
                                <br>
                                <small class="text-muted"><?= date('H:i', strtotime($approval['approved_at'])) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($approval['work_order_number']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($approval['branch_name']) ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($approval['item_number']) ?></strong>
                                <br>
                                <small><?= htmlspecialchars(substr($approval['work_item_description'], 0, 40)) ?>...</small>
                            </td>
                            <td>
                                <?= number_format($approval['quantity_completed'], 2) ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($approval['unit']) ?></small>
                            </td>
                            <td>
                                <strong><?= number_format($approval['approval_value'], 2) ?> ريال</strong>
                            </td>
                            <td>
                                <?php
                                $actionClass = [
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'returned' => 'warning'
                                ];
                                $actionText = [
                                    'approved' => 'معتمد',
                                    'rejected' => 'مرفوض',
                                    'returned' => 'مرجع'
                                ];
                                ?>
                                <span class="badge badge-<?= $actionClass[$approval['action']] ?>">
                                    <?= $actionText[$approval['action']] ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($approval['approver_name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= $approval['approval_level'] ?></small>
                            </td>
                            <td>
                                <?php if ($approval['comments']): ?>
                                <small><?= htmlspecialchars(substr($approval['comments'], 0, 50)) ?><?= strlen($approval['comments']) > 50 ? '...' : '' ?></small>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
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

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
