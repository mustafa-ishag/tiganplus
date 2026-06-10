<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'إدارة الفروع';
$currentPage = 'branches';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الفروع', 'url' => 'branches/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('branches_view')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

// جلب الفروع
try {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $branches = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">إدارة الفروع والمواقع</p>
    </div>
    <div>
        <?php if (hasPermission('branches_create')): ?>
        <a href="<?= path('branches/create.php') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            إضافة فرع جديد
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Branches Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-list me-2"></i>
            قائمة الفروع
            <span class="badge bg-primary ms-2"><?= count($branches) ?> فرع</span>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="branchesTable" class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>المعرف</th>
                        <th>رمز الفرع</th>
                        <th>اسم الفرع</th>
                        <th>الملاحظات</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                    <tr>
                        <td><?= $branch['id'] ?></td>
                        <td>
                            <span class="badge bg-info">
                                <?= htmlspecialchars($branch['code']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($branch['name']) ?></td>
                        <td><?= htmlspecialchars($branch['notes'] ?? '-') ?></td>
                        <td>
                            <?php if ($branch['status'] === 'active'): ?>
                                <span class="badge bg-success">نشط</span>
                            <?php else: ?>
                                <span class="badge bg-warning">غير نشط</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('Y-m-d H:i', strtotime($branch['created_at'])) ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <?php if (hasPermission('branches_view_details')): ?>
                                <button type="button" class="btn btn-sm btn-outline-info"
                                        onclick="viewBranch(<?= $branch['id'] ?>)" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php endif; ?>

                                <?php if (hasPermission('branches_edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick="editBranch(<?= $branch['id'] ?>)" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>

                                <?php if (hasPermission('branches_delete')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="deleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars($branch['name']) ?>')" title="حذف">
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



<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<script>
$(document).ready(function() {
    // التحقق من عدم تهيئة DataTable مسبقاً
    if (!$.fn.DataTable.isDataTable('#branchesTable')) {
        // تهيئة DataTable مع الترجمة العربية المحلية
        $('#branchesTable').DataTable({
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
            "order": [[0, 'desc']],
            "columnDefs": [
                { "orderable": false, "targets": -1 }
            ]
        });
    }
});

// دوال أساسية
function viewBranch(id) {
    // إنشاء modal لعرض تفاصيل الفرع
    Swal.fire({
        title: 'عرض تفاصيل الفرع',
        html: '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">جاري التحميل...</span></div></div>',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            // تحميل تفاصيل الفرع
            fetch(`view.php?id=${id}&ajax=1`)
                .then(response => response.text())
                .then(html => {
                    Swal.update({
                        html: html,
                        showConfirmButton: true,
                        confirmButtonText: 'إغلاق'
                    });
                })
                .catch(error => {
                    Swal.update({
                        icon: 'error',
                        title: 'خطأ',
                        html: 'حدث خطأ أثناء تحميل البيانات',
                        showConfirmButton: true,
                        confirmButtonText: 'موافق'
                    });
                });
        }
    });
}

function editBranch(id) {
    window.location.href = `edit.php?id=${id}`;
}

function deleteBranch(id, name) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: `هل أنت متأكد من حذف الفرع "${name}"؟`,
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
                text: 'جاري حذف الفرع',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال طلب الحذف
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
</script>
