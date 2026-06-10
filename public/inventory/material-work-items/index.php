<?php
/**
 * صفحة إدارة العلاقات بين المواد وبنود الأعمال
 * Material Work Items Relationships Management
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إدارة العلاقات بين المواد وبنود الأعمال';
$currentPage = 'inventory';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('material_work_items_view')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_relationship':
                $material_id = (int)$_POST['material_id'];
                $work_item_id = (int)$_POST['work_item_id'];
                $quantity_ratio = (float)$_POST['quantity_ratio'];
                $is_primary = isset($_POST['is_primary']) ? 1 : 0;
                $notes = trim($_POST['notes']);

                try {
                    $stmt = $db->prepare("
                        INSERT INTO material_work_items 
                        (material_id, work_item_id, quantity_ratio, is_primary, notes, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$material_id, $work_item_id, $quantity_ratio, $is_primary, $notes, $user_id]);
                    
                    $_SESSION['success_message'] = 'تم إضافة العلاقة بنجاح';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $_SESSION['error_message'] = 'هذه العلاقة موجودة مسبقاً';
                    } else {
                        $_SESSION['error_message'] = 'حدث خطأ في إضافة العلاقة';
                    }
                }
                break;

            case 'toggle_status':
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("UPDATE material_work_items SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_message'] = 'تم تحديث حالة العلاقة';
                break;

            case 'delete_relationship':
                $id = (int)$_POST['id'];
                $stmt = $db->prepare("DELETE FROM material_work_items WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success_message'] = 'تم حذف العلاقة بنجاح';
                break;
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// جلب العلاقات مع بيانات المواد وبنود الأعمال
$relationshipsQuery = "
    SELECT 
        mwi.*,
        m.item_number as material_number,
        mc.description as material_description,
        mc.unit as material_unit,
        wi.item_number as work_item_number,
        wi.description as work_item_description,
        wi.unit as work_item_unit,
        u.full_name as created_by_name
    FROM material_work_items mwi
    LEFT JOIN materials m ON mwi.material_id = m.id
    LEFT JOIN work_items wi ON mwi.work_item_id = wi.id
    LEFT JOIN users u ON mwi.created_by = u.id
    ORDER BY wi.item_number, mwi.is_primary DESC, m.item_number
";
$relationships = $db->query($relationshipsQuery)->fetchAll();

// جلب المواد النشطة
$materials = $db->query("
    SELECT id, item_number, description, unit, group_number
    FROM materials
    WHERE is_active = 1
    ORDER BY item_number
")->fetchAll();

// جلب بنود الأعمال النشطة
$workItems = $db->query("
    SELECT id, item_number, description, unit, standard_price
    FROM work_items
    WHERE is_active = 1
    ORDER BY item_number
")->fetchAll();

// إحصائيات
$stats = [
    'total_relationships' => count($relationships),
    'active_relationships' => count(array_filter($relationships, fn($r) => $r['is_active'])),
    'primary_relationships' => count(array_filter($relationships, fn($r) => $r['is_primary'])),
    'materials_with_relationships' => count(array_unique(array_column($relationships, 'material_id'))),
    'work_items_with_relationships' => count(array_unique(array_column($relationships, 'work_item_id')))
];

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-link text-primary me-2"></i>
                إدارة العلاقات بين المواد وبنود الأعمال
            </h1>
            <p class="text-muted mb-0">ربط المواد ببنود الأعمال لتسهيل إنشاء شهادات الإنجاز</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRelationshipModal">
                <i class="fas fa-plus me-1"></i>
                إضافة علاقة جديدة
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">إجمالي العلاقات</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_relationships']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-link fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">العلاقات النشطة</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_relationships']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">العلاقات الأساسية</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['primary_relationships']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">مواد مرتبطة</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['materials_with_relationships']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-sm-6 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">بنود أعمال مرتبطة</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['work_items_with_relationships']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relationships Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                قائمة العلاقات بين المواد وبنود الأعمال
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="relationshipsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>رقم المادة</th>
                            <th>وصف المادة</th>
                            <th>رقم بند العمل</th>
                            <th>وصف بند العمل</th>
                            <th>نسبة الكمية</th>
                            <th>أساسية</th>
                            <th>الحالة</th>
                            <th>أنشئ بواسطة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($relationships as $rel): ?>
                        <tr>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($rel['material_number']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($rel['material_description']); ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($rel['work_item_number']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($rel['work_item_description']); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo number_format($rel['quantity_ratio'], 4); ?></span>
                            </td>
                            <td>
                                <?php if ($rel['is_primary']): ?>
                                    <span class="badge bg-warning"><i class="fas fa-star me-1"></i>أساسية</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">مساعدة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rel['is_active']): ?>
                                    <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">غير نشط</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($rel['created_by_name']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="toggleStatus(<?php echo $rel['id']; ?>)" 
                                            title="تغيير الحالة">
                                        <i class="fas fa-toggle-<?php echo $rel['is_active'] ? 'on' : 'off'; ?>"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteRelationship(<?php echo $rel['id']; ?>)" 
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
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- Add Relationship Modal -->
<div class="modal fade" id="addRelationshipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    إضافة علاقة جديدة بين مادة وبند عمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_relationship">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="material_id" class="form-label">المادة <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="material_search_input"
                                       placeholder="ابحث عن مادة..." autocomplete="off">
                                <input type="hidden" id="material_id" name="material_id" required>
                                <div id="material_dropdown" class="dropdown-menu w-100" style="max-height: 300px; overflow-y: auto;">
                                    <!-- سيتم عرض نتائج البحث هنا -->
                                </div>
                            </div>
                            <div id="selected_material_info" class="mt-2" style="display: none;">
                                <div class="alert alert-info py-2 mb-0">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <span id="selected_material_text"></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="work_item_id" class="form-label">بند العمل <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="work_search_input"
                                       placeholder="ابحث عن بند عمل..." autocomplete="off">
                                <input type="hidden" id="work_item_id" name="work_item_id" required>
                                <div id="work_dropdown" class="dropdown-menu w-100" style="max-height: 300px; overflow-y: auto;">
                                    <!-- سيتم عرض نتائج البحث هنا -->
                                </div>
                            </div>
                            <div id="selected_work_info" class="mt-2" style="display: none;">
                                <div class="alert alert-success py-2 mb-0">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <span id="selected_work_text"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quantity_ratio" class="form-label">نسبة الكمية <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity_ratio" name="quantity_ratio"
                                   step="0.0001" min="0" value="1.0000" required>
                            <div class="form-text">كمية المادة المطلوبة لكل وحدة من بند العمل</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">خصائص العلاقة</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary">
                                <label class="form-check-label" for="is_primary">
                                    <i class="fas fa-star text-warning me-1"></i>
                                    مادة أساسية لبند العمل
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="أدخل أي ملاحظات حول هذه العلاقة..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="save_relationship_btn">
                        <i class="fas fa-save me-1"></i>
                        حفظ العلاقة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* تحسين مظهر القوائم المنسدلة للبحث السريع */
