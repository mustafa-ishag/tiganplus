<?php
/**
 * صفحة إنشاء / تعديل عملية مواد مزالة
 * Create / Edit Removed Material Transaction
 */

// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/RemovedMaterial.php';
require_once __DIR__ . '/../../../models/Material.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('/auth/login.php');
}

if (!hasPermission('removed_materials_create') && !hasPermission('inventory_access')) {
    setAlert('ليس لديك صلاحية لإنشاء عملية مواد مزالة', 'error');
    redirect('/dashboard.php');
}

$currentPage = 'removed-materials';

$removedMaterial = new RemovedMaterial();
$materialModel = new Material();
$db = getDB();

$editId = $_GET['edit'] ?? null;
$editMode = false;
$editData = null;

// تحميل بيانات التعديل
if ($editId) {
    $editData = $removedMaterial->getTransactionWithDetails($editId);
    if (!$editData || $editData['status'] !== 'pending') {
        header('Location: ' . path('inventory/removed-materials/index.php'));
        exit;
    }
    $editMode = true;
    $transactionType = $editData['transaction_type'];
    $pageTitle = 'تعديل عملية مواد مزالة - ' . $editData['transaction_number'];
} else {
    $transactionType = $_GET['type'] ?? 'incoming';
    if (!in_array($transactionType, ['incoming', 'outgoing'])) {
        $transactionType = 'incoming';
    }
    $pageTitle = $transactionType === 'incoming' ? 'إنشاء عملية وارد - مواد مزالة' : 'إنشاء عملية صادر - مواد مزالة';
}

// جلب أوامر العمل مع كود نوع أمر العمل
$workOrders = $db->query("
    SELECT wo.id, wo.work_order_number, wo.department, wo.location,
           b.name as branch_name,
           wot.type_code as work_order_type_code
    FROM work_orders wo
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    ORDER BY wo.work_order_number DESC
")->fetchAll(PDO::FETCH_ASSOC);

// جلب المواد
$materials = $materialModel->fetchAll("SELECT m.id, m.item_number, mc.description, mc.unit, mc.group_number FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.is_active = 1 ORDER BY m.item_number");

// معالجة النموذج
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'transaction_type' => $transactionType,
        'material_category' => $_POST['material_category'] ?? 'scrap', // Default
        'work_order_id' => $_POST['work_order_id'] ?? '',
        'branch_id' => $_SESSION['branch_id'] ?? 1,
        'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
        'destination' => $_POST['destination'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'created_by' => $_SESSION['user_id'],
    ];

    // التحقق
    if (empty($data['work_order_id'])) {
        $errors[] = 'يرجى اختيار أمر العمل';
    }
    if (empty($data['transaction_date'])) {
        $errors[] = 'يرجى تحديد تاريخ العملية';
    }
    if ($transactionType === 'outgoing' && empty($data['destination'])) {
        $errors[] = 'يرجى تحديد جهة التسليم';
    }

    // المواد
    $materialsData = [];
    $materialIds = $_POST['material_id'] ?? [];
    
    $hasItems = false;
    for ($i = 0; $i < count($materialIds); $i++) {
        $qty = $_POST['quantity'][$i] ?? 0;
        if (!empty($materialIds[$i]) && $qty > 0) {
            $hasItems = true;
            $materialsData[] = [
                'material_id' => $materialIds[$i],
                'quantity' => $qty,
                'item_type' => $_POST['item_type'][$i] ?? 'تشغيلي',
                'status' => $_POST['item_status'][$i] ?? 'تخريد',
                'disposal_reason' => $_POST['disposal_reason'][$i] ?? '',
                'material_condition' => $_POST['material_condition'][$i] ?? '',
                'remarks' => $_POST['remarks'][$i] ?? '',
                'functional_location' => $_POST['functional_location'][$i] ?? '',
                'equipment' => $_POST['equipment'][$i] ?? '',
                'capacity_kva' => $_POST['capacity_kva'][$i] ?? '',
                'manufacturer' => $_POST['manufacturer'][$i] ?? '',
                'prim_sec_volt' => $_POST['prim_sec_volt'][$i] ?? '',
                'manufacture_year' => $_POST['manufacture_year'][$i] ?? '',
                'serial_number' => $_POST['serial_number'][$i] ?? '',
                'images' => isset($_POST['images'][$i]) ? json_decode($_POST['images'][$i], true) : [],
                'notes' => ''
            ];
        }
    }

    if (!$hasItems) {
        $errors[] = 'يرجى إضافة مادة واحدة على الأقل';
    }

    if (empty($errors)) {
        if ($editMode) {
            unset($data['created_by']);
            $data['updated_at'] = getCurrentDateTime();
            $result = $removedMaterial->updateTransaction($editId, $data, $materialsData);
        } else {
            $result = $removedMaterial->createTransaction($data, $materialsData);
        }

        if ($result['success']) {
            $redirectId = $editMode ? $editId : $result['transaction_id'];
            header('Location: ' . path('inventory/removed-materials/view.php?id=' . $redirectId . '&msg=success'));
            exit;
        } else {
            $errors[] = $result['message'] ?? 'فشل في حفظ العملية';
        }
    }
}

