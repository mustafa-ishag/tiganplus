<?php
/**
 * صفحة استيراد المستخلصات النهائية العادية
 * Final Regular Extracts Import Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_import')) {
    header('Location: index.php');
    exit();
}

$pageTitle = 'استيراد المستخلصات النهائية العادية';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    $db = getDB();
    
    // معالجة رفع الملف
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
        require_once __DIR__ . '/../../../includes/FinalRegularExtractImporter.php';

        $uploadedFile = $_FILES['import_file'];

        // التحقق من الملف
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطأ في رفع الملف');
        }

        $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, ['xls', 'xlsx'])) {
            throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف Excel (.xls أو .xlsx)');
        }

        // نقل الملف إلى مجلد التحميلات
        $uploadDir = __DIR__ . '/../../../public/uploads/final_regular_extracts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'import_' . date('Y-m-d_H-i-s') . '_' . $uploadedFile['name'];
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            throw new Exception('فشل في حفظ الملف');
        }

        // حفظ مسار الملف في الجلسة وتوجيه للمعاينة
        $_SESSION['import_file_path'] = $filePath;
        $_SESSION['import_file_name'] = $fileName;
        header('Location: import-preview.php');
        exit();
    }
    
    // جلب سجل العمليات الأخيرة
    $recentLogsQuery = "
        SELECT frel.*, u.full_name as user_name
        FROM final_regular_extract_import_export_logs frel
        LEFT JOIN users u ON frel.user_id = u.id
        WHERE frel.operation_type = 'import'
        ORDER BY frel.started_at DESC
        LIMIT 10
    ";
    $recentLogs = $db->query($recentLogsQuery)->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-upload me-2"></i>
            <?php echo $pageTitle; ?>
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/etganplus/public/dashboard.php">الرئيسية</a></li>
                <li class="breadcrumb-item"><a href="index.php">المستخلصات النهائية العادية</a></li>
                <li class="breadcrumb-item active">استيراد</li>
            </ol>
        </nav>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- نموذج الاستيراد -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-file-upload me-2"></i>
                        رفع ملف الاستيراد
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i>
                            تعليمات الاستيراد
                        </h6>
                        <ul class="mb-0">
                            <li>قم بتحميل نموذج Excel من <a href="export-template.php" class="alert-link">هنا</a></li>
                            <li>املأ البيانات بدءاً من الصف 5</li>
                            <li>الحقول المطلوبة: رقم المستخلص، تاريخ المستخلص، رقم أمر العمل، نوع أمر العمل، قيمة المستخلص</li>
                            <li>الفرع والقسم سيتم جلبهما تلقائياً من أمر العمل</li>
                            <li>الغرامة اختيارية (افتراضياً = 0)</li>
                            <li>سيتم حساب المبالغ تلقائياً (الضريبة 15% + الصافي)</li>
                        </ul>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="importForm">
                        <div class="mb-3">
                            <label for="import_file" class="form-label">
                                <i class="fas fa-file-excel me-1"></i>
                                ملف Excel
                            </label>
                            <input type="file" 
                                   name="import_file" 
                                   id="import_file" 
                                   class="form-control" 
                                   accept=".xls,.xlsx"
                                   required>
                            <div class="form-text">
                                الصيغ المدعومة: .xls, .xlsx
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>
                                رفع الملف ومعاينة البيانات
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- روابط سريعة -->
            <div class="card shadow mt-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-link me-2"></i>
                        روابط سريعة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="export-template.php" class="btn btn-outline-info">
                            <i class="fas fa-download me-2"></i>
                            تحميل نموذج الاستيراد
                        </a>
                        <a href="export.php" class="btn btn-outline-success">
                            <i class="fas fa-file-excel me-2"></i>
                            تصدير المستخلصات الحالية
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            العودة للقائمة
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- سجل العمليات الأخيرة -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i>
                        سجل عمليات الاستيراد الأخيرة
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentLogs)): ?>
                        <p class="text-muted text-center py-4">لا توجد عمليات استيراد سابقة</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>المستخدم</th>
                                        <th>السجلات</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo date('Y-m-d H:i', strtotime($log['started_at'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo number_format($log['records_count']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['status'] === 'success'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>
                                                        نجح
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i>
                                                        فشل
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- معلومات إضافية -->
            <div class="card shadow mt-4">
                <div class="card-header py-3 bg-warning text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-calculator me-2"></i>
                        الحسابات التلقائية
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>سيتم حساب المبالغ التالية تلقائياً:</strong></p>
                    <ul class="mb-0">
                        <li><strong>المبلغ الإجمالي:</strong> مجموع قيم أوامر العمل</li>
                        <li><strong>الضريبة:</strong> المبلغ الإجمالي × 15%</li>
                        <li><strong>إجمالي الغرامات:</strong> مجموع الغرامات</li>
                        <li><strong>الصافي:</strong> المبلغ الإجمالي + الضريبة - الغرامات</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تأكيد قبل رفع الملف
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('import_file');
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('يرجى اختيار ملف للرفع');
        return false;
    }
    
    // عرض رسالة تحميل
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري رفع الملف...';
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>

