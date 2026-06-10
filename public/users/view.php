<?php
/**
 * صفحة عرض تفاصيل المستخدم
 * User Details View Page
 */

session_start();

require_once __DIR__ . '/../../includes/path-helper.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';

$pageTitle = 'تفاصيل المستخدم';
$currentPage = 'users';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '/etganplus/public/dashboard.php'],
    ['title' => 'إدارة النظام', 'url' => '/etganplus/public/admin/'],
    ['title' => 'المستخدمين', 'url' => '/etganplus/public/users/'],
    ['title' => 'تفاصيل المستخدم', 'url' => '']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من معرف المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المستخدم غير صحيح';
    header('Location: ' . path('users/index.php'));
    exit();
}

$userId = (int)$_GET['id'];

// التحقق من الصلاحيات
if (!hasPermission('view_users') && $_SESSION['user_id'] != $userId) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لعرض تفاصيل المستخدمين';
    header('Location: ' . path('users/index.php'));
    exit();
}

try {
    $db = getDB();
    
    // جلب بيانات المستخدم مع الفرع
    $sql = "SELECT u.*, b.name as branch_name, b.code as branch_code
            FROM users u
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = 'المستخدم غير موجود';
        header('Location: ' . path('users/index.php'));
        exit();
    }
    
    // جلب أدوار المستخدم
    $sql = "SELECT r.id, r.name, r.display_name, r.description, r.level
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
            ORDER BY r.level DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $userRoles = $stmt->fetchAll();
    
    // جلب إحصائيات نشاط المستخدم
    $stats = [];
    
    // عدد مرات تسجيل الدخول
    $stmt = $db->prepare("SELECT COUNT(*) as login_count FROM activity_logs WHERE user_id = ? AND action = 'login'");
    $stmt->execute([$userId]);
    $stats['login_count'] = $stmt->fetchColumn();
    
    // آخر نشاط
    $stmt = $db->prepare("SELECT created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $stats['last_activity'] = $stmt->fetchColumn();
    
    // عدد الأنشطة الإجمالي
    $stmt = $db->prepare("SELECT COUNT(*) as total_activities FROM activity_logs WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_activities'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب بيانات المستخدم: ' . $e->getMessage();
    $user = null;
    $userRoles = [];
    $stats = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-2 text-gray-800">
                <i class="fas fa-user me-2"></i>
                تفاصيل المستخدم
            </h1>
            <p class="text-muted mb-0">عرض معلومات المستخدم والصلاحيات والنشاطات</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-right me-1"></i>
                العودة للقائمة
            </a>
            <?php if (hasPermission('manage_users')): ?>
                <a href="edit.php?id=<?= $userId ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    تعديل
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

    <?php if ($user): ?>
        <div class="row">
            <!-- المعلومات الأساسية -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-circle me-2"></i>
                            المعلومات الأساسية
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold text-muted">اسم المستخدم:</td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">الاسم الكامل:</td>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">البريد الإلكتروني:</td>
                                        <td>
                                            <?php if ($user['email']): ?>
                                                <a href="mailto:<?= htmlspecialchars($user['email']) ?>">
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">رقم الهاتف:</td>
                                        <td>
                                            <?php if ($user['phone']): ?>
                                                <a href="tel:<?= htmlspecialchars($user['phone']) ?>">
                                                    <?= htmlspecialchars($user['phone']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold text-muted">القسم:</td>
                                        <td><?= $user['department'] ? htmlspecialchars($user['department']) : '<span class="text-muted">غير محدد</span>' ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">المنصب:</td>
                                        <td><?= $user['position'] ? htmlspecialchars($user['position']) : '<span class="text-muted">غير محدد</span>' ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">الفرع:</td>
                                        <td>
                                            <?php if ($user['branch_name']): ?>
                                                <?= htmlspecialchars($user['branch_name']) ?>
                                                <?php if ($user['branch_code']): ?>
                                                    <small class="text-muted">(<?= htmlspecialchars($user['branch_code']) ?>)</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">جميع الفروع</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">الحالة:</td>
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
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الأدوار والصلاحيات -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-tag me-2"></i>
                            الأدوار والصلاحيات
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($userRoles)): ?>
                            <div class="row">
                                <?php foreach ($userRoles as $role): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-left-primary h-100">
                                            <div class="card-body">
                                                <h6 class="card-title text-primary">
                                                    <i class="fas fa-shield-alt me-1"></i>
                                                    <?= htmlspecialchars($role['display_name'] ?? $role['name']) ?>
                                                </h6>
                                                <?php if ($role['description']): ?>
                                                    <p class="card-text text-muted small">
                                                        <?= htmlspecialchars($role['description']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-layer-group me-1"></i>
                                                    مستوى: <?= $role['level'] ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                لا توجد أدوار مخصصة لهذا المستخدم
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- إحصائيات النشاط -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-bar me-2"></i>
                            إحصائيات النشاط
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="border-bottom pb-2">
                                    <h4 class="text-primary mb-0"><?= number_format($stats['login_count'] ?? 0) ?></h4>
                                    <small class="text-muted">مرات تسجيل الدخول</small>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="border-bottom pb-2">
                                    <h4 class="text-success mb-0"><?= number_format($stats['total_activities'] ?? 0) ?></h4>
                                    <small class="text-muted">إجمالي الأنشطة</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6 class="text-muted">آخر نشاط:</h6>
                            <?php if ($stats['last_activity']): ?>
                                <p class="mb-0">
                                    <i class="fas fa-clock me-1"></i>
                                    <?= date('Y-m-d H:i', strtotime($stats['last_activity'])) ?>
                                </p>
                            <?php else: ?>
                                <p class="text-muted mb-0">لا يوجد نشاط مسجل</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- معلومات الحساب -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle me-2"></i>
                            معلومات الحساب
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="fw-bold text-muted">تاريخ الإنشاء:</td>
                                <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">آخر تحديث:</td>
                                <td><?= date('Y-m-d', strtotime($user['updated_at'])) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">آخر دخول:</td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?= date('Y-m-d H:i', strtotime($user['last_login'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">لم يسجل دخول</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- إجراءات سريعة -->
                <?php if (hasPermission('manage_users') && $user['id'] != $_SESSION['user_id']): ?>
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-tools me-2"></i>
                                إجراءات سريعة
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit.php?id=<?= $userId ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i>
                                    تعديل البيانات
                                </a>
                                <a href="roles.php?user_id=<?= $userId ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-user-cog me-1"></i>
                                    إدارة الأدوار
                                </a>
                                <?php if ($user['status'] === 'active'): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" 
                                            onclick="changeUserStatus(<?= $userId ?>, 'inactive')">
                                        <i class="fas fa-user-slash me-1"></i>
                                        إلغاء التفعيل
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-success btn-sm" 
                                            onclick="changeUserStatus(<?= $userId ?>, 'active')">
                                        <i class="fas fa-user-check me-1"></i>
                                        تفعيل الحساب
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="deleteUser(<?= $userId ?>)">
                                    <i class="fas fa-trash me-1"></i>
                                    حذف المستخدم
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// تغيير حالة المستخدم
function changeUserStatus(userId, status) {
    const statusText = status === 'active' ? 'تفعيل' : 'إلغاء تفعيل';
    
    Swal.fire({
        title: `تأكيد ${statusText}`,
        text: `هل أنت متأكد من ${statusText} هذا المستخدم؟`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'active' ? '#28a745' : '#6c757d',
        cancelButtonColor: '#3085d6',
        confirmButtonText: `نعم، ${statusText}`,
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('update-status-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    user_id: userId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'تم التحديث!',
                        text: `تم ${statusText} المستخدم بنجاح.`,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'خطأ!',
                        text: data.message || 'حدث خطأ أثناء تحديث حالة المستخدم.',
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
                        window.location.href = 'index.php';
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
</script>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.table-borderless td {
    border: none;
    padding: 0.5rem 0;
}

.badge {
    font-size: 0.75em;
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