.dropdown-menu.show {
    display: block;
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1000;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    background-color: #fff;
}

.dropdown-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f8f9fa;
    text-decoration: none;
    color: #212529;
    display: block;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #16181b;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item strong {
    color: #0d6efd;
}

.dropdown-item small {
    color: #6c757d;
}

/* تحسين مظهر حقول البحث */
.position-relative input[type="text"] {
    border-radius: 0.375rem;
}

.position-relative input[type="text"]:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* تحسين مظهر معلومات الاختيار */
.alert.py-2 {
    font-size: 0.875rem;
}
</style>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTable
    if (!$.fn.DataTable.isDataTable('#relationshipsTable')) {
        $('#relationshipsTable').DataTable({
            "language": {
                "sProcessing": "جارٍ التحميل...",
                "sLengthMenu": "أظهر _MENU_ مدخلات",
                "sZeroRecords": "لم يعثر على أية سجلات",
                "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
                "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
                "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
                "sSearch": "ابحث:",
                "oPaginate": {
                    "sFirst": "الأول",
                    "sPrevious": "السابق",
                    "sNext": "التالي",
                    "sLast": "الأخير"
                }
            },
            "responsive": true,
            "order": [[ 2, "asc" ]],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": -1 }
            ]
        });
    }
});

function toggleStatus(id) {
    Swal.fire({
        title: 'تأكيد تغيير الحالة',
        text: 'هل أنت متأكد من تغيير حالة هذه العلاقة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، غير الحالة',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteRelationship(id) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذه العلاقة؟ لا يمكن التراجع عن هذا الإجراء.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_relationship">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// بيانات المواد وبنود الأعمال للبحث السريع
const materialsData = <?= json_encode($materials) ?>;
const workItemsData = <?= json_encode($workItems) ?>;

// متغيرات للتحكم في عرض النتائج
let currentMaterialResults = [];
let currentWorkResults = [];
let materialDisplayCount = 10;
let workDisplayCount = 10;
const resultsIncrement = 10;

// إعداد البحث السريع للمواد
document.getElementById('material_search_input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    materialDisplayCount = 10; // إعادة تعيين العداد
    searchMaterials(searchTerm);
});

function searchMaterials(searchTerm) {
    const dropdown = document.getElementById('material_dropdown');

    if (searchTerm.length < 2) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        currentMaterialResults = [];
        return;
    }

    // فلترة جميع النتائج
    currentMaterialResults = materialsData.filter(material =>
        material.item_number.toLowerCase().includes(searchTerm) ||
        material.description.toLowerCase().includes(searchTerm)
    );

    if (currentMaterialResults.length === 0) {
        dropdown.innerHTML = `
            <div class="dropdown-item-text text-center text-muted py-3">
                <i class="fas fa-search me-1"></i>
                لا توجد مواد تطابق البحث
            </div>
        `;
    } else {
        renderMaterialResults();
    }

    dropdown.classList.add('show');
}

function renderMaterialResults() {
    const dropdown = document.getElementById('material_dropdown');
    const displayResults = currentMaterialResults.slice(0, materialDisplayCount);
    const hasMore = currentMaterialResults.length > materialDisplayCount;

    const headerHtml = `<div class="dropdown-header">عرض ${displayResults.length} من ${currentMaterialResults.length} نتيجة</div>`;

    const itemsHtml = displayResults.map(material => `
        <a href="#" class="dropdown-item" onclick="selectMaterial(${material.id}, '${material.item_number}', '${material.description.replace(/'/g, "\\'")}'); return false;">
            <div>
                <strong>${material.item_number}</strong>
                <br>
                <small>${material.description}</small>
                <br>
                <small class="text-muted">المجموعة: ${material.group_number || 'غير محدد'}</small>
            </div>
        </a>
    `).join('');

    const loadMoreHtml = hasMore ? `
        <div class="dropdown-item-text text-center border-top pt-2">
            <button class="btn btn-sm btn-outline-primary" onclick="loadMoreMaterials(); return false;" id="load-more-materials-btn">
                <i class="fas fa-chevron-down me-1"></i>
                عرض المزيد (${currentMaterialResults.length - materialDisplayCount} متبقي)
            </button>
            <div class="text-muted small mt-1">
                <i class="fas fa-info-circle me-1"></i>
                أو قم بالتمرير لأسفل لتحميل المزيد تلقائياً
            </div>
        </div>
    ` : '';

    const endMessageHtml = !hasMore && currentMaterialResults.length > 10 ? `
        <div class="dropdown-item-text text-center text-muted py-2 border-top">
            <i class="fas fa-check-circle me-1"></i>
            تم عرض جميع النتائج (${currentMaterialResults.length})
        </div>
    ` : '';

    dropdown.innerHTML = headerHtml + itemsHtml + loadMoreHtml + endMessageHtml;
}

function loadMoreMaterials() {
    materialDisplayCount += resultsIncrement;
    renderMaterialResults();
}

// إضافة مستمع التمرير للمواد
document.getElementById('material_dropdown').addEventListener('scroll', function() {
    const dropdown = this;
    if (dropdown.scrollTop + dropdown.clientHeight >= dropdown.scrollHeight - 5) {
        // وصل إلى نهاية القائمة، تحميل المزيد
        if (currentMaterialResults.length > materialDisplayCount) {
            // إظهار مؤشر التحميل
            const loadMoreBtn = document.getElementById('load-more-materials-btn');
            if (loadMoreBtn) {
                loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري التحميل...';
                loadMoreBtn.disabled = true;
            }

            // تأخير قصير لإظهار مؤشر التحميل
            setTimeout(() => {
                loadMoreMaterials();
            }, 200);
        }
    }
});

// إعداد البحث السريع لبنود الأعمال
document.getElementById('work_search_input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    workDisplayCount = 10; // إعادة تعيين العداد
    searchWorks(searchTerm);
});

