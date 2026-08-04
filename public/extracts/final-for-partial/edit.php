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
           pe.tax_amount as partial_extract_tax_amount,
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="fas fa-edit text-warning me-2"></i>
                        تعديل المستخلص النهائي للجزئية
                    </h5>
                    <p class="text-muted mb-0 small">تعديل المستخلص رقم: <?= htmlspecialchars($extract['extract_number']) ?></p>
                </div>
                <div>
                    <a href="view.php?id=<?= $extractId ?>" class="btn btn-outline-primary rounded-pill px-3 shadow-sm btn-sm me-2">
                        <i class="fas fa-eye me-1"></i>
                        عرض التفاصيل
                    </a>
                    <a href="index.php" class="btn btn-light rounded-pill px-3 shadow-sm text-secondary fw-bold border-0 btn-sm">
                        <i class="fas fa-arrow-right me-2"></i>
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
                
                <!-- معلومات المستخلص الأساسية -->
                <div class="card dash-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="fas fa-info-circle text-warning opacity-75 me-2"></i>معلومات المستخلص الأساسية
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="extract_number" class="form-label small fw-bold mb-1">رقم المستخلص <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="extract_number" name="extract_number"
                                       value="<?= htmlspecialchars($extract['extract_number']) ?>" readonly required>
                                <small class="text-muted" style="font-size: 0.75rem;">رقم المستخلص لا يمكن تعديله</small>
                            </div>
                            <div class="col-md-3">
                                <label for="extract_date" class="form-label small fw-bold mb-1">تاريخ المستخلص <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" id="extract_date" name="extract_date" 
                                       value="<?= $extract['extract_date'] ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="invoice_number" class="form-label small fw-bold mb-1">رقم الفاتورة</label>
                                <input type="text" class="form-control form-control-sm" id="invoice_number" name="invoice_number"
                                       value="<?= htmlspecialchars($extract['invoice_number'] ?? '') ?>"
                                       placeholder="رقم الفاتورة (اختياري)">
                            </div>
                            <div class="col-md-3">
                                <label for="branch_id" class="form-label small fw-bold mb-1">الفرع <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="branch_id" name="branch_id" required>
                                    <option value="">اختر الفرع</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>" <?= $branch['id'] == $extract['branch_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($branch['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="related_partial_extract_display" class="form-label small fw-bold mb-1">المستخلص الجزئي المرتبط</label>
                                <input type="text" class="form-control form-control-sm" id="related_partial_extract_display"
                                       value="<?= htmlspecialchars($extract['related_partial_extract_number']) ?>" readonly>
                                <input type="hidden" name="related_partial_extract_id" value="<?= $extract['related_partial_extract_id'] ?>">
                                <small class="text-muted" style="font-size: 0.75rem;">لا يمكن تغيير المستخلص الجزئي المرتبط بعد الإنشاء</small>
                            </div>
                            <div class="col-md-6">
                                <label for="description" class="form-label small fw-bold mb-1">وصف المستخلص</label>
                                <input type="text" class="form-control form-control-sm" id="description" name="description"
                                          placeholder="وصف تفصيلي للمستخلص النهائي للجزئية..." value="<?= htmlspecialchars($extract['description'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- معلومات المستخلص الجزئي المرتبط -->
                <div class="card dash-card shadow-sm border-0 mb-4" id="relatedPartialInfo" style="background-color: #f8fafc; <?= $extract['related_partial_extract_id'] ? '' : 'display: none;' ?>">
                    <div class="card-body p-3" id="partialExtractDetails">
                        <div class="alert alert-info mb-0 border-0 bg-transparent p-0">
                            <h6 class="mb-3 text-info fw-bold"><i class="fas fa-info-circle me-2"></i>تفاصيل المستخلص الجزئي:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong class="text-muted me-2">رقم المستخلص:</strong> <?= htmlspecialchars($extract['related_partial_extract_number']) ?></p>
                                    <p class="mb-1"><strong class="text-muted me-2">تاريخ المستخلص:</strong> <?= htmlspecialchars($extract['partial_extract_date']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong class="text-muted me-2">إجمالي المبلغ:</strong> <?= number_format($extract['partial_total_amount'], 2) ?> <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></p>
                                    <p class="mb-1"><strong class="text-muted me-2">ضريبة المستخلص الجزئي:</strong> <?= number_format($extract['partial_extract_tax_amount'] ?? 0, 2) ?> <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></p>
                                    <input type="hidden" id="partial_extract_tax_amount" value="<?= $extract['partial_extract_tax_amount'] ?? 0 ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- أوامر العمل المرتبطة -->
                <div class="card dash-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="fas fa-check-circle text-primary opacity-75 me-2"></i>أوامر العمل المرتبطة
                            <span class="badge bg-primary-soft text-primary rounded-pill ms-1" id="workOrdersCount"><?= count($extractWorkOrders) ?></span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="selectedWorkOrdersContainer" class="d-flex flex-column" style="min-height: 150px;">
                            <?php if (!empty($extractWorkOrders)): ?>
                                <div class="table-responsive">
                                    <table class="table premium-table table-hover table-sm align-middle mb-0" id="selectedWorkOrdersTable" style="font-size: 0.85rem;">
                                        <thead style="background-color: #f8fafc; color: #64748b; position: sticky; top: 0; z-index: 1;">
                                            <tr>
                                                <th class="ps-3 border-0">رقم أمر العمل</th>
                                                <th class="border-0">النوع</th>
                                                <th class="border-0">القسم</th>
                                                <th class="border-0" style="width: 150px;">تاريخ الإنجاز <span class="text-danger">*</span></th>
                                                <th class="border-0" style="width: 150px;">قيمة المستخلص <span class="text-danger">*</span></th>
                                                <th class="border-0" style="width: 150px;">الغرامة</th>
                                                <th class="border-0 text-center">الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="selectedWorkOrdersBody">
                                            <?php foreach ($extractWorkOrders as $wo): ?>
                                                <tr data-work-order-id="<?= $wo['work_order_id'] ?>">
                                                    <td class="ps-3 fw-bold text-dark">
                                                        <?= htmlspecialchars($wo['work_order_number']) ?>
                                                        <input type="hidden" name="work_order_ids[]" value="<?= $wo['work_order_id'] ?>">
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-primary-soft text-primary border border-primary border-opacity-25"><?= htmlspecialchars($wo['work_order_type_code']) ?></span>
                                                    </td>
                                                    <td><?= htmlspecialchars($wo['department_name']) ?></td>
                                                    <td>
                                                        <input type="date" class="form-control form-control-sm completion-date text-center bg-light"
                                                               name="completion_dates[<?= $wo['work_order_id'] ?>]"
                                                               value="<?= $wo['completion_date'] ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm extract-value text-center fw-bold text-primary bg-primary-soft border-primary border-opacity-25"
                                                               name="extract_values[<?= $wo['work_order_id'] ?>]"
                                                               value="<?= $wo['extract_value'] ?>"
                                                               step="0.01" min="0" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm penalty-amount text-center fw-bold text-danger bg-danger-soft border-danger border-opacity-25"
                                                               name="penalty_amounts[<?= $wo['work_order_id'] ?>]"
                                                               value="<?= $wo['penalty_amount'] ?>"
                                                               step="0.01" min="0">
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-light text-danger border-0 shadow-sm remove-work-order"
                                                                data-id="<?= $wo['work_order_id'] ?>" style="border-radius: 0.5rem; width: 32px; height: 32px; padding: 0;" title="إزالة">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-5 my-auto" id="emptyMessage">
                                    <div class="icon-circle bg-light mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                        <i class="fas fa-inbox text-muted"></i>
                                    </div>
                                    <p class="mb-0 fw-bold">لم يتم إضافة أوامر عمل بعد</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Summary -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-12">
                        <div class="card dash-card shadow-sm border-0 h-100 bg-primary-soft">
                            <div class="card-body py-3 d-flex flex-column justify-content-center">
                                <div class="row text-center g-2 align-items-center">
                                    <div class="col px-1">
                                        <small class="text-muted fw-bold d-block mb-1">الإجمالي</small>
                                        <h5 class="text-dark fw-bold mb-0 fs-6" id="totalAmount"><?= number_format($extract['total_amount'], 2) ?></h5>
                                    </div>
                                    <div class="col-auto text-muted small"><i class="fas fa-plus"></i></div>
                                    <div class="col px-1 border-start border-primary border-opacity-25">
                                        <small class="text-muted fw-bold d-block mb-1">الضريبة</small>
                                        <h5 class="text-dark fw-bold mb-0 fs-6" id="taxAmount"><?= number_format($extract['tax_amount'], 2) ?></h5>
                                    </div>
                                    <div class="col-auto text-muted small"><i class="fas fa-plus"></i></div>
                                    <div class="col px-1 border-start border-primary border-opacity-25">
                                        <small class="text-muted fw-bold d-block mb-1">ضريبة الجزئي</small>
                                        <h5 class="text-dark fw-bold mb-0 fs-6" id="partialTaxAmount"><?= number_format($extract['partial_extract_tax_amount'] ?? 0, 2) ?></h5>
                                    </div>
                                    <div class="col-auto text-muted small"><i class="fas fa-minus"></i></div>
                                    <div class="col px-1 border-start border-primary border-opacity-25">
                                        <small class="text-muted fw-bold d-block mb-1">الغرامات</small>
                                        <h5 class="text-danger fw-bold mb-0 fs-6" id="totalPenalty"><?= number_format($extract['total_penalty_amount'], 2) ?></h5>
                                    </div>
                                </div>
                                <div class="row mt-3 text-center">
                                    <div class="col-12 px-2 border-top border-primary border-opacity-25 pt-2">
                                        <small class="text-muted fw-bold d-block mb-1">الصافي النهائي</small>
                                        <h4 class="text-success fw-bold mb-0" id="netAmount"><?= number_format($extract['net_amount'], 2) ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                                    </div>
                                </div>
                                
                                <hr class="my-3 border-primary opacity-25">
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-primary fw-bold"><i class="fas fa-info-circle me-1"></i> يرجى التأكد من صحة القيم المدخلة قبل الحفظ.</small>
                                    <button type="submit" class="btn btn-warning rounded-pill px-4 shadow-sm" name="action" value="submit" id="submitBtn">
                                        <i class="fas fa-save me-2"></i>حفظ التعديلات
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

        // تحديث الملخص بعد تحميل ضريبة المستخلص الجزئي
        const partialExtractTax = extract.tax_amount || 0;

        let html = `
            <div class="alert alert-info mb-0 border-0 bg-transparent p-0">
                <h6 class="mb-3 text-info fw-bold"><i class="fas fa-info-circle me-2"></i>تفاصيل المستخلص الجزئي:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong class="text-muted me-2">رقم المستخلص:</strong> ${extract.extract_number || 'غير متوفر'}</p>
                        <p class="mb-1"><strong class="text-muted me-2">تاريخ المستخلص:</strong> ${extract.extract_date || 'غير متوفر'}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong class="text-muted me-2">إجمالي المبلغ:</strong> ${extract.total_amount ? parseFloat(extract.total_amount).toLocaleString('ar-SA', {minimumFractionDigits: 2}) : '0.00'} <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></p>
                        <p class="mb-1"><strong class="text-muted me-2">ضريبة المستخلص الجزئي:</strong> ${parseFloat(partialExtractTax).toLocaleString('ar-SA', {minimumFractionDigits: 2})} <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></p>
                        <input type="hidden" id="partial_extract_tax_amount" value="${partialExtractTax}">
                    </div>
                </div>
            </div>
        `;

        $('#partialExtractDetails').html(html);
        
        // تحديث الملخص بعد إضافة الحقل المخفي
        updateSummary();
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
        const partialExtractTax = parseFloat($('#partial_extract_tax_amount').val()) || 0;

        // الصافي = مجموع قيم أوامر العمل + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامة
        const net = total + tax + partialExtractTax - totalPenalty;

        $('#totalAmount').text(total.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#taxAmount').text(tax.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#partialTaxAmount').text(partialExtractTax.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#totalPenalty').text(totalPenalty.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
        $('#netAmount').text(net.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ريال');
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
