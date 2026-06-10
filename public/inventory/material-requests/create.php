<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * صفحة إضافة طلب صرف جديد
 * Create New Material Request Page
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
if (!hasPermission('inventory_requests_create')) {
    setAlert('ليس لديك صلاحية لإنشاء طلبات الصرف', 'error');
    redirect('index.php');
}

$materialRequestModel = new MaterialRequest();
$materialModel = new Material();
$workOrderModel = new WorkOrder();

// الحصول على أوامر العمل المتاحة للصرف
$workOrders = $workOrderModel->getWorkOrdersForMaterialRequest($_SESSION['user_branch_id'] ?? null);

// الحصول على المواد النشطة
$materials = $materialModel->fetchAll(
    "SELECT m.id, m.item_number, mc.description, mc.unit, m.current_stock
     FROM materials m
     LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
     WHERE m.is_active = 1 AND m.current_stock > 0
     ORDER BY m.item_number"
);

$errors = [];
$warnings = [];
$formData = [
    'work_order_id' => '',
    'request_date' => date('Y-m-d'),
    'required_date' => '',
    'notes' => '',
    'materials' => []
];

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // تنظيف بيانات المواد من الصفوف الفارغة
    $rawMaterials = $_POST['materials'] ?? [];
    $cleanMaterials = [];

    foreach ($rawMaterials as $material) {
        // تضمين المادة فقط إذا كانت تحتوي على معرف مادة وكمية صحيحة
        if (!empty($material['material_id']) && !empty($material['quantity']) && $material['quantity'] > 0) {
            $cleanMaterials[] = [
                'material_id' => intval($material['material_id']),
                'quantity' => floatval($material['quantity'])
            ];
        }
    }

    $formData = [
        'work_order_id' => $_POST['work_order_id'] ?? '',
        'request_date' => $_POST['request_date'] ?? '',
        'required_date' => $_POST['required_date'] ?? '',
        'notes' => trim($_POST['notes'] ?? ''),
        'materials' => $cleanMaterials
    ];

    // التحقق من صحة البيانات
    if (empty($formData['work_order_id'])) {
        $errors['work_order_id'] = 'أمر العمل مطلوب';
    } else {
        // التحقق من إمكانية إنشاء طلب صرف لهذا الأمر
        $canCreate = $workOrderModel->canCreateMaterialRequest($formData['work_order_id']);
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

            // التحقق من توفر المخزون (تحذير فقط، لا يمنع الإنشاء)
            if (!empty($material['material_id']) && !empty($material['quantity'])) {
                $materialData = $materialModel->findById($material['material_id']);
                if ($materialData && $materialData['current_stock'] < $material['quantity']) {
                    // تسجيل تحذير بدلاً من منع الإنشاء
                    $warnings["materials_{$index}_quantity"] = "تحذير: الكمية المطلوبة ({$material['quantity']}) أكبر من المخزون المتاح ({$materialData['current_stock']})";
                }
            }
        }
    }

    // تسجيل معلومات التشخيص
    error_log("Material Request Debug - Errors: " . json_encode($errors));
    error_log("Material Request Debug - Materials count: " . count($formData['materials']));
    error_log("Material Request Debug - POST data size: " . strlen(serialize($_POST)));

    // إذا لم توجد أخطاء، قم بإنشاء الطلب
    if (empty($errors)) {
        $action = $_POST['action'] ?? 'save_draft';

        // الحصول على معلومات أمر العمل للتحقق من صحته
        $workOrder = $workOrderModel->findById($formData['work_order_id']);
        if (!$workOrder) {
            $errors['work_order_id'] = 'أمر العمل غير موجود';
        } else {
            $requestData = [
                'work_order_id' => $formData['work_order_id'],
                'request_date' => $formData['request_date'],
                'required_date' => $formData['required_date'],
                'notes' => $formData['notes'],
                'requested_by' => $_SESSION['user_id']
            ];
        }

        // المتابعة فقط إذا لم توجد أخطاء جديدة
        if (empty($errors)) {
            // تحويل المواد إلى التنسيق المطلوب
            $details = [];
            foreach ($formData['materials'] as $material) {
                if (!empty($material['material_id']) && !empty($material['quantity'])) {
                    $details[] = [
                        'material_id'        => $material['material_id'],
                        'requested_quantity' => $material['quantity'],
                        'purpose'            => '',
                        'notes'              => ''
                    ];
                }
            }

            error_log("[create.php] action={$action}, details_count=" . count($details));

            $result = $materialRequestModel->createRequest($requestData, $details, $action);

            if ($result['success']) {
                $message = $action === 'submit' ? 'تم إنشاء وإرسال طلب الصرف بنجاح' : 'تم إنشاء طلب الصرف كمسودة بنجاح';
                setAlert($message, 'success');

                // ===== إرسال البريد مباشرة عند تقديم الطلب =====
                if ($action === 'submit' && !empty($details)) {
                    ignore_user_abort(true);
                    set_time_limit(60);
                    try {
                        require_once __DIR__ . '/../../../includes/EmailService.php';
                        $db   = getDB();
                        $stmt = $db->prepare(
                            "SELECT mrd.*, m.item_number, mc.description, mc.unit, m.current_stock
                             FROM material_request_details mrd
                             JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                             WHERE mrd.request_id = ?
                             ORDER BY mc.description"
                        );
                        $stmt->execute([$result['request_id']]);
                        $emailDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $emailData = array_merge($requestData, [
                            'id'             => $result['request_id'],
                            'request_number' => $result['request_number'],
                        ]);

                        $emailService = new EmailService();
                        $emailSent    = $emailService->sendMaterialRequestNotification($emailData, $emailDetails);
                        error_log("[create.php] Email result: " . ($emailSent ? 'SENT' : 'FAILED') . " for " . $result['request_number']);
                    } catch (Exception $emailEx) {
                        error_log("[create.php] Email exception: " . $emailEx->getMessage());
                    }
                }

                redirect('view.php?id=' . $result['request_id']);
            } else {
                $errors['general'] = $result['message'];
                error_log("Material Request Creation Failed: " . $result['message']);
                error_log("Request Data: " . json_encode($requestData));
                error_log("Details: " . json_encode($details));
            }
        }
    }
}