function searchWorks(searchTerm) {
    const dropdown = document.getElementById('work_dropdown');

    if (searchTerm.length < 2) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        currentWorkResults = [];
        return;
    }

    // فلترة جميع النتائج
    currentWorkResults = workItemsData.filter(work =>
        work.item_number.toLowerCase().includes(searchTerm) ||
        work.description.toLowerCase().includes(searchTerm)
    );

    if (currentWorkResults.length === 0) {
        dropdown.innerHTML = `
            <div class="dropdown-item-text text-center text-muted py-3">
                <i class="fas fa-search me-1"></i>
                لا توجد بنود أعمال تطابق البحث
            </div>
        `;
    } else {
        renderWorkResults();
    }

    dropdown.classList.add('show');
}

function renderWorkResults() {
    const dropdown = document.getElementById('work_dropdown');
    const displayResults = currentWorkResults.slice(0, workDisplayCount);
    const hasMore = currentWorkResults.length > workDisplayCount;

    const headerHtml = `<div class="dropdown-header">عرض ${displayResults.length} من ${currentWorkResults.length} نتيجة</div>`;

    const itemsHtml = displayResults.map(work => `
        <a href="#" class="dropdown-item" onclick="selectWork(${work.id}, '${work.item_number}', '${work.description.replace(/'/g, "\\'")}'); return false;">
            <div>
                <strong>${work.item_number}</strong>
                <br>
                <small>${work.description}</small>
                <br>
                <small class="text-muted">السعر المعياري: ${work.standard_price || 0} ريال</small>
            </div>
        </a>
    `).join('');

    const loadMoreHtml = hasMore ? `
        <div class="dropdown-item-text text-center border-top pt-2">
            <button class="btn btn-sm btn-outline-primary" onclick="loadMoreWorks(); return false;" id="load-more-works-btn">
                <i class="fas fa-chevron-down me-1"></i>
                عرض المزيد (${currentWorkResults.length - workDisplayCount} متبقي)
            </button>
            <div class="text-muted small mt-1">
                <i class="fas fa-info-circle me-1"></i>
                أو قم بالتمرير لأسفل لتحميل المزيد تلقائياً
            </div>
        </div>
    ` : '';

    const endMessageHtml = !hasMore && currentWorkResults.length > 10 ? `
        <div class="dropdown-item-text text-center text-muted py-2 border-top">
            <i class="fas fa-check-circle me-1"></i>
            تم عرض جميع النتائج (${currentWorkResults.length})
        </div>
    ` : '';

    dropdown.innerHTML = headerHtml + itemsHtml + loadMoreHtml + endMessageHtml;
}

