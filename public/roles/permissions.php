<?php
/**
 * صفحة إدارة صلاحيات الدور
 * Role Permissions Management Page
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
if (!hasPermission('manage_roles')) {
    header('Location: index.php');
    exit();
}

// التحقق من معرف الدور
$roleId = intval($_GET['role_id'] ?? 0);
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
    
    // فحص وجود عمود category
    $stmt = $db->query("SHOW COLUMNS FROM permissions LIKE 'category'");
    $hasCategoryColumn = $stmt->fetch() !== false;

    // جلب جميع الصلاحيات مجمعة حسب الفئة
    if ($hasCategoryColumn) {
        $stmt = $db->prepare("
            SELECT
                p.*,
                COALESCE(p.category, p.module, 'عام') as category,
                CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = ?
            ORDER BY p.category, p.display_name
        ");
    } else {
        $stmt = $db->prepare("
            SELECT
                p.*,
                CASE
                    WHEN p.module = 'inventory' AND p.category = 'access' THEN 'المخزون - الوصول'
                    WHEN p.module = 'inventory' AND p.category = 'materials' THEN 'المخزون - المواد'
                    WHEN p.module = 'inventory' AND p.category = 'transactions' THEN 'المخزون - المعاملات'
                    WHEN p.module = 'inventory' AND p.category = 'requests' THEN 'المخزون - طلبات الصرف'
                    WHEN p.module = 'inventory' AND p.category = 'locations' THEN 'المخزون - المواقع'
                    WHEN p.module = 'inventory' AND p.category = 'reports' THEN 'المخزون - التقارير'
                    WHEN p.module = 'inventory' THEN 'إدارة المخزون'
                    WHEN p.module = 'users' THEN 'إدارة المستخدمين'
                    WHEN p.module = 'roles' THEN 'إدارة الأدوار'
                    WHEN p.module = 'projects' THEN 'إدارة المشاريع'
                    WHEN p.module = 'reports' THEN 'التقارير'
                    WHEN p.module = 'settings' THEN 'الإعدادات'
                    ELSE COALESCE(p.module, 'عام')
                END as category,
                CASE WHEN rp.permission_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role_id = ?
            ORDER BY category, p.display_name
        ");
    }

    $stmt->execute([$roleId]);
    $allPermissions = $stmt->fetchAll();

    // تجميع الصلاحيات حسب الفئة مع ترتيب خاص للمخزون
    $permissionsByCategory = [];
    $assignedCount = 0;

    // ترتيب خاص لفئات المخزون
    $inventoryOrder = [
        'access' => 'الوصول الأساسي',
        'materials' => 'إدارة المواد',
        'transactions' => 'معاملات المخزون',
        'requests' => 'طلبات الصرف',
        'locations' => 'إدارة المواقع',
        'reports' => 'التقارير',
        'certificates' => 'شهادات الإنجاز'
    ];

    foreach ($allPermissions as $permission) {
        $category = $permission['category'] ?: 'عام';

        // ترجمة أسماء الفئات للمخزون
        if ($permission['module'] == 'inventory' && isset($inventoryOrder[$category])) {
            $category = $inventoryOrder[$category];
        }

        $permissionsByCategory[$category][] = $permission;
        if ($permission['is_assigned']) {
            $assignedCount++;
        }
    }
    
} catch (Exception $e) {
    $error = "حدث خطأ أثناء جلب البيانات: " . $e->getMessage();
}

// متغيرات الصفحة
$pageTitle = 'إدارة صلاحيات الدور: ' . ($role['display_name'] ?? 'غير محدد');
$currentPage = 'roles';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => '/etganplus/public/dashboard.php'],
    ['title' => 'إدارة الأدوار', 'url' => '/etganplus/public/roles/index.php'],
    ['title' => 'تفاصيل الدور', 'url' => '/etganplus/public/roles/view.php?id=' . $roleId],
    ['title' => 'إدارة الصلاحيات', 'url' => '']
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
                        <i class="fas fa-key text-primary me-2"></i>
                        إدارة صلاحيات الدور
                    </h1>
                    <p class="text-muted mb-0">
                        إدارة صلاحيات الدور: <strong><?= htmlspecialchars($role['display_name']) ?></strong>
                    </p>
                </div>
                <div>
                    <a href="view.php?id=<?= $role['id'] ?>" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-right me-1"></i>
                        العودة لتفاصيل الدور
                    </a>
                    <button type="button" class="btn btn-success" onclick="savePermissions()">
                        <i class="fas fa-save me-1"></i>
                        حفظ التغييرات
                    </button>
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

    <!-- معلومات الدور -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                            <i class="fas fa-user-shield text-primary fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1"><?= htmlspecialchars($role['display_name']) ?></h5>
                            <p class="text-muted mb-0"><?= htmlspecialchars($role['description'] ?: 'لا يوجد وصف') ?></p>
                        </div>
                        <div class="text-end">
                            <h4 class="text-primary mb-0"><?= $assignedCount ?></h4>
                            <small class="text-muted">صلاحية مخصصة</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-around">
                        <button type="button" class="btn btn-outline-primary" onclick="selectAllPermissions()">
                            <i class="fas fa-check-double me-1"></i>
                            تحديد الكل
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearAllPermissions()">
                            <i class="fas fa-times me-1"></i>
                            إلغاء التحديد
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الصلاحيات -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                الصلاحيات المتاحة
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($permissionsByCategory)): ?>
                <div class="row">
                    <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-primary mb-0">
                                        <i class="fas fa-folder me-1"></i>
                                        <?= htmlspecialchars($category) ?>
                                        <span class="badge bg-primary ms-2"><?= count($permissions) ?></span>
                                    </h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="toggleCategoryPermissions('<?= $category ?>')">
                                        تحديد/إلغاء الفئة
                                    </button>
                                </div>
                                
                                <?php foreach ($permissions as $permission): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input permission-checkbox" 
                                               type="checkbox" 
                                               value="<?= $permission['id'] ?>" 
                                               id="permission_<?= $permission['id'] ?>"
                                               data-category="<?= $category ?>"
                                               <?= $permission['is_assigned'] ? 'checked' : '' ?>>
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
                <div class="text-center py-4">
                    <i class="fas fa-key text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">لا توجد صلاحيات</h5>
                    <p class="text-muted">لا توجد صلاحيات متاحة في النظام</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- أزرار الحفظ -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
                <a href="view.php?id=<?= $role['id'] ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </a>
                <button type="button" class="btn btn-success" onclick="savePermissions()">
                    <i class="fas fa-save me-1"></i>
                    حفظ التغييرات
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// تحديد جميع الصلاحيات
function selectAllPermissions() {
    $('.permission-checkbox').prop('checked', true);
    updatePermissionCount();
}

// إلغاء تحديد جميع الصلاحيات
function clearAllPermissions() {
    $('.permission-checkbox').prop('checked', false);
    updatePermissionCount();
}

// تحديد/إلغاء تحديد صلاحيات فئة معينة
function toggleCategoryPermissions(category) {
    const categoryCheckboxes = $(`.permission-checkbox[data-category="${category}"]`);
    const allChecked = categoryCheckboxes.length === categoryCheckboxes.filter(':checked').length;
    
    categoryCheckboxes.prop('checked', !allChecked);
    updatePermissionCount();
}

// تحديث عداد الصلاحيات
function updatePermissionCount() {
    const selectedCount = $('.permission-checkbox:checked').length;
    // يمكن إضافة تحديث للعداد هنا
}

// حفظ الصلاحيات
function savePermissions() {
    // جمع الصلاحيات المحددة
    const selectedPermissions = [];
    $('.permission-checkbox:checked').each(function() {
        selectedPermissions.push($(this).val());
    });
    
    // إرسال البيانات
    fetch('update-permissions-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            role_id: <?= $roleId ?>,
            permissions: selectedPermissions
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'تم بنجاح!',
                text: data.message,
                icon: 'success'
            }).then(() => {
                window.location.href = `view.php?id=<?= $roleId ?>`;
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
            text: 'حدث خطأ أثناء حفظ الصلاحيات',
            icon: 'error'
        });
    });
}

// تحديث العداد عند تغيير التحديد
$(document).on('change', '.permission-checkbox', function() {
    updatePermissionCount();
});

// تحديث العداد عند تحميل الصفحة
$(document).ready(function() {
    updatePermissionCount();
});
</script>

<?php
// إنهاء المحتوى وحفظه
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
