<?php
/**
 * صفحة تحرير شهادة إنجاز
 * Edit Completion Certificate Page
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
if (!hasPermission('inventory_certificates_edit')) {
    header('Location: ' . path('inventory/completion-certificates/index.php'));
    exit();
}

// التحقق من معرف الشهادة
$certificateId = (int)($_GET['id'] ?? 0);
if (!$certificateId) {
    header('Location: ' . path('inventory/completion-certificates/index.php'));
    exit();
}

$db = getDB();

// جلب بيانات الشهادة
$certificateStmt = $db->prepare("
    SELECT cc.*, wo.work_order_number, wo.branch_id, wo.location as work_order_location,
           ce.name as current_entity_name, b.name as branch_name, wot.type_code as work_order_type_code
    FROM completion_certificates cc
    LEFT JOIN work_orders wo ON cc.work_order_id = wo.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
    WHERE cc.id = ? AND cc.status = 'in_progress'
");
$certificateStmt->execute([$certificateId]);
$certificate = $certificateStmt->fetch();

if (!$certificate) {
    setAlert('شهادة الإنجاز غير موجودة أو لا يمكن تحريرها', 'error');
    header('Location: ' . path('inventory/completion-certificates/index.php'));
    exit();
}

// جلب المواد المرتبطة بالشهادة
$materialsStmt = $db->prepare("
    SELECT * FROM completion_certificate_materials 
    WHERE certificate_id = ? 
    ORDER BY id
");
$materialsStmt->execute([$certificateId]);
$certificateMaterials = $materialsStmt->fetchAll();

// جلب الأعمال المرتبطة بالشهادة
$worksStmt = $db->prepare("
    SELECT * FROM completion_certificate_works 
    WHERE certificate_id = ? 
    ORDER BY id
");
$worksStmt->execute([$certificateId]);
$certificateWorks = $worksStmt->fetchAll();

$pageTitle = 'تحرير شهادة الإنجاز - أمر العمل رقم ' . $certificate['work_order_number'];
$currentPage = 'completion-certificates';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'إدارة المخزون', 'url' => 'inventory/index.php'],
    ['title' => 'شهادات الإنجاز', 'url' => 'inventory/completion-certificates/index.php'],
    ['title' => 'تحرير شهادة الإنجاز', 'url' => '']
];

$error = '';
$success = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // تحديث البيانات الأساسية
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $certificateDate = $_POST['certificate_date'] ?? '';
        $location = trim($_POST['location'] ?? '') ?: null;

        if (!$title || !$certificateDate) {
            throw new Exception('يرجى ملء جميع الحقول المطلوبة');
        }

        // تحديث الشهادة
        $updateStmt = $db->prepare("
            UPDATE completion_certificates
            SET title = ?, description = ?, certificate_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$title, $description, $certificateDate, $certificateId]);

        // تحديث موقع أمر العمل المرتبط
        $updateLocationStmt = $db->prepare("
            UPDATE work_orders
            SET location = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateLocationStmt->execute([$location, $certificate['work_order_id']]);
        
        // حذف المواد والأعمال الحالية
        $db->prepare("DELETE FROM completion_certificate_materials WHERE certificate_id = ?")->execute([$certificateId]);
        $db->prepare("DELETE FROM completion_certificate_works WHERE certificate_id = ?")->execute([$certificateId]);
        
        // إضافة المواد الجديدة
        if (isset($_POST['materials']) && is_array($_POST['materials'])) {
            $materialStmt = $db->prepare("
                INSERT INTO completion_certificate_materials (
                    certificate_id, material_id, material_code, material_description,
                    material_group, unit, estimated_quantity, actual_quantity,
                    dispensed_quantity, returned_quantity, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['materials'] as $materialData) {
                if (!empty($materialData['material_id'])) {
                    // جلب معلومات المادة
                    $materialInfoStmt = $db->prepare("SELECT * FROM materials WHERE id = ?");
                    $materialInfoStmt->execute([$materialData['material_id']]);
                    $materialInfo = $materialInfoStmt->fetch();

                    if ($materialInfo) {
                        $estimatedQty = (float)($materialData['estimated_quantity'] ?? 0);
                        $actualQty = (float)($materialData['actual_quantity'] ?? 0);

                        // حساب الصرف والإرجاع
                        $dispensedQty = 0;
                        $returnedQty = 0;
                        if ($actualQty > $estimatedQty) {
                            $dispensedQty = $actualQty - $estimatedQty;
                        } elseif ($estimatedQty > $actualQty) {
                            $returnedQty = $estimatedQty - $actualQty;
                        }

                        $materialStmt->execute([
                            $certificateId,
                            $materialData['material_id'],
                            $materialInfo['item_number'],
                            $materialInfo['description'],
                            $materialInfo['group_number'] ?? '',
                            $materialInfo['unit'],
                            $estimatedQty,
                            $actualQty,
                            $dispensedQty,
                            $returnedQty,
                            trim($materialData['notes'] ?? '')
                        ]);
                    }
                }
            }
        }
        
        // إضافة الأعمال الجديدة
        if (isset($_POST['works']) && is_array($_POST['works'])) {
            $workStmt = $db->prepare("
                INSERT INTO completion_certificate_works (
                    certificate_id, work_item_id, work_item_code, work_description,
                    work_category, unit, estimated_quantity, quantity, unit_price, total_value, completion_percentage, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['works'] as $workData) {
                if (!empty($workData['work_item_id'])) {
                    // جلب معلومات بند العمل
                    $workInfoStmt = $db->prepare("SELECT * FROM work_items WHERE id = ?");
                    $workInfoStmt->execute([$workData['work_item_id']]);
                    $workInfo = $workInfoStmt->fetch();

                    if ($workInfo) {
                        $quantity = (float)($workData['quantity'] ?? 0);
                        $unitPrice = (float)($workData['unit_price'] ?? 0);
                        $totalValue = $quantity * $unitPrice;

                        $workStmt->execute([
                            $certificateId,
                            $workData['work_item_id'],
                            $workInfo['item_number'],
                            $workInfo['description'],
                            $workInfo['category'] ?? 'كهربائي',
                            $workInfo['unit'],
                            (float)($workData['estimated_quantity'] ?? $quantity), // المقايسة
                            $quantity,
                            $unitPrice,
                            $totalValue,
                            (float)($workData['completion_percentage'] ?? 100),
                            trim($workData['notes'] ?? '')
                        ]);
                    }
                }
            }
        }
        
        // لا حاجة لتحديث الإجماليات المالية
        
        $db->commit();
        
        setAlert('تم تحديث شهادة الإنجاز بنجاح', 'success');
        header('Location: ' . path('inventory/completion-certificates/view.php?id=' . $certificateId));
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
    }
}

// جلب قوائم البيانات المطلوبة
$materialsStmt = $db->prepare("SELECT m.id, m.item_number, mc.description, mc.unit, mc.group_number FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.is_active = 1 ORDER BY m.item_number");
$materialsStmt->execute();
$materials = $materialsStmt->fetchAll();

$workItemsStmt = $db->prepare("SELECT id, item_number, description, unit, standard_price FROM work_items WHERE is_active = 1 ORDER BY item_number");
$workItemsStmt->execute();
$workItems = $workItemsStmt->fetchAll();

// تحديد المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h4 mb-1">
                <i class="fas fa-edit me-2"></i>
                تحرير شهادة الإنجاز
            </h2>
            <p class="text-muted mb-0">
                تحرير شهادة الإنجاز - أمر العمل رقم: <?= htmlspecialchars($certificate['work_order_number']) ?>
                <?php if ($certificate['work_order_type_code']): ?>
                    <span class="badge bg-primary ms-2"><?= htmlspecialchars($certificate['work_order_type_code']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= path('inventory/completion-certificates/index.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                العودة للقائمة
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="editCertificateForm">
        <!-- معلومات أمر العمل -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    معلومات أمر العمل
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">رقم أمر العمل</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($certificate['work_order_number']) ?>" readonly>
                            <?php if ($certificate['work_order_type_code']): ?>
                                <span class="input-group-text bg-primary text-white">
                                    <?= htmlspecialchars($certificate['work_order_type_code']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="work_order_id" value="<?= $certificate['work_order_id'] ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الفرع</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($certificate['branch_name']) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">الجهة الحالية</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($certificate['current_entity_name'] ?? 'غير محدد') ?>" readonly>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <label class="form-label">الموقع</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-map-marker-alt text-primary"></i>
                            </span>
                            <input type="text" class="form-control" name="location" id="location"
                                   value="<?= htmlspecialchars($certificate['work_order_location'] ?? '') ?>"
                                   placeholder="أدخل موقع تنفيذ أمر العمل"
                                   maxlength="255">
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            تعديل الموقع هنا سيؤثر على أمر العمل المرتبط بهذه الشهادة
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- البيانات الأساسية -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    البيانات الأساسية
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="title" class="form-label">الموقع <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" class="form-control"
                               value="<?= htmlspecialchars($certificate['title']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="certificate_date" class="form-label">تاريخ الشهادة <span class="text-danger">*</span></label>
                        <input type="date" name="certificate_date" id="certificate_date" class="form-control"
                               value="<?= $certificate['certificate_date'] ?>" required>
                    </div>
                    <div class="col-12 mb-3">
                        <label for="description" class="form-label">وصف الأعمال المنجزة</label>
                        <textarea name="description" id="description" class="form-control" rows="3"
                                  placeholder="وصف تفصيلي للأعمال التي تم إنجازها"><?= htmlspecialchars($certificate['description']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- قسم المواد -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">
                        <i class="fas fa-boxes me-2"></i>
                        المواد المستخدمة
                    </h5>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        ملاحظة: عمودي "الصرف" و "الإرجاع" يتم حسابهما تلقائياً بناءً على الفرق بين المقايسة والطبيعة
                    </small>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="updateMaterialsFromWorkOrder()" id="updateMaterialsBtn" title="تحديث المواد من طلبات الصرف">
                        <i class="fas fa-sync-alt me-1"></i>
                        تحديث من طلبات الصرف
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addMaterialRow()">
                        <i class="fas fa-plus me-1"></i>
                        إضافة مادة
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" onclick="testSearchDropdown()">
                        <i class="fas fa-search me-1"></i>اختبار البحث
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 materials-table">
                        <thead class="table-primary">
                            <tr>
                                <th style="width: 120px;">رقم البند</th>
                                <th style="width: 100px;">رمز المجموعة</th>
                                <th style="width: 200px;">وصف المادة</th>
                                <th style="width: 80px;">المقايسة</th>
                                <th style="width: 80px;">الطبيعة</th>
                                <th style="width: 80px;">صرف <small class="text-muted">(تلقائي)</small></th>
                                <th style="width: 80px;">إرجاع <small class="text-muted">(تلقائي)</small></th>

                                <th style="width: 80px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="materialsTableBody">
                            <tr id="noMaterialsRow">
                                <td colspan="8" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    اضغط على "إضافة مادة" لبدء إضافة المواد المستخدمة.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- قسم الأعمال -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tools me-2"></i>
                    الأعمال المنجزة
                </h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="autoGenerateWorkItems()" id="autoGenerateBtn" title="توليد تلقائي من المواد المستخدمة">
                        <i class="fas fa-magic me-1"></i>
                        توليد تلقائي من المواد المستخدمة
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="addWorkRow()">
                        <i class="fas fa-plus me-1"></i>
                        إضافة عمل يدوي
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 works-table">
                        <thead class="table-success">
                            <tr>
                                <th style="width: 120px;">رقم البند</th>
                                <th style="width: 250px;">وصف العمل</th>
                                <th style="width: 80px;">الوحدة</th>
                                <th style="width: 80px;">المقايسة</th>
                                <th style="width: 80px;">الكمية</th>
                                <th style="width: 80px;">نسبة الإنجاز</th>
                                <th style="width: 80px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="worksTableBody">
                            <tr id="noWorksRow">
                                <td colspan="9" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    اضغط على "إضافة عمل" لبدء إضافة الأعمال المنجزة.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- البحث السريع -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-search me-1"></i>
                            البحث السريع في المواد
                        </h6>
                    </div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-3" id="material-search"
                               placeholder="ابحث عن مادة برقم البند أو الوصف...">

                        <div id="material-suggestions" class="list-group" style="max-height: 300px; overflow-y: auto;">
                            <!-- سيتم عرض اقتراحات المواد هنا -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-search me-1"></i>
                            البحث السريع في بنود الأعمال
                        </h6>
                    </div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-3" id="work-search"
                               placeholder="ابحث عن بند عمل برقم البند أو الوصف...">

                        <div id="work-suggestions" class="list-group" style="max-height: 300px; overflow-y: auto;">
                            <!-- سيتم عرض اقتراحات بنود الأعمال هنا -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ملخص القيم -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calculator me-2"></i>
                    ملخص القيم
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="text-center p-3 border rounded">
                            <h6 class="text-muted mb-1">عدد المواد</h6>
                            <h4 class="text-primary mb-0" id="totalMaterialsValue">0 مادة</h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- مساحة فارغة -->
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        ملاحظة: يتم عرض عدد المواد
                    </small>
                </div>
            </div>
        </div>

        <!-- أزرار الحفظ -->
        <div class="card">
            <div class="card-body text-center">
                <button type="submit" class="btn btn-success btn-lg me-2">
                    <i class="fas fa-save me-1"></i>
                    حفظ التحديثات
                </button>
                <a href="<?= path('inventory/completion-certificates/view.php?id=' . $certificateId) ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-times me-1"></i>
                    إلغاء
                </a>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// بيانات المواد والأعمال الحالية
const existingMaterials = <?= json_encode($certificateMaterials) ?>;
const existingWorks = <?= json_encode($certificateWorks) ?>;
const materialsData = <?= json_encode($materials) ?>;
const workItemsData = <?= json_encode($workItems) ?>;

// متغيرات العدادات
let materialRowCounter = 0;
let workRowCounter = 0;

// تحميل البيانات عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    loadExistingData();
    setupSearchFunctionality();
    updateTotals();
});

// إضافة CSS للبحث السريع
const searchStyles = `
<style>
/* تحسين مظهر حقول البحث */
.material-search-input,
.work-search-input {
    border: 2px solid #e9ecef;
    transition: border-color 0.3s;
}

