<?php
/**
 * صفحة إضافة مستخدم جديد - نسخة محسنة
 * Add New User Page - Enhanced Version
 */

session_start();

require_once __DIR__ . '/../../includes/path-helper.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Role.php';

$pageTitle = 'إضافة مستخدم جديد';
$currentPage = 'users';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => path('dashboard.php')],
    ['title' => 'إدارة النظام', 'url' => path('admin/')],
    ['title' => 'المستخدمين', 'url' => path('users/')],
    ['title' => 'إضافة مستخدم', 'url' => '']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('manage_users')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لإضافة مستخدمين';
    header('Location: ' . path('users/index.php'));
    exit();
}

// جلب البيانات المطلوبة
try {
    $db = getDB();
    
    // جلب الفروع
    $branches = $db->query("SELECT * FROM branches ORDER BY name")->fetchAll();
    
    // جلب الأدوار
    $roles = $db->query("SELECT * FROM roles ORDER BY level DESC, display_name")->fetchAll();
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
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
                <i class="fas fa-user-plus me-2"></i>
                إضافة مستخدم جديد
            </h1>
            <p class="text-muted mb-0">إنشاء حساب مستخدم جديد مع تحديد الصلاحيات والأدوار</p>
        </div>
        <div class="col-md-4 text-end">
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

    <!-- نموذج إضافة المستخدم -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-edit me-2"></i>
                        بيانات المستخدم
                    </h6>
                </div>
                <div class="card-body">
                    <form id="createUserForm" method="POST" action="create-ajax.php">
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
                                           required minlength="3" maxlength="50"
                                           placeholder="أدخل اسم المستخدم">
                                    <div class="form-text">يجب أن يكون فريداً ولا يحتوي على مسافات</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">
                                        الاسم الكامل <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           required maxlength="100"
                                           placeholder="أدخل الاسم الكامل">
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
                                           maxlength="100" placeholder="user@example.com">
                                    <div class="form-text">اختياري - سيتم استخدامه لإعادة تعيين كلمة المرور</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           maxlength="20" placeholder="05xxxxxxxx">
                                </div>
                            </div>
                        </div>

                        <!-- كلمة المرور -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                    <i class="fas fa-lock me-1"></i>
                                    كلمة المرور
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        كلمة المرور <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               required minlength="8" placeholder="أدخل كلمة المرور">
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
                                    <label for="confirm_password" class="form-label">
                                        تأكيد كلمة المرور <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required minlength="8" 
                                               placeholder="أعد إدخال كلمة المرور">
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
                                           maxlength="100" placeholder="مثل: المالية، الهندسة، الإدارة">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">المنصب</label>
                                    <input type="text" class="form-control" id="position" name="position" 
                                           maxlength="100" placeholder="مثل: مدير، محاسب، مهندس">
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
                                            <option value="<?= $branch['id'] ?>">
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
                                    <select class="form-select" id="status" name="status">
                                        <option value="active">نشط</option>
                                        <option value="inactive">غير نشط</option>
                                        <option value="suspended">معلق</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- أزرار الحفظ -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-outline-secondary">
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
                                            حفظ المستخدم
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
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-tag me-2"></i>
                        الأدوار والصلاحيات
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">اختر الأدوار التي تريد تخصيصها للمستخدم:</p>
                    
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $role): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" 
                                       name="roles[]" value="<?= $role['id'] ?>" 
                                       id="role_<?= $role['id'] ?>">
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

            <!-- نصائح -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        نصائح مهمة
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            اختر اسم مستخدم واضح وسهل التذكر
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            استخدم كلمة مرور قوية تحتوي على أرقام وحروف
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            حدد الأدوار بعناية حسب مسؤوليات المستخدم
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            يمكن تعديل البيانات لاحقاً من صفحة التعديل
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
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
    if (confirm('هل أنت متأكد من إعادة تعيين جميع البيانات؟')) {
        document.getElementById('createUserForm').reset();
    }
}

// التحقق من تطابق كلمات المرور
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('كلمات المرور غير متطابقة');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// معالجة إرسال النموذج
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // التحقق من تطابق كلمات المرور
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
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
    
    fetch('create-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'تم الحفظ!',
                text: 'تم إضافة المستخدم بنجاح',
                icon: 'success'
            }).then(() => {
                window.location.href = 'index.php';
            });
        } else {
            Swal.fire({
                title: 'خطأ!',
                text: data.message || 'حدث خطأ أثناء إضافة المستخدم',
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

// التحقق من توفر اسم المستخدم
let usernameTimeout;
document.getElementById('username').addEventListener('input', function() {
    const username = this.value.trim();
    
    if (username.length < 3) return;
    
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

.form-text {
    font-size: 0.875em;
    color: #6c757d;
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
