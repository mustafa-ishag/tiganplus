<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة إضافة مادة جديدة
 * Create New Material Page
 */

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
if (!hasPermission('inventory_materials_create')) {
    setAlert('ليس لديك صلاحية لإضافة المواد', 'error');
    redirect('index.php');
}

$materialModel = new Material();


// الحصول على أرقام المجاميع المستخدمة
$usedGroupNumbers = $materialModel->getUsedGroupNumbers();

$errors = [];
$formData = [];

// ===== AJAX: جلب مواد الكتالوج غير الموجودة في المستودع =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'catalog_items') {
    header('Content-Type: application/json');
    $db = getDB();
    $search = trim($_GET['q'] ?? '');

    $sql = "
        SELECT mc.id, mc.item_number, mc.group_number, mc.description, mc.unit
        FROM material_catalog mc
        WHERE mc.item_number NOT IN (
            SELECT COALESCE(item_number,'') FROM materials WHERE item_number IS NOT NULL
        )
    ";
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (mc.item_number LIKE ? OR mc.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY mc.item_number LIMIT 50";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تنسيق لـ Select2
    $results = array_map(fn($r) => [
        'id' => $r['item_number'],
        'text' => $r['item_number'] . ' - ' . mb_substr($r['description'], 0, 60),
        'item_number' => $r['item_number'],
        'group_number' => $r['group_number'] ?? '',
        'description' => $r['description'],
        'unit' => $r['unit']
    ], $items);
    echo json_encode(['results' => $results]);
    exit();
}


// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'item_number' => trim($_POST['item_number'] ?? ''),
        'current_stock' => (float) ($_POST['current_stock'] ?? 0),
        'minimum_stock' => (float) ($_POST['minimum_stock'] ?? 0),
        'maximum_stock' => (float) ($_POST['maximum_stock'] ?? 0),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    // التحقق من صحة البيانات
    if (empty($formData['item_number'])) {
        $errors['item_number'] = 'رقم البند مطلوب';
    } elseif (strlen($formData['item_number']) > 20) {
        $errors['item_number'] = 'رقم البند يجب أن يكون أقل من 20 حرف';
    } else {
        // التحقق من وجود المادة في الكتالوج
        $db = getDB();
        $catalogCheck = $db->prepare("SELECT id FROM material_catalog WHERE item_number = ?");
        $catalogCheck->execute([$formData['item_number']]);
        if (!$catalogCheck->fetch()) {
            $errors['item_number'] = 'رقم البند غير موجود في كتالوج المواد';
        }
    }

    if ($formData['current_stock'] < 0) {
        $errors['current_stock'] = 'المخزون الحالي يجب أن يكون أكبر من أو يساوي صفر';
    }

    if ($formData['minimum_stock'] < 0) {
        $errors['minimum_stock'] = 'الحد الأدنى للمخزون يجب أن يكون أكبر من أو يساوي صفر';
    }

    if ($formData['maximum_stock'] < 0) {
        $errors['maximum_stock'] = 'الحد الأقصى للمخزون يجب أن يكون أكبر من أو يساوي صفر';
    }

    if ($formData['maximum_stock'] > 0 && $formData['minimum_stock'] > $formData['maximum_stock']) {
        $errors['minimum_stock'] = 'الحد الأدنى يجب أن يكون أقل من أو يساوي الحد الأقصى';
    }

    // التحقق من عدم تكرار رقم البند
    if (empty($errors['item_number'])) {
        $existingMaterial = $materialModel->findByItemNumber($formData['item_number']);
        if ($existingMaterial) {
            $errors['item_number'] = 'رقم البند موجود بالفعل';
        }
    }

    // إذا لم توجد أخطاء، قم بإنشاء المادة
    if (empty($errors)) {
        $result = $materialModel->createMaterial($formData);

        if ($result['success']) {
            setAlert('تم إضافة المادة بنجاح', 'success');
            redirect('/inventory/materials/view.php?id=' . $result['material_id']);
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

$pageTitle = 'إضافة مادة جديدة';
$currentPage = 'inventory';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-plus-circle text-primary me-2"></i>
                إضافة مادة جديدة
            </h2>
            <p class="text-muted mb-0">إضافة مادة كهربائية جديدة إلى المخزون</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i>
                العودة إلى قائمة المواد
            </a>
        </div>
    </div>

    <!-- نموذج إضافة المادة -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-book-open me-2"></i>
                        جلب من كتالوج المواد
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        اختر رقم البند من الكتالوج لملء التفاصيل تلقائياً.
                        تظهر فقط المواد <strong>غير الموجودة</strong> في المستودع حالياً.
                    </p>
                    <div class="input-group">
                        <select id="catalogSelect" class="form-select" style="width:100%">
                            <option value="">ابحث عن رقم البند أو الوصف...</option>
                        </select>
                        <button type="button" class="btn btn-success" id="loadFromCatalog" disabled>
                            <i class="fas fa-download me-1"></i> جلب التفاصيل
                        </button>
                    </div>
                    <div id="catalogPreview" class="alert alert-info mt-2 d-none small"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">بيانات المادة</h5>
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
                                <label for="item_number" class="form-label">
                                    رقم البند <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                    class="form-control <?= isset($errors['item_number']) ? 'is-invalid' : '' ?>"
                                    id="item_number" name="item_number"
                                    value="<?= htmlspecialchars($formData['item_number'] ?? '') ?>" maxlength="20"
                                    required>
                                <div class="form-text">حتى 20 حرف، يجب أن يكون فريد</div>
                                <?php if (isset($errors['item_number'])): ?>
                                    <div class="invalid-feedback"><?= $errors['item_number'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- معلومات المادة من الكتالوج (للقراءة فقط) -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">رقم المجموعة <small class="text-muted">(من الكتالوج)</small></label>
                                <input type="text" class="form-control" id="group_number" readonly
                                    style="background: #f8f9fa;" placeholder="سيُملأ تلقائياً من الكتالوج">
                            </div>
                        </div>

                        <!-- وصف المادة -->
                        <div class="mb-3">
                            <label class="form-label">وصف المادة <small class="text-muted">(من الكتالوج)</small></label>
                            <textarea class="form-control" id="description" rows="3" readonly
                                style="background: #f8f9fa;" placeholder="سيُملأ تلقائياً من الكتالوج"></textarea>
                        </div>

                        <div class="row">
                            <!-- وحدة القياس -->
                            <div class="col-md-4 mb-3">
                                <label class="form-label">وحدة القياس <small class="text-muted">(من الكتالوج)</small></label>
                                <input type="text" class="form-control" id="unit" readonly
                                    style="background: #f8f9fa;" placeholder="سيُملأ تلقائياً">
                            </div>
                        </div>

                        <div class="row">
                            <!-- المخزون الحالي -->
                            <div class="col-md-4 mb-3">
                                <label for="current_stock" class="form-label">المخزون الحالي</label>
                                <input type="number"
                                    class="form-control <?= isset($errors['current_stock']) ? 'is-invalid' : '' ?>"
                                    id="current_stock" name="current_stock"
                                    value="<?= $formData['current_stock'] ?? 0 ?>" min="0" step="0.001">
                                <?php if (isset($errors['current_stock'])): ?>
                                    <div class="invalid-feedback"><?= $errors['current_stock'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- الحد الأدنى للمخزون -->
                            <div class="col-md-4 mb-3">
                                <label for="minimum_stock" class="form-label">الحد الأدنى للمخزون</label>
                                <input type="number"
                                    class="form-control <?= isset($errors['minimum_stock']) ? 'is-invalid' : '' ?>"
                                    id="minimum_stock" name="minimum_stock"
                                    value="<?= $formData['minimum_stock'] ?? 0 ?>" min="0" step="0.001">
                                <div class="form-text">سيتم التنبيه عند الوصول لهذا الحد</div>
                                <?php if (isset($errors['minimum_stock'])): ?>
                                    <div class="invalid-feedback"><?= $errors['minimum_stock'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- الحد الأقصى للمخزون -->
                            <div class="col-md-4 mb-3">
                                <label for="maximum_stock" class="form-label">الحد الأقصى للمخزون</label>
                                <input type="number"
                                    class="form-control <?= isset($errors['maximum_stock']) ? 'is-invalid' : '' ?>"
                                    id="maximum_stock" name="maximum_stock"
                                    value="<?= $formData['maximum_stock'] ?? 0 ?>" min="0" step="0.001">
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
                                    <?= ($formData['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    المادة نشطة
                                </label>
                                <div class="form-text">المواد غير النشطة لن تظهر في قوائم المواد المتاحة</div>
                            </div>
                        </div>

                        <!-- أزرار الإجراءات -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                حفظ المادة
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

        <!-- معلومات مساعدة -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        معلومات مساعدة
                    </h6>
                </div>
                <div class="card-body">
                    <h6>رقم البند:</h6>
                    <p class="small text-muted">رقم فريد لكل مادة، يمكن أن يحتوي على أحرف وأرقام (حتى 20 حرف)</p>

                    <h6>رقم المجموعة:</h6>
                    <p class="small text-muted">رقم تصنيفي مكون من 10 أرقام لتجميع المواد المتشابهة</p>

                    <h6>أمثلة على أرقام المجاميع:</h6>
                    <ul class="small text-muted">
                        <li><strong>1000000000:</strong> كابلات كهربائية</li>
                        <li><strong>2000000000:</strong> أعمدة الإنارة</li>
                        <li><strong>3000000000:</strong> محولات كهربائية</li>
                        <li><strong>4000000000:</strong> لوحات التحكم</li>
                        <li><strong>5000000000:</strong> مواد العزل</li>
                    </ul>

                    <h6>وحدات القياس الشائعة:</h6>
                    <ul class="small text-muted">
                        <li><strong>متر:</strong> للكابلات والأسلاك</li>
                        <li><strong>قطعة:</strong> للأجهزة والمعدات</li>
                        <li><strong>كيلو:</strong> للمواد بالوزن</li>
                        <li><strong>لفة:</strong> للأشرطة والمواد الملفوفة</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // التحقق من صحة النموذج
    document.getElementById('materialForm').addEventListener('submit', function (e) {
        const groupNumber = document.getElementById('group_number').value;
        const minStock = parseFloat(document.getElementById('minimum_stock').value) || 0;
        const maxStock = parseFloat(document.getElementById('maximum_stock').value) || 0;

        // التحقق من رقم المجموعة
        if (!/^\d{10}$/.test(groupNumber)) {
            e.preventDefault();
            alert('رقم المجموعة يجب أن يكون 10 أرقام بالضبط');
            document.getElementById('group_number').focus();
            return;
        }

        // التحقق من الحد الأدنى والأقصى
        if (maxStock > 0 && minStock > maxStock) {
            e.preventDefault();
            alert('الحد الأدنى للمخزون يجب أن يكون أقل من أو يساوي الحد الأقصى');
            document.getElementById('minimum_stock').focus();
            return;
        }
    });

    // تنسيق رقم المجموعة أثناء الكتابة
    document.getElementById('group_number').addEventListener('input', function (e) {
        // إزالة أي شيء ليس رقم
        this.value = this.value.replace(/\D/g, '');

        // تحديد الطول إلى 10 أرقام
        if (this.value.length > 10) {
            this.value = this.value.substring(0, 10);
        }
    });
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        // تهيئة Select2 مع AJAX
        $('#catalogSelect').select2({
            placeholder: 'ابحث عن رقم البند أو الوصف...',
            allowClear: true,
            minimumInputLength: 1,
            language: {
                inputTooShort: () => 'اكتب حرفاً واحداً على الأقل للبحث',
                searching: () => 'جاري البحث...',
                noResults: () => 'لا توجد نتائج - ربما المادة موجودة مسبقاً في المستودع'
            },
            ajax: {
                url: window.location.pathname,
                dataType: 'json',
                delay: 300,
                data: params => ({ ajax: 'catalog_items', q: params.term }),
                processResults: data => ({ results: data.results }),
                cache: true
            }
        });

        // عند اختيار مادة
        $('#catalogSelect').on('select2:select', function (e) {
            const item = e.params.data;
            $('#loadFromCatalog').prop('disabled', false).data('item', item);

            // معاينة
            $('#catalogPreview').removeClass('d-none').html(
                `<strong>رقم البند:</strong> ${item.item_number} &nbsp;|
             <strong>المجموعة:</strong> ${item.group_number || '-'} &nbsp;|
             <strong>الوصف:</strong> ${item.description.substring(0, 80)}... &nbsp;|
             <strong>الوحدة:</strong> ${item.unit}`
            );
        });

        $('#catalogSelect').on('select2:clear', function () {
            $('#loadFromCatalog').prop('disabled', true);
            $('#catalogPreview').addClass('d-none');
        });

        // زر جلب التفاصيل
        $('#loadFromCatalog').on('click', function () {
            const item = $(this).data('item');
            if (!item) return;

            // ملء الحقول
            $('#item_number').val(item.item_number).prop('readonly', true);
            $('#group_number').val(item.group_number || '');
            $('#description').val(item.description);
            $('#unit').val(item.unit || 'قطعة');

            // تلميح للمستخدم
            Swal.fire({
                icon: 'success',
                title: 'تم جلب التفاصيل',
                text: `تم ملء بيانات المادة ${item.item_number} من الكتالوج. يمكنك تعديل الكميات قبل الحفظ.`,
                timer: 2500,
                showConfirmButton: false
            });

            // التمرير للنموذج
            document.getElementById('item_number').scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
</script>