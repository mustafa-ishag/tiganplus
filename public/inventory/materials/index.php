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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">إدارة المواد</h4>
            <p class="text-muted mb-0 small">عرض وإدارة جميع المواد والأصناف في المخزون</p>
        </div>
        <div>
            <a href="inactive.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-secondary fw-bold ms-2">
                <i class="fas fa-ban me-2"></i> المواد غير النشطة
            </a>
            <?php if (hasPermission('inventory_materials_edit')): ?>
                <a href="import-export.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-success fw-bold ms-2">
                    <i class="fas fa-file-import me-2"></i> استيراد/تصدير
                </a>
                <a href="create.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="fas fa-plus me-2"></i> إضافة مادة جديدة
                </a>
            <?php endif; ?>
        </div>
    </div>



    <!-- فلاتر سريعة -->
    <div class="dash-card mb-4">
        <div class="card-header bg-transparent border-0 p-4 pb-2">
            <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-filter text-primary me-2"></i>فلاتر التصنيف</h6>
            <p class="text-muted mb-0 small">تصفية المواد حسب الحالة والمخزون</p>
        </div>
        <div class="card-body px-4 pb-4 pt-2">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="?status=all" class="btn rounded-pill px-4 fw-bold <?= $status === 'all' ? 'btn-primary shadow-sm' : 'btn-light text-primary border-0' ?>">
                    جميع المواد <span class="badge <?= $status === 'all' ? 'bg-white text-primary' : 'bg-primary-soft text-primary' ?> ms-2 rounded-pill"><?= number_format($stats['total_materials']) ?></span>
                </a>
                <a href="?status=active" class="btn rounded-pill px-4 fw-bold <?= $status === 'active' && empty($filter) ? 'btn-success shadow-sm' : 'btn-light text-success border-0' ?>">
                    المواد النشطة <span class="badge <?= $status === 'active' && empty($filter) ? 'bg-white text-success' : 'bg-success-soft text-success' ?> ms-2 rounded-pill"><?= number_format($stats['active_materials']) ?></span>
                </a>
                <a href="?status=active&filter=low_stock" class="btn rounded-pill px-4 fw-bold <?= $filter === 'low_stock' ? 'btn-warning shadow-sm' : 'btn-light text-warning border-0' ?>">
                    مخزون منخفض <span class="badge <?= $filter === 'low_stock' ? 'bg-white text-warning' : 'bg-warning-soft text-warning' ?> ms-2 rounded-pill"><?= number_format($stats['low_stock_materials']) ?></span>
                </a>
                <a href="?status=active&filter=out_of_stock" class="btn rounded-pill px-4 fw-bold <?= $filter === 'out_of_stock' ? 'btn-danger shadow-sm' : 'btn-light text-danger border-0' ?>">
                    نفد المخزون <span class="badge <?= $filter === 'out_of_stock' ? 'bg-white text-danger' : 'bg-danger-soft text-danger' ?> ms-2 rounded-pill"><?= number_format($stats['out_of_stock_materials']) ?></span>
                </a>
                <a href="?status=inactive" class="btn rounded-pill px-4 fw-bold <?= $status === 'inactive' ? 'btn-secondary shadow-sm' : 'btn-light text-secondary border-0' ?>">
                    المواد غير النشطة <span class="badge <?= $status === 'inactive' ? 'bg-white text-secondary' : 'bg-secondary-soft text-secondary' ?> ms-2 rounded-pill"><?= number_format($stats['inactive_materials']) ?></span>
                </a>
                <a href="index.php" class="btn btn-light rounded-pill px-4 fw-bold text-muted border-0 ms-auto">
                    <i class="fas fa-sync-alt me-2"></i>إعادة تعيين
                </a>
            </div>
            <div class="d-flex align-items-center p-3 rounded-3" style="background: rgba(13, 110, 253, 0.05); border: 1px dashed rgba(13, 110, 253, 0.2);">
                <div class="icon-circle bg-primary-soft me-3" style="width: 32px; height: 32px; font-size: 0.8rem; flex-shrink: 0;">
                    <i class="fas fa-info text-primary"></i>
                </div>
                <p class="mb-0 text-muted small"><strong>نصيحة ذكية:</strong> استخدم مربع البحث الموجود في جدول البيانات أدناه للبحث السريع عن أي مادة باستخدام رقم البند أو جزء من الوصف.</p>
            </div>
        </div>
    </div>

    <!-- جدول المواد -->
    <div class="dash-card">
        <div class="card-header bg-transparent border-0 p-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-list-ul text-info me-2"></i>قائمة المواد</h6>
                <p class="text-muted mb-0 small">إجمالي السجلات: <?= number_format($totalRecords) ?> مادة</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- مربع البحث المخصص -->
                <div class="position-relative">
                    <input type="text" id="customSearchInput" class="form-control form-control-sm rounded-pill ps-4" placeholder="ابحث في المواد..." style="width: 250px;">
                    <i class="fas fa-search position-absolute text-muted" style="top: 50%; left: 12px; transform: translateY(-50%); font-size: 0.85rem;"></i>
                </div>
                
                <div class="btn-group ms-2" role="group">
                    <?php if (hasPermission('inventory_reports_export')): ?>
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-bold text-success border-0 me-2" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>تصدير Excel
                    </button>
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-bold text-primary border-0" onclick="printTable()">
                        <i class="fas fa-print me-2"></i>طباعة
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($materials)): ?>
                <div class="text-center py-5">
                    <div class="icon-circle bg-secondary-soft mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <i class="fas fa-box-open text-secondary"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">لا توجد مواد</h6>
                    <p class="text-muted mb-3 small">لم يتم العثور على مواد تطابق معايير البحث الحالية</p>
                    <?php if (hasPermission('inventory_materials_edit')): ?>
                        <a href="create.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                            <i class="fas fa-plus me-2"></i>إضافة أول مادة
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive px-4 pb-4">
                    <table id="materialsTable" class="table table-hover mb-0" style="font-size: 0.85rem;">
                        <thead style="background: #f8f9fc;">
                            <tr>
                                <th class="ps-3 border-0 text-muted fw-bold" style="font-size: 0.75rem;">رقم البند</th>
                                <th class="border-0 text-muted fw-bold text-center" style="font-size: 0.75rem;">رقم المجموعة</th>
                                <th class="border-0 text-muted fw-bold" style="font-size: 0.75rem;">الوصف</th>
                                <th class="border-0 text-muted fw-bold" style="font-size: 0.75rem;">الوحدة</th>
                                <th class="border-0 text-muted fw-bold text-center" style="font-size: 0.75rem;">المخزون الحالي</th>
                                <th class="border-0 text-muted fw-bold text-center" style="font-size: 0.75rem;">الحالة</th>
                                <th class="pe-3 border-0 text-muted fw-bold text-center" style="font-size: 0.75rem;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $material): ?>
                                <?php 
                                $isLowStock = $material['current_stock'] <= $material['minimum_stock'] && $material['current_stock'] > 0;
                                $isOutOfStock = $material['current_stock'] == 0;
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <a href="view.php?id=<?= $material['id'] ?>" class="text-decoration-none fw-bold text-primary" style="font-size: 0.85rem;">
                                            <?= htmlspecialchars($material['item_number']) ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-soft text-secondary rounded-pill px-2" style="font-size: 0.7rem;"><?= htmlspecialchars($material['group_number']) ?></span>
                                    </td>
                                    <td dir="ltr" class="text-start">
                                        <div class="text-truncate text-dark fw-bold" style="max-width: 200px; font-size: 0.8rem;" title="<?= htmlspecialchars($material['description'] ?? '') ?>">
                                            <?= htmlspecialchars($material['description'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td><span class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($material['unit'] ?? '') ?></span></td>
                                    <td class="text-center">
                                        <span class="fw-bold <?= $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') ?>" style="font-size: 0.9rem;">
                                            <?= formatNumber($material['current_stock'], 3) ?>
                                        </span>
                                        <?php if ($isLowStock): ?>
                                            <i class="fas fa-exclamation-triangle text-warning ms-1" style="font-size: 0.75rem;" title="مخزون منخفض"></i>
                                        <?php elseif ($isOutOfStock): ?>
                                            <i class="fas fa-times-circle text-danger ms-1" style="font-size: 0.75rem;" title="نافد"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($material['is_active']): ?>
                                            <span class="badge bg-success-soft text-success rounded-pill px-2" style="font-size: 0.7rem;">نشط</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-soft text-secondary rounded-pill px-2" style="font-size: 0.7rem;">غير نشط</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3 text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <a href="view.php?id=<?= $material['id'] ?>" 
                                               class="btn btn-sm btn-light rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="عرض التفاصيل">
                                                <i class="fas fa-eye text-primary" style="font-size: 0.75rem;"></i>
                                            </a>
                                            <?php if (hasPermission('inventory_materials_edit')): ?>
                                                <a href="edit.php?id=<?= $material['id'] ?>" 
                                                   class="btn btn-sm btn-light rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;" title="تعديل">
                                                    <i class="fas fa-edit text-warning" style="font-size: 0.75rem;"></i>
                                                </a>
                                                <?php if ($material['is_active']): ?>
                                                    <button type="button" class="btn btn-sm btn-light rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;"
                                                            onclick="deactivateMaterial(<?= $material['id'] ?>)" title="إلغاء تفعيل">
                                                        <i class="fas fa-ban text-danger" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-light rounded-circle" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;"
                                                            onclick="activateMaterial(<?= $material['id'] ?>)" title="تفعيل">
                                                        <i class="fas fa-check text-success" style="font-size: 0.75rem;"></i>
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
            "searching": true,
            "dom": "<'row mb-3'<'col-sm-12'l>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            "stateSave": true, // حفظ حالة الجدول (البحث، الترتيب، الصفحة)
            "stateDuration": 60 * 60 * 24, // حفظ لمدة يوم واحد
            "processing": true,
            "deferRender": true // تحسين الأداء للجداول الكبيرة
        });

        // ربط مربع البحث المخصص بالجدول
        $('#customSearchInput').on('keyup', function () {
            $('#materialsTable').DataTable().search(this.value).draw();
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
