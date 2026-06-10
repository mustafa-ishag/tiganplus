<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة إضافة معاملة مخزون جديدة
 * Create New Inventory Transaction Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryTransaction.php';
require_once __DIR__ . '/../../../models/Material.php';

require_once __DIR__ . '/../../../models/WorkOrder.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_transactions_create')) {
    setAlert('ليس لديك صلاحية لإضافة معاملات المخزون', 'error');
    redirect('index.php');
}

$transactionModel = new InventoryTransaction();
$materialModel = new Material();

$workOrderModel = new WorkOrder();

// ===== AJAX: إنشاء مادة من الكتالوج =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'create_from_catalog') {
    header('Content-Type: application/json');
    $catalogId = (int) ($_POST['catalog_id'] ?? 0);
    if ($catalogId <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف الكتالوج غير صحيح']);
        exit;
    }
    $db = getDB();
    $catalogItem = $db->prepare("SELECT * FROM material_catalog WHERE id = ?");
    $catalogItem->execute([$catalogId]);
    $catalogItem = $catalogItem->fetch(PDO::FETCH_ASSOC);
    if (!$catalogItem) {
        echo json_encode(['success' => false, 'message' => 'المادة غير موجودة في الكتالوج']);
        exit;
    }
    // التحقق من عدم وجود المادة بالفعل
    $existing = $materialModel->findOneWhere('item_number = ?', [$catalogItem['item_number']]);
    if ($existing) {
        echo json_encode(['success' => true, 'material_id' => $existing['id'], 'item_number' => $existing['item_number'], 'description' => $existing['description'], 'unit' => $existing['unit'], 'current_stock' => $existing['current_stock']]);
        exit;
    }
    // إنشاء المادة الجديدة
    $newMaterialData = [
        'item_number' => $catalogItem['item_number'],
        'current_stock' => 0,
        'minimum_stock' => 0,
        'maximum_stock' => 0,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $result = $materialModel->createMaterial($newMaterialData);
    if ($result['success']) {
        echo json_encode(['success' => true, 'material_id' => $result['material_id'], 'item_number' => $catalogItem['item_number'], 'description' => $catalogItem['description'], 'unit' => $catalogItem['unit'] ?? 'قطعة', 'current_stock' => 0]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'فشل في إنشاء المادة']);
    }
    exit;
}

// تحديد نوع المعاملة
$transactionType = $_GET['type'] ?? 'incoming';
$allowedTypes = ['incoming', 'outgoing', 'transfer', 'return'];

if (!in_array($transactionType, $allowedTypes)) {
    $transactionType = 'incoming';
}

// الحصول على المواد النشطة (مع بيانات الكتالوج)
$materials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description, mc.unit, m.current_stock 
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE m.is_active = 1 
     ORDER BY m.item_number"
);

// للمعاملات الواردة: جلب مواد الكتالوج غير الموجودة في المستودع
$catalogItems = [];
if ($transactionType === 'incoming') {
    $db = getDB();
    $catalogStmt = $db->query("
        SELECT mc.id as catalog_id, mc.item_number, mc.description, mc.unit, mc.unit_price, mc.group_number,
               0 as current_stock, 'catalog' as source
        FROM material_catalog mc
        WHERE mc.item_number NOT IN (
            SELECT COALESCE(item_number,'') FROM materials WHERE item_number IS NOT NULL
        )
        ORDER BY mc.item_number
    ");
    $catalogItems = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);
}


// الحصول على أوامر العمل المتاحة (للصادر والمرتجع)
$workOrders = [];
if (in_array($transactionType, ['outgoing', 'return'])) {
    $workOrders = $workOrderModel->getWorkOrdersForMaterialRequest($_SESSION['user_branch_id'] ?? null);
}

