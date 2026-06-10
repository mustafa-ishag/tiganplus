<?php
/**
 * صفحة تعديل المستخلص النهائي للجزئية
 * Edit Final for Partial Extract Page
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
    SELECT ffpe.*,
           b.name as branch_name,
           pe.extract_number as related_partial_extract_number,
           pe.id as related_partial_extract_id,
           pe.extract_date as partial_extract_date,
           pe.total_amount as partial_total_amount,
           pe.net_amount as partial_net_amount
    FROM final_for_partial_extracts ffpe
    LEFT JOIN branches b ON ffpe.branch_id = b.id
    LEFT JOIN partial_extracts pe ON ffpe.related_partial_extract_id = pe.id
    WHERE ffpe.id = ? AND (ffpe.approval_stage = 'draft' OR ffpe.approval_stage IS NULL)
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
    SELECT ffpewo.*,
           wo.work_order_number,
           wo.actual_value,
           wo.estimated_value,
           wo.department,
           wot.type_code as work_order_type_code,
           wot.description as work_order_type_description,
           b.name as work_order_branch_name,
           CASE
               WHEN wo.department = 'connections' THEN 'التوصيلات'
               WHEN wo.department = 'projects' THEN 'المشاريع'
               ELSE wo.department
           END as department_name
    FROM final_for_partial_extract_work_orders ffpewo
    INNER JOIN work_orders wo ON ffpewo.work_order_id = wo.id
    LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
    LEFT JOIN branches b ON wo.branch_id = b.id
    WHERE ffpewo.final_for_partial_extract_id = ?
    ORDER BY wo.work_order_number
";

$stmt = $db->prepare($workOrdersQuery);
$stmt->execute([$extractId]);
$extractWorkOrders = $stmt->fetchAll();

// جلب المستخلصات الجزئية المؤهلة (للتبديل إذا لزم الأمر)
$partialExtractsQuery = "
    SELECT pe.*,
           b.name as branch_name,
           COUNT(DISTINCT pewo.id) as total_work_orders,
           COUNT(DISTINCT CASE WHEN cc.completion_certificate_confirmation = 'confirmed' THEN pewo.id END) as confirmed_certificates
    FROM partial_extracts pe
    LEFT JOIN branches b ON pe.branch_id = b.id
    LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
    LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
    LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
    LEFT JOIN final_for_partial_extracts ffpe ON pe.id = ffpe.related_partial_extract_id
    WHERE pe.approval_stage IN ('disbursed', 'taif_finance')
    AND (ffpe.id IS NULL OR ffpe.id = ?)
    GROUP BY pe.id
    HAVING total_work_orders > 0 AND confirmed_certificates = total_work_orders
    ORDER BY pe.extract_date DESC
";

$stmt = $db->prepare($partialExtractsQuery);
$stmt->execute([$extractId]);
$partialExtracts = $stmt->fetchAll();

// إعداد متغيرات الصفحة
$pageTitle = 'تعديل المستخلص النهائي للجزئية - ' . $extract['extract_number'];
$currentPage = 'extracts';
$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية للجزئية', 'url' => 'extracts/final-for-partial/index.php'],
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
                        تعديل المستخلص النهائي للجزئية
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
            <form id="editFinalForPartialExtractForm" method="POST" enctype="multipart/form-data">
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
                                               value="<?= htmlspecialchars($extract['extract_number']) ?>" readonly required>
                                        <small class="text-muted">رقم المستخلص لا يمكن تعديله</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">رقم الفاتورة</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                                               value="<?= htmlspecialchars($extract['invoice_number'] ?? '') ?>"
                                               placeholder="رقم الفاتورة (اختياري)">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="related_partial_extract_display" class="form-label">المستخلص الجزئي المرتبط</label>
                                        <input type="text" class="form-control" id="related_partial_extract_display"
                                               value="<?= htmlspecialchars($extract['related_partial_extract_number']) ?>" readonly>
                                        <input type="hidden" name="related_partial_extract_id" value="<?= $extract['related_partial_extract_id'] ?>">
                                        <small class="text-muted">لا يمكن تغيير المستخلص الجزئي المرتبط بعد الإنشاء</small>
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
                                               value="<?= $extract['extract_date'] ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="description" class="form-label">وصف المستخلص</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="وصف تفصيلي للمستخلص النهائي للجزئية..."><?= htmlspecialchars($extract['description'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- معلومات المستخلص الجزئي المرتبط -->
                        <div class="card shadow-sm mb-4" id="relatedPartialInfo" style="<?= $extract['related_partial_extract_id'] ? '' : 'display: none;' ?>">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-link text-info me-2"></i>
                                    معلومات المستخلص الجزئي المرتبط
                                </h5>
                            </div>
                            <div class="card-body" id="partialExtractDetails">
                                <!-- سيتم تحميل التفاصيل عبر AJAX -->
                            </div>
                        </div>

                        <!-- أوامر العمل المرتبطة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-tasks text-primary me-2"></i>
                                    أوامر العمل المرتبطة
                                    <span class="badge bg-primary ms-2" id="workOrdersCount"><?= count($extractWorkOrders) ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="selectedWorkOrdersContainer">
                                    <?php if (!empty($extractWorkOrders)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm" id="selectedWorkOrdersTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>رقم أمر العمل</th>
                                                        <th>النوع</th>
                                                        <th>القسم</th>
                                                        <th>الفرع</th>
                                                        <th>تاريخ الإنجاز</th>
                                                        <th>قيمة المستخلص</th>
                                                        <th>الغرامة</th>
                                                        <th>ملاحظات</th>
                                                        <th>الإجراءات</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="selectedWorkOrdersBody">
                                                    <?php foreach ($extractWorkOrders as $wo): ?>
                                                        <tr data-work-order-id="<?= $wo['work_order_id'] ?>">
                                                            <td>
                                                                <strong><?= htmlspecialchars($wo['work_order_number']) ?></strong>
                                                                <input type="hidden" name="work_order_ids[]" value="<?= $wo['work_order_id'] ?>">
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary"><?= htmlspecialchars($wo['work_order_type_code']) ?></span>
                                                            </td>
                                                            <td><?= htmlspecialchars($wo['department_name']) ?></td>
                                                            <td><?= htmlspecialchars($wo['work_order_branch_name']) ?></td>
                                                            <td>
                                                                <input type="date" class="form-control form-control-sm completion-date"
                                                                       name="completion_dates[<?= $wo['work_order_id'] ?>]"
                                                                       value="<?= $wo['completion_date'] ?>" required>
                                                            </td>
                                                            <td>
                                                                <div class="input-group input-group-sm">
                                                                    <input type="number" class="form-control extract-value"
                                                                           name="extract_values[<?= $wo['work_order_id'] ?>]"
                                                                           value="<?= $wo['extract_value'] ?>"
                                                                           step="0.01" min="0" required>
                                                                    <span class="input-group-text">ريال</span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="input-group input-group-sm">
                                                                    <input type="number" class="form-control penalty-amount"
                                                                           name="penalty_amounts[<?= $wo['work_order_id'] ?>]"
                                                                           value="<?= $wo['penalty_amount'] ?>"
                                                                           step="0.01" min="0">
                                                                    <span class="input-group-text">ريال</span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm"
                                                                       name="work_order_notes[<?= $wo['work_order_id'] ?>]"
                                                                       value="<?= htmlspecialchars($wo['notes'] ?? '') ?>"
                                                                       placeholder="ملاحظات">
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-danger remove-work-order"
                                                                        data-id="<?= $wo['work_order_id'] ?>" title="إزالة">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-4" id="emptyMessage">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <p>لم يتم إضافة أوامر عمل بعد</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الملخص المالي -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-calculator text-success me-2"></i>
                                    الملخص المالي
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between">
                                            <span>المجموع قبل الضريبة:</span>
                                            <strong id="totalAmount"><?= number_format($extract['total_amount'], 2) ?> ريال</strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between">
                                            <span>ضريبة القيمة المضافة (15%):</span>
                                            <strong id="taxAmount"><?= number_format($extract['tax_amount'], 2) ?> ريال</strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between text-danger">
                                            <span>إجمالي الغرامات:</span>
                                            <strong id="totalPenalty"><?= number_format($extract['total_penalty_amount'], 2) ?> ريال</strong>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <span><strong>المجموع النهائي:</strong></span>
                                            <strong class="text-success" id="netAmount"><?= number_format($extract['net_amount'], 2) ?> ريال</strong>
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
    let selectedWorkOrders = <?= json_encode(array_map(function($wo) {
        return [
            'id' => $wo['work_order_id'],
            'work_order_number' => $wo['work_order_number'],
            'work_order_type_code' => $wo['work_order_type_code'],
            'department_name' => $wo['department_name'],
            'work_order_branch_name' => $wo['work_order_branch_name'],
            'extract_value' => $wo['extract_value'],
            'penalty_amount' => $wo['penalty_amount'],
            'completion_date' => $wo['completion_date'],
            'notes' => $wo['notes']
        ];
    }, $extractWorkOrders)) ?>;

    // تحديث الملخص عند تحميل الصفحة
    updateSummary();

    // تحميل تفاصيل المستخلص الجزئي عند تحميل الصفحة
    const relatedPartialExtractId = $('input[name="related_partial_extract_id"]').val();
    if (relatedPartialExtractId) {
        $('#relatedPartialInfo').show();
        loadPartialExtractDetails(relatedPartialExtractId);
    }

    // دالة تحميل تفاصيل المستخلص الجزئي
    function loadPartialExtractDetails(partialExtractId) {
        if (!partialExtractId) return;

        $.ajax({
            url: 'get-partial-extract-details.php',
            type: 'GET',
            data: { id: partialExtractId, edit_mode: 'true' },
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Response:', response); // للتشخيص
                if (response.success) {
                    console.log('Response data:', response.data); // للتشخيص
                    displayPartialExtractDetails(response.data);
                } else {
                    console.error('Error loading partial extract details:', response.message);
                    $('#partialExtractDetails').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading partial extract details:', error);
                console.error('Response:', xhr.responseText);
                let errorMessage = 'حدث خطأ أثناء تحميل تفاصيل المستخلص الجزئي';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $('#partialExtractDetails').html('<div class="alert alert-danger">' + errorMessage + '</div>');
            }
        });
    }

    // دالة عرض تفاصيل المستخلص الجزئي
    function displayPartialExtractDetails(data) {
        console.log('Received data:', data); // للتشخيص

        // البيانات مُرجعة مباشرة، ليس كخصائص منفصلة
        const extract = data;
        const workOrders = data.work_orders || [];

        // تخزين ضريبة المستخلص الجزئي في حقل مخفي
        if ($('#partial_extract_tax_amount').length === 0) {
            $('#editFinalForPartialExtractForm').append(`<input type="hidden" id="partial_extract_tax_amount" value="${extract.tax_amount || 0}">`);
        } else {
            $('#partial_extract_tax_amount').val(extract.tax_amount || 0);
        }

        // تحديث الملخص بعد تحميل ضريبة المستخلص الجزئي
        updateSummary();

        let html = `
            <div class="row">
                <div class="col-md-6">
                    <p><strong>رقم المستخلص:</strong> ${extract.extract_number || 'غير متوفر'}</p>
                    <p><strong>تاريخ المستخلص:</strong> ${extract.extract_date || 'غير متوفر'}</p>
                    <p><strong>الفرع:</strong> ${extract.branch_name || 'غير متوفر'}</p>
                    <p><strong>ضريبة المستخلص الجزئي:</strong> ${parseFloat(extract.tax_amount || 0).toLocaleString()} ريال</p>
                </div>
                <div class="col-md-6">
                    <p><strong>المجموع:</strong> ${extract.total_amount ? parseFloat(extract.total_amount).toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال' : 'غير متوفر'}</p>
                    <p><strong>الصافي:</strong> ${extract.net_amount ? parseFloat(extract.net_amount).toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال' : 'غير متوفر'}</p>
                    <p><strong>عدد أوامر العمل:</strong> ${workOrders.length}</p>
                </div>
            </div>
        `;

        $('#partialExtractDetails').html(html);
    }

    // حذف أمر عمل
    $(document).on('click', '.remove-work-order', function() {
        const id = parseInt($(this).data('id'));

        Swal.fire({
            title: 'تأكيد الحذف',
            text: 'هل أنت متأكد من إزالة أمر العمل هذا؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                // إزالة من المصفوفة
                selectedWorkOrders = selectedWorkOrders.filter(wo => wo.id !== id);

                // إزالة الصف من الجدول
                $(`tr[data-work-order-id="${id}"]`).remove();

                // تحديث العداد والملخص
                $('#workOrdersCount').text(selectedWorkOrders.length);
                updateSummary();

                // إظهار رسالة فارغة إذا لم تعد هناك أوامر عمل
                if (selectedWorkOrders.length === 0) {
                    $('#selectedWorkOrdersContainer').html(`
                        <div class="text-center text-muted py-4" id="emptyMessage">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>لم يتم إضافة أوامر عمل بعد</p>
                        </div>
                    `);
                }
            }
        });
    });

    // تحديث قيم المستخلص
    $(document).on('input', '.extract-value, .completion-date, .penalty-amount', function() {
        updateSummary();
    });

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

        // جلب ضريبة المستخلص الجزئي المرتبط
        const partialExtractTax = parseFloat($('#partial_extract_tax_amount').val()) || 0;

        // الصافي = مجموع قيم أوامر العمل + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامة
        const net = total + tax + partialExtractTax - totalPenalty;

        $('#totalAmount').text(total.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#taxAmount').text(tax.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#totalPenalty').text(totalPenalty.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#netAmount').text(net.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
    }

    // تقديم النموذج
    $('#editFinalForPartialExtractForm').on('submit', function(e) {
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
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
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
            },
            complete: function() {
                // إعادة تفعيل الزر
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }
});
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تحميل التخطيط
require_once __DIR__ . '/../../includes/layout.php';
?>
