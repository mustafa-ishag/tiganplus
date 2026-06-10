<?php
/**
 * صفحة إدارة أدوار المستخدم
 * User Roles Management Page
 */

session_start();

require_once __DIR__ . '/../../includes/path-helper.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Role.php';

$pageTitle = 'إدارة أدوار المستخدم';
$currentPage = 'users';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لإدارة أدوار المستخدمين';
    header('Location: ' . path('users/index.php'));
    exit();
}

// التحقق من معرف المستخدم
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$userId) {
    $_SESSION['error_message'] = 'معرف المستخدم غير صحيح';
    header('Location: ' . path('users/index.php'));
    exit();
}

try {
    $db = getDB();
    
    // جلب بيانات المستخدم
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = 'المستخدم غير موجود';
        header('Location: ' . path('users/index.php'));
        exit();
    }
    
    // جلب جميع الأدوار المتاحة
    $roles = $db->query("SELECT * FROM roles WHERE status = 'active' ORDER BY level DESC, display_name")->fetchAll();
    
    // جلب أدوار المستخدم الحالية
    $stmt = $db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userRoleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $breadcrumbs = [
        ['title' => 'الرئيسية', 'url' => path('dashboard.php')],
        ['title' => 'إدارة النظام', 'url' => path('admin/')],
        ['title' => 'المستخدمين', 'url' => path('users/')],
        ['title' => 'إدارة أدوار المستخدم', 'url' => '']
    ];
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
    $roles = [];
    $userRoleIds = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-2 text-gray-800">
                <i class="fas fa-user-cog me-2"></i>
                إدارة أدوار المستخدم
            </h1>
            <p class="text-muted mb-0">
                إدارة الأدوار والصلاحيات للمستخدم: 
                <strong><?= htmlspecialchars($user['full_name'] ?? 'غير محدد') ?></strong>
                (<?= htmlspecialchars($user['username'] ?? 'غير محدد') ?>)
            </p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i>
                العودة للقائمة
            </a>
            <a href="edit.php?id=<?= $userId ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i>
                تعديل المستخدم
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- معلومات المستخدم -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-user me-2"></i>
                        معلومات المستخدم
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>الاسم الكامل:</strong><br>
                            <?= htmlspecialchars($user['full_name'] ?? 'غير محدد') ?>
                        </div>
                        <div class="col-md-3">
                            <strong>اسم المستخدم:</strong><br>
                            <?= htmlspecialchars($user['username'] ?? 'غير محدد') ?>
                        </div>
                        <div class="col-md-3">
                            <strong>القسم:</strong><br>
                            <?= htmlspecialchars($user['department'] ?? 'غير محدد') ?>
                        </div>
                        <div class="col-md-3">
                            <strong>المنصب:</strong><br>
                            <?= htmlspecialchars($user['position'] ?? 'غير محدد') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- إدارة الأدوار -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-user-tag me-2"></i>
                        إدارة الأدوار والصلاحيات
                    </h6>
                </div>
                <div class="card-body">
                    <form id="userRolesForm" method="POST">
                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                        
                        <div class="row">
                            <?php if (!empty($roles)): ?>
                                <?php foreach ($roles as $role): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100 <?= in_array($role['id'], $userRoleIds) ? 'border-primary' : 'border-light' ?>">
                                            <div class="card-body">
                                                <div class="form-check">
                                                    <input class="form-check-input" 
                                                           type="checkbox" 
                                                           name="roles[]" 
                                                           value="<?= $role['id'] ?>"
                                                           id="role_<?= $role['id'] ?>"
                                                           <?= in_array($role['id'], $userRoleIds) ? 'checked' : '' ?>>
                                                    <label class="form-check-label w-100" for="role_<?= $role['id'] ?>">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <h6 class="mb-1"><?= htmlspecialchars($role['display_name']) ?></h6>
                                                                <small class="text-muted"><?= htmlspecialchars($role['name']) ?></small>
                                                            </div>
                                                            <span class="badge bg-secondary">المستوى <?= $role['level'] ?></span>
                                                        </div>
                                                        <?php if (!empty($role['description'])): ?>
                                                            <p class="text-muted small mt-2 mb-0">
                                                                <?= htmlspecialchars($role['description']) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        لا توجد أدوار متاحة حالياً
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary" onclick="selectAll()">
                                            <i class="fas fa-check-double me-1"></i>
                                            تحديد الكل
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearAll()">
                                            <i class="fas fa-times me-1"></i>
                                            إلغاء التحديد
                                        </button>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                                            <i class="fas fa-times me-1"></i>
                                            إلغاء
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>
                                            حفظ التغييرات
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تحديد جميع الأدوار
function selectAll() {
    document.querySelectorAll('input[name="roles[]"]').forEach(checkbox => {
        checkbox.checked = true;
        updateCardStyle(checkbox);
    });
}

// إلغاء تحديد جميع الأدوار
function clearAll() {
    document.querySelectorAll('input[name="roles[]"]').forEach(checkbox => {
        checkbox.checked = false;
        updateCardStyle(checkbox);
    });
}

// تحديث شكل البطاقة عند التحديد
function updateCardStyle(checkbox) {
    const card = checkbox.closest('.card');
    if (checkbox.checked) {
        card.classList.remove('border-light');
        card.classList.add('border-primary');
    } else {
        card.classList.remove('border-primary');
        card.classList.add('border-light');
    }
}

// إضافة مستمع للأحداث لجميع checkboxes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="roles[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateCardStyle(this);
        });
    });
});

// معالجة إرسال النموذج
document.getElementById('userRolesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // عرض رسالة التحميل
    Swal.fire({
        title: 'جاري حفظ التغييرات...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('update-user-roles-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح!',
                text: data.message,
                confirmButtonText: 'موافق'
            }).then(() => {
                // إعادة تحميل الصفحة لإظهار التحديثات
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'خطأ!',
                text: data.message || 'حدث خطأ أثناء حفظ التغييرات',
                confirmButtonText: 'موافق'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: 'حدث خطأ في الاتصال',
            confirmButtonText: 'موافق'
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../includes/layout.php';
?>
