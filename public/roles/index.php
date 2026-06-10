    <?php
/**
 * صفحة إدارة الأدوار
 * Roles Management Page
 */

session_start();

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/path-helper.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('view_roles') && !hasPermission('manage_roles')) {
    header('Location: ../dashboard.php');
    exit();
}

// متغيرات الصفحة
$pageTitle = 'إدارة الأدوار';
$currentPage = 'roles';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '/etganplus/public/dashboard.php'],
    ['title' => 'إدارة الأدوار', 'url' => '']
];

try {
    $db = getDB();
    
    // جلب إحصائيات الأدوار
    $totalRoles = $db->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    $activeRoles = $db->query("SELECT COUNT(*) FROM roles WHERE status = 'active'")->fetchColumn();
    $totalPermissions = $db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    $totalRolePermissions = $db->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
    
    // جلب الأدوار مع عدد المستخدمين والصلاحيات
    $stmt = $db->prepare("
        SELECT 
            r.*,
            COUNT(DISTINCT ur.user_id) as users_count,
            COUNT(DISTINCT rp.permission_id) as permissions_count
        FROM roles r
        LEFT JOIN user_roles ur ON r.id = ur.role_id
        LEFT JOIN role_permissions rp ON r.id = rp.role_id
        GROUP BY r.id
        ORDER BY r.level DESC, r.name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "حدث خطأ أثناء جلب بيانات الأدوار: " . $e->getMessage();
}

// بدء المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-user-shield text-primary me-2"></i>
                        إدارة الأدوار
                    </h1>
                    <p class="text-muted mb-0">إدارة أدوار المستخدمين والصلاحيات</p>
                </div>
                <div>
                    <?php if (hasPermission('create_roles')): ?>
                        <button type="button" class="btn btn-primary" onclick="createRole()">
                            <i class="fas fa-plus me-1"></i>
                            إضافة دور جديد
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-user-shield text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">إجمالي الأدوار</h6>
                            <h4 class="mb-0"><?= number_format($totalRoles) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">الأدوار النشطة</h6>
                            <h4 class="mb-0"><?= number_format($activeRoles) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-key text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">إجمالي الصلاحيات</h6>
                            <h4 class="mb-0"><?= number_format($totalPermissions) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                <i class="fas fa-link text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">ربط الأدوار بالصلاحيات</h6>
                            <h4 class="mb-0"><?= number_format($totalRolePermissions) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- جدول الأدوار -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                قائمة الأدوار
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="rolesTable">
                    <thead class="table-light">
                        <tr>
                            <th>الدور</th>
                            <th>الوصف</th>
                            <th>المستوى</th>
                            <th>عدد المستخدمين</th>
                            <th>عدد الصلاحيات</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                            <i class="fas fa-user-shield text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($role['display_name']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($role['name']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted"><?= htmlspecialchars($role['description'] ?? 'لا يوجد وصف') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $role['level'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $role['users_count'] ?> مستخدم</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $role['permissions_count'] ?> صلاحية</span>
                                </td>
                                <td>
                                    <?php if ($role['status'] === 'active'): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غير نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('Y-m-d', strtotime($role['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="viewRole(<?= $role['id'] ?>)"
                                                title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if (hasPermission('manage_permissions')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                    onclick="managePermissions(<?= $role['id'] ?>)"
                                                    title="إدارة الصلاحيات">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('edit_roles')): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="editRole(<?= $role['id'] ?>)"
                                                    title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (hasPermission('delete_roles') && $role['users_count'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteRole(<?= $role['id'] ?>)"
                                                    title="حذف">
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
</div>

<script>
$(document).ready(function() {
    // تهيئة DataTable
    $('#rolesTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/ar.json'
        },
        order: [[2, 'desc']], // ترتيب حسب المستوى
        pageLength: 25,
        responsive: true
    });
});

// عرض تفاصيل الدور
function viewRole(roleId) {
    window.location.href = `view.php?id=${roleId}`;
}

// إنشاء دور جديد
function createRole() {
    window.location.href = 'create.php';
}

// تعديل الدور
function editRole(roleId) {
    window.location.href = `edit.php?id=${roleId}`;
}

// إدارة صلاحيات الدور
function managePermissions(roleId) {
    window.location.href = `permissions.php?role_id=${roleId}`;
}

// حذف الدور
function deleteRole(roleId) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذا الدور؟ لا يمكن التراجع عن هذا الإجراء.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إرسال طلب الحذف
            fetch('delete-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    role_id: roleId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'تم الحذف!',
                        text: data.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: data.message,
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'خطأ!',
                    text: 'حدث خطأ أثناء حذف الدور',
                    icon: 'error'
                });
            });
        }
    });
}
</script>

<?php
// إنهاء المحتوى وحفظه
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
