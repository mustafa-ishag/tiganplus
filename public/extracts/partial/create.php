<?php
/**
 * نموذج إنشاء المستخلص الجزئي
 * Partial Extract Creation Form
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إنشاء مستخلص جزئي';
$currentPage = 'extracts-partial';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'إنشاء مستخلص جزئي', 'url' => 'extracts/partial/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_create')) {
    header('Location: index.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// التحقق من وضع التعديل
$isEdit = false;
$editExtract = null;
$editWorkOrders = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $extractId = (int) $_GET['id'];

    // جلب بيانات المستخلص للتعديل (فقط المسودات)
    $stmt = $db->prepare("
        SELECT pe.*, b.name as branch_name
        FROM partial_extracts pe
        LEFT JOIN branches b ON pe.branch_id = b.id
        WHERE pe.id = ? AND (pe.approval_stage = 'draft' OR pe.approval_stage IS NULL)
    ");
    $stmt->execute([$extractId]);
    $editExtract = $stmt->fetch();

    if ($editExtract) {
        $isEdit = true;

        // جلب أوامر العمل المرتبطة بالمستخلص
        $stmt = $db->prepare("
            SELECT pewo.*, wo.work_order_number, wo.actual_value, wo.department,
                   wot.type_code as work_order_type_code,
                   b.name as branch_name,
                   ce.name as current_entity_name
            FROM partial_extract_work_orders pewo
            JOIN work_orders wo ON pewo.work_order_id = wo.id
            LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
            LEFT JOIN branches b ON wo.branch_id = b.id
            LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
            WHERE pewo.partial_extract_id = ?
        ");
        $stmt->execute([$extractId]);
        $editWorkOrders = $stmt->fetchAll();

        // تحديث عنوان الصفحة
        $pageTitle = 'تعديل المستخلص الجزئي - ' . $editExtract['extract_number'];
        $breadcrumbs[2]['title'] = 'تعديل مستخلص جزئي';
    } else {
        // المستخلص غير موجود أو لا يمكن تعديله
        header('Location: index.php');
        exit();
    }
}

// جلب الفروع
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();

// جلب أوامر العمل المتاحة (التي لم تدخل في أي مستخلصات)
$workOrdersQuery = "
    SELECT wo.*,
           wot.type_code as work_order_type_code,
           wot.description as work_order_type_name,
           b.name as branch_name,
           b.id as branch_id,
           ce.name as current_entity_name,
           CASE
               WHEN wo.department = 'connections' THEN 'التوصيلات'
               WHEN wo.department = 'projects' THEN 'المشاريع'
               ELSE wo.department
           END as department_name
    FROM work_orders wo
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
    WHERE wo.status IN ('active', 'completed')
    AND wo.id NOT IN (
        -- أوامر العمل في المستخلصات الجزئية (جميع الحالات)
        SELECT DISTINCT pewo.work_order_id
        FROM partial_extract_work_orders pewo
        INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id" .
        ($isEdit ? " WHERE pe.id != " . $extractId : "") . "
        UNION
        -- أوامر العمل في المستخلصات النهائية العادية (جميع الحالات)
        SELECT DISTINCT frewo.work_order_id
        FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        UNION
        -- أوامر العمل في المستخلصات النهائية للجزئية (جميع الحالات)
        SELECT DISTINCT ffpewo.work_order_id
        FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    )
    ORDER BY wo.assignment_date DESC
";
$workOrders = $db->query($workOrdersQuery)->fetchAll();

// توليد رقم المستخلص التلقائي (فقط للإنشاء الجديد)
$suggestedExtractNumber = '';
if (!$isEdit) {
    $currentYear = date('Y');
    $lastExtractQuery = "SELECT extract_number FROM partial_extracts WHERE extract_number LIKE 'PE-$currentYear-%' ORDER BY id DESC LIMIT 1";
    $lastExtract = $db->query($lastExtractQuery)->fetch();

    if ($lastExtract) {
        $lastNumber = intval(substr($lastExtract['extract_number'], -3));
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '001';
    }
    $suggestedExtractNumber = "PE-$currentYear-$newNumber";
}

// بدء تخزين المحتوى
ob_start();
?>

<style>
/* إخفاء أزرار الزيادة والنقصان من حقول الأرقام */
input[type=number].extract-value::-webkit-inner-spin-button, 
input[type=number].extract-value::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
input[type=number].extract-value {
    -moz-appearance: textfield;
}
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-bold text-dark mb-1">
            <i class="fas fa-file-invoice text-primary me-2"></i>
            <?php echo $isEdit ? 'تعديل المستخلص الجزئي' : 'إنشاء مستخلص جزئي جديد'; ?>
        </h5>
        <p class="text-muted mb-0 small">إدخال بيانات المستخلص وأوامر العمل المرتبطة به</p>
    </div>
    <a href="<?= path('extracts/partial/index.php') ?>" class="btn btn-light rounded-pill px-3 shadow-sm text-secondary fw-bold border-0">
        <i class="fas fa-arrow-right me-2"></i>العودة للقائمة
    </a>
