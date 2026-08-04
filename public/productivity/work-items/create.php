<?php
/**
 * إضافة بند إنتاجية جديد
 * Create New Productivity Work Item
 */

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/ProductivityWorkItem.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('productivity_work_items_create')) {
    header('Location: ' . path('dashboard.php?error=no_permission'));
    exit();
}

$pageTitle = 'إضافة بند إنتاجية جديد';
$currentPage = 'productivity-work-items';

// إنشاء كائن النموذج
$workItemModel = new ProductivityWorkItem();

// متغيرات النموذج
$formData = [
    'work_order_id' => $_GET['work_order_id'] ?? '',
    'work_item_id' => '',
    'target_quantity' => '',
    'unit_price' => '',
    'start_date' => '',
    'target_end_date' => '',
    'status' => 'active',
    'priority' => 'medium',
    'notes' => ''
];

$errors = [];
$success = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'work_order_id' => $_POST['work_order_id'] ?? '',
        'work_item_id' => $_POST['work_item_id'] ?? '',
        'target_quantity' => $_POST['target_quantity'] ?? '',
        'unit_price' => $_POST['unit_price'] ?? '',
        'start_date' => $_POST['start_date'] ?? '',
        'target_end_date' => $_POST['target_end_date'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'priority' => $_POST['priority'] ?? 'medium',
        'notes' => $_POST['notes'] ?? '',
        'created_by' => $_SESSION['user_id']
    ];
    
    // التحقق من صحة البيانات
    $errors = $workItemModel->validate($formData);
    
    // التحقق من عدم وجود بند مكرر
    if (empty($errors)) {
        $db = getDB();
        $duplicateCheck = $db->prepare("
            SELECT COUNT(*) FROM productivity_work_items 
            WHERE work_order_id = ? AND work_item_id = ?
        ");
        $duplicateCheck->execute([$formData['work_order_id'], $formData['work_item_id']]);
        
        if ($duplicateCheck->fetchColumn() > 0) {
            $errors[] = 'هذا البند موجود مسبقاً في أمر العمل المحدد';
        }
    }
    
    // إنشاء البند إذا لم توجد أخطاء
    if (empty($errors)) {
        $newId = $workItemModel->create($formData);
        
        if ($newId) {
            $success = 'تم إضافة بند الإنتاجية بنجاح';
            // إعادة تعيين النموذج
            $formData = [
                'work_order_id' => $formData['work_order_id'], // الاحتفاظ بأمر العمل
                'work_item_id' => '',
                'target_quantity' => '',
                'unit_price' => '',
                'start_date' => '',
                'target_end_date' => '',
                'status' => 'active',
                'priority' => 'medium',
                'notes' => ''
            ];
        } else {
            $errors[] = 'حدث خطأ أثناء إضافة البند';
        }
    }
}

// جلب أوامر العمل النشطة
$db = getDB();
$workOrdersQuery = "
    SELECT wo.id, wo.work_order_number, b.name as branch_name, wo.estimated_value
    FROM work_orders wo
    JOIN branches b ON wo.branch_id = b.id
    WHERE wo.status = 'active'
";

// تطبيق فلتر الفرع حسب الصلاحيات
$workOrdersParams = [];
if (!hasPermission('productivity_daily_logs_view_all_branches') && isset($_SESSION['branch_id'])) {
    $workOrdersQuery .= " AND wo.branch_id = ?";
    $workOrdersParams[] = $_SESSION['branch_id'];
}

$workOrdersQuery .= " ORDER BY wo.work_order_number";

$workOrdersStmt = $db->prepare($workOrdersQuery);
$workOrdersStmt->execute($workOrdersParams);
$workOrders = $workOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

// بنود الأعمال سيتم جلبها عبر AJAX بناءً على العقد
$workItems = [];

// التحقق من أمر العمل المحدد مسبقاً
$preselectedWorkOrderId = $_GET['work_order_id'] ?? null;
$preselectedWorkOrder = null;
if ($preselectedWorkOrderId) {
    foreach ($workOrders as $wo) {
        if ($wo['id'] == $preselectedWorkOrderId) {
            $preselectedWorkOrder = $wo;
            break;
        }
    }
}

