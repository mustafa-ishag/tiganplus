<?php
/**
 * صفحة عرض تفاصيل الدور
 * Role Details View Page
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
if (!hasPermission('view_roles')) {
    header('Location: index.php');
    exit();
}

// التحقق من معرف الدور
$roleId = intval($_GET['id'] ?? 0);
if (!$roleId) {
    header('Location: index.php');
    exit();
}

try {
    $db = getDB();
    
    // جلب بيانات الدور
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if (!$role) {
        header('Location: index.php');
        exit();
    }
    
    // جلب المستخدمين المرتبطين بالدور
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.full_name, u.email, u.status, u.last_login,
               b.name as branch_name
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE ur.role_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$roleId]);
    $roleUsers = $stmt->fetchAll();
    
    // جلب صلاحيات الدور
    $stmt = $db->prepare("
        SELECT p.*, COALESCE(p.category, 'عام') as category
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.category, p.display_name
    ");
    $stmt->execute([$roleId]);
    $rolePermissions = $stmt->fetchAll();
    
    // تجميع الصلاحيات حسب الفئة
    $permissionsByCategory = [];
    foreach ($rolePermissions as $permission) {
        $category = $permission['category'] ?: 'عام';
        $permissionsByCategory[$category][] = $permission;
    }
    
    // إحصائيات
    $totalUsers = count($roleUsers);
    $totalPermissions = count($rolePermissions);
    $activeUsers = count(array_filter($roleUsers, function($user) {
        return $user['status'] === 'active';
    }));
    
} catch (Exception $e) {
    $error = "حدث خطأ أثناء جلب بيانات الدور: " . $e->getMessage();
}

// متغيرات الصفحة
$pageTitle = 'تفاصيل الدور: ' . ($role['display_name'] ?? 'غير محدد');
$currentPage = 'roles';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '/etganplus/public/dashboard.php'],
    ['title' => 'إدارة الأدوار', 'url' => '/etganplus/public/roles/index.php'],
    ['title' => 'تفاصيل الدور', 'url' => '']
];

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
                        تفاصيل الدور: <?= htmlspecialchars($role['display_name']) ?>
                    </h1>
                    <p class="text-muted mb-0">عرض تفاصيل الدور والمستخدمين والصلاحيات</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-right me-1"></i>
                        العودة للقائمة
                    </a>
                    <?php if (hasPermission('edit_roles')): ?>
                        <a href="edit.php?id=<?= $role['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>
                            تعديل الدور
                        </a>
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

    <div class="row">
        <!-- معلومات الدور -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات الدور
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-4 d-inline-block">
                            <i class="fas fa-user-shield text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h4 class="mt-3 mb-1"><?= htmlspecialchars($role['display_name']) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars($role['name']) ?></p>
                    </div>

                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="text-primary mb-0"><?= $totalUsers ?></h5>
                                <small class="text-muted">مستخدم</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <h5 class="text-success mb-0"><?= $totalPermissions ?></h5>
                                <small class="text-muted">صلاحية</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <h5 class="text-info mb-0"><?= $role['level'] ?></h5>
                            <small class="text-muted">المستوى</small>
                        </div>
                    </div>

                    <table class="table table-sm">
                        <tr>
                            <td><strong>الوصف:</strong></td>
                            <td><?= htmlspecialchars($role['description'] ?: 'لا يوجد وصف') ?></td>
                        </tr>
                        <tr>
                            <td><strong>الحالة:</strong></td>
                            <td>
                                <?php if ($role['status'] === 'active'): ?>
                                    <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">غير نشط</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>تاريخ الإنشاء:</strong></td>
                            <td><?= date('Y-m-d H:i', strtotime($role['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>آخر تحديث:</strong></td>
                            <td><?= date('Y-m-d H:i', strtotime($role['updated_at'])) ?></td>
                        </tr>
                    </table>

                    <?php if (hasPermission('manage_roles')): ?>
                        <div class="d-grid">
                            <a href="permissions.php?role_id=<?= $role['id'] ?>" class="btn btn-outline-warning">
                                <i class="fas fa-key me-1"></i>
                                إدارة الصلاحيات
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- المستخدمين -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>
                        المستخدمين (<?= $totalUsers ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($roleUsers)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الفرع</th>
                                        <th>الحالة</th>
                                        <th>آخر دخول</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roleUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($user['full_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($user['username']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email'] ?: 'غير محدد') ?></td>
                                            <td><?= htmlspecialchars($user['branch_name'] ?: 'غير محدد') ?></td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">نشط</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">غير نشط</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <small><?= date('Y-m-d H:i', strtotime($user['last_login'])) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">لم يسجل دخول</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="../users/view.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-outline-info" title="عرض المستخدم">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">لا يوجد مستخدمين</h5>
                            <p class="text-muted">لم يتم تخصيص هذا الدور لأي مستخدم بعد</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- الصلاحيات -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">
                <i class="fas fa-key me-2"></i>
                صلاحيات الدور (<?= $totalPermissions ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionsByCategory)): ?>
                <div class="row">
                    <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="border rounded p-3">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-folder me-1"></i>
                                    <?= htmlspecialchars($category) ?>
                                    <span class="badge bg-primary ms-2"><?= count($permissions) ?></span>
                                </h6>
                                
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <div>
                                            <strong><?= htmlspecialchars($permission['display_name']) ?></strong>
                                            <?php if ($permission['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($permission['description']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-key text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">لا توجد صلاحيات</h5>
                    <p class="text-muted">لم يتم تخصيص أي صلاحيات لهذا الدور</p>
                    <?php if (hasPermission('manage_permissions')): ?>
                        <a href="permissions.php?role_id=<?= $role['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            إضافة صلاحيات
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// إنهاء المحتوى وحفظه
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