</div>

<!-- Container for Bootstrap Alerts (Replaces SweetAlert) -->
<div id="alertContainer" class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1050; width: 100%; max-width: 600px;"></div>

<form id="partialExtractForm" method="POST" enctype="multipart/form-data">
    <?php if ($isEdit): ?>
        <input type="hidden" name="extract_id" value="<?php echo $editExtract['id']; ?>">
    <?php endif; ?>

    <!-- Basic Info -->
    <div class="card dash-card shadow-sm border-0 mb-3">
        <div class="card-header bg-white border-0 py-2" style="border-radius: 20px 20px 0 0;">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-info-circle text-primary opacity-75 me-2"></i>معلومات المستخلص الأساسية
            </h6>
        </div>
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">رقم المستخلص <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="extract_number" name="extract_number"
                           value="<?php echo $isEdit ? htmlspecialchars($editExtract['extract_number']) : $suggestedExtractNumber; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">رقم صحيفة الإدخال</label>
                    <input type="text" class="form-control form-control-sm" id="entry_sheet_number" name="entry_sheet_number"
                           pattern="[0-9]{10}" maxlength="10" placeholder="10 أرقام"
                           value="<?php echo $isEdit ? htmlspecialchars($editExtract['entry_sheet_number'] ?? '') : ''; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">تاريخ المستخلص <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" id="extract_date" name="extract_date"
                           value="<?php echo $isEdit ? $editExtract['extract_date'] : date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">رقم الفاتورة</label>
                    <input type="text" class="form-control form-control-sm" id="invoice_number" name="invoice_number"
                           value="<?php echo $isEdit ? htmlspecialchars($editExtract['invoice_number'] ?? '') : ''; ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">الفرع <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="branch_id" name="branch_id" required>
                        <option value="">سيتم تحديده تلقائياً</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>"
                                    <?php echo ($isEdit && $editExtract['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">القسم <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="department" name="department" required>
                        <option value="">سيتم تحديده تلقائياً</option>
                        <option value="connections" <?php echo ($isEdit && $editExtract['department'] == 'connections') ? 'selected' : ''; ?>>التوصيلات</option>
                        <option value="projects" <?php echo ($isEdit && $editExtract['department'] == 'projects') ? 'selected' : ''; ?>>المشاريع</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold mb-1">ملاحظات المستخلص</label>
                    <input type="text" class="form-control form-control-sm" id="description" name="description"
                           placeholder="ملاحظات وتفاصيل إضافية..." value="<?php echo $isEdit ? htmlspecialchars($editExtract['description'] ?? '') : ''; ?>">
                </div>
            </div>
            <div id="autoSelectionMessageContainer" class="mt-2"></div>
        </div>
    </div>

    <!-- Split view for Work Orders -->
    <div class="row g-3 mb-3">
        <!-- Available Work Orders (Right side in RTL) -->
        <div class="col-lg-6">
            <div class="card dash-card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-list text-primary opacity-75 me-2"></i>أوامر العمل المتاحة
                        <span class="badge bg-primary-soft text-primary rounded-pill ms-1" id="availableCount"><?php echo count($workOrders); ?></span>
                    </h6>
                    <div style="max-width: 200px;">
                        <input type="tel" inputmode="numeric" pattern="[0-9]*" id="customSearchAvailable" class="form-control form-control-sm rounded-pill px-3 shadow-none border-1" placeholder="ابحث برقم الأمر...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table id="availableWorkOrdersTable" class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                            <thead style="background-color: #f8fafc; color: #64748b; position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th class="ps-3 border-0">رقم الأمر</th>
                                    <th class="border-0">النوع</th>
                                    <th class="border-0">القيمة الفعلية</th>
                                    <th class="pe-3 border-0 text-center">إضافة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workOrders as $wo): ?>
                                    <tr data-work-order-id="<?php echo $wo['id']; ?>">
                                        <td class="ps-3 fw-bold text-dark"><?php echo htmlspecialchars($wo['work_order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($wo['work_order_type_code']); ?></td>
                                        <td class="text-success fw-bold"><?php echo number_format($wo['actual_value'] ?: $wo['estimated_value'], 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></td>
                                        <td class="pe-3 text-center">
                                            <button type="button" class="btn btn-sm btn-light text-primary border-0 shadow-sm add-work-order" style="border-radius: 0.5rem; width: 32px; height: 32px; padding: 0;"
                                                    data-id="<?php echo $wo['id']; ?>"
                                                    data-number="<?php echo htmlspecialchars($wo['work_order_number']); ?>"
                                                    data-type="<?php echo htmlspecialchars($wo['work_order_type_code']); ?>"
                                                    data-department="<?php echo htmlspecialchars($wo['department']); ?>"
                                                    data-department-name="<?php echo htmlspecialchars($wo['department_name']); ?>"
                                                    data-branch-id="<?php echo $wo['branch_id']; ?>"
                                                    data-branch-name="<?php echo htmlspecialchars($wo['branch_name']); ?>"
                                                    data-value="<?php echo $wo['actual_value'] ?: $wo['estimated_value']; ?>"
                                                    data-receipt-date="<?php echo $wo['receipt_date'] ?? ''; ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selected Work Orders (Left side in RTL) -->
        <div class="col-lg-6">
            <div class="card dash-card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-check-circle text-success opacity-75 me-2"></i>أوامر العمل المختارة
                    </h6>
                    <span class="badge bg-success-soft text-success rounded-pill px-3 py-1" id="selectedCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div id="selectedWorkOrdersContainer" class="h-100 d-flex flex-column">
                        <div class="text-center text-muted py-5 my-auto" id="emptyMessage">
                            <div class="icon-circle bg-light mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <i class="fas fa-inbox text-muted"></i>
                            </div>
                            <p class="mb-0 fw-bold">لم يتم اختيار أي أوامر عمل بعد</p>
                            <small>اختر من القائمة المجاورة لإضافتها للمستخلص</small>
                        </div>
                        
                        <div id="selectedWorkOrdersTable" style="display: none;" class="table-responsive h-100">
                            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                <thead style="background-color: #f8fafc; color: #64748b; position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th class="ps-3 border-0">رقم الأمر</th>
                                        <th class="border-0">قيمة المستخلص <span class="text-danger">*</span></th>
                                        <th class="border-0">تاريخ الإنجاز <span class="text-danger">*</span></th>
                                        <th class="pe-3 border-0 text-center">إزالة</th>
                                    </tr>
                                </thead>
                                <tbody id="selectedWorkOrdersBody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Summary & Attachments -->
    <div class="row g-3 mb-4">
        <!-- Attachments -->
        <div class="col-lg-5">
            <div class="card dash-card shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-paperclip text-primary me-2"></i>المرفقات</h6>
                    <input type="file" class="form-control form-control-sm mb-1" id="attachments" name="attachments[]" multiple 
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    <small class="text-muted d-block" style="font-size: 0.75rem;">يمكنك رفع ملفات PDF, Word, Excel, أو صور</small>
                    <div id="attachmentsList" class="mt-2"></div>
                </div>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="col-lg-7">
            <div class="card dash-card shadow-sm border-0 h-100 bg-primary-soft">
                <div class="card-body py-3 d-flex flex-column justify-content-center">
                    <div class="row text-center g-2 align-items-center">
                        <div class="col-3 px-3">
                            <small class="text-muted fw-bold d-block mb-1">إجمالي المبلغ</small>
                            <h5 class="text-dark fw-bold mb-0" id="totalAmount">0.00 <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></h5>
                        </div>
                        <div class="col-1 text-muted"><i class="fas fa-plus"></i></div>
                        <div class="col-3 border-start border-end border-primary border-opacity-25 px-3">
                            <small class="text-muted fw-bold d-block mb-1">الضريبة (15%)</small>
                            <h5 class="text-dark fw-bold mb-0" id="taxAmount">0.00 <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></h5>
                        </div>
                        <div class="col-1 text-muted"><i class="fas fa-equals"></i></div>
                        <div class="col-4">
                            <small class="text-muted fw-bold d-block mb-1">الصافي (بدون ضريبة)</small>
                            <h4 class="text-success fw-bold mb-0" id="netAmount">0.00 <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                        </div>
                    </div>
                    
                    <hr class="my-2 border-primary opacity-25">
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-primary fw-bold"><i class="fas fa-info-circle me-1"></i> يتم حساب الصافي بدون إضافة الضريبة للرقم النهائي.</small>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" name="action" value="submit">
                            <i class="fas fa-check me-2"></i>
                            <?php echo $isEdit ? 'حفظ التعديلات' : 'إنشاء المستخلص'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// Bootstrap Alert function (replaces SweetAlert)
function showAlert(message, type = 'danger') {
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm border-0 d-flex align-items-center" role="alert" style="border-radius: 12px;">
            <i class="fas ${icon} fs-4 me-3"></i>
            <div>
                <strong>${type === 'success' ? 'نجاح!' : 'تنبيه!'}</strong> ${message}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    const container = $('#alertContainer');
    container.html(alertHtml);
    
    // Auto dismiss success alerts
    if (type === 'success') {
        setTimeout(() => {
            container.find('.alert').alert('close');
        }, 3000);
    }
}

$(document).ready(function() {
    var availableTable = $('#availableWorkOrdersTable').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json' },
        pageLength: 50,
        scrollY: "280px",
        scrollCollapse: true,
        info: false,
        dom: 't', // Search handled by custom input
        order: [[0, 'desc']]
    });

    // Custom search input functionality (numeric only)
    $('#customSearchAvailable').on('input', function() {
        var val = $(this).val().replace(/[^0-9]/g, '');
        if ($(this).val() !== val) $(this).val(val);
        availableTable.search(val).draw();
    });

    let selectedWorkOrders = [];

    // Load existing work orders for editing
    <?php if ($isEdit && !empty($editWorkOrders)): ?>
    <?php foreach ($editWorkOrders as $ewo): ?>
    selectedWorkOrders.push({
        id: <?php echo $ewo['work_order_id']; ?>,
        number: '<?php echo htmlspecialchars($ewo['work_order_number']); ?>',
        type: '<?php echo htmlspecialchars($ewo['work_order_type_code']); ?>',
        value: <?php echo $ewo['actual_value'] ?: 0; ?>,
        extractValue: <?php echo $ewo['extract_value']; ?>,
        completionDate: '<?php echo $ewo['completion_date']; ?>',
        department: '<?php echo $ewo['department']; ?>',
        departmentName: '<?php echo $ewo['department'] == 'connections' ? 'التوصيلات' : 'المشاريع'; ?>',
        branchId: <?php echo $editExtract['branch_id']; ?>,
        branchName: '<?php echo htmlspecialchars($ewo['branch_name']); ?>'
    });
    <?php endforeach; ?>
    setTimeout(function() {
        updateSelectedWorkOrders();
        updateSummary();
    }, 100);
    <?php endif; ?>

    // Add Work Order
    $(document).on('click', '.add-work-order', function() {
        const id = $(this).data('id');
        const number = $(this).data('number');
        const type = $(this).data('type');
        const value = $(this).data('value');
        const department = $(this).data('department');
        const departmentName = $(this).data('department-name');
        const branchId = $(this).data('branch-id');
        const branchName = $(this).data('branch-name');
        const receiptDate = $(this).data('receipt-date');
        
        let completionDate = receiptDate ? receiptDate : '';

        if (selectedWorkOrders.find(wo => wo.id === id)) {
            showAlert('تم إضافة هذا الأمر مسبقاً', 'warning');
            return;
        }

        if (selectedWorkOrders.length === 0) {
            $('#branch_id').val(branchId).trigger('change');
            $('#department').val(department).trigger('change');
            showAutoSelectionMessage(branchName, departmentName);
        } else {
            const currentBranch = $('#branch_id').val();
            const currentDepartment = $('#department').val();

            if (currentBranch != branchId && !confirm(`أمر العمل من فرع مختلف (${branchName}). هل تريد المتابعة؟`)) return;
            if (currentDepartment != department && !confirm(`أمر العمل من قسم مختلف (${departmentName}). هل تريد المتابعة؟`)) return;
        }

        selectedWorkOrders.push({
            id: id, number: number, type: type, value: value,
            department: department, departmentName: departmentName,
            branchId: branchId, branchName: branchName,
            completionDate: completionDate, extractValue: value // Default to full value
        });

        $(this).closest('tr').hide();

        updateSelectedWorkOrders();
        updateSummary();
    });

    // Remove Work Order
    $(document).on('click', '.remove-work-order', function() {
        const id = parseInt($(this).data('id'));
        selectedWorkOrders = selectedWorkOrders.filter(wo => wo.id !== id);
        
        $(`tr[data-work-order-id="${id}"]`).show();

        if (selectedWorkOrders.length === 0) {
            $('#branch_id').val('');
            $('#department').val('');
            $('#autoSelectionMessageContainer').empty();
        }

        updateSelectedWorkOrders();
        updateSummary();
    });

    $(document).on('input', '.extract-value', function() { updateSummary(); });

    function updateSelectedWorkOrders() {
        const table = $('#selectedWorkOrdersTable');
        const tbody = $('#selectedWorkOrdersBody');
        const emptyMessage = $('#emptyMessage');
        
        if (selectedWorkOrders.length === 0) {
            table.hide();
            emptyMessage.removeClass('d-none').show();
        } else {
            emptyMessage.addClass('d-none').hide();
            table.show();
            
            tbody.empty();
            selectedWorkOrders.forEach(wo => {
                tbody.append(`
                    <tr>
                        <td class="ps-3 fw-bold text-dark">
                            ${wo.number} <span class="badge bg-secondary text-white ms-1" style="font-size: 0.65rem; padding: 0.2rem 0.4rem;">${wo.type}</span>
                            <div class="small text-muted fw-normal">${parseFloat(wo.value).toLocaleString('ar-SA')} <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></div>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm extract-value fw-bold text-primary rounded-3 shadow-none border-primary px-2"
                                   name="extract_values[${wo.id}]" step="0.01" min="0"
                                   value="${wo.extractValue}" required style="min-width: 140px; font-size: 0.85rem;">
                        </td>
                        <td>
                            <input type="date" class="form-control form-control-sm completion-date rounded-3 shadow-none border-secondary text-dark px-2"
                                   name="completion_dates[${wo.id}]" value="${wo.completionDate}" required style="min-width: 150px; font-size: 0.85rem;">
                        </td>
                        <td class="pe-3 text-center">
                            <button type="button" class="btn btn-sm btn-light text-danger border-0 shadow-sm remove-work-order" data-id="${wo.id}" style="border-radius: 0.5rem; width: 32px; height: 32px; padding: 0;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
        $('#selectedCount').text(selectedWorkOrders.length);
    }

    function updateSummary() {
        let total = 0;
        $('.extract-value').each(function() {
            total += parseFloat($(this).val()) || 0;
        });

        const tax = total * 0.15;
        const net = total;

        const iconHtml = ' <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span>';
        $('#totalAmount').html(total.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + iconHtml);
        $('#taxAmount').html(tax.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + iconHtml);
        $('#netAmount').html(net.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + iconHtml);
    }

    function showAutoSelectionMessage(branchName, departmentName) {
        const alertHtml = `
            <div class="alert alert-success alert-dismissible fade show py-2 mb-0 d-inline-flex align-items-center" role="alert" style="border-radius: 8px;">
                <i class="fas fa-check-circle me-2"></i>
                <small>تم التحديد التلقائي للفرع (<strong>${branchName}</strong>) والقسم (<strong>${departmentName}</strong>).</small>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" style="padding: 0.75rem;"></button>
            </div>
        `;
        $('#autoSelectionMessageContainer').html(alertHtml);
        setTimeout(() => $('#autoSelectionMessageContainer').empty(), 5000);
    }

    $('#entry_sheet_number').on('input', function() {
        const val = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(val);
        if (val.length > 0 && val.length !== 10) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    // التنقل بالأسهم بين حقول الجدول المختار للتحرك في جميع الاتجاهات
    $(document).on('keydown', '#selectedWorkOrdersTable input', function(e) {
        const keys = ['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Enter'];
        if (keys.includes(e.key)) {
            e.preventDefault();
            const $this = $(this);
            const $currentRow = $this.closest('tr');
            const $inputsInRow = $currentRow.find('input:visible');
            const colIndex = $inputsInRow.index(this);
            
            let $targetInput = null;

            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                const $targetRow = $currentRow.next('tr');
                if ($targetRow.length) $targetInput = $targetRow.find('input:visible').eq(colIndex);
            } else if (e.key === 'ArrowUp') {
                const $targetRow = $currentRow.prev('tr');
                if ($targetRow.length) $targetInput = $targetRow.find('input:visible').eq(colIndex);
            } else if (e.key === 'ArrowLeft') {
                if (colIndex < $inputsInRow.length - 1) $targetInput = $inputsInRow.eq(colIndex + 1);
            } else if (e.key === 'ArrowRight') {
                if (colIndex > 0) $targetInput = $inputsInRow.eq(colIndex - 1);
            }
            
            if ($targetInput && $targetInput.length) {
                $targetInput.focus();
                $targetInput.select();
            }
        }
    });

    $('#partialExtractForm').on('submit', function(e) {
        e.preventDefault();

        if (selectedWorkOrders.length === 0) {
            showAlert('يجب إضافة أمر عمل واحد على الأقل', 'danger');
            return false;
        }

        const entrySheetNumber = $('#entry_sheet_number').val();
        if (entrySheetNumber && entrySheetNumber.length !== 10) {
            showAlert('رقم صحيفة الإدخال يجب أن يكون مكون من 10 أرقام بالضبط', 'danger');
            return false;
        }

        const $form = $(this);
        $form.find('input[name="work_order_ids[]"]').remove(); // clear previous
        selectedWorkOrders.forEach(wo => {
            $form.append(`<input type="hidden" name="work_order_ids[]" value="${wo.id}">`);
        });

        const formData = new FormData($form[0]);
        const submitBtn = $form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...');

        $.ajax({
            url: 'create-ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(() => {
                        window.location.href = response.redirect_url || 'index.php';
                    }, 1500);
                } else {
                    showAlert(response.message, 'danger');
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = xhr.responseJSON?.message || 'حدث خطأ أثناء حفظ المستخلص';
                showAlert(errorMessage, 'danger');
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
