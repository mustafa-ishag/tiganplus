<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة عرض تفاصيل المادة
 * Material Details View Page
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
if (!hasPermission('inventory_materials_view')) {
    setAlert('ليس لديك صلاحية لعرض تفاصيل المواد', 'error');
    redirect('../../dashboard.php');
}

$materialId = (int) ($_GET['id'] ?? 0);

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



// حساب حالة المخزون
$isLowStock = $material['current_stock'] <= $material['minimum_stock'];
$isOutOfStock = $material['current_stock'] == 0;

// الحصول على المعاملات الأخيرة لهذه المادة
try {
    $db = getDB();
    $recentTransactions = $db->prepare("
        SELECT it.id, it.transaction_number, it.transaction_type, it.transaction_date, it.status, it.created_at,
               td.quantity
        FROM inventory_transactions it
        JOIN transaction_details td ON it.id = td.transaction_id
        WHERE td.material_id = ?
        ORDER BY it.created_at DESC
        LIMIT 10
    ");
    $recentTransactions->execute([$materialId]);
    $recentTransactions = $recentTransactions->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentTransactions = [];
}

$pageTitle = 'تفاصيل المادة - ' . $material['description'];
$currentPage = 'materials';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-box me-2"></i>
                تفاصيل المادة
            </h2>
            <p class="text-muted mb-0">عرض تفاصيل وإحصائيات المادة</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i>
                    العودة إلى القائمة
                </a>
                <?php if (hasPermission('inventory_materials_edit')): ?>
                    <a href="edit.php?id=<?= $material['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i>
                        تعديل
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- معلومات المادة الأساسية -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات المادة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-muted">رقم البند:</td>
                                    <td><span
                                            class="badge bg-primary fs-6"><?= htmlspecialchars($material['item_number']) ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">رقم المجموعة:</td>
                                    <td><span
                                            class="badge bg-secondary"><?= htmlspecialchars($material['group_number'] ?? '') ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">الوصف:</td>
                                    <td><strong><?= htmlspecialchars($material['description'] ?? '') ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">الوحدة:</td>
                                    <td><?= htmlspecialchars($material['unit'] ?? '') ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">المخزون الحالي:</td>
                                    <td>
                                        <span
                                            class="fw-bold fs-5 <?= $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') ?>">
                                            <?= formatNumber($material['current_stock'], 3) ?>
                                            <?= htmlspecialchars($material['unit'] ?? '') ?>
                                        </span>
                                        <?php if ($isLowStock): ?>
                                            <i class="fas fa-exclamation-triangle text-warning ms-1"
                                                title="مخزون منخفض"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">الحد الأدنى:</td>
                                    <td><?= formatNumber($material['minimum_stock'], 3) ?>
                                        <?= htmlspecialchars($material['unit'] ?? '') ?>
                                    </td>
                                </tr>

                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-muted">الحد الأقصى:</td>
                                    <td>
                                        <?php if ($material['maximum_stock'] > 0): ?>
                                            <?= formatNumber($material['maximum_stock'], 3) ?>
                                            <?= htmlspecialchars($material['unit'] ?? '') ?>
                                        <?php else: ?>
                                            <span class="text-muted">غير محدد</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="fw-bold text-muted">الحالة:</td>
                                    <td>
                                        <?php if ($material['is_active']): ?>
                                            <span class="badge bg-success">نشط</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">غير نشط</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">تاريخ الإنشاء:</td>
                                    <td><small class="text-muted"><?= formatDate($material['created_at']) ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">آخر تحديث:</td>
                                    <td><small class="text-muted"><?= formatDate($material['updated_at']) ?></small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (isset($material['notes']) && $material['notes']): ?>
                        <div class="mt-3">
                            <h6 class="text-muted">ملاحظات:</h6>
                            <div class="alert alert-light">
                                <?= nl2br(htmlspecialchars($material['notes'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>


                </div>
            </div>

            <!-- المعاملات الأخيرة -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        المعاملات الأخيرة
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recentTransactions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">لا توجد معاملات لهذه المادة</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>رقم المعاملة</th>
                                        <th>النوع</th>
                                        <th>التاريخ</th>
                                        <th>الكمية</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <a href="../transactions/view.php?id=<?= $transaction['id'] ?>"
                                                    class="font-monospace text-decoration-none text-primary fw-semibold"
                                                    title="عرض تفاصيل المعاملة">
                                                    <?= htmlspecialchars($transaction['transaction_number']) ?>
                                                    <i class="fas fa-external-link-alt ms-1" style="font-size:0.7rem;"></i>
                                                </a>
                                            </td>
                                            <td>
                                                <?php
                                                $typeLabels = [
                                                    'incoming' => ['وارد', 'success'],
                                                    'outgoing' => ['صادر', 'danger'],
                                                    'transfer' => ['تحويل', 'info'],
                                                    'return' => ['مرتجع', 'warning'],
                                                    'initial_balance' => ['رصيد افتتاحي', 'primary'],
                                                    'loan_out' => ['سلفة صادرة', 'dark'],
                                                    'loan_in' => ['سلفة واردة', 'secondary'],
                                                    'loan_return' => ['إرجاع سلفة', 'light'],
                                                    'stocktake_adjustment' => ['تسوية جرد', 'dark']
                                                ];
                                                $typeInfo = $typeLabels[$transaction['transaction_type']] ?? ['غير معروف', 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $typeInfo[1] ?>"><?= $typeInfo[0] ?></span>
                                            </td>
                                            <td><small><?= formatDateTime($transaction['created_at']) ?></small></td>
                                            <td><?= formatNumber($transaction['quantity'], 3) ?></td>
                                            <td>
                                                <?php
                                                $statusLabels = [
                                                    'pending' => ['معلق', 'warning'],
                                                    'approved' => ['معتمد', 'success'],
                                                    'rejected' => ['مرفوض', 'danger']
                                                ];
                                                $statusInfo = $statusLabels[$transaction['status']] ?? ['غير معروف', 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- الشريط الجانبي -->
        <div class="col-md-4">
            <!-- حالة المخزون -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        حالة المخزون
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($isOutOfStock): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>نفد المخزون!</strong><br>
                            <small>المادة غير متوفرة حالياً</small>
                        </div>
                    <?php elseif ($isLowStock): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>مخزون منخفض!</strong><br>
                            <small>المخزون أقل من الحد الأدنى المطلوب</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>المخزون جيد</strong><br>
                            <small>المخزون أعلى من الحد الأدنى</small>
                        </div>
                    <?php endif; ?>

                    <!-- مؤشر المخزون -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">المخزون الحالي:
                                <?= formatNumber($material['current_stock'], 3) ?></small>
                            <small class="text-muted">الحد الأقصى:
                                <?= $material['maximum_stock'] > 0 ? formatNumber($material['maximum_stock'], 3) : '∞' ?>
                            </small>
                        </div>
                        <?php
                        $percentage = 0;
                        if ($material['maximum_stock'] > 0) {
                            $percentage = min(100, ($material['current_stock'] / $material['maximum_stock']) * 100);
                        } else {
                            $percentage = $material['current_stock'] > $material['minimum_stock'] ? 75 : 25;
                        }

                        $progressClass = 'bg-success';
                        if ($isOutOfStock) {
                            $progressClass = 'bg-danger';
                        } elseif ($isLowStock) {
                            $progressClass = 'bg-warning';
                        }
                        ?>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar <?= $progressClass ?>" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>

                    <!-- إحصائيات سريعة -->
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="border-end">
                                <div class="fs-6 fw-bold text-primary">
                                    <?= formatNumber($material['current_stock'], 0) ?>
                                </div>
                                <small class="text-muted">الحالي</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="border-end">
                                <div class="fs-6 fw-bold text-warning">
                                    <?= formatNumber($material['minimum_stock'], 0) ?>
                                </div>
                                <small class="text-muted">الأدنى</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="fs-6 fw-bold text-success">
                                <?= $material['maximum_stock'] > 0 ? formatNumber($material['maximum_stock'], 0) : '∞' ?>
                            </div>
                            <small class="text-muted">الأقصى</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الباركود -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-barcode me-2"></i>
                        باركود المادة
                    </h6>
                </div>
                <div class="card-body text-center">
                    <svg id="barcodeDisplay"></svg>
                    <div class="mt-2">
                        <small class="text-muted d-block mb-2">رقم البند: <strong><?= htmlspecialchars($material['item_number']) ?></strong></small>
                        <button class="btn btn-outline-primary btn-sm" onclick="printBarcode()">
                            <i class="fas fa-print me-1"></i> طباعة الملصق
                        </button>
                    </div>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <?php if (hasPermission('inventory_materials_edit')): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-tools me-2"></i>
                            إجراءات سريعة
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../transactions/create.php?type=incoming&material_id=<?= $material['id'] ?>"
                                class="btn btn-success btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                إضافة وارد
                            </a>
                            <a href="../transactions/create.php?type=outgoing&material_id=<?= $material['id'] ?>"
                                class="btn btn-danger btn-sm">
                                <i class="fas fa-minus me-1"></i>
                                إضافة صادر
                            </a>
                            <?php if ($material['is_active']): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                    onclick="deactivateMaterial(<?= $material['id'] ?>)">
                                    <i class="fas fa-ban me-1"></i>
                                    إلغاء تفعيل
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-success btn-sm"
                                    onclick="activateMaterial(<?= $material['id'] ?>)">
                                    <i class="fas fa-check me-1"></i>
                                    تفعيل
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function deactivateMaterial(materialId) {
        if (!confirm('هل أنت متأكد من إلغاء تفعيل هذه المادة؟')) return;
        toggleMaterialStatus(materialId, 0);
    }

    function activateMaterial(materialId) {
        if (!confirm('هل أنت متأكد من تفعيل هذه المادة؟')) return;
        toggleMaterialStatus(materialId, 1);
    }

    function toggleMaterialStatus(materialId, isActive) {
        const formData = new FormData();
        formData.append('material_id', materialId);
        formData.append('is_active', isActive);

        fetch('toggle-status-ajax.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // إظهار رسالة نجاح ثم تحديث الصفحة
                    alert(data.message);
                    location.reload();
                } else {
                    alert('خطأ: ' + data.message);
                }
            })
            .catch(err => {
                alert('حدث خطأ في الاتصال: ' + err.message);
            });
    }
</script>

<!-- JsBarcode Library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    // توليد الباركود
    try {
        JsBarcode("#barcodeDisplay", "<?= htmlspecialchars($material['item_number']) ?>", {
            format: "CODE128",
            width: 2,
            height: 60,
            displayValue: true,
            fontSize: 14,
            font: "monospace",
            margin: 10
        });
    } catch(e) { console.error('Barcode error:', e); }

    // طباعة الباركود
    function printBarcode() {
        const svg = document.getElementById('barcodeDisplay');
        const win = window.open('', '_blank', 'width=400,height=300');
        win.document.write(`
            <html><head><title>طباعة باركود</title>
            <style>
                body{margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;font-family:Cairo,sans-serif}
                .label{text-align:center;padding:10px;border:1px dashed #ccc;width:300px}
                .label .desc{font-size:10px;color:#666;margin-top:4px;word-break:break-all}
                @media print{.no-print{display:none}.label{border:none}}
            </style></head><body>
            <div class="label">
                ${svg.outerHTML}
                <div class="desc"><?= htmlspecialchars(mb_substr($material['description'],0,60)) ?></div>
                <button class="no-print" onclick="window.print()" style="margin-top:10px;padding:5px 20px;cursor:pointer">طباعة</button>
            </div>
            </body></html>
        `);
        win.document.close();
    }
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../../public/includes/layout.php';
?>