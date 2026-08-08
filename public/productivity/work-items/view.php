<?php
/**
 * عرض تفاصيل بند الإنتاجية
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
require_once __DIR__ . '/../../../models/ProductivityDailyLog.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_work_items_view')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'تفاصيل بند الإنتاجية';
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
        wo.estimated_value as work_order_value,
        b.name as branch_name,
        wot.type_code,
        wot.description as work_order_type_name,
        wi.item_number,
        wi.description as original_description,
        wi.price as standard_price,
        wi.unit as original_unit,
        u.username as created_by_name,
        -- استخدام وصف البند من productivity_work_items أولاً، ثم من contract_work_items كبديل
        COALESCE(pwi.work_item_description, wi.description) as work_item_description,
        COALESCE(pwi.unit, wi.unit) as unit
    FROM productivity_work_items pwi
    JOIN work_orders wo ON pwi.work_order_id = wo.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
    LEFT JOIN users u ON pwi.created_by = u.id
    WHERE pwi.id = ?
";

$stmt = $db->prepare($sql);
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: ' . path('productivity/work-items/index.php?error=item_not_found'));
    exit();
}

// جلب السجلات اليومية للبند
$dailyLogsSql = "
    SELECT
        pdl.*,
        u.username as logged_by_name
    FROM productivity_daily_logs pdl
    LEFT JOIN users u ON pdl.created_by = u.id
    WHERE pdl.work_item_id = ?
    ORDER BY pdl.log_date DESC, pdl.created_at DESC
";

$dailyLogsStmt = $db->prepare($dailyLogsSql);
$dailyLogsStmt->execute([$itemId]);
$dailyLogs = $dailyLogsStmt->fetchAll(PDO::FETCH_ASSOC);

// حساب الإحصائيات
$totalApprovedQuantity = 0;
$totalPendingQuantity = 0;
$totalRejectedQuantity = 0;

foreach ($dailyLogs as $log) {
    switch ($log['status']) {
        case 'approved':
            $totalApprovedQuantity += $log['quantity_completed'];
            break;
        case 'submitted':
            $totalPendingQuantity += $log['quantity_completed'];
            break;
        case 'rejected':
            $totalRejectedQuantity += $log['quantity_completed'];
            break;
    }
}

// حساب النسب
$progressPercentage = $item['target_quantity'] > 0 ? ($totalApprovedQuantity / $item['target_quantity']) * 100 : 0;
$remainingQuantity = $item['target_quantity'] - $totalApprovedQuantity;
$completedValue = $totalApprovedQuantity * $item['unit_price'];

// ملاحظة: تم نقل تحديث الإحصائيات إلى عمليات الاعتماد بدلاً من صفحة العرض
// لأن GET requests يجب ألا تعدّل قاعدة البيانات

// تحديد لون شريط التقدم
$progressClass = $progressPercentage >= 100 ? 'success' : 
                ($progressPercentage >= 75 ? 'info' : 
                ($progressPercentage >= 50 ? 'warning' : 'danger'));

$deptText = $item['department'] == 'connections' ? 'التوصيلات' : 'المشاريع';
$statusText = [
    'active' => 'نشط',
    'completed' => 'مكتمل',
    'paused' => 'متوقف',
    'cancelled' => 'ملغي'
];

$priorityText = [
    'low' => 'منخفضة',
    'medium' => 'متوسطة',
    'high' => 'عالية',
    'urgent' => 'عاجلة'
];

$priorityClass = [
    'low' => 'secondary',
    'medium' => 'info',
    'high' => 'warning',
    'urgent' => 'danger'
];
?>

<!-- رأس الصفحة -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= path('productivity/index.php') ?>">نظام الإنتاجية</a></li>
                <li class="breadcrumb-item"><a href="<?= path('work-orders/index.php') ?>">أوامر العمل</a></li>
                <li class="breadcrumb-item"><a href="<?= path('productivity/work-items/index.php?work_order_id=' . $item['work_order_id']) ?>">بنود الإنتاجية</a></li>
                <li class="breadcrumb-item active">تفاصيل البند</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye me-2"></i>
            تفاصيل بند الإنتاجية
        </h1>
        <p class="text-muted mb-0">عرض تفاصيل وإحصائيات بند الإنتاجية</p>
    </div>
    <div>
        <?php if (hasPermission('productivity_work_items_edit')): ?>
        <a href="<?= path('productivity/work-items/edit.php?id=' . $item['id']) ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-2"></i>
            تعديل البند
        </a>
        <?php endif; ?>
        
        <?php if (hasPermission('productivity_daily_logs_create')): ?>
        <a href="<?= path('productivity/daily-logs/create.php?work_item_id=' . $item['id']) ?>" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>
            تسجيل إنتاجية
        </a>
        <?php endif; ?>
    </div>
</div>

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

<!-- تفاصيل البند -->
<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-info-circle me-2"></i>
                    تفاصيل البند
                </h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>رقم البند:</strong>
                        <p><?= htmlspecialchars($item['item_number'] ?? 'غير محدد') ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>الحالة:</strong>
                        <p>
                            <span class="badge bg-<?= $item['status'] == 'completed' ? 'success' : ($item['status'] == 'active' ? 'primary' : 'secondary') ?>">
                                <?= $statusText[$item['status']] ?? $item['status'] ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>وصف البند:</strong>
                    <p><?= htmlspecialchars($item['work_item_description'] ?? '') ?></p>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>الكمية المستهدفة:</strong>
                        <p><?= number_format($item['target_quantity'] ?? 0, 3) ?> <?= htmlspecialchars($item['unit'] ?? '') ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>سعر الوحدة:</strong>
                        <p><?= number_format($item['unit_price'], 2) ?> ريال</p>
                    </div>
                    <div class="col-md-4">
                        <strong>القيمة الإجمالية:</strong>
                        <p class="text-success"><?= number_format($item['total_value'], 2) ?> ريال</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>تاريخ البداية:</strong>
                        <p><?= $item['start_date'] ? date('Y-m-d', strtotime($item['start_date'])) : 'غير محدد' ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>تاريخ الانتهاء المستهدف:</strong>
                        <p><?= $item['target_end_date'] ? date('Y-m-d', strtotime($item['target_end_date'])) : 'غير محدد' ?></p>
                    </div>
                    <div class="col-md-4">
                        <strong>الأولوية:</strong>
                        <p>
                            <span class="badge bg-<?= $priorityClass[$item['priority']] ?? 'secondary' ?>">
                                <?= $priorityText[$item['priority']] ?? $item['priority'] ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <?php if ($item['notes']): ?>
                <div class="mb-3">
                    <strong>ملاحظات:</strong>
                    <p><?= nl2br(htmlspecialchars($item['notes'] ?? '')) ?></p>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <strong>تم الإنشاء بواسطة:</strong>
                        <p><?= htmlspecialchars($item['created_by_name'] ?? 'غير محدد') ?></p>
                    </div>
                    <div class="col-md-6">
                        <strong>تاريخ الإنشاء:</strong>
                        <p><?= date('Y-m-d H:i', strtotime($item['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- إحصائيات الإنجاز -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-chart-pie me-2"></i>
                    إحصائيات الإنجاز
                </h6>
            </div>
            <div class="card-body">
                <!-- نسبة الإنجاز -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span>نسبة الإنجاز</span>
                        <span class="fw-bold"><?= number_format($progressPercentage, 1) ?>%</span>
                    </div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-<?= $progressClass ?>" role="progressbar" 
                             style="width: <?= min($progressPercentage, 100) ?>%">
                            <?= number_format($progressPercentage, 1) ?>%
                        </div>
                    </div>
                </div>
                
                <!-- الكميات -->
                <div class="row text-center">
                    <div class="col-12 mb-3">
                        <div class="border rounded p-2">
                            <div class="h5 mb-0 text-success"><?= number_format($totalApprovedQuantity, 3) ?></div>
                            <small class="text-muted">منجز ومعتمد</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <div class="h6 mb-0 text-warning"><?= number_format($totalPendingQuantity, 3) ?></div>
                            <small class="text-muted">في الانتظار</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <div class="h6 mb-0 text-danger"><?= number_format($totalRejectedQuantity, 3) ?></div>
                            <small class="text-muted">مرفوض</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-2 bg-light">
                            <div class="h6 mb-0 text-info"><?= number_format($remainingQuantity, 3) ?></div>
                            <small class="text-muted">متبقي</small>
                        </div>
                    </div>
                </div>
                
                <!-- القيم المالية -->
                <hr>
                <div class="row text-center">
                    <div class="col-12 mb-2">
                        <div class="border rounded p-2">
                            <div class="h6 mb-0 text-success"><?= number_format($completedValue, 2) ?></div>
                            <small class="text-muted">قيمة المنجز (ريال)</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-2 bg-light">
                            <div class="h6 mb-0 text-primary"><?= number_format($item['total_value'] - $completedValue, 2) ?></div>
                            <small class="text-muted">قيمة المتبقي (ريال)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- السجلات اليومية للبند -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-info">
            <i class="fas fa-calendar-alt me-2"></i>
            السجلات اليومية للإنتاجية
        </h6>
        <?php if (hasPermission('productivity_daily_logs_create')): ?>
        <a href="<?= path('productivity/daily-logs/create.php?work_item_id=' . $item['id']) ?>" class="btn btn-sm btn-success">
            <i class="fas fa-plus me-2"></i>
            تسجيل إنتاجية جديدة
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($dailyLogs)): ?>
        <div class="text-center py-4">
            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">لا توجد سجلات يومية</h5>
            <p class="text-muted">لم يتم تسجيل أي إنتاجية يومية لهذا البند بعد</p>
            <?php if (hasPermission('productivity_daily_logs_create')): ?>
            <a href="<?= path('productivity/daily-logs/create.php?work_item_id=' . $item['id']) ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>
                تسجيل أول إنتاجية
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>الكمية المنجزة</th>
                        <th>ساعات العمل</th>
                        <th>عدد العمال</th>
                        <th>الأحوال الجوية</th>
                        <th>الحالة</th>
                        <th>المسجل بواسطة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dailyLogs as $log): ?>
                    <tr>
                        <td>
                            <strong><?= date('Y-m-d', strtotime($log['log_date'])) ?></strong>
                            <br>
                            <small class="text-muted"><?= date('l', strtotime($log['log_date'])) ?></small>
                        </td>
                        <td>
                            <span class="h6 text-primary"><?= number_format($log['quantity_completed'], 3) ?></span>
                            <small class="text-muted d-block"><?= htmlspecialchars($item['unit'] ?? '') ?></small>
                        </td>
                        <td>
                            <?php if ($log['work_hours']): ?>
                                <span class="badge bg-info"><?= number_format($log['work_hours'], 1) ?> ساعة</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['workers_count']): ?>
                                <span class="badge bg-secondary"><?= $log['workers_count'] ?> عامل</span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $weatherText = [
                                'excellent' => 'ممتازة',
                                'good' => 'جيدة',
                                'fair' => 'مقبولة',
                                'poor' => 'سيئة',
                                'bad' => 'سيئة جداً'
                            ];
                            $weatherClass = [
                                'excellent' => 'success',
                                'good' => 'primary',
                                'fair' => 'warning',
                                'poor' => 'danger',
                                'bad' => 'dark'
                            ];
                            ?>
                            <?php if ($log['weather_condition']): ?>
                                <span class="badge bg-<?= $weatherClass[$log['weather_condition']] ?? 'secondary' ?>">
                                    <?= $weatherText[$log['weather_condition']] ?? $log['weather_condition'] ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
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
                            ?>
                            <span class="badge bg-<?= $statusClass[$log['status']] ?? 'secondary' ?>">
                                <?= $statusText[$log['status']] ?? $log['status'] ?>
                            </span>
                        </td>
                        <td>
                            <small>
                                <?= htmlspecialchars($log['logged_by_name'] ?? 'غير محدد') ?>
                                <br>
                                <span class="text-muted"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                            </small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <?php if (hasPermission('productivity_daily_logs_view')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick="viewLogDetails(<?= $log['id'] ?>)" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php endif; ?>

                                <?php if (hasPermission('productivity_daily_logs_edit') && $log['status'] != 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning"
                                        onclick="editLog(<?= $log['id'] ?>)" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>

                                <?php if (hasPermission('productivity_daily_logs_delete') && $log['status'] == 'draft'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="deleteLog(<?= $log['id'] ?>)" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ملخص السجلات -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success"><?= count(array_filter($dailyLogs, fn($log) => $log['status'] == 'approved')) ?></h5>
                        <p class="card-text">سجلات معتمدة</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h5 class="card-title text-warning"><?= count(array_filter($dailyLogs, fn($log) => $log['status'] == 'submitted')) ?></h5>
                        <p class="card-text">في الانتظار</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h5 class="card-title text-danger"><?= count(array_filter($dailyLogs, fn($log) => $log['status'] == 'rejected')) ?></h5>
                        <p class="card-text">مرفوضة</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info"><?= count($dailyLogs) ?></h5>
                        <p class="card-text">إجمالي السجلات</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal لعرض تفاصيل السجل -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل السجل اليومي</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- سيتم تحميل المحتوى هنا -->
            </div>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// إضافة JavaScript للصفحة (سيتم تنفيذه بعد تحميل jQuery)
$additionalJS = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // التأكد من تحميل jQuery
    if (typeof $ !== "undefined") {
        initializePageFunctions();
    } else {
        // انتظار تحميل jQuery
        var checkJQuery = setInterval(function() {
            if (typeof $ !== "undefined") {
                clearInterval(checkJQuery);
                initializePageFunctions();
            }
        }, 100);
    }
});

function initializePageFunctions() {
    // عرض تفاصيل السجل
    window.viewLogDetails = function(logId) {
        $.ajax({
            url: "' . path('productivity/daily-logs/get-details.php') . '",
            method: "GET",
            data: { id: logId },
            success: function(response) {
                $("#logDetailsContent").html(response);
                $("#logDetailsModal").modal("show");
            },
            error: function() {
                alert("حدث خطأ أثناء تحميل تفاصيل السجل");
            }
        });
    };

    // تعديل السجل
    window.editLog = function(logId) {
        window.location.href = "' . path('productivity/daily-logs/edit.php') . '?id=" + logId;
    };

    // حذف السجل
    window.deleteLog = function(logId) {
        if (confirm("هل أنت متأكد من حذف هذا السجل؟")) {
            $.ajax({
                url: "' . path('productivity/daily-logs/delete.php') . '",
                method: "POST",
                data: { id: logId },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || "حدث خطأ أثناء حذف السجل");
                    }
                },
                error: function() {
                    alert("حدث خطأ أثناء حذف السجل");
                }
            });
        }
    };
}
</script>
';
$content .= $additionalJS;

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
