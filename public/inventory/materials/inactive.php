<?php
/**
 * صفحة المواد غير النشطة
 * Inactive Materials Page
 */

session_start();

require_once __DIR__ . '/../../../includes/path-helper.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_materials_view')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لعرض المواد';
    header('Location: ' . path('dashboard.php'));
    exit();
}

$materialModel = new Material();

// جلب المواد غير النشطة فقط
$materials = $materialModel->fetchAll(
    "SELECT m.*, mc.description, mc.group_number, mc.unit 
     FROM materials m 
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number 
     WHERE m.is_active = 0 ORDER BY m.item_number ASC"
);

// الحصول على إحصائيات المواد غير النشطة
$inactiveStats = [
    'total_inactive' => count($materials),
    'with_stock' => 0,
    'without_stock' => 0
];

foreach ($materials as $material) {
    if ($material['current_stock'] > 0) {
        $inactiveStats['with_stock']++;
    } else {
        $inactiveStats['without_stock']++;
    }
}

$pageTitle = 'المواد غير النشطة';
$currentPage = 'materials-inactive';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '/etganplus/public/dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => '/etganplus/public/inventory/'],
    ['title' => 'المواد', 'url' => '/etganplus/public/inventory/materials/'],
    ['title' => 'المواد غير النشطة', 'url' => '']
];

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-ban text-secondary me-2"></i>
                المواد غير النشطة
            </h2>
            <p class="text-muted mb-0">عرض وإدارة المواد المعطلة في النظام</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i>
                    العودة لجميع المواد
                </a>
                <?php if (hasPermission('inventory_materials_edit')): ?>
                    <button type="button" class="btn btn-success" onclick="activateSelected()">
                        <i class="fas fa-check me-1"></i>
                        تفعيل المحدد
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- إحصائيات المواد غير النشطة -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center py-3">
                    <i class="fas fa-ban fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= number_format($inactiveStats['total_inactive']) ?></h4>
                    <small>إجمالي المواد غير النشطة</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body text-center py-3">
                    <i class="fas fa-boxes fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= number_format($inactiveStats['with_stock']) ?></h4>
                    <small>لديها مخزون</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center py-3">
                    <i class="fas fa-empty-set fa-2x mb-2"></i>
                    <h4 class="mb-1"><?= number_format($inactiveStats['without_stock']) ?></h4>
                    <small>بدون مخزون</small>
                </div>
            </div>
        </div>
    </div>

    <!-- تنبيه -->
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>تنبيه:</strong> هذه المواد غير نشطة ولا تظهر في العمليات العادية. يمكنك تفعيلها مرة أخرى إذا لزم الأمر.
    </div>

    <!-- جدول المواد غير النشطة -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-1"></i>
                قائمة المواد غير النشطة (<?= number_format(count($materials)) ?> مادة)
            </h5>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                <label class="form-check-label" for="selectAll">
                    تحديد الكل
                </label>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($materials)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h4>ممتاز! لا توجد مواد غير نشطة</h4>
                    <p class="text-muted">جميع المواد في النظام نشطة ومتاحة للاستخدام</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>
                        العودة لقائمة المواد
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="inactiveMaterialsTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAllHeader" onchange="toggleSelectAll()">
                                </th>
                                <th>رقم البند</th>
                                <th>رقم المجموعة</th>
                                <th>الوصف</th>
                                <th>الوحدة</th>
                                <th>المخزون الحالي</th>
                                <th>تاريخ التعطيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $material): ?>
                                <?php 
                                $hasStock = $material['current_stock'] > 0;
                                ?>
                                <tr class="<?= $hasStock ? 'table-warning' : '' ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input material-checkbox" value="<?= $material['id'] ?>">
                                    </td>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($material['item_number']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($material['group_number'] ?? '') ?></td>
                                    <td>
                                        <?= htmlspecialchars($material['description'] ?? '') ?>
                                        <?php if ($hasStock): ?>
                                            <span class="badge bg-warning text-dark ms-1">لديه مخزون</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($material['unit'] ?? '') ?></td>
                                    <td class="text-end">
                                        <span class="<?= $hasStock ? 'text-warning fw-bold' : 'text-muted' ?>">
                                            <?= number_format($material['current_stock'], 3) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= date('Y-m-d', strtotime($material['updated_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="view.php?id=<?= $material['id'] ?>" class="btn btn-outline-info" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission('inventory_materials_edit')): ?>
                                                <button type="button" class="btn btn-outline-success" onclick="activateMaterial(<?= $material['id'] ?>)" title="تفعيل">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <a href="edit.php?id=<?= $material['id'] ?>" class="btn btn-outline-primary" title="تعديل">
                                                    <i class="fas fa-edit"></i>
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
</div>

<script>
// تهيئة DataTable
$(document).ready(function() {
    $('#inactiveMaterialsTable').DataTable({
        language: {
            url: '<?= path('assets/js/datatables-arabic.json') ?>'
        },
        order: [[1, 'asc']],
        pageLength: 25,
        responsive: true,
        columnDefs: [
            { orderable: false, targets: [0, 7] }
        ]
    });
});

// تحديد/إلغاء تحديد الكل
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll') || document.getElementById('selectAllHeader');
    const checkboxes = document.querySelectorAll('.material-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    // مزامنة checkbox الرأس مع checkbox الجدول
    const headerCheckbox = document.getElementById('selectAllHeader');
    const mainCheckbox = document.getElementById('selectAll');
    if (headerCheckbox && mainCheckbox) {
        headerCheckbox.checked = mainCheckbox.checked = selectAll.checked;
    }
}

// تفعيل مادة واحدة
function activateMaterial(materialId) {
    Swal.fire({
        title: 'تأكيد التفعيل',
        text: 'هل أنت متأكد من تفعيل هذه المادة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، فعل',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إرسال طلب AJAX لتفعيل المادة
            fetch('activate_material.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ material_id: materialId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response:', data); // للتشخيص
                if (data.success) {
                    Swal.fire('تم!', 'تم تفعيل المادة بنجاح', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: data.message || 'حدث خطأ أثناء تفعيل المادة',
                        icon: 'error',
                        footer: data.debug ? `تفاصيل: ${data.debug}` : ''
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error); // للتشخيص
                Swal.fire('خطأ!', `حدث خطأ في الاتصال: ${error.message}`, 'error');
            });
        }
    });
}

// تفعيل المواد المحددة
function activateSelected() {
    const selectedMaterials = Array.from(document.querySelectorAll('.material-checkbox:checked')).map(cb => cb.value);
    
    if (selectedMaterials.length === 0) {
        Swal.fire('تنبيه', 'يرجى تحديد مادة واحدة على الأقل', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'تأكيد التفعيل',
        text: `هل أنت متأكد من تفعيل ${selectedMaterials.length} مادة؟`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'نعم، فعل الكل',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إرسال طلب AJAX لتفعيل المواد المحددة
            fetch('activate_material.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ material_ids: selectedMaterials })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('تم!', `تم تفعيل ${data.activated_count} مادة بنجاح`, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('خطأ!', data.message || 'حدث خطأ أثناء تفعيل المواد', 'error');
                }
            })
            .catch(error => {
                Swal.fire('خطأ!', 'حدث خطأ في الاتصال', 'error');
            });
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