.material-search-input:focus,
.work-search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* تحسين مظهر القوائم المنسدلة المخصصة */
.material-select-container,
.work-select-container {
    position: relative !important;
}

.custom-dropdown {
    position: absolute !important;
    top: calc(100% + 2px) !important;
    left: 0 !important;
    right: 0 !important;
    background: white !important;
    border: 1px solid #ddd !important;
    border-radius: 0.375rem !important;
    max-height: 200px !important;
    overflow-y: auto !important;
    z-index: 9999 !important;
    display: none !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    width: 100% !important;
    min-width: 250px !important;
}

.custom-dropdown.show {
    display: block !important;
}

.dropdown-item-custom {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.15s ease-in-out;
}

.dropdown-item-custom:hover {
    background-color: #f8f9fa;
}

.dropdown-item-custom:last-child {
    border-bottom: none;
}

.dropdown-item-custom .item-number {
    font-weight: bold;
    color: #0d6efd;
}

.dropdown-item-custom .item-description {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

/* إصلاح مشكلة الجداول */
.table-responsive {
    overflow: visible !important;
}

.table td {
    position: relative !important;
    overflow: visible !important;
}

/* تصميم الصفوف غير الموجودة في طلبات الصرف */
.material-row-not-found {
    background-color: #f8d7da !important;
}

.material-row-not-found td {
    background-color: #f8d7da !important;
    border-color: #f5c6cb !important;
    color: #721c24 !important;
}

.material-row-not-found:hover td {
    background-color: #f1b0b7 !important;
}

.material-row-updated {
    background-color: #d1edff !important;
}

.material-row-updated td {
    background-color: #d1edff !important;
    border-color: #bee5eb !important;
    animation: highlight 2s ease-out;
}

.material-row-updated:hover td {
    background-color: #b3d9ff !important;
}

@keyframes highlight {
    0% { background-color: #b3d9ff !important; }
    100% { background-color: #d1edff !important; }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', searchStyles);

// دالة لاختبار القوائم المنسدلة
function testDropdowns() {
    console.log('Testing dropdowns...');
    const dropdowns = document.querySelectorAll('.custom-dropdown');
    console.log('Found dropdowns:', dropdowns.length);
    dropdowns.forEach((dropdown, index) => {
        console.log(`Dropdown ${index}:`, dropdown.className, dropdown.style.display);

        // اختبار إظهار القائمة
        dropdown.innerHTML = `
            <div class="dropdown-item-custom">
                <div class="item-number">TEST-001</div>
                <div class="item-description">عنصر تجريبي</div>
                <small class="text-muted">للاختبار فقط</small>
            </div>
        `;
        dropdown.classList.add('show');
        dropdown.style.display = 'block';
        console.log(`Dropdown ${index} should be visible now`);
    });

    // اختبار إضافة صف جديد
    if (dropdowns.length === 0) {
        console.log('No dropdowns found, adding a test row...');
        addMaterialRow();
        setTimeout(() => {
            const newDropdowns = document.querySelectorAll('.custom-dropdown');
            console.log('New dropdowns found:', newDropdowns.length);
        }, 100);
    }
}

// تحميل البيانات الموجودة
function loadExistingData() {
    // تحميل المواد
    existingMaterials.forEach(material => {
        addMaterialRowWithData(material);
    });

    // تحميل الأعمال
    existingWorks.forEach(work => {
        addWorkRowWithData(work);
    });
}

// إضافة صف مادة جديد
function addMaterialRow() {
    addMaterialRowWithData({});
}

// إضافة صف مادة مع بيانات
function addMaterialRowWithData(materialData) {
    materialRowCounter++;
    const tbody = document.getElementById('materialsTableBody');
    const noMaterialsRow = document.getElementById('noMaterialsRow');

    if (noMaterialsRow) {
        noMaterialsRow.remove();
    }

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <div class="material-select-container position-relative">
                <input type="text" class="form-control form-control-sm material-search-input"
                       placeholder="ابحث عن مادة..." autocomplete="off"
                       onkeyup="searchMaterialInRow(this, ${materialRowCounter})"
                       value="${materialData.material_id ? materialsData.find(m => m.id == materialData.material_id)?.item_number || '' : ''}">
                <select name="materials[${materialRowCounter}][material_id]" class="form-select form-select-sm d-none" required onchange="updateMaterialInfo(this, ${materialRowCounter})">
                    <option value="">اختر المادة</option>
                    ${materialsData.map(material =>
                        `<option value="${material.id}"
                            data-description="${material.description || ''}"
                            data-unit="${material.unit || ''}"
                            data-group="${material.group_number || ''}"
                            ${material.id == materialData.material_id ? 'selected' : ''}>${material.item_number}</option>`
                    ).join('')}
                </select>
                <div class="material-dropdown-${materialRowCounter} custom-dropdown"></div>
            </div>
        </td>
        <td><span class="material-group">${materialData.material_group || '-'}</span></td>
        <td><span class="material-description">${materialData.material_description || '-'}</span></td>
        <td><input type="number" name="materials[${materialRowCounter}][estimated_quantity]" class="form-control form-control-sm" step="0.001" min="0" value="${materialData.estimated_quantity || 0}" onchange="calculateMaterialQuantities(this)"></td>
        <td><input type="number" name="materials[${materialRowCounter}][actual_quantity]" class="form-control form-control-sm" step="0.001" min="0" value="${materialData.actual_quantity || 0}" onchange="calculateMaterialQuantities(this)"></td>
        <td><span class="fw-bold text-info dispensed-quantity">${materialData.dispensed_quantity || 0}</span><input type="hidden" name="materials[${materialRowCounter}][dispensed_quantity]" value="${materialData.dispensed_quantity || 0}"></td>
        <td><span class="fw-bold text-success returned-quantity">${materialData.returned_quantity || 0}</span><input type="hidden" name="materials[${materialRowCounter}][returned_quantity]" value="${materialData.returned_quantity || 0}"></td>

        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMaterialRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);
    updateTotals();
}

// إضافة صف عمل جديد
function addWorkRow() {
    addWorkRowWithData({});
}

// إضافة صف عمل مع بيانات
function addWorkRowWithData(workData) {
    console.log('addWorkRowWithData called with:', workData);

    workRowCounter++;
    const tbody = document.getElementById('worksTableBody');
    const noWorksRow = document.getElementById('noWorksRow');

    if (noWorksRow) {
        noWorksRow.remove();
    }

    // البحث عن بند العمل في البيانات
    const foundWorkItem = workItemsData.find(wi => wi.id == workData.work_item_id);
    console.log('Found work item:', foundWorkItem, 'for ID:', workData.work_item_id);

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <div class="work-select-container position-relative">
                <input type="text" class="form-control form-control-sm work-search-input"
                       placeholder="ابحث عن عمل..." autocomplete="off"
                       onkeyup="searchWorkInRow(this, ${workRowCounter})"
                       value="${workData.work_item_id ? workItemsData.find(w => w.id == workData.work_item_id)?.item_number || '' : ''}">
                <select name="works[${workRowCounter}][work_item_id]" class="form-select form-select-sm d-none" required onchange="updateWorkInfo(this, ${workRowCounter})">
                    <option value="">اختر العمل</option>
                    ${workItemsData.map(workItem =>
                        `<option value="${workItem.id}"
                            data-description="${workItemc.description || ''}"
                            data-unit="${workItemc.unit || ''}"
                            data-price="${workItem.standard_price || 0}"
                            ${workItem.id == workData.work_item_id ? 'selected' : ''}>${workItem.item_number}</option>`
                    ).join('')}
                </select>
                <div class="work-dropdown-${workRowCounter} custom-dropdown"></div>
            </div>
        </td>
        <td><span class="work-description">${workData.work_description || workData.work_item_description || '-'}</span></td>
        <td><span class="work-unit">${workData.unit || '-'}</span></td>
        <td><input type="number" name="works[${workRowCounter}][estimated_quantity]" class="form-control form-control-sm" step="0.001" min="0" value="${workData.estimated_quantity || workData.quantity || 0}" placeholder="المقايسة"></td>
        <td><input type="number" name="works[${workRowCounter}][quantity]" class="form-control form-control-sm" step="0.001" min="0" value="${workData.quantity || 0}" required></td>
        <td><input type="number" name="works[${workRowCounter}][completion_percentage]" class="form-control form-control-sm" step="0.1" min="0" max="100" value="${workData.completion_percentage || 100}"></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeWorkRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);

    // تحديث الوصف والسعر إذا كان بند العمل محدد
    if (foundWorkItem && workData.work_item_id) {
        const descriptionSpan = row.querySelector('.work-description');
    // تم إزالة حسابات الأسعار
    updateTotals();
}

// حذف صف مادة
function removeMaterialRow(button) {
    button.closest('tr').remove();
    updateTotals();

    const tbody = document.getElementById('materialsTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="noMaterialsRow">
                <td colspan="10" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    اضغط على "إضافة مادة" لبدء إضافة المواد المستخدمة.
                </td>
            </tr>
        `;
    }
}

// حذف صف عمل
function removeWorkRow(button) {
    button.closest('tr').remove();
    updateTotals();

    const tbody = document.getElementById('worksTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="noWorksRow">
                <td colspan="9" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    اضغط على "إضافة عمل" لبدء إضافة الأعمال المنجزة.
                </td>
            </tr>
        `;
    }
}

// تحديث معلومات المادة عند اختيارها
function updateMaterialInfo(select, rowIndex) {
    const row = select.closest('tr');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption.value) {
        row.querySelector('.material-description').textContent = selectedOption.dataset.description || '-';
        row.querySelector('.material-group').textContent = selectedOption.dataset.group || '-';

        // تحديث حقل البحث إذا كان موجوداً
        const searchInput = row.querySelector('.material-search-input');
        if (searchInput) {
            const material = materialsData.find(m => m.id == selectedOption.value);
            if (material) {
                searchInput.value = material.item_number;
            }
        }
    } else {
        row.querySelector('.material-description').textContent = '-';
        row.querySelector('.material-group').textContent = '-';
    }
}

// تحديث معلومات العمل عند اختياره
function updateWorkInfo(select, rowIndex) {
    const row = select.closest('tr');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption.value) {
        row.querySelector('.work-description').textContent = selectedOption.dataset.description || '-';
        const priceInput = row.querySelector('input[name*="[unit_price]"]');
        if (priceInput && selectedOption.dataset.price) {
            priceInput.value = selectedOption.dataset.price;
            calculateWorkTotal(priceInput);
        }

        // تحديث حقل البحث إذا كان موجوداً
        const searchInput = row.querySelector('.work-search-input');
        if (searchInput) {
            const workItem = workItemsData.find(w => w.id == selectedOption.value);
            if (workItem) {
                searchInput.value = workItem.item_number;
            }
        }
    } else {
        row.querySelector('.work-description').textContent = '-';
    }
}

// حساب كميات المواد (الصرف والإرجاع)
function calculateMaterialQuantities(input) {
    const row = input.closest('tr');
    const estimatedInput = row.querySelector('input[name*="[estimated_quantity]"]');
    const actualInput = row.querySelector('input[name*="[actual_quantity]"]');
    const dispensedSpan = row.querySelector('.dispensed-quantity');
    const returnedSpan = row.querySelector('.returned-quantity');
    const dispensedHidden = row.querySelector('input[name*="[dispensed_quantity]"]');
    const returnedHidden = row.querySelector('input[name*="[returned_quantity]"]');

    const estimated = parseFloat(estimatedInput.value) || 0;
    const actual = parseFloat(actualInput.value) || 0;

    let dispensed = 0;
    let returned = 0;

    if (actual > estimated) {
        dispensed = actual - estimated;
    } else if (estimated > actual) {
        returned = estimated - actual;
    }

    dispensedSpan.textContent = dispensed.toFixed(3);
    returnedSpan.textContent = returned.toFixed(3);
    dispensedHidden.value = dispensed;
    returnedHidden.value = returned;

    updateTotals();
}

// تم حذف دالة حساب إجمالي المادة - لا نحتاج أسعار للمواد

// البحث في المواد داخل الصف
function searchMaterialInRow(input, rowIndex) {
    console.log('searchMaterialInRow called:', input.value, rowIndex);
    const searchTerm = input.value.toLowerCase();
    const dropdownContainer = document.querySelector(`.material-dropdown-${rowIndex}`);

    console.log('Dropdown container found:', dropdownContainer);

    if (!dropdownContainer) {
        console.error('Dropdown container not found for rowIndex:', rowIndex);
        return;
    }

    if (searchTerm.length < 2) {
        dropdownContainer.classList.remove('show');
        return;
    }

    const filteredMaterials = materialsData.filter(material =>
        material.item_number.toLowerCase().includes(searchTerm) ||
        material.description.toLowerCase().includes(searchTerm)
    );

    console.log('Filtered materials:', filteredMaterials.length);

    if (filteredMaterials.length === 0) {
        dropdownContainer.innerHTML = '<div class="dropdown-item-custom">لا توجد نتائج</div>';
        dropdownContainer.classList.add('show');
        return;
    }

    dropdownContainer.innerHTML = filteredMaterials.slice(0, 10).map(material => `
        <div class="dropdown-item-custom" onclick="selectMaterialInRow(${material.id}, ${rowIndex}, '${material.item_number.replace(/'/g, "\\'")}')">
            <div class="item-number">${material.item_number}</div>
            <div class="item-description">${material.description}</div>
            <small class="text-muted">المجموعة: ${material.group_number || 'غير محدد'}</small>
        </div>
    `).join('');

    dropdownContainer.classList.add('show');
    console.log('Dropdown should be visible now');
}

// اختيار مادة في الصف
function selectMaterialInRow(materialId, rowIndex, itemNumber) {
    const input = document.querySelector(`input[onkeyup*="searchMaterialInRow"][onkeyup*="${rowIndex}"]`);
    const select = document.querySelector(`select[name="materials[${rowIndex}][material_id]"]`);
    const dropdownContainer = document.querySelector(`.material-dropdown-${rowIndex}`);

    if (input && select) {
        input.value = itemNumber;
        select.value = materialId;
        dropdownContainer.classList.remove('show');

        // تحديث معلومات المادة
        updateMaterialInfo(select, rowIndex);
    }
}

// البحث في بنود الأعمال داخل الصف
function searchWorkInRow(input, rowIndex) {
    console.log('searchWorkInRow called:', input.value, rowIndex);
    const searchTerm = input.value.toLowerCase();
    const dropdownContainer = document.querySelector(`.work-dropdown-${rowIndex}`);

    console.log('Work dropdown container found:', dropdownContainer);

    if (!dropdownContainer) {
        console.error('Work dropdown container not found for rowIndex:', rowIndex);
        return;
    }

    if (searchTerm.length < 2) {
        dropdownContainer.classList.remove('show');
        return;
    }

    const filteredWorks = workItemsData.filter(work =>
        work.item_number.toLowerCase().includes(searchTerm) ||
        work.description.toLowerCase().includes(searchTerm)
    );

    console.log('Filtered works:', filteredWorks.length);

    if (filteredWorks.length === 0) {
        dropdownContainer.innerHTML = '<div class="dropdown-item-custom">لا توجد نتائج</div>';
        dropdownContainer.classList.add('show');
        return;
    }

    dropdownContainer.innerHTML = filteredWorks.slice(0, 10).map(work => `
        <div class="dropdown-item-custom" onclick="selectWorkInRow(${work.id}, ${rowIndex}, '${work.item_number.replace(/'/g, "\\'")}')">
            <div class="item-number">${work.item_number}</div>
            <div class="item-description">${work.description}</div>
            <small class="text-muted">السعر: ${work.standard_price || 0} ريال</small>
        </div>
    `).join('');

    dropdownContainer.classList.add('show');
    console.log('Work dropdown should be visible now');
}

// اختيار بند عمل في الصف
function selectWorkInRow(workId, rowIndex, itemNumber) {
    const input = document.querySelector(`input[onkeyup*="searchWorkInRow"][onkeyup*="${rowIndex}"]`);
    const select = document.querySelector(`select[name="works[${rowIndex}][work_item_id]"]`);
    const dropdownContainer = document.querySelector(`.work-dropdown-${rowIndex}`);

    if (input && select) {
        input.value = itemNumber;
        select.value = workId;
        dropdownContainer.classList.remove('show');

        // تحديث معلومات العمل
        updateWorkInfo(select, rowIndex);
    }
}

// دالة اختبار البحث
function testSearchDropdown() {
    console.log('Testing search dropdown...');

    // إضافة صف جديد إذا لم يكن موجود
    if (document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)').length === 0) {
        addMaterialRow();
    }

    setTimeout(() => {
        const searchInputs = document.querySelectorAll('.material-search-input');
        console.log('Found search inputs:', searchInputs.length);

        if (searchInputs.length > 0) {
            const firstInput = searchInputs[0];
            firstInput.value = 'test';
            firstInput.focus();

            // محاكاة البحث
            const event = new Event('keyup');
            firstInput.dispatchEvent(event);

            // إظهار قائمة تجريبية
            const rowIndex = firstInput.getAttribute('onkeyup').match(/\d+/)[0];
            const dropdown = document.querySelector(`.material-dropdown-${rowIndex}`);

            if (dropdown) {
                dropdown.innerHTML = `
                    <div class="dropdown-item-custom" style="padding: 10px; border-bottom: 1px solid #eee;">
                        <div style="font-weight: bold; color: #007bff;">TEST-001</div>
                        <div style="font-size: 0.9em; color: #666;">مادة تجريبية للاختبار</div>
                        <small style="color: #999;">المجموعة: اختبار</small>
                    </div>
                `;
                dropdown.classList.add('show');
                dropdown.style.display = 'block';
                dropdown.style.visibility = 'visible';
                dropdown.style.opacity = '1';

                console.log('Test dropdown shown:', dropdown);
            }
        }
    }, 100);
}