function loadMoreWorks() {
    workDisplayCount += resultsIncrement;
    renderWorkResults();
}

// إضافة مستمع التمرير لبنود الأعمال
document.getElementById('work_dropdown').addEventListener('scroll', function() {
    const dropdown = this;
    if (dropdown.scrollTop + dropdown.clientHeight >= dropdown.scrollHeight - 5) {
        // وصل إلى نهاية القائمة، تحميل المزيد
        if (currentWorkResults.length > workDisplayCount) {
            // إظهار مؤشر التحميل
            const loadMoreBtn = document.getElementById('load-more-works-btn');
            if (loadMoreBtn) {
                loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري التحميل...';
                loadMoreBtn.disabled = true;
            }

            // تأخير قصير لإظهار مؤشر التحميل
            setTimeout(() => {
                loadMoreWorks();
            }, 200);
        }
    }
});

// اختيار مادة من نتائج البحث
function selectMaterial(id, itemNumber, description) {
    document.getElementById('material_id').value = id;
    document.getElementById('material_search_input').value = itemNumber;
    document.getElementById('selected_material_text').textContent = `${itemNumber} - ${description}`;
    document.getElementById('selected_material_info').style.display = 'block';
    document.getElementById('material_dropdown').classList.remove('show');
}

// اختيار بند عمل من نتائج البحث
function selectWork(id, itemNumber, description) {
    document.getElementById('work_item_id').value = id;
    document.getElementById('work_search_input').value = itemNumber;
    document.getElementById('selected_work_text').textContent = `${itemNumber} - ${description}`;
    document.getElementById('selected_work_info').style.display = 'block';
    document.getElementById('work_dropdown').classList.remove('show');
}

