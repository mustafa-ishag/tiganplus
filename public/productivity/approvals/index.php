<?php
/**
 * إدارة اعتمادات الإنتاجية
 * Productivity Approvals Management
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

$pageTitle = 'إدارة اعتمادات الإنتاجية';
$currentPage = 'productivity-approvals';

// إنشاء كائن النموذج
$approvalModel = new ProductivityApproval();

// معالجة الفلاتر
$filters = [];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'submitted'; // افتراضياً عرض السجلات المعلقة
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';

if (!empty($search)) {
    $filters['search'] = $search;
}
if (!empty($status)) {
    $filters['status'] = $status;
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

// جلب السجلات المعلقة للاعتماد
$pendingApprovals = $approvalModel->getPendingApprovals($_SESSION['user_id'], $filters, $limit, $offset);
$totalCount = $approvalModel->getPendingApprovalsCount($_SESSION['user_id'], $filters);
$totalPages = ceil($totalCount / $limit);

// جلب إحصائيات الاعتماد للمستخدم الحالي
$approvalStats = $approvalModel->getApprovalStatistics($_SESSION['user_id'], $filters);

// جلب قوائم الفلاتر
$db = getDB();

// جلب الفروع (إذا كان لديه صلاحية عرض جميع الفروع)
$branches = [];
if (hasPermission('productivity_daily_logs_view_all_branches')) {
    $branchesStmt = $db->prepare("SELECT id, name FROM branches WHERE status = 'active' ORDER BY name");
    $branchesStmt->execute();
    $branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// بدء تخزين المحتوى
ob_start();
?>
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-check-circle text-primary"></i>
            إدارة اعتمادات الإنتاجية
        </h1>
        <div class="btn-group" role="group">
            <a href="history.php" class="btn btn-info btn-sm">
                <i class="fas fa-history"></i> تاريخ الاعتمادات
            </a>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                السجلات المعلقة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($approvalStats['pending_count'] ?? 0) ?>
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
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                اعتمدت هذا الشهر
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($approvalStats['approved_this_month'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                رفضت هذا الشهر
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($approvalStats['rejected_this_month'] ?? 0) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times fa-2x text-gray-300"></i>
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
                                إجمالي القيمة المعتمدة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($approvalStats['total_approved_value'] ?? 0, 2) ?> ريال
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                        <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>معلق</option>
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
                <div class="col-md-3 mb-3">
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
                السجلات المعلقة للاعتماد (<?= number_format($totalCount) ?> سجل)
            </h6>
            <div class="btn-group" role="group">
                <?php if (hasPermission('productivity_approvals_approve')): ?>
                <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()">
                    <i class="fas fa-check"></i> اعتماد متعدد
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($pendingApprovals)): ?>
            <div class="text-center py-4">
                <i class="fas fa-check-circle fa-3x text-gray-300 mb-3"></i>
                <p class="text-gray-500">لا توجد سجلات معلقة للاعتماد</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <?php if (hasPermission('productivity_approvals_approve')): ?>
                            <th width="40">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <?php endif; ?>
                            <th>التاريخ</th>
                            <th>أمر العمل</th>
                            <th>بند العمل</th>
                            <th>الكمية المنجزة</th>
                            <th>القيمة المحسوبة</th>
                            <th>عدد العمال</th>
                            <th>ساعات العمل</th>
                            <th>المسجل بواسطة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingApprovals as $approval): ?>
                        <tr>
                            <?php if (hasPermission('productivity_approvals_approve')): ?>
                            <td>
                                <input type="checkbox" class="approval-checkbox" value="<?= $approval['id'] ?>">
                            </td>
                            <?php endif; ?>
                            <td>
                                <strong><?= date('Y-m-d', strtotime($approval['log_date'])) ?></strong>
                                <br>
                                <small class="text-muted"><?= date('l', strtotime($approval['log_date'])) ?></small>
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
                                <strong class="text-success"><?= number_format($approval['calculated_value'], 2) ?> ريال</strong>
                            </td>
                            <td>
                                <?= number_format($approval['workers_count']) ?> عامل
                            </td>
                            <td>
                                <?= number_format($approval['work_hours'] ?? 0, 1) ?> ساعة
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($approval['created_by_name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= date('Y-m-d H:i', strtotime($approval['created_at'])) ?></small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="../daily-logs/view.php?id=<?= $approval['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (hasPermission('productivity_approvals_approve')): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-success" 
                                            title="اعتماد"
                                            onclick="approveLog(<?= $approval['id'] ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('productivity_approvals_reject')): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger" 
                                            title="رفض"
                                            onclick="rejectLog(<?= $approval['id'] ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if (hasPermission('productivity_approvals_return')): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-warning" 
                                            title="إرجاع للتعديل"
                                            onclick="returnLog(<?= $approval['id'] ?>)">
                                        <i class="fas fa-undo"></i>
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

<!-- نافذة تأكيد الاعتماد -->
<div class="modal fade" id="approvalModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الاعتماد</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="approvalForm">
                    <input type="hidden" id="approvalLogId" name="log_id">
                    <div class="form-group">
                        <label for="approvalComments">ملاحظات الاعتماد (اختيارية)</label>
                        <textarea class="form-control" id="approvalComments" name="comments" rows="3"
                                  placeholder="أي ملاحظات حول الاعتماد"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-success" onclick="confirmApproval()">
                    <i class="fas fa-check"></i> اعتماد
                </button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة تأكيد الرفض -->
<div class="modal fade" id="rejectionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الرفض</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="rejectionForm">
                    <input type="hidden" id="rejectionLogId" name="log_id">
                    <div class="form-group">
                        <label for="rejectionComments">سبب الرفض <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectionComments" name="comments" rows="3" required
                                  placeholder="يرجى توضيح سبب رفض السجل"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" onclick="confirmRejection()">
                    <i class="fas fa-times"></i> رفض
                </button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة تأكيد الإرجاع -->
<div class="modal fade" id="returnModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إرجاع للتعديل</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="returnForm">
                    <input type="hidden" id="returnLogId" name="log_id">
                    <div class="form-group">
                        <label for="returnComments">ملاحظات الإرجاع <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="returnComments" name="comments" rows="3" required
                                  placeholder="يرجى توضيح التعديلات المطلوبة"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-warning" onclick="confirmReturn()">
                    <i class="fas fa-undo"></i> إرجاع للتعديل
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// التأكد من تحميل jQuery
document.addEventListener("DOMContentLoaded", function() {
    if (typeof $ !== "undefined") {
        initializeApprovalFunctions();
    } else {
        var checkJQuery = setInterval(function() {
            if (typeof $ !== "undefined") {
                clearInterval(checkJQuery);
                initializeApprovalFunctions();
            }
        }, 100);
    }
});

function initializeApprovalFunctions() {
    // تحديد/إلغاء تحديد جميع السجلات
    window.toggleSelectAll = function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.approval-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    };

    // اعتماد سجل واحد
    window.approveLog = function(logId) {
        document.getElementById('approvalLogId').value = logId;
        document.getElementById('approvalComments').value = '';
        $('#approvalModal').modal('show');
    };
}

    // تأكيد الاعتماد
    window.confirmApproval = function() {
        const logId = document.getElementById('approvalLogId').value;
        const comments = document.getElementById('approvalComments').value;

        fetch('approve-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                log_id: logId,
                comments: comments
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#approvalModal').modal('hide');
                alert('تم اعتماد السجل بنجاح');
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء الاعتماد');
        });
    };

    // رفض سجل
    window.rejectLog = function(logId) {
        document.getElementById('rejectionLogId').value = logId;
        document.getElementById('rejectionComments').value = '';
        $('#rejectionModal').modal('show');
    };

    // تأكيد الرفض
    window.confirmRejection = function() {
        const logId = document.getElementById('rejectionLogId').value;
        const comments = document.getElementById('rejectionComments').value;

        if (!comments.trim()) {
            alert('يرجى إدخال سبب الرفض');
            return;
        }

        fetch('reject-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                log_id: logId,
                comments: comments
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#rejectionModal').modal('hide');
                alert('تم رفض السجل');
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء الرفض');
        });
    };

    // إرجاع سجل للتعديل
    window.returnLog = function(logId) {
        document.getElementById('returnLogId').value = logId;
        document.getElementById('returnComments').value = '';
        $('#returnModal').modal('show');
    };

    // تأكيد الإرجاع
    window.confirmReturn = function() {
        const logId = document.getElementById('returnLogId').value;
        const comments = document.getElementById('returnComments').value;

        if (!comments.trim()) {
            alert('يرجى إدخال ملاحظات الإرجاع');
            return;
        }

        fetch('return-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                log_id: logId,
                comments: comments
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#returnModal').modal('hide');
                alert('تم إرجاع السجل للتعديل');
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء الإرجاع');
        });
    };

    // اعتماد متعدد
    window.bulkApprove = function() {
        const selectedCheckboxes = document.querySelectorAll('.approval-checkbox:checked');

        if (selectedCheckboxes.length === 0) {
            alert('يرجى اختيار سجل واحد على الأقل');
            return;
        }

        const logIds = Array.from(selectedCheckboxes).map(cb => cb.value);

        if (confirm(`هل أنت متأكد من اعتماد ${logIds.length} سجل؟`)) {
            fetch('bulk-approve-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    log_ids: logIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`تم اعتماد ${data.approved_count} سجل بنجاح`);
                    location.reload();
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء الاعتماد المتعدد');
            });
        }
    };
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>