// إخفاء القوائم المنسدلة عند النقر خارجها
document.addEventListener('click', function(event) {
    if (!event.target.closest('.material-select-container') && !event.target.closest('.work-select-container')) {
        document.querySelectorAll('.custom-dropdown').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// تم إزالة calculateWorkTotal

// تحديث الإجماليات
function updateTotals() {
    // حساب عدد المواد
    const materialsCount = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)').length;

    document.getElementById('totalMaterialsValue').textContent = materialsCount + ' مادة';
}

// إعداد وظائف البحث
function setupSearchFunctionality() {
    // البحث في المواد
    const materialSearch = document.getElementById('material-search');
    const materialSuggestions = document.getElementById('material-suggestions');

    materialSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        materialSuggestions.innerHTML = '';

        if (query.length < 2) return;

        const filteredMaterials = materialsData.filter(material =>
            material.item_number.toLowerCase().includes(query) ||
            (material.description && material.description.toLowerCase().includes(query))
        );

        filteredMaterials.slice(0, 10).forEach(material => {
            const item = document.createElement('a');
            item.className = 'list-group-item list-group-item-action';
            item.href = '#';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${material.item_number}</strong>
                        <br>
                        <small class="text-muted">${material.description || 'لا يوجد وصف'}</small>
                    </div>
                    <span class="badge bg-primary">${material.unit || '-'}</span>
                </div>
            `;

            item.addEventListener('click', function(e) {
                e.preventDefault();
                addMaterialRowWithData({
                    material_id: material.id,
                    material_description: material.description,
                    material_group: material.group_number,
                    unit: material.unit
                });
                materialSearch.value = '';
                materialSuggestions.innerHTML = '';
            });

            materialSuggestions.appendChild(item);
        });
    });

    // البحث في بنود الأعمال
    const workSearch = document.getElementById('work-search');
    const workSuggestions = document.getElementById('work-suggestions');

    workSearch.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        workSuggestions.innerHTML = '';

        if (query.length < 2) return;

        const filteredWorks = workItemsData.filter(work =>
            work.item_number.toLowerCase().includes(query) ||
            (work.description && work.description.toLowerCase().includes(query))
        );

        filteredWorks.slice(0, 10).forEach(work => {
            const item = document.createElement('a');
            item.className = 'list-group-item list-group-item-action';
            item.href = '#';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${work.item_number}</strong>
                        <br>
                        <small class="text-muted">${work.description || 'لا يوجد وصف'}</small>
                    </div>
                </div>
            `;

            item.addEventListener('click', function(e) {
                e.preventDefault();
                addWorkRowWithData({
                    work_item_id: work.id,
                    work_item_description: work.description
                });
                workSearch.value = '';
                workSuggestions.innerHTML = '';
            });

            workSuggestions.appendChild(item);
        });
    });
}

