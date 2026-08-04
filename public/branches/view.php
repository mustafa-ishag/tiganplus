<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        echo '<div class="alert alert-danger">غير مصرح لك بالوصول</div>';
        exit();
    }
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('branches_view_details')) {
    if (isset($_GET['ajax'])) {
        echo '<div class="alert alert-danger">ليس لديك صلاحية لعرض تفاصيل الفروع</div>';
        exit();
    }
    header('Location: ' . path('branches/index.php'));
    exit();
}

// التحقق من معرف الفرع
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (isset($_GET['ajax'])) {
        echo '<div class="alert alert-danger">معرف الفرع غير صحيح</div>';
        exit();
    }
    header('Location: index.php');
    exit();
}

$branchId = (int) $_GET['id'];

try {
    $db = getDB();

    // جلب بيانات الفرع
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        if (isset($_GET['ajax'])) {
            echo '<div class="alert alert-danger">الفرع غير موجود</div>';
            exit();
        }
        header('Location: index.php');
        exit();
    }

    // جلب عدد المستخدمين المرتبطين بالفرع
    $stmt = $db->prepare("SELECT COUNT(*) as user_count FROM users WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];

    // جلب عدد أوامر العمل المرتبطة بالفرع
    $stmt = $db->prepare("SELECT COUNT(*) as work_order_count FROM work_orders WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    $workOrderCount = $stmt->fetch(PDO::FETCH_ASSOC)['work_order_count'];

} catch (Exception $e) {
    if (isset($_GET['ajax'])) {
        echo '<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات</div>';
        exit();
    }
    $error = 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage();
}

// إذا كان طلب AJAX، إرجاع المحتوى فقط
if (isset($_GET['ajax'])) {
    ?>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <i class="fas fa-info-circle text-primary"></i>
                    <h6 class="mb-0">معلومات أساسية</h6>
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-barcode me-2"></i>رمز الفرع
                        </div>
                        <div class="info-value">
                            <span class="badge bg-gradient-info"><?= htmlspecialchars($branch['code']) ?></span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-building me-2"></i>اسم الفرع
                        </div>
                        <div class="info-value fw-bold text-dark">
                            <?= htmlspecialchars($branch['name']) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-toggle-on me-2"></i>الحالة
                        </div>
                        <div class="info-value">
                            <?php if ($branch['status'] === 'active'): ?>
                                <span class="badge bg-gradient-success">
                                    <i class="fas fa-check-circle me-1"></i>نشط
                                </span>
                            <?php else: ?>
                                <span class="badge bg-gradient-warning">
                                    <i class="fas fa-pause-circle me-1"></i>غير نشط
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="info-card h-100">
                <div class="info-card-header">
                    <i class="fas fa-clock text-success"></i>
                    <h6 class="mb-0">معلومات زمنية</h6>
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-plus me-2"></i>تاريخ الإنشاء
                        </div>
                        <div class="info-value">
                            <?= date('Y-m-d H:i', strtotime($branch['created_at'])) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-edit me-2"></i>آخر تحديث
                        </div>
                        <div class="info-value">
                            <?= $branch['updated_at'] ? date('Y-m-d H:i', strtotime($branch['updated_at'])) : 'لم يتم التحديث' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-md-6">
            <div class="stats-card bg-gradient-primary">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-content">
                    <h3 class="stats-number"><?= $userCount ?></h3>
                    <p class="stats-label">مستخدم مرتبط</p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="stats-card bg-gradient-secondary">
                <div class="stats-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stats-content">
                    <h3 class="stats-number"><?= $workOrderCount ?></h3>
                    <p class="stats-label">أمر عمل مرتبط</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($branch['notes'])): ?>
    <div class="info-card mt-4">
        <div class="info-card-header">
            <i class="fas fa-file-alt text-info"></i>
            <h6 class="mb-0">الملاحظات</h6>
        </div>
        <div class="info-card-body">
            <p class="mb-0 text-muted"><?= htmlspecialchars($branch['notes']) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="edit.php?id=<?= $branch['id'] ?>" class="btn btn-gradient-primary btn-lg me-3">
            <i class="fas fa-edit me-2"></i>تعديل الفرع
        </a>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-lg">
            <i class="fas fa-times me-2"></i>إغلاق
        </button>
    </div>

    <style>
    .info-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        border: 1px solid #e3e6f0;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    .info-card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e3e6f0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-card-body {
        padding: 1.5rem;
    }

    .info-item {
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f3f4;
        display: flex;
        justify-content: between;
        align-items: center;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: #5a6c7d;
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
        flex: 1;
    }

    .info-value {
        color: #2d3748;
        font-size: 0.95rem;
        text-align: left;
    }

    .stats-card {
        background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
        border-radius: 15px;
        padding: 2rem;
        color: white;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        transition: transform 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
    }

    .bg-gradient-secondary {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
        box-shadow: 0 5px 20px rgba(108, 117, 125, 0.3) !important;
    }

    .stats-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }

    .stats-number {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
        line-height: 1;
    }

    .stats-label {
        margin: 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .badge.bg-gradient-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
        color: white;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .badge.bg-gradient-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
        color: white;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .badge.bg-gradient-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
        color: white;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .btn-gradient-primary {
        background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
        border: none;
        color: white;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-gradient-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        color: white;
    }
    </style>
    <?php
    exit();
}

