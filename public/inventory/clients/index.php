<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryClient.php';

if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

$clientModel = new InventoryClient();
$clients = $clientModel->getAllClients();

$pageTitle = 'قائمة المقاولين والعملاء';
$currentPage = 'inventory_clients';

ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-users text-primary me-2"></i> المقاولين والعملاء</h2>
            <p class="text-muted mb-0">إدارة المقاولين والجهات الخارجية للسلف</p>
        </div>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> إضافة مقاول جديد
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الاسم</th>
                            <th>النوع</th>
                            <th>رقم الجوال</th>
                            <th>البريد الإلكتروني</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">لا يوجد مقاولين حالياً</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= $client['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($client['name']) ?></td>
                                    <td>
                                        <?php if ($client['type'] === 'contractor'): ?>
                                            <span class="badge bg-primary">مقاول</span>
                                        <?php elseif ($client['type'] === 'company'): ?>
                                            <span class="badge bg-success">شركة</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">أخرى</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($client['phone'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($client['email'] ?? '-') ?></td>
                                    <td><?= date('Y-m-d', strtotime($client['created_at'])) ?></td>
                                    <td>
                                        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> تعديل
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
