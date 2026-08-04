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

<div class="container-fluid px-4 py-3">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold text-dark mb-1">
                <i class="fas fa-file-invoice text-primary me-2"></i>
                إنشاء مستخلص نهائي عادي جديد
            </h5>
            <p class="text-muted mb-0 small">إدخال بيانات المستخلص وأوامر العمل المرتبطة به</p>
        </div>
        <a href="index.php" class="btn btn-light rounded-pill px-3 shadow-sm text-secondary fw-bold border-0">
            <i class="fas fa-arrow-right me-2"></i>العودة للقائمة
        </a>
    </div>

    <!-- Container for Bootstrap Alerts -->
    <div id="alertContainer" class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1050; width: 100%; max-width: 600px;"></div>

    <!-- نموذج إنشاء المستخلص -->
    <form id="finalRegularExtractForm" method="POST" enctype="multipart/form-data">
        <!-- معلومات المستخلص الأساسية -->
        <div class="card dash-card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-0 py-2" style="border-radius: 20px 20px 0 0;">
                <h6 class="card-title mb-0 fw-bold text-dark">
                    <i class="fas fa-info-circle text-primary opacity-75 me-2"></i>معلومات المستخلص الأساسية
                </h6>
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label for="extract_number" class="form-label small fw-bold mb-1">رقم المستخلص <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="extract_number" name="extract_number" 
                               value="<?php echo $suggestedExtractNumber; ?>" readonly required>
                    </div>
                    <div class="col-md-3">
                        <label for="invoice_number" class="form-label small fw-bold mb-1">رقم الفاتورة</label>
                        <input type="text" class="form-control form-control-sm" id="invoice_number" name="invoice_number"
                               placeholder="اختياري">
                    </div>
                    <div class="col-md-3">
                        <label for="extract_date" class="form-label small fw-bold mb-1">تاريخ المستخلص <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="extract_date" name="extract_date"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="branch_id" class="form-label small fw-bold mb-1">الفرع <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="branch_id" name="branch_id" required>
                            <option value="">سيتم تحديده تلقائياً</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="department" class="form-label small fw-bold mb-1">القسم <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="department" name="department" required>
                            <option value="">سيتم تحديده تلقائياً</option>
                            <option value="connections">التوصيلات</option>
                            <option value="projects">المشاريع</option>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label for="description" class="form-label small fw-bold mb-1">وصف المستخلص</label>
                        <input type="text" class="form-control form-control-sm" id="description" name="description"
                               placeholder="وصف تفصيلي للمستخلص...">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- العمود الأيمن (أوامر العمل المتاحة) -->
            <div class="col-lg-6">
                <!-- الجزء الأول: أوامر العمل المتاحة -->
                <div class="card dash-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 py-2 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="fas fa-list text-primary opacity-75 me-2"></i>أوامر العمل المتاحة
                            <span class="badge bg-secondary-soft text-secondary rounded-pill ms-1" id="availableCount"><?php echo count($workOrders); ?></span>
                        </h6>
                        <div style="max-width: 200px;">
                            <input type="tel" inputmode="numeric" pattern="[0-9]*" id="customSearchAvailable" class="form-control form-control-sm rounded-pill px-3 shadow-none border-1" placeholder="ابحث برقم الأمر...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table id="availableWorkOrdersTable" class="table premium-table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
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
                                            <td class="ps-3 fw-bold"><?php echo htmlspecialchars($wo['work_order_number']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($wo['work_order_type_code']); ?></span></td>
                                            <td><?php echo number_format($wo['actual_value'] ?: $wo['estimated_value'], 2); ?> <small class="text-muted">ريال</small></td>
                                            <td class="pe-3 text-center">
                                                <button type="button" class="btn btn-sm btn-primary rounded-circle add-work-order" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"
                                                        data-id="<?php echo $wo['id']; ?>"
                                                        data-number="<?php echo htmlspecialchars($wo['work_order_number']); ?>"
                                                        data-type="<?php echo htmlspecialchars($wo['work_order_type_code']); ?>"
                                                        data-department="<?php echo htmlspecialchars($wo['department']); ?>"
                                                        data-department-name="<?php echo htmlspecialchars($wo['department_name']); ?>"
                                                        data-branch-id="<?php echo $wo['branch_id']; ?>"
                                                        data-branch-name="<?php echo htmlspecialchars($wo['branch_name']); ?>"
                                                        data-value="<?php echo $wo['actual_value'] ?: $wo['estimated_value']; ?>"
                                                        data-receipt-date="<?php echo $wo['receipt_date'] ?? ''; ?>"
                                                        data-assignment-date="<?php echo $wo['assignment_date'] ?? ''; ?>" title="إضافة">
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
            <!-- العمود الأيسر (أوامر العمل المختارة، المرفقات، والملخص) -->
            <div class="col-lg-6">
                <!-- الجزء الثاني: أوامر العمل المختارة -->
                <div class="card dash-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="fas fa-check-circle text-success opacity-75 me-2"></i>الأوامر المختارة
                            <span class="badge bg-success-soft text-success rounded-pill ms-1" id="selectedCount">0</span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="selectedWorkOrdersContainer" class="d-flex flex-column" style="min-height: 150px;">
                            <div class="text-center text-muted py-5 my-auto" id="emptyMessage">
                                <div class="icon-circle bg-light mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    <i class="fas fa-inbox text-muted"></i>
                                </div>
                                <p class="mb-0 fw-bold">لم يتم اختيار أي أوامر عمل بعد</p>
                                <small>استخدم القائمة لإضافة أوامر العمل</small>
                            </div>
                            
                            <div id="selectedWorkOrdersTable" style="display: none;" class="table-responsive">
                                <table class="table premium-table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead style="background-color: #f8fafc; color: #64748b; position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th class="ps-2 border-0">رقم الأمر</th>
                                            <th class="border-0" style="width: 100px;">قيمة المستخلص <span class="text-danger">*</span></th>
                                            <th class="border-0" style="width: 90px;">الغرامة</th>
                                            <th class="border-0" style="width: 110px;">تاريخ الإنجاز</th>
                                            <th class="pe-2 border-0 text-center">حذف</th>
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

                <!-- المرفقات -->
                <div class="card dash-card shadow-sm border-0 mb-4">
                    <div class="card-body py-3">
                        <h6 class="fw-bold text-dark mb-2"><i class="fas fa-paperclip text-primary me-2"></i>المرفقات</h6>
                        <input type="file" class="form-control form-control-sm mb-1" id="attachments" name="attachments[]" multiple 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <small class="text-muted d-block" style="font-size: 0.75rem;">يمكنك رفع ملفات PDF, Word, Excel, أو صور</small>
                        <div id="attachmentsList" class="mt-2"></div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="card dash-card shadow-sm border-0 mb-4 bg-primary-soft">
                    <div class="card-body py-3 d-flex flex-column justify-content-center">
                        <div class="row text-center g-2 align-items-center">
                            <div class="col-3 px-1">
                                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.7rem;">إجمالي المبالغ</small>
                                <h6 class="text-dark fw-bold mb-0" id="totalAmount">0.00 <span class="sar-icon text-muted" style="width:8px; height:8px;"><svg><use href="#sar-symbol"/></svg></span></h6>
                            </div>
                            <div class="col-1 text-muted px-0"><i class="fas fa-plus fa-sm"></i></div>
                            <div class="col-3 px-1 border-start border-primary border-opacity-25">
                                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.7rem;">الضريبة (15%)</small>
                                <h6 class="text-dark fw-bold mb-0" id="taxAmount">0.00 <span class="sar-icon text-muted" style="width:8px; height:8px;"><svg><use href="#sar-symbol"/></svg></span></h6>
                            </div>
                            <div class="col-1 text-muted px-0"><i class="fas fa-minus fa-sm"></i></div>
                            <div class="col-4 px-1 border-start border-primary border-opacity-25">
                                <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.7rem;">الغرامات</small>
                                <h6 class="text-danger fw-bold mb-0" id="totalPenalty">0.00 <span class="sar-icon text-muted" style="width:8px; height:8px;"><svg><use href="#sar-symbol"/></svg></span></h6>
                            </div>
                        </div>
                        <div class="row mt-3 text-center">
                            <div class="col-12 px-2 border-top border-primary border-opacity-25 pt-2">
                                <small class="text-muted fw-bold d-block mb-1">الصافي النهائي</small>
                                <h4 class="text-success fw-bold mb-0" id="netAmount">0.00 <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- أزرار الحفظ -->
                <div class="d-grid mt-3">
                    <button type="submit" class="btn btn-primary rounded-pill py-2 fw-bold shadow-sm" name="action" value="save">
                        <i class="fas fa-save me-2"></i>
                        حفظ المستخلص النهائي العادي
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة DataTable
    var availableTable = $('#availableWorkOrdersTable').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json' },
        pageLength: 50,
        scrollY: "280px",
        scrollCollapse: true,
        info: false,
        dom: 't',
        order: [[0, 'desc']]
    });

    // Custom search input functionality (numeric only)
    $('#customSearchAvailable').on('input', function() {
        var val = $(this).val().replace(/[^0-9]/g, '');
        if ($(this).val() !== val) $(this).val(val);
        availableTable.search(val).draw();
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
                        <td class="ps-2 fw-bold">${wo.number} <br><small class="text-muted fw-normal">${wo.type}</small></td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 140px;">
                                <input type="number" class="form-control text-center fw-bold text-primary extract-value"
                                       name="extract_values[${wo.id}]" step="0.01" value="" required>
                                <span class="input-group-text bg-light"><small>ريال</small></span>
                            </div>
                            <div class="mt-1 text-muted" style="font-size: 0.7rem;">الفعلي: ${parseFloat(wo.value).toLocaleString('ar-SA')}</div>
                        </td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 110px;">
                                <input type="number" class="form-control text-center text-danger penalty-amount" 
                                       name="penalty_amounts[${wo.id}]" step="0.01" min="0" value="0">
                            </div>
                        </td>
                        <td>
                            <input type="date" class="form-control form-control-sm completion-date text-center" style="width: 120px;"
                                   name="completion_dates[${wo.id}]" value="${wo.completionDate}" required>
                        </td>
                        <td class="pe-2 text-center">
                            <button type="button" class="btn btn-sm btn-light text-danger rounded-circle remove-work-order" 
                                    data-id="${wo.id}" style="width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="حذف">
                                <i class="fas fa-trash-alt"></i>
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
        
        const formatter = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        $('#totalAmount').html(formatter.format(total) + ' <span class="sar-icon text-muted" style="width:8px; height:8px;"><svg><use href="#sar-symbol"/></svg></span>');
        $('#taxAmount').html(formatter.format(tax) + ' <span class="sar-icon text-muted" style="width:8px; height:8px;"><svg><use href="#sar-symbol"/></svg></span>');
        $('#totalPenalty').html(formatter.format(totalPenalty) + ' <span class="sar-icon text-muted" style="width:8px; height:8px;"><svg><use href="#sar-symbol"/></svg></span>');
        $('#netAmount').html(formatter.format(net) + ' <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span>');
    }

    // التنقل بالأسهم بين حقول الجدول المختار للتحرك في جميع الاتجاهات
    $(document).on('keydown', '#selectedWorkOrdersTable input', function(e) {
        const keys = ['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Enter'];
        if (keys.includes(e.key)) {
            // السماح بالتنقل داخل حقل التاريخ يمين/يسار بالأسهم
            if (e.target.type === 'date' && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) {
                return;
            }
            
            e.preventDefault();
            const $this = $(this);
            const $currentRow = $this.closest('tr');
            const $inputsInRow = $currentRow.find('input:visible:not([disabled])');
            const colIndex = $inputsInRow.index(this);
            
            let $targetInput = null;

            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                const $targetRow = $currentRow.next('tr');
                if ($targetRow.length) $targetInput = $targetRow.find('input:visible:not([disabled])').eq(colIndex);
            } else if (e.key === 'ArrowUp') {
                const $targetRow = $currentRow.prev('tr');
                if ($targetRow.length) $targetInput = $targetRow.find('input:visible:not([disabled])').eq(colIndex);
            } else if (e.key === 'ArrowLeft') {
                if (colIndex < $inputsInRow.length - 1) $targetInput = $inputsInRow.eq(colIndex + 1);
            } else if (e.key === 'ArrowRight') {
                if (colIndex > 0) $targetInput = $inputsInRow.eq(colIndex - 1);
            }
            
            if ($targetInput && $targetInput.length) {
                $targetInput.focus();
                if ($targetInput.attr('type') !== 'date') {
                    $targetInput.select();
                }
            }
        }
    });

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
