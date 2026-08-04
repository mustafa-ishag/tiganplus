<?php
/**
 * تعديل سجل إنتاجية يومي
 * Edit Daily Productivity Log
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityDailyLog.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_daily_logs_edit')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تعديل السجل اليومي';
$currentPage = 'productivity-daily-logs';

// الحصول على معرف السجل
$logId = $_GET['id'] ?? null;
if (!$logId) {
    header('Location: index.php?error=missing_id');
    exit();
}

// إنشاء كائن النموذج
$dailyLogModel = new ProductivityDailyLog();
$workItemModel = new ProductivityWorkItem();

// جلب السجل
$log = $dailyLogModel->getById($logId);
if (!$log) {
    header('Location: index.php?error=log_not_found');
    exit();
}

// التحقق من صلاحية التعديل
if (!in_array($log['status'], ['draft', 'returned', 'rejected'])) {
    header('Location: index.php?error=cannot_edit');
    exit();
}

// التحقق من ملكية السجل أو صلاحية تعديل سجلات الآخرين
if ($log['created_by'] != $_SESSION['user_id'] && !hasPermission('productivity_daily_logs_edit_all')) {
    header('Location: index.php?error=no_permission');
    exit();
}

$errors = [];
$success = false;

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'work_item_id' => $_POST['work_item_id'] ?? '',
        'log_date' => $_POST['log_date'] ?? '',
        'quantity_completed' => $_POST['quantity_completed'] ?? '',
        'work_hours' => $_POST['work_hours'] ?? '',
        'workers_count' => $_POST['workers_count'] ?? '',
        'equipment_used' => $_POST['equipment_used'] ?? '',
        'weather_condition' => $_POST['weather_condition'] ?? '',
        'work_quality' => $_POST['work_quality'] ?? '',
        'obstacles' => $_POST['obstacles'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];

    // التحقق من صحة البيانات
    if (empty($formData['work_item_id'])) {
        $errors[] = 'بند العمل مطلوب';
    }
    
    if (empty($formData['log_date'])) {
        $errors[] = 'تاريخ التسجيل مطلوب';
    }
    
    if (empty($formData['quantity_completed']) || $formData['quantity_completed'] <= 0) {
        $errors[] = 'الكمية المنجزة مطلوبة ويجب أن تكون أكبر من صفر';
    }
    
    if (empty($formData['work_hours']) || $formData['work_hours'] <= 0) {
        $errors[] = 'ساعات العمل مطلوبة ويجب أن تكون أكبر من صفر';
    }
    
    if (empty($formData['workers_count']) || $formData['workers_count'] <= 0) {
        $errors[] = 'عدد العمال مطلوب ويجب أن يكون أكبر من صفر';
    }

    // التحقق من عدم تكرار التاريخ لنفس بند العمل (إذا تم تغيير التاريخ أو بند العمل)
    if (($formData['work_item_id'] != $log['work_item_id'] || $formData['log_date'] != $log['log_date']) && 
        !$dailyLogModel->isDateAvailable($formData['work_item_id'], $formData['log_date'], $logId)) {
        $errors[] = 'يوجد سجل آخر لنفس بند العمل في هذا التاريخ';
    }

    // إذا لم توجد أخطاء، قم بالتحديث
    if (empty($errors)) {
        $result = $dailyLogModel->update($logId, $formData);
        if ($result) {
            $success = true;
            // إعادة جلب البيانات المحدثة
            $log = $dailyLogModel->getById($logId);
        } else {
            $errors[] = 'حدث خطأ أثناء تحديث السجل';
        }
    }
}

// جلب بنود الإنتاجية النشطة
$db = getDB();
$workItemsQuery = "
    SELECT pwi.id, wo.work_order_number, wi.item_number, wi.description, wi.unit, b.name as branch_name
    FROM productivity_work_items pwi
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
    JOIN branches b ON wo.branch_id = b.id
    WHERE pwi.status = 'active'
";

$workItemsParams = [];
if (!hasPermission('productivity_daily_logs_view_all_branches') && isset($_SESSION['branch_id'])) {
    $workItemsQuery .= " AND wo.branch_id = ?";
    $workItemsParams[] = $_SESSION['branch_id'];
}

$workItemsQuery .= " ORDER BY wo.work_order_number, wi.item_number";

$workItemsStmt = $db->prepare($workItemsQuery);
$workItemsStmt->execute($workItemsParams);
$workItems = $workItemsStmt->fetchAll(PDO::FETCH_ASSOC);

// بدء تخزين المحتوى
ob_start();
?>
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit text-primary"></i>
            تعديل السجل اليومي
        </h1>
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> العودة للقائمة
            </a>
            <a href="view.php?id=<?= $log['id'] ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> عرض التفاصيل
            </a>
        </div>
    </div>

    <!-- عرض الرسائل -->
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i>
        تم تحديث السجل بنجاح
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>يرجى تصحيح الأخطاء التالية:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- بطاقة النموذج -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-edit"></i>
                تعديل بيانات السجل اليومي
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="work_item_id" class="form-label">بند العمل <span class="text-danger">*</span></label>
                        <select class="form-control" id="work_item_id" name="work_item_id" required>
                            <option value="">اختر بند العمل</option>
                            <?php foreach ($workItems as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= $log['work_item_id'] == $item['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['work_order_number'] ?? '') ?> -
                                <?= htmlspecialchars($item['item_number'] ?? '') ?> -
                                <?= htmlspecialchars($item['description'] ?? '') ?>
                                (<?= htmlspecialchars($item['branch_name'] ?? '') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            يرجى اختيار بند العمل
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="log_date" class="form-label">تاريخ التسجيل <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="log_date" name="log_date" 
                               value="<?= htmlspecialchars($log['log_date'] ?? '') ?>" required>
                        <div class="invalid-feedback">
                            يرجى إدخال تاريخ التسجيل
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="quantity_completed" class="form-label">الكمية المنجزة <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" class="form-control" id="quantity_completed" 
                               name="quantity_completed" value="<?= htmlspecialchars($log['quantity_completed'] ?? '') ?>" required>
                        <div class="invalid-feedback">
                            يرجى إدخال الكمية المنجزة
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="work_hours" class="form-label">ساعات العمل <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" class="form-control" id="work_hours" 
                               name="work_hours" value="<?= htmlspecialchars($log['work_hours'] ?? '') ?>" required>
                        <div class="invalid-feedback">
                            يرجى إدخال ساعات العمل
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="workers_count" class="form-label">عدد العمال <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="workers_count" 
                               name="workers_count" value="<?= htmlspecialchars($log['workers_count'] ?? '') ?>" required>
                        <div class="invalid-feedback">
                            يرجى إدخال عدد العمال
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="weather_condition" class="form-label">حالة الطقس</label>
                        <select class="form-control" id="weather_condition" name="weather_condition">
                            <option value="">اختر حالة الطقس</option>
                            <option value="excellent" <?= $log['weather_condition'] === 'excellent' ? 'selected' : '' ?>>ممتازة</option>
                            <option value="good" <?= $log['weather_condition'] === 'good' ? 'selected' : '' ?>>جيدة</option>
                            <option value="fair" <?= $log['weather_condition'] === 'fair' ? 'selected' : '' ?>>مقبولة</option>
                            <option value="poor" <?= $log['weather_condition'] === 'poor' ? 'selected' : '' ?>>سيئة</option>
                            <option value="bad" <?= $log['weather_condition'] === 'bad' ? 'selected' : '' ?>>سيئة جداً</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="work_quality" class="form-label">جودة العمل</label>
                        <select class="form-control" id="work_quality" name="work_quality">
                            <option value="excellent" <?= $log['work_quality'] === 'excellent' ? 'selected' : '' ?>>ممتازة</option>
                            <option value="good" <?= $log['work_quality'] === 'good' ? 'selected' : '' ?>>جيدة</option>
                            <option value="acceptable" <?= $log['work_quality'] === 'acceptable' ? 'selected' : '' ?>>مقبولة</option>
                            <option value="poor" <?= $log['work_quality'] === 'poor' ? 'selected' : '' ?>>ضعيفة</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="equipment_used" class="form-label">المعدات المستخدمة</label>
                    <textarea class="form-control" id="equipment_used" name="equipment_used" rows="3"
                              placeholder="اذكر المعدات والأدوات المستخدمة في العمل"><?= htmlspecialchars($log['equipment_used'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="obstacles" class="form-label">العوائق والمشاكل</label>
                    <textarea class="form-control" id="obstacles" name="obstacles" rows="3"
                              placeholder="اذكر أي عوائق أو مشاكل واجهتها أثناء العمل"><?= htmlspecialchars($log['obstacles'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">ملاحظات إضافية</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"
                              placeholder="أي ملاحظات أو تفاصيل إضافية"><?= htmlspecialchars($log['notes'] ?? '') ?></textarea>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ التعديلات
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
