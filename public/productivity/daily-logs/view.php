<?php
/**
 * عرض تفاصيل السجل اليومي للإنتاجية
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

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_daily_logs_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تفاصيل السجل اليومي';
$currentPage = 'productivity-daily-logs';

// بدء تخزين المحتوى
ob_start();

// الحصول على معرف السجل
$logId = $_GET['id'] ?? null;
if (!$logId) {
    header('Location: ' . path('productivity/daily-logs/index.php?error=missing_id'));
    exit();
}

$db = getDB();

// جلب تفاصيل السجل
$sql = "
    SELECT 
        pdl.*,
        pwi.target_quantity,
        pwi.unit_price,
        pwi.total_value as work_item_total_value,
        pwi.status as work_item_status,
        pwi.priority as work_item_priority,
        pwi.start_date as work_item_start_date,
        pwi.target_end_date as work_item_target_end_date,
        wo.work_order_number,
        wo.department,
        wo.estimated_value as work_order_value,
        b.name as branch_name,
        wot.type_code,
        wot.description as work_order_type_name,
        wi.item_number,
        wi.description as work_item_description,
        wi.unit,
        u.username as created_by_name,
        u.full_name as created_by_full_name,
        COALESCE(pwi.work_item_description, wi.description) as final_work_item_description,
        COALESCE(pwi.unit, wi.unit) as final_unit,
        (pdl.quantity_completed * pwi.unit_price) as calculated_value
    FROM productivity_daily_logs pdl
    JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
    LEFT JOIN users u ON pdl.created_by = u.id
    WHERE pdl.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$logId]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    header('Location: ' . path('productivity/daily-logs/index.php?error=log_not_found'));
    exit();
}

// جلب تاريخ الاعتمادات إن وجد
$approvalHistorySql = "
    SELECT
        pa.*,
        u.username as approver_name,
        u.full_name as approver_full_name
    FROM productivity_approvals pa
    LEFT JOIN users u ON pa.approver_id = u.id
    WHERE pa.daily_log_id = ?
    ORDER BY pa.approved_at DESC
";

$approvalStmt = $db->prepare($approvalHistorySql);
$approvalStmt->execute([$logId]);
$approvalHistory = $approvalStmt->fetchAll(PDO::FETCH_ASSOC);

// تحديد حالة الاعتماد
$statusBadges = [
    'draft' => ['class' => 'bg-secondary', 'text' => 'مسودة'],
    'submitted' => ['class' => 'bg-warning', 'text' => 'مرسل للاعتماد'],
    'approved' => ['class' => 'bg-success', 'text' => 'معتمد'],
    'rejected' => ['class' => 'bg-danger', 'text' => 'مرفوض'],
    'returned' => ['class' => 'bg-info', 'text' => 'مرجع للتعديل']
];

$currentStatus = $statusBadges[$log['status']] ?? ['class' => 'bg-secondary', 'text' => 'غير محدد'];

// تحديد حالة جودة العمل
$qualityBadges = [
    'excellent' => ['class' => 'bg-success', 'text' => 'ممتاز'],
    'good' => ['class' => 'bg-primary', 'text' => 'جيد'],
    'acceptable' => ['class' => 'bg-warning', 'text' => 'مقبول'],
    'poor' => ['class' => 'bg-danger', 'text' => 'ضعيف']
];

$qualityStatus = $qualityBadges[$log['work_quality']] ?? ['class' => 'bg-secondary', 'text' => 'غير محدد'];

// تحديد حالة الطقس
$weatherConditions = [
    'excellent' => ['class' => 'bg-success', 'text' => 'ممتاز', 'icon' => 'fas fa-sun'],
    'good' => ['class' => 'bg-primary', 'text' => 'جيد', 'icon' => 'fas fa-cloud-sun'],
    'fair' => ['class' => 'bg-warning', 'text' => 'متوسط', 'icon' => 'fas fa-cloud'],
    'poor' => ['class' => 'bg-danger', 'text' => 'سيء', 'icon' => 'fas fa-cloud-rain'],
    'bad' => ['class' => 'bg-dark', 'text' => 'سيء جداً', 'icon' => 'fas fa-cloud-showers-heavy']
];

$weatherStatus = $weatherConditions[$log['weather_condition']] ?? ['class' => 'bg-secondary', 'text' => 'غير محدد', 'icon' => 'fas fa-question'];
?>

<div class="container-fluid">
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye text-primary"></i>
            تفاصيل السجل اليومي
        </h1>
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-right"></i> العودة للقائمة
            </a>
            <?php if (hasPermission('productivity_daily_logs_edit') && in_array($log['status'], ['draft', 'returned'])): ?>
            <a href="edit.php?id=<?= $log['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- معلومات أمر العمل -->
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i>
                        معلومات أمر العمل
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>رقم أمر العمل:</strong>
                            <p class="text-primary"><?= htmlspecialchars($log['work_order_number'] ?? '') ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>نوع الأمر:</strong>
                            <p>
                                <span class="badge bg-secondary"><?= htmlspecialchars($log['type_code'] ?? 'غير محدد') ?></span>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($log['work_order_type_name'] ?? 'غير محدد') ?></small>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <strong>القسم:</strong>
                            <p><?= htmlspecialchars($log['department'] ?? '') ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>الفرع:</strong>
                            <p class="text-info"><?= htmlspecialchars($log['branch_name'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- معلومات بند العمل -->
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tasks"></i>
                        معلومات بند العمل
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>رقم البند:</strong>
                            <p><?= htmlspecialchars($log['item_number'] ?? 'غير محدد') ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>حالة السجل:</strong>
                            <p>
                                <span class="badge <?= $currentStatus['class'] ?>">
                                    <?= $currentStatus['text'] ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <strong>وصف البند:</strong>
                        <p><?= htmlspecialchars($log['final_work_item_description'] ?? '') ?></p>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>الكمية المستهدفة:</strong>
                            <p><?= number_format($log['target_quantity'] ?? 0, 3) ?> <?= htmlspecialchars($log['final_unit'] ?? '') ?></p>
                        </div>
                        <div class="col-md-4">
                            <strong>سعر الوحدة:</strong>
                            <p><?= number_format($log['unit_price'] ?? 0, 2) ?> ريال</p>
                        </div>
                        <div class="col-md-4">
                            <strong>القيمة الإجمالية للبند:</strong>
                            <p class="text-success"><?= number_format($log['work_item_total_value'] ?? 0, 2) ?> ريال</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تفاصيل السجل اليومي -->
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calendar-day"></i>
                        تفاصيل السجل اليومي
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>تاريخ التسجيل:</strong>
                            <p class="text-primary"><?= date('Y-m-d', strtotime($log['log_date'])) ?></p>
                        </div>
                        <div class="col-md-3">
                            <strong>الكمية المنجزة:</strong>
                            <p class="text-success">
                                <span class="h5"><?= number_format($log['quantity_completed'] ?? 0, 3) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($log['final_unit'] ?? '') ?></small>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <strong>القيمة المحققة:</strong>
                            <p class="text-success">
                                <span class="h5"><?= number_format($log['calculated_value'] ?? 0, 2) ?></span>
                                <small class="text-muted">ريال</small>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <strong>عدد العمال:</strong>
                            <p><?= number_format($log['workers_count'] ?? 0) ?> عامل</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>ساعات العمل:</strong>
                            <p><?= number_format($log['work_hours'] ?? 0, 2) ?> ساعة</p>
                        </div>
                        <div class="col-md-4">
                            <strong>جودة العمل:</strong>
                            <p>
                                <span class="badge <?= $qualityStatus['class'] ?>">
                                    <?= $qualityStatus['text'] ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <strong>حالة الطقس:</strong>
                            <p>
                                <span class="badge <?= $weatherStatus['class'] ?>">
                                    <i class="<?= $weatherStatus['icon'] ?>"></i>
                                    <?= $weatherStatus['text'] ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($log['equipment_used'])): ?>
                    <div class="mb-3">
                        <strong>المعدات المستخدمة:</strong>
                        <p><?= nl2br(htmlspecialchars($log['equipment_used'] ?? '')) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($log['notes'])): ?>
                    <div class="mb-3">
                        <strong>ملاحظات:</strong>
                        <p><?= nl2br(htmlspecialchars($log['notes'] ?? '')) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <strong>تم الإنشاء بواسطة:</strong>
                            <p><?= htmlspecialchars($log['created_by_full_name'] ?? $log['created_by_name'] ?? 'غير محدد') ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>تاريخ الإنشاء:</strong>
                            <p><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تاريخ الاعتمادات -->
        <?php if (!empty($approvalHistory)): ?>
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i>
                        تاريخ الاعتمادات
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>التاريخ</th>
                                    <th>الإجراء</th>
                                    <th>المعتمد</th>
                                    <th>الملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approvalHistory as $approval): ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i', strtotime($approval['approved_at'])) ?></td>
                                    <td>
                                        <?php
                                        $actionBadges = [
                                            'approved' => ['class' => 'bg-success', 'text' => 'اعتماد'],
                                            'rejected' => ['class' => 'bg-danger', 'text' => 'رفض'],
                                            'returned' => ['class' => 'bg-info', 'text' => 'إرجاع للتعديل']
                                        ];
                                        $actionStatus = $actionBadges[$approval['action']] ?? ['class' => 'bg-secondary', 'text' => $approval['action']];
                                        ?>
                                        <span class="badge <?= $actionStatus['class'] ?>">
                                            <?= $actionStatus['text'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($approval['approver_full_name'] ?? $approval['approver_name'] ?? 'غير محدد') ?></td>
                                    <td><?= htmlspecialchars($approval['comments'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