$errors = [];
$formData = [
    'transaction_type' => $transactionType,
    'transaction_date' => date('Y-m-d'),

    'work_order_id' => '',
    'notes' => '',
    'materials' => []
];

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'transaction_type' => $_POST['transaction_type'] ?? $transactionType,
        'transaction_date' => $_POST['transaction_date'] ?? '',

        'work_order_id' => $_POST['work_order_id'] ?? '',
        'notes' => trim($_POST['notes'] ?? ''),
        'materials' => $_POST['materials'] ?? []
    ];

    // التحقق من صحة البيانات
    if (empty($formData['transaction_date'])) {
        $errors['transaction_date'] = 'تاريخ المعاملة مطلوب';
    }



    if (empty($formData['materials'])) {
        $errors['materials'] = 'يجب إضافة مادة واحدة على الأقل';
    } else {
        // التحقق من صحة بيانات المواد
        foreach ($formData['materials'] as $index => $material) {
            if (empty($material['material_id'])) {
                $errorMsg = 'يجب اختيار المادة في السطر ' . ($index + 1);
                $errors["materials_{$index}_material_id"] = $errorMsg;
                if (!isset($errors['general'])) {
                    $errors['general'] = "يوجد خطأ في بيانات المواد:\n - " . $errorMsg;
                } else {
                    $errors['general'] .= "\n - " . $errorMsg;
                }
            }

            if (empty($material['quantity']) || $material['quantity'] <= 0) {
                $errorMsg = 'الكمية يجب أن تكون أكبر من صفر في السطر ' . ($index + 1);
                $errors["materials_{$index}_quantity"] = $errorMsg;
                if (!isset($errors['general'])) {
                    $errors['general'] = "يوجد خطأ في كميات المواد:\n - " . $errorMsg;
                } else {
                    $errors['general'] .= "\n - " . $errorMsg;
                }
            }



            // للمعاملات الصادرة، التحقق من توفر المخزون
            if ($formData['transaction_type'] === 'outgoing' && !empty($material['material_id']) && !empty($material['quantity'])) {
                $materialData = $materialModel->findById($material['material_id']);
                if ($materialData && $materialData['current_stock'] < $material['quantity']) {
                    $errorMsg = "الكمية المطلوبة ({$material['quantity']}) أكبر من المخزون المتاح ({$materialData['current_stock']}) للمادة ({$materialData['item_number']})";
                    $errors["materials_{$index}_quantity"] = $errorMsg;
                    if (!isset($errors['general'])) {
                        $errors['general'] = "يوجد خطأ في كميات المواد:\n - " . $errorMsg;
                    } else {
                        $errors['general'] .= "\n - " . $errorMsg;
                    }
                }
            }
        }
    }

    // إذا لم توجد أخطاء، قم بإنشاء المعاملة
    if (empty($errors)) {
        $transactionData = [
            'transaction_type' => $formData['transaction_type'],
            'transaction_date' => $formData['transaction_date'],

            'branch_id' => $_SESSION['branch_id'] ?? 1,
            'notes' => $formData['notes'],
            'created_by' => $_SESSION['user_id']
        ];

        // إضافة أمر العمل للمعاملات الصادرة والمرتجعات
        if (in_array($formData['transaction_type'], ['outgoing', 'return']) && !empty($formData['work_order_id'])) {
            $transactionData['work_order_id'] = $formData['work_order_id'];
        }

        $result = $transactionModel->createTransaction($transactionData, $formData['materials']);

        if ($result['success']) {
            setAlert('تم إنشاء المعاملة بنجاح', 'success');
            redirect('view.php?id=' . $result['transaction_id']);
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

// تحديد عناوين الصفحة حسب نوع المعاملة
$typeLabels = [
    'incoming' => ['إضافة معاملة وارد', 'استلام مواد جديدة إلى المخزون', 'success', 'arrow-down', '#198754'],
    'outgoing' => ['إضافة معاملة صادر', 'صرف مواد من المخزون', 'danger', 'arrow-up', '#dc3545'],
    'transfer' => ['إضافة معاملة تحويل', 'تحويل مواد بين المواقع', 'info', 'exchange-alt', '#0dcaf0'],
    'return' => ['إضافة معاملة مرتجع', 'إرجاع مواد إلى المخزون', 'warning', 'undo', '#ffc107']
];

$typeInfo = $typeLabels[$transactionType];
$pageTitle = $typeInfo[0];
$currentPage = 'inventory';

// بدء تخزين المحتوى
ob_start();
?>

<style>
    /* ===== Page Header ===== */
    .page-header {
        background: linear-gradient(135deg,
                <?= $typeInfo[4] ?>
                15 0%,
                <?= $typeInfo[4] ?>
                08 100%);
        border: 1px solid
            <?= $typeInfo[4] ?>
            25;
        border-radius: 12px;
        padding: 1.5rem 2rem;
        margin-bottom: 1.75rem;
    }

    .page-header .header-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background:
            <?= $typeInfo[4] ?>
        ;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .page-header h2 {
        font-size: 1.35rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-color, #333);
    }

    .page-header p {
        font-size: 0.9rem;
        color: #6c757d;
        margin: 0;
    }

    /* ===== Cards ===== */
    .form-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.5rem;
        overflow: visible;
    }

    .form-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--text-color, #333);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-card .card-header i {
        color: var(--primary-color, #2c5aa0);
        font-size: 1rem;
    }

    .form-card .card-body {
        padding: 1.5rem;
    }

    /* ===== Materials Table ===== */
    .materials-table {
        margin: 0;
        font-size: 0.9rem;
    }

    .materials-table thead {
        background: linear-gradient(135deg, #2c5aa0 0%, #1e3d72 100%) !important;
    }

    .materials-table thead th {
        color: #fff !important;
        background: transparent !important;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.85rem 0.75rem;
        border: none !important;
        white-space: nowrap;
        vertical-align: middle;
        text-align: center;
    }

    .materials-table tbody td {
        vertical-align: middle;
        padding: 0.75rem;
        border-color: #f0f0f0;
    }

    .materials-table tbody tr:hover {
        background: #f8fafc;
    }

    .materials-table tbody tr.table-warning {
        background: #fff3cd !important;
    }

    .materials-table tbody tr.table-danger {
        background: #f8d7da !important;
    }

    /* ===== Custom Dropdown Search ===== */
    .material-select-container {
        position: relative !important;
    }

    .custom-dropdown {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 1050 !important;
        background-color: white !important;
        border: 1px solid #dee2e6 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        max-height: 250px !important;
        overflow-y: auto !important;
        margin-top: 2px !important;
        display: none;
    }

    .custom-dropdown.show {
        display: block !important;
    }

    .dropdown-item-custom {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
        transition: background-color 0.2s;
    }

    .dropdown-item-custom:hover {
        background-color: #e7f3ff;
    }

    .dropdown-item-custom:last-child {
        border-bottom: none;
    }

    .dropdown-item-custom .item-number {
        font-weight: 700;
        color: #2c5aa0;
        font-size: 0.9rem;
    }

    .dropdown-item-custom .item-description {
        font-size: 0.82rem;
        color: #6c757d;
        margin-top: 2px;
    }

    .dropdown-item-custom .item-stock {
        font-size: 0.78rem;
        color: #999;
    }

    .material-search-input {
        border: 2px solid #e9ecef;
        transition: border-color 0.3s;
    }

    .material-search-input:focus {
        border-color: #2c5aa0;
        box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
    }

    .selected-item {
        background-color: #e7f3ff;
        border-color: #2c5aa0;
        color: #2c5aa0;
        font-weight: 600;
    }

    /* Material Description Display */
    .material-desc-display {
        display: none;
        margin-top: 6px;
        padding: 6px 10px;
        background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 100%);
        border-right: 3px solid #2c5aa0;
        border-radius: 0 6px 6px 0;
        font-size: 0.8rem;
        color: #555;
        line-height: 1.4;
        animation: fadeSlideIn 0.3s ease;
    }

    .material-desc-display.show {
        display: block;
    }

    .material-desc-display .desc-text {
        color: #6c757d;
        font-size: 0.8rem;
    }

    @keyframes fadeSlideIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Table overflow fix */
    .table td {
        position: relative;
        overflow: visible !important;
    }

    .table {
        overflow: visible !important;
    }

    .table-responsive {
        overflow: visible !important;
    }

    /* ===== Summary Bar ===== */
    .summary-bar {
        background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        border: 1px solid #e2e8f0;
        border-radius: 0 0 12px 12px;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .summary-bar .summary-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .summary-bar .summary-item i {
        color: var(--primary-color, #2c5aa0);
    }

    .summary-bar .summary-value {
        font-weight: 700;
        color: var(--primary-color, #2c5aa0);
        font-size: 1.05rem;
    }

    /* ===== Sidebar ===== */
    .info-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 1.25rem;
        overflow: hidden;
    }

    .info-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.85rem 1.25rem;
        font-weight: 700;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-card .card-header i {
        color: var(--primary-color, #2c5aa0);
    }

    .info-card .card-body {
        padding: 1.25rem;
    }

    .info-card .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .info-card .info-list li {
        padding: 0.4rem 0;
        font-size: 0.88rem;
        color: #555;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .info-card .info-list li i {
        color:
            <?= $typeInfo[4] ?>
        ;
        margin-top: 3px;
        width: 14px;
        flex-shrink: 0;
    }

    /* ===== Action Buttons ===== */
    .action-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.75rem;
        padding-top: 1.25rem;
        border-top: 1px solid #e2e8f0;
    }

    .action-buttons .btn {
        padding: 0.65rem 1.5rem;
        font-weight: 600;
        border-radius: 8px;
        font-size: 0.95rem;
    }

    /* ===== Empty State ===== */
    .empty-state {
        padding: 2.5rem;
        text-align: center;
        color: #adb5bd;
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
        opacity: 0.5;
    }

    .empty-state p {
        margin: 0;
        font-size: 0.9rem;
    }

    @media (max-width: 991px) {
        .page-header {
            padding: 1rem 1.25rem;
        }

        .form-card .card-body {
            padding: 1rem;
        }

        .action-buttons {
            flex-direction: column;
            gap: 0.75rem;
        }

        .action-buttons .btn {
            width: 100%;
        }
    }

    /* ===== Work Order Dropdown (Outgoing) ===== */
    .wo-select-container {
        position: relative;
    }

    .wo-select-container .wo-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        max-height: 220px;
        overflow-y: auto;
        z-index: 1050;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
        margin-top: 2px;
    }

    .wo-select-container .wo-dropdown.show {
        display: block;
    }

    .wo-dropdown-item {
        padding: 0.6rem 0.85rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background .15s;
    }

    .wo-dropdown-item:last-child {
        border-bottom: none;
    }

    .wo-dropdown-item:hover {
        background: #f0f7ff;
    }

    .wo-dropdown-item .wo-number {
        font-weight: 600;
        font-size: .9rem;
        color: #2c5aa0;
    }

    .wo-dropdown-item .wo-type {
        font-size: .78rem;
        color: #888;
        margin-top: 1px;
    }

    .wo-dropdown-item .wo-value {
        font-size: .78rem;
        color: #666;
    }

    .wo-selected {
        border-color: #2c5aa0 !important;
        background: #f0f7ff !important;
    }

    .wo-type-display {
        display: none;
        margin-top: 4px;
        font-size: .82rem;
        color: #2c5aa0;
        font-weight: 600;
    }

    .wo-type-display.show {
        display: block;
    }
</style>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="header-icon"><i class="fas fa-<?= $typeInfo[3] ?>"></i></div>
            <div>
                <h2><?= $typeInfo[0] ?></h2>
                <p><?= $typeInfo[1] ?></p>
            </div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-right me-1"></i> العودة للمعاملات
        </a>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger d-flex align-items-center mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <div><?= nl2br(htmlspecialchars($errors['general'])) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" id="transactionForm">
        <input type="hidden" name="transaction_type" value="<?= $transactionType ?>">

        <div class="row">
            <div class="col-lg-8">

                <!-- معلومات المعاملة -->
                <div class="form-card">
                    <div class="card-header"><i class="fas fa-file-alt"></i> معلومات المعاملة</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="transaction_date" class="form-label fw-semibold">
                                    <i class="fas fa-calendar-alt text-muted me-1"></i>
                                    تاريخ المعاملة <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                    class="form-control <?= isset($errors['transaction_date']) ? 'is-invalid' : '' ?>"
                                    id="transaction_date" name="transaction_date"
                                    value="<?= htmlspecialchars($formData['transaction_date']) ?>" required>
                            </div>

                            <?php if (in_array($transactionType, ['outgoing', 'return'])): ?>
                                <div class="col-md-6">
                                    <label for="wo_search_input" class="form-label fw-semibold">
                                        <i class="fas fa-clipboard-list text-muted me-1"></i>
                                        أمر العمل
                                    </label>
                                    <div class="wo-select-container">
                                        <input type="text" class="form-control" id="wo_search_input"
                                            placeholder="اكتب رقم أمر العمل للبحث..." autocomplete="off" value="<?php
                                            if (!empty($formData['work_order_id'])) {
                                                foreach ($workOrders as $wo) {
                                                    if ($wo['id'] == $formData['work_order_id']) {
                                                        echo htmlspecialchars($wo['work_order_number']);
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>">
                                        <input type="hidden" id="work_order_id" name="work_order_id"
                                            value="<?= htmlspecialchars($formData['work_order_id']) ?>">
                                        <div class="wo-dropdown" id="woDropdown"></div>
                                    </div>
                                    <div class="wo-type-display" id="woTypeDisplay"></div>
                                    <div class="form-text">اكتب رقم أمر العمل أو النوع للبحث (اختياري)</div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <label for="notes" class="form-label fw-semibold">
                                    <i class="fas fa-sticky-note text-muted me-1"></i> ملاحظات
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"
                                    placeholder="ملاحظات إضافية (اختياري)"><?= htmlspecialchars($formData['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المواد -->
                <div class="form-card" style="overflow: visible;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2"><i class="fas fa-boxes"></i> المواد</div>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addMaterialRow()"
                            style="border-radius: 6px;">
                            <i class="fas fa-plus me-1"></i> إضافة مادة
                        </button>
                    </div>

                    <?php if (isset($errors['materials'])): ?>
                        <div class="alert alert-danger m-3 mb-0"><i
                                class="fas fa-exclamation-triangle me-2"></i><?= $errors['materials'] ?></div>
                    <?php endif; ?>

                    <div class="table-responsive" style="overflow: visible !important;">
                        <table class="table materials-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">المادة</th>
                                    <th style="width: 15%;">الوحدة</th>
                                    <th style="width: 20%;">الكمية</th>
                                    <th style="width: 15%;">المخزون</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="materialsTableBody">
                                <tr id="noMaterialsRow">
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-box-open d-block"></i>
                                            <p>اضغط على <strong>«إضافة مادة»</strong> لبدء إضافة المواد</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="summary-bar">
                        <div class="summary-item">
                            <i class="fas fa-list-ol"></i> <span>عدد البنود:</span>
                            <span class="summary-value" id="total-items">0</span>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-<?= $typeInfo[2] ?>">
                        <i class="fas fa-save me-1"></i> حفظ المعاملة
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i> إلغاء
                    </a>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <div class="info-card">
                    <div class="card-header"><i class="fas fa-info-circle"></i> تعليمات</div>
                    <div class="card-body">
                        <?php if ($transactionType === 'incoming'): ?>
                            <ul class="info-list">
                                <li><i class="fas fa-check-circle"></i> سيتم إضافة الكميات إلى المخزون</li>
                                <li><i class="fas fa-check-circle"></i> يمكنك البحث عن المادة بالرقم أو الوصف</li>
                                <li><i class="fas fa-check-circle"></i> تأكد من صحة الأسعار والكميات</li>
                            </ul>
                        <?php elseif ($transactionType === 'outgoing'): ?>
                            <ul class="info-list">
                                <li><i class="fas fa-exclamation-circle"></i> سيتم خصم الكميات من المخزون</li>
                                <li><i class="fas fa-exclamation-circle"></i> تأكد من توفر الكميات المطلوبة</li>
                                <li><i class="fas fa-exclamation-circle"></i> الصفوف الحمراء = مخزون غير كافٍ</li>
                            </ul>
                        <?php elseif ($transactionType === 'transfer'): ?>
                            <ul class="info-list">
                                <li><i class="fas fa-info-circle"></i> نقل المواد بين المواقع</li>
                                <li><i class="fas fa-info-circle"></i> لا يؤثر على إجمالي المخزون</li>
                            </ul>
                        <?php else: ?>
                            <ul class="info-list">
                                <li><i class="fas fa-undo"></i> إرجاع مواد تم صرفها سابقاً</li>
                                <li><i class="fas fa-undo"></i> سيتم إضافة الكميات إلى المخزون</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header"><i class="fas fa-exchange-alt"></i> أنواع المعاملات</div>
                    <div class="card-body p-2">
                        <a href="create.php?type=incoming"
                            class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none mb-1 <?= $transactionType === 'incoming' ? 'bg-success bg-opacity-10 border border-success' : 'text-secondary' ?>">
                            <i
                                class="fas fa-arrow-down <?= $transactionType === 'incoming' ? 'text-success' : '' ?>"></i>
                            <span
                                class="<?= $transactionType === 'incoming' ? 'fw-bold text-success' : '' ?>">وارد</span>
                        </a>
                        <a href="create.php?type=outgoing"
                            class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none mb-1 <?= $transactionType === 'outgoing' ? 'bg-danger bg-opacity-10 border border-danger' : 'text-secondary' ?>">
                            <i class="fas fa-arrow-up <?= $transactionType === 'outgoing' ? 'text-danger' : '' ?>"></i>
                            <span
                                class="<?= $transactionType === 'outgoing' ? 'fw-bold text-danger' : '' ?>">صادر</span>
                        </a>
                        <a href="create.php?type=transfer"
                            class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none mb-1 <?= $transactionType === 'transfer' ? 'bg-info bg-opacity-10 border border-info' : 'text-secondary' ?>">
                            <i
                                class="fas fa-exchange-alt <?= $transactionType === 'transfer' ? 'text-info' : '' ?>"></i>
                            <span class="<?= $transactionType === 'transfer' ? 'fw-bold text-info' : '' ?>">تحويل</span>
                        </a>
                        <a href="create.php?type=return"
                            class="d-flex align-items-center gap-2 p-2 rounded text-decoration-none <?= $transactionType === 'return' ? 'bg-warning bg-opacity-10 border border-warning' : 'text-secondary' ?>">
                            <i class="fas fa-undo <?= $transactionType === 'return' ? 'text-warning' : '' ?>"></i>
                            <span
                                class="<?= $transactionType === 'return' ? 'fw-bold text-warning' : '' ?>">مرتجع</span>
                        </a>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header"><i class="fas fa-chart-pie"></i> ملخص سريع</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted small">المواد المتاحة</span>
                            <span class="badge bg-primary rounded-pill"><?= count($materials) ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">المستخدم الحالي</span>
                            <span
                                class="small fw-semibold"><?= htmlspecialchars($_SESSION['full_name'] ?? 'غير محدد') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    // بيانات المواد
    var materialsData = <?= json_encode($materials) ?>;
    var catalogData = <?= json_encode($catalogItems) ?>;
    var transactionType = '<?= $transactionType ?>';
    var isIncoming = (transactionType === 'incoming');
    var materialRowIndex = 0;
    var preselectedMaterialId = <?= (int) ($_GET['material_id'] ?? 0) ?>;


    <?php if (in_array($transactionType, ['outgoing', 'return']) && !empty($workOrders)): ?>
        // ===== بيانات أوامر العمل =====
        var workOrdersData = <?= json_encode($workOrders) ?>;

        // ===== البحث في أوامر العمل =====
        function searchWorkOrder(input) {
            var searchTerm = input.value.toLowerCase();
            var dropdown = document.getElementById('woDropdown');
            var hiddenInput = document.getElementById('work_order_id');

            if (searchTerm.length < 1) {
                dropdown.innerHTML = '';
                dropdown.classList.remove('show');
                input.classList.remove('wo-selected');
                hiddenInput.value = '';
                document.getElementById('woTypeDisplay').textContent = '';
                document.getElementById('woTypeDisplay').classList.remove('show');
                return;
            }

            var filtered = workOrdersData.filter(function (wo) {
                return wo.work_order_number.toLowerCase().indexOf(searchTerm) !== -1 ||
                    (wo.type_code && wo.type_code.toLowerCase().indexOf(searchTerm) !== -1) ||
                    (wo.work_order_type_description && wo.work_order_type_description.toLowerCase().indexOf(searchTerm) !== -1);
            }).slice(0, 10);

            if (filtered.length > 0) {
                dropdown.innerHTML = filtered.map(function (wo) {
                    var escapedNum = wo.work_order_number.replace(/'/g, "\\'");
                    return '<div class="wo-dropdown-item" onclick="selectWorkOrder(' + wo.id + ', \'' + escapedNum + '\')">' +
                        '<div class="wo-number">' + wo.work_order_number + '</div>' +
                        '<div class="wo-type">' + (wo.type_code || '') + '</div>' +
                        '<div class="wo-value">القيمة: ' + parseFloat(wo.estimated_value).toLocaleString() + ' ريال</div>' +
                        '</div>';
                }).join('');
                dropdown.classList.add('show');
            } else {
                dropdown.innerHTML = '<div class="wo-dropdown-item text-muted">لا توجد نتائج</div>';
                dropdown.classList.add('show');
            }
        }

        // ===== اختيار أمر عمل =====
        function selectWorkOrder(woId, woNumber) {
            var input = document.getElementById('wo_search_input');
            var hiddenInput = document.getElementById('work_order_id');
            var dropdown = document.getElementById('woDropdown');

            input.value = woNumber;
            input.classList.add('wo-selected');
            hiddenInput.value = woId;
            dropdown.classList.remove('show');

            var wo = workOrdersData.find(function (w) { return w.id == woId; });
            var typeDisplay = document.getElementById('woTypeDisplay');
            if (wo && wo.type_code) {
                typeDisplay.textContent = wo.type_code;
                typeDisplay.classList.add('show');
            }
        }

        // ===== تفعيل البحث عند الكتابة =====
        document.addEventListener('DOMContentLoaded', function () {
            var woInput = document.getElementById('wo_search_input');
            if (woInput) {
                woInput.addEventListener('input', function () { searchWorkOrder(this); });
            }

            // إغلاق القائمة عند النقر خارجها
            document.addEventListener('click', function (e) {
                var woContainer = document.querySelector('.wo-select-container');
                if (woContainer && !woContainer.contains(e.target)) {
                    document.getElementById('woDropdown').classList.remove('show');
                }
            });
        });
    <?php endif; ?>

    // ===== إضافة صف مادة =====
    function addMaterialRow() {
        var tbody = document.getElementById('materialsTableBody');
        var noRow = document.getElementById('noMaterialsRow');
        if (noRow) noRow.remove();

        materialRowIndex++;

        var row = document.createElement('tr');
        row.className = 'material-row';
        row.innerHTML =
            '<td>' +
            '<div class="material-select-container position-relative">' +
            '<input type="text" class="form-control form-control-sm material-search-input" ' +
            'placeholder="اكتب رقم المادة أو الوصف..." autocomplete="off" ' +
            'onkeyup="searchMaterialInRow(this, ' + materialRowIndex + ')">' +
            '<select name="materials[' + materialRowIndex + '][material_id]" class="form-select form-select-sm d-none" required>' +
            '<option value="">اختر المادة</option>' +
            materialsData.map(function (m) {
                return '<option value="' + m.id + '" data-code="' + m.item_number + '" data-desc="' + (m.description || '').replace(/"/g, '&quot;') + '" data-unit="' + m.unit + '" data-stock="' + m.current_stock + '">' + m.item_number + '</option>';
            }).join('') +
            '</select>' +
            '<div class="material-dropdown-' + materialRowIndex + ' custom-dropdown"></div>' +
            '</div>' +
            '<div class="material-desc-display"><span class="desc-text"></span></div>' +
            '</td>' +
            '<td class="text-center"><span class="unit-display text-muted">-</span></td>' +
            '<td><input type="number" class="form-control form-control-sm quantity-input" name="materials[' + materialRowIndex + '][quantity]" min="0" step="0.001" placeholder="0" required oninput="updateRowCalcs(this)"></td>' +
            '<td class="text-center"><span class="stock-display text-muted">-</span></td>' +
            '<td class="text-center">' +
            '<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMaterialRow(this)" title="حذف">' +
            '<i class="fas fa-trash-alt"></i>' +
            '</button>' +
            '</td>';

        tbody.appendChild(row);
        updateTotals();
        row.querySelector('.material-search-input').focus();
    }

    // ===== البحث في المواد داخل الصف =====
    function searchMaterialInRow(input, rowIndex) {
        var searchTerm = input.value.toLowerCase();
        var dropdownContainer = document.querySelector('.material-dropdown-' + rowIndex);

        if (searchTerm.length < 1) {
            dropdownContainer.innerHTML = '';
            dropdownContainer.classList.remove('show');
            input.classList.remove('selected-item');
            var select = input.closest('td').querySelector('select');
            if (select) select.value = '';
            var row = input.closest('.material-row');
            if (row) {
                row.querySelector('.unit-display').textContent = '-';
                row.querySelector('.stock-display').textContent = '-';
                var totalEl = row.querySelector('.item-total');
                if (totalEl) totalEl.textContent = '0.00';
                row.classList.remove('table-warning', 'table-danger');
                var descBox = row.querySelector('.material-desc-display');
                if (descBox) descBox.classList.remove('show');
            }
            updateTotals();
            return;
        }

        // البحث في مواد المستودع
        var filtered = materialsData.filter(function (m) {
            return m.item_number.toLowerCase().indexOf(searchTerm) !== -1 ||
                m.description.toLowerCase().indexOf(searchTerm) !== -1;
        }).slice(0, 10);

        // البحث في الكتالوج (للوارد فقط)
        var catalogFiltered = [];
        if (isIncoming && catalogData && catalogData.length > 0) {
            catalogFiltered = catalogData.filter(function (m) {
                return m.item_number.toLowerCase().indexOf(searchTerm) !== -1 ||
                    m.description.toLowerCase().indexOf(searchTerm) !== -1;
            }).slice(0, 10);
        }

        var html = '';

        if (filtered.length > 0) {
            html += filtered.map(function (m) {
                var escapedNum = m.item_number.replace(/'/g, "\\'");
                return '<div class="dropdown-item-custom" onclick="selectMaterialInRow(' + m.id + ', ' + rowIndex + ', \'' + escapedNum + '\')">' +
                    '<div class="item-number">' + m.item_number + '</div>' +
                    '<div class="item-description">' + m.description + '</div>' +
                    '<div class="item-stock">المخزون: ' + parseFloat(m.current_stock).toFixed(2) + ' ' + m.unit + '</div>' +
                    '</div>';
            }).join('');
        }

        if (catalogFiltered.length > 0) {
            if (filtered.length > 0) {
                html += '<div style="border-top:2px dashed #ffc107;margin:4px 8px;"></div>';
            }
            html += '<div style="padding:4px 10px;font-size:11px;color:#856404;background:#fff3cd;font-weight:bold;"><i class="fas fa-book me-1"></i>من الكتالوج (سيتم إضافته للمستودع تلقائياً)</div>';
            html += catalogFiltered.map(function (m) {
                var escapedNum = m.item_number.replace(/'/g, "\\'");
                return '<div class="dropdown-item-custom" style="border-right:3px solid #ffc107;" onclick="selectCatalogItem(' + m.catalog_id + ', ' + rowIndex + ', \'' + escapedNum + '\')">' +
                    '<div class="item-number">' + m.item_number + ' <span class="badge bg-warning text-dark" style="font-size:9px;">كتالوج</span></div>' +
                    '<div class="item-description">' + m.description + '</div>' +
                    '<div class="item-stock text-warning">جديد - غير موجود في المستودع</div>' +
                    '</div>';
            }).join('');
        }

        if (html === '') {
            html = '<div class="dropdown-item-custom text-muted">لا توجد نتائج في المستودع أو الكتالوج</div>';
        }

        dropdownContainer.innerHTML = html;
        dropdownContainer.classList.add('show');
    }

    // ===== اختيار مادة من الكتالوج (إنشاء تلقائي) =====
    function selectCatalogItem(catalogId, rowIndex, itemNumber) {
        var dropdownContainer = document.querySelector('.material-dropdown-' + rowIndex);
        dropdownContainer.innerHTML = '<div class="dropdown-item-custom text-center"><i class="fas fa-spinner fa-spin me-1"></i> جاري إضافة المادة للمستودع...</div>';

        fetch('create.php?ajax=create_from_catalog', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'catalog_id=' + catalogId
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                dropdownContainer.classList.remove('show');
                if (data.success) {
                    // إضافة المادة لقائمة المواد المحلية
                    var newMat = { id: data.material_id, item_number: data.item_number, description: data.description, unit: data.unit, current_stock: data.current_stock };
                    materialsData.push(newMat);

                    // إضافة option للـ select
                    var select = document.querySelector('select[name="materials[' + rowIndex + '][material_id]"]');
                    var opt = document.createElement('option');
                    opt.value = data.material_id;
                    opt.dataset.code = data.item_number;
                    opt.dataset.desc = data.description;
                    opt.dataset.unit = data.unit;
                    opt.dataset.stock = data.current_stock;
                    opt.textContent = data.item_number;
                    select.appendChild(opt);

                    // حذف من قائمة الكتالوج
                    catalogData = catalogData.filter(function (c) { return c.catalog_id != catalogId; });

                    // اختيار المادة
                    selectMaterialInRow(data.material_id, rowIndex, data.item_number);
                } else {
                    alert('فشل إضافة المادة: ' + (data.message || ''));
                }
            })
            .catch(function (err) {
                dropdownContainer.classList.remove('show');
                alert('حدث خطأ في الاتصال');
            });
    }


    // ===== اختيار مادة في الصف =====
    function selectMaterialInRow(materialId, rowIndex, itemNumber) {
        var inputs = document.querySelectorAll('.material-search-input');
        var searchInput = null;
        var select = document.querySelector('select[name="materials[' + rowIndex + '][material_id]"]');
        var dropdownContainer = document.querySelector('.material-dropdown-' + rowIndex);
        if (dropdownContainer) dropdownContainer.classList.remove('show');

        inputs.forEach(function (inp) {
            var attr = inp.getAttribute('onkeyup');
            if (attr && attr.indexOf(', ' + rowIndex + ')') !== -1) {
                searchInput = inp;
            }
        });

        if (searchInput && select) {
            searchInput.value = itemNumber;
            searchInput.classList.add('selected-item');
            select.value = materialId;
            dropdownContainer.classList.remove('show');

            var selectedOption = select.options[select.selectedIndex];
            if (selectedOption && selectedOption.value) {
                var row = searchInput.closest('.material-row');
                row.querySelector('.unit-display').textContent = selectedOption.dataset.unit || '-';
                var stock = parseFloat(selectedOption.dataset.stock || 0);
                row.querySelector('.stock-display').textContent = stock.toFixed(2);

                // Show description below the search input
                var descBox = row.querySelector('.material-desc-display');
                if (descBox) {
                    descBox.querySelector('.desc-text').textContent = selectedOption.dataset.desc || '';
                    descBox.classList.add('show');
                }

                checkStockRow(row);
                updateRowCalcsFromRow(row);
            }
        }
    }

    // ===== إخفاء القوائم عند النقر خارجها =====
    document.addEventListener('click', function (e) {
        document.querySelectorAll('[class*="material-dropdown-"]').forEach(function (dropdown) {
            var parent = dropdown.closest('.material-select-container');
            if (parent && !parent.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    });

    // ===== حذف صف =====
    function removeMaterialRow(button) {
        button.closest('.material-row').remove();
        updateTotals();
        var tbody = document.getElementById('materialsTableBody');
        if (tbody.querySelectorAll('.material-row').length === 0) {
            tbody.innerHTML = '<tr id="noMaterialsRow"><td colspan="5"><div class="empty-state"><i class="fas fa-box-open d-block"></i><p>اضغط على <strong>«إضافة مادة»</strong> لبدء إضافة المواد</p></div></td></tr>';
        }
    }

    // ===== حساب إجمالي الصف =====
    function updateRowCalcs(input) {
        updateRowCalcsFromRow(input.closest('.material-row'));
    }
    function updateRowCalcsFromRow(row) {
        checkStockRow(row);
        updateTotals();
    }

    // ===== التحقق من المخزون =====
    function checkStockRow(row) {
        var qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
        var stock = parseFloat(row.querySelector('.stock-display').textContent) || 0;
        row.classList.remove('table-warning', 'table-danger');
        if (transactionType === 'outgoing' && qty > 0) {
            if (stock === 0) row.classList.add('table-danger');
            else if (qty > stock) row.classList.add('table-warning');
        }
    }

    // ===== تحديث الإجماليات =====
    function updateTotals() {
        var rows = document.querySelectorAll('.material-row');
        document.getElementById('total-items').textContent = rows.length;
    }

    // ===== التحقق عند الإرسال =====
    document.getElementById('transactionForm').addEventListener('submit', function (e) {
        var rows = document.querySelectorAll('.material-row');
        if (rows.length === 0) {
            e.preventDefault();
            alert('يجب إضافة مادة واحدة على الأقل');
            return;
        }
        var valid = false;
        rows.forEach(function (row) {
            var sel = row.querySelector('select[name*="material_id"]');
            var qty = row.querySelector('.quantity-input');
            if (sel.value && parseFloat(qty.value) > 0) valid = true;
        });
        if (!valid) {
            e.preventDefault();
            alert('يجب اختيار مادة وإدخال كمية أكبر من صفر');
        }
    });

    // ===== إضافة أول صف تلقائياً =====
    document.addEventListener('DOMContentLoaded', function () {
        addMaterialRow();

        // إذا تم تمرير معرف المادة من صفحة عرض المادة، اختيارها تلقائياً
        if (preselectedMaterialId > 0) {
            var material = materialsData.find(function (m) { return m.id == preselectedMaterialId; });
            if (material) {
                selectMaterialInRow(material.id, materialRowIndex, material.item_number);
            }
        }
    });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>