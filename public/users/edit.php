<?php
/**
 * صفحة تعديل المستخدم
 * Edit User Page
 */

session_start();

require_once __DIR__ . '/../../includes/path-helper.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Role.php';

$pageTitle = 'تعديل المستخدم';
$currentPage = 'users';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => path('dashboard.php')],
    ['title' => 'إدارة النظام', 'url' => path('admin/')],
    ['title' => 'المستخدمين', 'url' => path('users/')],
    ['title' => 'تعديل المستخدم', 'url' => '']
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
if (!hasPermission('manage_users') && $_SESSION['user_id'] != $userId) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لتعديل المستخدمين';
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

    // جلب الفروع
    $branches = $db->query("SELECT * FROM branches ORDER BY name")->fetchAll();

    // جلب الأدوار
    $roles = $db->query("SELECT * FROM roles ORDER BY level DESC, display_name")->fetchAll();

    // جلب أدوار المستخدم الحالية
    $stmt = $db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userRoleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    error_log("Error in edit.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $error = 'حدث خطأ أثناء جلب بيانات المستخدم: ' . $e->getMessage();
    $user = null;
    $branches = [];
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
                <i class="fas fa-user-edit me-2"></i>
                تعديل المستخدم
            </h1>
            <p class="text-muted mb-0">تحديث بيانات المستخدم والصلاحيات</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="view.php?id=<?= $userId ?>" class="btn btn-outline-info me-2">
                <i class="fas fa-eye me-1"></i>
                عرض التفاصيل
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i>
                العودة للقائمة
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

    <?php if ($user): ?>
        <div class="row">
            <!-- نموذج التعديل -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-user-edit me-2"></i>
                            تعديل بيانات المستخدم: <?= htmlspecialchars($user['username']) ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="editUserForm" method="POST" action="update-ajax.php">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            
                            <!-- المعلومات الأساسية -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-info-circle me-1"></i>
                                        المعلومات الأساسية
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            اسم المستخدم <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?= htmlspecialchars($user['username']) ?>"
                                               required minlength="3" maxlength="50"
                                               <?= $_SESSION['user_id'] == $userId ? 'readonly' : '' ?>>
                                        <?php if ($_SESSION['user_id'] == $userId): ?>
                                            <div class="form-text text-warning">لا يمكنك تغيير اسم المستخدم الخاص بك</div>
                                        <?php else: ?>
                                            <div class="form-text">يجب أن يكون فريداً ولا يحتوي على مسافات</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">
                                            الاسم الكامل <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?= htmlspecialchars($user['full_name']) ?>"
                                               required maxlength="100">
                                    </div>
                                </div>
                            </div>

                            <!-- معلومات الاتصال -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-address-book me-1"></i>
                                        معلومات الاتصال
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                               maxlength="100">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">رقم الهاتف</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                               maxlength="20">
                                    </div>
                                </div>
                            </div>

                            <!-- كلمة المرور -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-lock me-1"></i>
                                        تغيير كلمة المرور (اختياري)
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">كلمة المرور الجديدة</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   minlength="8" placeholder="اتركه فارغاً إذا لم تريد التغيير">
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="togglePassword('password')">
                                                <i class="fas fa-eye" id="password-icon"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">يجب أن تكون 8 أحرف على الأقل</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" 
                                                   name="confirm_password" minlength="8" 
                                                   placeholder="أعد إدخال كلمة المرور الجديدة">
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- معلومات العمل -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-briefcase me-1"></i>
                                        معلومات العمل
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">القسم</label>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?= htmlspecialchars($user['department'] ?? '') ?>"
                                               maxlength="100">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="position" class="form-label">المنصب</label>
                                        <input type="text" class="form-control" id="position" name="position" 
                                               value="<?= htmlspecialchars($user['position'] ?? '') ?>"
                                               maxlength="100">
                                    </div>
                                </div>
                            </div>

                            <!-- الفرع والحالة -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-cog me-1"></i>
                                        إعدادات الحساب
                                    </h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="branch_id" class="form-label">الفرع</label>
                                        <select class="form-select" id="branch_id" name="branch_id">
                                            <option value="">جميع الفروع</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?= $branch['id'] ?>" 
                                                        <?= $user['branch_id'] == $branch['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($branch['name']) ?>
                                                    <?php if ($branch['code']): ?>
                                                        (<?= htmlspecialchars($branch['code']) ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">حالة الحساب</label>
                                        <select class="form-select" id="status" name="status"
                                                <?= $_SESSION['user_id'] == $userId ? 'disabled' : '' ?>>
                                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>نشط</option>
                                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                                            <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>معلق</option>
                                        </select>
                                        <?php if ($_SESSION['user_id'] == $userId): ?>
                                            <div class="form-text text-warning">لا يمكنك تغيير حالة حسابك الخاص</div>
                                            <input type="hidden" name="status" value="<?= $user['status'] ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- أزرار الحفظ -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="view.php?id=<?= $userId ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-1"></i>
                                            إلغاء
                                        </a>
                                        <div>
                                            <button type="button" class="btn btn-outline-primary me-2" onclick="resetForm()">
                                                <i class="fas fa-undo me-1"></i>
                                                إعادة تعيين
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

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- إدارة الأدوار -->
                <?php if (hasPermission('manage_users') && $_SESSION['user_id'] != $userId): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-user-tag me-2"></i>
                                الأدوار والصلاحيات
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">اختر الأدوار المخصصة للمستخدم:</p>
                            
                            <?php if (!empty($roles)): ?>
                                <?php foreach ($roles as $role): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" 
                                               name="roles[]" value="<?= $role['id'] ?>" 
                                               id="role_<?= $role['id'] ?>"
                                               <?= in_array($role['id'], $userRoleIds) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                            <strong><?= htmlspecialchars($role['display_name'] ?? $role['name']) ?></strong>
                                            <?php if ($role['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($role['description']) ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    لا توجد أدوار متاحة
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- معلومات الحساب -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">
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

                <!-- نصائح -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning">
                            <i class="fas fa-lightbulb me-2"></i>
                            نصائح مهمة
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                اترك كلمة المرور فارغة إذا لم تريد تغييرها
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                تأكد من صحة البريد الإلكتروني للإشعارات
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                حدد الأدوار بعناية حسب المسؤوليات
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success me-2"></i>
                                سيتم حفظ جميع التغييرات فوراً
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// تبديل إظهار/إخفاء كلمة المرور
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// إعادة تعيين النموذج
function resetForm() {
    if (confirm('هل أنت متأكد من إعادة تعيين جميع التغييرات؟')) {
        location.reload();
    }
}

// التحقق من تطابق كلمات المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('كلمات المرور غير متطابقة');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        if (password && confirmPassword && password === confirmPassword) {
            this.classList.add('is-valid');
        }
    }
});

// معالجة إرسال النموذج
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // التحقق من تطابق كلمات المرور
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        Swal.fire({
            title: 'خطأ!',
            text: 'كلمات المرور غير متطابقة',
            icon: 'error'
        });
        return;
    }
    
    // إرسال البيانات
    const formData = new FormData(this);
    
    // إضافة الأدوار المحددة
    const selectedRoles = [];
    document.querySelectorAll('input[name="roles[]"]:checked').forEach(function(checkbox) {
        selectedRoles.push(checkbox.value);
    });
    formData.append('selected_roles', JSON.stringify(selectedRoles));
    
    // إظهار مؤشر التحميل
    Swal.fire({
        title: 'جاري الحفظ...',
        text: 'يرجى الانتظار',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('update-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'تم الحفظ!',
                text: 'تم تحديث بيانات المستخدم بنجاح',
                icon: 'success'
            }).then(() => {
                window.location.href = `view.php?id=${<?= $userId ?>}`;
            });
        } else {
            Swal.fire({
                title: 'خطأ!',
                text: data.message || 'حدث خطأ أثناء تحديث بيانات المستخدم',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'خطأ!',
            text: 'حدث خطأ في الاتصال',
            icon: 'error'
        });
    });
});

// التحقق من توفر اسم المستخدم (إذا لم يكن المستخدم الحالي)
<?php if ($_SESSION['user_id'] != $userId): ?>
let usernameTimeout;
document.getElementById('username').addEventListener('input', function() {
    const username = this.value.trim();
    const originalUsername = '<?= $user['username'] ?>';
    
    if (username.length < 3 || username === originalUsername) return;
    
    clearTimeout(usernameTimeout);
    usernameTimeout = setTimeout(() => {
        fetch('check-username.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ username: username })
        })
        .then(response => response.json())
        .then(data => {
            const field = document.getElementById('username');
            if (data.available) {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            } else {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
            }
        })
        .catch(error => {
            console.error('Error checking username:', error);
        });
    }, 500);
});
<?php endif; ?>
</script>

<style>
.form-check-input:checked {
    background-color: #4e73df;
    border-color: #4e73df;
}

.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.border-bottom {
    border-bottom: 1px solid #e3e6f0 !important;
}

.is-valid {
    border-color: #1cc88a;
}

.is-invalid {
    border-color: #e74a3b;
}

.table-borderless td {
    border: none;
    padding: 0.5rem 0;
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
