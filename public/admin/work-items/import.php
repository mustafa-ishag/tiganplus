<?php
/**
 * استيراد بنود الأعمال من Excel
 * Import Work Items from Excel
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'استيراد بنود الأعمال من Excel';
$currentPage = 'work-items';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الإدارة', 'url' => 'admin/index.php'],
    ['title' => 'إدارة بنود الأعمال', 'url' => 'admin/work-items/index.php'],
    ['title' => 'استيراد من Excel', 'url' => 'admin/work-items/import.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$error = '';
$success = '';
$importResults = null;

try {
    $db = getDB();
    
    // معالجة رفع الملف
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
        $uploadedFile = $_FILES['excel_file'];
        
        // التحقق من الملف
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('خطأ في رفع الملف');
        }
        
        $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $fileType = $uploadedFile['type'];
        $fileName = $uploadedFile['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, ['csv', 'xls', 'xlsx'])) {
            throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف CSV أو Excel');
        }
        
        // قراءة الملف
        $filePath = $uploadedFile['tmp_name'];
        $importData = [];
        
        if ($fileExtension === 'csv') {
            // قراءة ملف CSV
            if (($handle = fopen($filePath, 'r')) !== FALSE) {
                $headers = fgetcsv($handle); // تجاهل العناوين
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 6) { // التأكد من وجود البيانات الأساسية
                        $importData[] = [
                            'item_number' => trim($data[0] ?? ''),
                            'description' => trim($data[1] ?? ''),
                            'unit' => trim($data[2] ?? ''),
                            'category' => trim($data[3] ?? 'كهربائي'),
                            'subcategory' => trim($data[4] ?? ''),
                            'standard_price' => floatval($data[5] ?? 0),
                            'notes' => trim($data[6] ?? ''),
                            'is_active' => 1
                        ];
                    }
                }
                fclose($handle);
            }
        } else {
            throw new Exception('حالياً يتم دعم ملفات CSV فقط. يرجى تحويل ملف Excel إلى CSV');
        }
        
        // معالجة البيانات المستوردة
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        $db->beginTransaction();
        
        try {
            foreach ($importData as $index => $item) {
                $rowNumber = $index + 2; // +2 لأن الصف الأول عناوين والفهرس يبدأ من 0
                
                // التحقق من صحة البيانات
                if (empty($item['item_number'])) {
                    $errors[] = "الصف $rowNumber: رقم البند مطلوب";
                    $errorCount++;
                    continue;
                }
                
                if (empty($item['description'])) {
                    $errors[] = "الصف $rowNumber: وصف العمل مطلوب";
                    $errorCount++;
                    continue;
                }
                
                if (empty($item['unit'])) {
                    $errors[] = "الصف $rowNumber: وحدة القياس مطلوبة";
                    $errorCount++;
                    continue;
                }
                
                // التحقق من عدم تكرار رقم البند
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM work_items WHERE item_number = ?");
                $checkStmt->execute([$item['item_number']]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    // تحديث البند الموجود
                    $updateStmt = $db->prepare("
                        UPDATE work_items 
                        SET description = ?, unit = ?, category = ?, subcategory = ?, 
                            standard_price = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE item_number = ?
                    ");
                    
                    if ($updateStmt->execute([
                        $item['description'],
                        $item['unit'],
                        $item['category'],
                        $item['subcategory'] ?: null,
                        $item['standard_price'],
                        $item['notes'] ?: null,
                        $item['item_number']
                    ])) {
                        $successCount++;
                    } else {
                        $errors[] = "الصف $rowNumber: خطأ في تحديث البند {$item['item_number']}";
                        $errorCount++;
                    }
                } else {
                    // إدراج بند جديد
                    $insertStmt = $db->prepare("
                        INSERT INTO work_items (item_number, description, unit, category, subcategory, standard_price, notes, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($insertStmt->execute([
                        $item['item_number'],
                        $item['description'],
                        $item['unit'],
                        $item['category'],
                        $item['subcategory'] ?: null,
                        $item['standard_price'],
                        $item['notes'] ?: null,
                        $item['is_active']
                    ])) {
                        $successCount++;
                    } else {
                        $errors[] = "الصف $rowNumber: خطأ في إدراج البند {$item['item_number']}";
                        $errorCount++;
                    }
                }
            }
            
            $db->commit();
            
            $importResults = [
                'total' => count($importData),
                'success' => $successCount,
                'errors' => $errorCount,
                'error_details' => $errors
            ];
            
            if ($successCount > 0) {
                $success = "تم استيراد $successCount بند بنجاح";
                if ($errorCount > 0) {
                    $success .= " مع $errorCount خطأ";
                }
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    $error = 'خطأ في استيراد البيانات: ' . $e->getMessage();
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-file-upload text-primary me-2"></i>
                استيراد بنود الأعمال من Excel
            </h1>
            <p class="text-muted mb-0">رفع ملف Excel أو CSV لاستيراد بنود الأعمال</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-right me-1"></i>
                العودة للقائمة
            </a>
            <a href="export.php" class="btn btn-success">
                <i class="fas fa-download me-1"></i>
                تحميل نموذج
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- نموذج الرفع -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload me-2"></i>
                        رفع ملف Excel/CSV
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="excel_file" class="form-label">اختر ملف Excel أو CSV</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                   accept=".csv,.xls,.xlsx" required>
                            <div class="form-text">
                                الأنواع المدعومة: CSV, XLS, XLSX (الحد الأقصى: 10MB)
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>تنسيق الملف المطلوب:</h6>
                            <p class="mb-2">يجب أن يحتوي الملف على الأعمدة التالية بالترتيب:</p>
                            <ol class="mb-0">
                                <li><strong>رقم البند</strong> (مطلوب)</li>
                                <li><strong>وصف العمل</strong> (مطلوب)</li>
                                <li><strong>وحدة القياس</strong> (مطلوب)</li>
                                <li><strong>الفئة</strong> (اختياري)</li>
                                <li><strong>الفئة الفرعية</strong> (اختياري)</li>
                                <li><strong>السعر المعياري</strong> (اختياري)</li>
                                <li><strong>ملاحظات</strong> (اختياري)</li>
                            </ol>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>
                            رفع واستيراد
                        </button>
                    </form>
                </div>
            </div>

            <!-- نتائج الاستيراد -->
            <?php if ($importResults): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        نتائج الاستيراد
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <h4 class="text-primary"><?= $importResults['total'] ?></h4>
                                <small class="text-muted">إجمالي الصفوف</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <h4 class="text-success"><?= $importResults['success'] ?></h4>
                                <small class="text-muted">تم بنجاح</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <h4 class="text-danger"><?= $importResults['errors'] ?></h4>
                                <small class="text-muted">أخطاء</small>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($importResults['error_details'])): ?>
                    <div class="mt-4">
                        <h6 class="text-danger">تفاصيل الأخطاء:</h6>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach (array_slice($importResults['error_details'], 0, 10) as $errorDetail): ?>
                                <li><?= htmlspecialchars($errorDetail) ?></li>
                                <?php endforeach; ?>
                                <?php if (count($importResults['error_details']) > 10): ?>
                                <li class="text-muted">... و <?= count($importResults['error_details']) - 10 ?> أخطاء أخرى</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        إرشادات الاستيراد
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>مهم:</h6>
                        <ul class="mb-0 small">
                            <li>الصف الأول يجب أن يحتوي على العناوين</li>
                            <li>رقم البند يجب أن يكون فريد</li>
                            <li>إذا كان رقم البند موجود، سيتم تحديثه</li>
                            <li>البيانات المطلوبة: رقم البند، الوصف، الوحدة</li>
                            <li>تأكد من صحة البيانات قبل الرفع</li>
                        </ul>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>نصائح:</h6>
                        <ul class="mb-0 small">
                            <li>احفظ ملف Excel كـ CSV للحصول على أفضل النتائج</li>
                            <li>استخدم الترميز UTF-8 للنصوص العربية</li>
                            <li>تجنب الخلايا الفارغة في البيانات المطلوبة</li>
                            <li>راجع البيانات بعد الاستيراد</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- مثال على التنسيق -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-table me-2"></i>
                        مثال على التنسيق
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>رقم البند</th>
                                    <th>وصف العمل</th>
                                    <th>الوحدة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>ELEC-021</td>
                                    <td>تمديد كابل</td>
                                    <td>متر</td>
                                </tr>
                                <tr>
                                    <td>ELEC-022</td>
                                    <td>تركيب مفتاح</td>
                                    <td>قطعة</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
