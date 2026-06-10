<?php
/**
 * صفحة تأكيد استيراد المستخلصات النهائية للجزئية
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
require_once __DIR__ . '/../../../includes/FinalForPartialExtractImporter.php';

$pageTitle = 'تأكيد استيراد المستخلصات النهائية للجزئية';
$user_id = $_SESSION['user_id'];

// التحقق من وجود بيانات المعاينة
if (!isset($_SESSION['preview_data']) || empty($_SESSION['preview_data'])) {
    $_SESSION['error_message'] = 'لا توجد بيانات للاستيراد. يرجى رفع ملف جديد.';
    header('Location: import.php');
    exit();
}

$previewData = $_SESSION['preview_data'];
$calculations = $_SESSION['preview_calculations'] ?? [];

// معالجة تأكيد الاستيراد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $db = getDB();
        $importer = new FinalForPartialExtractImporter($db, $user_id);
        
        // تأكيد الاستيراد
        $result = $importer->confirmImport($previewData);
        
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            $_SESSION['import_stats'] = $result['stats'];
            
            // تنظيف متغيرات الجلسة
            unset($_SESSION['preview_data']);
            unset($_SESSION['preview_calculations']);
            unset($_SESSION['import_file_path']);
            unset($_SESSION['import_file_name']);
            
            // حذف الملف المؤقت إن وجد
            if (isset($_SESSION['import_file_path']) && file_exists($_SESSION['import_file_path'])) {
                unlink($_SESSION['import_file_path']);
            }
            
            // إعادة التوجيه لصفحة الاستيراد
            header('Location: import.php');
            exit();
        } else {
            $error = $result['message'];
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-warning">
                <i class="fas fa-check-circle text-warning me-2"></i>
                تأكيد استيراد المستخلصات النهائية للجزئية
            </h1>
            <p class="text-muted mb-0">مراجعة نهائية قبل تأكيد الاستيراد</p>
        </div>
        <div>
            <a href="import.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                إلغاء والعودة
            </a>
        </div>
    </div>

    <!-- عرض الأخطاء -->
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h5 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            حدث خطأ أثناء الاستيراد
        </h5>
        <hr>
        <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ملخص الإحصائيات -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                إجمالي الصفوف
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['total_rows'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                الصفوف الصحيحة
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['valid_rows'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                إجمالي الغرامات
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['total_penalty'] ?? 0, 2); ?> ر.س
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                الصافي المتوقع
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($calculations['total_net'] ?? 0, 2); ?> ر.س
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- تحذير هام -->
    <div class="alert alert-warning" role="alert">
        <h5 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            تحذير هام
        </h5>
        <hr>
        <ul class="mb-0">
            <li>سيتم استيراد <strong><?php echo number_format($calculations['valid_rows'] ?? 0); ?></strong> صف من البيانات</li>
            <li>إذا كان المستخلص موجوداً، سيتم تحديث بياناته</li>
            <li>سيتم حذف أوامر العمل القديمة واستبدالها بالجديدة</li>
            <li>هذه العملية <strong>لا يمكن التراجع عنها</strong></li>
        </ul>
    </div>

    <!-- ملخص مالي -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-info text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-calculator me-2"></i>
                الملخص المالي للاستيراد
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p class="mb-1"><strong>المبلغ الإجمالي:</strong></p>
                    <p class="h5 text-primary"><?php echo number_format($calculations['total_amount'] ?? 0, 2); ?> ر.س</p>
                </div>
                <div class="col-md-3">
                    <p class="mb-1"><strong>الضريبة (15%):</strong></p>
                    <p class="h5 text-success"><?php echo number_format($calculations['total_tax'] ?? 0, 2); ?> ر.س</p>
                </div>
                <div class="col-md-3">
                    <p class="mb-1"><strong>إجمالي الغرامات:</strong></p>
                    <p class="h5 text-danger"><?php echo number_format($calculations['total_penalty'] ?? 0, 2); ?> ر.س</p>
                </div>
                <div class="col-md-3">
                    <p class="mb-1"><strong>الصافي:</strong></p>
                    <p class="h5 text-info"><?php echo number_format($calculations['total_net'] ?? 0, 2); ?> ر.س</p>
                </div>
            </div>
            <hr>
            <p class="mb-0 small text-muted">
                <i class="fas fa-info-circle me-1"></i>
                الصافي = المبلغ الإجمالي + الضريبة - الغرامات
            </p>
        </div>
    </div>

    <!-- نموذج التأكيد -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-success text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-check me-2"></i>
                تأكيد الاستيراد
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="confirmForm">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmCheckbox" required>
                        <label class="form-check-label" for="confirmCheckbox">
                            <strong>أؤكد أنني راجعت البيانات وأرغب في المتابعة بالاستيراد</strong>
                        </label>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <button type="submit" name="confirm" class="btn btn-success btn-lg" id="confirmButton" disabled>
                        <i class="fas fa-check me-2"></i>
                        تأكيد الاستيراد
                    </button>
                    <a href="import.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- معاينة عينة من البيانات -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                معاينة عينة من البيانات (أول 10 صفوف)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>الفرع</th>
                            <th>المستخلص الجزئي</th>
                            <th>رقم أمر العمل</th>
                            <th>القيمة</th>
                            <th>الغرامة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sampleData = array_slice($previewData, 0, 10);
                        foreach ($sampleData as $row): 
                            if ($row['status'] !== 'valid') continue;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['related_partial_extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['work_order_number']); ?> (<?php echo htmlspecialchars($row['work_order_type_code']); ?>)</td>
                            <td><?php echo number_format($row['extract_value'], 2); ?> ر.س</td>
                            <td><?php echo number_format($row['penalty_amount'], 2); ?> ر.س</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($previewData) > 10): ?>
            <p class="text-muted small mb-0">
                <i class="fas fa-info-circle me-1"></i>
                يتم عرض أول 10 صفوف فقط. إجمالي الصفوف: <?php echo count($previewData); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين التخطيط
include __DIR__ . '/../../includes/layout.php';
?>

<script>
$(document).ready(function() {
    // تفعيل زر التأكيد عند تحديد الـ checkbox
    $('#confirmCheckbox').change(function() {
        $('#confirmButton').prop('disabled', !this.checked);
    });

    // تأكيد إضافي عند الإرسال
    $('#confirmForm').submit(function(e) {
        if (!confirm('هل أنت متأكد من رغبتك في استيراد البيانات؟ هذه العملية لا يمكن التراجع عنها.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