// تحديث المواد من طلبات الصرف
function updateMaterialsFromWorkOrder() {
    const workOrderId = document.querySelector('input[name="work_order_id"]').value;

    if (!workOrderId) {
        Swal.fire({
            title: 'تنبيه',
            text: 'لم يتم العثور على أمر العمل',
            icon: 'warning',
            confirmButtonText: 'موافق'
        });
        return;
    }

    Swal.fire({
        title: 'تحديث المواد',
        text: 'سيتم تحديث المواد من طلبات الصرف المرتبطة بأمر العمل. هل تريد المتابعة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، حدث المواد',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            performMaterialsUpdate(workOrderId);
        }
    });
}

// تنفيذ تحديث المواد
function performMaterialsUpdate(workOrderId) {
    const updateBtn = document.getElementById('updateMaterialsBtn');
    const originalText = updateBtn.innerHTML;
    updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التحديث...';
    updateBtn.disabled = true;

    fetch('get-work-order-materials.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ work_order_id: workOrderId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateExistingMaterialsFromWorkOrder(data.materials || []);
        } else {
            Swal.fire({
                title: 'خطأ',
                text: data.message || 'حدث خطأ أثناء تحديث المواد',
                icon: 'error',
                confirmButtonText: 'موافق'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'خطأ',
            text: 'حدث خطأ في الاتصال بالخادم',
            icon: 'error',
            confirmButtonText: 'موافق'
        });
    })
    .finally(() => {
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    });
}

