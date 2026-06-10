<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'أنواع أوامر العمل';
$currentPage = 'work-order-types';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أنواع أوامر العمل', 'url' => 'work-order-types/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// جلب البيانات
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM work_order_types ORDER BY type_code");
    $workOrderTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $workOrderTypes = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">إدارة أنواع أوامر العمل</p>
    </div>
    <div>
        <!-- Import/Export Dropdown -->
        <div class="btn-group me-2" role="group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-exchange-alt me-2"></i>
                استيراد/تصدير
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item" href="#" onclick="openExportModal()">
                        <i class="fas fa-download me-2"></i>
                        تصدير البيانات
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="import.php">
                        <i class="fas fa-upload me-2"></i>
                        استيراد البيانات
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="sample-import.csv" download>
                        <i class="fas fa-file-csv me-2"></i>
                        تحميل نموذج CSV
                    </a>
                </li>
            </ul>
        </div>

        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus me-2"></i>
            إضافة نوع جديد
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-primary text-uppercase mb-1">
                            إجمالي الأنواع
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= count($workOrderTypes) ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-success text-uppercase mb-1">
                            أنواع نشطة
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= count(array_filter($workOrderTypes, fn($type) => $type['status'] === 'active')) ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="small fw-bold text-warning text-uppercase mb-1">
                            أنواع غير نشطة
                        </div>
                        <div class="h5 mb-0 fw-bold text-gray-800">
                            <?= count(array_filter($workOrderTypes, fn($type) => $type['status'] === 'inactive')) ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-pause-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Work Order Types Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-list me-2"></i>
            قائمة أنواع أوامر العمل
            <span class="badge bg-primary ms-2"><?= count($workOrderTypes) ?> نوع</span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="workOrderTypesTable" width="100%" cellspacing="0">
                <thead class="table-dark">
                    <tr>
                        <th>الرمز</th>
                        <th>الوصف</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>آخر تحديث</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workOrderTypes as $type): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?= htmlspecialchars($type['type_code']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($type['description']) ?></td>
                            <td>
                                <?php if ($type['status'] === 'active'): ?>
                                    <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">غير نشط</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= date('Y-m-d H:i', strtotime($type['created_at'])) ?>
                            </td>
                            <td>
                                <?= date('Y-m-d H:i', strtotime($type['updated_at'])) ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="viewType(<?= $type['id'] ?>)" 
                                            title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="editType(<?= $type['id'] ?>)"
                                            title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-<?= $type['status'] === 'active' ? 'warning' : 'success' ?>"
                                            onclick="toggleStatus(<?= $type['id'] ?>, '<?= $type['status'] ?>')"
                                            title="<?= $type['status'] === 'active' ? 'إلغاء التفعيل' : 'تفعيل' ?>">
                                        <i class="fas fa-<?= $type['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteType(<?= $type['id'] ?>, '<?= htmlspecialchars($type['type_code']) ?>')"
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

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createModalLabel">
                    <i class="fas fa-plus me-2"></i>
                    إضافة نوع أمر عمل جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createWorkOrderTypeForm" onsubmit="submitCreateForm(event)">
                    <!-- Type Code -->
                    <div class="mb-3">
                        <label for="create_code" class="form-label">
                            <i class="fas fa-code me-2"></i>
                            كود النوع *
                        </label>
                        <input type="text" class="form-control" id="create_code" name="code"
                               required maxlength="10" placeholder="مثال: WO-001"
                               pattern="[A-Za-z0-9]+" title="أحرف إنجليزية وأرقام فقط">
                        <div class="form-text">
                            كود فريد لنوع أمر العمل (2-10 أحرف، أحرف إنجليزية وأرقام فقط)
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="create_description" class="form-label">
                            <i class="fas fa-align-left me-2"></i>
                            الوصف
                        </label>
                        <textarea class="form-control" id="create_description" name="description"
                                  rows="4" maxlength="500" placeholder="وصف نوع أمر العمل (اختياري)"></textarea>
                        <div class="form-text">
                            وصف تفصيلي لنوع أمر العمل (الحد الأقصى 500 حرف)
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-toggle-on me-2"></i>
                            الحالة
                        </label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="create_status" name="status" checked>
                            <label class="form-check-label" for="create_status">
                                نشط
                            </label>
                        </div>
                        <div class="form-text">
                            تحديد ما إذا كان نوع أمر العمل نشطاً أم لا
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>
                            إلغاء
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            إضافة النوع
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class="fas fa-eye me-2"></i>
                    تفاصيل نوع أمر العمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalContent">
                <!-- سيتم تحميل المحتوى هنا -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">
                    <i class="fas fa-edit me-2"></i>
                    تعديل نوع أمر العمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editModalContent">
                <!-- سيتم تحميل المحتوى هنا -->
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">
                    <i class="fas fa-download me-2"></i>
                    تصدير أنواع أوامر العمل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <!-- Export Format -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-file me-2"></i>
                            صيغة التصدير
                        </label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="format_excel" value="excel" checked>
                            <label class="form-check-label" for="format_excel">
                                <i class="fas fa-file-excel text-success me-2"></i>
                                Excel (.xlsx)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="format_csv" value="csv">
                            <label class="form-check-label" for="format_csv">
                                <i class="fas fa-file-csv text-info me-2"></i>
                                CSV (.csv)
                            </label>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-filter me-2"></i>
                            تصفية حسب الحالة
                        </label>
                        <select class="form-select" name="status" id="export_status">
                            <option value="all">جميع الأنواع</option>
                            <option value="active" selected>الأنواع النشطة فقط</option>
                            <option value="inactive">الأنواع غير النشطة فقط</option>
                        </select>
                    </div>

                    <!-- Additional Options -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_inactive" name="include_inactive">
                            <label class="form-check-label" for="include_inactive">
                                تضمين الأنواع غير النشطة
                            </label>
                        </div>
                    </div>

                    <!-- Export Info -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>معلومات التصدير:</strong>
                        <ul class="mb-0 mt-2">
                            <li>سيتم تصدير <?= count($workOrderTypes) ?> نوع أمر عمل</li>
                            <li>يشمل التصدير: الكود، الوصف، الحالة، وتواريخ الإنشاء والتحديث</li>
                            <li>يمكن استخدام الملف المصدر لاستيراد البيانات لاحقاً</li>
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    إلغاء
                </button>
                <button type="button" class="btn btn-success" onclick="startExport()">
                    <i class="fas fa-download me-2"></i>
                    بدء التصدير
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
$(document).ready(function() {
    // التحقق من عدم تهيئة DataTable مسبقاً
    if (!$.fn.DataTable.isDataTable('#workOrderTypesTable')) {
        // تهيئة DataTable بطريقة مباشرة
        $('#workOrderTypesTable').DataTable({
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
    } else {
        console.log('DataTable already initialized');
    }
});

// دوال أساسية
function openCreateModal() {
    const modal = new bootstrap.Modal(document.getElementById('createModal'));
    modal.show();

    // إعادة تعيين النموذج
    document.getElementById('createWorkOrderTypeForm').reset();
    document.getElementById('create_status').checked = true;
}

function openExportModal() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();

    // إعادة تعيين النموذج
    document.getElementById('exportForm').reset();
    document.getElementById('format_excel').checked = true;
    document.getElementById('export_status').value = 'active';
}

function startExport() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);

    // بناء URL التصدير
    const format = formData.get('format');
    const status = formData.get('status');
    const includeInactive = formData.get('include_inactive') ? '1' : '0';

    let exportUrl = `export.php?format=${format}&status=${status}`;
    if (includeInactive === '1') {
        exportUrl += '&include_inactive=1';
    }

    // إغلاق النافذة المنبثقة
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();

    // إظهار رسالة تحميل
    Swal.fire({
        title: 'جاري التصدير...',
        text: 'يتم تحضير ملف التصدير',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // بدء التحميل
    window.open(exportUrl, '_blank');

    // إغلاق رسالة التحميل بعد ثانيتين
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'تم بدء التصدير',
            text: 'سيتم تحميل الملف قريباً',
            timer: 2000,
            showConfirmButton: false
        });
    }, 2000);
}