// بدء تخزين المحتوى
ob_start();
?>
    <!-- عنوان الصفحة -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus text-primary"></i>
                إضافة بند إنتاجية جديد
            </h1>
            <?php if ($preselectedWorkOrder): ?>
                <p class="text-muted mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    أمر العمل: <?= htmlspecialchars($preselectedWorkOrder['work_order_number']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-building me-2"></i>
                    <?= htmlspecialchars($preselectedWorkOrder['branch_name']) ?>
                </p>
            <?php endif; ?>
        </div>
        <div>
            <a href="index.php<?= $preselectedWorkOrderId ? '?work_order_id=' . $preselectedWorkOrderId : '' ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة للقائمة
            </a>
        </div>
    </div>

    <!-- عرض الرسائل -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- نموذج الإضافة -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-edit"></i>
                بيانات بند الإنتاجية
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" id="createForm">
                <div class="row">
                    <!-- أمر العمل -->
                    <div class="col-md-6 mb-3">
                        <label for="work_order_id" class="form-label">
                            أمر العمل <span class="text-danger">*</span>
                        </label>
                        <div class="work-order-search-container position-relative">
                            <input type="text" class="form-control" id="work_order_search"
                                   placeholder="ابحث عن أمر العمل برقم الأمر أو اسم الفرع..."
                                   autocomplete="off">
                            <div id="work_order_dropdown" class="dropdown-menu w-100" style="max-height: 300px; overflow-y: auto;">
                                <!-- سيتم عرض نتائج البحث هنا -->
                            </div>
                        </div>
                        <input type="hidden" id="work_order_id" name="work_order_id" value="<?= htmlspecialchars($formData['work_order_id']) ?>" required>
                        <small class="form-text text-muted">
                            <i class="fas fa-search me-1"></i>
                            ابدأ الكتابة للبحث السريع في أوامر العمل
                        </small>
                        <?php if ($preselectedWorkOrder): ?>
                        <div class="mt-2">
                            <span class="badge bg-primary">
                                <i class="fas fa-clipboard-list me-1"></i>
                                <?= htmlspecialchars($preselectedWorkOrder['work_order_number']) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- بند العمل -->
                    <div class="col-md-6 mb-3">
                        <label for="work_item_id" class="form-label">
                            بند العمل <span class="text-danger">*</span>
                        </label>
                        <div class="work-item-search-container position-relative">
                            <input type="text" class="form-control" id="work_item_search"
                                   placeholder="ابحث عن بند العمل برقم البند أو الوصف..."
                                   autocomplete="off">
                            <div id="work_item_dropdown" class="dropdown-menu w-100" style="max-height: 300px; overflow-y: auto;">
                                <!-- سيتم عرض نتائج البحث هنا -->
                            </div>
                        </div>
                        <input type="hidden" id="work_item_id" name="work_item_id" value="<?= htmlspecialchars($formData['work_item_id']) ?>" required>
                        <small class="form-text text-muted">
                            <i class="fas fa-search me-1"></i>
                            ابدأ الكتابة للبحث السريع في بنود الأعمال
                        </small>
                    </div>
                </div>

                <div class="row">
                    <!-- الكمية المستهدفة -->
                    <div class="col-md-4 mb-3">
                        <label for="target_quantity" class="form-label">
                            الكمية المستهدفة <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="target_quantity" 
                                   name="target_quantity" step="0.001" min="0" required
                                   value="<?= htmlspecialchars($formData['target_quantity']) ?>">
                            <div class="input-group-append">
                                <span class="input-group-text" id="unit-display">وحدة</span>
                            </div>
                        </div>
                    </div>

                    <!-- سعر الوحدة -->
                    <div class="col-md-4 mb-3">
                        <label for="unit_price" class="form-label">
                            سعر الوحدة <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="unit_price" 
                                   name="unit_price" step="0.01" min="0" required
                                   value="<?= htmlspecialchars($formData['unit_price']) ?>">
                            <div class="input-group-append">
                                <span class="input-group-text">ريال</span>
                            </div>
                        </div>
                    </div>

                    <!-- القيمة الإجمالية -->
                    <div class="col-md-4 mb-3">
                        <label for="total_value" class="form-label">القيمة الإجمالية</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="total_value" readonly>
                            <div class="input-group-append">
                                <span class="input-group-text">ريال</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- تاريخ البداية -->
                    <div class="col-md-4 mb-3">
                        <label for="start_date" class="form-label">تاريخ البداية</label>
                        <input type="date" class="form-control" id="start_date" name="start_date"
                               value="<?= htmlspecialchars($formData['start_date']) ?>">
                    </div>

                    <!-- تاريخ الانتهاء المستهدف -->
                    <div class="col-md-4 mb-3">
                        <label for="target_end_date" class="form-label">تاريخ الانتهاء المستهدف</label>
                        <input type="date" class="form-control" id="target_end_date" name="target_end_date"
                               value="<?= htmlspecialchars($formData['target_end_date']) ?>">
                    </div>

                    <!-- الحالة -->
                    <div class="col-md-4 mb-3">
                        <label for="status" class="form-label">الحالة</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>نشط</option>
                            <option value="paused" <?= $formData['status'] === 'paused' ? 'selected' : '' ?>>متوقف</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- الأولوية -->
                    <div class="col-md-6 mb-3">
                        <label for="priority" class="form-label">الأولوية</label>
                        <select class="form-control" id="priority" name="priority">
                            <option value="low" <?= $formData['priority'] === 'low' ? 'selected' : '' ?>>منخفض</option>
                            <option value="medium" <?= $formData['priority'] === 'medium' ? 'selected' : '' ?>>متوسط</option>
                            <option value="high" <?= $formData['priority'] === 'high' ? 'selected' : '' ?>>عالي</option>
                            <option value="urgent" <?= $formData['priority'] === 'urgent' ? 'selected' : '' ?>>عاجل</option>
                        </select>
                    </div>
                </div>

                <!-- الملاحظات -->
                <div class="mb-3">
                    <label for="notes" class="form-label">الملاحظات</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"
                              placeholder="أي ملاحظات إضافية حول بند الإنتاجية"><?= htmlspecialchars($formData['notes']) ?></textarea>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ البند
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* تحسين مظهر البحث السريع */
.work-order-search-container,
.work-item-search-container {
    position: relative;
}

