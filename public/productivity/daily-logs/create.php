<?php
/**
 * تسجيل إنتاجية يومية جديدة
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين الملفات المطلوبة
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/path-helper.php';
require_once __DIR__ . '/../../../models/ProductivityDailyLog.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_daily_logs_create')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تسجيل إنتاجية يومية';
$currentPage = 'productivity-daily-logs';

// بدء تخزين المحتوى
ob_start();

$db = getDB();

// الحصول على معرف بند العمل (مطلوب)
$preselectedWorkItemId = $_GET['work_item_id'] ?? ($_POST['work_item_id'] ?? null);

if (!$preselectedWorkItemId) {
    header('Location: ' . path('productivity/work-orders/index.php?error=missing_work_item'));
    exit();
}

// جلب بيانات البند المحدد
$workItemStmt = $db->prepare("
    SELECT 
        pwi.id,
        pwi.work_item_description,
        pwi.unit,
        pwi.target_quantity,
        pwi.actual_quantity_completed,
        pwi.remaining_quantity,
        pwi.unit_price,
        wo.work_order_number,
        wo.department,
        b.name as branch_name,
        wot.type_code
    FROM productivity_work_items pwi
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    WHERE pwi.id = ? AND pwi.status = 'active'
");

$workItemStmt->execute([$preselectedWorkItemId]);
$preselectedWorkItem = $workItemStmt->fetch(PDO::FETCH_ASSOC);

if (!$preselectedWorkItem) {
    header('Location: ' . path('productivity/work-orders/index.php?error=invalid_work_item'));
    exit();
}

// معالجة النموذج
$errors = [];
$formData = [
    'work_item_id' => $preselectedWorkItemId ?? '',
    'log_date' => date('Y-m-d'),
    'quantity_completed' => '',
    'work_description' => '',
    'notes' => '',
    'weather_conditions' => '',
    'equipment_used' => '',
    'crew_size' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // جلب البيانات من النموذج
    $formData = [
        'work_item_id' => $_POST['work_item_id'] ?? '',
        'log_date' => $_POST['log_date'] ?? '',
        'quantity_completed' => floatval($_POST['quantity_completed'] ?? 0),
        'work_description' => trim($_POST['work_description'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'weather_conditions' => trim($_POST['weather_conditions'] ?? ''),
        'equipment_used' => trim($_POST['equipment_used'] ?? ''),
        'crew_size' => intval($_POST['crew_size'] ?? 0)
    ];
    
    // التحقق من صحة البيانات
    if (empty($formData['work_item_id'])) {
        $errors[] = 'يجب اختيار بند الإنتاجية';
    }
    
    if (empty($formData['log_date'])) {
        $errors[] = 'تاريخ التسجيل مطلوب';
    }
    
    if ($formData['quantity_completed'] <= 0) {
        $errors[] = 'الكمية المنجزة يجب أن تكون أكبر من صفر';
    }
    
    if (empty($formData['work_description'])) {
        $errors[] = 'وصف العمل المنجز مطلوب';
    }
    
    // التحقق من أن التاريخ ليس في المستقبل
    if (!empty($formData['log_date']) && strtotime($formData['log_date']) > time()) {
        $errors[] = 'لا يمكن تسجيل إنتاجية لتاريخ في المستقبل';
    }
    
    // التحقق من عدم تجاوز الكمية المتبقية
    if (!empty($formData['work_item_id']) && $formData['quantity_completed'] > 0) {
        if ($preselectedWorkItem && $formData['quantity_completed'] > $preselectedWorkItem['remaining_quantity']) {
            $errors[] = 'الكمية المنجزة (' . $formData['quantity_completed'] . ') تتجاوز الكمية المتبقية (' . $preselectedWorkItem['remaining_quantity'] . ')';
        }
    }
    
    // التحقق من عدم وجود تسجيل مكرر لنفس اليوم
    if (!empty($formData['work_item_id']) && !empty($formData['log_date'])) {
        $duplicateStmt = $db->prepare("
            SELECT COUNT(*) FROM productivity_daily_logs
            WHERE work_item_id = ? AND log_date = ? AND created_by = ?
        ");
        $duplicateStmt->execute([$formData['work_item_id'], $formData['log_date'], $_SESSION['user_id']]);

        if ($duplicateStmt->fetchColumn() > 0) {
            $errors[] = 'تم تسجيل إنتاجية لهذا البند في نفس التاريخ مسبقاً';
        }
    }
    
    // إذا لم توجد أخطاء، قم بالحفظ
    if (empty($errors)) {
        try {
            $dailyLogModel = new ProductivityDailyLog();
            
            // تجهيز بيانات النموذج بالحقول المطلوبة
            $logData = [
                'work_item_id' => $formData['work_item_id'],
                'log_date' => $formData['log_date'],
                'quantity_completed' => $formData['quantity_completed'],
                'work_hours' => 0, // الحقل غير مستخدم حالياً في النموذج
                'workers_count' => $formData['crew_size'] > 0 ? $formData['crew_size'] : 1,
                'equipment_used' => $formData['equipment_used'] ?: null,
                'weather_condition' => !empty($formData['weather_conditions']) ? $formData['weather_conditions'] : null,
                'work_quality' => 'good',
                'obstacles' => null,
                'notes' => trim(
                    (!empty($formData['work_description']) ? "وصف العمل: " . $formData['work_description'] : '') .
                    (!empty($formData['notes']) ? "\n\nملاحظات: " . $formData['notes'] : '')
                ) ?: null,
                'status' => 'submitted',
                'created_by' => $_SESSION['user_id']
            ];
            
            $newId = $dailyLogModel->create($logData);
            
            if ($newId) {
                // إعادة التوجيه إلى صفحة تفاصيل البند أو قائمة السجلات
                if ($preselectedWorkItemId) {
                    header('Location: ' . path('productivity/work-items/view.php?id=' . $preselectedWorkItemId . '&success=log_created'));
                } else {
                    header('Location: ' . path('productivity/daily-logs/index.php?success=log_created'));
                }
                exit();
            } else {
                $errors[] = 'حدث خطأ أثناء حفظ السجل';
            }
            
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ أثناء حفظ السجل: ' . $e->getMessage();
        }
    }
}
?>

<!-- رأس الصفحة -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= path('productivity/index.php') ?>">نظام الإنتاجية</a></li>
                <?php if ($preselectedWorkItem): ?>
                <li class="breadcrumb-item"><a href="<?= path('work-orders/index.php') ?>">أوامر العمل</a></li>
                <li class="breadcrumb-item"><a href="<?= path('productivity/work-items/view.php?id=' . $preselectedWorkItem['id']) ?>">تفاصيل البند</a></li>
                <?php else: ?>
                <li class="breadcrumb-item"><a href="<?= path('productivity/daily-logs/index.php') ?>">السجلات اليومية</a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active">تسجيل إنتاجية</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus me-2"></i>
            تسجيل إنتاجية يومية
        </h1>
        <p class="text-muted mb-0">تسجيل الكمية المنجزة والتفاصيل اليومية</p>
    </div>
    <div>
        <?php if ($preselectedWorkItem): ?>
        <a href="<?= path('productivity/work-items/view.php?id=' . $preselectedWorkItem['id']) ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            العودة للبند
        </a>
        <?php else: ?>
        <a href="<?= path('productivity/daily-logs/index.php') ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-2"></i>
            العودة للسجلات
        </a>
        <?php endif; ?>
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

<!-- معلومات البند المحدد مسبقاً -->
<?php if ($preselectedWorkItem): ?>
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-info-circle me-2"></i>
            معلومات البند المحدد
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>أمر العمل:</strong>
                <p class="text-primary"><?= htmlspecialchars($preselectedWorkItem['work_order_number'] ?? '') ?></p>
            </div>
            <div class="col-md-3">
                <strong>نوع الأمر:</strong>
                <p><span class="badge bg-secondary"><?= htmlspecialchars($preselectedWorkItem['type_code'] ?? 'غير محدد') ?></span></p>
            </div>
            <div class="col-md-3">
                <strong>القسم:</strong>
                <p>
                    <span class="badge bg-<?= $preselectedWorkItem['department'] == 'connections' ? 'primary' : 'success' ?>">
                        <?= $preselectedWorkItem['department'] == 'connections' ? 'التوصيلات' : 'المشاريع' ?>
                    </span>
                </p>
            </div>
            <div class="col-md-3">
                <strong>الفرع:</strong>
                <p class="text-info"><?= htmlspecialchars($preselectedWorkItem['branch_name'] ?? '') ?></p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <strong>وصف البند:</strong>
                <p><?= htmlspecialchars($preselectedWorkItem['work_item_description'] ?? '') ?></p>
            </div>
            <div class="col-md-6">
                <div class="row">
                    <div class="col-6">
                        <strong>الكمية المستهدفة:</strong>
                        <p><?= number_format($preselectedWorkItem['target_quantity'], 3) ?> <?= htmlspecialchars($preselectedWorkItem['unit'] ?? '') ?></p>
                    </div>
                    <div class="col-6">
                        <strong>الكمية المتبقية:</strong>
                        <p class="text-warning"><?= number_format($preselectedWorkItem['remaining_quantity'], 3) ?> <?= htmlspecialchars($preselectedWorkItem['unit'] ?? '') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- نموذج تسجيل الإنتاجية -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-success">
            <i class="fas fa-clipboard-check me-2"></i>
            تفاصيل الإنتاجية اليومية
        </h6>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">بند الإنتاجية</label>
                        <div class="p-2 border rounded bg-light">
                            <strong><?= htmlspecialchars($preselectedWorkItem['work_item_description'] ?? '') ?></strong>
                        </div>
                        <input type="hidden" name="work_item_id" value="<?= htmlspecialchars($preselectedWorkItem['id']) ?>">
                        
                        <!-- عناصر مخفية لعمل سكريبت الجافاسكربت الخاص بالحد الأقصى للكمية -->
                        <select name="_work_item_id_hidden" style="display:none;" id="hiddenWorkItemSelect">
                            <option value="<?= $preselectedWorkItem['id'] ?>" selected 
                                    data-remaining="<?= $preselectedWorkItem['remaining_quantity'] ?>"
                                    data-unit="<?= htmlspecialchars($preselectedWorkItem['unit'] ?? '') ?>">
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">تاريخ التسجيل <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="log_date" 
                               value="<?= $formData['log_date'] ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">الكمية المنجزة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="quantity_completed" 
                                   value="<?= $formData['quantity_completed'] ?>" step="0.001" min="0.001" required>
                            <span class="input-group-text" id="unitDisplay">وحدة</span>
                        </div>
                        <small class="text-muted">الحد الأقصى: <span id="maxQuantity">-</span></small>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">حجم الفريق</label>
                        <input type="number" class="form-control" name="crew_size" 
                               value="<?= $formData['crew_size'] ?>" min="1" placeholder="عدد العمال">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">الأحوال الجوية</label>
                        <select class="form-select" name="weather_conditions">
                            <option value="">اختر الحالة الجوية</option>
                            <option value="excellent" <?= $formData['weather_conditions'] == 'excellent' ? 'selected' : '' ?>>ممتازة</option>
                            <option value="good" <?= $formData['weather_conditions'] == 'good' ? 'selected' : '' ?>>جيدة</option>
                            <option value="fair" <?= $formData['weather_conditions'] == 'fair' ? 'selected' : '' ?>>مقبولة</option>
                            <option value="poor" <?= $formData['weather_conditions'] == 'poor' ? 'selected' : '' ?>>سيئة</option>
                            <option value="bad" <?= $formData['weather_conditions'] == 'bad' ? 'selected' : '' ?>>سيئة جداً</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">وصف العمل المنجز <span class="text-danger">*</span></label>
                <textarea class="form-control" name="work_description" rows="3" required
                          placeholder="اكتب وصفاً تفصيلياً للعمل المنجز..."><?= htmlspecialchars($formData['work_description'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">المعدات المستخدمة</label>
                <textarea class="form-control" name="equipment_used" rows="2"
                          placeholder="اذكر المعدات والأدوات المستخدمة..."><?= htmlspecialchars($formData['equipment_used'] ?? '') ?></textarea>
            </div>
            
            <div class="mb-3">
                <label class="form-label">ملاحظات إضافية</label>
                <textarea class="form-control" name="notes" rows="2"
                          placeholder="أي ملاحظات أو تحديات واجهتها..."><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <?php if ($preselectedWorkItem): ?>
                <a href="<?= path('productivity/work-items/view.php?id=' . $preselectedWorkItem['id']) ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>
                    إلغاء
                </a>
                <?php else: ?>
                <a href="<?= path('productivity/daily-logs/index.php') ?>" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>
                    إلغاء
                </a>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i>
                    حفظ السجل
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
    // تحديث معلومات البند عند الاختيار
    $("#hiddenWorkItemSelect").on("change", function() {
        var selectedOption = $(this).find("option:selected");
        var remaining = selectedOption.data("remaining") || 0;
        var unit = selectedOption.data("unit") || "وحدة";
        
        $("#unitDisplay").text(unit);
        $("#maxQuantity").text(remaining.toLocaleString("ar-SA", {minimumFractionDigits: 3}) + " " + unit);
        
        // تحديث الحد الأقصى للكمية
        $("input[name=\'quantity_completed\']").attr("max", remaining);
    });
    
    // تحديث المعلومات عند تحميل الصفحة
    $("#hiddenWorkItemSelect").trigger("change");
    
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
