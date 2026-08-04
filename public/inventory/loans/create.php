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
require_once __DIR__ . '/../../../models/Material.php';

if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

$materialModel = new Material();

// ===== AJAX: إنشاء مادة من الكتالوج =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'create_from_catalog') {
    header('Content-Type: application/json');
    $catalogId = (int)($_POST['catalog_id'] ?? 0);
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

$loanModel = new InventoryLoan();
$clientModel = new InventoryClient();

$clients = $clientModel->getAllClients();

// الحصول على المواد النشطة
$materials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description, mc.unit, m.current_stock 
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE m.is_active = 1 
     ORDER BY m.item_number"
);

// جلب مواد الكتالوج غير الموجودة في المستودع
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $clientId = $_POST['client_id'] ?? '';
    $receiverName = trim($_POST['receiver_name'] ?? '');
    $receiverIdentity = trim($_POST['receiver_identity'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($type) || !in_array($type, ['borrow', 'lend'])) {
        $errors[] = 'يرجى تحديد نوع السلفة';
    }
    if (empty($clientId)) {
        $errors[] = 'يرجى اختيار المقاول/العميل';
    }
    if (empty($receiverName)) {
        $errors[] = 'اسم المستلم مطلوب';
    }
    if (empty($items) || !is_array($items)) {
        $errors[] = 'يجب إضافة بند واحد على الأقل';
    } else {
        foreach ($items as $index => $item) {
            if (empty($item['item_number']) || empty($item['description']) || empty($item['quantity'])) {
                $errors[] = "بيانات البند رقم " . ($index + 1) . " غير مكتملة";
            }
        }
    }

    if (empty($errors)) {
        $data = [
            'type' => $type,
            'client_id' => $clientId,
            'receiver_name' => $receiverName,
            'receiver_identity' => $receiverIdentity,
            'notes' => $notes,
            'created_by' => $_SESSION['user_id']
        ];

        // Format items to include material_id if present
        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItem = [
                'item_number' => $item['item_number'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?? ''
            ];
            if (!empty($item['material_id'])) {
                $formattedItem['material_id'] = $item['material_id'];
            }
            $formattedItems[] = $formattedItem;
        }

        $result = $loanModel->createLoan($data, $formattedItems);

        if ($result['success']) {
            // إرسال بريد إلكتروني
            try {
                require_once __DIR__ . '/../../../includes/EmailService.php';
                $emailService = new EmailService();
                $fullLoan = $loanModel->getLoanDetails($result['loan_id']);
                $emailService->sendLoanNotification($fullLoan);
            } catch (Exception $e) {
                error_log("Failed to send loan email: " . $e->getMessage());
            }

            setAlert('تم إنشاء السلفة بنجاح', 'success');
            redirect('view.php?id=' . $result['loan_id']);
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'إنشاء سلفة جديدة';
$currentPage = 'inventory_loans';

ob_start();
?>

<style>
    .custom-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        z-index: 1000;
        width: 100%;
        min-width: 300px;
        background-color: #fff;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        max-height: 250px;
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
        color: #176cb4;
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
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1"><i class="fas fa-plus-circle text-primary me-2"></i> إنشاء سلفة جديدة</h2>
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

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" id="loanForm">
                <!-- البيانات الأساسية -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white"><i class="fas fa-info-circle me-1 text-primary"></i> البيانات الأساسية</div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">نوع السلفة <span class="text-danger">*</span></label>
                                <select name="type" class="form-select" required>
                                    <option value="">اختر النوع...</option>
                                    <option value="borrow">استلاف (استلام مواد من مقاول)</option>
                                    <option value="lend">تسليف (تسليم مواد لمقاول)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المقاول/العميل <span class="text-danger">*</span></label>
                                <select name="client_id" id="client_id" class="form-select" required>
                                    <option value="">اختر المقاول...</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">اسم المستلم <span class="text-danger">*</span></label>
                                <input type="text" name="receiver_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">رقم هوية المستلم</label>
                                <input type="text" name="receiver_identity" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- المواد/البنود -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-boxes me-1 text-primary"></i> بنود السلفة</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItemRow()">
                            <i class="fas fa-plus"></i> إضافة بند
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="min-height: 300px; overflow: visible !important;">
                            <table class="table table-borderless mb-0" id="itemsTable" style="overflow: visible !important;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 35%">المادة (بحث بالرقم أو الوصف)</th>
                                        <th style="width: 35%">الوصف</th>
                                        <th style="width: 20%">الكمية</th>
                                        <th style="width: 10%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary px-4 mb-5" id="submitLoanBtn">
                    <i class="fas fa-save me-1"></i> حفظ السلفة
                </button>
            </form>
        </div>

        <div class="col-lg-4">
            <!-- عرض السلف السابقة للمقاول -->
            <div class="card shadow-sm border-0 bg-light" id="previousLoansCard" style="display: none;">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-history text-secondary me-1"></i> السلف السابقة مع المقاول</h6>
                </div>
                <div class="card-body p-0" id="previousLoansList">
                    <!-- سيتم تحميل السلف هنا عبر AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const materialsData = <?= json_encode($materials) ?>;
const catalogData = <?= json_encode($catalogItems) ?>;
let itemIndex = 0;

function addItemRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    const tr = document.createElement('tr');
    tr.className = 'material-row';
    
    tr.innerHTML = `
        <td style="position: relative; overflow: visible !important;">
            <input type="hidden" name="items[${itemIndex}][material_id]" class="material-id-input">
            <input type="hidden" name="items[${itemIndex}][unit]" class="unit-input">
            <div class="position-relative">
                <input type="text" class="form-control form-control-sm material-search-input" 
                       name="items[${itemIndex}][item_number]" 
                       placeholder="ابحث بالرقم أو الوصف..." autocomplete="off"
                       oninput="searchMaterialInRow(this, ${itemIndex})"
                       onfocus="searchMaterialInRow(this, ${itemIndex})" required>
                <div class="custom-dropdown material-dropdown-${itemIndex}"></div>
            </div>
            <div class="material-desc-display mt-1 small text-muted" style="display:none;"></div>
        </td>
        <td>
            <input type="text" name="items[${itemIndex}][description]" class="form-control form-control-sm description-input" required placeholder="الوصف" readonly>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="0.01" name="items[${itemIndex}][quantity]" class="form-control form-control-sm quantity-input" required placeholder="الكمية">
                <span class="input-group-text unit-display">-</span>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fas fa-trash"></i></button>
        </td>
    `;
    
    tbody.appendChild(tr);
    itemIndex++;
}

// ===== البحث في المواد داخل الصف =====
function searchMaterialInRow(input, rowIndex) {
    var searchTerm = input.value.toLowerCase();
    var dropdownContainer = document.querySelector('.material-dropdown-' + rowIndex);

    if (searchTerm.length < 1) {
        dropdownContainer.innerHTML = '';
        dropdownContainer.classList.remove('show');
        input.classList.remove('selected-item');
        
        var row = input.closest('.material-row');
        if (row) {
            row.querySelector('.material-id-input').value = '';
            row.querySelector('.description-input').value = '';
            row.querySelector('.unit-input').value = '';
            row.querySelector('.unit-display').textContent = '-';
            row.querySelector('.material-desc-display').style.display = 'none';
        }
        return;
    }

    // البحث في مواد المستودع
    var filtered = materialsData.filter(function (m) {
        return m.item_number.toLowerCase().indexOf(searchTerm) !== -1 ||
            (m.description && m.description.toLowerCase().indexOf(searchTerm) !== -1);
    }).slice(0, 10);

    // البحث في الكتالوج
    var catalogFiltered = [];
    if (catalogData && catalogData.length > 0) {
        catalogFiltered = catalogData.filter(function (m) {
            return m.item_number.toLowerCase().indexOf(searchTerm) !== -1 ||
                (m.description && m.description.toLowerCase().indexOf(searchTerm) !== -1);
        }).slice(0, 10);
    }

    var html = '';

    if (filtered.length > 0) {
        html += filtered.map(function (m) {
            var escapedNum = m.item_number.replace(/'/g, "\\'");
            return '<div class="dropdown-item-custom" onclick="selectMaterialInRow(' + m.id + ', ' + rowIndex + ', \'' + escapedNum + '\')">' +
                '<div class="item-number">' + m.item_number + '</div>' +
                '<div class="item-description">' + (m.description || '') + '</div>' +
                '<div class="item-stock">المخزون: ' + parseFloat(m.current_stock).toFixed(2) + ' ' + (m.unit || '') + '</div>' +
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
                '<div class="item-description">' + (m.description || '') + '</div>' +
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

// ===== اختيار مادة موجودة في المستودع =====
function selectMaterialInRow(materialId, rowIndex, itemNumber) {
    var material = materialsData.find(function (m) { return m.id == materialId; });
    if (material) {
        fillRowData(rowIndex, material);
    }
}

// ===== اختيار مادة من الكتالوج (إنشاء تلقائي) =====
function selectCatalogItem(catalogId, rowIndex, itemNumber) {
    var dropdownContainer = document.querySelector('.material-dropdown-' + rowIndex);
    dropdownContainer.innerHTML = '<div class="dropdown-item-custom text-center"><i class="fas fa-spinner fa-spin me-1"></i> جاري إضافة المادة للمستودع...</div>';

    fetch('create.php?ajax=create_from_catalog', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'catalog_id=' + catalogId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var newMat = {
                id: data.material_id,
                item_number: data.item_number,
                description: data.description,
                unit: data.unit,
                current_stock: data.current_stock
            };
            materialsData.push(newMat);
            fillRowData(rowIndex, newMat);
        } else {
            alert('فشل إضافة المادة من الكتالوج: ' + data.message);
            dropdownContainer.innerHTML = '';
            dropdownContainer.classList.remove('show');
        }
    })
    .catch(function(err) {
        alert('حدث خطأ في الاتصال بالخادم');
        dropdownContainer.innerHTML = '';
        dropdownContainer.classList.remove('show');
    });
}

function fillRowData(rowIndex, material) {
    var dropdownContainer = document.querySelector('.material-dropdown-' + rowIndex);
    var row = document.querySelector('.material-dropdown-' + rowIndex).closest('.material-row');
    
    dropdownContainer.innerHTML = '';
    dropdownContainer.classList.remove('show');
    
    var searchInput = row.querySelector('.material-search-input');
    searchInput.value = material.item_number;
    searchInput.classList.add('selected-item');
    
    row.querySelector('.material-id-input').value = material.id;
    row.querySelector('.description-input').value = material.description;
    row.querySelector('.unit-input').value = material.unit;
    row.querySelector('.unit-display').textContent = material.unit;
    
    var descBox = row.querySelector('.material-desc-display');
    descBox.textContent = material.description;
    descBox.style.display = 'block';
    
    row.querySelector('.quantity-input').focus();
}

// إغلاق القوائم عند النقر خارجها
document.addEventListener('click', function (e) {
    if (!e.target.closest('.position-relative')) {
        document.querySelectorAll('.custom-dropdown').forEach(function (el) {
            el.classList.remove('show');
        });
    }
});

// منع الإرسال المزدوج
let isSubmitting = false;
document.getElementById('loanForm').addEventListener('submit', function(e) {
    if (isSubmitting) {
        e.preventDefault();
        return false;
    }
    isSubmitting = true;
    const btn = document.getElementById('submitLoanBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ...';
});

// Add first row by default
document.addEventListener('DOMContentLoaded', () => {
    addItemRow();
});

// Load previous loans when client is selected
document.getElementById('client_id').addEventListener('change', function() {
    const clientId = this.value;
    const previousLoansCard = document.getElementById('previousLoansCard');
    const previousLoansList = document.getElementById('previousLoansList');
    
    if (!clientId) {
        previousLoansCard.style.display = 'none';
        return;
    }
    
    previousLoansList.innerHTML = '<div class="p-3 text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> جاري التحميل...</div>';
    previousLoansCard.style.display = 'block';
    
    fetch(`get_client_loans.php?client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.loans.length === 0) {
                    previousLoansList.innerHTML = '<div class="p-3 text-center text-muted small">لا توجد سلف سابقة مع هذا المقاول</div>';
                    return;
                }
                
                let html = '<ul class="list-group list-group-flush">';
                data.loans.forEach(loan => {
                    const statusBadge = loan.status === 'active' ? '<span class="badge bg-primary rounded-pill">نشطة</span>' : '<span class="badge bg-success rounded-pill">مخالصة</span>';
                    const typeIcon = loan.type === 'borrow' ? '<i class="fas fa-arrow-down text-info" title="استلاف"></i>' : '<i class="fas fa-arrow-up text-warning" title="تسليف"></i>';
                    
                    html += `
                        <li class="list-group-item bg-transparent">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong><a href="view.php?id=${loan.id}" target="_blank">${loan.loan_number}</a></strong>
                                ${statusBadge}
                            </div>
                            <div class="small text-muted">
                                ${typeIcon} ${loan.created_at.split(' ')[0]}
                            </div>
                        </li>
                    `;
                });
                html += '</ul>';
                previousLoansList.innerHTML = html;
            } else {
                previousLoansList.innerHTML = '<div class="p-3 text-center text-danger small">حدث خطأ أثناء تحميل السلف السابقة</div>';
            }
        })
        .catch(error => {
            previousLoansList.innerHTML = '<div class="p-3 text-center text-danger small">حدث خطأ في الاتصال</div>';
        });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/layout.php';
?>