.work-order-search-container input,
.work-item-search-container input {
    border: 2px solid #e9ecef;
    transition: border-color 0.3s, box-shadow 0.3s;
    direction: rtl;
    text-align: right;
}

.work-order-search-container input:focus,
.work-item-search-container input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    outline: none;
}

/* تحسين مظهر القوائم المنسدلة */
.dropdown-menu {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    z-index: 1050;
    direction: rtl;
    text-align: right;
}

.dropdown-menu .dropdown-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.15s ease-in-out;
    cursor: pointer;
    text-align: right;
    direction: rtl;
}

.dropdown-menu .dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-menu .dropdown-item:hover {
    background-color: #f8f9fa;
    color: #495057;
}

.dropdown-menu .dropdown-item.active,
.dropdown-menu .dropdown-item:active {
    background-color: #0d6efd;
    color: #fff;
}

.dropdown-menu .dropdown-item-text {
    padding: 1rem;
    color: #6c757d;
    text-align: center;
}

/* تحسين النص المساعد */
.form-text.text-muted {
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.form-text.text-muted i {
    color: #28a745;
}

/* تحسين مظهر الشارات */
.badge {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
}

/* تحسين مظهر النتائج */
.search-result-item {
    display: block;
    padding: 0.75rem 1rem;
    margin-bottom: 0;
    background-color: transparent;
    border: 0;
    border-bottom: 1px solid #dee2e6;
    text-decoration: none;
    color: #495057;
    transition: all 0.15s ease-in-out;
}

.search-result-item:hover {
    background-color: #f8f9fa;
    color: #495057;
    text-decoration: none;
}

.search-result-item:focus {
    background-color: #e9ecef;
    color: #495057;
    text-decoration: none;
    outline: 0;
}

.search-result-item .item-number {
    font-weight: 600;
    color: #0d6efd;
}

.search-result-item .item-description {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

.search-result-item .item-details {
    font-size: 0.75rem;
    color: #adb5bd;
    margin-top: 0.25rem;
}

/* تحسين مظهر الحقول المطلوبة */
.form-control:invalid {
    border-color: #dc3545;
}

.form-control:valid {
    border-color: #28a745;
}

/* تحسين الاستجابة للشاشات الصغيرة */
@media (max-width: 768px) {
    .dropdown-menu {
        max-height: 250px;
    }

    .search-result-item {
        padding: 0.5rem 0.75rem;
    }

    .search-result-item .item-description {
        font-size: 0.8rem;
    }
}
</style>

<script>
// بيانات أوامر العمل وبنود الأعمال للبحث السريع
const workOrdersData = <?= json_encode($workOrders) ?>;
let workItemsData = []; // سيتم تعبئتها عبر AJAX

document.addEventListener('DOMContentLoaded', function() {
    console.log('تهيئة البحث السريع');

    // تهيئة البحث السريع
    initializeQuickSearch();

    // تهيئة عناصر النموذج
    initializeFormElements();
});

// دالة تهيئة البحث السريع
function initializeQuickSearch() {
    // تهيئة البحث في أوامر العمل
    initializeWorkOrderSearch();

    // تهيئة البحث في بنود الأعمال
    initializeWorkItemSearch();
}

// تهيئة البحث في أوامر العمل
function initializeWorkOrderSearch() {
    const searchInput = document.getElementById('work_order_search');
    const dropdown = document.getElementById('work_order_dropdown');
    const hiddenInput = document.getElementById('work_order_id');

    if (!searchInput || !dropdown || !hiddenInput) return;

    // إذا كان هناك قيمة محددة مسبقاً، اعرضها
    if (hiddenInput.value) {
        const selectedOrder = workOrdersData.find(wo => wo.id == hiddenInput.value);
        if (selectedOrder) {
            searchInput.value = `${selectedOrder.work_order_number} - ${selectedOrder.branch_name}`;
        }
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        searchWorkOrders(searchTerm, dropdown, hiddenInput, searchInput);
    });

    // إخفاء القائمة عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

// تهيئة البحث في بنود الأعمال
function initializeWorkItemSearch() {
    const searchInput = document.getElementById('work_item_search');
    const dropdown = document.getElementById('work_item_dropdown');
    const hiddenInput = document.getElementById('work_item_id');

    if (!searchInput || !dropdown || !hiddenInput) return;

    // إذا كان هناك قيمة محددة مسبقاً، اعرضها
    if (hiddenInput.value) {
        const selectedItem = workItemsData.find(wi => wi.id == hiddenInput.value);
        if (selectedItem) {
            searchInput.value = `${selectedItem.item_number} - ${selectedItem.description.substring(0, 30)}...`;
        }
    }

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        searchWorkItems(searchTerm, dropdown, hiddenInput, searchInput);
    });

    // إخفاء القائمة عند النقر خارجها
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
}

