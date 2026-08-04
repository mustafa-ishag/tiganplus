<?php
/**
 * إعدادات الربط مع قيود
 * Qoyod Integration Settings
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../includes/functions.php';
    $db = getDB();
} catch (Exception $e) {
    echo "خطأ في الاتصال: " . $e->getMessage();
    exit();
}

// التحقق من الصلاحيات (نستخدم صلاحية إعدادات النظام العامة أو الفواتير)
if (!hasPermission('menu_site_settings') && !hasPermission('menu_site_invoice')) {
    header('Location: ../dashboard.php');
    exit();
}

// معالجة تحديث الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $api_key = trim($_POST['api_key']);
        $default_contact_id = !empty($_POST['default_contact_id']) ? (int)$_POST['default_contact_id'] : null;
        $connections_product_id = !empty($_POST['connections_product_id']) ? (int)$_POST['connections_product_id'] : null;
        $projects_product_id = !empty($_POST['projects_product_id']) ? (int)$_POST['projects_product_id'] : null;
        $connections_project_id = !empty($_POST['connections_project_id']) ? (int)$_POST['connections_project_id'] : null;
        $projects_project_id = !empty($_POST['projects_project_id']) ? (int)$_POST['projects_project_id'] : null;

        $updateQuery = "
            UPDATE qoyod_settings 
            SET api_key = ?, 
                default_contact_id = ?, 
                connections_product_id = ?, 
                projects_product_id = ?, 
                connections_project_id = ?, 
                projects_project_id = ?
        ";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([
            $api_key, 
            $default_contact_id, 
            $connections_product_id, 
            $projects_product_id, 
            $connections_project_id, 
            $projects_project_id
        ]);

        $_SESSION['success_message'] = "تم تحديث إعدادات قيود بنجاح!";
        header('Location: qoyod_settings.php');
        exit();

    } catch (Exception $e) {
        $error = "خطأ في حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$settings = null;
try {
    $stmt = $db->query("SELECT * FROM qoyod_settings LIMIT 1");
    $settings = $stmt->fetch();
    
    // تأكد من وجود صف للإعدادات
    if (!$settings) {
        $db->exec("INSERT INTO qoyod_settings (created_at) VALUES (CURRENT_TIMESTAMP)");
        $stmt = $db->query("SELECT * FROM qoyod_settings LIMIT 1");
        $settings = $stmt->fetch();
    }
} catch (Exception $e) {
    $error = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

$pageTitle = 'إعدادات الربط مع برنامج قيود';
$currentPage = 'qoyod-settings';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الإدارة العامة', 'url' => 'admin/index.php'],
    ['title' => 'إعدادات قيود', 'url' => '']
];

include __DIR__ . '/../includes/layout.php';
?>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="dash-card">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                    <div class="icon-circle bg-primary-soft me-3">
                        <i class="fas fa-link"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">إعدادات الربط مع برنامج قيود المحاسبي (Qoyod)</h5>
                </div>
                <div class="card-body p-4">
                    <form action="qoyod_settings.php" method="POST">
                        
                        <h6 class="text-primary mb-3"><i class="fas fa-key me-2"></i> بيانات الـ API</h6>
                        <div class="mb-4">
                            <label class="form-label fw-bold">مفتاح الربط (API Key)</label>
                            <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($settings['api_key'] ?? '') ?>" placeholder="أدخل مفتاح الـ API الخاص ببرنامج قيود">
                            <small class="text-muted">يمكنك الحصول على المفتاح من الإعدادات العامة في حسابك ببرنامج قيود.</small>
                        </div>

                        <hr class="my-4">
                        
                        <h6 class="text-primary mb-3"><i class="fas fa-users me-2"></i> إعدادات العميل الافتراضي</h6>
                        <div class="mb-4">
                            <label class="form-label fw-bold">رقم العميل (Contact ID)</label>
                            <input type="number" name="default_contact_id" class="form-control" value="<?= htmlspecialchars($settings['default_contact_id'] ?? '') ?>" placeholder="مثال: 1">
                            <small class="text-muted">رقم العميل في قيود الذي سيتم إنشاء فواتير المبيعات باسمه (تأكد من أنه Contact ID وليس رقم آخر).</small>
                        </div>

                        <hr class="my-4">

                        <h6 class="text-primary mb-3"><i class="fas fa-box me-2"></i> إعدادات المنتجات (Products) في قيود</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">رقم منتج "التوصيلات" (Product ID)</label>
                                <input type="number" name="connections_product_id" class="form-control" value="<?= htmlspecialchars($settings['connections_product_id'] ?? '') ?>" placeholder="مثال: 15">
                                <small class="text-muted">للمستخلصات الخاصة بقسم التوصيلات.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">رقم منتج "المشاريع" (Product ID)</label>
                                <input type="number" name="projects_product_id" class="form-control" value="<?= htmlspecialchars($settings['projects_product_id'] ?? '') ?>" placeholder="مثال: 16">
                                <small class="text-muted">للمستخلصات الخاصة بقسم المشاريع.</small>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="text-primary mb-3"><i class="fas fa-project-diagram me-2"></i> إعدادات المشاريع (Projects) في قيود</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">رقم مشروع التوصيلات (Project ID)</label>
                                <input type="number" name="connections_project_id" class="form-control" value="<?= htmlspecialchars($settings['connections_project_id'] ?? '') ?>" placeholder="مثال: 5">
                                <small class="text-muted">العقد الموحد - توصيلات (prj-uc-002)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">رقم مشروع المشاريع (Project ID)</label>
                                <input type="number" name="projects_project_id" class="form-control" value="<?= htmlspecialchars($settings['projects_project_id'] ?? '') ?>" placeholder="مثال: 6">
                                <small class="text-muted">العقد الموحد - المشاريع (prj-uc-001)</small>
                            </div>
                        </div>

                        <div class="mt-4 text-start">
                            <button type="submit" class="action-btn action-btn-primary">
                                <i class="fas fa-save"></i> حفظ الإعدادات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-end.php'; ?>