// إذا لم يكن طلب AJAX، عرض الصفحة كاملة
$pageTitle = 'تفاصيل الفرع - ' . $branch['name'];
$currentPage = 'branches';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الفروع', 'url' => 'branches/index.php'],
    ['title' => 'تفاصيل الفرع', 'url' => '']
];

ob_start();
?>

<!-- Page Header -->
<div class="page-header-modern mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="page-title-section">
                <h1 class="page-title-modern">
                    <i class="fas fa-building me-3"></i>
                    <?= htmlspecialchars($branch['name']) ?>
                </h1>
                <p class="page-subtitle-modern">تفاصيل شاملة عن الفرع</p>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <a href="edit.php?id=<?= $branch['id'] ?>" class="btn btn-gradient-primary me-2">
                <i class="fas fa-edit me-2"></i>تعديل
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-2"></i>العودة
            </a>
        </div>
    </div>
</div>

<!-- Branch Details -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="info-card h-100">
            <div class="info-card-header">
                <i class="fas fa-info-circle text-primary"></i>
                <h6 class="mb-0">معلومات أساسية</h6>
            </div>
            <div class="info-card-body">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-barcode me-2"></i>رمز الفرع
                    </div>
                    <div class="info-value">
                        <span class="badge bg-gradient-info"><?= htmlspecialchars($branch['code']) ?></span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-building me-2"></i>اسم الفرع
                    </div>
                    <div class="info-value fw-bold text-dark">
                        <?= htmlspecialchars($branch['name']) ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-toggle-on me-2"></i>الحالة
                    </div>
                    <div class="info-value">
                        <?php if ($branch['status'] === 'active'): ?>
                            <span class="badge bg-gradient-success">
                                <i class="fas fa-check-circle me-1"></i>نشط
                            </span>
                        <?php else: ?>
                            <span class="badge bg-gradient-warning">
                                <i class="fas fa-pause-circle me-1"></i>غير نشط
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="info-card h-100">
            <div class="info-card-header">
                <i class="fas fa-clock text-success"></i>
                <h6 class="mb-0">معلومات زمنية</h6>
            </div>
            <div class="info-card-body">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-calendar-plus me-2"></i>تاريخ الإنشاء
                    </div>
                    <div class="info-value">
                        <?= date('Y-m-d H:i', strtotime($branch['created_at'])) ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-calendar-edit me-2"></i>آخر تحديث
                    </div>
                    <div class="info-value">
                        <?= $branch['updated_at'] ? date('Y-m-d H:i', strtotime($branch['updated_at'])) : 'لم يتم التحديث' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <div class="col-md-6">
        <div class="stats-card bg-gradient-primary">
            <div class="stats-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stats-content">
                <h3 class="stats-number"><?= $userCount ?></h3>
                <p class="stats-label">مستخدم مرتبط</p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="stats-card bg-gradient-secondary">
            <div class="stats-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stats-content">
                <h3 class="stats-number"><?= $workOrderCount ?></h3>
                <p class="stats-label">أمر عمل مرتبط</p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($branch['notes'])): ?>
<div class="info-card mt-4">
    <div class="info-card-header">
        <i class="fas fa-file-alt text-info"></i>
        <h6 class="mb-0">الملاحظات</h6>
    </div>
    <div class="info-card-body">
        <p class="mb-0 text-muted"><?= htmlspecialchars($branch['notes']) ?></p>
    </div>
</div>
<?php endif; ?>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

<style>
.page-header-modern {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    margin-bottom: 2rem;
}

.page-title-modern {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: white;
}

.page-subtitle-modern {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

.info-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    border: 1px solid #e3e6f0;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.info-card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e3e6f0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-card-body {
    padding: 1.5rem;
}

.info-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f3f4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #5a6c7d;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
    flex: 1;
}

.info-value {
    color: #2d3748;
    font-size: 0.95rem;
    text-align: left;
}

.stats-card {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
    border-radius: 15px;
    padding: 2rem;
    color: white;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.bg-gradient-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
    box-shadow: 0 5px 20px rgba(108, 117, 125, 0.3) !important;
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    line-height: 1;
}

.stats-label {
    margin: 0;
    opacity: 0.9;
    font-size: 1rem;
}

.badge.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.badge.bg-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.badge.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
    color: white;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.btn-gradient-primary {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
    border: none;
    color: white;
    border-radius: 10px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-gradient-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    color: white;
}
</style>

