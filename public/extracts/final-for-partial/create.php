<?php
/**
 * نموذج إنشاء المستخلص النهائي للجزئية
 * Final for Partial Extract Creation Form
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'إنشاء مستخلص نهائي للجزئية';
$currentPage = 'extracts-final-for-partial';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'إنشاء مستخلص نهائي للجزئية', 'url' => 'extracts/final-for-partial/create.php']
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

// جلب المستخلصات الجزئية المؤهلة لإنشاء مستخلص نهائي
// الشروط: 1) في مرحلة مصروف أو مالية الطائف 2) جميع أوامر العمل مؤكدة شهادة الإنجاز 3) لم يتم إنشاء مستخلص نهائي لها مسبقاً
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
    AND ffpe.id IS NULL
    GROUP BY pe.id
    HAVING total_work_orders > 0 AND confirmed_certificates = total_work_orders
    ORDER BY pe.extract_date DESC
";
$partialExtracts = $db->query($partialExtractsQuery)->fetchAll();



// توليد رقم المستخلص التلقائي
$currentYear = date('Y');
$lastExtractQuery = "SELECT extract_number FROM final_for_partial_extracts WHERE extract_number LIKE 'FFPE-$currentYear-%' ORDER BY id DESC LIMIT 1";
$lastExtract = $db->query($lastExtractQuery)->fetch();

if ($lastExtract) {
    $lastNumber = intval(substr($lastExtract['extract_number'], -3));
    $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
} else {
    $newNumber = '001';
}
$suggestedExtractNumber = "FFPE-$currentYear-$newNumber";

// إعداد متغيرات الصفحة
$pageTitle = 'إنشاء مستخلص نهائي للجزئية';
$currentPage = 'extracts-final-for-partial';
$breadcrumbs = [
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'إنشاء مستخلص نهائي للجزئية', 'url' => '']
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
                        <i class="fas fa-plus-circle text-warning me-2"></i>
                        إنشاء مستخلص نهائي للجزئية جديد
                    </h2>
                    <p class="text-muted mb-0">إضافة مستخلص نهائي للجزئية جديد للمشروع</p>
                </div>
                <div>
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right me-1"></i>
                        العودة للقائمة
                    </a>
                </div>
            </div>

            <!-- رسالة توضيحية للشروط -->
            <div class="alert alert-info mb-4">
                <h6 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>
                    شروط إنشاء المستخلص النهائي للجزئي
                </h6>
                <p class="mb-2">لإنشاء مستخلص نهائي للجزئي، يجب أن يستوفي المستخلص الجزئي الشروط التالية:</p>
                <ul class="mb-0">
                    <li><strong>مرحلة الاعتماد:</strong> يجب أن يكون المستخلص في مرحلة "مصروف" أو "مالية الطائف"</li>
                    <li><strong>شهادات الإنجاز:</strong> يجب تأكيد جميع شهادات الإنجاز لأوامر العمل في المستخلص</li>
                    <li><strong>عدم التكرار:</strong> لم يتم إنشاء مستخلص نهائي لهذا المستخلص الجزئي مسبقاً</li>
                </ul>
                <small class="text-muted">
                    <i class="fas fa-lightbulb me-1"></i>
                    ستظهر فقط المستخلصات الجزئية التي تستوفي هذه الشروط في القائمة أدناه
                </small>
            </div>

            <?php if (empty($partialExtracts)): ?>
            <!-- رسالة عدم وجود مستخلصات مؤهلة -->
            <div class="alert alert-warning mb-4">
                <h6 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    لا توجد مستخلصات جزئية مؤهلة
                </h6>
                <p class="mb-2">لا توجد مستخلصات جزئية تستوفي الشروط المطلوبة لإنشاء مستخلص نهائي.</p>
                <p class="mb-0">
                    <strong>للمتابعة، تأكد من:</strong>
                </p>
                <ul class="mb-2">
                    <li>وجود مستخلصات جزئية في مرحلة "مصروف" أو "مالية الطائف"</li>
                    <li>تأكيد جميع شهادات الإنجاز لأوامر العمل في تلك المستخلصات</li>
                    <li>عدم إنشاء مستخلص نهائي لتلك المستخلصات مسبقاً</li>
                </ul>
                <a href="../partial/index.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-list me-1"></i>
                    عرض المستخلصات الجزئية
                </a>
            </div>
            <?php endif; ?>

            <!-- نموذج إنشاء المستخلص -->
            <?php if (!empty($partialExtracts)): ?>
            <form id="finalForPartialExtractForm" method="POST" enctype="multipart/form-data">
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
                                               value="<?php echo $suggestedExtractNumber; ?>" readonly required>
                                        <small class="text-muted">
                                            <i class="fas fa-lock me-1"></i>
                                            سيتم تحديد الرقم تلقائياً عند اختيار المستخلص الجزئي
                                        </small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="invoice_number" class="form-label">رقم الفاتورة</label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="related_partial_extract_id" class="form-label">المستخلص الجزئي المرتبط <span class="text-danger">*</span></label>
                                        <select class="form-select" id="related_partial_extract_id" name="related_partial_extract_id" required>
                                            <option value="">اختر المستخلص الجزئي</option>
                                            <?php if (empty($partialExtracts)): ?>
                                                <option value="" disabled>لا توجد مستخلصات جزئية مؤهلة</option>
                                            <?php else: ?>
                                                <?php foreach ($partialExtracts as $pe): ?>
                                                    <option value="<?php echo $pe['id']; ?>" data-branch="<?php echo $pe['branch_id']; ?>">
                                                        <?php echo htmlspecialchars($pe['extract_number']); ?> - <?php echo htmlspecialchars($pe['branch_name']); ?>
                                                        (<?php echo $pe['confirmed_certificates']; ?>/<?php echo $pe['total_work_orders']; ?> شهادات مؤكدة - متاح للإنشاء)
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <small class="text-muted">
                                            <i class="fas fa-check-circle text-success me-1"></i>
                                            تظهر فقط المستخلصات المؤهلة (مصروف/مالية الطائف + جميع شهادات الإنجاز مؤكدة + لم يتم إنشاء مستخلص نهائي لها)
                                        </small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="branch_id" class="form-label">الفرع <span class="text-danger">*</span></label>
                                        <select class="form-select" id="branch_id" name="branch_id" required>
                                            <option value="">اختر الفرع</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="extract_date" class="form-label">تاريخ المستخلص <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="extract_date" name="extract_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label for="description" class="form-label">وصف المستخلص</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="وصف تفصيلي للمستخلص النهائي للجزئية..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- معلومات المستخلص الجزئي المرتبط -->
                        <div class="card shadow-sm mb-4" id="relatedPartialInfo" style="display: none;">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-link me-2"></i>
                                    معلومات المستخلص الجزئي المرتبط
                                </h5>
                            </div>
                            <div class="card-body" id="relatedPartialDetails">
                                <!-- سيتم ملء هذا القسم ديناميكياً -->
                            </div>
                        </div>

                        <!-- أوامر العمل المختارة -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    أوامر العمل المرتبطة
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
                                                    <th>الغرامة</th>
                                                    <th>الإجراء</th>
                                                    <th>تاريخ الإنجاز</th>
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
                                    <i class="fas fa-calculator text-warning me-2"></i>
                                    ملخص المستخلص
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="text-muted mb-1">إجمالي المبلغ</h6>
                                            <h4 class="text-warning mb-0" id="totalAmount">0.00 ريال</h4>
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
                                            <h4 class="text-warning mb-0" id="netAmount">0.00 ريال</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- المرفقات -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-paperclip text-warning me-2"></i>
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
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save me-2"></i>
                                        حفظ المستخلص النهائي للجزئية
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {


    let selectedWorkOrders = [];

    // تحديث الملخص عند تحميل الصفحة
    updateSummary();

    // عند اختيار مستخلص جزئي مرتبط
    $('#related_partial_extract_id').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const branchId = selectedOption.data('branch');

        if ($(this).val()) {
            // تحديد الفرع تلقائياً
            if (branchId) {
                $('#branch_id').val(branchId);
            }



            // إظهار معلومات المستخلص الجزئي
            $('#relatedPartialInfo').show();
            loadPartialExtractDetails($(this).val());
        } else {
            $('#relatedPartialInfo').hide();
            $('#branch_id').val('');
            // إعادة تعيين رقم المستخلص للرقم الافتراضي
            $('#extract_number').val('<?php echo $suggestedExtractNumber; ?>');
            // مسح أوامر العمل المختارة
            selectedWorkOrders = [];
            updateSelectedWorkOrders();
            updateSummary();
        }
    });

    function loadPartialExtractDetails(partialExtractId) {
        $('#relatedPartialDetails').html(`
            <div class="text-center">
                <i class="fas fa-spinner fa-spin"></i>
                <p>جاري تحميل تفاصيل المستخلص الجزئي...</p>
            </div>
        `);

        // جلب تفاصيل المستخلص الجزئي عبر AJAX
        $.ajax({
            url: 'get-partial-extract-details.php',
            method: 'GET',
            data: { partial_extract_id: partialExtractId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    // تحديث رقم المستخلص ليكون نفس رقم المستخلص الجزئي
                    $('#extract_number').val(data.extract_number);

                    // تخزين ضريبة المستخلص الجزئي في حقل مخفي
                    if ($('#partial_extract_tax_amount').length === 0) {
                        $('#finalForPartialExtractForm').append(`<input type="hidden" id="partial_extract_tax_amount" value="${data.tax_amount || 0}">`);
                    } else {
                        $('#partial_extract_tax_amount').val(data.tax_amount || 0);
                    }

                    // عرض تفاصيل المستخلص الجزئي
                    $('#relatedPartialDetails').html(`
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>تفاصيل المستخلص الجزئي:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>رقم المستخلص:</strong> ${data.extract_number}</p>
                                    <p><strong>تاريخ المستخلص:</strong> ${data.extract_date}</p>
                                    <p><strong>إجمالي المبلغ:</strong> ${parseFloat(data.total_amount).toLocaleString()} ريال</p>
                                    <p><strong>ضريبة المستخلص الجزئي:</strong> ${parseFloat(data.tax_amount || 0).toLocaleString()} ريال</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>عدد أوامر العمل:</strong> ${data.work_orders_count}</p>
                                    <p><strong>حالة الاعتماد:</strong> ${getApprovalStageText(data.approval_stage)}</p>
                                    <p><strong>تاريخ الإنشاء:</strong> ${data.created_at}</p>
                                </div>
                            </div>
                        </div>
                    `);

                    // تحميل أوامر العمل المرتبطة بالمستخلص الجزئي
                    loadPartialExtractWorkOrders(data.work_orders);

                } else {
                    $('#relatedPartialDetails').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            خطأ في تحميل تفاصيل المستخلص: ${response.message}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#relatedPartialDetails').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        تعذر الاتصال بالخادم لتحميل تفاصيل المستخلص
                    </div>
                `);
            }
        });
    }

    function loadPartialExtractWorkOrders(workOrders) {
        // مسح أوامر العمل المختارة الحالية
        selectedWorkOrders = [];

        // إضافة أوامر العمل من المستخلص الجزئي
        workOrders.forEach(wo => {
            // حساب القيمة المتبقية (القيمة الفعلية - قيمة المستخلص الجزئي)
            const remainingValue = parseFloat(wo.actual_value || wo.estimated_value) - parseFloat(wo.partial_extract_value);

            selectedWorkOrders.push({
                id: wo.work_order_id,
                number: wo.work_order_number,
                type: wo.work_order_type_code,
                value: wo.actual_value || wo.estimated_value,
                department: wo.department,
                departmentName: wo.department_name,
                branchId: wo.branch_id,
                branchName: wo.branch_name,
                completionDate: wo.completion_date, // من المستخلص الجزئي
                extractValue: remainingValue > 0 ? remainingValue : 0, // القيمة المتبقية
                penaltyAmount: 0 // يدخلها المستخدم
            });
        });

        // تحديث عرض أوامر العمل المختارة
        updateSelectedWorkOrders();

        // تحديث ملخص المستخلص
        updateSummary();

        // إظهار رسالة توضيحية
        if (workOrders.length > 0) {
            $('#relatedPartialDetails').append(`
                <div class="alert alert-success mt-2">
                    <i class="fas fa-check-circle me-2"></i>
                    تم تحميل ${workOrders.length} أمر عمل من المستخلص الجزئي تلقائياً.
                    <br><small>قيم المستخلص تم حسابها كالقيمة المتبقية (القيمة الفعلية - قيمة المستخلص الجزئي)</small>
                </div>
            `);
        }
    }

    function getApprovalStageText(stage) {
        const stages = {
            'technical_support': 'الدعم الفني',
            'construction': 'الإنشاءات',
            'department_manager': 'مدير القسم',
            'administration_manager': 'مدير الإدارة',
            'taif_finance': 'مالية الطائف',
            'disbursed': 'تم الصرف'
        };
        return stages[stage] || stage;
    }



    // حذف أمر عمل
    $(document).on('click', '.remove-work-order', function() {
        const id = parseInt($(this).data('id'));

        // إزالة من المصفوفة
        selectedWorkOrders = selectedWorkOrders.filter(wo => wo.id !== id);

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
                                   name="extract_values[${wo.id}]" step="0.01" min="0" max="${wo.value}"
                                   value="${wo.extractValue || ''}" required>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm penalty-amount"
                                   name="penalty_amounts[${wo.id}]" step="0.01" min="0"
                                   value="${wo.penaltyAmount || 0}">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-work-order" data-id="${wo.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                        <td>
                            <input type="date" class="form-control form-control-sm"
                                   name="completion_dates[${wo.id}]" value="${wo.completionDate || ''}"
                                   readonly style="background-color: #f8f9fa;">
                            <input type="hidden" name="completion_dates[${wo.id}]" value="${wo.completionDate || ''}">
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
    $('#finalForPartialExtractForm').on('submit', function(e) {
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
            $(this).append(`<input type="hidden" name="work_order_ids[]" value="${wo.id}">`);
        });

        // إرسال النموذج عبر AJAX
        submitForm($(this));
    });

    // دالة إرسال النموذج عبر AJAX
    function submitForm($form) {
        const formData = new FormData($form[0]);

        // تسجيل البيانات المرسلة للتشخيص
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }

        // إظهار مؤشر التحميل
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
                        text: response.message || 'حدث خطأ أثناء حفظ المستخلص',
                        confirmButtonText: 'موافق'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال!',
                    text: 'تعذر الاتصال بالخادم. يرجى المحاولة مرة أخرى.',
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

<style>
/* تنسيق حقل رقم المستخلص للقراءة فقط */
#extract_number[readonly] {
    background-color: #f8f9fa;
    border-color: #dee2e6;
    color: #495057;
    cursor: not-allowed;
}

#extract_number[readonly]:focus {
    background-color: #f8f9fa;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* تحسين مظهر الرسائل التوضيحية */
.text-muted .fas {
    color: #6c757d;
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تحميل التخطيط
require_once __DIR__ . '/../../includes/layout.php';
?>
