<?php
/**
 * صفحة تعديل المستخلص النهائي العادي
 * Edit Final Regular Extract Page
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_edit')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// التحقق من وجود معرف المستخلص
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$extractId = (int) $_GET['id'];

// جلب بيانات المستخلص للتعديل
$extractQuery = "
    SELECT fre.*,
           b.name as branch_name,
           b.id as branch_id
    FROM final_regular_extracts fre
    LEFT JOIN branches b ON fre.branch_id = b.id
    WHERE fre.id = ? AND (fre.approval_stage = 'draft' OR fre.approval_stage IS NULL)
";

$stmt = $db->prepare($extractQuery);
$stmt->execute([$extractId]);
$extract = $stmt->fetch();

if (!$extract) {
    header('Location: index.php');
    exit();
}

// جلب الفروع
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();

// جلب أوامر العمل المرتبطة بالمستخلص
$workOrdersQuery = "
    SELECT frewo.*,
           wo.work_order_number,
           wo.actual_value,
           wo.estimated_value,
           wo.department,
           wo.branch_id as work_order_branch_id,
           wot.type_code as work_order_type_code,
           wot.description as work_order_type_description,
           b.name as work_order_branch_name,
           CASE
               WHEN wo.department = 'connections' THEN 'التوصيلات'
               WHEN wo.department = 'projects' THEN 'المشاريع'
               ELSE wo.department
           END as department_name
    FROM final_regular_extract_work_orders frewo
    INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    WHERE frewo.final_regular_extract_id = ?
    ORDER BY wo.work_order_number
";

$stmt = $db->prepare($workOrdersQuery);
$stmt->execute([$extractId]);
$extractWorkOrders = $stmt->fetchAll();

// جلب أوامر العمل المتاحة (التي لم تدخل في مستخلصات أخرى)
$availableWorkOrdersQuery = "
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
        -- أوامر العمل في المستخلصات الجزئية
        SELECT DISTINCT pewo.work_order_id
        FROM partial_extract_work_orders pewo
        INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
        UNION
        -- أوامر العمل في المستخلصات النهائية العادية (باستثناء المستخلص الحالي)
        SELECT DISTINCT frewo.work_order_id
        FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE fre.id != ?
        UNION
        -- أوامر العمل في المستخلصات النهائية للجزئية
        SELECT DISTINCT ffpewo.work_order_id
        FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    )
    ORDER BY wo.assignment_date DESC
";

$stmt = $db->prepare($availableWorkOrdersQuery);
$stmt->execute([$extractId]);
$availableWorkOrders = $stmt->fetchAll();

// إعداد متغيرات الصفحة
$pageTitle = 'تعديل المستخلص النهائي العادي - ' . $extract['extract_number'];
$currentPage = 'extracts';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية العادية', 'url' => 'extracts/final-regular/index.php'],
    ['title' => 'تعديل المستخلص', 'url' => '']
];

// بدء تخزين المحتوى
ob_start();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="h4 mb-1">
                        <i class="fas fa-edit text-warning me-2"></i>
                        تعديل المستخلص النهائي العادي
                    </h2>
                    <p class="text-muted mb-0">تعديل المستخلص رقم: <?= htmlspecialchars($extract['extract_number']) ?></p>
                </div>
                <div>
                    <a href="view.php?id=<?= $extractId ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-eye me-1"></i>
                        عرض التفاصيل
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right me-1"></i>
                        العودة للقائمة
                    </a>
                </div>
            </div>

            <!-- تنبيه حالة المستخلص -->
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>ملاحظة:</strong> يمكن تعديل المستخلص فقط في مرحلة المسودة. بعد التقديم لن يكون التعديل متاحاً.
            </div>

            <!-- نموذج تعديل المستخلص -->
            <form id="editFinalRegularExtractForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="extract_id" value="<?= $extractId ?>">
                
                <div class="row">
                    <!-- معلومات المستخلص الأساسية -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle text-warning me-2"></i>
                                    معلومات المستخلص الأساسية
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="extract_number" class="form-label">رقم المستخلص <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="extract_number" name="extract_number" 
                                               value="<?= htmlspecialchars($extract['extract_number']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">رقم الفاتورة</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                                               value="<?= htmlspecialchars($extract['invoice_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="branch_id" class="form-label">الفرع <span class="text-danger">*</span></label>
                                        <select class="form-select" id="branch_id" name="branch_id" required>
                                            <option value="">اختر الفرع</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $extract['branch_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($branch['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="extract_date" class="form-label">تاريخ المستخلص <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="extract_date" name="extract_date"
                                               value="<?= htmlspecialchars($extract['extract_date']) ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="description" class="form-label">وصف المستخلص</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="وصف تفصيلي للمستخلص النهائي العادي..."><?= htmlspecialchars($extract['description'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الجزء الأول: أوامر العمل المتاحة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list text-primary me-2"></i>
                                    أوامر العمل المتاحة للإضافة
                                    <span class="badge bg-secondary ms-2" id="availableCount"><?= count($availableWorkOrders) ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="availableWorkOrdersTable" class="table table-sm table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>رقم الأمر</th>
                                                <th>كود النوع</th>
                                                <th>القيمة الفعلية</th>
                                                <th>الإجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($availableWorkOrders as $wo): ?>
                                                <tr data-work-order-id="<?= $wo['id'] ?>">
                                                    <td><?= htmlspecialchars($wo['work_order_number']) ?></td>
                                                    <td><?= htmlspecialchars($wo['work_order_type_code']) ?></td>
                                                    <td><?= number_format($wo['actual_value'] ?: $wo['estimated_value'], 2) ?> ريال</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary add-work-order"
                                                                data-id="<?= $wo['id'] ?>"
                                                                data-number="<?= htmlspecialchars($wo['work_order_number']) ?>"
                                                                data-type="<?= htmlspecialchars($wo['work_order_type_code']) ?>"
                                                                data-department="<?= htmlspecialchars($wo['department']) ?>"
                                                                data-department-name="<?= htmlspecialchars($wo['department_name']) ?>"
                                                                data-branch-id="<?= $wo['branch_id'] ?>"
                                                                data-branch-name="<?= htmlspecialchars($wo['branch_name']) ?>"
                                                                data-value="<?= $wo['actual_value'] ?: $wo['estimated_value'] ?>"
                                                                data-receipt-date="<?= $wo['receipt_date'] ?? '' ?>"
                                                                data-assignment-date="<?= $wo['assignment_date'] ?? '' ?>">
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

                        <!-- الجزء الثاني: أوامر العمل المختارة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    أوامر العمل المختارة
                                    <span class="badge bg-success ms-2" id="selectedCount"><?= count($extractWorkOrders) ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="selectedWorkOrdersContainer">
                                    <div class="text-center text-muted py-4" id="emptyMessage" style="<?= count($extractWorkOrders) > 0 ? 'display: none;' : '' ?>">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>لم يتم اختيار أي أوامر عمل بعد</p>
                                        <small>استخدم القسم أعلاه لإضافة أوامر العمل</small>
                                    </div>
                                </div>

                                <div id="selectedWorkOrdersTable" style="<?= count($extractWorkOrders) > 0 ? '' : 'display: none;' ?>">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>رقم الأمر</th>
                                                    <th>النوع</th>
                                                    <th>القيمة الفعلية</th>
                                                    <th>قيمة المستخلص <span class="text-danger">*</span></th>
                                                    <th>تاريخ الإنجاز <span class="text-danger">*</span></th>
                                                    <th>الغرامة</th>
                                                    <th>الإجراء</th>
                                                </tr>
                                            </thead>
                                            <tbody id="selectedWorkOrdersBody">
                                                <?php foreach ($extractWorkOrders as $wo): ?>
                                                <tr data-work-order-id="<?= $wo['work_order_id'] ?>">
                                                    <td><?= htmlspecialchars($wo['work_order_number']) ?></td>
                                                    <td><?= htmlspecialchars($wo['work_order_type_code']) ?></td>
                                                    <td><?= number_format($wo['actual_value'] ?: $wo['estimated_value'], 2) ?> ريال</td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control form-control-sm extract-value"
                                                               name="extract_values[<?= $wo['work_order_id'] ?>]"
                                                               value="<?= $wo['extract_value'] ?>"
                                                               required>
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control form-control-sm completion-date"
                                                               name="completion_dates[<?= $wo['work_order_id'] ?>]"
                                                               value="<?= $wo['completion_date'] ?>"
                                                               required>
                                                    </td>
                                                    <td>
                                                        <input type="number" step="0.01" class="form-control form-control-sm penalty-amount"
                                                               name="penalty_amounts[<?= $wo['work_order_id'] ?>]"
                                                               value="<?= $wo['penalty_amount'] ?? 0 ?>"
                                                               min="0">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-danger remove-work-order">
                                                            <i class="fas fa-times"></i>
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
                    </div>

                    <!-- الشريط الجانبي -->
                    <div class="col-lg-4">
                        <!-- ملخص المستخلص -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calculator text-warning me-2"></i>
                                    ملخص المستخلص
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-1">إجمالي المبلغ</h6>
                                            <h4 class="text-success mb-0" id="totalAmount">0.00 ريال</h4>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted">الضريبة (15%)</small>
                                            <div class="fw-bold" id="taxAmount">0.00 ريال</div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <small class="text-muted">إجمالي الغرامات</small>
                                            <div class="fw-bold text-danger" id="totalPenalty">0.00 ريال</div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="border rounded p-3 bg-light">
                                            <h6 class="text-muted mb-1">الصافي</h6>
                                            <h4 class="text-primary mb-0" id="netAmount">0.00 ريال</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- أزرار الحفظ -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="fas fa-save me-2"></i>
                                        حفظ التعديلات
                                    </button>
                                    <a href="view.php?id=<?= $extractId ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        إلغاء
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTables
    $('#availableWorkOrdersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        pageLength: 10,
        order: [[0, 'desc']]
    });

    // مصفوفة أوامر العمل المختارة
    let selectedWorkOrders = [];

    // تحميل أوامر العمل المختارة من البيانات الموجودة
    <?php foreach ($extractWorkOrders as $wo): ?>
    selectedWorkOrders.push({
        id: <?= $wo['work_order_id'] ?>,
        number: '<?= htmlspecialchars($wo['work_order_number']) ?>',
        type: '<?= htmlspecialchars($wo['work_order_type_code']) ?>',
        department: '<?= htmlspecialchars($wo['department']) ?>',
        departmentName: '<?= htmlspecialchars($wo['department_name']) ?>',
        branchId: <?= $wo['work_order_branch_id'] ?>,
        branchName: '<?= htmlspecialchars($wo['work_order_branch_name']) ?>',
        value: <?= $wo['actual_value'] ?: $wo['estimated_value'] ?>,
        extractValue: <?= $wo['extract_value'] ?>,
        completionDate: '<?= $wo['completion_date'] ?>',
        penaltyAmount: <?= $wo['penalty_amount'] ?? 0 ?>
    });
    <?php endforeach; ?>

    // تحديث العدادات والملخص عند التحميل
    updateCounts();
    updateSummary();

    // إضافة أمر عمل
    $(document).on('click', '.add-work-order', function() {
        const btn = $(this);
        const workOrder = {
            id: btn.data('id'),
            number: btn.data('number'),
            type: btn.data('type'),
            department: btn.data('department'),
            departmentName: btn.data('department-name'),
            branchId: btn.data('branch-id'),
            branchName: btn.data('branch-name'),
            value: parseFloat(btn.data('value')),
            receiptDate: btn.data('receipt-date'),
            assignmentDate: btn.data('assignment-date')
        };

        // التحقق من عدم التكرار
        if (selectedWorkOrders.find(wo => wo.id === workOrder.id)) {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه!',
                text: 'أمر العمل موجود بالفعل في القائمة',
                confirmButtonText: 'موافق'
            });
            return;
        }

        // إضافة إلى المصفوفة
        selectedWorkOrders.push(workOrder);

        // إضافة الصف إلى الجدول
        addWorkOrderRow(workOrder);

        // إخفاء الصف من الجدول المتاح
        btn.closest('tr').hide();

        // تحديث العدادات والملخص
        updateCounts();
        updateSummary();
    });

    // إزالة أمر عمل
    $(document).on('click', '.remove-work-order', function() {
        const row = $(this).closest('tr');
        const workOrderId = parseInt(row.data('work-order-id'));

        // إزالة من المصفوفة
        selectedWorkOrders = selectedWorkOrders.filter(wo => wo.id !== workOrderId);

        // إزالة الصف
        row.remove();

        // إظهار الصف في الجدول المتاح
        $(`#availableWorkOrdersTable tr[data-work-order-id="${workOrderId}"]`).show();

        // تحديث العدادات والملخص
        updateCounts();
        updateSummary();
    });

    // تحديث الملخص عند تغيير القيم
    $(document).on('input', '.extract-value, .penalty-amount', function() {
        updateSummary();
    });

    // دالة إضافة صف أمر عمل
    function addWorkOrderRow(wo) {
        const completionDate = wo.completionDate || wo.receiptDate || wo.assignmentDate || '';
        const extractValue = wo.extractValue || wo.value || 0;
        const penaltyAmount = wo.penaltyAmount || 0;

        const row = `
            <tr data-work-order-id="${wo.id}">
                <td>${wo.number}</td>
                <td>${wo.type}</td>
                <td>${wo.value.toLocaleString('ar-SA', {minimumFractionDigits: 2})} ريال</td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm extract-value"
                           name="extract_values[${wo.id}]" value="${extractValue}" required>
                </td>
                <td>
                    <input type="date" class="form-control form-control-sm completion-date"
                           name="completion_dates[${wo.id}]" value="${completionDate}" required>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm penalty-amount"
                           name="penalty_amounts[${wo.id}]" value="${penaltyAmount}" min="0">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-work-order">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#selectedWorkOrdersBody').append(row);
        $('#selectedWorkOrdersTable').show();
        $('#emptyMessage').hide();
    }

    // تحديث العدادات
    function updateCounts() {
        $('#selectedCount').text(selectedWorkOrders.length);

        if (selectedWorkOrders.length === 0) {
            $('#selectedWorkOrdersTable').hide();
            $('#emptyMessage').show();
        } else {
            $('#selectedWorkOrdersTable').show();
            $('#emptyMessage').hide();
        }
    }

    // تحديث الملخص
    function updateSummary() {
        let total = 0;
        let totalPenalty = 0;

        $('.extract-value').each(function() {
            const value = parseFloat($(this).val()) || 0;
            total += value;
        });

        $('.penalty-amount').each(function() {
            const penalty = parseFloat($(this).val()) || 0;
            totalPenalty += penalty;
        });

        const tax = total * 0.15;
        const net = total + tax - totalPenalty;

        $('#totalAmount').text(total.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#taxAmount').text(tax.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#totalPenalty').text(totalPenalty.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#netAmount').text(net.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
    }

    // تقديم النموذج
    $('#editFinalRegularExtractForm').on('submit', function(e) {
        e.preventDefault();

        if (selectedWorkOrders.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه!',
                text: 'يجب إضافة أمر عمل واحد على الأقل',
                confirmButtonText: 'موافق'
            });
            return false;
        }

        // إضافة معرفات أوامر العمل المختارة
        selectedWorkOrders.forEach(wo => {
            if (!$(`input[name="work_order_ids[]"][value="${wo.id}"]`).length) {
                $(this).append(`<input type="hidden" name="work_order_ids[]" value="${wo.id}">`);
            }
        });

        // إرسال النموذج عبر AJAX
        submitForm($(this));
    });

    // دالة إرسال النموذج عبر AJAX
    function submitForm($form) {
        const formData = new FormData($form[0]);

        // إظهار مؤشر التحميل
        const submitBtn = $form.find('button[type="submit"]').first();
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...');

        $.ajax({
            url: 'edit-ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // إظهار رسالة نجاح
                    Swal.fire({
                        icon: 'success',
                        title: 'تم بنجاح!',
                        text: response.message,
                        confirmButtonText: 'موافق'
                    }).then(() => {
                        // إعادة التوجيه إلى صفحة العرض أو القائمة
                        if (response.redirect_url) {
                            window.location.href = response.redirect_url;
                        } else {
                            window.location.href = 'view.php?id=<?= $extractId ?>';
                        }
                    });
                } else {
                    // إظهار رسالة خطأ
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: response.message || 'حدث خطأ أثناء حفظ التعديلات',
                        confirmButtonText: 'موافق'
                    });
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);

                let errorMessage = 'حدث خطأ أثناء حفظ التعديلات';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: errorMessage,
                    confirmButtonText: 'موافق'
                });
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>