// دالة البحث في أوامر العمل
function searchWorkOrders(searchTerm, dropdown, hiddenInput, searchInput) {
    if (searchTerm.length < 2) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        return;
    }

    // فلترة أوامر العمل
    const filteredOrders = workOrdersData.filter(order =>
        order.work_order_number.toLowerCase().includes(searchTerm) ||
        order.branch_name.toLowerCase().includes(searchTerm)
    );

    if (filteredOrders.length === 0) {
        dropdown.innerHTML = `
            <div class="dropdown-item-text text-center text-muted py-3">
                <i class="fas fa-search me-1"></i>
                لا توجد أوامر عمل تطابق البحث
            </div>
        `;
    } else {
        dropdown.innerHTML = filteredOrders.map(order => `
            <a href="#" class="dropdown-item search-result-item"
               onclick="selectWorkOrder(${order.id}, '${order.work_order_number}', '${order.branch_name}'); return false;">
                <div class="item-number">${order.work_order_number}</div>
                <div class="item-description">${order.branch_name}</div>
                <div class="item-details">القيمة المقدرة: ${parseFloat(order.estimated_value).toLocaleString('ar-SA')} ريال</div>
            </a>
        `).join('');
    }

    dropdown.classList.add('show');
}

// دالة البحث في بنود الأعمال
function searchWorkItems(searchTerm, dropdown, hiddenInput, searchInput) {
    if (searchTerm.length < 2) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        return;
    }

    // فلترة بنود الأعمال
    const filteredItems = workItemsData.filter(item =>
        item.item_number.toLowerCase().includes(searchTerm) ||
        item.description.toLowerCase().includes(searchTerm)
    );

    if (filteredItems.length === 0) {
        dropdown.innerHTML = `
            <div class="dropdown-item-text text-center text-muted py-3">
                <i class="fas fa-search me-1"></i>
                لا توجد بنود أعمال تطابق البحث
            </div>
        `;
    } else {
        dropdown.innerHTML = filteredItems.map(item => `
            <a href="#" class="dropdown-item search-result-item"
               onclick="selectWorkItem(${item.id}, '${item.item_number}', '${item.description.replace(/'/g, "\\'")}', '${item.unit}', ${item.price}); return false;">
                <div class="item-number">${item.item_number}</div>
                <div class="item-description">${item.description.substring(0, 60)}${item.description.length > 60 ? '...' : ''}</div>
                <div class="item-details">السعر: ${parseFloat(item.price).toLocaleString('ar-SA')} ريال/${item.unit}</div>
            </a>
        `).join('');
    }

    dropdown.classList.add('show');
}

