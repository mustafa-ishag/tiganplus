<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'إدارة البيانات المرجعية';
$currentPage = 'reference-data';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'البيانات المرجعية', 'url' => 'reference-data/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

try {
    $db = getDB();

    // جلب الجهات الحالية
    $stmt = $db->query("
        SELECT ce.*, 
               COUNT(wo.id) as work_orders_count
        FROM current_entities ce
        LEFT JOIN work_orders wo ON ce.id = wo.current_entity_id
        GROUP BY ce.id
        ORDER BY ce.name
    ");
    $currentEntities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب مراحل الاعتماد من الجدول الجديد
    $approvalStages = $db->query("
        SELECT * FROM approval_stages
        ORDER BY stage_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات مراحل الاعتماد من المستخلصات الجزئية
    $approvalStagesStats = $db->query("
        SELECT
            pe.approval_stage,
            COUNT(*) as count
        FROM partial_extracts pe
        GROUP BY pe.approval_stage
        ORDER BY
            (SELECT stage_order FROM approval_stages WHERE stage_key = pe.approval_stage)
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $currentEntities = [];
    $approvalStages = [];
    $approvalStagesStats = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-cogs text-primary me-2"></i>
                إدارة البيانات المرجعية
            </h1>
            <p class="text-muted mb-0">إدارة الجهات الحالية ومراحل الاعتماد</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="referenceDataTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="entities-tab" data-bs-toggle="tab" data-bs-target="#entities"
                type="button" role="tab">
                <i class="fas fa-building me-2"></i>
                الجهات الحالية
                <span class="badge bg-primary ms-2"><?= count($currentEntities) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="approval-stages-tab" data-bs-toggle="tab" data-bs-target="#approval-stages"
                type="button" role="tab">
                <i class="fas fa-check-circle me-2"></i>
                مراحل الاعتماد
                <span class="badge bg-success ms-2"><?= count($approvalStages) ?></span>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="referenceDataTabContent">
        <!-- الجهات الحالية -->
        <div class="tab-pane fade show active" id="entities" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-building me-2"></i>
                                قائمة الجهات الحالية
                            </h5>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openEntityModal()">
                                <i class="fas fa-plus me-1"></i>
                                إضافة جهة جديدة
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="entitiesTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>الاسم</th>
                                            <th>الكود</th>
                                            <th>الوصف</th>
                                            <th>أوامر العمل</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($currentEntities as $entity): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($entity['name']) ?></strong>
                                                </td>
                                                <td>
                                                    <?php if ($entity['code']): ?>
                                                        <span
                                                            class="badge bg-info"><?= htmlspecialchars($entity['code']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($entity['description'] ?? 'لا يوجد وصف') ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= $entity['work_orders_count'] ?> أمر
                                                    </span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= $entity['is_active'] ? 'success' : 'danger' ?>">
                                                        <?= $entity['is_active'] ? 'نشط' : 'غير نشط' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="editEntity(<?= $entity['id'] ?>)" title="تعديل">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($entity['work_orders_count'] == 0): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteEntity(<?= $entity['id'] ?>, '<?= htmlspecialchars($entity['name']) ?>')"
                                                                title="حذف">
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
                        </div>
                    </div>
                </div>

                <!-- إحصائيات الجهات -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                إحصائيات الجهات
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-primary mb-1"><?= count($currentEntities) ?></h4>
                                        <small class="text-muted">إجمالي الجهات</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h4 class="text-success mb-1">
                                            <?= count(array_filter($currentEntities, fn($e) => $e['is_active'])) ?>
                                        </h4>
                                        <small class="text-muted">جهات نشطة</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="border rounded p-3">
                                        <h4 class="text-info mb-1">
                                            <?= array_sum(array_column($currentEntities, 'work_orders_count')) ?>
                                        </h4>
                                        <small class="text-muted">إجمالي أوامر العمل</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- مراحل الاعتماد -->
        <div class="tab-pane fade" id="approval-stages" role="tabpanel">
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                قائمة مراحل الاعتماد
                            </h5>
                            <button type="button" class="btn btn-success btn-sm" onclick="openStageModal()">
                                <i class="fas fa-plus me-1"></i>
                                إضافة مرحلة جديدة
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="stagesTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>الترتيب</th>
                                            <th>المرحلة</th>
                                            <th>المفتاح</th>
                                            <th>الوصف</th>
                                            <th>عدد المستخلصات</th>
                                            <th>اللون</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($approvalStages as $stage):
                                            $count = 0;
                                            foreach ($approvalStagesStats as $stat) {
                                                if ($stat['approval_stage'] === $stage['stage_key']) {
                                                    $count = $stat['count'];
                                                    break;
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-secondary"><?= $stage['stage_order'] ?></span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($stage['stage_name']) ?></strong>
                                                    <?php if ($stage['is_final']): ?>
                                                        <span class="badge bg-warning ms-1">نهائي</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code
                                                        class="text-muted"><?= htmlspecialchars($stage['stage_key']) ?></code>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($stage['stage_description'] ?? 'لا يوجد وصف') ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $stage['stage_color'] ?>"><?= $count ?>
                                                        مستخلص</span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= $stage['stage_color'] ?>"><?= $stage['stage_color'] ?></span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-<?= $stage['is_active'] ? 'success' : 'danger' ?>">
                                                        <?= $stage['is_active'] ? 'نشط' : 'غير نشط' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="editStage(<?= $stage['id'] ?>)" title="تعديل">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($count == 0): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteStage(<?= $stage['id'] ?>, '<?= htmlspecialchars($stage['stage_name']) ?>')"
                                                                title="حذف">
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
                        </div>
                    </div>
                </div>

                <!-- إحصائيات مراحل الاعتماد -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                توزيع المستخلصات
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <h4 class="text-success mb-1"><?= count($approvalStages) ?></h4>
                                        <small class="text-muted">إجمالي المراحل</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <h4 class="text-info mb-1">
                                            <?= count(array_filter($approvalStages, fn($s) => $s['is_active'])) ?>
                                        </h4>
                                        <small class="text-muted">مراحل نشطة</small>
                                    </div>
                                </div>
                            </div>

                            <?php
                            $totalExtracts = array_sum(array_column($approvalStagesStats, 'count'));
                            foreach ($approvalStages as $stage):
                                $count = 0;
                                foreach ($approvalStagesStats as $stat) {
                                    if ($stat['approval_stage'] === $stage['stage_key']) {
                                        $count = $stat['count'];
                                        break;
                                    }
                                }
                                $percentage = $totalExtracts > 0 ? round(($count / $totalExtracts) * 100, 1) : 0;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="fw-bold"><?= htmlspecialchars($stage['stage_name']) ?></small>
                                        <small class="text-muted"><?= $count ?> (<?= $percentage ?>%)</small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $stage['stage_color'] ?>"
                                            style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة/تعديل مرحلة الاعتماد -->
<div class="modal fade" id="stageModal" tabindex="-1" aria-labelledby="stageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stageModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="stageModalTitle">إضافة مرحلة اعتماد جديدة</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="stageFormError" class="alert alert-danger d-none" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="stageFormErrorMsg"></span>
                </div>
                <form id="stageForm">
                    <input type="hidden" id="stageId" name="stage_id">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="stageName" class="form-label">
                                    اسم المرحلة <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="stageName" name="stage_name" required
                                    placeholder="مثال: مراجعة الجودة">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stageOrder" class="form-label">
                                    ترتيب المرحلة <span class="text-danger">*</span>
                                </label>
                                <input type="number" class="form-control" id="stageOrder" name="stage_order" min="1"
                                    max="99" required placeholder="1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="stageKey" class="form-label">
                                    مفتاح المرحلة <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="stageKey" name="stage_key" required
                                    placeholder="quality_review" pattern="[a-z_]+" maxlength="50">
                                <div class="form-text">أحرف إنجليزية صغيرة وشرطة سفلية فقط</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stageColor" class="form-label">لون المرحلة</label>
                                <select class="form-select" id="stageColor" name="stage_color">
                                    <option value="primary">أزرق (Primary)</option>
                                    <option value="secondary">رمادي (Secondary)</option>
                                    <option value="success">أخضر (Success)</option>
                                    <option value="danger">أحمر (Danger)</option>
                                    <option value="warning">برتقالي (Warning)</option>
                                    <option value="info">سماوي (Info)</option>
                                    <option value="dark">أسود (Dark)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="stageDescription" class="form-label">وصف المرحلة</label>
                        <textarea class="form-control" id="stageDescription" name="stage_description" rows="3"
                            placeholder="وصف مفصل لهذه المرحلة ومتطلباتها"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="stageActive" name="is_active"
                                    checked>
                                <label class="form-check-label" for="stageActive">
                                    مرحلة نشطة
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="stageFinal" name="is_final">
                                <label class="form-check-label" for="stageFinal">
                                    مرحلة نهائية
                                </label>
                                <div class="form-text">المرحلة النهائية تعني انتهاء عملية الاعتماد</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </button>
                <button type="button" class="btn btn-success" onclick="saveStage()">
                    <i class="fas fa-save me-1"></i>
                    حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة/تعديل الجهة -->
<div class="modal fade" id="entityModal" tabindex="-1" aria-labelledby="entityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entityModalLabel">
                    <i class="fas fa-building me-2"></i>
                    <span id="modalTitle">إضافة جهة جديدة</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="entityForm">
                    <input type="hidden" id="entityId" name="entity_id">

                    <div class="mb-3">
                        <label for="entityName" class="form-label">
                            اسم الجهة <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="entityName" name="name" required
                            placeholder="مثال: شركة الكهرباء السعودية">
                    </div>

                    <div class="mb-3">
                        <label for="entityCode" class="form-label">كود الجهة</label>
                        <input type="text" class="form-control" id="entityCode" name="code" maxlength="10"
                            placeholder="مثال: SEC">
                        <div class="form-text">كود مختصر للجهة (اختياري)</div>
                    </div>

                    <div class="mb-3">
                        <label for="entityDescription" class="form-label">الوصف</label>
                        <textarea class="form-control" id="entityDescription" name="description" rows="3"
                            placeholder="وصف مختصر للجهة"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="entityActive" name="is_active" checked>
                            <label class="form-check-label" for="entityActive">
                                جهة نشطة
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </button>
                <button type="button" class="btn btn-primary" onclick="saveEntity()">
                    <i class="fas fa-save me-1"></i>
                    حفظ
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<script>
    $(document).ready(function () {
        // تهيئة DataTable للجهات
        if (!$.fn.DataTable.isDataTable('#entitiesTable')) {
            $('#entitiesTable').DataTable({
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
                "order": [[0, 'asc']],
                "columnDefs": [
                    { "orderable": false, "targets": -1 }
                ]
            });
        }

        // تهيئة DataTable لمراحل الاعتماد
        if (!$.fn.DataTable.isDataTable('#stagesTable')) {
            $('#stagesTable').DataTable({
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
                "order": [[0, 'asc']],
                "columnDefs": [
                    { "orderable": false, "targets": -1 }
                ]
            });
        }
    });

    // فتح مودال إضافة جهة جديدة
    function openEntityModal() {
        $('#entityForm')[0].reset();
        $('#entityId').val('');
        $('#modalTitle').text('إضافة جهة جديدة');
        $('#entityActive').prop('checked', true);
        $('#entityModal').modal('show');
    }

    // تعديل جهة
    function editEntity(entityId) {
        // جلب بيانات الجهة
        $.ajax({
            url: 'get-entity-ajax.php',
            type: 'GET',
            data: { id: entityId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const entity = response.data;
                    $('#entityId').val(entity.id);
                    $('#entityName').val(entity.name);
                    $('#entityCode').val(entity.code || '');
                    $('#entityDescription').val(entity.description || '');
                    $('#entityActive').prop('checked', entity.is_active == 1);
                    $('#modalTitle').text('تعديل الجهة');
                    $('#entityModal').modal('show');
                } else {
                    showAlert('error', response.message || 'حدث خطأ أثناء جلب بيانات الجهة');
                }
            },
            error: function () {
                showAlert('error', 'حدث خطأ في الاتصال بالخادم');
            }
        });
    }

    // حفظ الجهة
    function saveEntity() {
        // التحقق من صحة البيانات
        const name = $('#entityName').val().trim();
        if (!name) {
            showAlert('error', 'اسم الجهة مطلوب');
            $('#entityName').focus();
            return;
        }

        const code = $('#entityCode').val().trim();
        if (code && code.length > 10) {
            showAlert('error', 'كود الجهة يجب أن يكون أقل من 10 أحرف');
            $('#entityCode').focus();
            return;
        }

        const form = $('#entityForm')[0];
        const formData = new FormData(form);

        // تحويل checkbox إلى قيمة رقمية
        formData.set('is_active', $('#entityActive').is(':checked') ? '1' : '0');

        const isEdit = $('#entityId').val() !== '';
        const url = isEdit ? 'update-entity-ajax.php' : 'create-entity-ajax.php';

        // تعطيل الزر أثناء الحفظ
        const saveBtn = $('.modal-footer .btn-primary');
        const originalText = saveBtn.html();
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح!',
                        text: response.message,
                        confirmButtonText: 'موافق',
                        confirmButtonColor: '#176cb4'
                    }).then(() => {
                        $('#entityModal').modal('hide');
                        location.reload();
                    });
                } else {
                    showAlert('error', response.message || 'حدث خطأ أثناء حفظ البيانات');
                }
            },
            error: function (xhr) {
                let errorMessage = 'حدث خطأ في الاتصال بالخادم';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showAlert('error', errorMessage);
            },
            complete: function () {
                // إعادة تفعيل الزر
                saveBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    // حذف جهة
    function deleteEntity(entityId, entityName) {
        Swal.fire({
            title: 'تأكيد الحذف',
            html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                <p>هل أنت متأكد من حذف الجهة:</p>
                <strong class="text-danger">"${entityName}"</strong>
                <p class="text-muted mt-2">لا يمكن التراجع عن هذا الإجراء!</p>
            </div>
        `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-1"></i>نعم، احذف',
            cancelButtonText: '<i class="fas fa-times me-1"></i>إلغاء',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار مؤشر التحميل
                Swal.fire({
                    title: 'جاري الحذف...',
                    text: 'يرجى الانتظار',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'delete-entity-ajax.php',
                    type: 'POST',
                    data: { id: entityId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف!',
                                text: response.message,
                                confirmButtonText: 'موافق',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'فشل الحذف!',
                                text: response.message || 'حدث خطأ أثناء الحذف',
                                confirmButtonText: 'موافق',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function (xhr) {
                        let errorMessage = 'حدث خطأ في الاتصال بالخادم';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الاتصال!',
                            text: errorMessage,
                            confirmButtonText: 'موافق',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    }

    // === وظائف مراحل الاعتماد ===

    // فتح مودال إضافة مرحلة جديدة
    function openStageModal() {
        $('#stageForm')[0].reset();
        $('#stageId').val('');
        $('#stageModalTitle').text('إضافة مرحلة اعتماد جديدة');
        $('#stageActive').prop('checked', true);
        $('#stageFinal').prop('checked', false);
        $('#stageFormError').addClass('d-none');
        $('#stageFormErrorMsg').text('');
        $('#stageModal').modal('show');
    }

    // تعديل مرحلة
    function editStage(stageId) {
        $.ajax({
            url: 'get-stage-ajax.php',
            type: 'GET',
            data: { id: stageId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const stage = response.data;
                    $('#stageId').val(stage.id);
                    $('#stageName').val(stage.stage_name);
                    $('#stageKey').val(stage.stage_key);
                    $('#stageDescription').val(stage.stage_description || '');
                    $('#stageOrder').val(stage.stage_order);
                    $('#stageColor').val(stage.stage_color);
                    $('#stageActive').prop('checked', stage.is_active == 1);
                    $('#stageFinal').prop('checked', stage.is_final == 1);
                    $('#stageModalTitle').text('تعديل مرحلة الاعتماد');
                    $('#stageModal').modal('show');
                } else {
                    showAlert('error', response.message || 'حدث خطأ أثناء جلب بيانات المرحلة');
                }
            },
            error: function () {
                showAlert('error', 'حدث خطأ في الاتصال بالخادم');
            }
        });
    }

    // حفظ المرحلة
    function saveStage() {
        // إخفاء رسائل الخطأ السابقة
        $('#stageFormError').addClass('d-none');

        function showStageError(msg) {
            $('#stageFormErrorMsg').text(msg);
            $('#stageFormError').removeClass('d-none');
            $('#stageFormError')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // التحقق من صحة البيانات
        const stageName = $('#stageName').val().trim();
        const stageKey = $('#stageKey').val().trim();
        const stageOrder = $('#stageOrder').val();

        if (!stageName) {
            showStageError('اسم المرحلة مطلوب');
            $('#stageName').focus();
            return;
        }

        if (!stageKey) {
            showStageError('مفتاح المرحلة مطلوب');
            $('#stageKey').focus();
            return;
        }

        if (!stageOrder || stageOrder < 1) {
            showStageError('ترتيب المرحلة مطلوب ويجب أن يكون أكبر من 0');
            $('#stageOrder').focus();
            return;
        }

        // التحقق من صحة مفتاح المرحلة
        const keyPattern = /^[a-z_]+$/;
        if (!keyPattern.test(stageKey)) {
            showStageError('مفتاح المرحلة يجب أن يحتوي على أحرف إنجليزية صغيرة وشرطة سفلية فقط');
            $('#stageKey').focus();
            return;
        }

        const form = $('#stageForm')[0];
        const formData = new FormData(form);

        // تحويل checkboxes إلى قيم رقمية
        formData.set('is_active', $('#stageActive').is(':checked') ? '1' : '0');
        formData.set('is_final', $('#stageFinal').is(':checked') ? '1' : '0');

        const isEdit = $('#stageId').val() !== '';
        const url = isEdit ? 'update-stage-ajax.php' : 'create-stage-ajax.php';

        // تعطيل الزر أثناء الحفظ
        const saveBtn = $('#stageModal .modal-footer .btn-success');
        const originalText = saveBtn.html();
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    try {
                        Swal.fire({
                            icon: 'success',
                            title: 'تم بنجاح!',
                            text: response.message,
                            confirmButtonText: 'موافق',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            $('#stageModal').modal('hide');
                            location.reload();
                        });
                    } catch (e) {
                        alert('تم بنجاح!\n' + response.message);
                        $('#stageModal').modal('hide');
                        location.reload();
                    }
                } else {
                    showStageError(response.message || 'حدث خطأ أثناء حفظ البيانات');
                }
            },
            error: function (xhr) {
                let errorMessage = 'حدث خطأ في الاتصال بالخادم';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showStageError(errorMessage);
            },
            complete: function () {
                // إعادة تفعيل الزر دائماً
                saveBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    // حذف مرحلة
    function deleteStage(stageId, stageName) {
        Swal.fire({
            title: 'تأكيد الحذف',
            html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                <p>هل أنت متأكد من حذف مرحلة الاعتماد:</p>
                <strong class="text-danger">"${stageName}"</strong>
                <p class="text-muted mt-2">لا يمكن التراجع عن هذا الإجراء!</p>
            </div>
        `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash me-1"></i>نعم، احذف',
            cancelButtonText: '<i class="fas fa-times me-1"></i>إلغاء',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                // إظهار مؤشر التحميل
                Swal.fire({
                    title: 'جاري الحذف...',
                    text: 'يرجى الانتظار',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'delete-stage-ajax.php',
                    type: 'POST',
                    data: { id: stageId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'تم الحذف!',
                                text: response.message,
                                confirmButtonText: 'موافق',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'فشل الحذف!',
                                text: response.message || 'حدث خطأ أثناء الحذف',
                                confirmButtonText: 'موافق',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function (xhr) {
                        let errorMessage = 'حدث خطأ في الاتصال بالخادم';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'خطأ في الاتصال!',
                            text: errorMessage,
                            confirmButtonText: 'موافق',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    }
</script>

<style>
    /* تحسينات إضافية للصفحة */
    .nav-tabs .nav-link {
        border-radius: 0.5rem 0.5rem 0 0;
        margin-right: 0.25rem;
    }

    .nav-tabs .nav-link.active {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .nav-tabs .nav-link:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: none;
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .table th {
        background-color: #343a40;
        color: white;
        border: none;
        font-weight: 600;
        text-align: center;
    }

    .table td {
        vertical-align: middle;
        text-align: center;
    }

    .badge {
        font-size: 0.75em;
    }

    .progress {
        background-color: #e9ecef;
    }

    .btn-group .btn {
        margin: 0 1px;
    }

    .modal-header {
        background-color: var(--primary-color);
        color: white;
    }

    .modal-header .btn-close {
        filter: invert(1);
    }

    .form-label {
        font-weight: 600;
        color: #495057;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
    }

    .text-primary {
        color: var(--primary-color) !important;
    }

    .bg-primary {
        background-color: var(--primary-color) !important;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background-color: var(--secondary-color);
        border-color: var(--secondary-color);
    }

    /* تحسين مظهر الإحصائيات */
    .border.rounded {
        border: 2px solid #e9ecef !important;
        transition: all 0.3s ease;
    }

    .border.rounded:hover {
        border-color: var(--primary-color) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* تحسين مظهر الجدول */
    .table-hover tbody tr:hover {
        background-color: rgba(44, 90, 160, 0.05);
    }

    /* تحسين مظهر التبويبات */
    .tab-content {
        background-color: white;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 0.5rem 0.5rem;
        padding: 1.5rem;
    }

    /* تحسين مظهر التنبيهات */
    .alert {
        border: none;
        border-radius: 0.5rem;
    }

    .alert-info {
        background-color: #e7f3ff;
        color: #0c5460;
    }
</style>