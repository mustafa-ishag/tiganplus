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

<!-- تعريف رمز الريال السعودي SVG -->
<svg style="display: none;">
    <symbol id="sar-symbol" viewBox="0 0 1124.14 1256.39">
        <path d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z"/>
        <path d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z"/>
    </symbol>
</svg>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fas fa-file-invoice text-primary me-2"></i>
                        إنشاء مستخلص نهائي للجزئية جديد
                    </h5>
                    <p class="text-muted mb-0 small">إضافة مستخلص نهائي للجزئية جديد للمشروع</p>
                </div>
                <a href="../index.php" class="btn btn-light rounded-pill px-3 shadow-sm text-secondary fw-bold border-0">
                    <i class="fas fa-arrow-right me-2"></i>العودة للقائمة
                </a>
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
                <!-- معلومات المستخلص الأساسية -->
                <div class="card dash-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="fas fa-file-invoice-dollar text-primary opacity-75 me-2"></i>معلومات المستخلص الأساسية
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="extract_number" class="form-label small fw-bold mb-1">رقم المستخلص <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="extract_number" name="extract_number"
                                       value="<?php echo $suggestedExtractNumber; ?>" readonly required>
                                <small class="text-muted" style="font-size: 0.75rem;">يحدد تلقائياً</small>
                            </div>
                            <div class="col-md-3">
                                <label for="extract_date" class="form-label small fw-bold mb-1">تاريخ المستخلص <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="extract_date" name="extract_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="invoice_number" class="form-label small fw-bold mb-1">رقم الفاتورة</label>
                                <input type="text" class="form-control form-control-sm" id="invoice_number" name="invoice_number">
                            </div>
                            <div class="col-md-3">
                                <label for="branch_id" class="form-label small fw-bold mb-1">الفرع <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="branch_id" name="branch_id" required>
                                    <option value="">اختر الفرع</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="related_partial_extract_id" class="form-label small fw-bold mb-1">المستخلص الجزئي المرتبط <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="related_partial_extract_id" name="related_partial_extract_id" required>
                                    <option value="">اختر المستخلص الجزئي</option>
                                    <?php if (empty($partialExtracts)): ?>
                                        <option value="" disabled>لا توجد مستخلصات جزئية مؤهلة</option>
                                    <?php else: ?>
                                        <?php foreach ($partialExtracts as $pe): ?>
                                            <option value="<?php echo $pe['id']; ?>" data-branch="<?php echo $pe['branch_id']; ?>">
                                                <?php echo htmlspecialchars($pe['extract_number']); ?> - <?php echo htmlspecialchars($pe['branch_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label small fw-bold mb-1">وصف المستخلص</label>
                                <input type="text" class="form-control form-control-sm" id="description" name="description"
                                       placeholder="وصف تفصيلي للمستخلص النهائي للجزئية...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات المستخلص الجزئي المرتبط (تظهر عند الاختيار) -->
                <div class="card dash-card shadow-sm border-0 mb-4" id="relatedPartialInfo" style="display: none; background-color: #f8fafc;">
                    <div class="card-body p-3" id="relatedPartialDetails">
                        <!-- سيتم ملء هذا القسم ديناميكياً -->
                    </div>
                </div>

                <!-- أوامر العمل المختارة -->
                <div class="card dash-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="fas fa-check-circle text-success opacity-75 me-2"></i>أوامر العمل المرتبطة
                            <span class="badge bg-success-soft text-success rounded-pill ms-1" id="selectedCount">0</span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="selectedWorkOrdersContainer" class="d-flex flex-column" style="min-height: 150px;">
                            <div class="text-center text-muted py-5 my-auto" id="emptyMessage">
                                <div class="icon-circle bg-light mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                    <i class="fas fa-inbox text-muted"></i>
                                </div>
                                <p class="mb-0 fw-bold">لم يتم اختيار مستخلص جزئي بعد</p>
                                <small>يرجى اختيار المستخلص الجزئي لجلب أوامر العمل</small>
                            </div>
                            
                            <div id="selectedWorkOrdersTable" style="display: none;" class="table-responsive">
                                <table class="table premium-table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead style="background-color: #f8fafc; color: #64748b; position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th class="ps-3 border-0">رقم الأمر</th>
                                            <th class="border-0">النوع</th>
                                            <th class="border-0">القيمة الفعلية</th>
                                            <th class="border-0" style="width: 150px;">قيمة المستخلص <span class="text-danger">*</span></th>
                                            <th class="border-0" style="width: 150px;">الغرامة</th>
                                            <th class="border-0 text-center">حذف</th>
                                            <th class="pe-3 border-0">تاريخ الإنجاز</th>
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
                                    <div class="col px-1">
                                        <small class="text-muted fw-bold d-block mb-1">الإجمالي</small>
                                        <h5 class="text-dark fw-bold mb-0 fs-6" id="totalAmount">0.00</h5>
                                    </div>
                                    <div class="col-auto text-muted small"><i class="fas fa-plus"></i></div>
                                    <div class="col px-1 border-start border-primary border-opacity-25">
                                        <small class="text-muted fw-bold d-block mb-1">الضريبة</small>
                                        <h5 class="text-dark fw-bold mb-0 fs-6" id="taxAmount">0.00</h5>
                                    </div>
                                    <div class="col-auto text-muted small"><i class="fas fa-plus"></i></div>
                                    <div class="col px-1 border-start border-primary border-opacity-25">
                                        <small class="text-muted fw-bold d-block mb-1">ضريبة الجزئي</small>
                                        <h5 class="text-dark fw-bold mb-0 fs-6" id="partialTaxAmount">0.00</h5>
                                    </div>
                                    <div class="col-auto text-muted small"><i class="fas fa-minus"></i></div>
                                    <div class="col px-1 border-start border-primary border-opacity-25">
                                        <small class="text-muted fw-bold d-block mb-1">الغرامات</small>
                                        <h5 class="text-danger fw-bold mb-0 fs-6" id="totalPenalty">0.00</h5>
                                    </div>
                                </div>
                                <div class="row mt-3 text-center">
                                    <div class="col-12 px-2 border-top border-primary border-opacity-25 pt-2">
                                        <small class="text-muted fw-bold d-block mb-1">الصافي النهائي</small>
                                        <h4 class="text-success fw-bold mb-0" id="netAmount">0.00 <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                                    </div>
                                </div>
                                
                                <hr class="my-3 border-primary opacity-25">
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-primary fw-bold"><i class="fas fa-info-circle me-1"></i> يرجى التأكد من صحة القيم المدخلة قبل الحفظ.</small>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" name="action" value="submit" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>حفظ المستخلص النهائي
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
                                    <p><strong>إجمالي المبلغ:</strong> ${parseFloat(data.total_amount).toLocaleString()} <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></p>
                                    <p><strong>ضريبة المستخلص الجزئي:</strong> ${parseFloat(data.tax_amount || 0).toLocaleString()} <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></p>
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
                        <td class="ps-3 fw-bold text-dark">${wo.number}</td>
                        <td><span class="badge bg-primary-soft text-primary border border-primary border-opacity-25">${wo.type}</span></td>
                        <td class="text-success fw-bold">${parseFloat(wo.value).toLocaleString('ar-SA', {minimumFractionDigits: 2})} <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></td>
                        <td>
                            <input type="number" class="form-control form-control-sm extract-value text-center fw-bold text-primary bg-primary-soft border-primary border-opacity-25"
                                   name="extract_values[${wo.id}]" step="0.01" min="0" max="${wo.value}"
                                   value="${wo.extractValue || ''}" required>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm penalty-amount text-center fw-bold text-danger bg-danger-soft border-danger border-opacity-25"
                                   name="penalty_amounts[${wo.id}]" step="0.01" min="0"
                                   value="${wo.penaltyAmount || 0}">
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-light text-danger border-0 shadow-sm remove-work-order" style="border-radius: 0.5rem; width: 32px; height: 32px; padding: 0;" data-id="${wo.id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                        <td class="pe-3">
                            <input type="date" class="form-control form-control-sm bg-light"
                                   name="completion_dates[${wo.id}]" value="${wo.completionDate || ''}"
                                   readonly style="color: #64748b;">
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

        $('#totalAmount').html(total.toLocaleString('ar-SA', {minimumFractionDigits: 2}));
        $('#taxAmount').html(tax.toLocaleString('ar-SA', {minimumFractionDigits: 2}));
        $('#partialTaxAmount').html(partialExtractTax.toLocaleString('ar-SA', {minimumFractionDigits: 2}));
        $('#totalPenalty').html(totalPenalty.toLocaleString('ar-SA', {minimumFractionDigits: 2}));
        $('#netAmount').html(net.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span>');
    }

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
