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

<div class="container-fluid py-4">
    <!-- رأس الصفحة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="icon-circle bg-primary text-white shadow-sm me-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                <i class="fas fa-box-open"></i>
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-1">تفاصيل المادة</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 m-0 p-0 bg-transparent">
                        <li class="breadcrumb-item"><a href="index.php" class="text-muted text-decoration-none">المواد</a></li>
                        <li class="breadcrumb-item active text-primary fw-bold" aria-current="page"><?= htmlspecialchars($material['item_number']) ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="btn-group gap-2" role="group">
            <a href="index.php" class="btn btn-white rounded-pill px-4 fw-bold text-muted border-0 shadow-sm hover-elevate">
                <i class="fas fa-arrow-right me-2"></i>العودة للقائمة
            </a>
            <?php if (hasPermission('inventory_materials_edit')): ?>
                <a href="edit.php?id=<?= $material['id'] ?>" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm hover-elevate text-dark">
                    <i class="fas fa-edit me-2"></i>تعديل المادة
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- معلومات المادة الأساسية -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header bg-white border-0 p-4 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.03) !important;">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-primary-soft me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-info-circle text-primary"></i>
                        </div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;">المعلومات الأساسية</h6>
                    </div>
                    <?php if ($material['is_active']): ?>
                        <span class="badge bg-success-soft text-success rounded-pill px-3 py-2"><i class="fas fa-circle me-1" style="font-size: 8px;"></i>نشط</span>
                    <?php else: ?>
                        <span class="badge bg-secondary-soft text-secondary rounded-pill px-3 py-2"><i class="fas fa-circle me-1" style="font-size: 8px;"></i>غير نشط</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <!-- الوصف (Full Width) -->
                    <div class="p-4 bg-light rounded-4 mb-4 border border-white" style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                        <span class="text-muted small fw-bold d-block mb-2"><i class="fas fa-align-right me-2"></i>الوصف</span>
                        <h5 class="text-dark fw-bold mb-0 lh-base"><?= htmlspecialchars($material['description'] ?? '') ?></h5>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 h-100 border border-white" style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                <span class="text-muted small fw-bold d-block mb-2">رقم البند</span>
                                <span class="badge bg-white text-primary shadow-sm rounded-pill px-3 py-2 fs-6 border border-primary border-opacity-10 font-monospace"><?= htmlspecialchars($material['item_number']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 h-100 border border-white" style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                <span class="text-muted small fw-bold d-block mb-2">رقم المجموعة</span>
                                <?php if ($material['group_number']): ?>
                                    <span class="badge bg-white text-dark shadow-sm rounded-pill px-3 py-2 fs-6 font-monospace border border-dark border-opacity-10"><?= htmlspecialchars($material['group_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">غير محدد</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 h-100 border border-white" style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                <span class="text-muted small fw-bold d-block mb-2">الوحدة القياسية</span>
                                <div class="d-flex align-items-center mt-2">
                                    <div class="icon-circle bg-white text-secondary shadow-sm me-3" style="width: 32px; height: 32px;"><i class="fas fa-balance-scale"></i></div>
                                    <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($material['unit'] ?? '') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 h-100 border border-white" style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                                <span class="text-muted small fw-bold d-block mb-2">تاريخ الإنشاء / التحديث</span>
                                <div class="small text-dark fw-bold mb-2 mt-2"><i class="far fa-calendar-alt text-muted me-2"></i><?= formatDate($material['created_at']) ?></div>
                                <div class="small text-muted"><i class="far fa-clock text-muted me-2"></i><?= formatDate($material['updated_at']) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($material['notes']) && $material['notes']): ?>
                        <div class="p-4 rounded-4" style="background: rgba(13, 110, 253, 0.03); border: 1px dashed rgba(13, 110, 253, 0.2);">
                            <h6 class="text-primary fw-bold mb-2"><i class="fas fa-sticky-note me-2"></i>ملاحظات إضافية:</h6>
                            <div class="text-muted small lh-lg">
                                <?= nl2br(htmlspecialchars($material['notes'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- المعاملات الأخيرة -->
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-white border-0 p-4 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.03) !important;">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-info-soft me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-history text-info"></i>
                        </div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;">المعاملات الأخيرة للمادة</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentTransactions)): ?>
                        <div class="text-center py-5">
                            <div class="icon-circle bg-secondary-soft mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <i class="fas fa-receipt text-secondary"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">لا توجد معاملات</h6>
                            <p class="text-muted mb-0 small">لم يتم إجراء أي حركة على هذه المادة حتى الآن.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 border-0">
                                <thead style="background-color: #f8fafc;">
                                    <tr>
                                        <th class="border-0 fw-bold text-muted ps-4 py-3" style="font-size: 0.85rem;">رقم المعاملة</th>
                                        <th class="border-0 fw-bold text-muted py-3" style="font-size: 0.85rem;">النوع</th>
                                        <th class="border-0 fw-bold text-muted py-3" style="font-size: 0.85rem;">التاريخ</th>
                                        <th class="border-0 fw-bold text-muted py-3" style="font-size: 0.85rem;">الكمية</th>
                                        <th class="border-0 fw-bold text-muted pe-4 py-3" style="font-size: 0.85rem;">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.03);">
                                            <td class="ps-4 py-3">
                                                <a href="../transactions/view.php?id=<?= $transaction['id'] ?>"
                                                    class="font-monospace text-decoration-none text-primary fw-bold bg-primary-soft px-3 py-1 rounded-pill">
                                                    <?= htmlspecialchars($transaction['transaction_number']) ?>
                                                </a>
                                            </td>
                                            <td class="py-3">
                                                <?php
                                                $typeLabels = [
                                                    'incoming' => ['وارد', 'success'],
                                                    'outgoing' => ['صادر', 'danger'],
                                                    'transfer' => ['تحويل', 'info'],
                                                    'return' => ['مرتجع', 'warning'],
                                                    'initial_balance' => ['رصيد افتتاحي', 'primary'],
                                                    'loan_out' => ['سلفة صادرة', 'dark'],
                                                    'loan_in' => ['سلفة واردة', 'secondary'],
                                                    'loan_return' => ['إرجاع سلفة', 'dark'],
                                                    'stocktake_adjustment' => ['تسوية جرد', 'dark']
                                                ];
                                                $typeInfo = $typeLabels[$transaction['transaction_type']] ?? ['غير معروف', 'secondary'];
                                                ?>
                                                <span class="badge bg-<?= $typeInfo[1] ?>-soft text-<?= $typeInfo[1] ?> rounded-pill px-3 py-2"><i class="fas fa-circle me-1" style="font-size: 6px;"></i><?= $typeInfo[0] ?></span>
                                            </td>
                                            <td class="py-3"><span class="text-muted small fw-bold"><i class="far fa-clock me-2"></i><?= formatDateTime($transaction['created_at']) ?></span></td>
                                            <td class="py-3"><span class="fw-bold fs-6 text-dark"><?= formatNumber($transaction['quantity'], 3) ?></span></td>
                                            <td class="pe-4 py-3">
                                                <?php
                                                $statusLabels = [
                                                    'pending' => ['معلق', 'warning'],
                                                    'approved' => ['معتمد', 'success'],
                                                    'rejected' => ['مرفوض', 'danger']
                                                ];
                                                $statusInfo = $statusLabels[$transaction['status']] ?? ['غير معروف', 'secondary'];
                                                ?>
                                                <span class="badge bg-white shadow-sm border border-<?= $statusInfo[1] ?> border-opacity-25 text-<?= $statusInfo[1] ?> rounded-pill px-3 py-2"><?= $statusInfo[0] ?></span>
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
        <div class="col-lg-4">
            <!-- حالة المخزون -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; overflow: hidden; background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);">
                <div class="card-body p-4 p-xl-5 text-center">
                    <div class="icon-circle mx-auto mb-4 shadow-sm <?= $isOutOfStock ? 'bg-danger text-white' : ($isLowStock ? 'bg-warning text-white' : 'bg-success text-white') ?>" style="width: 72px; height: 72px; font-size: 1.75rem;">
                        <i class="fas <?= $isOutOfStock ? 'fa-times' : ($isLowStock ? 'fa-exclamation' : 'fa-check') ?>"></i>
                    </div>
                    
                    <h5 class="fw-bold text-dark mb-1">حالة المخزون</h5>
                    <?php if ($isOutOfStock): ?>
                        <p class="text-danger fw-bold small mb-4">نفد المخزون بالكامل!</p>
                    <?php elseif ($isLowStock): ?>
                        <p class="text-warning fw-bold small mb-4">المخزون منخفض ويحتاج لتعويض</p>
                    <?php else: ?>
                        <p class="text-success fw-bold small mb-4">المخزون في مستويات جيدة</p>
                    <?php endif; ?>

                    <div class="display-4 fw-bold mb-2 <?= $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') ?>" style="letter-spacing: -1px;">
                        <?= formatNumber($material['current_stock'], 3) ?>
                    </div>
                    <div class="text-muted small fw-bold mb-4 border-bottom pb-4">الكمية الحالية المتوفرة (<?= htmlspecialchars($material['unit'] ?? '') ?>)</div>

                    <!-- إحصائيات سريعة -->
                    <div class="row text-center g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 bg-white rounded-4 shadow-sm border border-light h-100">
                                <div class="fs-4 fw-bold text-dark mb-1">
                                    <?= formatNumber($material['minimum_stock'], 0) ?>
                                </div>
                                <span class="text-muted small fw-bold d-block">الحد الأدنى</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white rounded-4 shadow-sm border border-light h-100">
                                <div class="fs-4 fw-bold text-dark mb-1">
                                    <?= $material['maximum_stock'] > 0 ? formatNumber($material['maximum_stock'], 0) : '∞' ?>
                                </div>
                                <span class="text-muted small fw-bold d-block">الحد الأقصى</span>
                            </div>
                        </div>
                    </div>

                    <!-- شريط التقدم -->
                    <div class="text-start">
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
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted fw-bold">نسبة الامتلاء</small>
                            <small class="fw-bold <?= $progressClass === 'bg-success' ? 'text-success' : ($progressClass === 'bg-warning' ? 'text-warning' : 'text-danger') ?>"><?= round($percentage) ?>%</small>
                        </div>
                        <div class="progress rounded-pill bg-light shadow-inner" style="height: 12px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
                            <div class="progress-bar <?= $progressClass ?> rounded-pill" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الباركود -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-white border-0 p-4 d-flex align-items-center" style="border-bottom: 1px solid rgba(0,0,0,0.03) !important;">
                    <div class="icon-circle bg-secondary-soft me-3" style="width: 40px; height: 40px;">
                        <i class="fas fa-barcode text-secondary"></i>
                    </div>
                    <h6 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;">باركود المادة</h6>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="p-4 bg-light rounded-4 mb-4 d-inline-block shadow-sm w-100 overflow-hidden border border-white" style="box-shadow: inset 0 2px 4px rgba(0,0,0,0.02) !important;">
                        <svg id="barcodeDisplay" style="max-width: 100%; height: auto;"></svg>
                    </div>
                    <button class="btn btn-outline-primary rounded-pill px-4 fw-bold w-100 py-2 hover-elevate border-2" onclick="printBarcode()">
                        <i class="fas fa-print me-2"></i>طباعة الملصق
                    </button>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <?php if (hasPermission('inventory_materials_edit')): ?>
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-header bg-white border-0 p-4 d-flex align-items-center" style="border-bottom: 1px solid rgba(0,0,0,0.03) !important;">
                        <div class="icon-circle bg-success-soft me-3" style="width: 40px; height: 40px;">
                            <i class="fas fa-bolt text-success"></i>
                        </div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 1.1rem;">إجراءات سريعة</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-3">
                            <a href="../transactions/create.php?type=incoming&material_id=<?= $material['id'] ?>"
                                class="btn btn-success rounded-pill fw-bold shadow-sm py-3 hover-elevate d-flex justify-content-between align-items-center px-4">
                                <span><i class="fas fa-plus-circle me-2"></i>إضافة وارد جديد</span>
                                <i class="fas fa-chevron-left opacity-50"></i>
                            </a>
                            <a href="../transactions/create.php?type=outgoing&material_id=<?= $material['id'] ?>"
                                class="btn btn-danger rounded-pill fw-bold shadow-sm py-3 hover-elevate d-flex justify-content-between align-items-center px-4">
                                <span><i class="fas fa-minus-circle me-2"></i>إضافة صادر جديد</span>
                                <i class="fas fa-chevron-left opacity-50"></i>
                            </a>
                            
                            <hr class="text-muted my-2 opacity-25">
                            
                            <?php if ($material['is_active']): ?>
                                <button type="button" class="btn btn-light text-danger rounded-pill fw-bold py-2 hover-elevate border-0"
                                    onclick="deactivateMaterial(<?= $material['id'] ?>)">
                                    <i class="fas fa-ban me-2"></i>إلغاء التفعيل
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-light text-success rounded-pill fw-bold py-2 hover-elevate border-0"
                                    onclick="activateMaterial(<?= $material['id'] ?>)">
                                    <i class="fas fa-check-circle me-2"></i>إعادة التفعيل
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
                body{margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;font-family:Tajawal,sans-serif}
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