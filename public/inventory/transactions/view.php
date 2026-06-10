<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة عرض تفاصيل المعاملة
 * Transaction Details View Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryTransaction.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_transactions_view')) {
    setAlert('ليس لديك صلاحية لعرض معاملات المخزون', 'error');
    redirect('../../dashboard.php');
}

$transactionId = (int)($_GET['id'] ?? 0);

if ($transactionId <= 0) {
    setAlert('معرف المعاملة غير صحيح', 'error');
    redirect('index.php');
}

$transactionModel = new InventoryTransaction();

// الحصول على بيانات المعاملة مع التفاصيل
$transaction = $transactionModel->getTransactionWithDetails($transactionId);

if (!$transaction) {
    setAlert('المعاملة غير موجودة', 'error');
    redirect('index.php');
}

// حساب الإجماليات
$totalItems = count($transaction['details']);
$totalQuantity = array_sum(array_column($transaction['details'], 'quantity'));

// تحديد معلومات نوع المعاملة
$typeLabels = [
    'incoming' => ['معاملة وارد', 'استلام مواد جديدة', 'success', 'arrow-down'],
    'outgoing' => ['معاملة صادر', 'صرف مواد من المخزون', 'danger', 'arrow-up'],
    'transfer' => ['معاملة تحويل', 'تحويل مواد بين المواقع', 'info', 'exchange-alt'],
    'return' => ['معاملة مرتجع', 'إرجاع مواد إلى المخزون', 'warning', 'undo'],
    'initial_balance' => ['رصيد افتتاحي', 'تسجيل رصيد ابتدائي للمادة', 'primary', 'balance-scale'],
    'loan_out' => ['سلفة صادرة', 'تسليف مواد لجهة خارجية', 'dark', 'hand-holding'],
    'loan_in' => ['سلفة واردة', 'استلاف مواد من جهة خارجية', 'secondary', 'handshake'],
    'loan_return' => ['إرجاع سلفة', 'مخالصة سلفة وإرجاع المواد', 'info', 'undo-alt'],
    'stocktake_adjustment' => ['تسوية جرد', 'تعديل المخزون نتيجة الجرد الفعلي', 'dark', 'clipboard-check']
];

$typeInfo = $typeLabels[$transaction['transaction_type']] ?? ['معاملة غير معروفة', '', 'secondary', 'question'];