function viewType(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    const content = document.getElementById('viewModalContent');

    // إظهار loading
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">جاري التحميل...</span></div></div>';
    modal.show();

    // تحميل المحتوى
    fetch(`view-modal.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>حدث خطأ أثناء تحميل البيانات</div>';
            console.error('Error:', error);
        });
}

function editType(id) {
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    const content = document.getElementById('editModalContent');

    // إظهار loading
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">جاري التحميل...</span></div></div>';
    modal.show();

    // تحميل المحتوى
    fetch(`edit-modal.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>حدث خطأ أثناء تحميل البيانات</div>';
            console.error('Error:', error);
        });
}

function toggleStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'إلغاء تفعيل' : 'تفعيل';
    const actionType = currentStatus === 'active' ? 'deactivate' : 'activate';

    Swal.fire({
        title: 'تأكيد العملية',
        text: `هل أنت متأكد من ${action} هذا النوع؟`,
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
                text: `جاري ${action} النوع`,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال الطلب
            fetch('toggle-status.php', {
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

function deleteType(id, code) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: `هل أنت متأكد من حذف نوع أمر العمل "${code}"؟`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إظهار loading
            Swal.fire({
                title: 'جاري الحذف...',
                text: 'جاري حذف نوع أمر العمل',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال الطلب
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف',
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

// دالة إنشاء نوع جديد
function submitCreateForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    // تحويل checkbox إلى قيمة نصية
    const statusCheckbox = form.querySelector('#create_status');
    formData.set('status', statusCheckbox.checked ? 'active' : 'inactive');

    // إظهار loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الإضافة...';
    submitBtn.disabled = true;

    fetch('create-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إغلاق Modal وإعادة تحميل الصفحة
            bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();

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
    })
    .finally(() => {
        // إعادة تعيين الزر
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// دالة تعديل نوع (ستستدعى من edit-modal.php)
function submitEditForm(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    // تحويل checkbox إلى قيمة نصية
    const statusCheckbox = form.querySelector('#edit_status');
    formData.set('status', statusCheckbox.checked ? 'active' : 'inactive');

    // إظهار loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...';
    submitBtn.disabled = true;

    fetch('update-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إغلاق Modal وإعادة تحميل الصفحة
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();

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
    })
    .finally(() => {
        // إعادة تعيين الزر
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}
</script>
