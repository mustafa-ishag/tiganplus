<?php
/**
 * صفحة معاينة استيراد كتالوج المواد
 */
session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

if (!isset($_SESSION['catalog_import_preview'])) {
    $_SESSION['error_message'] = 'لا توجد بيانات للمعاينة. يرجى اختيار ملف أولاً.';
    header('Location: import-export.php');
    exit();
}

$preview = $_SESSION['catalog_import_preview'];
$filename = $_SESSION['catalog_import_filename'] ?? 'ملف غير معروف';

$pageTitle = 'معاينة استيراد الكتالوج';
$currentPage = 'material-catalog';

ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h4 mb-1">
                <i class="fas fa-eye me-2"></i>
                معاينة استيراد كتالوج المواد
            </h2>
            <p class="text-muted mb-0">مراجعة البيانات قبل الاستيراد - الملف:
                <?= htmlspecialchars($filename) ?>
            </p>
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

    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-success text-center">
                <div class="card-body">
                    <i class="fas fa-plus-circle fa-2x text-success mb-2"></i>
                    <h4 class="text-success">
                        <?= $preview['summary']['new_count'] ?>
                    </h4>
                    <p class="mb-0">مواد جديدة</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning text-center">
                <div class="card-body">
                    <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                    <h4 class="text-warning">
                        <?= $preview['summary']['update_count'] ?>
                    </h4>
                    <p class="mb-0">مواد للتحديث</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger text-center">
                <div class="card-body">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h4 class="text-danger">
                        <?= $preview['summary']['error_count'] ?>
                    </h4>
                    <p class="mb-0">صفوف بها أخطاء</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info text-center">
                <div class="card-body">
                    <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                    <h4 class="text-info">
                        <?= $preview['total_rows'] ?>
                    </h4>
                    <p class="mb-0">إجمالي الصفوف</p>
                </div>
            </div>
        </div>
    </div>

    <!-- تبويبات البيانات -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="previewTabs">
                <li class="nav-item">
                    <button class="nav-link active text-success fw-bold" data-bs-toggle="tab" data-bs-target="#new-tab">
                        <i class="fas fa-plus-circle me-1"></i>
                        جديدة (
                        <?= $preview['summary']['new_count'] ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link text-warning fw-bold" data-bs-toggle="tab" data-bs-target="#update-tab">
                        <i class="fas fa-edit me-1"></i>
                        للتحديث (
                        <?= $preview['summary']['update_count'] ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link text-danger fw-bold" data-bs-toggle="tab" data-bs-target="#errors-tab">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        أخطاء (
                        <?= $preview['summary']['error_count'] ?>)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- مواد جديدة -->
                <div class="tab-pane fade show active" id="new-tab">
                    <?php if (empty($preview['new_items'])): ?>
                        <p class="text-center text-muted py-4">لا توجد مواد جديدة</p>
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
                                        <th>السعر</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['new_items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <?= $item['row_number'] ?>
                                            </td>
                                            <td><strong>
                                                    <?= htmlspecialchars($item['item_number']) ?>
                                                </strong></td>
                                            <td>
                                                <?= htmlspecialchars($item['group_number']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['description']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['unit']) ?>
                                            </td>
                                            <td>
                                                <?= number_format($item['unit_price'], 2) ?> ريال
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- مواد للتحديث -->
                <div class="tab-pane fade" id="update-tab">
                    <?php if (empty($preview['update_items'])): ?>
                        <p class="text-center text-muted py-4">لا توجد مواد للتحديث</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-warning">
                                    <tr>
                                        <th>الصف</th>
                                        <th>رقم البند</th>
                                        <th>رقم المجموعة</th>
                                        <th>الوصف</th>
                                        <th>الوحدة</th>
                                        <th>السعر الجديد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['update_items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <?= $item['row_number'] ?>
                                            </td>
                                            <td><strong>
                                                    <?= htmlspecialchars($item['item_number']) ?>
                                                </strong></td>
                                            <td>
                                                <?= htmlspecialchars($item['group_number']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['description']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['unit']) ?>
                                            </td>
                                            <td>
                                                <?= number_format($item['unit_price'], 2) ?> ريال
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- أخطاء -->
                <div class="tab-pane fade" id="errors-tab">
                    <?php if (empty($preview['error_items'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                            <p class="text-success">ممتاز! لا توجد أخطاء</p>
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
                                    <?php foreach ($preview['error_items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <?= $item['row_number'] ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['item_number']) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['description']) ?>
                                            </td>
                                            <td>
                                                <?php foreach ($item['errors'] as $err): ?>
                                                    <span class="badge bg-danger me-1">
                                                        <?= htmlspecialchars($err) ?>
                                                    </span>
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

<!-- نموذج التأكيد -->
<form id="confirmForm" method="POST" action="import-export.php" style="display:none">
    <input type="hidden" name="action" value="import_catalog">
    <input type="hidden" name="confirmed" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmImport() {
        const newCount = <?= $preview['summary']['new_count'] ?>;
        const updateCount = <?= $preview['summary']['update_count'] ?>;
        const errorCount = <?= $preview['summary']['error_count'] ?>;

        const msg = `سيتم استيراد:<br>• <strong>${newCount}</strong> مادة جديدة<br>• تحديث <strong>${updateCount}</strong> مادة موجودة`
            + (errorCount > 0 ? `<br><br>⚠️ <strong>${errorCount}</strong> صف لن يُستورد بسبب أخطاء` : '');

        Swal.fire({
            title: 'تأكيد الاستيراد',
            html: msg,
            icon: errorCount > 0 ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، استورد الآن',
            cancelButtonText: 'إلغاء'
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'جاري الاستيراد...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                document.getElementById('confirmForm').submit();
            }
        });
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>