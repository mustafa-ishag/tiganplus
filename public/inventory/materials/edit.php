<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة تعديل المادة
 * Edit Material Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_materials_edit')) {
    setAlert('ليس لديك صلاحية لتعديل المواد', 'error');
    redirect('index.php');
}

$materialId = (int)($_GET['id'] ?? 0);

if ($materialId <= 0) {
    setAlert('معرف المادة غير صحيح', 'error');
    redirect('index.php');
}

$materialModel = new Material();

// الحصول على بيانات المادة
$material = $materialModel->findByIdFull($materialId);

if (!$material) {
    setAlert('المادة غير موجودة', 'error');
    redirect('index.php');
}

$errors = [];
$formData = $material; // تعبئة النموذج ببيانات المادة الحالية

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'item_number' => trim($_POST['item_number'] ?? ''),
        'minimum_stock' => (float)($_POST['minimum_stock'] ?? 0),
        'maximum_stock' => (float)($_POST['maximum_stock'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    $formData['current_stock'] = $material['current_stock']; // للعرض فقط
    // جلب بيانات الكتالوج للعرض
    $formData['description'] = $material['description'] ?? '';
    $formData['group_number'] = $material['group_number'] ?? '';
    $formData['unit'] = $material['unit'] ?? '';
    
    // التحقق من صحة البيانات
    if (empty($formData['item_number'])) {
        $errors['item_number'] = 'رقم البند مطلوب';
    } elseif (strlen($formData['item_number']) > 20) {
        $errors['item_number'] = 'رقم البند يجب أن يكون أقل من 20 حرف';
    }
    
    // group_number, description, unit تأتي من الكتالوج ولا يتم تعديلها هنا
    

    
    // المخزون الحالي لا يتم تعديله من هنا - يتم تغييره فقط عبر المعاملات
    
    if ($formData['minimum_stock'] < 0) {
        $errors['minimum_stock'] = 'الحد الأدنى للمخزون يجب أن يكون أكبر من أو يساوي صفر';
    }
    
    if ($formData['maximum_stock'] > 0 && $formData['minimum_stock'] > $formData['maximum_stock']) {
        $errors['maximum_stock'] = 'الحد الأقصى يجب أن يكون أكبر من أو يساوي الحد الأدنى';
    }
    
    // التحقق من عدم تكرار رقم البند (إذا تم تغييره)
    if ($formData['item_number'] !== $material['item_number']) {
        $existingMaterial = $materialModel->findByItemNumber($formData['item_number']);
        if ($existingMaterial) {
            $errors['item_number'] = 'رقم البند موجود بالفعل';
        }
    }
    
    // إذا لم توجد أخطاء، قم بالتحديث
    if (empty($errors)) {
        $updateData = [
            'item_number' => $formData['item_number'],
            'minimum_stock' => $formData['minimum_stock'],
            'maximum_stock' => $formData['maximum_stock'],
            'is_active' => $formData['is_active'],
            'updated_at' => getCurrentDateTime()
        ];
        
        $result = $materialModel->update($materialId, $updateData);
        
        if ($result) {
            logActivity('update_material', "تم تحديث المادة: {$formData['item_number']}");
            setAlert('تم تحديث المادة بنجاح', 'success');
            redirect("view.php?id={$materialId}");
        } else {
            $errors['general'] = 'فشل في تحديث المادة';
        }
    }
}

$pageTitle = 'تعديل المادة - ' . ($material['description'] ?? $material['item_number']);
$currentPage = 'materials';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-edit text-warning me-2"></i>
                تعديل المادة
            </h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($material['item_number']) ?> - <?= htmlspecialchars($material['description'] ?? '') ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="view.php?id=<?= $materialId ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i>
                    عرض التفاصيل
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i>
                    العودة إلى القائمة
                </a>
            </div>
        </div>
    </div>

    <!-- نموذج تعديل المادة -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">تعديل بيانات المادة</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($errors['general']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="materialForm">
                        <div class="row">
                            <!-- رقم البند -->
                            <div class="col-md-6 mb-3">
                                <label for="item_number" class="form-label">رقم البند <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['item_number']) ? 'is-invalid' : '' ?>" 
                                       id="item_number" name="item_number" 
                                       value="<?= htmlspecialchars($formData['item_number']) ?>"
                                       maxlength="20" required>
                                <?php if (isset($errors['item_number'])): ?>
                                    <div class="invalid-feedback"><?= $errors['item_number'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- رقم المجموعة (من الكتالوج) -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم المجموعة <small class="text-muted">(من الكتالوج)</small></label>
                                <input type="text" class="form-control" 
                                       value="<?= htmlspecialchars($formData['group_number'] ?? '') ?>"
                                       readonly style="background: #f8f9fa;">
                            </div>
                        </div>

                        <!-- وصف المادة (من الكتالوج) -->
                        <div class="mb-3">
                            <label class="form-label">وصف المادة <small class="text-muted">(من الكتالوج)</small></label>
                            <textarea class="form-control" rows="3" readonly
                                      style="background: #f8f9fa;"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <!-- وحدة القياس (من الكتالوج) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">وحدة القياس <small class="text-muted">(من الكتالوج)</small></label>
                                <input type="text" class="form-control"
                                       value="<?= htmlspecialchars($formData['unit'] ?? '') ?>"
                                       readonly style="background: #f8f9fa;">
                            </div>
                        </div>

                        <div class="row">
                            <!-- المخزون الحالي (للعرض فقط) -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">المخزون الحالي</label>
                                <input type="text" class="form-control" 
                                       value="<?= formatNumber($material['current_stock'], 3) ?> <?= htmlspecialchars($material['unit'] ?? '') ?>"
                                       readonly style="background: #f8f9fa; font-weight: bold;">
                                <div class="form-text">
                                    <i class="fas fa-lock text-warning me-1"></i>
                                    المخزون يتغير فقط عبر المعاملات (وارد / صادر / تسوية جرد)
                                </div>
                            </div>

                            <!-- الحد الأدنى للمخزون -->
                            <div class="col-md-4 mb-3">
                                <label for="minimum_stock" class="form-label">الحد الأدنى للمخزون</label>
                                <input type="number" class="form-control <?= isset($errors['minimum_stock']) ? 'is-invalid' : '' ?>" 
                                       id="minimum_stock" name="minimum_stock" 
                                       value="<?= $formData['minimum_stock'] ?>"
                                       min="0" step="0.001">
                                <div class="form-text">سيتم التنبيه عند الوصول لهذا الحد</div>
                                <?php if (isset($errors['minimum_stock'])): ?>
                                    <div class="invalid-feedback"><?= $errors['minimum_stock'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- الحد الأقصى للمخزون -->
                            <div class="col-md-4 mb-3">
                                <label for="maximum_stock" class="form-label">الحد الأقصى للمخزون</label>
                                <input type="number" class="form-control <?= isset($errors['maximum_stock']) ? 'is-invalid' : '' ?>" 
                                       id="maximum_stock" name="maximum_stock" 
                                       value="<?= $formData['maximum_stock'] ?>"
                                       min="0" step="0.001">
                                <div class="form-text">اختياري، للتحكم في الحد الأقصى</div>
                                <?php if (isset($errors['maximum_stock'])): ?>
                                    <div class="invalid-feedback"><?= $errors['maximum_stock'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- حالة المادة -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?= $formData['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    المادة نشطة
                                </label>
                                <div class="form-text">المواد غير النشطة لن تظهر في قوائم المواد المتاحة</div>
                            </div>
                        </div>

                        <!-- أزرار الإجراءات -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-1"></i>
                                حفظ التغييرات
                            </button>
                            <div>
                                <a href="view.php?id=<?= $materialId ?>" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-eye me-1"></i>
                                    عرض التفاصيل
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>
                                    إلغاء
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- معلومات مساعدة -->
        <div class="col-lg-4">
            <!-- معلومات المادة الحالية -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        المعلومات الحالية
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted">رقم البند:</td>
                            <td><strong><?= htmlspecialchars($material['item_number']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">رقم المجموعة:</td>
                            <td><?= htmlspecialchars($material['group_number'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">المخزون الحالي:</td>
                            <td>
                                <span class="fw-bold"><?= formatNumber($material['current_stock'], 3) ?></span>
                                <?= htmlspecialchars($material['unit'] ?? '') ?>
                            </td>
                        </tr>
                            <td class="text-muted">آخر تحديث:</td>
                            <td><?= formatDateTime($material['updated_at']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- تحذيرات -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                        تحذيرات مهمة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-1"></i> تنبيه:</h6>
                        <ul class="mb-0 small">
                            <li>تغيير <strong>رقم البند</strong> قد يؤثر على الربط مع المعاملات السابقة</li>
                            <li>تعديل <strong>المخزون الحالي</strong> سيؤثر على الكمية المتاحة مباشرة</li>
                            <li>إلغاء تفعيل المادة سيخفيها من قوائم المواد المتاحة</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// التحقق من صحة النموذج
document.getElementById('materialForm').addEventListener('submit', function(e) {
    const minStock = parseFloat(document.getElementById('minimum_stock').value) || 0;
    const maxStock = parseFloat(document.getElementById('maximum_stock').value) || 0;
    
    // التحقق من الحد الأدنى والأقصى
    if (maxStock > 0 && minStock > maxStock) {
        e.preventDefault();
        alert('الحد الأدنى للمخزون يجب أن يكون أقل من أو يساوي الحد الأقصى');
        document.getElementById('minimum_stock').focus();
        return;
    }
    
    // تأكيد التعديل
    if (!confirm('هل أنت متأكد من حفظ التغييرات؟')) {
        e.preventDefault();
        return;
    }
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../../public/includes/layout.php';
?>
