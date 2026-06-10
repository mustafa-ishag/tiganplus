<?php
/**
 * تأكيد استيراد المستخلصات الجزئية
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_import')) {
    header('Location: index.php');
    exit();
}
require_once __DIR__ . '/../../../includes/PartialExtractImporter.php';

// إعداد متغيرات الصفحة
$pageTitle = 'تأكيد استيراد المستخلصات الجزئية';
$currentPage = 'extracts-partial';
$breadcrumbs = [
    ['title' => 'المستخلصات', 'url' => '../index.php'],
    ['title' => 'المستخلصات الجزئية', 'url' => 'index.php'],
    ['title' => 'استيراد', 'url' => 'import.php'],
    ['title' => 'تأكيد', 'url' => '']
];

$result = null;
$errors = [];

// معالجة تأكيد الاستيراد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_data'])) {
    try {
        $previewData = json_decode($_POST['preview_data'], true);
        
        if (empty($previewData)) {
            throw new Exception('لا توجد بيانات للاستيراد');
        }
        
        // إنشاء مستورد
        $db = getDB();
        $importer = new PartialExtractImporter($db, $_SESSION['user_id']);
        
        // تنفيذ الاستيراد
        $result = $importer->confirmImport($previewData);

        // تنظيف الملف المؤقت
        if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
            unlink($_SESSION['import_file_path']);
            unset($_SESSION['import_file_path']);
            unset($_SESSION['import_file_name']);
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
} else {
    // إعادة توجيه إذا لم تكن هناك بيانات
    header('Location: import.php');
    exit();
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-check-circle text-primary me-2"></i>
                نتيجة استيراد المستخلصات الجزئية
            </h1>
            <p class="text-muted mb-0">نتائج عملية الاستيراد النهائية</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-list me-1"></i>
                عرض المستخلصات
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <!-- عرض الأخطاء -->
    <div class="alert alert-danger">
        <h6 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            فشل في الاستيراد
        </h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <hr>
        <div class="mb-0">
            <a href="import.php" class="btn btn-outline-danger">
                <i class="fas fa-redo me-1"></i>
                إعادة المحاولة
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($result && $result['success']): ?>
    <!-- نتيجة ناجحة -->
    <div class="alert alert-success">
        <h6 class="alert-heading">
            <i class="fas fa-check-circle me-2"></i>
            تم الاستيراد بنجاح!
        </h6>
        <p class="mb-2"><?= htmlspecialchars($result['message']) ?></p>
    </div>

    <!-- إحصائيات الاستيراد -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">مستخلصات جديدة</h6>
                            <h3 class="mb-0"><?= $result['stats']['new_extracts'] ?? 0 ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-plus-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">مستخلصات محدثة</h6>
                            <h3 class="mb-0"><?= $result['stats']['updated_extracts'] ?? 0 ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-edit fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">أوامر عمل</h6>
                            <h3 class="mb-0"><?= $result['stats']['work_orders'] ?? 0 ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-tasks fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">تكرارات معالجة</h6>
                            <h3 class="mb-0"><?= $result['stats']['duplicates_handled'] ?? 0 ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-copy fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($result['processed_extracts'])): ?>
    <!-- تفاصيل المستخلصات المعالجة -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                المستخلصات المعالجة
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم الفاتورة</th>
                            <th>الفرع</th>
                            <th>القسم</th>
                            <th>المبلغ الإجمالي</th>
                            <th>أوامر العمل</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['processed_extracts'] as $extract): ?>
                        <tr>
                            <td><?= htmlspecialchars($extract['extract_number']) ?></td>
                            <td><?= htmlspecialchars($extract['invoice_number']) ?></td>
                            <td><?= htmlspecialchars($extract['branch_name']) ?></td>
                            <td><?= htmlspecialchars($extract['department']) ?></td>
                            <td><?= number_format($extract['total_amount'], 2) ?> ريال</td>
                            <td><?= $extract['work_orders_count'] ?></td>
                            <td>
                                <?php if ($extract['action'] === 'created'): ?>
                                    <span class="badge bg-success">جديد</span>
                                <?php elseif ($extract['action'] === 'updated'): ?>
                                    <span class="badge bg-info">محدث</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">معالج</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?= $extract['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>
                                    عرض
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($result['duplicates'])): ?>
    <!-- تفاصيل التكرارات المعالجة -->
    <div class="card shadow mt-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="card-title mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                التكرارات المعالجة
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>رقم أمر العمل</th>
                            <th>نوع أمر العمل</th>
                            <th>المستخلص السابق</th>
                            <th>المستخلص الجديد</th>
                            <th>الإجراء المتخذ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['duplicates'] as $duplicate): ?>
                        <tr>
                            <td><?= htmlspecialchars($duplicate['work_order_number']) ?></td>
                            <td><?= htmlspecialchars($duplicate['work_order_type']) ?></td>
                            <td><?= htmlspecialchars($duplicate['previous_extract']) ?></td>
                            <td><?= htmlspecialchars($duplicate['new_extract']) ?></td>
                            <td>
                                <?php if ($duplicate['action'] === 'updated'): ?>
                                    <span class="badge bg-info">تم التحديث</span>
                                <?php elseif ($duplicate['action'] === 'merged'): ?>
                                    <span class="badge bg-success">تم الدمج</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">معالج</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- أزرار الإجراءات -->
    <div class="mt-4 text-center">
        <a href="index.php" class="btn btn-primary btn-lg me-3">
            <i class="fas fa-list me-2"></i>
            عرض جميع المستخلصات
        </a>
        <a href="import.php" class="btn btn-outline-secondary btn-lg">
            <i class="fas fa-upload me-2"></i>
            استيراد ملف آخر
        </a>
    </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../../public/includes/layout.php';
?>
