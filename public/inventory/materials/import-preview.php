<?php
/**
 * صفحة معاينة استيراد المواد
 * Materials Import Preview Page
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'معاينة استيراد المواد';
$currentPage = 'inventory';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من وجود بيانات المعاينة
if (!isset($_SESSION['import_preview'])) {
    $_SESSION['error_message'] = 'لا توجد بيانات للمعاينة. يرجى اختيار ملف للاستيراد أولاً.';
    header('Location: import-export.php');
    exit();
}

$preview = $_SESSION['import_preview'];
$filename = $_SESSION['import_filename'] ?? 'ملف غير معروف';

$breadcrumbs = [
    ['title' => 'لوحة التحكم', 'url' => 'dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'المواد', 'url' => 'inventory/materials/index.php'],
    ['title' => 'استيراد وتصدير', 'url' => 'inventory/materials/import-export.php'],
    ['title' => 'معاينة الاستيراد', 'url' => '']
];

// تحديد المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h4 mb-1">
                <i class="fas fa-eye me-2"></i>
                معاينة استيراد المواد
            </h2>
            <p class="text-muted mb-0">مراجعة البيانات قبل الاستيراد النهائي - الملف: <?= htmlspecialchars($filename) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="import-export.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>
                العودة
            </a>
            <button type="button" class="btn btn-success" onclick="confirmImport()">
                <i class="fas fa-check me-1"></i>
                تأكيد الاستيراد
            </button>
        </div>
    </div>

    <!-- Debug Info (للتشخيص) -->
    <?php if (isset($preview['debug_info'])): ?>
    <div class="alert alert-info mb-4">
        <h6><i class="fas fa-bug me-1"></i> معلومات التشخيص:</h6>
        <p><strong>عدد الأعمدة:</strong> <?= count($preview['debug_info']['first_row_keys'] ?? []) ?></p>
        <p><strong>أعمدة الملف:</strong></p>
        <ul class="mb-2">
            <?php foreach ($preview['debug_info']['first_row_keys'] ?? [] as $i => $key): ?>
                <li>"<?= htmlspecialchars($key) ?>" = "<?= htmlspecialchars($preview['debug_info']['first_row_values'][$i] ?? '') ?>"</li>
            <?php endforeach; ?>
        </ul>

        <?php if (isset($preview['debug_info']['unit_processing'])): ?>
        <p><strong>معالجة الوحدات:</strong></p>
        <ul class="mb-0">
            <?php foreach ($preview['debug_info']['unit_processing'] as $unitInfo): ?>
                <li>
                    <strong>الصف <?= $unitInfo['row'] ?>:</strong>
                    المفتاح="<?= htmlspecialchars($unitInfo['key']) ?>" |
                    المفتاح المنظف="<?= htmlspecialchars($unitInfo['clean_key']) ?>" |
                    القيمة الخام="<?= htmlspecialchars($unitInfo['raw_value']) ?>" |
                    القيمة النهائية="<?= htmlspecialchars($unitInfo['final_unit']) ?>" |
                    الحالة: <?= htmlspecialchars($unitInfo['condition_met']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (isset($preview['debug_info']['status_processing'])): ?>
        <p><strong>معالجة الحالة:</strong></p>
        <ul class="mb-0">
            <?php foreach ($preview['debug_info']['status_processing'] as $statusInfo): ?>
                <li>
                    <strong>الصف <?= $statusInfo['row'] ?>:</strong>
                    المفتاح="<?= htmlspecialchars($statusInfo['key']) ?>" |
                    المفتاح المنظف="<?= htmlspecialchars($statusInfo['clean_key']) ?>" |
                    القيمة الخام="<?= htmlspecialchars($statusInfo['raw_value']) ?>" |
                    القيمة المنظفة="<?= htmlspecialchars($statusInfo['trimmed_value']) ?>" |
                    الحالة النهائية=<?= $statusInfo['final_status'] ?> (<?= htmlspecialchars($statusInfo['final_status_text']) ?>)
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <i class="fas fa-plus-circle fa-2x text-success mb-2"></i>
                    <h4 class="text-success"><?= $preview['summary']['new_count'] ?></h4>
                    <p class="mb-0">مواد جديدة</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning"><?= $preview['summary']['update_count'] ?></h4>
                    <p class="mb-0">مواد للتحديث</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger"><?= $preview['summary']['error_count'] ?></h4>
                    <p class="mb-0">مواد بها أخطاء</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                    <h4 class="text-info"><?= $preview['total_rows'] ?></h4>
                    <p class="mb-0">إجمالي الصفوف</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs for different categories -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="previewTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active text-success fw-bold" id="new-tab" data-bs-toggle="tab" data-bs-target="#new-materials" type="button" role="tab">
                        <i class="fas fa-plus-circle me-1"></i>
                        مواد جديدة (<?= $preview['summary']['new_count'] ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-warning fw-bold" id="update-tab" data-bs-toggle="tab" data-bs-target="#update-materials" type="button" role="tab">
                        <i class="fas fa-edit me-1"></i>
                        مواد للتحديث (<?= $preview['summary']['update_count'] ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-danger fw-bold" id="errors-tab" data-bs-toggle="tab" data-bs-target="#error-materials" type="button" role="tab">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        أخطاء (<?= $preview['summary']['error_count'] ?>)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="previewTabsContent">
                <!-- New Materials Tab -->
                <div class="tab-pane fade show active" id="new-materials" role="tabpanel">
                    <?php if (empty($preview['new_materials'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">لا توجد مواد جديدة للإضافة</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-success">
                                    <tr>
                                        <th>الصف</th>
                                        <th>رقم البند</th>
                                        <th>رقم المجموعة</th>
                                        <th>الوصف</th>
                                        <th>الوحدة</th>
                                        <th>المخزون</th>
                                        <th>الرصيد الافتتاحي</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['new_materials'] as $material): ?>
                                        <tr>
                                            <td><?= $material['row_number'] ?></td>
                                            <td><strong><?= htmlspecialchars($material['item_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($material['group_number']) ?></td>
                                            <td><?= htmlspecialchars($material['description']) ?></td>
                                            <td><?= htmlspecialchars($material['unit']) ?></td>
                                            <td><?= number_format($material['current_stock'], 3) ?></td>
                                            <td>
                                                <?php if (!empty($material['initial_balance']) && $material['initial_balance'] > 0): ?>
                                                    <span class="badge bg-primary"><?= number_format($material['initial_balance'], 3) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $material['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $material['status_text'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Update Materials Tab -->
                <div class="tab-pane fade" id="update-materials" role="tabpanel">
                    <?php if (empty($preview['update_materials'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">لا توجد مواد للتحديث</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-warning">
                                    <tr>
                                        <th>الصف</th>
                                        <th>رقم البند</th>
                                        <th>الوصف</th>
                                        <th>الوحدة الحالية</th>
                                        <th>الوحدة الجديدة</th>
                                        <th>المخزون الحالي</th>
                                        <th>المخزون الجديد</th>
                                        <th>الرصيد الافتتاحي</th>
                                        <th>الحالة الحالية</th>
                                        <th>الحالة الجديدة</th>
                                        <th>التغييرات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['update_materials'] as $material): ?>
                                        <?php 
                                        $changes = [];
                                        if ($material['description'] !== $material['existing_data']['description']) {
                                            $changes[] = 'الوصف';
                                        }
                                        if ($material['current_stock'] != $material['existing_data']['current_stock']) {
                                            $changes[] = 'المخزون';
                                        }
                                        if (!empty($material['initial_balance']) && $material['initial_balance'] > 0) {
                                            $changes[] = 'رصيد افتتاحي';
                                        }
                                        if (isset($material['existing_data']['unit']) && $material['unit'] !== $material['existing_data']['unit']) {
                                            $changes[] = 'الوحدة';
                                        }
                                        if (isset($material['existing_data']['is_active']) && $material['is_active'] != $material['existing_data']['is_active']) {
                                            $changes[] = 'الحالة';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= $material['row_number'] ?></td>
                                            <td><strong><?= htmlspecialchars($material['item_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($material['description']) ?></td>
                                            <td><?= htmlspecialchars($material['existing_data']['unit'] ?? 'غير محدد') ?></td>
                                            <td>
                                                <span class="<?= isset($material['existing_data']['unit']) && $material['unit'] !== $material['existing_data']['unit'] ? 'text-warning fw-bold' : '' ?>">
                                                    <?= htmlspecialchars($material['unit']) ?>
                                                </span>
                                            </td>
                                            <td><?= number_format($material['existing_data']['current_stock'], 3) ?></td>
                                            <td>
                                                <span class="<?= $material['current_stock'] != $material['existing_data']['current_stock'] ? 'text-warning fw-bold' : '' ?>">
                                                    <?= number_format($material['current_stock'], 3) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($material['initial_balance']) && $material['initial_balance'] > 0): ?>
                                                    <span class="badge bg-primary"><?= number_format($material['initial_balance'], 3) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $material['existing_data']['is_active'] ? 'success' : 'secondary' ?>">
                                                    <?= $material['existing_data']['is_active'] ? 'نشط' : 'غير نشط' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $material['is_active'] ? 'success' : 'secondary' ?> <?= isset($material['existing_data']['is_active']) && $material['is_active'] != $material['existing_data']['is_active'] ? 'border border-warning' : '' ?>">
                                                    <?= $material['is_active'] ? 'نشط' : 'غير نشط' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($changes)): ?>
                                                    <span class="badge bg-warning"><?= implode(', ', $changes) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">لا توجد تغييرات</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Error Materials Tab -->
                <div class="tab-pane fade" id="error-materials" role="tabpanel">
                    <?php if (empty($preview['error_materials'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p class="text-success">ممتاز! لا توجد أخطاء في البيانات</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-danger">
                                    <tr>
                                        <th>الصف</th>
                                        <th>رقم البند</th>
                                        <th>الوصف</th>
                                        <th>الأخطاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['error_materials'] as $material): ?>
                                        <tr>
                                            <td><?= $material['row_number'] ?></td>
                                            <td><?= htmlspecialchars($material['item_number']) ?></td>
                                            <td><?= htmlspecialchars($material['description']) ?></td>
                                            <td>
                                                <?php foreach ($material['errors'] as $error): ?>
                                                    <span class="badge bg-danger me-1"><?= htmlspecialchars($error) ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form for final import -->
<form id="finalImportForm" method="POST" action="import-export.php" style="display: none;">
    <input type="hidden" name="action" value="import_materials">
    <input type="hidden" name="confirmed" value="1">
</form>

<style>
/* تحسين مظهر التبويبات */
.nav-tabs .nav-link {
    color: #495057 !important;
    font-weight: 600;
    border: 1px solid transparent;
    background-color: #f8f9fa;
    margin-left: 2px;
}

