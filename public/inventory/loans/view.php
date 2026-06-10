<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryLoan.php';

if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    setAlert('معرف السلفة غير صحيح', 'error');
    redirect('index.php');
}

$loanModel = new InventoryLoan();
$loan = $loanModel->getLoanDetails($id);

if (!$loan) {
    setAlert('السلفة غير موجودة', 'error');
    redirect('index.php');
}

$pageTitle = 'تفاصيل السلفة #' . $loan['loan_number'];
$currentPage = 'inventory_loans';

ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-file-invoice text-primary me-2"></i> تفاصيل السلفة</h2>
        </div>
    <div>
            <a href="export-loan-pdf.php?id=<?= $loan['id'] ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-file-pdf me-1"></i> طباعة PDF
            </a>
            <?php if ($loan['status'] === 'settled' && $loan['type'] === 'borrow'): ?>
                <a href="export-loan-settlement-pdf.php?id=<?= $loan['id'] ?>" class="btn btn-outline-success me-2">
                    <i class="fas fa-file-signature me-1"></i> طباعة مخالصة PDF
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <!-- بطاقة التفاصيل الأساسية -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle text-primary me-2"></i> معلومات السلفة</h6>
                    <div>
                        <?php if ($loan['status'] === 'active'): ?>
                            <span class="badge bg-primary px-3 py-2 rounded-pill">نشطة</span>
                        <?php else: ?>
                            <span class="badge bg-success px-3 py-2 rounded-pill">مخالصة</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-4">
                        <div class="col-sm-6">
                            <div class="text-muted small mb-1">رقم السلفة</div>
                            <div class="fw-bold fs-5 text-dark"><?= htmlspecialchars($loan['loan_number']) ?></div>
                        </div>
                        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
                            <div class="text-muted small mb-1">تاريخ الإنشاء</div>
                            <div class="fw-bold text-dark"><?= date('Y-m-d H:i', strtotime($loan['created_at'])) ?></div>
                        </div>
                    </div>

                    <div class="row g-4 border-top pt-3">
                        <div class="col-sm-6 col-md-3">
                            <div class="text-muted small mb-1">نوع السلفة</div>
                            <div class="fw-bold">
                                <?php if ($loan['type'] === 'borrow'): ?>
                                    <span class="text-info"><i class="fas fa-arrow-down me-1"></i> استلاف مواد</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="fas fa-arrow-up me-1"></i> تسليف مواد</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="text-muted small mb-1">المقاول/العميل</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($loan['client_name']) ?></div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="text-muted small mb-1">المستلم</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($loan['receiver_name']) ?></div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="text-muted small mb-1">رقم هوية المستلم</div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($loan['receiver_identity'] ?: '-') ?></div>
                        </div>
                    </div>

                    <?php if (!empty($loan['notes'])): ?>
                        <div class="mt-4 p-3 bg-light rounded border border-light">
                            <div class="text-muted small mb-1">ملاحظات:</div>
                            <div><?= nl2br(htmlspecialchars($loan['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- جدول البنود -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-boxes text-primary me-2"></i> بنود السلفة</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>رقم البند</th>
                                    <th>الوصف</th>
                                    <th class="text-center">الكمية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loan['items'] as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($item['item_number']) ?></td>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td class="text-center fw-bold fs-6">
                                            <?= number_format($item['quantity'], 2) ?>
                                            <span class="text-muted small fw-normal ms-1"><?= htmlspecialchars($item['unit'] ?? '') ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-print-none">
            <!-- بطاقة الإجراءات -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-cogs text-secondary me-2"></i> الإجراءات</h6>
                </div>
                <div class="card-body">
                    <?php if ($loan['status'] === 'active'): ?>
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fas fa-info-circle me-1"></i> السلفة قيد التنفيذ حالياً.
                        </div>
                        <button class="btn btn-success w-100" onclick="updateStatus('settled')">
                            <i class="fas fa-check-circle me-1"></i> مخالصة العملية
                        </button>
                    <?php else: ?>
                        <div class="alert alert-success py-2 mb-3 text-center">
                            <i class="fas fa-check-circle fs-4 d-block mb-2"></i>
                            <strong>تمت المخالصة</strong>
                            <div class="small mt-1">تاريخ المخالصة: <?= date('Y-m-d H:i', strtotime($loan['settled_at'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus(status) {
    if (!confirm('هل أنت متأكد من إجراء المخالصة لهذه السلفة؟')) return;

    fetch('update-status-ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            loan_id: <?= $loan['id'] ?>,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم تحديث حالة السلفة بنجاح');
            window.location.reload();
        } else {
            alert('حدث خطأ: ' + (data.message || 'غير معروف'));
        }
    })
    .catch(err => {
        alert('حدث خطأ في الاتصال');
    });
}

</script>

<style>
@media print {
    body { background-color: #fff; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background-color: transparent !important; border-bottom: 2px solid #000 !important; }
    .badge { border: 1px solid #000; color: #000 !important; background-color: transparent !important; }
}
</style>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/layout.php';
?>