// إخفاء القوائم المنسدلة عند النقر خارجها
document.addEventListener('click', function(e) {
    if (!e.target.closest('#material_search_input') && !e.target.closest('#material_dropdown')) {
        document.getElementById('material_dropdown').classList.remove('show');
    }
    if (!e.target.closest('#work_search_input') && !e.target.closest('#work_dropdown')) {
        document.getElementById('work_dropdown').classList.remove('show');
    }
});

// إعادة تعيين النموذج عند إغلاق المودال
document.getElementById('addRelationshipModal').addEventListener('hidden.bs.modal', function() {
    // إعادة تعيين حقول البحث
    document.getElementById('material_search_input').value = '';
    document.getElementById('work_search_input').value = '';
    document.getElementById('material_id').value = '';
    document.getElementById('work_item_id').value = '';

    // إعادة تعيين متغيرات البحث
    currentMaterialResults = [];
    currentWorkResults = [];
    materialDisplayCount = 10;
    workDisplayCount = 10;

    // إخفاء معلومات الاختيار
    document.getElementById('selected_material_info').style.display = 'none';
    document.getElementById('selected_work_info').style.display = 'none';

    // إخفاء القوائم المنسدلة
    document.getElementById('material_dropdown').classList.remove('show');
    document.getElementById('work_dropdown').classList.remove('show');

    // إعادة تعيين باقي الحقول
    document.getElementById('quantity_ratio').value = '1.0000';
    document.getElementById('is_primary').checked = false;
    document.getElementById('notes').value = '';
});

// التحقق من صحة النموذج قبل الإرسال
document.querySelector('#addRelationshipModal form').addEventListener('submit', function(e) {
    const materialId = document.getElementById('material_id').value;
    const workItemId = document.getElementById('work_item_id').value;

    if (!materialId) {
        e.preventDefault();
        Swal.fire({
            title: 'خطأ في البيانات',
            text: 'يرجى اختيار مادة',
            icon: 'error',
            confirmButtonText: 'موافق'
        });
        return false;
    }

    if (!workItemId) {
        e.preventDefault();
        Swal.fire({
            title: 'خطأ في البيانات',
            text: 'يرجى اختيار بند عمل',
            icon: 'error',
            confirmButtonText: 'موافق'
        });
        return false;
    }

    // إظهار loading على الزر
    const saveBtn = document.getElementById('save_relationship_btn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ...';
    saveBtn.disabled = true;

    // إعادة تفعيل الزر في حالة فشل الإرسال
    setTimeout(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }, 5000);
});
</script>