ob_start();
?>

<style>
    .form-card {
        border-radius: 12px;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-card h5 {
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .type-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .type-incoming { background: #d4edda; color: #155724; }
    .type-outgoing { background: #cfe2ff; color: #084298; }

    /* Custom Dropdown for Search */
    .search-container { position: relative !important; }
    .custom-dropdown {
        position: absolute !important;
        top: 100% !important; left: 0 !important; right: 0 !important;
        z-index: 1050 !important;
        background-color: white !important;
        border: 1px solid #dee2e6 !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        max-height: 300px !important;
        overflow-y: auto !important;
        margin-top: 2px !important;
        display: none;
    }
    .custom-dropdown.show { display: block !important; }
    .dropdown-item-custom {
        padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f8f9fa; transition: background-color 0.2s;
    }
    .dropdown-item-custom:hover { background-color: #f8f9fa; }
    .dropdown-item-custom .item-number { font-weight: 600; color: #0d6efd; }
    .dropdown-item-custom .item-description { font-size: 0.85em; color: #6c757d; margin-top: 2px; }
    .selected-item { background-color: #e7f3ff; border-color: #0d6efd; color: #0d6efd; font-weight: 600; }

    /* Material Cards */
    .material-item-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fafafa;
        position: relative;
    }
    .remove-material-btn {
        position: absolute;
        top: 10px;
        left: 10px;
    }
    
    .capital-fields {
        display: none;
        background: #f0f7ff;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
        border: 1px solid #cce3f6;
    }
    
    .image-preview-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }
    .image-preview {
        position: relative;
        width: 80px;
        height: 80px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #ddd;
    }
    .image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .remove-image {
        position: absolute;
        top: 2px;
        right: 2px;
        background: rgba(255,0,0,0.8);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        cursor: pointer;
    }
</style>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="transactionForm">
    <!-- معلومات العملية -->
    <div class="form-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-info-circle me-2"></i> معلومات العملية
            </h5>
            <div class="type-indicator <?= $transactionType === 'incoming' ? 'type-incoming' : 'type-outgoing' ?>">
                <i class="fas <?= $transactionType === 'incoming' ? 'fa-arrow-down' : 'fa-arrow-up' ?>"></i>
                <?= $transactionType === 'incoming' ? 'عملية وارد (إرجاع مواد للمستودع)' : 'عملية صادر (تسليم مواد لجهة خارجية)' ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">أمر العمل <span class="text-danger">*</span></label>
                <div class="search-container">
                    <input type="text" class="form-control" id="work_order_search"
                           placeholder="ابحث عن أمر العمل..." autocomplete="off"
                           value="<?php
                               if ($editMode) {
                                   foreach ($workOrders as $wo) {
                                       if ($wo['id'] == $editData['work_order_id']) {
                                           echo htmlspecialchars($wo['work_order_number'] . ' (' . ($wo['work_order_type_code'] ?? '') . ') - ' . ($wo['branch_name'] ?? ''));
                                           break;
                                       }
                                   }
                               }
                           ?>">
                    <select name="work_order_id" id="work_order_id" class="form-select d-none" required>
                        <option value="">-- اختر أمر العمل --</option>
                        <?php foreach ($workOrders as $wo): ?>
                            <option value="<?= $wo['id'] ?>"
                                    data-number="<?= htmlspecialchars($wo['work_order_number']) ?>"
                                    data-type-code="<?= htmlspecialchars($wo['work_order_type_code'] ?? '') ?>"
                                    data-branch="<?= htmlspecialchars($wo['branch_name'] ?? '') ?>"
                                    data-department="<?= htmlspecialchars($wo['department'] ?? '') ?>"
                                    data-location="<?= htmlspecialchars($wo['location'] ?? '') ?>"
                                    <?= ($editMode && $editData['work_order_id'] == $wo['id']) || ($_POST['work_order_id'] ?? '') == $wo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wo['work_order_number']) ?> (<?= htmlspecialchars($wo['work_order_type_code'] ?? '') ?>) - <?= htmlspecialchars($wo['branch_name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="work_order_suggestions" class="custom-dropdown"></div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">تاريخ العملية <span class="text-danger">*</span></label>
                <input type="date" name="transaction_date" class="form-control" required
                    value="<?= $editMode ? $editData['transaction_date'] : ($_POST['transaction_date'] ?? date('Y-m-d')) ?>">
            </div>

            <?php if ($transactionType === 'outgoing'): ?>
                <div class="col-md-12">
                    <label class="form-label">جهة التسليم <span class="text-danger">*</span></label>
                    <input type="text" name="destination" class="form-control" placeholder="مثال: مستودع شركة الكهرباء / المقاول"
                        value="<?= htmlspecialchars($editMode ? ($editData['destination'] ?? '') : ($_POST['destination'] ?? '')) ?>">
                </div>
            <?php endif; ?>

            <div class="col-md-12">
                <label class="form-label">ملاحظات عامة</label>
                <textarea name="notes" class="form-control"
                    rows="2"><?= htmlspecialchars($editMode ? ($editData['notes'] ?? '') : ($_POST['notes'] ?? '')) ?></textarea>
            </div>
        </div>
    </div>

    <!-- المواد المزالة -->
    <div class="form-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="fas fa-boxes me-2"></i> تفاصيل المواد المزالة
            </h5>
            <button type="button" class="btn btn-success btn-sm" onclick="addMaterialCard()">
                <i class="fas fa-plus me-1"></i> إضافة مادة
            </button>
        </div>

        <div id="materialsContainer">
            <!-- سيتم إضافة المواد هنا -->
        </div>
    </div>

    <!-- أزرار الحفظ -->
    <div class="d-flex gap-2 justify-content-end mb-5">
        <a href="<?= path('inventory/removed-materials/index.php') ?>" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> إلغاء
        </a>
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save me-1"></i> <?= $editMode ? 'تحديث العملية' : 'حفظ واعتماد' ?>
        </button>
    </div>
</form>

<script>
    const materialsData = <?= json_encode($materials, JSON_UNESCAPED_UNICODE) ?>;
    const workOrdersData = <?= json_encode($workOrders, JSON_UNESCAPED_UNICODE) ?>;
    let cardCount = 0;

    document.addEventListener('DOMContentLoaded', function() {
        initializeWorkOrderSearch();

        <?php if ($editMode && !empty($editData['details'])): ?>
            <?php foreach ($editData['details'] as $detail): ?>
                <?php
                $imagesJson = $detail['images'] ?: '[]';
                if(is_string($imagesJson) && $imagesJson === '') $imagesJson = '[]';
                ?>
                addMaterialCard(<?= json_encode([
                    'material_id' => $detail['material_id'],
                    'quantity' => $detail['quantity'],
                    'item_type' => $detail['item_type'],
                    'status' => $detail['status'],
                    'disposal_reason' => $detail['disposal_reason'],
                    'material_condition' => $detail['material_condition'],
                    'remarks' => $detail['remarks'],
                    'functional_location' => $detail['functional_location'],
                    'equipment' => $detail['equipment'],
                    'capacity_kva' => $detail['capacity_kva'],
                    'manufacturer' => $detail['manufacturer'],
                    'prim_sec_volt' => $detail['prim_sec_volt'],
                    'manufacture_year' => $detail['manufacture_year'],
                    'serial_number' => $detail['serial_number'],
                    'images' => $imagesJson
                ], JSON_UNESCAPED_UNICODE) ?>);
            <?php endforeach; ?>
        <?php else: ?>
            addMaterialCard();
        <?php endif; ?>

        <?php if ($editMode): ?>
            document.getElementById('work_order_search').classList.add('selected-item');
        <?php endif; ?>
    });

    // ============ بحث أمر العمل ============
    function initializeWorkOrderSearch() {
        const searchInput = document.getElementById('work_order_search');
        const suggestionsContainer = document.getElementById('work_order_suggestions');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            this.classList.remove('selected-item');
            if (searchTerm.length < 1) {
                suggestionsContainer.innerHTML = '';
                suggestionsContainer.classList.remove('show');
                return;
            }
            const filteredOrders = workOrdersData.filter(order =>
                order.work_order_number.toLowerCase().includes(searchTerm) ||
                (order.branch_name && order.branch_name.toLowerCase().includes(searchTerm))
            ).slice(0, 10);

            if (filteredOrders.length > 0) {
                suggestionsContainer.innerHTML = filteredOrders.map(order => `
                    <div class="dropdown-item-custom" onclick="selectWorkOrder(${order.id}, '${escapeJs(order.work_order_number)}', '${escapeJs(order.branch_name || '')}', '${escapeJs(order.work_order_type_code || '')}')">
                        <div class="item-number">${order.work_order_number}</div>
                        <div class="item-description">${order.branch_name || ''}</div>
                    </div>
                `).join('');
                suggestionsContainer.classList.add('show');
            } else {
                suggestionsContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
                suggestionsContainer.classList.add('show');
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.remove('show');
            }
        });
    }

    let currentWorkOrderLocation = '';

    function selectWorkOrder(id, number, branch, typeCode) {
        const searchInput = document.getElementById('work_order_search');
        document.getElementById('work_order_id').value = id;
        searchInput.value = `${number} (${typeCode || ''}) - ${branch}`;
        searchInput.classList.add('selected-item');
        document.getElementById('work_order_suggestions').classList.remove('show');
        
        // تحديث موقع المعدة بناءً على أمر العمل
        const selectedOrder = workOrdersData.find(wo => wo.id == id);
        if (selectedOrder && selectedOrder.location) {
            currentWorkOrderLocation = selectedOrder.location;
            // تحديث الحقول الحالية إذا كانت فارغة
            const locationInputs = document.querySelectorAll('input[name="functional_location[]"]');
            locationInputs.forEach(input => {
                if (!input.value) {
                    input.value = currentWorkOrderLocation;
                }
            });
        } else {
            currentWorkOrderLocation = '';
        }
    }

    function escapeJs(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    // ============ كروت المواد ============
    function addMaterialCard(data = null) {
        cardCount++;
        const container = document.getElementById('materialsContainer');
        const currentCard = cardCount;
        
        const selectedMaterial = data ? materialsData.find(m => m.id == data.material_id) : null;
        const searchValue = selectedMaterial ? `${selectedMaterial.item_number} - ${selectedMaterial.description}` : '';

        const card = document.createElement('div');
        card.className = 'material-item-card';
        card.id = `card_${currentCard}`;
        
        let imagesHtml = '';
        let imagesJson = '[]';
        if(data && data.images) {
            try {
                let imgs = typeof data.images === 'string' ? JSON.parse(data.images) : data.images;
                if(Array.isArray(imgs)) {
                    imagesJson = JSON.stringify(imgs);
                    imgs.forEach((img, idx) => {
                        imagesHtml += `
                        <div class="image-preview" id="img_${currentCard}_${idx}">
                            <img src="${img}" alt="Preview">
                            <div class="remove-image" onclick="removeImage(${currentCard}, ${idx})"><i class="fas fa-times"></i></div>
                        </div>`;
                    });
                }
            } catch(e) {}
        }

        card.innerHTML = `
            <button type="button" class="btn btn-outline-danger btn-sm remove-material-btn" onclick="removeCard(${currentCard})">
                <i class="fas fa-trash"></i> إزالة المادة
            </button>
            <h6 class="mb-3 text-primary border-bottom pb-2">المادة #${currentCard}</h6>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">البحث عن المادة <span class="text-danger">*</span></label>
                    <div class="search-container">
                        <input type="text" class="form-control material-search-input" 
                               id="material_search_${currentCard}"
                               placeholder="ابحث برقم البند أو الوصف..."
                               autocomplete="off"
                               value="${searchValue}"
                               oninput="searchMaterial(this, ${currentCard})"
                               ${selectedMaterial ? 'class="form-control selected-item"' : ''} required>
                        <input type="hidden" name="material_id[]" id="material_id_${currentCard}" value="${data ? data.material_id : ''}">
                        <div id="material_suggestions_${currentCard}" class="custom-dropdown"></div>
                    </div>
                    <small class="text-muted mt-1 d-block">الوحدة: <span id="unit_${currentCard}" class="fw-bold text-dark">${selectedMaterial ? selectedMaterial.unit : '-'}</span></small>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">الكمية <span class="text-danger">*</span></label>
                    <input type="number" name="quantity[]" class="form-control" step="0.001" min="0.001" value="${data ? data.quantity : ''}" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">نوع المادة <span class="text-danger">*</span></label>
                    <select name="item_type[]" class="form-select" onchange="toggleCapitalFields(${currentCard}, this.value)" required>
                        <option value="تشغيلي" ${data && data.item_type === 'تشغيلي' ? 'selected' : ''}>تشغيلي</option>
                        <option value="رأس مالي" ${data && data.item_type === 'رأس مالي' ? 'selected' : ''}>رأس مالي</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">الحالة <span class="text-danger">*</span></label>
                    <select name="item_status[]" class="form-select" required>
                        <option value="تخريد" ${data && data.status === 'تخريد' ? 'selected' : ''}>تخريد</option>
                        <option value="إرجاع" ${data && data.status === 'إرجاع' ? 'selected' : ''}>إرجاع</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">سبب التخلص</label>
                    <input type="text" name="disposal_reason[]" class="form-control" placeholder="مثال: معطوب ومستهلك" value="${data ? (data.disposal_reason || '') : ''}">
                </div>
                
                <div class="col-md-5">
                    <label class="form-label">حالة المادة</label>
                    <input type="text" name="material_condition[]" class="form-control" placeholder="مثال: بحاجة إلى صيانة" value="${data ? (data.material_condition || '') : ''}">
                </div>

                <div class="col-md-12">
                    <label class="form-label">ملاحظات</label>
                    <input type="text" name="remarks[]" class="form-control" value="${data ? (data.remarks || '') : ''}">
                </div>
                
                <div class="col-md-12 capital-fields" id="capital_fields_${currentCard}" style="${data && data.item_type === 'رأس مالي' ? 'display:block;' : 'display:none;'}">
                    <h6 class="text-primary mb-3"><i class="fas fa-bolt me-1"></i> بيانات رأس المال (للمعدات)</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Functional Location (موقع المعدة)</label>
                            <input type="text" name="functional_location[]" class="form-control" value="${data ? (data.functional_location || '') : currentWorkOrderLocation}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Equipment</label>
                            <input type="text" name="equipment[]" class="form-control" value="${data ? (data.equipment || '') : ''}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Capacity (KVA)</label>
                            <input type="text" name="capacity_kva[]" class="form-control" value="${data ? (data.capacity_kva || '') : ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer[]" class="form-control" value="${data ? (data.manufacturer || '') : ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Prim/Sec Volt</label>
                            <input type="text" name="prim_sec_volt[]" class="form-control" value="${data ? (data.prim_sec_volt || '') : ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year</label>
                            <input type="number" name="manufacture_year[]" class="form-control" value="${data ? (data.manufacture_year || '') : ''}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" name="serial_number[]" class="form-control" value="${data ? (data.serial_number || '') : ''}">
                        </div>
                    </div>
                </div>

                <!-- الصور -->
                <div class="col-md-12 mt-3">
                    <label class="form-label">إرفاق صور للمادة <small class="text-muted">(اختياري)</small></label>
                    <input type="file" class="form-control" accept="image/*" multiple onchange="uploadImages(this, ${currentCard})">
                    <input type="hidden" name="images[]" id="images_data_${currentCard}" value='${imagesJson}'>
                    <div class="image-preview-container" id="image_preview_${currentCard}">
                        ${imagesHtml}
                    </div>
                </div>
            </div>
        `;

        container.appendChild(card);
        
        // Setup outside click for autocomplete
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById(`material_search_${currentCard}`);
            const suggestionsContainer = document.getElementById(`material_suggestions_${currentCard}`);
            if (searchInput && suggestionsContainer && !searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.classList.remove('show');
            }
        });
    }

    function removeCard(cardNumber) {
        const card = document.getElementById(`card_${cardNumber}`);
        if (card) card.remove();
    }

    function toggleCapitalFields(cardNumber, type) {
        const capitalFields = document.getElementById(`capital_fields_${cardNumber}`);
        if (type === 'رأس مالي') {
            capitalFields.style.display = 'block';
        } else {
            capitalFields.style.display = 'none';
        }
    }

    function searchMaterial(input, cardNumber) {
        const searchTerm = input.value.toLowerCase();
        const suggestionsContainer = document.getElementById(`material_suggestions_${cardNumber}`);

        if (searchTerm.length < 1) {
            suggestionsContainer.innerHTML = '';
            suggestionsContainer.classList.remove('show');
            return;
        }

        document.getElementById(`material_id_${cardNumber}`).value = '';
        input.classList.remove('selected-item');

        const filtered = materialsData.filter(m =>
            m.item_number.toLowerCase().includes(searchTerm) ||
            mc.description.toLowerCase().includes(searchTerm) ||
            (mc.group_number && mc.group_number.toLowerCase().includes(searchTerm))
        ).slice(0, 10);

        if (filtered.length > 0) {
            suggestionsContainer.innerHTML = filtered.map(m => `
                <div class="dropdown-item-custom" onclick="selectMaterial(${m.id}, '${escapeJs(m.item_number)}', '${escapeJs(mc.description)}', '${escapeJs(mc.unit)}', ${cardNumber})">
                    <div class="item-number">${m.item_number}</div>
                    <div class="item-description">${mc.description} (${mc.unit})</div>
                </div>
            `).join('');
            suggestionsContainer.classList.add('show');
        } else {
            suggestionsContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
            suggestionsContainer.classList.add('show');
        }
    }

    function selectMaterial(id, itemNumber, description, unit, cardNumber) {
        const searchInput = document.getElementById(`material_search_${cardNumber}`);
        const hiddenInput = document.getElementById(`material_id_${cardNumber}`);
        const unitSpan = document.getElementById(`unit_${cardNumber}`);

        searchInput.value = `${itemNumber} - ${description}`;
        searchInput.classList.add('selected-item');
        hiddenInput.value = id;
        unitSpan.textContent = unit;
        document.getElementById(`material_suggestions_${cardNumber}`).classList.remove('show');
    }

    // ============ رفع الصور ============
    async function uploadImages(inputElement, cardNumber) {
        const files = inputElement.files;
        if (!files || files.length === 0) return;

        const previewContainer = document.getElementById(`image_preview_${cardNumber}`);
        const dataInput = document.getElementById(`images_data_${cardNumber}`);
        
        let currentImages = [];
        try {
            currentImages = JSON.parse(dataInput.value || '[]');
        } catch(e) { currentImages = []; }

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch('upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    const idx = currentImages.length;
                    currentImages.push(result.url);
                    
                    const div = document.createElement('div');
                    div.className = 'image-preview';
                    div.id = `img_${cardNumber}_${idx}`;
                    div.innerHTML = `
                        <img src="${result.url}" alt="Preview">
                        <div class="remove-image" onclick="removeImage(${cardNumber}, ${idx})"><i class="fas fa-times"></i></div>
                    `;
                    previewContainer.appendChild(div);
                } else {
                    Swal.fire('خطأ', result.message, 'error');
                }
            } catch (error) {
                console.error("Upload error:", error);
                Swal.fire('خطأ', 'حدث خطأ أثناء رفع الصورة', 'error');
            }
        }
        
        dataInput.value = JSON.stringify(currentImages);
        inputElement.value = ''; // Reset input
    }

    window.removeImage = function(cardNumber, imageIndex) {
        const dataInput = document.getElementById(`images_data_${cardNumber}`);
        const imgDiv = document.getElementById(`img_${cardNumber}_${imageIndex}`);
        
        if (imgDiv) imgDiv.remove();
        
        try {
            let currentImages = JSON.parse(dataInput.value || '[]');
            currentImages[imageIndex] = null; // Mark as deleted to keep indices for other nodes
            dataInput.value = JSON.stringify(currentImages.filter(x => x !== null));
        } catch(e) {}
        
        // Re-render to fix indices? For simplicity, we just filter it.
        // In a real app we might re-render all thumbnails, but this works for simple usage.
    }

    // التحقق قبل الحفظ
    document.getElementById('transactionForm').addEventListener('submit', function (e) {
        const workOrderId = document.getElementById('work_order_id').value;
        if (!workOrderId) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى اختيار أمر العمل', confirmButtonText: 'حسناً' });
            return;
        }

        const cards = document.querySelectorAll('.material-item-card');
        let hasValidRow = false;
        cards.forEach(card => {
            const matId = card.querySelector('input[name="material_id[]"]').value;
            const qty = card.querySelector('input[name="quantity[]"]').value;
            if (matId && qty > 0) hasValidRow = true;
        });

        if (!hasValidRow) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'تنبيه', text: 'يرجى إضافة مادة واحدة على الأقل وتحديد الكمية بشكل صحيح', confirmButtonText: 'حسناً' });
        }
    });
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../includes/layout.php';
?>