$pageTitle = 'تفاصيل المعاملة - ' . $transaction['transaction_number'];
$currentPage = 'inventory-transactions';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-<?= $typeInfo[3] ?> text-<?= $typeInfo[2] ?> me-2"></i>
                تفاصيل المعاملة
            </h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($transaction['transaction_number']) ?> - <?= $typeInfo[0] ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i>
                    العودة إلى القائمة
                </a>
                <?php if ($transaction['status'] === 'pending' && hasPermission('inventory_transactions_edit')): ?>
                    <a href="edit.php?id=<?= $transaction['id'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i>
                        تعديل
                    </a>
                <?php
 endif; ?>
                <button type="button" class="btn btn-outline-primary" onclick="printTransaction()">
                    <i class="fas fa-print me-1"></i>
                    طباعة
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- معلومات المعاملة الأساسية -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        معلومات المعاملة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-muted">رقم المعاملة:</td>
                                    <td>
                                        <span class="badge bg-primary fs-6"><?= htmlspecialchars($transaction['transaction_number']) ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">نوع المعاملة:</td>
                                    <td>
                                        <span class="badge bg-<?= $typeInfo[2] ?>">
                                            <i class="fas fa-<?= $typeInfo[3] ?> me-1"></i><?= $typeInfo[0] ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">تاريخ المعاملة:</td>
                                    <td><?= formatDate($transaction['transaction_date']) ?></td>
                                </tr>
                                <?php if (!empty($transaction['work_order_number'])): ?>
                                <tr>
                                    <td class="fw-bold text-muted">أمر العمل:</td>
                                    <td>
                                        <a href="<?= path('work-orders/view.php?id=' . $transaction['work_order_id']) ?>"
                                           class="text-decoration-none" title="عرض أمر العمل">
                                            <?php if (!empty($transaction['work_order_type_code'])): ?>
                                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($transaction['work_order_type_code']) ?></span>
                                            <?php endif; ?>
                                            <span class="badge bg-info fs-6"><?= htmlspecialchars($transaction['work_order_number']) ?></span>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($transaction['reference_number'])): ?>
                                <tr>
                                    <td class="fw-bold text-muted">المرجع:</td>
                                    <td>
                                        <?php
                                        $loanTypes = ['loan_out', 'loan_in', 'loan_return'];
                                        if (in_array($transaction['transaction_type'], $loanTypes)):
                                            // البحث عن السلفة بواسطة رقم المرجع
                                            $loanRef = $transaction['reference_number'];
                                            $loanRow = $transactionModel->fetchOne(
                                                "SELECT id FROM inventory_loans WHERE loan_number = ?",
                                                [$loanRef]
                                            );
                                            if ($loanRow):
                                        ?>
                                            <a href="../loans/view.php?id=<?= $loanRow['id'] ?>" class="text-decoration-none fw-bold">
                                                <i class="fas fa-external-link-alt me-1"></i><?= htmlspecialchars($loanRef) ?>
                                            </a>
                                        <?php else: ?>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($loanRef) ?></small>
                                        <?php endif; ?>
                                        <?php elseif (str_starts_with($transaction['reference_number'], 'REQ-')):
                                            // البحث عن طلب الصرف بواسطة رقم المرجع
                                            $reqRef = $transaction['reference_number'];
                                            $reqRow = $transactionModel->fetchOne(
                                                "SELECT id FROM material_requests WHERE request_number = ?",
                                                [$reqRef]
                                            );
                                            if ($reqRow):
                                        ?>
                                            <a href="../material-requests/view.php?id=<?= $reqRow['id'] ?>" class="text-decoration-none fw-bold">
                                                <i class="fas fa-external-link-alt me-1"></i><?= htmlspecialchars($reqRef) ?>
                                            </a>
                                        <?php else: ?>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($reqRef) ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($transaction['reference_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold text-muted">الحالة:</td>
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
                                <tr>
                                    <td class="fw-bold text-muted">المنشئ:</td>
                                    <td><?= htmlspecialchars($transaction['created_by_name'] ?? 'غير معروف') ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">تاريخ الإنشاء:</td>
                                    <td><?= formatDateTime($transaction['created_at']) ?></td>
                                </tr>
                                <?php
 if ($transaction['approved_by_name']): ?>
                                <tr>
                                    <td class="fw-bold text-muted">معتمد بواسطة:</td>
                                    <td><?= htmlspecialchars($transaction['approved_by_name']) ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">تاريخ الاعتماد:</td>
                                    <td><?= formatDateTime($transaction['approved_at']) ?></td>
                                </tr>
                                <?php
 endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- الملاحظات -->
                    <?php
 if ($transaction['notes']): ?>
                        <div class="mt-3">
                            <h6 class="fw-bold text-muted">الملاحظات:</h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($transaction['notes'])) ?></p>
                        </div>
                    <?php
 endif; ?>
                </div>
            </div>

            <!-- تفاصيل المواد -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-boxes me-1"></i>
                        تفاصيل المواد (<?= $totalItems ?> بند)
                    </h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportMaterials()">
                        <i class="fas fa-file-excel me-1"></i>
                        تصدير
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php
 if (empty($transaction['details'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">لا توجد مواد في هذه المعاملة</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم البند</th>
                                        <th>وصف المادة</th>
                                        <th>الوحدة</th>
                                        <th>الكمية</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
 foreach ($transaction['details'] as $detail): ?>
                                        <tr>
                                            <td>
                                                <a href="../materials/view.php?id=<?= $detail['material_id'] ?>" 
                                                   class="text-decoration-none fw-bold">
                                                    <?= htmlspecialchars($detail['item_number']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" 
                                                     title="<?= htmlspecialchars($detail['description']) ?>">
                                                    <?= htmlspecialchars($detail['description']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($detail['unit']) ?></td>
                                            <td>
                                                <?php
                                                $addTypes = ['incoming', 'return', 'initial_balance', 'loan_in', 'loan_return'];
                                                $isAddition = in_array($transaction['transaction_type'], $addTypes);
                                                ?>
                                                <span class="fw-bold <?= $isAddition ? 'text-success' : 'text-danger' ?>">
                                                    <?= $isAddition ? '+' : '-' ?><?= formatNumber($detail['quantity'], 3) ?>
                                                </span>
                                            </td>
                                            </td>
                                            <td>
                                                <a href="../materials/view.php?id=<?= $detail['material_id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="عرض المادة">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php
 endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="3">الإجمالي</th>
                                        <th><?= formatNumber($totalQuantity, 3) ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php
 endif; ?>
                </div>
            </div>
        </div>

        <!-- الشريط الجانبي -->
        <div class="col-lg-4">
            <!-- ملخص المعاملة -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-pie me-1"></i>
                        ملخص المعاملة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-primary mb-1"><?= $totalItems ?></h4>
                                <small class="text-muted">عدد البنود</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3">
                                <h4 class="text-success mb-1"><?= formatNumber($totalQuantity, 3) ?></h4>
                                <small class="text-muted">إجمالي الكمية</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- حالة المعاملة -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-tasks me-1"></i>
                        حالة المعاملة
                    </h6>
                </div>
                <div class="card-body">
                    <?php
 if ($transaction['status'] === 'pending'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i>
                            <strong>في انتظار الموافقة</strong><br>
                            <small>المعاملة لم يتم اعتمادها بعد</small>
                        </div>
                        
                        <?php if (hasPermission('inventory_transactions_approve')): ?>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success"
                                        onclick="approveTransaction(<?= $transaction['id'] ?>)">
                                    <i class="fas fa-check me-1"></i>
                                    اعتماد المعاملة
                                </button>
                                <button type="button" class="btn btn-danger" 
                                        onclick="rejectTransaction(<?= $transaction['id'] ?>)">
                                    <i class="fas fa-times me-1"></i>
                                    رفض المعاملة
                                </button>
                            </div>
                        <?php
 endif; elseif ($transaction['status'] === 'approved'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>معتمدة</strong><br>
                            <small>تم اعتماد المعاملة وتحديث المخزون</small>
                        </div>
                        
                    <?php elseif ($transaction['status'] === 'rejected'): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>مرفوضة</strong><br>
                            <small>تم رفض المعاملة</small>
                        </div>
                    <?php
 endif; ?>
                </div>
            </div>

            <!-- إجراءات سريعة -->
            <?php if (hasPermission('inventory_transactions_approve') || hasPermission('inventory_transactions_edit')): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-tools me-1"></i>
                            إجراءات سريعة
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php
 if ($transaction['status'] === 'pending'): ?>
                                <a href="edit.php?id=<?= $transaction['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit me-1"></i>
                                    تعديل المعاملة
                                </a>
                            <?php
 endif; ?>
                            
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="printTransaction()">
                                <i class="fas fa-print me-1"></i>
                                طباعة المعاملة
                            </button>
                            
                            <a href="create.php?type=<?= $transaction['transaction_type'] ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-copy me-1"></i>
                                إنشاء معاملة مشابهة
                            </a>
                            
                            <?php
 if ($transaction['transaction_type'] === 'outgoing' && $transaction['status'] === 'approved'): ?>
                                <a href="create.php?type=return&reference=<?= $transaction['id'] ?>" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-undo me-1"></i>
                                    إنشاء مرتجع
                                </a>
                            <?php
 endif; ?>
                        </div>
                    </div>
                </div>
            <?php
 endif; ?>
        </div>
    </div>
</div>

<script>
// اعتماد المعاملة
function approveTransaction(transactionId) {
    if (confirm('هل أنت متأكد من اعتماد هذه المعاملة؟\nسيتم تحديث المخزون وفقاً لهذه المعاملة ولا يمكن التراجع عن هذا الإجراء.')) {
        updateTransactionStatus(transactionId, 'approved');
    }
}

// رفض المعاملة
function rejectTransaction(transactionId) {
    const reason = prompt('يرجى إدخال سبب الرفض:');
    if (reason !== null && reason.trim() !== '') {
        updateTransactionStatus(transactionId, 'rejected', reason);
    }
}

function updateTransactionStatus(transactionId, status, reason = '') {
    fetch('update-status-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            transaction_id: transactionId,
            status: status,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('حدث خطأ: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال');
    });
}

// طباعة المعاملة
function printTransaction() {
    window.print();
}

// تصدير المواد
function exportMaterials() {
    const transactionId = <?= $transaction['id'] ?>;
    window.location.href = `export-materials.php?transaction_id=${transactionId}`;
}
</script>

<style>
@media print {
    .btn, .card-header .btn-group, .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #000 !important;
    }
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
