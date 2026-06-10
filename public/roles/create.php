<?php
/**
 * صفحة إنشاء دور جديد
 * Create New Role Page
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
if (!hasPermission('create_roles')) {
    header('Location: index.php');
    exit();
}

// متغيرات الصفحة
$pageTitle = 'إنشاء دور جديد';
$currentPage = 'roles';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '/etganplus/public/dashboard.php'],
    ['title' => 'إدارة الأدوار', 'url' => '/etganplus/public/roles/index.php'],
    ['title' => 'إنشاء دور جديد', 'url' => '']
];

try {
    $db = getDB();
    
    // جلب جميع الصلاحيات مجمعة حسب الفئة
    $stmt = $db->prepare("
        SELECT 
            p.*,
            COALESCE(p.category, 'عام') as category
        FROM permissions p
        ORDER BY p.category, p.display_name
    ");
    $stmt->execute();
    $allPermissions = $stmt->fetchAll();
    
    // تجميع الصلاحيات حسب الفئة
    $permissionsByCategory = [];
    foreach ($allPermissions as $permission) {
        $category = $permission['category'] ?: 'عام';
        $permissionsByCategory[$category][] = $permission;
    }
    
} catch (Exception $e) {
    $error = "حدث خطأ أثناء جلب البيانات: " . $e->getMessage();
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
                        <i class="fas fa-plus-circle text-primary me-2"></i>
                        إنشاء دور جديد
                    </h1>
                    <p class="text-muted mb-0">إضافة دور جديد مع تحديد الصلاحيات</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right me-1"></i>
                        العودة للقائمة
                    </a>
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

    <!-- نموذج إنشاء الدور -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات الدور
                    </h5>
                </div>
                <div class="card-body">
                    <form id="createRoleForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">اسم الدور (بالإنجليزية) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       placeholder="مثال: manager">
                                <div class="form-text">يجب أن يكون باللغة الإنجليزية وبدون مسافات</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="display_name" class="form-label">الاسم المعروض <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="display_name" name="display_name" required
                                       placeholder="مثال: مدير">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">الوصف</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="وصف مختصر عن الدور وصلاحياته"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="level" class="form-label">مستوى الدور <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="level" name="level" required
                                       min="1" max="100" value="10">
                                <div class="form-text">المستوى الأعلى له صلاحيات أكثر (1-100)</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">نشط</option>
                                    <option value="inactive">غير نشط</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        نصائح
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-1"></i> إرشادات مهمة:</h6>
                        <ul class="mb-0 small">
                            <li>اختر اسماً واضحاً ومميزاً للدور</li>
                            <li>حدد مستوى مناسب للدور</li>
                            <li>اختر الصلاحيات بعناية</li>
                            <li>يمكن تعديل الصلاحيات لاحقاً</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-1"></i> تنبيه:</h6>
                        <p class="mb-0 small">
                            تأكد من عدم إعطاء صلاحيات أكثر من اللازم لضمان أمان النظام.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- صلاحيات الدور -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-key me-2"></i>
                    صلاحيات الدور
                </h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPermissions()">
                        تحديد الكل
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllPermissions()">
                        إلغاء التحديد
                    </button>
                </div>
            </div>
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
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" 
                                            onclick="toggleCategoryPermissions('<?= $category ?>')">
                                        تحديد/إلغاء الفئة
                                    </button>
                                </h6>
                                
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input permission-checkbox" 
                                               type="checkbox" 
                                               value="<?= $permission['id'] ?>" 
                                               id="permission_<?= $permission['id'] ?>"
                                               data-category="<?= $category ?>">
                                        <label class="form-check-label" for="permission_<?= $permission['id'] ?>">
                                            <strong><?= htmlspecialchars($permission['display_name']) ?></strong>
                                            <?php if ($permission['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($permission['description']) ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    لا توجد صلاحيات متاحة في النظام.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- أزرار الحفظ -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </a>
                <button type="button" class="btn btn-primary" onclick="saveRole()">
                    <i class="fas fa-save me-1"></i>
                    حفظ الدور
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// تحديد جميع الصلاحيات
function selectAllPermissions() {
    $('.permission-checkbox').prop('checked', true);
}

// إلغاء تحديد جميع الصلاحيات
function clearAllPermissions() {
    $('.permission-checkbox').prop('checked', false);
}

// تحديد/إلغاء تحديد صلاحيات فئة معينة
function toggleCategoryPermissions(category) {
    const categoryCheckboxes = $(`.permission-checkbox[data-category="${category}"]`);
    const allChecked = categoryCheckboxes.length === categoryCheckboxes.filter(':checked').length;
    
    categoryCheckboxes.prop('checked', !allChecked);
}

// حفظ الدور
function saveRole() {
    const form = document.getElementById('createRoleForm');
    const formData = new FormData(form);
    
    // جمع الصلاحيات المحددة
    const selectedPermissions = [];
    $('.permission-checkbox:checked').each(function() {
        selectedPermissions.push($(this).val());
    });
    
    // التحقق من البيانات المطلوبة
    if (!formData.get('name') || !formData.get('display_name')) {
        Swal.fire({
            title: 'خطأ!',
            text: 'يرجى ملء جميع الحقول المطلوبة',
            icon: 'error'
        });
        return;
    }
    
    // إضافة الصلاحيات للبيانات
    formData.append('permissions', JSON.stringify(selectedPermissions));
    
    // إرسال البيانات
    fetch('create-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'تم بنجاح!',
                text: data.message,
                icon: 'success'
            }).then(() => {
                window.location.href = 'index.php';
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
            text: 'حدث خطأ أثناء حفظ الدور',
            icon: 'error'
        });
    });
}

// التحقق من صحة اسم الدور
$('#name').on('input', function() {
    const value = $(this).val();
    const isValid = /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(value);
    
    if (value && !isValid) {
        $(this).addClass('is-invalid');
        $(this).siblings('.invalid-feedback').remove();
        $(this).after('<div class="invalid-feedback">يجب أن يكون اسم الدور باللغة الإنجليزية وبدون مسافات</div>');
    } else {
        $(this).removeClass('is-invalid');
        $(this).siblings('.invalid-feedback').remove();
    }
});
</script>

<?php
// إنهاء المحتوى وحفظه
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
