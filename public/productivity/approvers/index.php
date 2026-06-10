<?php
/**
 * إدارة المعتمدين للإنتاجية
 * Productivity Approvers Management
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/path-helper.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_approvers_manage')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'إدارة المعتمدين';
$currentPage = 'productivity-approvers';

$db = getDB();

// معالجة الإجراءات
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_approver') {
        $userId = intval($_POST['user_id'] ?? 0);
        $branchId = intval($_POST['branch_id'] ?? 0);
        $maxAmount = floatval($_POST['max_amount'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $department = $_POST['department'] ?? 'all';
        $approvalLevel = $_POST['approval_level'] ?? 'supervisor';

        if ($userId > 0) {
            try {
                // التحقق من عدم وجود المعتمد مسبقاً
                $checkStmt = $db->prepare("
                    SELECT COUNT(*) FROM productivity_approvers
                    WHERE user_id = ? AND branch_id = ? AND is_active = 1
                ");
                $checkStmt->execute([$userId, $branchId]);

                if ($checkStmt->fetchColumn() > 0) {
                    $message = 'المعتمد موجود مسبقاً لهذا الفرع';
                    $messageType = 'warning';
                } else {
                    $insertStmt = $db->prepare("
                        INSERT INTO productivity_approvers (
                            user_id, branch_id, department, approval_level,
                            max_amount_limit, can_approve_own_branch, can_approve_other_branches,
                            is_active, effective_from, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, 1, 0, ?, CURDATE(), ?, NOW())
                    ");

                    $insertStmt->execute([
                        $userId, $branchId, $department, $approvalLevel,
                        $maxAmount, $isActive, $_SESSION['user_id']
                    ]);

                    $message = 'تم إضافة المعتمد بنجاح';
                    $messageType = 'success';
                }
            } catch (Exception $e) {
                $message = 'خطأ في إضافة المعتمد: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = 'يجب اختيار المستخدم';
            $messageType = 'warning';
        }
    }
    
    elseif ($action === 'update_approver') {
        $approverId = intval($_POST['approver_id'] ?? 0);
        $maxAmount = floatval($_POST['max_amount'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($approverId > 0) {
            try {
                $updateStmt = $db->prepare("
                    UPDATE productivity_approvers
                    SET max_amount_limit = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                $updateStmt->execute([$maxAmount, $isActive, $approverId]);

                $message = 'تم تحديث بيانات المعتمد بنجاح';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'خطأ في تحديث المعتمد: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
    
    elseif ($action === 'delete_approver') {
        $approverId = intval($_POST['approver_id'] ?? 0);

        if ($approverId > 0) {
            try {
                $deleteStmt = $db->prepare("
                    UPDATE productivity_approvers
                    SET is_active = 0, updated_at = NOW()
                    WHERE id = ?
                ");

                $deleteStmt->execute([$approverId]);

                $message = 'تم حذف المعتمد بنجاح';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'خطأ في حذف المعتمد: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// جلب قائمة المعتمدين
$approversStmt = $db->prepare("
    SELECT
        pa.*,
        u.username,
        u.full_name,
        u.email,
        b.name as branch_name,
        creator.full_name as created_by_name
    FROM productivity_approvers pa
    JOIN users u ON pa.user_id = u.id
    LEFT JOIN branches b ON pa.branch_id = b.id
    LEFT JOIN users creator ON pa.created_by = creator.id
    WHERE pa.is_active = 1
    ORDER BY b.name, u.full_name
");
$approversStmt->execute();
$approvers = $approversStmt->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة المستخدمين المتاحين
$usersStmt = $db->prepare("
    SELECT id, username, full_name, email
    FROM users
    WHERE status = 'active'
    ORDER BY full_name
");
$usersStmt->execute();
$availableUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// جلب قائمة الفروع
$branchesStmt = $db->prepare("
    SELECT id, name 
    FROM branches 
    WHERE status = 'active' 
    ORDER BY name
");
$branchesStmt->execute();
$branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

// بدء تخزين المحتوى
ob_start();
?>

<!-- عنوان الصفحة -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-check text-primary"></i>
            إدارة المعتمدين
        </h1>
        <p class="text-muted mb-0">إدارة المستخدمين المخولين لاعتماد سجلات الإنتاجية</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApproverModal">
            <i class="fas fa-plus"></i> إضافة معتمد جديد
        </button>
    </div>
</div>

<!-- رسائل التنبيه -->
<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- بطاقة المعتمدين -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-users"></i>
            قائمة المعتمدين (<?= count($approvers) ?>)
        </h6>
    </div>
    <div class="card-body">
        <?php if (empty($approvers)): ?>
        <div class="text-center py-4">
            <i class="fas fa-user-plus fa-3x text-gray-300 mb-3"></i>
            <h5 class="text-muted">لا يوجد معتمدين</h5>
            <p class="text-muted">لم يتم إضافة أي معتمدين بعد</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApproverModal">
                <i class="fas fa-plus"></i> إضافة أول معتمد
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>المعتمد</th>
                        <th>الفرع</th>
                        <th>القسم</th>
                        <th>مستوى الاعتماد</th>
                        <th>الحد الأقصى للاعتماد</th>
                        <th>الحالة</th>
                        <th>تاريخ الإضافة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvers as $approver): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm me-3">
                                    <div class="avatar-title bg-primary text-white rounded-circle">
                                        <?= strtoupper(substr($approver['full_name'], 0, 2)) ?>
                                    </div>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($approver['full_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($approver['username']) ?>
                                        <?php if ($approver['email']): ?>
                                        | <?= htmlspecialchars($approver['email']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($approver['branch_name']): ?>
                                <span class="badge bg-info"><?= htmlspecialchars($approver['branch_name']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">جميع الفروع</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $departmentLabels = [
                                'all' => 'جميع الأقسام',
                                'connections' => 'التوصيلات',
                                'projects' => 'المشاريع'
                            ];
                            $departmentLabel = $departmentLabels[$approver['department']] ?? $approver['department'];
                            ?>
                            <span class="badge bg-primary"><?= htmlspecialchars($departmentLabel) ?></span>
                        </td>
                        <td>
                            <?php
                            $levelLabels = [
                                'supervisor' => 'مشرف',
                                'manager' => 'مدير',
                                'director' => 'مدير عام',
                                'general_manager' => 'المدير العام'
                            ];
                            $levelLabel = $levelLabels[$approver['approval_level']] ?? $approver['approval_level'];
                            ?>
                            <span class="badge bg-success"><?= htmlspecialchars($levelLabel) ?></span>
                        </td>
                        <td>
                            <?php
                            $maxAmount = $approver['max_amount_limit'] ?? 0;
                            if ($maxAmount > 0): ?>
                                <span class="text-success fw-bold">
                                    <?= number_format($maxAmount, 2) ?> ريال
                                </span>
                            <?php else: ?>
                                <span class="text-primary fw-bold">بلا حدود</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($approver['is_active']): ?>
                                <span class="badge bg-success">نشط</span>
                            <?php else: ?>
                                <span class="badge bg-warning">غير نشط</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small>
                                <?= date('Y-m-d', strtotime($approver['created_at'])) ?>
                                <br>
                                <span class="text-muted">
                                    بواسطة: <?= htmlspecialchars($approver['created_by_name'] ?? 'غير محدد') ?>
                                </span>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="editApprover(<?= $approver['id'] ?>, <?= $approver['max_amount_limit'] ?? 0 ?>, <?= $approver['is_active'] ?>)"
                                        title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteApprover(<?= $approver['id'] ?>, '<?= htmlspecialchars($approver['full_name']) ?>')"
                                        title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
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

<!-- Modal إضافة معتمد -->
<div class="modal fade" id="addApproverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_approver">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة معتمد جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">المستخدم <span class="text-danger">*</span></label>
                        <select class="form-control" id="user_id" name="user_id" required>
                            <option value="">اختر المستخدم</option>
                            <?php foreach ($availableUsers as $user): ?>
                            <option value="<?= $user['id'] ?>">
                                <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="branch_id" class="form-label">الفرع</label>
                        <select class="form-control" id="branch_id" name="branch_id">
                            <option value="0">جميع الفروع</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>">
                                <?= htmlspecialchars($branch['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">اتركه فارغاً للسماح بالاعتماد في جميع الفروع</small>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">القسم</label>
                        <select class="form-control" id="department" name="department" required>
                            <option value="all">جميع الأقسام</option>
                            <option value="connections">التوصيلات</option>
                            <option value="projects">المشاريع</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="approval_level" class="form-label">مستوى الاعتماد</label>
                        <select class="form-control" id="approval_level" name="approval_level" required>
                            <option value="supervisor">مشرف</option>
                            <option value="manager">مدير</option>
                            <option value="director">مدير عام</option>
                            <option value="general_manager">المدير العام</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="max_amount" class="form-label">الحد الأقصى للاعتماد (ريال)</label>
                        <input type="number" class="form-control" id="max_amount" name="max_amount"
                               min="0" step="0.01" placeholder="0.00">
                        <small class="form-text text-muted">اتركه 0 للسماح بالاعتماد بلا حدود</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                نشط
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة المعتمد</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal تعديل معتمد -->
<div class="modal fade" id="editApproverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_approver">
                <input type="hidden" name="approver_id" id="edit_approver_id">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل بيانات المعتمد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_max_amount" class="form-label">الحد الأقصى للاعتماد (ريال)</label>
                        <input type="number" class="form-control" id="edit_max_amount" name="max_amount"
                               min="0" step="0.01" placeholder="0.00">
                        <small class="form-text text-muted">اتركه 0 للسماح بالاعتماد بلا حدود</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                نشط
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">تحديث البيانات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal حذف معتمد -->
<div class="modal fade" id="deleteApproverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_approver">
                <input type="hidden" name="approver_id" id="delete_approver_id">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">حذف معتمد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>هل أنت متأكد من حذف المعتمد؟</h5>
                        <p class="text-muted" id="delete_approver_name"></p>
                        <div class="alert alert-warning">
                            <strong>تحذير:</strong> سيتم إلغاء صلاحيات الاعتماد لهذا المستخدم
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">حذف المعتمد</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // التأكد من تحميل Bootstrap
    if (typeof bootstrap !== "undefined") {
        initializeApproverFunctions();
    } else {
        var checkBootstrap = setInterval(function() {
            if (typeof bootstrap !== "undefined") {
                clearInterval(checkBootstrap);
                initializeApproverFunctions();
            }
        }, 100);
    }
});

function initializeApproverFunctions() {
    // تعديل معتمد
    window.editApprover = function(approverId, maxAmount, isActive) {
        document.getElementById('edit_approver_id').value = approverId;
        document.getElementById('edit_max_amount').value = maxAmount;
        document.getElementById('edit_is_active').checked = isActive == 1;

        var editModal = new bootstrap.Modal(document.getElementById('editApproverModal'));
        editModal.show();
    };

    // حذف معتمد
    window.deleteApprover = function(approverId, approverName) {
        document.getElementById('delete_approver_id').value = approverId;
        document.getElementById('delete_approver_name').textContent = approverName;

        var deleteModal = new bootstrap.Modal(document.getElementById('deleteApproverModal'));
        deleteModal.show();
    };
}
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
