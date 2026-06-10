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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    setAlert('معرف المقاول غير صحيح', 'error');
    redirect('index.php');
}

$clientModel = new InventoryClient();
$client = $clientModel->getClientById($id);

if (!$client) {
    setAlert('المقاول غير موجود', 'error');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'contractor';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name)) {
        $errors[] = 'اسم المقاول/العميل مطلوب';
    }

    if (empty($errors)) {
        $result = $clientModel->updateClient($id, [
            'name' => $name,
            'type' => $type,
            'phone' => $phone,
            'email' => $email
        ]);

        if ($result['success']) {
            setAlert('تم تحديث بيانات المقاول بنجاح', 'success');
            redirect('index.php');
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'تعديل مقاول';
$currentPage = 'inventory_clients';

ob_start();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-edit text-primary me-2"></i> تعديل بيانات المقاول</h2>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">الاسم <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($client['name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">النوع</label>
                        <select name="type" class="form-select">
                            <option value="contractor" <?= $client['type'] === 'contractor' ? 'selected' : '' ?>>مقاول</option>
                            <option value="company" <?= $client['type'] === 'company' ? 'selected' : '' ?>>شركة</option>
                            <option value="other" <?= $client['type'] === 'other' ? 'selected' : '' ?>>أخرى</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم الجوال</label>
                        <input type="text" name="phone" class="form-control" dir="ltr" value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control" dir="ltr" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> حفظ التعديلات
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/layout.php';
?>
