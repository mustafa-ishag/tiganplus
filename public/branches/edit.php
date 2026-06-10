<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('branches_edit')) {
    header('Location: ' . path('branches/index.php'));
    exit();
}

// التحقق من معرف الفرع
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$branchId = (int) $_GET['id'];
$error = '';
$success = '';

try {
    $db = getDB();
    
    // جلب بيانات الفرع
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        header('Location: index.php');
        exit();
    }
    
} catch (Exception $e) {
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
}

// معالجة تحديث الفرع
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            throw new InvalidArgumentException('يرجى إدخال اسم الفرع');
        }
        
        if (empty($code)) {
            throw new InvalidArgumentException('يرجى إدخال رمز الفرع');
        }

        // التحقق من عدم تكرار الرمز
        $stmt = $db->prepare("SELECT id FROM branches WHERE code = ? AND id != ?");
        $stmt->execute([$code, $branchId]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('رمز الفرع موجود مسبقاً');
        }

        // تحديث الفرع
        $stmt = $db->prepare("UPDATE branches SET name = ?, code = ?, notes = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$name, $code, $notes, $status, $branchId]);

        if ($result) {
            $success = 'تم تحديث الفرع بنجاح';
            // تحديث البيانات المعروضة
            $branch['name'] = $name;
            $branch['code'] = $code;
            $branch['notes'] = $notes;
            $branch['status'] = $status;
        } else {
            $error = 'فشل في تحديث الفرع';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'تعديل الفرع - ' . $branch['name'];
$currentPage = 'branches';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الفروع', 'url' => 'branches/index.php'],
    ['title' => 'تعديل الفرع', 'url' => '']
];

ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>تعديل الفرع</h2>
        <p class="text-muted mb-0">تعديل بيانات الفرع: <?= htmlspecialchars($branch['name']) ?></p>
    </div>
    <div>
        <a href="view.php?id=<?= $branch['id'] ?>" class="btn btn-info me-2">
            <i class="fas fa-eye me-2"></i>عرض التفاصيل
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>العودة
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Edit Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-edit me-2"></i>
            تعديل بيانات الفرع
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">اسم الفرع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= htmlspecialchars($branch['name']) ?>" required>
                        <div class="invalid-feedback">
                            يرجى إدخال اسم الفرع
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="code" class="form-label">رمز الفرع <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" 
                               value="<?= htmlspecialchars($branch['code']) ?>" required>
                        <div class="invalid-feedback">
                            يرجى إدخال رمز الفرع
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">الملاحظات</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($branch['notes'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">الحالة</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" <?= $branch['status'] === 'active' ? 'selected' : '' ?>>نشط</option>
                    <option value="inactive" <?= $branch['status'] === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                </select>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>إلغاء
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>حفظ التغييرات
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