// تحديث المواد الموجودة من طلبات الصرف
function updateExistingMaterialsFromWorkOrder(workOrderMaterials) {
    const tbody = document.getElementById('materialsTableBody');
    const existingRows = tbody.querySelectorAll('tr:not(#noMaterialsRow)');

    let updatedCount = 0;
    let notFoundCount = 0;
    let addedCount = 0;

    // إزالة التنسيقات السابقة
    existingRows.forEach(row => {
        row.classList.remove('material-row-not-found', 'material-row-updated');
        row.removeAttribute('title');
    });

    // تحديث الصفوف الموجودة
    existingRows.forEach(row => {
        const materialSelect = row.querySelector('select[name*="material_id"]');
        if (!materialSelect || !materialSelect.value) return;

        const materialId = parseInt(materialSelect.value);
        const workOrderMaterial = workOrderMaterials.find(m => m.material_id == materialId);

        if (workOrderMaterial) {
            // تحديث الكميات
            const estimatedInput = row.querySelector('input[name*="estimated_quantity"]');
            const actualInput = row.querySelector('input[name*="actual_quantity"]');
            const dispensedSpan = row.querySelector('.dispensed-quantity');
            const dispensedHidden = row.querySelector('input[name*="dispensed_quantity"]');

            if (estimatedInput) {
                estimatedInput.value = workOrderMaterial.total_dispensed_quantity || 0;
            }
            if (actualInput) {
                actualInput.value = workOrderMaterial.total_dispensed_quantity || 0;
            }
            if (dispensedSpan) {
                dispensedSpan.textContent = parseFloat(workOrderMaterial.total_dispensed_quantity || 0).toFixed(3);
            }
            if (dispensedHidden) {
                dispensedHidden.value = workOrderMaterial.total_dispensed_quantity || 0;
            }

            // إضافة تأثير التحديث
            row.classList.add('material-row-updated');
            updatedCount++;
        } else {
            // المادة غير موجودة في طلبات الصرف
            row.classList.add('material-row-not-found');
            row.title = 'هذه المادة غير موجودة في طلبات الصرف المعتمدة لأمر العمل';
            notFoundCount++;
        }
    });

    // إضافة المواد الجديدة التي لم تكن موجودة في الشهادة
    workOrderMaterials.forEach(material => {
        const existingMaterial = Array.from(existingRows).find(row => {
            const select = row.querySelector('select[name*="material_id"]');
            return select && parseInt(select.value) === material.material_id;
        });

        if (!existingMaterial) {
            // إضافة مادة جديدة
            addMaterialRowWithData({
                material_id: material.material_id,
                material_description: material.material_description,
                material_group: material.material_group,
                estimated_quantity: material.total_dispensed_quantity,
                actual_quantity: material.total_dispensed_quantity,
                dispensed_quantity: material.total_dispensed_quantity
            });
            addedCount++;
        }
    });

    // إعادة تمكين الزر
    const updateBtn = document.getElementById('updateMaterialsBtn');
    updateBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>تحديث من طلبات الصرف';
    updateBtn.disabled = false;

    // عرض رسالة النتيجة
    let message = '';
    if (updatedCount > 0) message += `تم تحديث ${updatedCount} مادة. `;
    if (addedCount > 0) message += `تم إضافة ${addedCount} مادة جديدة. `;
    if (notFoundCount > 0) message += `${notFoundCount} مادة غير موجودة في طلبات الصرف (باللون الأحمر).`;

    if (updatedCount === 0 && addedCount === 0 && notFoundCount === 0) {
        message = 'لا توجد مواد للتحديث.';
    }

    Swal.fire({
        title: 'تم التحديث',
        text: message,
        icon: updatedCount > 0 || addedCount > 0 ? 'success' : 'info',
        confirmButtonText: 'موافق'
    });

    // تحديث الإجماليات
    updateTotals();
}

