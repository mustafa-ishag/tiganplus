<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة قائمة المواد
 * Materials List Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_materials_view')) {
    setAlert('ليس لديك صلاحية لعرض المواد', 'error');
    redirect('../../dashboard.php');
}

$materialModel = new Material();

// معالجة البحث والتصفية
$search = $_GET['search'] ?? '';
$groupNumber = $_GET['group_number'] ?? '';
$status = $_GET['status'] ?? 'active';
$sortBy = $_GET['sort_by'] ?? 'mc.description';
$sortOrder = $_GET['sort_order'] ?? 'ASC';

// Whitelist للترتيب لمنع SQL injection
$allowedSortBy = ['mc.description', 'm.item_number', 'mc.group_number', 'mc.unit', 'm.current_stock'];
$safeSortBy = in_array($sortBy, $allowedSortBy) ? $sortBy : 'mc.description';
$safeSortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';

// بناء شروط البحث (للفلترة الأولية فقط)
$filter = $_GET['filter'] ?? '';
$whereConditions = [];
$params = [];

// فلترة الحالة فقط (البحث سيتم عبر DataTable)
if ($status === 'active') {
    $whereConditions[] = 'm.is_active = 1';
} elseif ($status === 'inactive') {
    $whereConditions[] = 'm.is_active = 0';
}

// فلترة المخزون
if ($filter === 'low_stock') {
    $whereConditions[] = 'm.current_stock <= m.minimum_stock AND m.current_stock > 0';
} elseif ($filter === 'out_of_stock') {
    $whereConditions[] = 'm.current_stock = 0';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// الحصول على جميع المواد (بدون pagination - سيتولى DataTable هذا الأمر)
$orderClause = "ORDER BY {$safeSortBy} {$safeSortOrder}";

$materials = $materialModel->fetchAll(
    "SELECT m.*, mc.description, mc.group_number, mc.unit
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     {$whereClause} {$orderClause}",
    $params
);

// حساب إجمالي السجلات للإحصائيات
$totalRecords = count($materials);

// الحصول على أرقام المجاميع المستخدمة
$usedGroupNumbers = $materialModel->getUsedGroupNumbers();

// الحصول على إحصائيات المواد
$stats = $materialModel->getMaterialStats();

$pageTitle = 'إدارة المواد';
$currentPage = 'materials';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-boxes text-primary me-2"></i>
                المخزون
            </h2>
            <p class="text-muted mb-0">عرض وإدارة جميع المواد في المخزون</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="inactive.php" class="btn btn-outline-secondary">
                    <i class="fas fa-ban me-1"></i>
                    المواد غير النشطة
                </a>
                <?php if (hasPermission('inventory_materials_edit')): ?>
                    <a href="import-export.php" class="btn btn-outline-success">
                        <i class="fas fa-exchange-alt me-1"></i>
                        استيراد/تصدير
                    </a>
                    <a href="create.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        إضافة مادة جديدة
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>



    <!-- فلاتر سريعة -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-filter me-1"></i>
                        فلاتر سريعة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <a href="?status=active" class="btn btn-outline-success w-100 <?= $status === 'active' ? 'active' : '' ?>">
                                <i class="fas fa-check-circle me-1"></i>
                                المواد النشطة
                                <span class="badge bg-success ms-1"><?= number_format($stats['active_materials']) ?></span>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="?status=inactive" class="btn btn-outline-secondary w-100 <?= $status === 'inactive' ? 'active' : '' ?>">
                                <i class="fas fa-ban me-1"></i>
                                المواد غير النشطة
                                <span class="badge bg-secondary ms-1"><?= number_format($stats['inactive_materials']) ?></span>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="?status=all" class="btn btn-outline-primary w-100 <?= $status === 'all' ? 'active' : '' ?>">
                                <i class="fas fa-list me-1"></i>
                                جميع المواد
                                <span class="badge bg-primary ms-1"><?= number_format($stats['total_materials']) ?></span>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="?status=active&filter=low_stock" class="btn btn-outline-warning w-100 <?= $filter === 'low_stock' ? 'active' : '' ?>">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                مخزون منخفض
                                <span class="badge bg-warning ms-1"><?= number_format($stats['low_stock_materials']) ?></span>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="?status=active&filter=out_of_stock" class="btn btn-outline-danger w-100 <?= $filter === 'out_of_stock' ? 'active' : '' ?>">
                                <i class="fas fa-times-circle me-1"></i>
                                نفد المخزون
                                <span class="badge bg-danger ms-1"><?= number_format($stats['out_of_stock_materials']) ?></span>
                            </a>
                        </div>
                        <div class="col-md-2">
                            <a href="index.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-refresh me-1"></i>
                                إعادة تعيين
                            </a>
                        </div>
                    </div>

                    <!-- معلومات إضافية -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>نصيحة:</strong> استخدم مربع البحث للبحث في رقم البند أو الوصف
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول المواد -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة المواد (<?= number_format($totalRecords) ?> مادة)</h5>
            <div class="btn-group" role="group">
                <?php if (hasPermission('inventory_reports_export')): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i>
                    تصدير Excel
                </button>
                <?php endif; ?>

                <?php if (hasPermission('inventory_reports_export')): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printTable()">
                    <i class="fas fa-print me-1"></i>
                    طباعة
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($materials)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">لا توجد مواد</h5>
                    <p class="text-muted">لم يتم العثور على مواد تطابق معايير البحث</p>
                    <?php if (hasPermission('inventory_materials_edit')): ?>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            إضافة أول مادة
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="materialsTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>رقم البند</th>
                                <th>رقم المجموعة</th>
                                <th>الوصف</th>
                                <th>الوحدة</th>
                                <th>المخزون الحالي</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $material): ?>
                                <?php 
                                $isLowStock = $material['current_stock'] <= $material['minimum_stock'];
                                $isOutOfStock = $material['current_stock'] == 0;
                                ?>
                                <tr class="<?= $isOutOfStock ? 'table-danger' : ($isLowStock ? 'table-warning' : '') ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($material['item_number']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($material['group_number']) ?></span>
                                    </td>
                                    <td dir="ltr" class="text-start">
                                        <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($material['description'] ?? '') ?>">
                                            <?= htmlspecialchars($material['description'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($material['unit'] ?? '') ?></td>
                                    <td>
                                        <span class="fw-bold <?= $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') ?>">
                                            <?= formatNumber($material['current_stock'], 3) ?>
                                        </span>
                                        <?php if ($isLowStock): ?>
                                            <i class="fas fa-exclamation-triangle text-warning ms-1" title="مخزون منخفض"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($material['is_active']): ?>
                                            <span class="badge bg-success">نشط</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">غير نشط</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?= $material['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission('inventory_materials_edit')): ?>
                                                <a href="edit.php?id=<?= $material['id'] ?>" 
                                                   class="btn btn-sm btn-outline-warning" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($material['is_active']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deactivateMaterial(<?= $material['id'] ?>)" title="إلغاء تفعيل">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                                            onclick="activateMaterial(<?= $material['id'] ?>)" title="تفعيل">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
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
// تفعيل/إلغاء تفعيل المادة
function activateMaterial(materialId) {
    if (confirm('هل أنت متأكد من تفعيل هذه المادة؟')) {
        updateMaterialStatus(materialId, 1);
    }
}

function deactivateMaterial(materialId) {
    if (confirm('هل أنت متأكد من إلغاء تفعيل هذه المادة؟\nلن تظهر في قوائم المواد النشطة.')) {
        updateMaterialStatus(materialId, 0);
    }
}

function updateMaterialStatus(materialId, status) {
    console.log('Updating material status:', { materialId, status });

    fetch('update-status-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            material_id: materialId,
            is_active: status
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            const action = status ? 'تفعيل' : 'إلغاء تفعيل';
            alert(`تم ${action} المادة بنجاح`);
            location.reload();
        } else {
            console.error('Server error:', data);
            alert('حدث خطأ: ' + data.message + (data.debug ? '\n\nتفاصيل: ' + data.debug : ''));
        }
    })
    .catch(error => {
        console.error('Network error:', error);
        alert('حدث خطأ في الاتصال: ' + error.message);
    });
}

// تصدير إلى Excel
function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'export.php?' + params.toString();
}

