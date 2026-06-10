<?php
/**
 * صفحة إضافة بند عمل جديد
 * Create Work Item Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إضافة بند عمل جديد';
$currentPage = 'work-items';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'الإدارة', 'url' => 'admin/index.php'],
    ['title' => 'إدارة بنود الأعمال', 'url' => 'admin/work-items/index.php'],
    ['title' => 'إضافة بند جديد', 'url' => 'admin/work-items/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$error = '';
$success = '';
$formData = [
    'item_number' => '',
    'description' => '',
    'unit' => '',
    'category' => 'كهربائي',
    'subcategory' => '',
    'standard_price' => '',
    'notes' => '',
    'is_active' => 1
];

try {
    $db = getDB();
    
    // معالجة إرسال النموذج
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = [
            'item_number' => trim($_POST['item_number'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'unit' => trim($_POST['unit'] ?? ''),
            'category' => trim($_POST['category'] ?? 'كهربائي'),
            'subcategory' => trim($_POST['subcategory'] ?? ''),
            'standard_price' => floatval($_POST['standard_price'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // التحقق من صحة البيانات
        $errors = [];
        
        if (empty($formData['item_number'])) {
            $errors[] = 'رقم البند مطلوب';
        } else {
            // التحقق من عدم تكرار رقم البند
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM work_items WHERE item_number = ?");
            $checkStmt->execute([$formData['item_number']]);
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = 'رقم البند موجود مسبقاً';
            }
        }
        
        if (empty($formData['description'])) {
            $errors[] = 'وصف العمل مطلوب';
        }
        
        if (empty($formData['unit'])) {
            $errors[] = 'وحدة القياس مطلوبة';
        }
        
        if ($formData['standard_price'] < 0) {
            $errors[] = 'السعر المعياري لا يمكن أن يكون سالباً';
        }
        
        if (empty($errors)) {
            // إدراج البند الجديد
            $insertStmt = $db->prepare("
                INSERT INTO work_items (item_number, description, unit, category, subcategory, standard_price, notes, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($insertStmt->execute([
                $formData['item_number'],
                $formData['description'],
                $formData['unit'],
                $formData['category'],
                $formData['subcategory'] ?: null,
                $formData['standard_price'],
                $formData['notes'] ?: null,
                $formData['is_active']
            ])) {
                $success = 'تم إضافة البند بنجاح';
                // إعادة تعيين النموذج
                $formData = [
                    'item_number' => '',
                    'description' => '',
                    'unit' => '',
                    'category' => 'كهربائي',
                    'subcategory' => '',
                    'standard_price' => '',
                    'notes' => '',
                    'is_active' => 1
                ];
            } else {
                $error = 'حدث خطأ أثناء إضافة البند';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
    // جلب الفئات الموجودة
    $categoriesQuery = "SELECT DISTINCT category FROM work_items WHERE category IS NOT NULL ORDER BY category";
    $categories = $db->query($categoriesQuery)->fetchAll(PDO::FETCH_COLUMN);
    
    // جلب الفئات الفرعية الموجودة
    $subcategoriesQuery = "SELECT DISTINCT subcategory FROM work_items WHERE subcategory IS NOT NULL ORDER BY subcategory";
    $subcategories = $db->query($subcategoriesQuery)->fetchAll(PDO::FETCH_COLUMN);
    
    // جلب الوحدات الموجودة
    $unitsQuery = "SELECT DISTINCT unit FROM work_items WHERE unit IS NOT NULL ORDER BY unit";
    $units = $db->query($unitsQuery)->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $error = 'خطأ في تحميل البيانات: ' . $e->getMessage();
    $categories = [];
    $subcategories = [];
    $units = [];
}

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-plus-circle text-primary me-2"></i>
                إضافة بند عمل جديد
            </h1>
            <p class="text-muted mb-0">إضافة بند عمل جديد إلى قاعدة البيانات</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right me-1"></i>
                العودة للقائمة
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= $error ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- نموذج الإضافة -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit me-2"></i>
                        بيانات البند
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <!-- رقم البند -->
                            <div class="col-md-6">
                                <label for="item_number" class="form-label">رقم البند <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="item_number" name="item_number" 
                                       value="<?= htmlspecialchars($formData['item_number']) ?>" 
                                       placeholder="مثال: ELEC-021" required>
                                <div class="form-text">رقم فريد للبند (مثال: ELEC-021)</div>
                            </div>

                            <!-- الوحدة -->
                            <div class="col-md-6">
                                <label for="unit" class="form-label">وحدة القياس <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="unit" name="unit" 
                                       value="<?= htmlspecialchars($formData['unit']) ?>" 
                                       placeholder="مثال: قطعة، متر، كيلو" 
                                       list="unitsList" required>
                                <datalist id="unitsList">
                                    <?php foreach ($units as $unit): ?>
                                    <option value="<?= htmlspecialchars($unit) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <!-- وصف العمل -->
                            <div class="col-12">
                                <label for="description" class="form-label">وصف العمل <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="وصف مفصل للعمل المطلوب" required><?= htmlspecialchars($formData['description']) ?></textarea>
                            </div>

                            <!-- الفئة -->
                            <div class="col-md-6">
                                <label for="category" class="form-label">الفئة</label>
                                <input type="text" class="form-control" id="category" name="category" 
                                       value="<?= htmlspecialchars($formData['category']) ?>" 
                                       placeholder="مثال: كهربائي، ميكانيكي" 
                                       list="categoriesList">
                                <datalist id="categoriesList">
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <!-- الفئة الفرعية -->
                            <div class="col-md-6">
                                <label for="subcategory" class="form-label">الفئة الفرعية</label>
                                <input type="text" class="form-control" id="subcategory" name="subcategory" 
                                       value="<?= htmlspecialchars($formData['subcategory']) ?>" 
                                       placeholder="مثال: تمديدات، مفاتيح، مآخذ" 
                                       list="subcategoriesList">
                                <datalist id="subcategoriesList">
                                    <?php foreach ($subcategories as $subcategory): ?>
                                    <option value="<?= htmlspecialchars($subcategory) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <!-- السعر المعياري -->
                            <div class="col-md-6">
                                <label for="standard_price" class="form-label">السعر المعياري (ريال)</label>
                                <input type="number" class="form-control" id="standard_price" name="standard_price" 
                                       value="<?= $formData['standard_price'] ?>" 
                                       step="0.01" min="0" placeholder="0.00">
                                <div class="form-text">السعر المعياري للوحدة الواحدة</div>
                            </div>

                            <!-- الحالة -->
                            <div class="col-md-6">
                                <label class="form-label">الحالة</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?= $formData['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        نشط (متاح للاستخدام)
                                    </label>
                                </div>
                            </div>

                            <!-- الملاحظات -->
                            <div class="col-12">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="ملاحظات إضافية (اختياري)"><?= htmlspecialchars($formData['notes']) ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                حفظ البند
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>
                                إعادة تعيين
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        إرشادات
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>نصائح:</h6>
                        <ul class="mb-0 small">
                            <li>استخدم رقم بند فريد ومنطقي</li>
                            <li>اكتب وصفاً واضحاً ومفصلاً</li>
                            <li>حدد وحدة القياس بدقة</li>
                            <li>السعر المعياري يمكن تعديله لاحقاً</li>
                            <li>استخدم الفئات لتنظيم البنود</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>تنبيه:</h6>
                        <p class="mb-0 small">
                            تأكد من صحة البيانات قبل الحفظ. 
                            رقم البند لا يمكن تغييره بعد الحفظ.
                        </p>
                    </div>
                </div>
            </div>

            <!-- أمثلة -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-examples me-2"></i>
                        أمثلة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <strong>أمثلة على أرقام البنود:</strong>
                        <ul class="mt-2">
                            <li>ELEC-021 (كهربائي)</li>
                            <li>MECH-001 (ميكانيكي)</li>
                            <li>PLUMB-001 (سباكة)</li>
                        </ul>

                        <strong class="mt-3 d-block">أمثلة على الوحدات:</strong>
                        <ul class="mt-2">
                            <li>قطعة، متر، كيلو</li>
                            <li>لوحة، نقطة، مجموعة</li>
                            <li>ساعة، يوم، مشروع</li>
                        </ul>
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
