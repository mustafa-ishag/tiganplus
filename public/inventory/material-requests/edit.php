<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة تعديل طلب الصرف
 * Edit Material Request Page
 */

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/MaterialRequest.php';
require_once __DIR__ . '/../../../models/Material.php';
require_once __DIR__ . '/../../../models/WorkOrder.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_requests_edit')) {
    setAlert('ليس لديك صلاحية لتعديل طلبات الصرف', 'error');
    redirect('index.php');
}

$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    setAlert('معرف طلب الصرف غير صحيح', 'error');
    redirect('index.php');
}

$materialRequestModel = new MaterialRequest();
$materialModel = new Material();
$workOrderModel = new WorkOrder();

// الحصول على طلب الصرف
$request = $materialRequestModel->findById($requestId);
if (!$request) {
    setAlert('طلب الصرف غير موجود', 'error');
    redirect('index.php');
}

// التحقق من إمكانية التعديل
if (!in_array($request['status'], ['draft', 'revision_requested'])) {
    setAlert('لا يمكن تعديل طلب الصرف بعد إرساله', 'error');
    redirect('view.php?id=' . $requestId);
}

// التحقق من صلاحية التعديل (المنشئ أو المدير)
if ($request['requested_by'] != $_SESSION['user_id'] && !hasPermission('inventory_requests_edit')) {
    setAlert('ليس لديك صلاحية لتعديل هذا الطلب', 'error');
    redirect('view.php?id=' . $requestId);
}

// الحصول على أوامر العمل المتاحة للصرف
$workOrders = $workOrderModel->getWorkOrdersForMaterialRequest($_SESSION['user_branch_id'] ?? null);

// الحصول على المواد النشطة
$materials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description, mc.unit, m.current_stock
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE m.is_active = 1
     ORDER BY m.item_number"
);

// الحصول على تفاصيل المواد الحالية
$currentMaterials = $materialRequestModel->fetchAll(
    "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
     FROM material_request_details mrd
     JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE mrd.request_id = ?
     ORDER BY m.item_number",
    [$requestId]
);

$errors = [];
$formData = [
    'work_order_id' => $request['work_order_id'],
    'request_date' => $request['request_date'],
    'required_date' => $request['required_date'],
    'notes' => $request['notes'],
    'materials' => []
];

