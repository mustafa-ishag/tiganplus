<?php
/**
 * صفحة إدارة تعيين المعتمدين وخطوات الاعتماد
 * Approval Assignments & Steps Management Page
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/ApprovalAssignment.php';

$pageTitle = 'إدارة تعيين المعتمدين';
$currentPage = 'admin';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '../dashboard.php'],
    ['title' => 'إدارة النظام', 'url' => 'index.php'],
    ['title' => 'تعيين المعتمدين', 'url' => '']
];

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!hasPermission('manage_system') && !hasPermission('system_admin') && !hasPermission('manage_users')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لإدارة تعيين المعتمدين';
    header('Location: index.php');
    exit();
}

try {
    $db = getDB();
    $approvalModel = new ApprovalAssignment();
    
    $assignments = $approvalModel->getAllAssignments();
    $steps = $approvalModel->getAllSteps();
    $users = $db->query("SELECT id, full_name, username FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();
    $branches = $db->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    $workOrders = $db->query("SELECT id, work_order_number FROM work_orders WHERE status = 'active' ORDER BY work_order_number")->fetchAll();
    $stats = $approvalModel->getAssignmentStats();
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $assignments = [];
    $steps = [];
    $users = [];
    $branches = [];
    $workOrders = [];
    $stats = [];
}

ob_start();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-2 text-gray-800">
                <i class="fas fa-user-check me-2"></i>
                إدارة تعيين المعتمدين
            </h1>
            <p class="text-muted mb-0">إدارة خطوات الاعتماد وتعيين المستخدمين المخولين للموافقة</p>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#addStepModal">
                <i class="fas fa-layer-group me-1"></i> إضافة خطوة
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                <i class="fas fa-plus me-1"></i> إضافة تعيين
            </button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ======== قسم خطوات الاعتماد ======== -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>خطوات الاعتماد (مسار الموافقة)</h6>
            <span class="badge bg-primary"><?= count($steps) ?> خطوة</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="60">الترتيب</th>
                            <th>اسم الخطوة</th>
                            <th>المفتاح</th>
                            <th>الوصف</th>
                            <th>نهائية؟</th>
                            <th>الحالة</th>
                            <th width="150">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($steps)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">لا توجد خطوات اعتماد</td></tr>
                        <?php else: ?>
                            <?php foreach ($steps as $step): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= $step['step_order'] ?></span></td>
                                    <td><strong><?= htmlspecialchars($step['step_name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($step['step_key']) ?></code></td>
                                    <td class="text-muted small"><?= htmlspecialchars($step['description'] ?? '') ?></td>
                                    <td>
                                        <?php if ($step['is_final']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> نعم</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">لا</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                <?= $step['is_active'] ? 'checked' : '' ?>
                                                onchange="toggleStep(<?= $step['id'] ?>, this.checked)">
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editStep(<?= htmlspecialchars(json_encode($step)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteStep(<?= $step['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======== قسم تعيين المعتمدين ======== -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-users me-2"></i>تعيينات المعتمدين</h6>
            <span class="badge bg-primary"><?= count($assignments) ?> تعيين</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>خطوة الاعتماد</th>
                            <th>المعتمد</th>
                            <th>النطاق</th>
                            <th>الأولوية</th>
                            <th>ملاحظات</th>
                            <th>الحالة</th>
                            <th width="150">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignments)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">لا توجد تعيينات</td></tr>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-info"><?= $assignment['step_order'] ?? '-' ?></span>
                                        <?= htmlspecialchars($assignment['step_name'] ?? $assignment['approval_type']) ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($assignment['approver_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($assignment['approver_username']) ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $scopeLabels = ['global' => 'عام', 'branch' => 'فرع', 'work_order' => 'أمر عمل'];
                                        $scopeLabel = $scopeLabels[$assignment['scope_type']] ?? $assignment['scope_type'];
                                        ?>
                                        <span class="badge bg-<?= $assignment['scope_type'] === 'global' ? 'success' : ($assignment['scope_type'] === 'branch' ? 'warning' : 'info') ?>">
                                            <?= $scopeLabel ?>
                                        </span>
                                        <?php if ($assignment['scope_type'] !== 'global'): ?>
                                            <br><small><?= htmlspecialchars($assignment['scope_name'] ?? '') ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= $assignment['priority'] ?></span></td>
                                    <td class="text-muted small"><?= htmlspecialchars($assignment['notes'] ?? '') ?></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                <?= $assignment['is_active'] ? 'checked' : '' ?>
                                                onchange="toggleAssignment(<?= $assignment['id'] ?>, this.checked)">
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?= $assignment['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?= $assignment['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: إضافة خطوة -->
<div class="modal fade" id="addStepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>إضافة خطوة اعتماد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStepForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الخطوة <span class="text-danger">*</span></label>
                        <input type="text" name="step_name" class="form-control" required placeholder="مثل: اعتماد المدير العام">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المفتاح الفريد <span class="text-danger">*</span></label>
                        <input type="text" name="step_key" class="form-control" required placeholder="مثل: general_manager" dir="ltr" pattern="[a-z_]+">
                        <small class="text-muted">أحرف إنجليزية صغيرة وشرطة سفلية فقط</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_final" id="addStepIsFinal">
                        <label class="form-check-label" for="addStepIsFinal">خطوة نهائية (يتم خصم المخزون عند اعتمادها)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: تعديل خطوة -->
<div class="modal fade" id="editStepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل خطوة اعتماد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStepForm">
                <input type="hidden" name="step_id" id="editStepId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الخطوة</label>
                        <input type="text" name="step_name" id="editStepName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المفتاح الفريد</label>
                        <input type="text" name="step_key" id="editStepKey" class="form-control" required dir="ltr" pattern="[a-z_]+">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" id="editStepDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="step_order" id="editStepOrder" class="form-control" min="1">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_final" id="editStepIsFinal">
                        <label class="form-check-label" for="editStepIsFinal">خطوة نهائية</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: إضافة تعيين -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>إضافة تعيين معتمد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAssignmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">خطوة الاعتماد <span class="text-danger">*</span></label>
                        <select name="step_id" class="form-select" required>
                            <option value="">-- اختر الخطوة --</option>
                            <?php foreach ($steps as $step): ?>
                                <option value="<?= $step['id'] ?>"><?= $step['step_order'] ?>. <?= htmlspecialchars($step['step_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المعتمد <span class="text-danger">*</span></label>
                        <select name="approver_user_id" class="form-select" required>
                            <option value="">-- اختر المعتمد --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نوع النطاق</label>
                        <select name="scope_type" id="addScopeType" class="form-select" onchange="toggleScopeField('add')">
                            <option value="global">عام (جميع الطلبات)</option>
                            <option value="branch">فرع محدد</option>
                            <option value="work_order">أمر عمل محدد</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="addScopeIdGroup">
                        <label class="form-label" id="addScopeIdLabel">الفرع/أمر العمل</label>
                        <select name="scope_id" id="addScopeId" class="form-select"></select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الأولوية</label>
                            <input type="number" name="priority" class="form-control" value="1" min="1" max="100">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: تعديل تعيين -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>تعديل تعيين</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAssignmentForm">
                <input type="hidden" name="assignment_id" id="editAssignmentId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">خطوة الاعتماد <span class="text-danger">*</span></label>
                        <select name="step_id" id="editStepIdAssignment" class="form-select" required>
                            <?php foreach ($steps as $step): ?>
                                <option value="<?= $step['id'] ?>"><?= $step['step_order'] ?>. <?= htmlspecialchars($step['step_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المعتمد <span class="text-danger">*</span></label>
                        <select name="approver_user_id" id="editApproverUserId" class="form-select" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">نوع النطاق</label>
                        <select name="scope_type" id="editScopeType" class="form-select" onchange="toggleScopeField('edit')">
                            <option value="global">عام</option>
                            <option value="branch">فرع</option>
                            <option value="work_order">أمر عمل</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="editScopeIdGroup">
                        <label class="form-label" id="editScopeIdLabel">الفرع/أمر العمل</label>
                        <select name="scope_id" id="editScopeId" class="form-select"></select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الأولوية</label>
                            <input type="number" name="priority" id="editPriority" class="form-control" value="1" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const branches = <?= json_encode($branches) ?>;
const workOrders = <?= json_encode($workOrders) ?>;

// ============ إدارة الخطوات ============
document.getElementById('addStepForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add_step');
    formData.set('is_final', document.getElementById('addStepIsFinal').checked ? '1' : '0');
    submitForm(formData);
});

document.getElementById('editStepForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'edit_step');
    formData.set('is_final', document.getElementById('editStepIsFinal').checked ? '1' : '0');
    submitForm(formData);
});

function editStep(step) {
    document.getElementById('editStepId').value = step.id;
    document.getElementById('editStepName').value = step.step_name;
    document.getElementById('editStepKey').value = step.step_key;
    document.getElementById('editStepDescription').value = step.description || '';
    document.getElementById('editStepOrder').value = step.step_order;
    document.getElementById('editStepIsFinal').checked = step.is_final == 1;
    new bootstrap.Modal(document.getElementById('editStepModal')).show();
}

function deleteStep(stepId) {
    if (confirm('هل أنت متأكد من حذف هذه الخطوة؟')) {
        const formData = new FormData();
        formData.append('action', 'delete_step');
        formData.append('step_id', stepId);
        submitForm(formData);
    }
}

function toggleStep(stepId, isActive) {
    const formData = new FormData();
    formData.append('action', 'toggle_step');
    formData.append('step_id', stepId);
    formData.append('is_active', isActive ? '1' : '0');
    submitForm(formData, false);
}

// ============ إدارة التعيينات ============
document.getElementById('addAssignmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'add');
    submitForm(formData);
});

document.getElementById('editAssignmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'edit');
    submitForm(formData);
});

function editAssignment(id) {
    fetch(`approval-assignments-ajax.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const a = data.assignment;
                document.getElementById('editAssignmentId').value = a.id;
                document.getElementById('editStepIdAssignment').value = a.step_id;
                document.getElementById('editApproverUserId').value = a.approver_user_id;
                document.getElementById('editScopeType').value = a.scope_type;
                document.getElementById('editPriority').value = a.priority;
                document.getElementById('editNotes').value = a.notes || '';
                toggleScopeField('edit');
                if (a.scope_id) {
                    setTimeout(() => { document.getElementById('editScopeId').value = a.scope_id; }, 100);
                }
                new bootstrap.Modal(document.getElementById('editAssignmentModal')).show();
            } else {
                alert(data.message);
            }
        });
}

function deleteAssignment(id) {
    if (confirm('هل أنت متأكد من حذف هذا التعيين؟')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        submitForm(formData);
    }
}

function toggleAssignment(id, isActive) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    formData.append('is_active', isActive ? '1' : '0');
    submitForm(formData, false);
}

// ============ دوال مساعدة ============
function toggleScopeField(prefix) {
    const scopeType = document.getElementById(prefix + 'ScopeType').value;
    const group = document.getElementById(prefix + 'ScopeIdGroup');
    const select = document.getElementById(prefix + 'ScopeId');
    const label = document.getElementById(prefix + 'ScopeIdLabel');
    
    if (scopeType === 'global') {
        group.classList.add('d-none');
        return;
    }
    
    group.classList.remove('d-none');
    select.innerHTML = '';
    
    if (scopeType === 'branch') {
        label.textContent = 'الفرع';
        branches.forEach(b => {
            select.innerHTML += `<option value="${b.id}">${b.name}</option>`;
        });
    } else {
        label.textContent = 'أمر العمل';
        workOrders.forEach(wo => {
            select.innerHTML += `<option value="${wo.id}">${wo.work_order_number}</option>`;
        });
    }
}

function submitForm(formData, reload = true) {
    fetch('approval-assignments-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (reload) {
                location.reload();
            }
        } else {
            alert('خطأ: ' + data.message);
        }
    })
    .catch(error => {
        alert('خطأ في الاتصال');
        console.error(error);
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
?>
