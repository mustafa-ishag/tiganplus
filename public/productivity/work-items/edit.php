<?php
/**
 * تعديل بند الإنتاجية
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين الملفات المطلوبة
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/path-helper.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_work_items_edit')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تعديل بند الإنتاجية';
$currentPage = 'productivity-work-items';

// بدء تخزين المحتوى
ob_start();

// الحصول على معرف البند
$itemId = $_GET['id'] ?? null;
if (!$itemId) {
    header('Location: ' . path('productivity/work-items/index.php?error=missing_id'));
    exit();
}

$db = getDB();

// جلب تفاصيل البند
$sql = "
    SELECT
        pwi.*,
        wo.work_order_number,
        wo.department,
        b.name as branch_name,
        wot.type_code,
        wot.description as work_order_type_name,
        wi.item_number,
        wi.description as original_description,
        wi.unit as original_unit,
        -- استخدام وصف البند من productivity_work_items أولاً، ثم من work_items كبديل
        COALESCE(pwi.work_item_description, wi.description) as work_item_description,
        COALESCE(pwi.unit, wi.unit) as unit
    FROM productivity_work_items pwi
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN work_items wi ON pwi.work_item_id = wi.id
    WHERE pwi.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: ' . path('productivity/work-items/index.php?error=item_not_found'));
    exit();
}

// معالجة النموذج
$errors = [];
$formData = [
    'target_quantity' => $item['target_quantity'],
    'unit_price' => $item['unit_price'],
    'start_date' => $item['start_date'],
    'target_end_date' => $item['target_end_date'],
    'status' => $item['status'],
    'priority' => $item['priority'],
    'notes' => $item['notes']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جلب البيانات من النموذج
    $formData = [
        'target_quantity' => floatval($_POST['target_quantity'] ?? 0),
        'unit_price' => floatval($_POST['unit_price'] ?? 0),
        'start_date' => $_POST['start_date'] ?? '',
        'target_end_date' => $_POST['target_end_date'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'priority' => $_POST['priority'] ?? 'medium',
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    // التحقق من صحة البيانات
    if ($formData['target_quantity'] <= 0) {
        $errors[] = 'الكمية المستهدفة يجب أن تكون أكبر من صفر';
    }
    
    if ($formData['unit_price'] <= 0) {
        $errors[] = 'سعر الوحدة يجب أن يكون أكبر من صفر';
    }
    
    if (empty($formData['start_date'])) {
        $errors[] = 'تاريخ البداية مطلوب';
    }
    
    if (empty($formData['target_end_date'])) {
        $errors[] = 'تاريخ الانتهاء المستهدف مطلوب';
    }
    
    if (!empty($formData['start_date']) && !empty($formData['target_end_date'])) {
        if (strtotime($formData['target_end_date']) <= strtotime($formData['start_date'])) {
            $errors[] = 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية';
        }
    }
    
    // إذا لم توجد أخطاء، قم بالتحديث
    if (empty($errors)) {
        try {
            $totalValue = $formData['target_quantity'] * $formData['unit_price'];
            $remainingQuantity = $formData['target_quantity'] - $item['actual_quantity_completed'];
            
            $updateSql = "
                UPDATE productivity_work_items 
                SET 
                    target_quantity = ?,
                    unit_price = ?,
                    total_value = ?,
                    remaining_quantity = ?,
                    start_date = ?,
                    target_end_date = ?,
                    status = ?,
                    priority = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([
                $formData['target_quantity'],
                $formData['unit_price'],
                $totalValue,
                $remainingQuantity,
                $formData['start_date'],
                $formData['target_end_date'],
                $formData['status'],
                $formData['priority'],
                $formData['notes'],
                $itemId
            ]);
            
            header('Location: ' . path('productivity/work-items/view.php?id=' . $itemId . '&success=updated'));
            exit();
            
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ أثناء تحديث البند: ' . $e->getMessage();
        }
    }
}

$deptText = $item['department'] == 'connections' ? 'التوصيلات' : 'المشاريع';
?>

<!-- رأس الصفحة -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= path('productivity/index.php') ?>">نظام الإنتاجية</a></li>
                <li class="breadcrumb-item"><a href="<?= path('productivity/work-orders/index.php') ?>">أوامر العمل</a></li>
                <li class="breadcrumb-item"><a href="<?= path('productivity/work-items/index.php?work_order_id=' . $item['work_order_id']) ?>">بنود الإنتاجية</a></li>
                <li class="breadcrumb-item"><a href="<?= path('productivity/work-items/view.php?id=' . $item['id']) ?>">تفاصيل البند</a></li>
                <li class="breadcrumb-item active">تعديل البند</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit me-2"></i>
            تعديل بند الإنتاجية
        </h1>
        <p class="text-muted mb-0">تعديل تفاصيل بند الإنتاجية</p>
    </div>
    <div>
        <a href="<?= path('productivity/work-items/view.php?id=' . $item['id']) ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            العودة للتفاصيل
        </a>
    </div>
</div>

<!-- عرض الأخطاء -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- معلومات أمر العمل -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-clipboard-list me-2"></i>
            معلومات أمر العمل
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>رقم أمر العمل:</strong>
                <p class="text-primary"><?= htmlspecialchars($item['work_order_number'] ?? '') ?></p>
            </div>
            <div class="col-md-3">
                <strong>نوع الأمر:</strong>
                <p>
                    <span class="badge bg-secondary"><?= htmlspecialchars($item['type_code'] ?? 'غير محدد') ?></span>
                    <br>
                    <small class="text-muted"><?= htmlspecialchars($item['work_order_type_name'] ?? 'غير محدد') ?></small>
                </p>
            </div>
            <div class="col-md-3">
                <strong>القسم:</strong>
                <p>
                    <span class="badge bg-<?= $item['department'] == 'connections' ? 'primary' : 'success' ?>">
                        <?= $deptText ?>
                    </span>
                </p>
            </div>
            <div class="col-md-3">
                <strong>الفرع:</strong>
                <p class="text-info"><?= htmlspecialchars($item['branch_name'] ?? '') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- نموذج التعديل -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-warning">
            <i class="fas fa-edit me-2"></i>
            تعديل تفاصيل البند
        </h6>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- معلومات البند الأساسية -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">رقم البند</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['item_number'] ?? 'غير محدد') ?>" readonly>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">الحالة <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" required>
                            <option value="active" <?= $formData['status'] == 'active' ? 'selected' : '' ?>>نشط</option>
                            <option value="completed" <?= $formData['status'] == 'completed' ? 'selected' : '' ?>>مكتمل</option>
                            <option value="paused" <?= $formData['status'] == 'paused' ? 'selected' : '' ?>>متوقف</option>
                            <option value="cancelled" <?= $formData['status'] == 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">وصف البند</label>
                <textarea class="form-control" rows="2" readonly><?= htmlspecialchars($item['work_item_description'] ?? '') ?></textarea>
                <small class="text-muted">لا يمكن تعديل وصف البند</small>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">الكمية المستهدفة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="target_quantity"
                                   value="<?= $formData['target_quantity'] ?>" step="0.001" min="0.001" required>
                            <span class="input-group-text"><?= htmlspecialchars($item['unit'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">سعر الوحدة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="unit_price" 
                                   value="<?= $formData['unit_price'] ?>" step="0.01" min="0.01" required>
                            <span class="input-group-text">ريال</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">القيمة الإجمالية</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" id="totalValue" readonly>
                            <span class="input-group-text">ريال</span>
                        </div>
                        <small class="text-muted">يتم حساب القيمة تلقائياً</small>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">تاريخ البداية <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?= $formData['start_date'] ?>" required>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">تاريخ الانتهاء المستهدف <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="target_end_date" 
                               value="<?= $formData['target_end_date'] ?>" required>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">الأولوية</label>
                        <select class="form-select" name="priority">
                            <option value="low" <?= $formData['priority'] == 'low' ? 'selected' : '' ?>>منخفضة</option>
                            <option value="medium" <?= $formData['priority'] == 'medium' ? 'selected' : '' ?>>متوسطة</option>
                            <option value="high" <?= $formData['priority'] == 'high' ? 'selected' : '' ?>>عالية</option>
                            <option value="urgent" <?= $formData['priority'] == 'urgent' ? 'selected' : '' ?>>عاجلة</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ملاحظات</label>
                <textarea class="form-control" name="notes" rows="3"
                          placeholder="أدخل أي ملاحظات إضافية..."><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="<?= path('productivity/work-items/view.php?id=' . $item['id']) ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>
                    إلغاء
                </a>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save me-2"></i>
                    حفظ التعديلات
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// إضافة JavaScript للصفحة
$pageJS = '
<script>
$(document).ready(function() {
    // حساب القيمة الإجمالية تلقائياً
    function calculateTotal() {
        var quantity = parseFloat($("input[name=\'target_quantity\']").val()) || 0;
        var price = parseFloat($("input[name=\'unit_price\']").val()) || 0;
        var total = quantity * price;
        $("#totalValue").val(total.toLocaleString("ar-SA", {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    }
    
    // حساب القيمة عند تحميل الصفحة
    calculateTotal();
    
    // حساب القيمة عند تغيير الكمية أو السعر
    $("input[name=\'target_quantity\'], input[name=\'unit_price\']").on("input", calculateTotal);
    
    // تفعيل التحقق من صحة النموذج
    $(".needs-validation").on("submit", function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass("was-validated");
    });
});
</script>
';
$content .= $pageJS;

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
