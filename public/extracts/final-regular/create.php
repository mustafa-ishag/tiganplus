<?php
/**
 * نموذج إنشاء المستخلص النهائي العادي
 * Final Regular Extract Creation Form
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إنشاء مستخلص نهائي عادي';
$currentPage = 'extracts-final-regular';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'إنشاء مستخلص نهائي عادي', 'url' => 'extracts/final-regular/create.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_create')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// جلب الفروع
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();

// جلب أوامر العمل المتاحة (التي لم تدخل في مستخلصات سابقة)
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
        INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
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

// توليد رقم المستخلص التلقائي
$currentYear = date('Y');
$lastExtractQuery = "SELECT extract_number FROM final_regular_extracts WHERE extract_number LIKE 'FRE-$currentYear-%' ORDER BY id DESC LIMIT 1";
$lastExtract = $db->query($lastExtractQuery)->fetch();

if ($lastExtract) {
    $lastNumber = intval(substr($lastExtract['extract_number'], -3));
    $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
} else {
    $newNumber = '001';
}
$suggestedExtractNumber = "FRE-$currentYear-$newNumber";

// إعداد متغيرات الصفحة
$pageTitle = 'إنشاء مستخلص نهائي عادي';
$currentPage = 'extracts-final-regular';
$breadcrumbs = [
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'إنشاء مستخلص نهائي عادي', 'url' => '']
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
                        <i class="fas fa-plus-circle text-success me-2"></i>
                        إنشاء مستخلص نهائي عادي جديد
                    </h2>
                    <p class="text-muted mb-0">إضافة مستخلص نهائي عادي جديد للمشروع</p>
                </div>
                <div>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right me-1"></i>
                        العودة للقائمة
                    </a>
                </div>
            </div>

            <!-- نموذج إنشاء المستخلص -->
            <form id="finalRegularExtractForm" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <!-- معلومات المستخلص الأساسية -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle text-success me-2"></i>
                                    معلومات المستخلص الأساسية
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="extract_number" class="form-label">رقم المستخلص <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="extract_number" name="extract_number" 
                                               value="<?php echo $suggestedExtractNumber; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">رقم الفاتورة</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="branch_id" class="form-label">الفرع <span class="text-danger">*</span></label>
                                        <select class="form-select" id="branch_id" name="branch_id" required>
                                            <option value="">سيتم تحديده تلقائياً</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle text-primary"></i>
                                            سيتم تحديد الفرع تلقائياً عند إضافة أول أمر عمل
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">القسم <span class="text-danger">*</span></label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">سيتم تحديده تلقائياً</option>
                                            <option value="connections">التوصيلات</option>
                                            <option value="projects">المشاريع</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle text-primary"></i>
                                            سيتم تحديد القسم تلقائياً عند إضافة أول أمر عمل
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="extract_date" class="form-label">تاريخ المستخلص <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="extract_date" name="extract_date"
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="description" class="form-label">وصف المستخلص</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="وصف تفصيلي للمستخلص النهائي العادي..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الجزء الأول: أوامر العمل المتاحة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list text-success me-2"></i>
                                    الجزء الأول: أوامر العمل المتاحة
                                    <span class="badge bg-secondary ms-2" id="availableCount"><?php echo count($workOrders); ?></span>
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
                                            <?php foreach ($workOrders as $wo): ?>
                                                <tr data-work-order-id="<?php echo $wo['id']; ?>">
                                                    <td><?php echo htmlspecialchars($wo['work_order_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($wo['work_order_type_code']); ?></td>
                                                    <td><?php echo number_format($wo['actual_value'] ?: $wo['estimated_value'], 2); ?> ريال</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-primary add-work-order"
                                                                data-id="<?php echo $wo['id']; ?>"
                                                                data-number="<?php echo htmlspecialchars($wo['work_order_number']); ?>"
                                                                data-type="<?php echo htmlspecialchars($wo['work_order_type_code']); ?>"
                                                                data-department="<?php echo htmlspecialchars($wo['department']); ?>"
                                                                data-department-name="<?php echo htmlspecialchars($wo['department_name']); ?>"
                                                                data-branch-id="<?php echo $wo['branch_id']; ?>"
                                                                data-branch-name="<?php echo htmlspecialchars($wo['branch_name']); ?>"
                                                                data-value="<?php echo $wo['actual_value'] ?: $wo['estimated_value']; ?>"
                                                                data-receipt-date="<?php echo $wo['receipt_date'] ?? ''; ?>"
                                                                data-assignment-date="<?php echo $wo['assignment_date'] ?? ''; ?>">
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
                                    الجزء الثاني: أوامر العمل المختارة
                                    <span class="badge bg-success ms-2" id="selectedCount">0</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="selectedWorkOrdersContainer">
                                    <div class="text-center text-muted py-4" id="emptyMessage">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <p>لم يتم اختيار أي أوامر عمل بعد</p>
                                        <small>استخدم الجزء الأول لإضافة أوامر العمل</small>
                                    </div>
                                </div>
                                
                                <div id="selectedWorkOrdersTable" style="display: none;">
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
                                                <!-- سيتم إضافة الصفوف هنا ديناميكياً -->
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
                                    <i class="fas fa-calculator text-success me-2"></i>
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
                                            <h6 class="text-muted mb-1">الصافي النهائي</h6>
                                            <h4 class="text-success mb-0" id="netAmount">0.00 ريال</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- المرفقات -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-paperclip text-success me-2"></i>
                                    المرفقات
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="file" class="form-control" id="attachments" name="attachments[]" multiple 
                                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                    <small class="text-muted">يمكنك رفع ملفات PDF, Word, Excel, أو صور</small>
                                </div>
                                <div id="attachmentsList"></div>
                            </div>
                        </div>

                        <!-- أزرار الحفظ -->
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg" name="action" value="save">
                                        <i class="fas fa-save me-2"></i>
                                        حفظ المستخلص النهائي العادي
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTable
    $('#availableWorkOrdersTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
        },
        pageLength: 10,
        order: [[0, 'desc']]
    });

    let selectedWorkOrders = [];

    // إضافة أمر عمل
    $(document).on('click', '.add-work-order', function() {
        const id = $(this).data('id');
        const number = $(this).data('number');
        const type = $(this).data('type');
        const value = $(this).data('value');
        const department = $(this).data('department');
        const departmentName = $(this).data('department-name');
        const branchId = $(this).data('branch-id');
        const branchName = $(this).data('branch-name');

        // التحقق من عدم الإضافة المكررة
        if (selectedWorkOrders.find(wo => wo.id === id)) {
            alert('تم إضافة هذا الأمر مسبقاً');
            return;
        }

        // التحديد التلقائي للفرع والقسم عند إضافة أول أمر عمل
        if (selectedWorkOrders.length === 0) {
            // تحديد الفرع تلقائياً
            $('#branch_id').val(branchId);
            $('#branch_id').trigger('change');

            // تحديد القسم تلقائياً
            $('#department').val(department);
            $('#department').trigger('change');

            // إظهار رسالة تأكيد
            showAutoSelectionMessage(branchName, departmentName);
        } else {
            // التحقق من تطابق الفرع والقسم مع الأوامر المختارة
            const currentBranch = $('#branch_id').val();
            const currentDepartment = $('#department').val();

            if (currentBranch != branchId) {
                if (!confirm(`أمر العمل من فرع مختلف (${branchName}). هل تريد المتابعة؟`)) {
                    return;
                }
            }

            if (currentDepartment != department) {
                if (!confirm(`أمر العمل من قسم مختلف (${departmentName}). هل تريد المتابعة؟`)) {
                    return;
                }
            }
        }

        // جلب تواريخ الإنجاز
        const receiptDate = $(this).data('receipt-date');
        const assignmentDate = $(this).data('assignment-date');
        const completionDate = receiptDate || assignmentDate || new Date().toISOString().split('T')[0];

        // إضافة إلى المصفوفة
        selectedWorkOrders.push({
            id: id,
            number: number,
            type: type,
            value: value,
            department: department,
            departmentName: departmentName,
            branchId: branchId,
            branchName: branchName,
            completionDate: completionDate
        });

        // إخفاء الصف من الجدول الأول
        $(this).closest('tr').hide();

        // تحديث العرض
        updateSelectedWorkOrders();
        updateSummary();
    });

    // حذف أمر عمل
    $(document).on('click', '.remove-work-order', function() {
        const id = parseInt($(this).data('id'));
        
        // إزالة من المصفوفة
        selectedWorkOrders = selectedWorkOrders.filter(wo => wo.id !== id);
        
        // إظهار الصف في الجدول الأول
        $(`tr[data-work-order-id="${id}"]`).show();
        
        // تحديث العرض
        updateSelectedWorkOrders();
        updateSummary();
    });

    // تحديث قيم المستخلص
    $(document).on('input', '.extract-value, .completion-date, .penalty-amount', function() {
        updateSummary();
    });

    function updateSelectedWorkOrders() {
        const container = $('#selectedWorkOrdersContainer');
        const table = $('#selectedWorkOrdersTable');
        const tbody = $('#selectedWorkOrdersBody');
        const emptyMessage = $('#emptyMessage');
        
        if (selectedWorkOrders.length === 0) {
            table.hide();
            emptyMessage.show();
        } else {
            emptyMessage.hide();
            table.show();
            
            tbody.empty();
            selectedWorkOrders.forEach(wo => {
                tbody.append(`
                    <tr>
                        <td>${wo.number}</td>
                        <td>${wo.type}</td>
                        <td>${parseFloat(wo.value).toLocaleString('ar-SA', {minimumFractionDigits: 2})} ريال</td>
                        <td>
                            <input type="number" class="form-control form-control-sm extract-value"
                                   name="extract_values[${wo.id}]" step="0.01" required>
                        </td>
                        <td>
                            <input type="date" class="form-control form-control-sm completion-date"
                                   name="completion_dates[${wo.id}]" value="${wo.completionDate}" required>
                            <small class="text-muted">من أمر العمل</small>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm penalty-amount" 
                                   name="penalty_amounts[${wo.id}]" step="0.01" min="0" value="0">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-work-order" data-id="${wo.id}">
                                <i class="fas fa-times"></i>
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

    // دالة إظهار رسالة التحديد التلقائي
    function showAutoSelectionMessage(branchName, departmentName) {
        // إنشاء رسالة تأكيد
        const alertHtml = `
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <h6 class="alert-heading">
                    <i class="fas fa-check-circle me-2"></i>
                    تم التحديد التلقائي
                </h6>
                <p class="mb-0">
                    <strong>الفرع:</strong> ${branchName}<br>
                    <strong>القسم:</strong> ${departmentName}
                </p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // إضافة الرسالة بعد معلومات المستخلص الأساسية
        $('.card:first .card-body').append(alertHtml);

        // إزالة الرسالة تلقائياً بعد 5 ثوان
        setTimeout(function() {
            $('.alert-success').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // تقديم النموذج
    $('#finalRegularExtractForm').on('submit', function(e) {
        e.preventDefault(); // منع الإرسال العادي

        if (selectedWorkOrders.length === 0) {
            alert('يجب إضافة أمر عمل واحد على الأقل');
            return false;
        }

        // إضافة معرفات أوامر العمل المختارة
        selectedWorkOrders.forEach(wo => {
            $(this).append(`<input type="hidden" name="work_order_ids[]" value="${wo.id}">`);
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
            url: 'create-ajax.php',
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
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    // إظهار رسالة خطأ
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ!',
                        text: response.message,
                        confirmButtonText: 'موافق'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                let errorMessage = 'حدث خطأ أثناء حفظ المستخلص';

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