$pageTitle = 'إضافة طلب صرف جديد';
$currentPage = 'material-requests';

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <!-- رأس الصفحة -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-plus text-success me-2"></i>
                إضافة طلب صرف جديد
            </h2>
            <p class="text-muted mb-0">إنشاء طلب صرف مواد لأمر عمل</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right me-1"></i>
                العودة إلى قائمة الطلبات
            </a>
        </div>
    </div>

    <!-- نموذج إضافة طلب الصرف -->
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
                                <?= nl2br(htmlspecialchars($errors['general'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($warnings)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>تحذيرات المخزون:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($warnings as $warning): ?>
                                        <li><?= htmlspecialchars($warning) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="work_order_search" class="form-label">
                                    أمر العمل <span class="text-danger">*</span>
                                </label>
                                <div class="wo-select-container position-relative">
                                    <input type="text"
                                        class="form-control <?= isset($errors['work_order_id']) ? 'is-invalid' : '' ?>"
                                        id="work_order_search" placeholder="اكتب رقم أمر العمل للبحث..."
                                        autocomplete="off" onkeyup="searchWorkOrder(this)" value="<?php
                                        if ($formData['work_order_id']) {
                                            foreach ($workOrders as $wo) {
                                                if ($wo['id'] == $formData['work_order_id']) {
                                                    echo htmlspecialchars($wo['work_order_number']);
                                                    break;
                                                }
                                            }
                                        }
                                        ?>">
                                    <select id="work_order_id" name="work_order_id" class="d-none" required>
                                        <option value="">اختر أمر العمل</option>
                                        <?php foreach ($workOrders as $workOrder): ?>
                                            <option value="<?= $workOrder['id'] ?>"
                                                <?= $formData['work_order_id'] == $workOrder['id'] ? 'selected' : '' ?>
                                                data-number="<?= htmlspecialchars($workOrder['work_order_number']) ?>"
                                                data-type="<?= htmlspecialchars($workOrder['work_order_type_description']) ?>"
                                                data-estimated-value="<?= $workOrder['estimated_value'] ?>"
                                                data-disbursement-status="<?= $workOrder['disbursement_status'] ?>">
                                                <?= htmlspecialchars($workOrder['work_order_number']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="wo-dropdown custom-dropdown" id="woDropdown"></div>
                                </div>
                                <div class="wo-type-display" id="woTypeDisplay"></div>
                                <?php if (isset($errors['work_order_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $errors['work_order_id'] ?></div>
                                <?php endif; ?>
                                <div class="form-text">اكتب رقم أمر العمل أو النوع للبحث</div>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="request_date" class="form-label">
                                    تاريخ الطلب <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                    class="form-control <?= isset($errors['request_date']) ? 'is-invalid' : '' ?>"
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
                                <input type="date"
                                    class="form-control <?= isset($errors['required_date']) ? 'is-invalid' : '' ?>"
                                    id="required_date" name="required_date"
                                    value="<?= htmlspecialchars($formData['required_date']) ?>" required>
                                <?php if (isset($errors['required_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['required_date'] ?></div>
                                <?php endif; ?>
                                <div class="form-text">التاريخ المطلوب توفر المواد فيه</div>
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
                            <button type="button" class="btn btn-sm btn-outline-success"
                                onclick="loadFromCompletionCertificate()" id="loadFromCertificateBtn" disabled
                                title="يرجى اختيار أمر العمل أولاً">
                                <i class="fas fa-certificate me-1"></i>
                                تحميل من شهادة الإنجاز
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>
                                إضافة مادة
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (isset($errors['materials'])): ?>
                            <div class="alert alert-danger m-3">
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
                            إرسال الطلب
                        </button>
                    </div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        إلغاء
                    </a>
                </div>
            </div>

            <!-- الشريط الجانبي -->
            <div class="col-lg-4">
                <!-- معلومات أمر العمل -->
                <div class="card mb-4" id="work-order-info" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            معلومات أمر العمل
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="work-order-details">
                            <!-- سيتم عرض تفاصيل أمر العمل هنا -->
                        </div>
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
                        <input type="text" class="form-control mb-3" id="material-search" placeholder="ابحث عن مادة...">

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
    const workOrdersData = <?= json_encode($workOrders) ?>;

    // إضافة صف مادة جديد
    function addMaterialRow() {
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
            `<option value="${material.id}" data-code="${material.item_number}" data-description="${material.description}" data-unit="${material.unit}" data-stock="${material.current_stock}">${material.item_number}</option>`
        ).join('')}
                </select>
                <div class="material-dropdown-${materialRowIndex} custom-dropdown"></div>
            </div>
        </td>
        <td><span id="material_description_${materialRowIndex}">-</span></td>
        <td><span id="material_unit_${materialRowIndex}">-</span></td>
        <td><span id="estimated_quantity_${materialRowIndex}" class="text-muted">-</span></td>
        <td>
            <input type="number" name="materials[${materialRowIndex}][quantity]"
                   class="form-control form-control-sm quantity-input"
                   step="0.001" min="0" value="0"
                   onchange="calculateDifference(${materialRowIndex})"
                   oninput="checkStockAvailability(${materialRowIndex})" required>
        </td>
        <td>
            <span id="difference_${materialRowIndex}" class="badge bg-secondary">-</span>
        </td>
        <td>
            <span id="current_stock_${materialRowIndex}" class="text-muted">-</span>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMaterialRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;

        tbody.appendChild(row);
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
            document.getElementById(`current_stock_${rowIndex}`).textContent = selectedOption.dataset.stock || '0';

            // التحقق من توفر المخزون
            checkStockAvailability(rowIndex);
        } else {
            document.getElementById(`material_description_${rowIndex}`).textContent = '-';
            document.getElementById(`material_unit_${rowIndex}`).textContent = '-';
            document.getElementById(`current_stock_${rowIndex}`).textContent = '-';
            document.getElementById(`estimated_quantity_${rowIndex}`).textContent = '-';
            document.getElementById(`difference_${rowIndex}`).textContent = '-';
            document.getElementById(`difference_${rowIndex}`).className = 'badge bg-secondary';

            // إزالة ألوان التحذير
            const row = select.closest('tr');
            row.classList.remove('table-danger', 'table-warning');
        }
    }

    // حساب الفرق بين الكمية المطلوبة والمقايسة
    function calculateDifference(rowIndex) {
        const quantityInput = document.querySelector(`input[name="materials[${rowIndex}][quantity]"]`);
        const estimatedSpan = document.getElementById(`estimated_quantity_${rowIndex}`);
        const differenceSpan = document.getElementById(`difference_${rowIndex}`);

        const requestedQuantity = parseFloat(quantityInput.value) || 0;
        const estimatedQuantity = parseFloat(estimatedSpan.textContent) || 0;

        if (estimatedQuantity > 0) {
            const difference = requestedQuantity - estimatedQuantity;

            if (difference > 0) {
                differenceSpan.textContent = `+${difference.toFixed(3)}`;
                differenceSpan.className = 'badge bg-warning text-dark';
                differenceSpan.title = 'صرف زائد عن المقايسة';
            } else if (difference < 0) {
                differenceSpan.textContent = `${difference.toFixed(3)}`;
                differenceSpan.className = 'badge bg-info';
                differenceSpan.title = 'صرف أقل من المقايسة';
            } else {
                differenceSpan.textContent = '0';
                differenceSpan.className = 'badge bg-success';
                differenceSpan.title = 'مطابق للمقايسة';
            }
        } else {
            differenceSpan.textContent = '-';
            differenceSpan.className = 'badge bg-secondary';
            differenceSpan.title = '';
        }

        // التحقق من المخزون أيضاً
        checkStockAvailability(rowIndex);
    }

    // التحقق من توفر المخزون
    function checkStockAvailability(rowIndex) {
        const quantityInput = document.querySelector(`input[name="materials[${rowIndex}][quantity]"]`);
        const stockSpan = document.getElementById(`current_stock_${rowIndex}`);
        const row = quantityInput.closest('tr');

        const requestedQuantity = parseFloat(quantityInput.value) || 0;
        const currentStock = parseFloat(stockSpan.textContent) || 0;

        // إزالة الألوان السابقة
        row.classList.remove('table-danger', 'table-warning');

        if (requestedQuantity > 0 && currentStock >= 0) {
            if (currentStock === 0) {
                row.classList.add('table-danger');
                row.title = 'تحذير: المادة غير متوفرة في المخزون';
            } else if (requestedQuantity > currentStock) {
                row.classList.add('table-warning');
                row.title = `تحذير: الكمية المطلوبة (${requestedQuantity}) أكبر من المخزون المتاح (${currentStock})`;
            } else {
                row.title = '';
            }
        }
    }

    // تحديث الإجماليات
    function updateTotals() {
        const rows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');
        let totalItems = rows.length;

        document.getElementById('total-items').textContent = totalItems;
    }

    // عرض معلومات أمر العمل
    document.getElementById('work_order_id').addEventListener('change', function () {
        const workOrderInfo = document.getElementById('work-order-info');
        const workOrderDetails = document.getElementById('work-order-details');

        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            const estimatedValue = selectedOption.dataset.estimatedValue;
            const disbursementStatus = selectedOption.dataset.disbursementStatus;

            const workOrder = workOrdersData.find(wo => wo.id == this.value);

            if (workOrder) {
                workOrderDetails.innerHTML = `
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="fw-bold text-muted">رقم الأمر:</td>
                        <td>${workOrder.work_order_number}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">النوع:</td>
                        <td>${workOrder.work_order_type_description}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">الفرع:</td>
                        <td>${workOrder.branch_name}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">القيمة المقدرة:</td>
                        <td>${parseFloat(estimatedValue).toLocaleString()} ريال</td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">حالة الصرف:</td>
                        <td><span class="badge bg-info">${getDisbursementStatusLabel(disbursementStatus)}</span></td>
                    </tr>
                </table>
            `;
                workOrderInfo.style.display = 'block';

                // تفعيل زر تحميل من شهادة الإنجاز
                const loadFromCertificateBtn = document.getElementById('loadFromCertificateBtn');
                loadFromCertificateBtn.disabled = false;
                loadFromCertificateBtn.title = 'اضغط لتحميل المواد من شهادة الإنجاز';

                // تحميل تلقائي للمواد من شهادة الإنجاز
                setTimeout(() => {
                    loadFromCompletionCertificate(true); // true = تحميل صامت
                }, 500);
            }
        } else {
            workOrderInfo.style.display = 'none';

            // تعطيل زر تحميل من شهادة الإنجاز
            const loadFromCertificateBtn = document.getElementById('loadFromCertificateBtn');
            loadFromCertificateBtn.disabled = true;
            loadFromCertificateBtn.title = 'يرجى اختيار أمر العمل أولاً';
        }
    });

    function getDisbursementStatusLabel(status) {
        const labels = {
            'none': 'لم يتم الصرف',
            'pending_disbursement': 'في انتظار الصرف',
            'partial_disbursement': 'صرف جزئي',
            'completed': 'مكتمل'
        };
        return labels[status] || status;
    }

    // تحميل المواد من شهادة الإنجاز
    async function loadFromCompletionCertificate(silent = false) {
        const workOrderId = document.getElementById('work_order_id').value;

        if (!workOrderId) {
            if (!silent) alert('يرجى اختيار أمر العمل أولاً');
            return;
        }

        const loadBtn = document.getElementById('loadFromCertificateBtn');
        const originalText = loadBtn.innerHTML;

        try {
            // تعطيل الزر وإظهار التحميل
            loadBtn.disabled = true;
            loadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>جاري التحميل...';

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
                if (!silent) alert('لا توجد مواد في شهادات الإنجاز لهذا أمر العمل');
                return;
            }

            // تأكيد التحميل (فقط إذا لم يكن صامتاً)
            if (!silent) {
                const confirmMessage = `تم العثور على ${data.materials.length} مادة في ${data.certificates.length} شهادة إنجاز.\n\nهل تريد تحميل هذه المواد؟ سيتم استبدال المواد الحالية.`;

                if (!confirm(confirmMessage)) {
                    return;
                }
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
                    document.getElementById(`current_stock_${rowIndex}`).textContent = material.current_stock || '0';
                    document.getElementById(`estimated_quantity_${rowIndex}`).textContent = material.estimated_quantity || '0';

                    // تعبئة الكمية من المقايسة
                    quantityInput.value = material.estimated_quantity;

                    // حساب الفرق والتحقق من المخزون
                    calculateDifference(rowIndex);
                }
            });

            showAlert(`تم تحميل ${data.materials.length} مادة من شهادات الإنجاز بنجاح`, 'success');

        } catch (error) {
            console.error('خطأ في تحميل المواد:', error);
            alert('حدث خطأ أثناء تحميل المواد: ' + error.message);
        } finally {
            // إعادة تفعيل الزر
            loadBtn.disabled = false;
            loadBtn.innerHTML = originalText;
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

    // البحث في المواد
    document.getElementById('material-search').addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        const suggestions = document.getElementById('material-suggestions');

        if (searchTerm.length < 2) {
            suggestions.innerHTML = '';
            return;
        }

        const filteredMaterials = materialsData.filter(material =>
            material.item_number.toLowerCase().includes(searchTerm) ||
            material.description.toLowerCase().includes(searchTerm)
        ).slice(0, 10);

        suggestions.innerHTML = filteredMaterials.map(material => `
        <a href="#" class="list-group-item list-group-item-action" 
           onclick="selectMaterial(${material.id}); return false;">
            <strong>${material.item_number}</strong><br>
            <small>${material.description}</small><br>
            <small class="text-muted">المخزون: ${material.current_stock} ${material.unit}</small>
        </a>
    `).join('');
    });

    // البحث السريع في المواد (الشريط الجانبي)
    function initializeMaterialSearch() {
        const materialSearch = document.getElementById('material-search');
        const materialSuggestions = document.getElementById('material-suggestions');

        materialSearch.addEventListener('input', function () {
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
    // Position dropdown as fixed on mobile to avoid table overflow clipping
    function positionDropdownMobile(dropdown, input) {
        if (window.innerWidth >= 768) {
            dropdown.classList.remove('mobile-fixed');
            dropdown.removeAttribute('style');
            return;
        }
        var rect = input.getBoundingClientRect();
        dropdown.classList.add('mobile-fixed');
        dropdown.style.position = 'fixed';
        dropdown.style.top = (rect.bottom + 2) + 'px';
        dropdown.style.left = '10px';
        dropdown.style.right = '10px';
        dropdown.style.width = 'auto';
        dropdown.style.zIndex = '9999';
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
            positionDropdownMobile(dropdownContainer, input);
        } else {
            dropdownContainer.innerHTML = '<div class="dropdown-item-custom text-muted">لا توجد نتائج</div>';
            dropdownContainer.classList.add('show');
            positionDropdownMobile(dropdownContainer, input);
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
            dropdownContainer.removeAttribute('style');
            select.dispatchEvent(new Event('change'));
        }
    }

    // التحقق من صحة النموذج
    document.getElementById('materialRequestForm').addEventListener('submit', function (e) {
        const materialRows = document.querySelectorAll('#materialsTableBody tr:not(#noMaterialsRow)');

        if (materialRows.length === 0) {
            e.preventDefault();
            alert('يجب إضافة مادة واحدة على الأقل');
            return;
        }

        let hasValidMaterial = false;
        let invalidMaterials = [];

        materialRows.forEach((row, index) => {
            const materialSelect = row.querySelector('select[name*="material_id"]');
            const quantityInput = row.querySelector('input[name*="quantity"]');
            const searchInput = row.querySelector('.material-search-input');

            if (materialSelect && quantityInput && searchInput) {
                // التحقق من أن المادة محددة والكمية صحيحة
                if (materialSelect.value && quantityInput.value > 0) {
                    hasValidMaterial = true;
                } else {
                    // إذا كان حقل البحث فارغ، تجاهل هذا الصف
                    if (!searchInput.value.trim()) {
                        return; // تجاهل الصفوف الفارغة
                    }

                    if (!materialSelect.value) {
                        invalidMaterials.push(`الصف ${index + 1}: لم يتم اختيار المادة بشكل صحيح`);
                    }
                    if (!quantityInput.value || quantityInput.value <= 0) {
                        invalidMaterials.push(`الصف ${index + 1}: الكمية غير صحيحة`);
                    }
                }
            }
        });

        if (!hasValidMaterial) {
            e.preventDefault();
            let message = 'يجب إضافة مادة واحدة صحيحة على الأقل\n\nالأخطاء:\n' + invalidMaterials.join('\n');
            alert(message);
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

    // ===== بحث أمر العمل =====
    function searchWorkOrder(input) {
        var searchTerm = input.value.toLowerCase();
        var dropdown = document.getElementById('woDropdown');
        var select = document.getElementById('work_order_id');

        if (searchTerm.length < 1) {
            dropdown.innerHTML = '';
            dropdown.classList.remove('show');
            input.classList.remove('wo-selected');
            select.value = '';
            document.getElementById('woTypeDisplay').textContent = '';
            document.getElementById('woTypeDisplay').classList.remove('show');
            select.dispatchEvent(new Event('change'));
            return;
        }

        var filtered = workOrdersData.filter(function (wo) {
            return wo.work_order_number.toLowerCase().indexOf(searchTerm) !== -1 ||
                (wo.type_code && wo.type_code.toLowerCase().indexOf(searchTerm) !== -1) ||
                wo.work_order_type_description.toLowerCase().indexOf(searchTerm) !== -1;
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

    function selectWorkOrder(woId, woNumber) {
        var input = document.getElementById('work_order_search');
        var select = document.getElementById('work_order_id');
        var dropdown = document.getElementById('woDropdown');

        input.value = woNumber;
        input.classList.add('wo-selected');
        select.value = woId;
        dropdown.classList.remove('show');

        // Show type code below the search input
        var wo = workOrdersData.find(function (w) { return w.id == woId; });
        var typeDisplay = document.getElementById('woTypeDisplay');
        if (wo && wo.type_code) {
            typeDisplay.textContent = wo.type_code;
            typeDisplay.classList.add('show');
        }

        // Trigger the existing change event to show work order details
        select.dispatchEvent(new Event('change'));
    }

    // إخفاء القوائم المنسدلة عند النقر خارجها
    document.addEventListener('click', function (e) {
        // Work order dropdown
        var woContainer = document.querySelector('.wo-select-container');
        var woDropdown = document.getElementById('woDropdown');
        if (woContainer && !woContainer.contains(e.target)) {
            woDropdown.classList.remove('show');
        }

        // Material search dropdowns
        document.querySelectorAll('[class*="material-dropdown-"]').forEach(function (dropdown) {
            var parent = dropdown.closest('.material-select-container');
            if (parent && !parent.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Reset fixed positioning on closed dropdowns
        document.querySelectorAll('[class*="material-dropdown-"]:not(.show)').forEach(function(d) {
            d.removeAttribute('style');
        });
    });

    // تهيئة الصفحة
    document.addEventListener('DOMContentLoaded', function () {
        initializeMaterialSearch();
        addMaterialRow();

        // If work order was pre-selected (from form data), mark it
        var select = document.getElementById('work_order_id');
        var input = document.getElementById('work_order_search');
        if (select.value && input.value) {
            input.classList.add('wo-selected');
        }
    });
</script>

<style>
    /* Work Order Search Dropdown */
    .wo-select-container {
        position: relative;
    }

    .wo-select-container .custom-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1060;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        max-height: 250px;
        overflow-y: auto;
        margin-top: 2px;
        display: none;
    }

    .wo-select-container .custom-dropdown.show {
        display: block;
    }

    .wo-dropdown-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
        transition: background-color 0.2s;
    }

    .wo-dropdown-item:hover {
        background-color: #e7f3ff;
    }

    .wo-dropdown-item:last-child {
        border-bottom: none;
    }

    .wo-dropdown-item .wo-number {
        font-weight: 700;
        color: #0d6efd;
        font-size: 0.9rem;
    }

    .wo-dropdown-item .wo-type {
        font-size: 0.82rem;
        color: #6c757d;
        margin-top: 2px;
    }

    .wo-dropdown-item .wo-value {
        font-size: 0.78rem;
        color: #999;
    }

    #work_order_search.wo-selected {
        background-color: #e7f3ff;
        border-color: #0d6efd;
        color: #0d6efd;
        font-weight: 600;
    }

    .wo-type-display {
        display: none;
        margin-top: 6px;
        padding: 5px 10px;
        background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 100%);
        border-right: 3px solid #0d6efd;
        border-radius: 0 6px 6px 0;
        font-size: 0.82rem;
        color: #0d6efd;
        font-weight: 600;
        animation: woFadeIn 0.3s ease;
    }

    .wo-type-display.show {
        display: block;
    }

    @keyframes woFadeIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

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

    /* ألوان تحذيرية للمخزون */
    .table-danger {
        background-color: #f8d7da !important;
        border-color: #f5c6cb !important;
    }

    .table-warning {
        background-color: #fff3cd !important;
        border-color: #ffeaa7 !important;
    }

    /* تحسين مظهر شارات الفرق */
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .badge.bg-warning {
        color: #000 !important;
    }

    /* تحسين مظهر الأعمدة */
    .materials-table td {
        vertical-align: middle;
        padding: 0.5rem 0.25rem;
    }

    .materials-table .quantity-input {
        min-width: 80px;
    }

    /* ===== Mobile Responsive ===== */
    @media (max-width: 991.98px) {
        .container-fluid {
            padding-left: 10px;
            padding-right: 10px;
        }
    }

    @media (max-width: 767.98px) {

        /* Page header */
        .row.mb-4>.col-md-8,
        .row.mb-4>.col-md-4 {
            text-align: center !important;
            margin-bottom: 0.5rem;
        }

        .row.mb-4>.col-md-4 .btn {
            width: 100%;
        }

        .row.mb-4 h2 {
            font-size: 1.2rem;
        }

        /* Form fields stacking */
        .col-md-6,
        .col-md-3 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        /* Card headers */
        .card-header.d-flex {
            flex-direction: column;
            gap: 0.75rem;
            text-align: center;
        }

        .card-header .btn-group {
            width: 100%;
            display: flex;
        }

        .card-header .btn-group .btn {
            flex: 1;
            font-size: 0.78rem;
            padding: 0.4rem 0.5rem;
        }

        /* Materials table on mobile */
        .table-responsive {
            overflow-x: auto !important;
            overflow-y: visible !important;
            -webkit-overflow-scrolling: touch;
        }

        /* Fixed dropdown on mobile to avoid table overflow clipping */
        .materials-table .custom-dropdown.show.mobile-fixed {
            position: fixed !important;
            z-index: 9999;
            max-height: 220px;
            overflow-y: auto;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        .materials-table {
            min-width: 700px;
        }

        .materials-table thead th {
            font-size: 0.72rem;
            padding: 0.4rem 0.2rem;
            white-space: nowrap;
        }

        .materials-table td {
            font-size: 0.78rem;
            padding: 0.35rem 0.15rem !important;
        }

        .materials-table .form-control-sm,
        .materials-table .form-select-sm {
            font-size: 0.75rem;
            padding: 0.2rem 0.3rem;
            min-width: 60px !important;
        }

        .materials-table .quantity-input {
            min-width: 65px !important;
        }

        .materials-table .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }

        /* Action buttons */
        .mt-4.d-flex {
            flex-direction: column-reverse;
            gap: 0.75rem;
        }

        .mt-4.d-flex>div {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .mt-4.d-flex .btn {
            width: 100%;
        }

        /* Summary bar */
        .bg-light.rounded {
            text-align: center;
        }

        /* Work order dropdown */
        .wo-select-container .custom-dropdown {
            max-height: 200px;
        }

        /* Custom dropdown items */
        .dropdown-item-custom,
        .wo-dropdown-item {
            padding: 0.6rem 0.5rem;
            font-size: 0.85rem;
        }

        /* Notes textarea */
        textarea.form-control {
            rows: 2;
        }

        /* Sidebar card */
        #work-order-info .table-sm td {
            font-size: 0.82rem;
            padding: 0.25rem 0.3rem;
        }

        /* Sidebar material search */
        .col-lg-4 .card {
            margin-top: 1rem;
        }
    }

    @media (max-width: 480px) {
        .row.mb-4 h2 {
            font-size: 1.05rem;
        }

        .row.mb-4 p {
            font-size: 0.8rem;
        }

        .card-header h5,
        .card-header h6 {
            font-size: 0.9rem;
        }

        .form-label {
            font-size: 0.85rem;
        }

        .form-text {
            font-size: 0.72rem;
        }

        .wo-type-display {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        .materials-table {
            min-width: 650px;
        }
    }
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>