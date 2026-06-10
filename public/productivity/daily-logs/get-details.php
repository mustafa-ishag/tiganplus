<?php
/**
 * عرض تفاصيل السجل اليومي (AJAX)
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين الملفات المطلوبة
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<div class="alert alert-danger">يجب تسجيل الدخول أولاً</div>';
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_daily_logs_view')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">ليس لديك صلاحية لعرض السجلات اليومية</div>';
    exit();
}

// الحصول على معرف السجل
$logId = $_GET['id'] ?? null;
if (!$logId) {
    echo '<div class="alert alert-danger">معرف السجل مطلوب</div>';
    exit();
}

$db = getDB();

// جلب تفاصيل السجل
$sql = "
    SELECT 
        pdl.*,
        pwi.work_item_description,
        pwi.unit,
        wo.work_order_number,
        u.username as logged_by_name,
        u.full_name as logged_by_full_name
    FROM productivity_daily_logs pdl
    JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    LEFT JOIN users u ON pdl.created_by = u.id
    WHERE pdl.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$logId]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    echo '<div class="alert alert-danger">السجل غير موجود</div>';
    exit();
}

// تحديد النصوص والألوان
$statusText = [
    'draft' => 'مسودة',
    'submitted' => 'مرسل',
    'approved' => 'معتمد',
    'rejected' => 'مرفوض',
    'returned' => 'مرتجع'
];

$statusClass = [
    'draft' => 'secondary',
    'submitted' => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'returned' => 'info'
];

$weatherText = [
    'excellent' => 'ممتازة',
    'good' => 'جيدة', 
    'fair' => 'مقبولة',
    'poor' => 'سيئة',
    'bad' => 'سيئة جداً'
];

$qualityText = [
    'excellent' => 'ممتاز',
    'good' => 'جيد',
    'acceptable' => 'مقبول',
    'poor' => 'ضعيف'
];
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary mb-3">معلومات أساسية</h6>
        
        <div class="mb-3">
            <strong>أمر العمل:</strong>
            <p class="mb-1"><?= htmlspecialchars($log['work_order_number'] ?? '') ?></p>
        </div>
        
        <div class="mb-3">
            <strong>بند الإنتاجية:</strong>
            <p class="mb-1"><?= htmlspecialchars($log['work_item_description'] ?? '') ?></p>
        </div>
        
        <div class="row">
            <div class="col-6">
                <div class="mb-3">
                    <strong>التاريخ:</strong>
                    <p class="mb-1"><?= date('Y-m-d', strtotime($log['log_date'])) ?></p>
                    <small class="text-muted"><?= date('l', strtotime($log['log_date'])) ?></small>
                </div>
            </div>
            <div class="col-6">
                <div class="mb-3">
                    <strong>الحالة:</strong>
                    <p class="mb-1">
                        <span class="badge bg-<?= $statusClass[$log['status']] ?? 'secondary' ?>">
                            <?= $statusText[$log['status']] ?? $log['status'] ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <strong>الكمية المنجزة:</strong>
            <p class="mb-1">
                <span class="h5 text-success"><?= number_format($log['quantity_completed'], 3) ?></span>
                <span class="text-muted"><?= htmlspecialchars($log['unit'] ?? '') ?></span>
            </p>
        </div>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-info mb-3">تفاصيل العمل</h6>
        
        <div class="row">
            <div class="col-6">
                <div class="mb-3">
                    <strong>ساعات العمل:</strong>
                    <p class="mb-1">
                        <?php if ($log['work_hours']): ?>
                            <span class="badge bg-info"><?= number_format($log['work_hours'], 1) ?> ساعة</span>
                        <?php else: ?>
                            <span class="text-muted">غير محدد</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-6">
                <div class="mb-3">
                    <strong>عدد العمال:</strong>
                    <p class="mb-1">
                        <?php if ($log['workers_count']): ?>
                            <span class="badge bg-secondary"><?= $log['workers_count'] ?> عامل</span>
                        <?php else: ?>
                            <span class="text-muted">غير محدد</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-6">
                <div class="mb-3">
                    <strong>الأحوال الجوية:</strong>
                    <p class="mb-1">
                        <?php if ($log['weather_condition']): ?>
                            <span class="badge bg-primary">
                                <?= $weatherText[$log['weather_condition']] ?? $log['weather_condition'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">غير محدد</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-6">
                <div class="mb-3">
                    <strong>جودة العمل:</strong>
                    <p class="mb-1">
                        <?php if ($log['work_quality']): ?>
                            <span class="badge bg-success">
                                <?= $qualityText[$log['work_quality']] ?? $log['work_quality'] ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">غير محدد</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <strong>المسجل بواسطة:</strong>
            <p class="mb-1">
                <?= htmlspecialchars($log['logged_by_full_name'] ?? $log['logged_by_name'] ?? 'غير محدد') ?>
                <br>
                <small class="text-muted">
                    <?= date('Y-m-d H:i', strtotime($log['created_at'])) ?>
                </small>
            </p>
        </div>
    </div>
</div>

<?php if ($log['equipment_used']): ?>
<div class="mb-3">
    <h6 class="text-warning mb-2">المعدات المستخدمة</h6>
    <div class="bg-light p-3 rounded">
        <?= nl2br(htmlspecialchars($log['equipment_used'] ?? '')) ?>
    </div>
</div>
<?php endif; ?>

<?php if ($log['obstacles']): ?>
<div class="mb-3">
    <h6 class="text-danger mb-2">العوائق والتحديات</h6>
    <div class="bg-light p-3 rounded">
        <?= nl2br(htmlspecialchars($log['obstacles'] ?? '')) ?>
    </div>
</div>
<?php endif; ?>

<?php if ($log['notes']): ?>
<div class="mb-3">
    <h6 class="text-secondary mb-2">ملاحظات إضافية</h6>
    <div class="bg-light p-3 rounded">
        <?= nl2br(htmlspecialchars($log['notes'] ?? '')) ?>
    </div>
</div>
<?php endif; ?>

<?php if ($log['location_coordinates']): ?>
<div class="mb-3">
    <h6 class="text-info mb-2">الموقع الجغرافي</h6>
    <div class="bg-light p-3 rounded">
        <i class="fas fa-map-marker-alt me-2"></i>
        <?= htmlspecialchars($log['location_coordinates'] ?? '') ?>
    </div>
</div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
                تم الإنشاء: <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
            </small>
            <?php if ($log['updated_at'] && $log['updated_at'] != $log['created_at']): ?>
            <small class="text-muted">
                آخر تحديث: <?= date('Y-m-d H:i:s', strtotime($log['updated_at'])) ?>
            </small>
            <?php endif; ?>
        </div>
    </div>
</div>