// دالة اختيار أمر العمل
function selectWorkOrder(id, workOrderNumber, branchName) {
    const searchInput = document.getElementById('work_order_search');
    const hiddenInput = document.getElementById('work_order_id');
    const dropdown = document.getElementById('work_order_dropdown');

    searchInput.value = `${workOrderNumber} - ${branchName}`;
    hiddenInput.value = id;
    dropdown.classList.remove('show');

    // جلب بنود الأعمال المرتبطة بعقد أمر العمل
    loadContractWorkItems(id);

    console.log('تم اختيار أمر العمل:', workOrderNumber);
}

// جلب بنود العقد الخاص بأمر العمل
function loadContractWorkItems(workOrderId) {
    fetch(`../../contracts/get-work-items-ajax.php?work_order_id=${workOrderId}`)
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                workItemsData = res.data;
                const searchInput = document.getElementById('work_item_search');
                const hiddenInput = document.getElementById('work_item_id');
                if (searchInput) searchInput.value = '';
                if (hiddenInput) hiddenInput.value = '';
            } else {
                console.error("خطأ في جلب البنود:", res.message);
                workItemsData = [];
            }
        })
        .catch(err => {
            console.error("Network error:", err);
            workItemsData = [];
        });
}

// دالة اختيار بند العمل
function selectWorkItem(id, itemNumber, description, unit, standardPrice) {
    const searchInput = document.getElementById('work_item_search');
    const hiddenInput = document.getElementById('work_item_id');
    const dropdown = document.getElementById('work_item_dropdown');

    searchInput.value = `${itemNumber} - ${description.substring(0, 30)}...`;
    hiddenInput.value = id;
    dropdown.classList.remove('show');

    // تحديث وحدة القياس وسعر الوحدة
    const unitDisplay = document.getElementById('unit-display');
    const unitPriceInput = document.getElementById('unit_price');

    if (unitDisplay) unitDisplay.textContent = unit || 'وحدة';
    if (unitPriceInput) unitPriceInput.value = standardPrice || '';

    // حساب القيمة الإجمالية
    calculateTotalValue();

    console.log('تم اختيار بند العمل:', itemNumber);
}

// دالة تهيئة عناصر النموذج
function initializeFormElements() {
    setupCalculationEvents();
    updateInitialValues();
    setupKeyboardShortcuts();
}

// حساب القيمة الإجمالية
function calculateTotalValue() {
    const targetQuantityInput = document.getElementById('target_quantity');
    const unitPriceInput = document.getElementById('unit_price');
    const totalValueInput = document.getElementById('total_value');

    if (!targetQuantityInput || !unitPriceInput || !totalValueInput) return;

    const quantity = parseFloat(targetQuantityInput.value) || 0;
    const price = parseFloat(unitPriceInput.value) || 0;
    const total = quantity * price;

    totalValueInput.value = total.toLocaleString('ar-SA', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ربط الأحداث لحساب القيمة الإجمالية
function setupCalculationEvents() {
    const targetQuantityInput = document.getElementById('target_quantity');
    const unitPriceInput = document.getElementById('unit_price');

    if (targetQuantityInput) {
        targetQuantityInput.addEventListener('input', calculateTotalValue);
    }
    if (unitPriceInput) {
        unitPriceInput.addEventListener('input', calculateTotalValue);
    }
}

// تحديث القيم عند تحميل الصفحة
function updateInitialValues() {
    calculateTotalValue();
}

// إضافة اختصارات لوحة المفاتيح للبحث السريع
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl + F للبحث في أمر العمل
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            const workOrderSearch = document.getElementById('work_order_search');
            if (workOrderSearch) workOrderSearch.focus();
        }
        // Ctrl + I للبحث في بند العمل
        if (e.ctrlKey && e.key === 'i') {
            e.preventDefault();
            const workItemSearch = document.getElementById('work_item_search');
            if (workItemSearch) workItemSearch.focus();
        }
    });
}

// التحقق من التواريخ
function setupDateValidation() {
    const startDateInput = document.getElementById('start_date');
    const targetEndDateInput = document.getElementById('target_end_date');

    if (!startDateInput || !targetEndDateInput) return;

    function validateDates() {
        if (startDateInput.value && targetEndDateInput.value) {
            if (new Date(startDateInput.value) > new Date(targetEndDateInput.value)) {
                targetEndDateInput.setCustomValidity('تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية');
            } else {
                targetEndDateInput.setCustomValidity('');
            }
        }
    }

    startDateInput.addEventListener('change', validateDates);
    targetEndDateInput.addEventListener('change', validateDates);
}

// تشغيل التحقق من التواريخ
setupDateValidation();
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>


