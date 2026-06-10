<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'إضافة فرع جديد';
$currentPage = 'branches';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة الفروع', 'url' => 'branches/index.php'],
    ['title' => 'إضافة فرع جديد', 'url' => 'branches/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('branches_create')) {
    header('Location: ' . path('branches/index.php'));
    exit();
}

$error = '';
$success = '';

// معالجة إنشاء الفرع
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $notes = trim($_POST['notes'] ?? '') ?: null;

        if (empty($code) || empty($name)) {
            throw new InvalidArgumentException('يرجى إدخال رمز الفرع واسم الفرع');
        }

        // إنشاء الفرع في قاعدة البيانات
        $db = getDB();

        // التحقق من عدم تكرار الرمز
        $stmt = $db->prepare("SELECT id FROM branches WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            throw new InvalidArgumentException('رمز الفرع موجود مسبقاً');
        }

        // إدراج الفرع الجديد
        $stmt = $db->prepare("INSERT INTO branches (code, name, notes, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
        $result = $stmt->execute([$code, $name, $notes]);

        if ($result) {
            $success = 'تم إنشاء الفرع بنجاح';
            // إعادة توجيه بعد ثانيتين
            header('refresh:2;url=index.php');
        } else {
            throw new Exception('فشل في إنشاء الفرع');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// تضمين header

$breadcrumbs = array (
  0 => 
  array (
    'title' => 'الرئيسية',
    'url' => 'dashboard.php',
  ),
  1 => 
  array (
    'title' => 'إدارة الفروع',
    'url' => 'branches/index.php',
  ),
  2 => 
  array (
    'title' => 'إضافة فرع جديد',
    'url' => 'branches/create.php',
  ),
);

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus text-primary me-2"></i>
                إضافة فرع جديد
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo path('../dashboard.php'); ?>">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo path('index.php'); ?>">الفروع</a></li>
                    <li class="breadcrumb-item active">إضافة فرع جديد</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
            <br><small>جاري إعادة التوجيه...</small>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة الفرع -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-white">
                <i class="fas fa-building me-2"></i>
                بيانات الفرع الجديد
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" id="createBranchForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="code" name="code" 
                                   placeholder="رمز الفرع" required maxlength="10"
                                   value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
                            <label for="code">
                                <i class="fas fa-code me-2"></i>رمز الفرع *
                            </label>
                            <div class="form-text">
                                رمز مختصر للفرع (مثل: TAF, RAN) - أحرف إنجليزية وأرقام فقط
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="اسم الفرع" required maxlength="100"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            <label for="name">
                                <i class="fas fa-building me-2"></i>اسم الفرع *
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-floating">
                    <textarea class="form-control" id="notes" name="notes"
                              placeholder="ملاحظات الفرع" style="height: 120px;" maxlength="500"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    <label for="notes">
                        <i class="fas fa-align-right me-2"></i>ملاحظات الفرع
                    </label>
                    <div class="form-text">
                        ملاحظات اختيارية للفرع (الحد الأقصى 500 حرف)
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="<?php echo path('index.php'); ?>" class="btn btn-secondary me-md-2">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>
                        حفظ الفرع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // تركيز على حقل رمز الفرع
    document.getElementById("code").focus();

    // تحويل رمز الفرع إلى أحرف كبيرة
    document.getElementById("code").addEventListener("input", function(e) {
        e.target.value = e.target.value.toUpperCase();
    });

    // التحقق من صحة النموذج
    document.getElementById("createBranchForm").addEventListener("submit", function(e) {
        const code = document.getElementById("code").value.trim();
        const name = document.getElementById("name").value.trim();

        if (!code || !name) {
            e.preventDefault();
            alert("يرجى إدخال رمز الفرع واسم الفرع");
            return;
        }

        // التحقق من رمز الفرع
        if (!/^[A-Z0-9]+$/.test(code)) {
            e.preventDefault();
            alert("رمز الفرع يجب أن يحتوي على أحرف إنجليزية وأرقام فقط");
            return;
        }

        if (code.length < 2) {
            e.preventDefault();
            alert("رمز الفرع يجب أن يكون حرفين على الأقل");
            return;
        }
    });
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
