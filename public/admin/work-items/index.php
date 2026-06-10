<?php
/**
 * صفحة إدارة بنود الأعمال
 * Work Items Management Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إدارة بنود الأعمال';
$currentPage = 'work-items';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الإدارة', 'url' => 'admin/index.php'],
    ['title' => 'إدارة بنود الأعمال', 'url' => 'admin/work-items/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$error = '';
$success = '';

try {
    $db = getDB();
    
    // معالجة الحذف
    if (isset($_POST['delete_item']) && isset($_POST['item_id'])) {
        $itemId = (int)$_POST['item_id'];
        
        // التحقق من عدم استخدام البند في شهادات الإنجاز
        $usageCheck = $db->prepare("SELECT COUNT(*) FROM completion_certificate_works WHERE work_item_id = ?");
        $usageCheck->execute([$itemId]);
        $usageCount = $usageCheck->fetchColumn();
        
        if ($usageCount > 0) {
            $error = "لا يمكن حذف هذا البند لأنه مستخدم في $usageCount شهادة إنجاز";
        } else {
            $deleteStmt = $db->prepare("DELETE FROM work_items WHERE id = ?");
            if ($deleteStmt->execute([$itemId])) {
                $success = "تم حذف البند بنجاح";
            } else {
                $error = "حدث خطأ أثناء حذف البند";
            }
        }
    }
    
    // معالجة تغيير الحالة
    if (isset($_POST['toggle_status']) && isset($_POST['item_id'])) {
        $itemId = (int)$_POST['item_id'];
        $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;
        
        $updateStmt = $db->prepare("UPDATE work_items SET is_active = ? WHERE id = ?");
        if ($updateStmt->execute([$newStatus, $itemId])) {
            $success = $newStatus ? "تم تفعيل البند بنجاح" : "تم إلغاء تفعيل البند بنجاح";
        } else {
            $error = "حدث خطأ أثناء تحديث حالة البند";
        }
    }
    
    // جلب جميع البيانات (DataTable سيتولى البحث والتصفية والتصفح)
    $dataQuery = "SELECT * FROM work_items ORDER BY item_number";
    $workItems = $db->query($dataQuery)->fetchAll();

    // إحصائيات بسيطة
    $totalItems = count($workItems);

} catch (Exception $e) {
    $error = 'خطأ في تحميل البيانات: ' . $e->getMessage();
    $workItems = [];
    $totalItems = 0;
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-tools text-primary me-2"></i>
                إدارة بنود الأعمال
            </h1>
            <p class="text-muted mb-0">إدارة وتحرير بنود الأعمال الكهربائية</p>
        </div>
        <div>
            <a href="create.php" class="btn btn-primary me-2">
                <i class="fas fa-plus me-1"></i>
                إضافة بند جديد
            </a>
            <div class="btn-group">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-file-excel me-1"></i>
                    Excel
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="import.php">
                        <i class="fas fa-upload me-2"></i>استيراد من Excel
                    </a></li>
                    <li><a class="dropdown-item" href="export.php">
                        <i class="fas fa-download me-2"></i>تصدير إلى Excel
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>



    <!-- جدول البيانات -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                بنود الأعمال (<?= number_format($totalItems) ?> بند)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="workItemsTable" class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>رقم البند</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>الفئة</th>
                            <th>السعر المعياري</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workItems)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <br>لا توجد بنود أعمال
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($workItems as $item): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-primary"><?= htmlspecialchars($item['item_number']) ?></span>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($item['description']) ?>">
                                    <?= htmlspecialchars($item['description']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($item['unit']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($item['category'] ?? 'غير محدد') ?></span>
                            </td>
                            <td>
                                <span class="fw-bold text-success"><?= number_format($item['standard_price'], 2) ?> ريال</span>
                            </td>
                            <td>
                                <?php if ($item['is_active']): ?>
                                <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                <span class="badge bg-danger">غير نشط</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= date('Y-m-d', strtotime($item['created_at'])) ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-warning" 
                                            onclick="toggleStatus(<?= $item['id'] ?>, <?= $item['is_active'] ? 0 : 1 ?>)" 
                                            title="<?= $item['is_active'] ? 'إلغاء التفعيل' : 'تفعيل' ?>">
                                        <i class="fas fa-<?= $item['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="deleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['item_number']) ?>')" 
                                            title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
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

<!-- نماذج مخفية للحذف وتغيير الحالة -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_item" value="1">
    <input type="hidden" name="item_id" id="deleteItemId">
</form>

<form id="toggleForm" method="POST" style="display: none;">
    <input type="hidden" name="toggle_status" value="1">
    <input type="hidden" name="item_id" id="toggleItemId">
    <input type="hidden" name="new_status" id="toggleNewStatus">
</form>

<script>
function deleteItem(itemId, itemNumber) {
    if (confirm(`هل أنت متأكد من حذف البند "${itemNumber}"؟\n\nهذا الإجراء لا يمكن التراجع عنه.`)) {
        document.getElementById('deleteItemId').value = itemId;
        document.getElementById('deleteForm').submit();
    }
}

function toggleStatus(itemId, newStatus) {
    const action = newStatus ? 'تفعيل' : 'إلغاء تفعيل';
    if (confirm(`هل أنت متأكد من ${action} هذا البند؟`)) {
        document.getElementById('toggleItemId').value = itemId;
        document.getElementById('toggleNewStatus').value = newStatus;
        document.getElementById('toggleForm').submit();
    }
}
</script>

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
    if ($('#workItemsTable').length) {
        $('#workItemsTable').DataTable({
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

// دوال إدارة بنود الأعمال
function editWorkItem(id) {
    window.location.href = `edit.php?id=${id}`;
}

function toggleWorkItemStatus(id, currentStatus) {
    const action = currentStatus === 'active' ? 'إلغاء تفعيل' : 'تفعيل';
    const actionType = currentStatus === 'active' ? 'inactive' : 'active';

    Swal.fire({
        title: 'تأكيد العملية',
        text: `هل أنت متأكد من ${action} هذا البند؟`,
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
                text: `جاري ${action} البند`,
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
                    status: actionType
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

function deleteWorkItem(id) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذا البند؟ لا يمكن التراجع عن هذا الإجراء!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إظهار loading
            Swal.fire({
                title: 'جاري الحذف...',
                text: 'جاري حذف البند',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال الطلب
            fetch('delete-ajax.php', {
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