.nav-tabs .nav-link:hover {
    background-color: #e9ecef;
    border-color: #dee2e6;
}

.nav-tabs .nav-link.active {
    background-color: #fff !important;
    border-color: #dee2e6 #dee2e6 #fff;
    border-bottom: 1px solid #fff;
}

.nav-tabs .nav-link.text-success {
    color: #198754 !important;
}

.nav-tabs .nav-link.text-warning {
    color: #fd7e14 !important;
}

.nav-tabs .nav-link.text-danger {
    color: #dc3545 !important;
}

.nav-tabs .nav-link.active.text-success {
    color: #198754 !important;
    background-color: #d1e7dd !important;
}

.nav-tabs .nav-link.active.text-warning {
    color: #fd7e14 !important;
    background-color: #fff3cd !important;
}

.nav-tabs .nav-link.active.text-danger {
    color: #dc3545 !important;
    background-color: #f8d7da !important;
}

/* تحسين مظهر الجداول */
.table-bordered th,
.table-bordered td {
    border: 1px solid #dee2e6;
}

.table thead th {
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
    vertical-align: middle;
}

.table tbody td {
    vertical-align: middle;
    font-size: 0.9rem;
}

/* تحسين الألوان للقراءة */
.table-success th {
    background-color: #d1e7dd !important;
    color: #0f5132 !important;
}

