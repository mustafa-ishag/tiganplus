<?php
/**
 * صفحة عرض تفاصيل شهادة الإنجاز
 * View Completion Certificate Details Page
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_certificates_view_details')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$certificateId = (int)($_GET['id'] ?? 0);

if (!$certificateId) {
    header('Location: index.php');
    exit();
}

$error = '';

try {
    $db = getDB();
    
    // جلب بيانات الشهادة
    $certificateStmt = $db->prepare("
        SELECT
            cc.*,
            wo.work_order_number,
            wo.department,
            wo.location as work_order_location,
            wo.assignment_date,
            wo.estimated_value as work_order_estimated_value,
            wo.actual_value as work_order_actual_value,
            wo.notes as work_order_notes,
            wot.type_code,
            b.name as branch_name,
            b.code as branch_code,
            ce.name as current_entity_name,
            u_created.username as created_by_name,
            u_updated.username as updated_by_name
        FROM completion_certificates cc
        JOIN work_orders wo ON cc.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
        LEFT JOIN users u_created ON cc.created_by = u_created.id
        LEFT JOIN users u_updated ON cc.updated_by = u_updated.id
        WHERE cc.id = ?
    ");
    
    $certificateStmt->execute([$certificateId]);
    $certificate = $certificateStmt->fetch();
    
    if (!$certificate) {
        throw new Exception('الشهادة غير موجودة');
    }
    
    // جلب مواد الشهادة
    $materialsStmt = $db->prepare("
        SELECT * FROM completion_certificate_materials 
        WHERE certificate_id = ? 
        ORDER BY material_code
    ");
    $materialsStmt->execute([$certificateId]);
    $materials = $materialsStmt->fetchAll();
    
    // جلب أعمال الشهادة
    $worksStmt = $db->prepare("
        SELECT * FROM completion_certificate_works
        WHERE certificate_id = ?
        ORDER BY work_item_code
    ");
    $worksStmt->execute([$certificateId]);
    $works = $worksStmt->fetchAll();

    // حساب إجمالي الأعمال (اختياري، تم إزالة الأسعار)
    $totalWorkValue = 0;
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

$pageTitle = 'عرض شهادة الإنجاز - ' . ($certificate['title'] ?? 'غير محدد');
$currentPage = 'completion-certificates';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'شهادات الإنجاز', 'url' => 'inventory/completion-certificates/index.php'],
    ['title' => 'عرض الشهادة', 'url' => 'inventory/completion-certificates/view.php?id=' . $certificateId]
];

// بدء تخزين المحتوى
ob_start();
?>

<?php if ($error): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?= htmlspecialchars($error) ?>
    <div class="mt-2">
        <a href="index.php" class="btn btn-sm btn-outline-primary">العودة للقائمة</a>
    </div>
</div>
<?php else: ?>

<!-- أزرار الإجراءات -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>
            العودة للقائمة
        </a>
    </div>
    <div class="btn-group">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>
            طباعة
        </button>
        <?php if (hasPermission('inventory_certificates_edit') && $certificate['status'] === 'in_progress'): ?>
        <a href="edit.php?id=<?= $certificate['id'] ?>" class="btn btn-warning">
            <i class="fas fa-edit me-2"></i>
            تحرير
        </a>
        <?php endif; ?>
        <?php if (hasPermission('inventory_certificates_status_update')): ?>
        <button type="button" class="btn btn-<?= $certificate['status'] === 'completed' ? 'warning' : 'success' ?>"
                onclick="updateStatus('<?= $certificate['status'] === 'completed' ? 'in_progress' : 'completed' ?>')">
            <i class="fas fa-<?= $certificate['status'] === 'completed' ? 'undo' : 'check' ?> me-2"></i>
            <?= $certificate['status'] === 'completed' ? 'إعادة لجاري الإعداد' : 'تحديد كمكتمل' ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- معلومات الشهادة الأساسية -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-certificate me-2"></i>
            معلومات شهادة الإنجاز
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold" style="width: 150px;">رقم أمر العمل:</td>
                        <td>
                            <?= htmlspecialchars($certificate['work_order_number']) ?>
                            <?php if ($certificate['type_code']): ?>
                                <span class="badge bg-primary ms-2"><?= htmlspecialchars($certificate['type_code']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">الموقع:</td>
                        <td>
                            <?php if (!empty($certificate['work_order_location'])): ?>
                                <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                <?= htmlspecialchars($certificate['work_order_location']) ?>
                            <?php else: ?>
                                <span class="text-muted">غير محدد</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">تاريخ الشهادة:</td>
                        <td><?= date('Y-m-d', strtotime($certificate['certificate_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">الحالة:</td>
                        <td>
                            <span class="badge bg-<?= $certificate['status'] === 'completed' ? 'success' : 'warning' ?> fs-6">
                                <i class="fas fa-<?= $certificate['status'] === 'completed' ? 'check-circle' : 'clock' ?> me-1"></i>
                                <?= $certificate['status'] === 'completed' ? 'مكتمل' : 'جاري الإعداد' ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold" style="width: 150px;">الفرع:</td>
                        <td><?= htmlspecialchars($certificate['branch_name'] ?? 'غير محدد') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">الجهة الحالية:</td>
                        <td><?= htmlspecialchars($certificate['current_entity_name'] ?? 'غير محدد') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">القسم:</td>
                        <td>
                            <span class="badge bg-<?= $certificate['department'] === 'connections' ? 'info' : 'warning' ?>">
                                <?= $certificate['department'] === 'connections' ? 'التوصيلات' : 'المشاريع' ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">المنشئ:</td>
                        <td><?= htmlspecialchars($certificate['created_by_name']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if ($certificate['description']): ?>
        <div class="mt-3">
            <h6 class="fw-bold">وصف الأعمال المنجزة:</h6>
            <p class="text-muted"><?= nl2br(htmlspecialchars($certificate['description'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- المواد المستخدمة -->
<?php if (!empty($materials)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-boxes me-2"></i>
            المواد المستخدمة (<?= count($materials) ?> مادة)
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-primary">
                    <tr>
                        <th>رقم البند</th>
                        <th>رمز المجموعة</th>
                        <th>وصف المادة</th>
                        <th>الوحدة</th>
                        <th>المقايسة</th>
                        <th>الطبيعة</th>
                        <th>صرف <small class="text-muted">(محسوب)</small></th>
                        <th>إرجاع <small class="text-muted">(محسوب)</small></th>

                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $material): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($material['material_code']) ?></strong></td>
                        <td><?= htmlspecialchars($material['material_group'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($material['material_description']) ?></td>
                        <td><?= htmlspecialchars($material['unit'] ?? '') ?></td>
                        <td><?= number_format($material['estimated_quantity'], 3) ?></td>
                        <td><?= number_format($material['actual_quantity'], 3) ?></td>
                        <td><?= number_format($material['dispensed_quantity'], 3) ?></td>
                        <td><?= number_format($material['returned_quantity'], 3) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="7" class="text-end">إجمالي المواد:</th>
                        <th class="text-success"><?= count($materials) ?> مادة</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- الأعمال المنجزة -->
<?php if (!empty($works)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-tools me-2"></i>
            الأعمال المنجزة (<?= count($works) ?> عمل)
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-success">
                    <tr>
                        <th>رقم البند</th>
                        <th>وصف العمل</th>
                        <th>الوحدة</th>
                        <th>المقايسة</th>
                        <th>الكمية</th>
                        <th>نسبة الإنجاز</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($works as $work):
                        $quantity = $work['quantity'] ?? 0;
                        $estimatedQuantity = $work['estimated_quantity'] ?? 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($work['work_item_code']) ?></strong></td>
                        <td><?= htmlspecialchars($work['work_description']) ?></td>
                        <td><?= htmlspecialchars($work['unit']) ?></td>
                        <td><?= number_format($estimatedQuantity, 3) ?></td>
                        <td><?= number_format($work['quantity'], 3) ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar"
                                     style="width: <?= $work['completion_percentage'] ?>%"
                                     aria-valuenow="<?= $work['completion_percentage'] ?>"
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?= number_format($work['completion_percentage'], 1) ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end">إجمالي الأعمال:</th>
                        <th class="text-center">
                            <span class="text-muted"><?= count($works) ?> عمل</span>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ملخص الكميات -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-chart-bar me-2"></i>
            ملخص الكميات
        </h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-4 mb-3">
                <div class="border rounded p-4 bg-light">
                    <i class="fas fa-boxes fa-2x text-info mb-2"></i>
                    <h6 class="text-muted">عدد المواد</h6>
                    <h4 class="text-info mb-0"><?= count($materials) ?> مادة</h4>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="border rounded p-4 bg-light">
                    <i class="fas fa-tools fa-2x text-success mb-2"></i>
                    <h6 class="text-muted">عدد الأعمال</h6>
                    <h4 class="text-success mb-0"><?= count($works) ?> عمل</h4>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <!-- إحصائية إضافية فارغة حاليا -->
            </div>
        </div>
    </div>
</div>

<!-- معلومات إضافية -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-info-circle me-2"></i>
            معلومات إضافية
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold">تواريخ مهمة:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-calendar-plus text-primary me-2"></i> تاريخ الإنشاء: <?= date('Y-m-d H:i', strtotime($certificate['created_at'])) ?></li>
                    <?php if ($certificate['updated_at'] !== $certificate['created_at']): ?>
                    <li><i class="fas fa-calendar-edit text-warning me-2"></i> آخر تحديث: <?= date('Y-m-d H:i', strtotime($certificate['updated_at'])) ?></li>
                    <?php endif; ?>
                    <?php if ($certificate['updated_by_name']): ?>
                    <li><i class="fas fa-user-edit text-info me-2"></i> آخر محدث: <?= htmlspecialchars($certificate['updated_by_name']) ?></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold">إحصائيات:</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-boxes text-info me-2"></i> عدد المواد: <?= count($materials) ?> مادة</li>
                    <li><i class="fas fa-tools text-success me-2"></i> عدد الأعمال: <?= count($works) ?> عمل</li>
                    <li><i class="fas fa-percentage text-primary me-2"></i> متوسط نسبة الإنجاز:
                        <?php
                        $avgCompletion = 0;
                        if (!empty($works)) {
                            $totalCompletion = array_sum(array_column($works, 'completion_percentage'));
                            $avgCompletion = $totalCompletion / count($works);
                        }
                        echo number_format($avgCompletion, 1) . '%';
                        ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// تحديث حالة الشهادة
function updateStatus(newStatus) {
    const statusText = newStatus === 'completed' ? 'مكتمل' : 'جاري الإعداد';

    if (confirm(`هل أنت متأكد من تغيير حالة الشهادة إلى "${statusText}"؟`)) {
        fetch('update-status-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                certificate_id: <?= $certificate['id'] ?>,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('خطأ: ' + data.message);
            }
        })
        .catch(error => {
            alert('خطأ في الاتصال: ' + error.message);
        });
    }
}

// تحسين الطباعة
window.addEventListener('beforeprint', function() {
    // إخفاء الأزرار عند الطباعة
    document.querySelectorAll('.btn-group, .d-flex').forEach(el => {
        el.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // إظهار الأزرار بعد الطباعة
    document.querySelectorAll('.btn-group, .d-flex').forEach(el => {
        el.style.display = '';
    });
});
</script>

<!-- أنماط الطباعة -->
<style media="print">
    .btn, .btn-group, .d-flex {
        display: none !important;
    }

    .card {
        border: 1px solid #000 !important;
        break-inside: avoid;
    }

    .table {
        border: 1px solid #000 !important;
    }

    .table th, .table td {
        border: 1px solid #000 !important;
    }

    @page {
        margin: 1cm;
        size: A4;
    }

    body {
        font-size: 12px;
    }

    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #000 !important;
    }
</style>

<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
