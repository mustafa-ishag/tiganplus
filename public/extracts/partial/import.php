<?php
/**
 * صفحة استيراد المستخلصات الجزئية
 * Partial Extracts Import Page
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

$pageTitle = 'استيراد المستخلصات الجزئية';
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$importResults = null;

try {
    $db = getDB();
    
    // معالجة رفع الملف
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
        require_once __DIR__ . '/../../../includes/PartialExtractImporter.php';

        $uploadedFile = $_FILES['import_file'];
        $action = $_POST['action'] ?? 'import';

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
        $uploadDir = __DIR__ . '/../../../public/uploads/partial_extracts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'import_' . date('Y-m-d_H-i-s') . '_' . $uploadedFile['name'];
        $filePath = $uploadDir . $fileName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            throw new Exception('فشل في حفظ الملف');
        }

        // معالجة حسب الإجراء المطلوب
        if ($action === 'preview') {
            // حفظ مسار الملف في الجلسة وتوجيه للمعاينة
            $_SESSION['import_file_path'] = $filePath;
            $_SESSION['import_file_name'] = $fileName;
            header('Location: import-preview.php');
            exit();
        } else {
            // استيراد مباشر
            $importer = new PartialExtractImporter($db, $user_id);
            $importResults = $importer->import($filePath, $fileName);
        }
        
        if ($importResults['success']) {
            $success = $importResults['message'];
            $_SESSION['import_results'] = $importResults;
        } else {
            $error = $importResults['message'];
            $_SESSION['import_errors'] = $importResults['errors'];
            $_SESSION['import_duplicates'] = $importer->getDuplicates();
        }
        
        // حذف الملف بعد المعالجة
        unlink($filePath);
    }
    
    // جلب سجل العمليات الأخيرة
    $recentLogsQuery = "
        SELECT pel.*, u.full_name as user_name
        FROM partial_extract_import_export_logs pel
        LEFT JOIN users u ON pel.user_id = u.id
        WHERE pel.operation_type = 'import'
        ORDER BY pel.started_at DESC
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
                <li class="breadcrumb-item"><a href="index.php">المستخلصات الجزئية</a></li>
                <li class="breadcrumb-item active">استيراد</li>
            </ol>
        </nav>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
            
            <?php if (isset($_SESSION['import_results']['stats'])): ?>
                <div class="mt-3">
                    <h6>إحصائيات الاستيراد:</h6>
                    <ul class="mb-0">
                        <li>إجمالي السجلات: <?php echo $_SESSION['import_results']['stats']['total_records']; ?></li>
                        <li>السجلات الناجحة: <?php echo $_SESSION['import_results']['stats']['successful_records']; ?></li>
                        <li>السجلات الفاشلة: <?php echo $_SESSION['import_results']['stats']['failed_records']; ?></li>
                        <li>المستخلصات المعالجة: <?php echo $_SESSION['import_results']['stats']['extracts_processed']; ?></li>
                        <li>أوامر العمل المحدثة: <?php echo $_SESSION['import_results']['stats']['updates_made']; ?></li>
                        <li>التكرارات الموجودة: <?php echo $_SESSION['import_results']['stats']['duplicates_found']; ?></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج الاستيراد -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-file-upload me-2"></i>
                رفع ملف الاستيراد
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="import_file" class="form-label">ملف Excel للاستيراد</label>
                            <input type="file" class="form-control" id="import_file" name="import_file" 
                                   accept=".xls,.xlsx" required>
                            <div class="form-text">
                                يجب أن يكون الملف بصيغة Excel (.xls أو .xlsx) ويحتوي على البيانات بالتنسيق المطلوب
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-grid gap-2 d-md-flex">
                                <button type="submit" name="action" value="preview" class="btn btn-info">
                                    <i class="fas fa-eye me-2"></i>
                                    معاينة البيانات
                                </button>
                                <button type="submit" name="action" value="import" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>
                                    استيراد مباشر
                                </button>
                            </div>
                            <div class="mt-2">
                                <a href="export.php" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>
                                    تحميل نموذج Excel
                                </a>
                                <a href="update-sap-entry-number.php" class="btn btn-warning">
                                    <i class="fas fa-file-import me-2"></i>
                                    تحديث رقم صحيفة الإدخال (SAP)
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    العودة للقائمة
                                </a>
                            </div>
                    </form>
                </div>
                
                <div class="col-md-4">
                    <div class="card border-left-info">
                        <div class="card-body">
                            <h6 class="card-title text-info">
                                <i class="fas fa-info-circle me-2"></i>
                                تعليمات الاستيراد
                            </h6>
                            <ul class="small mb-3">
                                <li>يجب أن يحتوي الملف على جميع الأعمدة المطلوبة</li>
                                <li>عند وجود مستخلص بنفس الرقم، سيتم تحديث أوامر العمل فقط</li>
                                <li>إذا كان أمر العمل موجود في مستخلص آخر، سيتم تحديث قيمته</li>
                                <li>يجب أن تكون أسماء الفروع مطابقة للموجود في النظام</li>
                                <li class="text-danger"><strong>مهم:</strong> رقم أمر العمل يجب أن يتطابق مع نوعه - نفس الرقم يمكن أن يكون لأنواع مختلفة</li>
                            </ul>

                            <div class="alert alert-warning alert-sm mb-3">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <small><strong>ملاحظة هامة عن أوامر العمل:</strong><br>
                                النظام يتحقق من رقم أمر العمل <strong>مع كود نوعه</strong>. مثلاً:<br>
                                • أمر عمل رقم "123" بكود "CON" (أعمال إنشائية) ≠ أمر عمل رقم "123" بكود "TO3" (توصيل منزلي)<br>
                                • عمود "نوع أمر العمل" يجب أن يحتوي على <strong>الكود</strong> (مثل: CON, TO3, TR7) وليس الوصف<br>
                                • إذا كان أمر العمل موجود بكود مختلف، سيظهر خطأ في المعاينة</small>
                            </div>

                            <h6 class="card-title text-success">
                                <i class="fas fa-calendar-alt me-2"></i>
                                صيغ التواريخ المدعومة
                            </h6>
                            <div class="small">
                                <p class="mb-2"><strong>النظام يقبل التواريخ بالصيغ التالية:</strong></p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>صيغ ISO:</strong>
                                        <ul class="mb-2">
                                            <li>2025-01-15</li>
                                            <li>2025/01/15</li>
                                            <li>2025.01.15</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>صيغ عربية/أوروبية:</strong>
                                        <ul class="mb-2">
                                            <li>15-01-2025</li>
                                            <li>15/01/2025</li>
                                            <li>15.01.2025</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>صيغ أمريكية:</strong>
                                        <ul class="mb-2">
                                            <li>01-15-2025</li>
                                            <li>01/15/2025</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>صيغ أخرى:</strong>
                                        <ul class="mb-2">
                                            <li>5/1/2025 (بدون أصفار)</li>
                                            <li>15-01-25 (سنة قصيرة)</li>
                                            <li>تواريخ Excel الرقمية</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="alert alert-info alert-sm mb-0">
                                    <i class="fas fa-lightbulb me-1"></i>
                                    <small>سيتم تحويل جميع التواريخ تلقائياً إلى الصيغة الموحدة (YYYY-MM-DD)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- عرض الأخطاء إن وجدت -->
    <?php if (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-danger text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-exclamation-triangle me-2"></i>
                أخطاء الاستيراد
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>رقم الصف</th>
                            <th>رقم المستخلص</th>
                            <th>رقم أمر العمل</th>
                            <th>نوع الخطأ</th>
                            <th>رسالة الخطأ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['import_errors'] as $error): ?>
                        <tr>
                            <td><?php echo $error['row_number']; ?></td>
                            <td><?php echo htmlspecialchars($error['extract_number']); ?></td>
                            <td><?php echo htmlspecialchars($error['work_order_number']); ?></td>
                            <td>
                                <?php
                                $errorTypes = [
                                    'validation' => 'تحقق',
                                    'duplicate' => 'تكرار',
                                    'database' => 'قاعدة بيانات',
                                    'business_rule' => 'قاعدة عمل'
                                ];
                                echo $errorTypes[$error['error_type']] ?? $error['error_type'];
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($error['message']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['import_errors']); ?>
    <?php endif; ?>

    <!-- عرض التكرارات إن وجدت -->
    <?php if (isset($_SESSION['import_duplicates']) && !empty($_SESSION['import_duplicates'])): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-warning text-dark">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-copy me-2"></i>
                أوامر العمل المكررة (تم تحديثها)
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>رقم أمر العمل</th>
                            <th>المستخلص الحالي</th>
                            <th>المستخلص السابق</th>
                            <th>رقم الصف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['import_duplicates'] as $duplicate): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($duplicate['work_order_number']); ?></td>
                            <td><?php echo htmlspecialchars($duplicate['current_extract']); ?></td>
                            <td><?php echo htmlspecialchars($duplicate['existing_extract']); ?></td>
                            <td><?php echo $duplicate['row_number']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                تم تحديث قيم أوامر العمل المذكورة أعلاه في المستخلصات الجديدة وفقاً للبيانات المستوردة.
            </div>
        </div>
    </div>
    <?php unset($_SESSION['import_duplicates']); ?>
    <?php endif; ?>

    <!-- سجل العمليات الأخيرة -->
    <?php if (!empty($recentLogs)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-info">
                <i class="fas fa-history me-2"></i>
                سجل عمليات الاستيراد الأخيرة
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>اسم الملف</th>
                            <th>المستخدم</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ الانتهاء</th>
                            <th>الحالة</th>
                            <th>السجلات الناجحة</th>
                            <th>السجلات الفاشلة</th>
                            <th>التكرارات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['file_name']); ?></td>
                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                            <td><?php echo $log['started_at']; ?></td>
                            <td><?php echo $log['completed_at'] ?: '-'; ?></td>
                            <td>
                                <?php
                                $statusClass = [
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'processing' => 'warning',
                                    'pending' => 'info'
                                ];
                                $statusText = [
                                    'completed' => 'مكتمل',
                                    'failed' => 'فاشل',
                                    'processing' => 'قيد المعالجة',
                                    'pending' => 'في الانتظار'
                                ];
                                $class = $statusClass[$log['status']] ?? 'secondary';
                                $text = $statusText[$log['status']] ?? $log['status'];
                                ?>
                                <span class="badge bg-<?php echo $class; ?>"><?php echo $text; ?></span>
                            </td>
                            <td><?php echo number_format($log['successful_records']); ?></td>
                            <td><?php echo number_format($log['failed_records']); ?></td>
                            <td><?php echo number_format($log['duplicates_found']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// تنظيف متغيرات الجلسة
unset($_SESSION['import_results']);

// حفظ المحتوى
$content = ob_get_clean();

// تضمين التخطيط
include __DIR__ . '/../../includes/layout.php';
?>