// طباعة الجدول
function printTable() {
    window.print();
}
</script>

        </div>
    </div>





<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTable للجدول
    if ($('#materialsTable').length) {
        $('#materialsTable').DataTable({
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
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "الكل"]],
            "order": [[0, 'asc']], // ترتيب حسب رقم البند
            "columnDefs": [
                { "orderable": false, "targets": 6 }, // عمود الإجراءات غير قابل للترتيب
                { "searchable": true, "targets": [0, 1, 2, 3] }, // البحث في رقم البند، المجموعة، الوصف، الوحدة
                { "className": "text-center", "targets": [1, 4, 5, 6] } // محاذاة وسط
            ],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                   '<"row"<"col-sm-12"tr>>' +
                   '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "stateSave": true, // حفظ حالة الجدول (البحث، الترتيب، الصفحة)
            "stateDuration": 60 * 60 * 24, // حفظ لمدة يوم واحد
            "processing": true,
            "deferRender": true // تحسين الأداء للجداول الكبيرة
        });
    }
});

// دوال إدارة المواد
function viewMaterial(id) {
    window.location.href = `view.php?id=${id}`;
}

function editMaterial(id) {
    window.location.href = `edit.php?id=${id}`;
}

function toggleMaterialStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'إلغاء تفعيل' : 'تفعيل';
    const actionType = currentStatus === 'active' ? 'deactivate' : 'activate';

    Swal.fire({
        title: 'تأكيد العملية',
        text: `هل أنت متأكد من ${action} هذه المادة؟`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم، متأكد',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إظهار loading
            Swal.fire({
                title: 'جاري المعالجة...',
                text: `جاري ${action} المادة`,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال الطلب
            fetch('update-status-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id,
                    action: actionType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح',
                        text: data.message,
                        confirmButtonText: 'موافق'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: data.message,
                        confirmButtonText: 'موافق'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال',
                    text: 'حدث خطأ أثناء الاتصال بالخادم',
                    confirmButtonText: 'موافق'
                });
                console.error('Error:', error);
            });
        }
    });
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggle = document.getElementById('sidebarToggle');

    if (sidebar && mainContent) {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');

        // إضافة تأثير الحركة للزر
        if (toggle) {
            toggle.classList.add('animating');
            setTimeout(() => {
                toggle.classList.remove('animating');
            }, 300);
        }

        // حفظ حالة الشريط الجانبي
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
}

// استعادة حالة الشريط الجانبي عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        if (sidebar && mainContent) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    }
});
</script>
