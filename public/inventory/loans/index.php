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
require_once __DIR__ . '/../../../models/InventoryClient.php';

if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

$loanModel = new InventoryLoan();
$clientModel = new InventoryClient();

$clients = $clientModel->getAllClients();

$filters = [
    'type' => $_GET['type'] ?? '',
    'client_id' => $_GET['client_id'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$loans = $loanModel->getLoans($filters);

$pageTitle = 'إدارة السلف';
$currentPage = 'inventory_loans';

ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-handshake text-primary me-2"></i> إدارة السلف</h2>
            <p class="text-muted mb-0">استلاف وتسليف المواد للمقاولين والشركات</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إنشاء سلفة جديدة
        </a>
    </div>

    <!-- الفلاتر -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">النوع</label>
                    <select name="type" class="form-select">
                        <option value="">الكل</option>
                        <option value="borrow" <?= $filters['type'] === 'borrow' ? 'selected' : '' ?>>استلاف مواد</option>
                        <option value="lend" <?= $filters['type'] === 'lend' ? 'selected' : '' ?>>تسليف مواد</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">المقاول/العميل</label>
                    <select name="client_id" class="form-select">
                        <option value="">الكل</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $filters['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>نشطة</option>
                        <option value="settled" <?= $filters['status'] === 'settled' ? 'selected' : '' ?>>مخالصة</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>رقم السلفة</th>
                            <th>النوع</th>
                            <th>المقاول/العميل</th>
                            <th>المستلم</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($loans)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">لا توجد سلف مطابقة للبحث</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($loan['loan_number']) ?></td>
                                    <td>
                                        <?php if ($loan['type'] === 'borrow'): ?>
                                            <span class="badge bg-info text-dark">استلاف (إلينا)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">تسليف (منّا)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($loan['client_name']) ?></td>
                                    <td><?= htmlspecialchars($loan['receiver_name'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($loan['status'] === 'active'): ?>
                                            <span class="badge bg-primary">نشطة</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">مخالصة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('Y-m-d', strtotime($loan['created_at'])) ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $loan['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/layout.php';
?>