// دالة التوليد التلقائي لبنود الأعمال
function autoGenerateWorkItems() {
    console.log('autoGenerateWorkItems called');

    // التحقق من وجود مواد في الشهادة
    const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');
    console.log('Material rows found in autoGenerateWorkItems:', materialRows.length);

    if (materialRows.length === 0) {
        Swal.fire({
            title: 'تنبيه',
            text: 'يرجى إضافة المواد المستخدمة في الشهادة أولاً',
            icon: 'warning',
            confirmButtonText: 'موافق'
        });
        return;
    }

    // تأكيد العملية
    Swal.fire({
        title: 'توليد بنود الأعمال تلقائياً',
        text: 'سيتم توليد بنود الأعمال بناءً على المواد المستخدمة في الشهادة. هل تريد المتابعة؟',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، ولّد تلقائياً',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            performAutoGenerationFromCertificateMaterials();
        }
    });
}

// تنفيذ التوليد التلقائي من المواد الموجودة في الشهادة
function performAutoGenerationFromCertificateMaterials() {
    console.log('performAutoGenerationFromCertificateMaterials called');

    // إظهار مؤشر التحميل
    const autoGenerateBtn = document.getElementById('autoGenerateBtn');
    const originalText = autoGenerateBtn.innerHTML;
    autoGenerateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التوليد...';
    autoGenerateBtn.disabled = true;

    try {
        // جمع بيانات المواد من الجدول
        const certificateMaterials = [];
        const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');

        console.log('Total material rows found:', materialRows.length);

        materialRows.forEach((row, index) => {
            // البحث عن عناصر المادة والكمية بطرق متعددة
            let materialSelect = row.querySelector('select[name*="[material_id]"]');
            if (!materialSelect) {
                materialSelect = row.querySelector('select[name*="material_id"]');
            }

            let quantityInput = row.querySelector('input[name*="[actual_quantity]"]');
            if (!quantityInput) {
                quantityInput = row.querySelector('input[name*="actual_quantity"]');
            }

            console.log('Row', index, '- Material Select:', materialSelect, 'Value:', materialSelect?.value, 'Quantity Input:', quantityInput, 'Value:', quantityInput?.value);

            if (materialSelect && materialSelect.value && quantityInput) {
                const materialId = parseInt(materialSelect.value);
                const quantity = parseFloat(quantityInput.value) || 0;

                console.log('Material ID:', materialId, 'Quantity:', quantity, 'Valid:', materialId && quantity > 0);

                if (materialId && quantity > 0) {
                    certificateMaterials.push({
                        material_id: materialId,
                        quantity: quantity
                    });
                }
            }
        });

        console.log('Certificate Materials:', certificateMaterials);

        if (certificateMaterials.length === 0) {
            // التحقق من سبب عدم وجود مواد
            const materialsWithZeroQuantity = [];
            materialRows.forEach((row, index) => {
                const materialSelect = row.querySelector('select[name*="[material_id]"]') || row.querySelector('select[name*="material_id"]');
                const quantityInput = row.querySelector('input[name*="[actual_quantity]"]') || row.querySelector('input[name*="actual_quantity"]');

                if (materialSelect && materialSelect.value && quantityInput) {
                    const materialId = parseInt(materialSelect.value);
                    const quantity = parseFloat(quantityInput.value) || 0;

                    if (materialId && quantity === 0) {
                        const materialText = materialSelect.options[materialSelect.selectedIndex].text;
                        materialsWithZeroQuantity.push(`- ${materialText}`);
                    }
                }
            });

            let errorMessage = 'لا توجد مواد صحيحة في الشهادة.\n\n';

            if (materialsWithZeroQuantity.length > 0) {
                errorMessage += 'المواد التالية محددة لكن الكمية الفعلية = 0:\n';
                errorMessage += materialsWithZeroQuantity.join('\n');
                errorMessage += '\n\nيرجى إدخال الكميات الفعلية للمواد المستخدمة.';
            } else {
                errorMessage += 'يرجى إضافة المواد وإدخال الكميات الفعلية أولاً.';
            }

            throw new Error(errorMessage);
        }

        // إرسال طلب التوليد التلقائي
        fetch('auto-generate-from-materials.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                materials: certificateMaterials
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // إظهار رسالة نجاح
                Swal.fire({
                    title: 'تم التوليد بنجاح!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonText: 'موافق'
                });

                // إضافة بنود الأعمال المولدة إلى الجدول
                if (data.data && data.data.generated_work_items) {
                    addGeneratedWorkItems(data.data.generated_work_items);
                }

                // عرض سجل التوليد
                if (data.data && data.data.generation_log && data.data.generation_log.length > 0) {
                    console.log('سجل التوليد:', data.data.generation_log);
                }
            } else {
                Swal.fire({
                    title: 'خطأ في التوليد',
                    text: data.message,
                    icon: 'error',
                    confirmButtonText: 'موافق'
                });
            }
        })
        .catch(error => {
            console.error('خطأ في التوليد التلقائي:', error);

            let errorMessage = 'حدث خطأ أثناء التوليد التلقائي. يرجى المحاولة مرة أخرى.';
            if (error.message) {
                errorMessage = error.message;
            }

            Swal.fire({
                title: 'خطأ في التوليد التلقائي',
                text: errorMessage,
                icon: 'error',
                confirmButtonText: 'موافق'
            });
        });

    } catch (error) {
        console.error('Error in auto generation:', error);
        Swal.fire({
            title: 'خطأ',
            text: 'حدث خطأ أثناء معالجة البيانات',
            icon: 'error',
            confirmButtonText: 'موافق'
        });
    } finally {
        autoGenerateBtn.innerHTML = originalText;
        autoGenerateBtn.disabled = false;
    }
}

// إضافة بنود الأعمال المولدة إلى الجدول
function addGeneratedWorkItems(generatedWorkItems) {
    console.log('Adding generated work items:', generatedWorkItems);

    // إضافة كل بند عمل مولد
    Object.values(generatedWorkItems).forEach(workItem => {
        console.log('Adding work item:', workItem);

        addWorkRowWithData({
            work_item_id: workItem.work_item_id,
            work_description: workItem.work_item_description,
            quantity: workItem.total_quantity,
            completion_percentage: 100
        });
    });

    // تحديث الإجماليات
    updateTotals();
}

</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/layout.php';
?>