.table-warning th {
    background-color: #fff3cd !important;
    color: #664d03 !important;
}

.table-danger th {
    background-color: #f8d7da !important;
    color: #721c24 !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmImport() {
    const errorCount = <?= $preview['summary']['error_count'] ?>;
    const newCount = <?= $preview['summary']['new_count'] ?>;
    const updateCount = <?= $preview['summary']['update_count'] ?>;
    
    if (errorCount > 0) {
        Swal.fire({
            title: 'تحذير!',
            html: `يوجد <strong>${errorCount}</strong> صف يحتوي على أخطاء.<br>هذه الصفوف لن يتم استيرادها.<br><br>هل تريد المتابعة مع الصفوف الصحيحة فقط؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، استورد الصفوف الصحيحة',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                executeImport();
            }
        });
    } else {
        Swal.fire({
            title: 'تأكيد الاستيراد',
            html: `سيتم استيراد:<br>• <strong>${newCount}</strong> مادة جديدة<br>• تحديث <strong>${updateCount}</strong> مادة موجودة<br><br>هل تريد المتابعة؟`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، استورد الآن',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                executeImport();
            }
        });
    }
}

function executeImport() {
    Swal.fire({
        title: 'جاري الاستيراد...',
        text: 'يرجى الانتظار حتى اكتمال العملية',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    document.getElementById('finalImportForm').submit();
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
