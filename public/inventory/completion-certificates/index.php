<?php
/**
 * صفحة فهرس شهادات الإنجاز
 * Completion Certificates Index Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'شهادات الإنجاز';
$currentPage = 'completion-certificates';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'شهادات الإنجاز', 'url' => 'inventory/completion-certificates/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_certificates_view')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$error = '';
$success = '';

// معالجة الفلاتر
$statusFilter = $_GET['status'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$searchTerm = $_GET['search'] ?? '';

try {
    $db = getDB();
    
    // جلب الإحصائيات
    $statsQuery = "
        SELECT
            COUNT(*) as total_certificates,
            COUNT(CASE WHEN cc.status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN cc.status = 'completed' THEN 1 END) as completed_count
        FROM completion_certificates cc
        JOIN work_orders wo ON cc.work_order_id = wo.id
        WHERE wo.status = 'active'
    ";
    
    $params = [];
    
    if ($statusFilter) {
        $statsQuery .= " AND cc.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($departmentFilter) {
        $statsQuery .= " AND wo.department = ?";
        $params[] = $departmentFilter;
    }
    
    if ($dateFrom) {
        $statsQuery .= " AND cc.certificate_date >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $statsQuery .= " AND cc.certificate_date <= ?";
        $params[] = $dateTo;
    }
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();
    
    // جلب قائمة الشهادات مع كود نوع أمر العمل
    $certificatesQuery = "
        SELECT
            cc.*,
            wo.work_order_number,
            wo.department,
            wo.location as work_order_location,
            wot.type_code as work_order_type_code,
            wot.description as work_order_type_description,
            b.name as branch_name,
            ce.name as current_entity_name,
            u.username as created_by_name,
            (SELECT COUNT(*) FROM completion_certificate_materials WHERE certificate_id = cc.id) as materials_count,
            (SELECT COUNT(*) FROM completion_certificate_works WHERE certificate_id = cc.id) as works_count
        FROM completion_certificates cc
        JOIN work_orders wo ON cc.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
        LEFT JOIN users u ON cc.created_by = u.id
        WHERE wo.status = 'active'
    ";
    
    $listParams = [];
    
    if ($statusFilter) {
        $certificatesQuery .= " AND cc.status = ?";
        $listParams[] = $statusFilter;
    }
    
    if ($departmentFilter) {
        $certificatesQuery .= " AND wo.department = ?";
        $listParams[] = $departmentFilter;
    }
    
    if ($dateFrom) {
        $certificatesQuery .= " AND cc.certificate_date >= ?";
        $listParams[] = $dateFrom;
    }
    
    if ($dateTo) {
        $certificatesQuery .= " AND cc.certificate_date <= ?";
        $listParams[] = $dateTo;
    }
    
    if ($searchTerm) {
        $certificatesQuery .= " AND (cc.title LIKE ? OR wo.work_order_number LIKE ? OR b.name LIKE ?)";
        $searchParam = "%$searchTerm%";
        $listParams[] = $searchParam;
        $listParams[] = $searchParam;
        $listParams[] = $searchParam;
    }
    
    $certificatesQuery .= " ORDER BY cc.created_at DESC LIMIT 50";
    
    $certificatesStmt = $db->prepare($certificatesQuery);
    $certificatesStmt->execute($listParams);
    $certificates = $certificatesStmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'خطأ في جلب البيانات: ' . $e->getMessage();
    $stats = [
        'total_certificates' => 0,
        'in_progress_count' => 0,
        'completed_count' => 0
    ];
    $certificates = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<!-- الإحصائيات -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= number_format($stats['total_certificates']) ?></h4>
                        <p class="mb-0">إجمالي الشهادات</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-certificate fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= number_format($stats['in_progress_count']) ?></h4>
                        <p class="mb-0">جاري الإعداد</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?= number_format($stats['completed_count']) ?></h4>
                        <p class="mb-0">مكتملة</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- مساحة فارغة -->
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

<!-- الفلاتر والبحث -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>
            البحث والفلاتر
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">البحث</label>
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                       placeholder="الموقع، رقم أمر العمل، الفرع...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select class="form-select" name="status">
                    <option value="">جميع الحالات</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>جاري الإعداد</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">القسم</label>
                <select class="form-select" name="department">
                    <option value="">جميع الأقسام</option>
                    <option value="connections" <?= $departmentFilter === 'connections' ? 'selected' : '' ?>>التوصيلات</option>
                    <option value="projects" <?= $departmentFilter === 'projects' ? 'selected' : '' ?>>المشاريع</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">من تاريخ</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- قائمة الشهادات -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-list me-2"></i>
            قائمة شهادات الإنجاز
        </h5>
        <?php if (hasPermission('inventory_certificates_create')): ?>
        <a href="create.php" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>
            إنشاء شهادة جديدة
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($certificates)): ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">لا توجد شهادات إنجاز</h5>
            <p class="text-muted">لم يتم العثور على أي شهادات إنجاز تطابق معايير البحث</p>
            <?php if (hasPermission('inventory_certificates_create')): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                إنشاء أول شهادة
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table id="completionCertificatesTable" class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>رقم أمر العمل</th>
                        <th>نوع الأمر</th>
                        <th>الموقع</th>
                        <th>الفرع</th>
                        <th>القسم</th>
                        <th>تاريخ الشهادة</th>
                        <th>الحالة</th>
                        <th>المواد</th>
                        <th>الأعمال</th>
                        <th>المنشئ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificates as $cert): ?>
                    <tr>
                        <td>
                            <strong class="text-primary"><?= htmlspecialchars($cert['work_order_number']) ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-primary" title="<?= htmlspecialchars($cert['work_order_type_description'] ?? '') ?>">
                                <?= htmlspecialchars($cert['work_order_type_code'] ?? 'غير محدد') ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($cert['work_order_location'])): ?>
                                <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                <?= htmlspecialchars($cert['work_order_location']) ?>
                            <?php else: ?>
                                <span class="text-muted">غير محدد</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($cert['branch_name'] ?? 'غير محدد') ?></td>
                        <td>
                            <span class="badge bg-<?= $cert['department'] === 'connections' ? 'info' : 'warning' ?>">
                                <?= $cert['department'] === 'connections' ? 'التوصيلات' : 'المشاريع' ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d', strtotime($cert['certificate_date'])) ?></td>
                        <td>
                            <span class="badge bg-<?= $cert['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <i class="fas fa-<?= $cert['status'] === 'completed' ? 'check-circle' : 'clock' ?> me-1"></i>
                                <?= $cert['status'] === 'completed' ? 'مكتمل' : 'جاري الإعداد' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= $cert['materials_count'] ?> مادة</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= $cert['works_count'] ?> عمل</span>
                        </td>
                        <td><?= htmlspecialchars($cert['created_by_name']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="view.php?id=<?= $cert['id'] ?>" class="btn btn-outline-primary" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (hasPermission('inventory_certificates_edit') && $cert['status'] === 'in_progress'): ?>
                                <a href="edit.php?id=<?= $cert['id'] ?>" class="btn btn-outline-warning" title="تحرير">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasPermission('inventory_certificates_status_update')): ?>
                                <button type="button" class="btn btn-outline-<?= $cert['status'] === 'completed' ? 'warning' : 'success' ?>"
                                        onclick="updateStatus(<?= $cert['id'] ?>, '<?= $cert['status'] === 'completed' ? 'in_progress' : 'completed' ?>')"
                                        title="<?= $cert['status'] === 'completed' ? 'إعادة لجاري الإعداد' : 'تحديد كمكتمل' ?>">
                                    <i class="fas fa-<?= $cert['status'] === 'completed' ? 'undo' : 'check' ?>"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (hasPermission('inventory_certificates_delete') && $cert['status'] === 'in_progress'): ?>
                                <button type="button" class="btn btn-outline-danger"
                                        onclick="deleteCertificate(<?= $cert['id'] ?>)" title="حذف">
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
        <?php endif; ?>
    </div>
</div>

<script>
// تحديث حالة الشهادة
function updateStatus(certificateId, newStatus) {
    const statusText = newStatus === 'completed' ? 'مكتمل' : 'جاري الإعداد';

    if (confirm(`هل أنت متأكد من تغيير حالة الشهادة إلى "${statusText}"؟`)) {
        fetch('update-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                certificate_id: certificateId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            alert('خطأ في الاتصال: ' + error.message);
        });
    }
}

// حذف الشهادة
function deleteCertificate(certificateId) {
    if (confirm('هل أنت متأكد من حذف هذه الشهادة؟ لا يمكن التراجع عن هذا الإجراء.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                certificate_id: certificateId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            alert('خطأ في الاتصال: ' + error.message);
        });
    }
}
</script>

<?php
$content = ob_get_clean();
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
    if ($('#completionCertificatesTable').length) {
        $('#completionCertificatesTable').DataTable({
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
</script>