// تحويل المواد الحالية إلى تنسيق النموذج
foreach ($currentMaterials as $material) {
    $formData['materials'][] = [
        'material_id' => $material['material_id'],
        'quantity' => $material['requested_quantity']
    ];
}

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'work_order_id' => $_POST['work_order_id'] ?? '',
        'request_date' => $_POST['request_date'] ?? '',
        'required_date' => $_POST['required_date'] ?? '',
        'notes' => trim($_POST['notes'] ?? ''),
        'materials' => $_POST['materials'] ?? []
    ];
    
    $action = $_POST['action'] ?? 'save_draft';
    
    // التحقق من صحة البيانات
    if (empty($formData['work_order_id'])) {
        $errors['work_order_id'] = 'أمر العمل مطلوب';
    } else {
        // التحقق من إمكانية إنشاء طلب صرف لهذا الأمر
        $canCreate = $workOrderModel->canCreateMaterialRequest($formData['work_order_id'], $requestId);
        if (!$canCreate['can_create']) {
            $errors['work_order_id'] = $canCreate['reason'];
        }
    }
    
    if (empty($formData['request_date'])) {
        $errors['request_date'] = 'تاريخ الطلب مطلوب';
    }
    
    if (empty($formData['required_date'])) {
        $errors['required_date'] = 'تاريخ الحاجة مطلوب';
    } elseif ($formData['required_date'] < $formData['request_date']) {
        $errors['required_date'] = 'تاريخ الحاجة يجب أن يكون بعد تاريخ الطلب';
    }
    
    if (empty($formData['materials'])) {
        $errors['materials'] = 'يجب إضافة مادة واحدة على الأقل';
    } else {
        // التحقق من صحة بيانات المواد
        foreach ($formData['materials'] as $index => $material) {
            if (empty($material['material_id'])) {
                $errors["materials_{$index}_material_id"] = 'يجب اختيار المادة';
            }
            
            if (empty($material['quantity']) || $material['quantity'] <= 0) {
                $errors["materials_{$index}_quantity"] = 'الكمية يجب أن تكون أكبر من صفر';
            }
            
            // التحقق من توفر المخزون
            if (!empty($material['material_id']) && !empty($material['quantity'])) {
                $materialData = $materialModel->findById($material['material_id']);
                if ($materialData && $materialData['current_stock'] < $material['quantity']) {
                    $errors["materials_{$index}_quantity"] = "الكمية المطلوبة ({$material['quantity']}) أكبر من المخزون المتاح ({$materialData['current_stock']})";
                }
            }
        }
    }
    
    // إذا لم توجد أخطاء، قم بتحديث الطلب
    if (empty($errors)) {
        $requestData = [
            'work_order_id' => $formData['work_order_id'],
            'request_date' => $formData['request_date'],
            'required_date' => $formData['required_date'],
            'notes' => $formData['notes'],
            'status' => $action === 'submit' ? 'submitted' : ($request['status'] === 'revision_requested' ? 'revision_requested' : 'draft'),
            'updated_at' => getCurrentDateTime()
        ];
        
        $result = $materialRequestModel->updateRequest($requestId, $requestData, $formData['materials'], $action);

        if ($result['success']) {
            $message = $action === 'submit' ? 'تم تحديث وإرسال طلب الصرف بنجاح' : 'تم تحديث طلب الصرف بنجاح';
            setAlert($message, 'success');
            redirect('view.php?id=' . $requestId);
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

$pageTitle = 'تعديل طلب الصرف - ' . $request['request_number'];
$currentPage = 'material-requests';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-edit text-warning me-2"></i>
                تعديل طلب الصرف
            </h2>
            <p class="text-muted mb-0">تعديل طلب الصرف رقم: <?= htmlspecialchars($request['request_number']) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="view.php?id=<?= $request['id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i>
                    عرض التفاصيل
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right me-1"></i>
                    العودة إلى القائمة
                </a>
            </div>
        </div>
    </div>

    <!-- تحذير -->
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>تنبيه:</strong> يمكن تعديل طلبات الصرف في حالة المسودة أو طلب التعديل فقط. بعد الإرسال لن يمكن التعديل.
    </div>

    <!-- نموذج تعديل طلب الصرف -->
    <form method="POST" id="materialRequestForm">
        <div class="row">
            <div class="col-lg-8">
                <!-- معلومات الطلب الأساسية -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">معلومات طلب الصرف</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($errors['general']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="work_order_id" class="form-label">
                                    أمر العمل <span class="text-danger">*</span>
                                </label>
                                <select class="form-select <?= isset($errors['work_order_id']) ? 'is-invalid' : '' ?>" 
                                        id="work_order_id" name="work_order_id" required>
                                    <option value="">اختر أمر العمل</option>
                                    <?php foreach ($workOrders as $workOrder): ?>
                                        <option value="<?= $workOrder['id'] ?>" 
                                                <?= $formData['work_order_id'] == $workOrder['id'] ? 'selected' : '' ?>
                                                data-estimated-value="<?= $workOrder['estimated_value'] ?>"
                                                data-disbursement-status="<?= $workOrder['disbursement_status'] ?>">
                                            <?= htmlspecialchars($workOrder['work_order_number']) ?> - 
                                            <?= htmlspecialchars($workOrder['work_order_type_description']) ?>
                                            (<?= formatCurrency($workOrder['estimated_value']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['work_order_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['work_order_id'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="request_date" class="form-label">
                                    تاريخ الطلب <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control <?= isset($errors['request_date']) ? 'is-invalid' : '' ?>" 
                                       id="request_date" name="request_date" 
                                       value="<?= htmlspecialchars($formData['request_date']) ?>" required>
                                <?php if (isset($errors['request_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['request_date'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="required_date" class="form-label">
                                    تاريخ الحاجة <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control <?= isset($errors['required_date']) ? 'is-invalid' : '' ?>" 
                                       id="required_date" name="required_date" 
                                       value="<?= htmlspecialchars($formData['required_date']) ?>" required>
                                <?php if (isset($errors['required_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['required_date'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="ملاحظات إضافية حول طلب الصرف"><?= htmlspecialchars($formData['notes']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- المواد المطلوبة -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">المواد المطلوبة</h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="loadFromCompletionCertificate()" id="loadFromCertificateBtn">
                                <i class="fas fa-certificate me-1"></i>
                                تحميل من شهادة الإنجاز
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>
                                إضافة مادة
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($errors['materials'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $errors['materials'] ?>
                            </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0 materials-table">
                                <thead class="table-primary">
                                    <tr>
                                        <th style="width: 120px;">رقم البند</th>
                                        <th style="width: 200px;">وصف المادة</th>
                                        <th style="width: 60px;">الوحدة</th>
                                        <th style="width: 80px;">المقايسة</th>
                                        <th style="width: 80px;">الكمية المطلوبة</th>
                                        <th style="width: 80px;">الفرق</th>
                                        <th style="width: 80px;">المخزون</th>
                                        <th style="width: 60px;">إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="materialsTableBody">
                                    <tr id="noMaterialsRow">
                                        <td colspan="8" class="text-center text-muted py-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            اضغط على "إضافة مادة" لبدء إضافة المواد المطلوبة.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 mx-3 mb-3 p-3 bg-light rounded">
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <strong>إجمالي البنود: <span id="total-items">0</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="mt-4 d-flex justify-content-between">
                    <div>
                        <button type="submit" name="action" value="save_draft" class="btn btn-secondary me-2">
                            <i class="fas fa-save me-1"></i>
                            حفظ كمسودة
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i>
                            حفظ وإرسال
                        </button>
                    </div>
                    <div>
                        <a href="view.php?id=<?= $request['id'] ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times me-1"></i>
                            إلغاء
                        </a>
                        <?php if (hasPermission('inventory_requests_delete')): ?>
                            <button type="button" class="btn btn-danger" onclick="deleteRequest()">
                                <i class="fas fa-trash me-1"></i>
                                حذف الطلب
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات الطلب الحالي -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            معلومات الطلب الحالي
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="fw-bold text-muted">رقم الطلب:</td>
                                <td><?= htmlspecialchars($request['request_number']) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">الحالة:</td>
                                <td>
                                    <?php
                                    $statusInfo = getStatusLabel($request['status']);
                                    ?>
                                    <span class="badge bg-<?= $statusInfo[1] ?>"><?= $statusInfo[0] ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">تاريخ الإنشاء:</td>
                                <td><?= formatDateTime($request['created_at']) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">آخر تحديث:</td>
                                <td><?= formatDateTime($request['updated_at']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- البحث السريع في المواد -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-search me-1"></i>
                            البحث السريع في المواد
                        </h6>
                    </div>
                    <div class="card-body">
                        <input type="text" class="form-control mb-3" id="material-search" 
                               placeholder="ابحث عن مادة...">
                        
                        <div id="material-suggestions" class="list-group" style="max-height: 300px; overflow-y: auto;">
                            <!-- سيتم عرض اقتراحات المواد هنا -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- قالب صف المادة -->
<template id="material-row-template">
    <!-- سيتم إنشاء صفوف الجدول بواسطة JavaScript -->
</template>

<script>
let materialRowIndex = 0;
const materialsData = <?= json_encode($materials) ?>;
const currentMaterials = <?= json_encode($formData['materials']) ?>;

// إضافة صف مادة جديد
function addMaterialRow(materialData = null) {
    const tbody = document.getElementById('materialsTableBody');
    const noMaterialsRow = document.getElementById('noMaterialsRow');

    if (noMaterialsRow) {
        noMaterialsRow.remove();
    }

    materialRowIndex++;

    const row = document.createElement('tr');
    row.innerHTML = `
        <td>
            <div class="material-select-container position-relative">
                <input type="text" class="form-control form-control-sm material-search-input"
                       placeholder="ابحث عن مادة..." autocomplete="off"
                       onkeyup="searchMaterialInRow(this, ${materialRowIndex})">
                <select name="materials[${materialRowIndex}][material_id]" class="form-select form-select-sm d-none" onchange="updateMaterialInfo(this, ${materialRowIndex})">
                    <option value="">اختر المادة</option>
                    ${materialsData.map(material =>
                        `<option value="${material.id}" data-code="${material.item_number}" data-description="${material.description || ''}" data-unit="${material.unit || ''}" data-stock="${material.current_stock || 0}">${material.item_number}</option>`
                    ).join('')}
                </select>
                <div class="material-dropdown-${materialRowIndex} custom-dropdown"></div>
            </div>
        </td>
        <td><span id="material_description_${materialRowIndex}">-</span></td>
        <td><span id="material_unit_${materialRowIndex}">-</span></td>
        <td><span id="material_estimated_${materialRowIndex}" class="text-muted">-</span></td>
        <td><input type="number" name="materials[${materialRowIndex}][quantity]" class="form-control form-control-sm" step="0.001" min="0" value="0" required></td>
        <td><span id="material_difference_${materialRowIndex}" class="text-muted">-</span></td>
        <td><span id="material_stock_${materialRowIndex}">-</span></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMaterialRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

    tbody.appendChild(row);

    // تعبئة البيانات إذا تم توفيرها
    if (materialData) {
        const materialSelect = row.querySelector('select[name*="material_id"]');
        const quantityInput = row.querySelector('input[name*="quantity"]');
        const searchInput = row.querySelector('.material-search-input');

        materialSelect.value = materialData.material_id;
        const selectedOption = materialSelect.options[materialSelect.selectedIndex];
        if (selectedOption) {
            searchInput.value = selectedOption.dataset.code;
            searchInput.classList.add('selected-item');
        }
        materialSelect.dispatchEvent(new Event('change'));
        quantityInput.value = materialData.quantity;
    }

    updateTotals();
}

// إزالة صف مادة
function removeMaterialRow(button) {
    button.closest('tr').remove();
    updateTotals();

    // إضافة صف "لا توجد مواد" إذا لم تعد هناك مواد
    const tbody = document.getElementById('materialsTableBody');
    if (tbody.children.length === 0) {
        tbody.innerHTML = `
            <tr id="noMaterialsRow">
                <td colspan="8" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    اضغط على "إضافة مادة" لبدء إضافة المواد المطلوبة.
                </td>
            </tr>
        `;
    }
}

// تحديث معلومات المادة
function updateMaterialInfo(select, rowIndex) {
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption.value) {
        document.getElementById(`material_description_${rowIndex}`).textContent = selectedOption.dataset.description || '-';
        document.getElementById(`material_unit_${rowIndex}`).textContent = selectedOption.dataset.unit || '-';
        const stock = parseFloat(selectedOption.dataset.stock || 0);
        const stockEl = document.getElementById(`material_stock_${rowIndex}`);
        if (stockEl) {
            stockEl.textContent = stock.toFixed(3);
            stockEl.className = stock <= 0 ? 'text-danger fw-bold' : '';
        }
    } else {
        document.getElementById(`material_description_${rowIndex}`).textContent = '-';
        document.getElementById(`material_unit_${rowIndex}`).textContent = '-';
        const stockEl = document.getElementById(`material_stock_${rowIndex}`);
        if (stockEl) { stockEl.textContent = '-'; stockEl.className = ''; }
    }
}

// تحديث الإجماليات
function updateTotals() {
    const rows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');
    let totalItems = rows.length;

    document.getElementById('total-items').textContent = totalItems;
}

// البحث السريع في المواد (الشريط الجانبي)
function initializeMaterialSearch() {
    const materialSearch = document.getElementById('material-search');
    const materialSuggestions = document.getElementById('material-suggestions');

    materialSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        if (searchTerm.length < 2) {
            materialSuggestions.innerHTML = '';
            return;
        }

        const filteredMaterials = materialsData.filter(material =>
            material.item_number.toLowerCase().includes(searchTerm) ||
            material.description.toLowerCase().includes(searchTerm)
        ).slice(0, 10);

        materialSuggestions.innerHTML = filteredMaterials.map(material => `
            <a href="#" class="list-group-item list-group-item-action"
               onclick="selectMaterialFromSidebar(${material.id}); return false;">
                <strong>${material.item_number}</strong><br>
                <small>${material.description}</small><br>
                <small class="text-muted">الوحدة: ${material.unit || 'غير محدد'}</small>
            </a>
        `).join('');
    });
}

// اختيار مادة من الشريط الجانبي
function selectMaterialFromSidebar(materialId) {
    // إضافة صف جديد إذا لم يكن هناك صفوف فارغة
    const emptyRows = document.querySelectorAll('select[name*="material_id"] option:checked[value=""]');
    if (emptyRows.length === 0) {
        addMaterialRow();
    }

    // تحديد المادة في آخر صف
    const lastRow = document.querySelector('#materialsTableBody tr:last-child');
    if (lastRow) {
        const select = lastRow.querySelector('select[name*="material_id"]');
        const searchInput = lastRow.querySelector('.material-search-input');

        if (select && searchInput) {
            select.value = materialId;
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption) {
                searchInput.value = selectedOption.dataset.code;
                searchInput.classList.add('selected-item');
            }
            select.dispatchEvent(new Event('change'));
        }
    }

    // مسح البحث
    document.getElementById('material-search').value = '';
    document.getElementById('material-suggestions').innerHTML = '';
}

// البحث في المواد داخل الصف
function searchMaterialInRow(input, rowIndex) {
    const searchTerm = input.value.toLowerCase();
    const dropdownContainer = document.querySelector(`.material-dropdown-${rowIndex}`);

    if (searchTerm.length < 1) {
        dropdownContainer.innerHTML = '';
        dropdownContainer.classList.remove('show');
        input.classList.remove('selected-item');
        return;
    }

    const filteredMaterials = materialsData.filter(material =>
        material.item_number.toLowerCase().includes(searchTerm) ||
        material.description.toLowerCase().includes(searchTerm)
    ).slice(0, 10);

    if (filteredMaterials.length > 0) {
        dropdownContainer.innerHTML = filteredMaterials.map(material => `
            <div class="dropdown-item-custom" onclick="selectMaterialInRow(${material.id}, ${rowIndex}, '${material.item_number}')">
                <div class="item-number">${material.item_number}</div>
                <div class="item-description">${material.description}</div>
                <small class="text-muted">الوحدة: ${material.unit || 'غير محدد'}</small>
            </div>
        `).join('');
        dropdownContainer.classList.add('show');
    } else {
        dropdownContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
        dropdownContainer.classList.add('show');
    }
}

// اختيار مادة في الصف
function selectMaterialInRow(materialId, rowIndex, itemNumber) {
    const input = document.querySelector(`input[onkeyup*="${rowIndex}"]`);
    const select = document.querySelector(`select[name="materials[${rowIndex}][material_id]"]`);
    const dropdownContainer = document.querySelector(`.material-dropdown-${rowIndex}`);

    if (input && select) {
        input.value = itemNumber;
        input.classList.add('selected-item');
        select.value = materialId;
        dropdownContainer.classList.remove('show');
        select.dispatchEvent(new Event('change'));
    }
}

// حذف الطلب
function deleteRequest() {
    if (confirm('هل أنت متأكد من حذف طلب الصرف؟ هذا الإجراء لا يمكن التراجع عنه.')) {
        window.location.href = 'delete.php?id=<?= $request['id'] ?>';
    }
}

// تحميل المواد من شهادة الإنجاز
async function loadFromCompletionCertificate(silent = false) {
    const workOrderSelect = document.getElementById('work_order_id');
    const workOrderId = workOrderSelect ? workOrderSelect.value : <?= $request['work_order_id'] ?>;

    if (!workOrderId) {
        if (!silent) {
            alert('يرجى اختيار أمر العمل أولاً');
        }
        return;
    }

    const loadBtn = document.getElementById('loadFromCertificateBtn');
    const originalText = loadBtn ? loadBtn.innerHTML : '';

    try {
        // تعطيل الزر وإظهار التحميل (إذا لم يكن تحميل صامت)
        if (!silent && loadBtn) {
            loadBtn.disabled = true;
            loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التحميل...';
        }

        // محاولة استخدام API الأساسي أولاً، ثم المبسط كبديل
        let response;
        try {
            response = await fetch(`get-completion-certificate-materials.php?work_order_id=${workOrderId}`);
        } catch (error) {
            console.log('فشل API الأساسي، جاري المحاولة مع API المبسط...');
            response = await fetch(`get-materials-simple.php?work_order_id=${workOrderId}`);
        }

        // قراءة الاستجابة حتى لو كانت خطأ
        const responseText = await response.text();
        console.log('API Response Status:', response.status);
        console.log('API Response:', responseText);

        // التحقق من حالة الاستجابة
        if (!response.ok) {
            // محاولة تحليل رسالة الخطأ من JSON
            try {
                const errorData = JSON.parse(responseText);
                const errorMessage = errorData.message || `HTTP error! status: ${response.status}`;

                // عرض رسالة مناسبة حسب نوع الخطأ
                if (errorMessage.includes('لا توجد شهادات إنجاز')) {
                    showAlert('لا توجد شهادات إنجاز (جاري الإعداد أو مكتملة) لهذا أمر العمل. يرجى إنشاء شهادة إنجاز أولاً.', 'warning');
                    return;
                } else if (errorMessage.includes('لا توجد مواد')) {
                    showAlert('لا توجد مواد في شهادات الإنجاز لهذا أمر العمل.', 'info');
                    return;
                }

                throw new Error(errorMessage);
            } catch (parseError) {
                throw new Error(`HTTP error! status: ${response.status} - ${responseText}`);
            }
        }

        // محاولة تحليل JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('JSON Parse Error:', jsonError);
            console.error('Response Text:', responseText);
            throw new Error('استجابة غير صالحة من الخادم. يرجى المحاولة مرة أخرى.');
        }

        if (!data.success) {
            throw new Error(data.message || 'فشل في تحميل البيانات');
        }

        if (data.materials.length === 0) {
            alert('لا توجد مواد في شهادات الإنجاز لهذا أمر العمل');
            return;
        }

        // تأكيد التحميل
        const confirmMessage = `تم العثور على ${data.materials.length} مادة في ${data.certificates.length} شهادة إنجاز.\n\nهل تريد تحميل هذه المواد؟ سيتم استبدال المواد الحالية.`;

        if (!confirm(confirmMessage)) {
            return;
        }

        // مسح المواد الحالية
        clearCurrentMaterials();

        // إضافة المواد الجديدة
        data.materials.forEach((material, index) => {
            addMaterialRow();
            const rowIndex = materialRowIndex;

            // تحديد المادة في القائمة المنسدلة
            const select = document.querySelector(`select[name="materials[${rowIndex}][material_id]"]`);
            const searchInput = document.querySelector(`input[onkeyup*="${rowIndex}"]`);
            const quantityInput = document.querySelector(`input[name="materials[${rowIndex}][quantity]"]`);

            if (select && searchInput && quantityInput) {
                select.value = material.material_id;
                searchInput.value = material.item_number;
                searchInput.classList.add('selected-item');

                // تحديث معلومات المادة مباشرة
                document.getElementById(`material_description_${rowIndex}`).textContent = material.description || '-';
                document.getElementById(`material_unit_${rowIndex}`).textContent = material.unit || '-';

                // تعبئة الكمية من المقايسة
                quantityInput.value = material.estimated_quantity;
            }
        });

        if (!silent) {
            showAlert(`تم تحميل ${data.materials.length} مادة من شهادات الإنجاز بنجاح`, 'success');
        }

    } catch (error) {
        console.error('خطأ في تحميل المواد:', error);
        if (!silent) {
            alert('حدث خطأ أثناء تحميل المواد: ' + error.message);
        }
    } finally {
        // إعادة تفعيل الزر (إذا لم يكن تحميل صامت)
        if (!silent && loadBtn) {
            loadBtn.disabled = false;
            loadBtn.innerHTML = originalText;
        }
    }
}

// مسح المواد الحالية
function clearCurrentMaterials() {
    const tbody = document.getElementById('materialsTableBody');
    tbody.innerHTML = `
        <tr id="noMaterialsRow">
            <td colspan="8" class="text-center text-muted py-3">
                <i class="fas fa-info-circle me-2"></i>
                اضغط على "إضافة مادة" لبدء إضافة المواد المطلوبة.
            </td>
        </tr>
    `;
    materialRowIndex = 0;
    updateTotals();
}

// تنظيف الصفوف الفارغة قبل الإرسال
function cleanupEmptyRows() {
    const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');

    materialRows.forEach(row => {
        const materialSelect = row.querySelector('select[name*="material_id"]');
        const quantityInput = row.querySelector('input[name*="quantity"]');
        const searchInput = row.querySelector('.material-search-input');

        // إزالة الصف إذا كان فارغاً أو غير مكتمل
        if (!materialSelect || !quantityInput || !searchInput ||
            !materialSelect.value || !quantityInput.value || quantityInput.value <= 0) {
            row.remove();
        }
    });
}

// عرض رسالة تنبيه
function showAlert(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // إضافة التنبيه في أعلى الصفحة
    const container = document.querySelector('.container-fluid');
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = alertHtml;
    container.insertBefore(tempDiv.firstElementChild, container.firstElementChild);
}

// التحقق من صحة النموذج
document.getElementById('materialRequestForm').addEventListener('submit', function(e) {
    const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');

    if (materialRows.length === 0) {
        e.preventDefault();
        alert('يجب إضافة مادة واحدة على الأقل');
        return;
    }

    let hasValidMaterial = false;
    materialRows.forEach(row => {
        const materialSelect = row.querySelector('select[name*="material_id"]');
        const quantityInput = row.querySelector('input[name*="quantity"]');
        const searchInput = row.querySelector('.material-search-input');

        if (materialSelect && quantityInput && searchInput) {
            if (materialSelect.value && quantityInput.value > 0) {
                hasValidMaterial = true;
            }
        }
    });

    if (!hasValidMaterial) {
        e.preventDefault();
        alert('يجب إضافة مادة واحدة صحيحة على الأقل');
        return;
    }

    // إزالة الصفوف الفارغة قبل الإرسال
    cleanupEmptyRows();

    // حفظ قيمة الإجراء في حقل مخفي قبل تعطيل الأزرار
    const clickedButton = e.submitter;
    if (clickedButton && clickedButton.name === 'action') {
        let hiddenAction = this.querySelector('input[name="action"]');
        if (!hiddenAction) {
            hiddenAction = document.createElement('input');
            hiddenAction.type = 'hidden';
            hiddenAction.name = 'action';
            this.appendChild(hiddenAction);
        }
        hiddenAction.value = clickedButton.value;
    }

    // إظهار رسالة تحميل
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري الحفظ...';
    });
});

// تحميل المواد الحالية
document.addEventListener('DOMContentLoaded', function() {
    initializeMaterialSearch();

    if (currentMaterials.length > 0) {
        currentMaterials.forEach(material => {
            addMaterialRow(material);
        });
    } else {
        addMaterialRow();
    }

    // إضافة مستمع لتغيير أمر العمل للتحميل التلقائي
    const workOrderSelect = document.getElementById('work_order_id');
    if (workOrderSelect) {
        workOrderSelect.addEventListener('change', function() {
            if (this.value) {
                // تحميل تلقائي للمواد من شهادة الإنجاز بعد تأخير قصير
                setTimeout(() => {
                    loadFromCompletionCertificate(true); // true = تحميل صامت
                }, 500);
            }
        });
    }
});
</script>

<style>
/* تحسين مظهر الجدول */
.table-responsive {
    overflow: visible !important;
}

.materials-table td:first-child {
    overflow: visible !important;
    position: relative;
}

/* تحسين مظهر حقول البحث */
.material-search-input {
    border: 2px solid #e9ecef;
    transition: border-color 0.3s;
}

.material-search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.selected-item {
    background-color: #e7f3ff;
    border-color: #0d6efd;
    color: #0d6efd;
    font-weight: 600;
}

/* قائمة البحث المنسدلة */
.custom-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}

.custom-dropdown.show {
    display: block;
}

.dropdown-item-custom {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f8f9fa;
}

.dropdown-item-custom:hover {
    background-color: #f8f9fa;
}

.dropdown-item-custom:last-child {
    border-bottom: none;
}

.item-number {
    font-weight: 600;
    color: #0d6efd;
}

.item-description {
    font-size: 0.875rem;
    color: #6c757d;
}

/* تحسين مظهر الجدول */
.materials-table thead th {
    background-color: #0d6efd !important;
    color: white;
    font-weight: 600;
    text-align: center;
    vertical-align: middle;
}

.materials-table tbody td {
    vertical-align: middle;
    text-align: center;
}

.materials-table tbody td:nth-child(2) {
    text-align: right;
}
</style>

</div> <!-- إنهاء container-fluid -->

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
