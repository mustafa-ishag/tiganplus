<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة تعديل معاملة مخزون
 * Edit Inventory Transaction Page
 */

// منع الوصول المباشر
if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryTransaction.php';
require_once __DIR__ . '/../../../models/Material.php';


// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    redirect('../../auth/login.php');
}

// التحقق من الصلاحيات
if (!hasPermission('inventory_transactions_edit')) {
    setAlert('ليس لديك صلاحية لتعديل معاملات المخزون', 'error');
    redirect('index.php');
}

$transactionId = (int)($_GET['id'] ?? 0);

if ($transactionId <= 0) {
    setAlert('معرف المعاملة غير صحيح', 'error');
    redirect('index.php');
}

$transactionModel = new InventoryTransaction();
$materialModel = new Material();


// الحصول على بيانات المعاملة
$transaction = $transactionModel->getTransactionWithDetails($transactionId);

if (!$transaction) {
    setAlert('المعاملة غير موجودة', 'error');
    redirect('index.php');
}

// التحقق من إمكانية التعديل
if ($transaction['status'] !== 'pending') {
    setAlert('لا يمكن تعديل المعاملة بعد اعتمادها أو رفضها', 'error');
    redirect('/inventory/transactions/view.php?id=' . $transactionId);
}

// الحصول على المواد النشطة
$materials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description, mc.unit, m.current_stock 
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE m.is_active = 1 
     ORDER BY m.item_number"
);



$errors = [];
$formData = [
    'transaction_type' => $transaction['transaction_type'],
    'transaction_date' => $transaction['transaction_date'],

    'notes' => $transaction['notes'],
    'materials' => $transaction['details']
];

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'transaction_type' => $_POST['transaction_type'] ?? $transaction['transaction_type'],
        'transaction_date' => $_POST['transaction_date'] ?? '',

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
    
    // إذا لم توجد أخطاء، قم بتحديث المعاملة
    if (empty($errors)) {
        $transactionData = [
            'transaction_date' => $formData['transaction_date'],

            'notes' => $formData['notes'],
            'updated_at' => getCurrentDateTime()
        ];
        
        $result = $transactionModel->updateTransaction($transactionId, $transactionData, $formData['materials']);
        
        if ($result['success']) {
            setAlert('تم تحديث المعاملة بنجاح', 'success');
            redirect('view.php?id=' . $transactionId);
        } else {
            $errors['general'] = $result['message'];
        }
    }
}

// تحديد عناوين الصفحة حسب نوع المعاملة
$typeLabels = [
    'incoming' => ['تعديل معاملة وارد', 'تعديل استلام مواد', 'success', 'arrow-down'],
    'outgoing' => ['تعديل معاملة صادر', 'تعديل صرف مواد', 'danger', 'arrow-up'],
    'transfer' => ['تعديل معاملة تحويل', 'تعديل تحويل مواد', 'info', 'exchange-alt'],
    'return' => ['تعديل معاملة مرتجع', 'تعديل إرجاع مواد', 'warning', 'undo']
];

