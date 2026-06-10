<?php
/**
 * صفحة إدارة المستخدمين
 * Users Management Page
 */

session_start();

require_once __DIR__ . '/../../includes/path-helper.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Role.php';

$pageTitle = 'إدارة المستخدمين';
$currentPage = 'users';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '../dashboard.php'],
    ['title' => 'إدارة النظام', 'url' => '../admin/'],
    ['title' => 'المستخدمين', 'url' => '']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('manage_users') && !hasPermission('view_users')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لعرض المستخدمين';
    header('Location: ' . path('dashboard.php'));
    exit();
}

// معالجة الفلاتر
$status = $_GET['status'] ?? 'all';
$role = $_GET['role'] ?? 'all';
$branch = $_GET['branch'] ?? 'all';
$search = $_GET['search'] ?? '';

// جلب البيانات
try {
    $db = getDB();
    $userModel = new User();
    $roleModel = new Role();

    // بناء شروط البحث
    $whereConditions = [];
    $params = [];

    if ($status !== 'all') {
        $whereConditions[] = "u.status = ?";
        $params[] = $status;
    }

    if ($role !== 'all') {
        $whereConditions[] = "ur.role_id = ?";
        $params[] = $role;
    }

    if ($branch !== 'all') {
        $whereConditions[] = "u.branch_id = ?";
        $params[] = $branch;
    }

    if (!empty($search)) {
        $whereConditions[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // جلب المستخدمين مع بيانات الفروع والأدوار
    $usersQuery = "
        SELECT
            u.*,
            b.name as branch_name,
            b.code as branch_code,
            GROUP_CONCAT(DISTINCT r.display_name SEPARATOR ', ') as roles,
            GROUP_CONCAT(DISTINCT r.id SEPARATOR ',') as role_ids
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        $whereClause
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    $users = $db->prepare($usersQuery);
    $users->execute($params);
    $users = $users->fetchAll();

    // حساب الإحصائيات
    $stats = [
        'total' => $userModel->count(),
        'active' => $userModel->count('status = ?', ['active']),
        'inactive' => $userModel->count('status = ?', ['inactive']),
        'suspended' => $userModel->count('status = ?', ['suspended']),
        'online' => $userModel->count('last_login > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND status = ?', ['active'])
    ];

    // جلب الفروع للقائمة المنسدلة
    $branches = $db->query("SELECT * FROM branches ORDER BY name")->fetchAll();

    // جلب الأدوار للقائمة المنسدلة
    $roles = $db->query("SELECT * FROM roles ORDER BY level DESC, display_name")->fetchAll();
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $users = [];
    $branches = [];
    $roles = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-2 text-gray-800">
                <i class="fas fa-users me-2"></i>
                إدارة المستخدمين
            </h1>
            <p class="text-muted mb-0">إدارة حسابات المستخدمين والصلاحيات والأدوار</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if (hasPermission('manage_users')): ?>
                <a href="create_new.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    إضافة مستخدم جديد
                </a>
                <a href="../roles/index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-user-tag me-1"></i>
                    إدارة الأدوار
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي المستخدمين
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($stats['total']) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                المستخدمون النشطون
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($stats['active']) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                متصل الآن
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($stats['online']) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wifi fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                غير نشط/معلق
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($stats['inactive'] + $stats['suspended']) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- فلاتر البحث -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter me-2"></i>
                فلاتر البحث
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">الحالة</label>
                    <select name="status" id="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>جميع الحالات</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>نشط</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>معلق</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">الدور</label>
                    <select name="role" id="role" class="form-select">
                        <option value="all" <?= $role === 'all' ? 'selected' : '' ?>>جميع الأدوار</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $role == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['display_name'] ?? $r['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="branch" class="form-label">الفرع</label>
                    <select name="branch" id="branch" class="form-select">
                        <option value="all" <?= $branch === 'all' ? 'selected' : '' ?>>جميع الفروع</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $branch == $b['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search" class="form-label">البحث</label>
                    <div class="input-group">
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="اسم المستخدم، الاسم، أو البريد الإلكتروني"
                               value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        تطبيق الفلاتر
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>
                        إعادة تعيين
                    </a>
                    <?php if (hasPermission('export_users')): ?>
                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-1"></i>
                            تصدير Excel
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول المستخدمين -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                قائمة المستخدمين (<?= count($users) ?> مستخدم)
            </h6>
            <div class="d-flex gap-2">
                <?php if (hasPermission('manage_users')): ?>
                    <button class="btn btn-outline-primary btn-sm" onclick="bulkActions()">
                        <i class="fas fa-tasks me-1"></i>
                        إجراءات متعددة
                    </button>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                            data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i>
                        تصدير
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportToCSV()">
                            <i class="fas fa-file-csv me-1"></i> CSV
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <?php if (hasPermission('manage_users')): ?>
                                <th width="30">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                            <?php endif; ?>
                            <th width="60">#</th>
                            <th>اسم المستخدم</th>
                            <th>الاسم الكامل</th>
                            <th>البريد الإلكتروني</th>
                            <th>الهاتف</th>
                            <th>الفرع</th>
                            <th>الأدوار</th>
                            <th width="100">الحالة</th>
                            <th width="120">آخر دخول</th>
                            <th width="150">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="<?= hasPermission('manage_users') ? '11' : '10' ?>" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-users fa-3x mb-3"></i>
                                        <p class="mb-0">لا توجد مستخدمين مطابقين للفلاتر المحددة</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <?php if (hasPermission('manage_users')): ?>
                                    <td>
                                        <input type="checkbox" class="form-check-input user-checkbox"
                                               value="<?= $user['id'] ?>">
                                    </td>
                                <?php endif; ?>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2">
                                            <div class="avatar-title bg-primary rounded-circle">
                                                <?= strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            <?php if ($user['last_login'] && strtotime($user['last_login']) > strtotime('-15 minutes')): ?>
                                                <span class="badge bg-success ms-1" title="متصل الآن">
                                                    <i class="fas fa-circle fa-xs"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?= htmlspecialchars($user['full_name'] ?? '') ?>
                                        <?php if ($user['position']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($user['position']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['email']): ?>
                                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?= htmlspecialchars($user['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['phone']): ?>
                                        <a href="tel:<?= htmlspecialchars($user['phone']) ?>" class="text-decoration-none">
                                            <i class="fas fa-phone me-1"></i>
                                            <?= htmlspecialchars($user['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">غير محدد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['branch_name']): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-building me-1"></i>
                                            <?= htmlspecialchars($user['branch_name']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">جميع الفروع</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['roles']): ?>
                                        <?php foreach (explode(', ', $user['roles']) as $role): ?>
                                            <span class="badge bg-secondary me-1 mb-1">
                                                <i class="fas fa-user-tag me-1"></i>
                                                <?= htmlspecialchars($role) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">لا يوجد دور</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = match($user['status']) {
                                        'active' => 'bg-success',
                                        'inactive' => 'bg-secondary',
                                        'suspended' => 'bg-warning',
                                        default => 'bg-dark'
                                    };
                                    $statusText = match($user['status']) {
                                        'active' => 'نشط',
                                        'inactive' => 'غير نشط',
                                        'suspended' => 'معلق',
                                        default => 'غير محدد'
                                    };
                                    ?>
                                    <span class="badge <?= $statusClass ?>">
                                        <i class="fas fa-circle me-1"></i>
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <div class="text-muted">
                                            <small>
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('Y-m-d', strtotime($user['last_login'])) ?>
                                                <br>
                                                <?= date('H:i', strtotime($user['last_login'])) ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-minus-circle me-1"></i>
                                            لم يسجل دخول
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="viewUser(<?= $user['id'] ?>)"
                                                title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (hasPermission('manage_users') || $user['id'] == $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="editUser(<?= $user['id'] ?>)"
                                                    title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (hasPermission('manage_users')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                    onclick="manageUserRoles(<?= $user['id'] ?>)"
                                                    title="إدارة الأدوار">
                                                <i class="fas fa-user-cog"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteUser(<?= $user['id'] ?>)"
                                                        title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<script>
// تهيئة DataTable
$(document).ready(function() {
    $('#usersTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json"
        },
        "order": [[ <?= hasPermission('manage_users') ? '1' : '0' ?>, "desc" ]],
        "pageLength": 25,
        "responsive": true,
        "dom": 'Bfrtip',
        "buttons": [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-info btn-sm'
            }
        ]
    });

    // تحديد/إلغاء تحديد الكل
    $('#selectAll').change(function() {
        $('.user-checkbox').prop('checked', this.checked);
    });

    // تحديث حالة "تحديد الكل" عند تغيير الصناديق الفردية
    $('.user-checkbox').change(function() {
        const totalCheckboxes = $('.user-checkbox').length;
        const checkedCheckboxes = $('.user-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
});

// عرض تفاصيل المستخدم
function viewUser(userId) {
    window.location.href = `view.php?id=${userId}`;
}

// تعديل المستخدم
function editUser(userId) {
    window.location.href = `edit.php?id=${userId}`;
}

// إدارة أدوار المستخدم
function manageUserRoles(userId) {
    window.location.href = `user-roles.php?user_id=${userId}`;
}

// حذف المستخدم
function deleteUser(userId) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: userId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'تم الحذف!',
                        text: 'تم حذف المستخدم بنجاح.',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: data.message || 'حدث خطأ أثناء حذف المستخدم.',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ في الاتصال.',
                    icon: 'error'
                });
            });
        }
    });
}

// الإجراءات المتعددة
function bulkActions() {
    const selectedUsers = $('.user-checkbox:checked').map(function() {
        return this.value;
    }).get();

    if (selectedUsers.length === 0) {
        Swal.fire({
            title: 'تنبيه',
            text: 'يرجى تحديد مستخدم واحد على الأقل.',
            icon: 'warning'
        });
        return;
    }

    Swal.fire({
        title: 'إجراءات متعددة',
        text: `تم تحديد ${selectedUsers.length} مستخدم. ما الإجراء المطلوب؟`,
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'تفعيل',
        denyButtonText: 'إلغاء تفعيل',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#28a745',
        denyButtonColor: '#ffc107'
    }).then((result) => {
        if (result.isConfirmed) {
            bulkUpdateStatus(selectedUsers, 'active');
        } else if (result.isDenied) {
            bulkUpdateStatus(selectedUsers, 'inactive');
        }
    });
}

// تحديث حالة متعددة
function bulkUpdateStatus(userIds, status) {
    fetch('bulk-update-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_ids: userIds,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'تم التحديث!',
                text: `تم تحديث حالة ${userIds.length} مستخدم بنجاح.`,
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'خطأ!',
                text: data.message || 'حدث خطأ أثناء التحديث.',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'خطأ!',
            text: 'حدث خطأ في الاتصال.',
            icon: 'error'
        });
    });
}

// تصدير البيانات
function exportToExcel() {
    $('#usersTable').DataTable().button('.buttons-excel').trigger();
}

function exportToPDF() {
    $('#usersTable').DataTable().button('.buttons-pdf').trigger();
}

function exportToCSV() {
    $('#usersTable').DataTable().button('.buttons-csv').trigger();
}
</script>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}

.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.text-xs {
    font-size: 0.7rem;
}

.table th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
}

.btn-group .btn {
    margin-right: 2px;
}

.badge {
    font-size: 0.75em;
}
</style>
