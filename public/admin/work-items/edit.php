<?php
/**
 * صفحة تعديل بند العمل
 * Edit Work Item Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'تعديل بند العمل';
$currentPage = 'work-items';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$error = '';
$success = '';
$workItem = null;

// التحقق من وجود معرف البند
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$itemId = (int)$_GET['id'];

try {
    $db = getDB();
    
    // جلب بيانات البند
    $stmt = $db->prepare("SELECT * FROM work_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $workItem = $stmt->fetch();
    
    if (!$workItem) {
        $_SESSION['error'] = 'البند المطلوب غير موجود';
        header('Location: index.php');
        exit();
    }
    
    $breadcrumbs = [
        ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
        ['title' => 'الإدارة', 'url' => 'admin/index.php'],
        ['title' => 'إدارة بنود الأعمال', 'url' => 'admin/work-items/index.php'],
        ['title' => 'تعديل: ' . $workItem['item_number'], 'url' => 'admin/work-items/edit.php?id=' . $itemId]
    ];
    
    // معالجة إرسال النموذج
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = [
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
            // تحديث البند
            $updateStmt = $db->prepare("
                UPDATE work_items 
                SET description = ?, unit = ?, category = ?, subcategory = ?, 
                    standard_price = ?, notes = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($updateStmt->execute([
                $formData['description'],
                $formData['unit'],
                $formData['category'],
                $formData['subcategory'] ?: null,
                $formData['standard_price'],
                $formData['notes'] ?: null,
                $formData['is_active'],
                $itemId
            ])) {
                $success = 'تم تحديث البند بنجاح';
                
                // إعادة جلب البيانات المحدثة
                $stmt = $db->prepare("SELECT * FROM work_items WHERE id = ?");
                $stmt->execute([$itemId]);
                $workItem = $stmt->fetch();
            } else {
                $error = 'حدث خطأ أثناء تحديث البند';
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
                <i class="fas fa-edit text-primary me-2"></i>
                تعديل بند العمل
            </h1>
            <p class="text-muted mb-0">تعديل بيانات البند: <strong><?= htmlspecialchars($workItem['item_number']) ?></strong></p>
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

    <!-- نموذج التعديل -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit me-2"></i>
                        تعديل بيانات البند
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <!-- رقم البند (للعرض فقط) -->
                            <div class="col-md-6">
                                <label class="form-label">رقم البند</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($workItem['item_number']) ?>" readonly>
                                <div class="form-text">رقم البند لا يمكن تعديله</div>
                            </div>

                            <!-- الوحدة -->
                            <div class="col-md-6">
                                <label for="unit" class="form-label">وحدة القياس <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="unit" name="unit" 
                                       value="<?= htmlspecialchars($workItem['unit']) ?>" 
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
                                          placeholder="وصف مفصل للعمل المطلوب" required><?= htmlspecialchars($workItem['description']) ?></textarea>
                            </div>

                            <!-- الفئة -->
                            <div class="col-md-6">
                                <label for="category" class="form-label">الفئة</label>
                                <input type="text" class="form-control" id="category" name="category" 
                                       value="<?= htmlspecialchars($workItem['category']) ?>" 
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
                                       value="<?= htmlspecialchars($workItem['subcategory'] ?? '') ?>" 
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
                                       value="<?= $workItem['standard_price'] ?>" 
                                       step="0.01" min="0" placeholder="0.00">
                                <div class="form-text">السعر المعياري للوحدة الواحدة</div>
                            </div>

                            <!-- الحالة -->
                            <div class="col-md-6">
                                <label class="form-label">الحالة</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                           <?= $workItem['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">
                                        نشط (متاح للاستخدام)
                                    </label>
                                </div>
                            </div>

                            <!-- الملاحظات -->
                            <div class="col-12">
                                <label for="notes" class="form-label">ملاحظات</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="ملاحظات إضافية (اختياري)"><?= htmlspecialchars($workItem['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                حفظ التغييرات
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>
                                إلغاء
                            </a>
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
                        معلومات البند
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>رقم البند:</strong></td>
                            <td><?= htmlspecialchars($workItem['item_number']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>تاريخ الإنشاء:</strong></td>
                            <td><?= date('Y-m-d H:i', strtotime($workItem['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>آخر تحديث:</strong></td>
                            <td><?= date('Y-m-d H:i', strtotime($workItem['updated_at'])) ?></td>
                        </tr>
                        <tr>
                            <td><strong>الحالة:</strong></td>
                            <td>
                                <?php if ($workItem['is_active']): ?>
                                <span class="badge bg-success">نشط</span>
                                <?php else: ?>
                                <span class="badge bg-danger">غير نشط</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تنبيه
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-0">
                        <p class="mb-2"><strong>ملاحظات مهمة:</strong></p>
                        <ul class="mb-0 small">
                            <li>رقم البند لا يمكن تعديله</li>
                            <li>التغييرات ستؤثر على جميع الاستخدامات المستقبلية</li>
                            <li>تأكد من صحة البيانات قبل الحفظ</li>
                            <li>إلغاء التفعيل سيخفي البند من القوائم</li>
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