$typeInfo = $typeLabels[$transaction['transaction_type']];
$pageTitle = $typeInfo[0] . ' - ' . $transaction['transaction_number'];
$currentPage = 'inventory';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-<?= $typeInfo[3] ?> text-<?= $typeInfo[2] ?> me-2"></i>
                <?= $typeInfo[0] ?>
            </h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($transaction['transaction_number']) ?> - <?= $typeInfo[1] ?></p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group" role="group">
                <a href="view.php?id=<?= $transactionId ?>" class="btn btn-outline-primary">
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

    <!-- تحذير هام -->
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>تحذير:</strong> تعديل المعاملة سيؤثر على البيانات المحفوظة. تأكد من صحة البيانات قبل الحفظ.
    </div>

    <!-- نموذج تعديل المعاملة -->
    <form method="POST" id="transactionForm">
        <div class="row">
            <div class="col-lg-8">
                <!-- معلومات المعاملة الأساسية -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">معلومات المعاملة</h5>
                    </div>
                    <div class="card-body">
                        <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 if (!empty($errors['general'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= nl2br(htmlspecialchars($errors['general'])) ?>
                            </div>
                        <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endif; ?>

                        <input type="hidden" name="transaction_type" value="<?= $transaction['transaction_type'] ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="transaction_date" class="form-label">
                                    تاريخ المعاملة <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control <?= isset($errors['transaction_date']) ? 'is-invalid' : '' ?>" 
                                       id="transaction_date" name="transaction_date" 
                                       value="<?= htmlspecialchars($formData['transaction_date']) ?>" required>
                                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 if (isset($errors['transaction_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['transaction_date'] ?></div>
                                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endif; ?>
                            </div>


                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="ملاحظات إضافية حول المعاملة"><?= htmlspecialchars($formData['notes']) ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- المواد -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">المواد</h5>
                        <button type="button" class="btn btn-primary btn-sm" onclick="addMaterialRow()">
                            <i class="fas fa-plus me-1"></i>
                            إضافة مادة
                        </button>
                    </div>
                    <div class="card-body">
                        <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 if (isset($errors['materials'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $errors['materials'] ?>
                            </div>
                        <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endif; ?>

                        <div id="materials-container">
                            <!-- سيتم إضافة صفوف المواد هنا بواسطة JavaScript -->
                        </div>

                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="row">
                                <div class="col-md-12">
                                    <strong>إجمالي البنود: <span id="total-items">0</span></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- أزرار الإجراءات -->
                <div class="mt-4 d-flex justify-content-between">
                    <button type="submit" class="btn btn-<?= $typeInfo[2] ?>">
                        <i class="fas fa-save me-1"></i>
                        حفظ التعديلات
                    </button>
                    <div>
                        <a href="view.php?id=<?= $transactionId ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-eye me-1"></i>
                            عرض التفاصيل
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            إلغاء
                        </a>
                    </div>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات المعاملة الحالية -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            معلومات المعاملة الحالية
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="fw-bold text-muted">رقم المعاملة:</td>
                                <td><?= htmlspecialchars($transaction['transaction_number']) ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">النوع:</td>
                                <td>
                                    <span class="badge bg-<?= $typeInfo[2] ?>"><?= $typeInfo[0] ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">الحالة:</td>
                                <td>
                                    <span class="badge bg-warning">معلق</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">المنشئ:</td>
                                <td><?= htmlspecialchars($transaction['created_by_name'] ?? 'غير معروف') ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-muted">تاريخ الإنشاء:</td>
                                <td><?= formatDateTime($transaction['created_at']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- تحذيرات مهمة -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                            تحذيرات مهمة
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <ul class="mb-0 small">
                                <li>تعديل المعاملة سيؤثر على البيانات المحفوظة</li>
                                <li>تأكد من صحة الكميات والأسعار</li>
                                <li>لا يمكن التراجع عن التعديلات بعد الحفظ</li>
                                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 if ($transaction['transaction_type'] === 'outgoing'): ?>
                                    <li>تأكد من توفر المخزون للكميات المطلوبة</li>
                                <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- قالب صف المادة -->
<template id="material-row-template">
    <div class="material-row border rounded p-3 mb-3">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">المادة <span class="text-danger">*</span></label>
                <select class="form-select material-select" name="materials[INDEX][material_id]" required>
                    <option value="">اختر المادة</option>
                    <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 foreach ($materials as $material): ?>
                        <option value="<?= $material['id'] ?>" 
                                data-unit="<?= htmlspecialchars($material['unit'] ?? '') ?>"
                                data-stock="<?= $material['current_stock'] ?>">
                            <?= htmlspecialchars($material['item_number']) ?> - <?= htmlspecialchars($material['description'] ?? '') ?>
                        </option>
                    <?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

 endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 mb-3">
                <label class="form-label">الكمية <span class="text-danger">*</span></label>
                <input type="number" class="form-control quantity-input" 
                       name="materials[INDEX][quantity]" 
                       min="0" step="0.001" required>
                <small class="form-text text-muted">الوحدة: <span class="unit-display">-</span></small>
                <small class="form-text text-muted stock-display" style="display: none;">
                    المخزون: <span class="stock-value">0</span>
                </small>
            </div>
            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeMaterialRow(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
let materialRowIndex = 0;
const transactionType = '<?= $transaction['transaction_type'] ?>';
const materialsData = <?= json_encode($materials) ?>;
const existingMaterials = <?= json_encode($formData['materials']) ?>;

// إضافة صف مادة جديد
function addMaterialRow(materialData = null) {
    const template = document.getElementById('material-row-template');
    const container = document.getElementById('materials-container');
    
    const newRow = template.content.cloneNode(true);
    
    // تحديث الفهارس
    newRow.querySelectorAll('[name*="INDEX"]').forEach(element => {
        element.name = element.name.replace('INDEX', materialRowIndex);
    });
    
    container.appendChild(newRow);
    
    // إضافة مستمعي الأحداث
    const addedRow = container.lastElementChild;
    setupMaterialRowEvents(addedRow);
    
    // تعبئة البيانات إذا كانت متوفرة
    if (materialData) {
        const materialSelect = addedRow.querySelector('.material-select');
        const quantityInput = addedRow.querySelector('.quantity-input');
        
        materialSelect.value = materialData.material_id;
        quantityInput.value = materialData.quantity;
        
        // تحديث معلومات المادة
        materialSelect.dispatchEvent(new Event('change'));
    }
    
    materialRowIndex++;
    updateTotals();
}

// إزالة صف مادة
function removeMaterialRow(button) {
    button.closest('.material-row').remove();
    updateTotals();
}

// إعداد أحداث صف المادة
function setupMaterialRowEvents(row) {
    const materialSelect = row.querySelector('.material-select');
    const quantityInput = row.querySelector('.quantity-input');
    const unitDisplay = row.querySelector('.unit-display');
    const stockDisplay = row.querySelector('.stock-display');
    const stockValue = row.querySelector('.stock-value');
    
    // عند تغيير المادة
    materialSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const unit = selectedOption.dataset.unit;
            const stock = parseFloat(selectedOption.dataset.stock);
            
            unitDisplay.textContent = unit;
            stockValue.textContent = stock.toFixed(3);
            
            if (transactionType === 'outgoing') {
                stockDisplay.style.display = 'block';
                quantityInput.max = stock;
            }
        } else {
            unitDisplay.textContent = '-';
            stockDisplay.style.display = 'none';
        }
        updateTotals();
    });
    
    quantityInput.addEventListener('input', () => updateTotals());
}

// تحديث الإجماليات
function updateTotals() {
    const rows = document.querySelectorAll('.material-row');
    document.getElementById('total-items').textContent = rows.length;
}

// التحقق من صحة النموذج
document.getElementById('transactionForm').addEventListener('submit', function(e) {
    const materialRows = document.querySelectorAll('.material-row');
    
    if (materialRows.length === 0) {
        e.preventDefault();
        alert('يجب إضافة مادة واحدة على الأقل');
        return;
    }
    
    let hasValidMaterial = false;
    materialRows.forEach(row => {
        const materialSelect = row.querySelector('.material-select');
        const quantityInput = row.querySelector('.quantity-input');
        
        if (materialSelect.value && quantityInput.value > 0) {
            hasValidMaterial = true;
        }
    });
    
    if (!hasValidMaterial) {
        e.preventDefault();
        alert('يجب إضافة مادة واحدة صحيحة على الأقل');
        return;
    }
});

// تحميل المواد الموجودة
document.addEventListener('DOMContentLoaded', function() {
    if (existingMaterials && existingMaterials.length > 0) {
        existingMaterials.forEach(material => {
            addMaterialRow(material);
        });
    } else {
        addMaterialRow();
    }
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
