<?php
/**
 * صفحة الإدارة العامة
 * Admin Dashboard Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'الإدارة العامة';
$currentPage = 'admin';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الإدارة العامة', 'url' => 'admin/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

try {
    $db = getDB();
    
    // إحصائيات سريعة
    $stats = [];
    
    // عدد بنود الأعمال
    $workItemsCount = $db->query("SELECT COUNT(*) FROM work_items WHERE is_active = 1")->fetchColumn();
    $stats['work_items'] = $workItemsCount;
    
    // عدد الفئات
    $categoriesCount = $db->query("SELECT COUNT(DISTINCT category) FROM work_items WHERE category IS NOT NULL")->fetchColumn();
    $stats['categories'] = $categoriesCount;
    
    // عدد المستخدمين
    $usersCount = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $stats['users'] = $usersCount;
    
    // عدد شهادات الإنجاز
    $certificatesCount = $db->query("SELECT COUNT(*) FROM completion_certificates")->fetchColumn();
    $stats['certificates'] = $certificatesCount;
    
} catch (Exception $e) {
    $stats = [
        'work_items' => 0,
        'categories' => 0,
        'users' => 0,
        'certificates' => 0
    ];
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-cogs text-primary me-2"></i>
                الإدارة العامة
            </h1>
            <p class="text-muted mb-0">إدارة وتكوين النظام</p>
        </div>
    </div>

    <!-- إحصائيات سريعة -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="fas fa-tools fa-2x text-primary"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-primary"><?= number_format($stats['work_items']) ?></h4>
                    <p class="text-muted mb-0">بند عمل نشط</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="fas fa-tags fa-2x text-success"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-success"><?= number_format($stats['categories']) ?></h4>
                    <p class="text-muted mb-0">فئة أعمال</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="fas fa-users fa-2x text-info"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-info"><?= number_format($stats['users']) ?></h4>
                    <p class="text-muted mb-0">مستخدم نشط</p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-3">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="fas fa-certificate fa-2x text-warning"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold text-warning"><?= number_format($stats['certificates']) ?></h4>
                    <p class="text-muted mb-0">شهادة إنجاز</p>
                </div>
            </div>
        </div>
    </div>

    <!-- أقسام الإدارة -->
    <div class="row">
        <!-- إدارة بنود الأعمال -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>
                        إدارة بنود الأعمال
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">إدارة وتحرير بنود الأعمال الكهربائية والميكانيكية</p>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold text-primary"><?= number_format($stats['work_items']) ?></h6>
                                <small class="text-muted">بند نشط</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold text-success"><?= number_format($stats['categories']) ?></h6>
                                <small class="text-muted">فئة</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold text-info">Excel</h6>
                                <small class="text-muted">دعم</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="work-items/index.php" class="btn btn-primary">
                            <i class="fas fa-list me-2"></i>
                            إدارة البنود
                        </a>
                        <div class="btn-group">
                            <a href="work-items/create.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-1"></i>
                                إضافة بند
                            </a>
                            <a href="work-items/import.php" class="btn btn-outline-success">
                                <i class="fas fa-upload me-1"></i>
                                استيراد
                            </a>
                            <a href="work-items/export.php" class="btn btn-outline-info">
                                <i class="fas fa-download me-1"></i>
                                تصدير
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- إدارة المستخدمين -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>
                        إدارة المستخدمين
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">إدارة حسابات المستخدمين والصلاحيات</p>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold text-info"><?= number_format($stats['users']) ?></h6>
                                <small class="text-muted">مستخدم</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold text-success">متعدد</h6>
                                <small class="text-muted">الأدوار</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border rounded p-2">
                                <h6 class="fw-bold text-warning">آمن</h6>
                                <small class="text-muted">النظام</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="../users/index.php" class="btn btn-info">
                            <i class="fas fa-users me-2"></i>
                            إدارة المستخدمين
                        </a>
                        <div class="btn-group">
                            <a href="../users/create_new.php" class="btn btn-outline-info">
                                <i class="fas fa-user-plus me-1"></i>
                                إضافة مستخدم
                            </a>
                            <a href="../users/roles.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user-tag me-1"></i>
                                الأدوار
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- البيانات المرجعية -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs me-2"></i>
                        البيانات المرجعية
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">إدارة البيانات الأساسية للنظام</p>
                    
                    <div class="list-group list-group-flush">
                        <a href="../branches/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            الفروع
                        </a>
                        <a href="../work-order-types/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-list me-2 text-success"></i>
                            أنواع أوامر العمل
                        </a>
                        <a href="../reference-data/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-database me-2 text-info"></i>
                            البيانات المرجعية
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- إعدادات النظام -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-sliders-h me-2"></i>
                        إعدادات النظام
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">تكوين وإعدادات النظام العامة</p>

                    <div class="list-group list-group-flush">
                        <a href="../settings/general.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2 text-primary"></i>
                            الإعدادات العامة
                        </a>
                        <a href="../settings/backup.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-database me-2 text-success"></i>
                            النسخ الاحتياطية
                        </a>
                        <a href="../settings/logs.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-alt me-2 text-info"></i>
                            سجلات النظام
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- إدارة تعيين المعتمدين -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-check me-2"></i>
                        إدارة تعيين المعتمدين
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">تعيين المستخدمين المخولين للموافقة على طلبات الصرف</p>

                    <div class="list-group list-group-flush">
                        <a href="approval-assignments.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-list me-2 text-success"></i>
                            عرض التعيينات
                        </a>
                        <a href="approval-assignments.php#addAssignmentModal" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus me-2 text-primary"></i>
                            إضافة تعيين جديد
                        </a>
                        <a href="../inventory/material-requests/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-clipboard-list me-2 text-info"></i>
                            طلبات الصرف
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- روابط سريعة -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-bolt me-2"></i>
                روابط سريعة
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="work-items/create.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-plus me-2"></i>
                        إضافة بند عمل
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="work-items/import.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-upload me-2"></i>
                        استيراد Excel
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="../users/create_new.php" class="btn btn-outline-info w-100">
                        <i class="fas fa-user-plus me-2"></i>
                        إضافة مستخدم
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="../inventory/completion-certificates/create.php" class="btn btn-outline-warning w-100">
                        <i class="fas fa-certificate me-2"></i>
                        شهادة إنجاز
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>
