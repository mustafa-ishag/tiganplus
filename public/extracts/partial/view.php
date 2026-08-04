<?php
/**
 * صفحة عرض تفاصيل المستخلص الجزئي
 * Partial Extract View Page
 */

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: /etganplus/public/auth/login.php');
    exit();
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_view_details')) {
    header('Location: index.php');
    exit();
}

// التحقق من وجود معرف المستخلص
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$extract_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();
} catch (Exception $e) {
    echo "خطأ في الاتصال: " . $e->getMessage();
    exit();
}

// جلب تفاصيل المستخلص الجزئي
$extract = null;
$workOrders = [];

try {
    $extractQuery = "
        SELECT pe.*,
               b.name as branch_name,
               b.code as branch_code,
               u.full_name as created_by_name,
               (SELECT con.contract_number FROM work_orders wo2 
                JOIN contracts con ON wo2.contract_id = con.id 
                JOIN partial_extract_work_orders pewo2 ON wo2.id = pewo2.work_order_id 
                WHERE pewo2.partial_extract_id = pe.id LIMIT 1) as contract_number
        FROM partial_extracts pe
        LEFT JOIN branches b ON pe.branch_id = b.id
        LEFT JOIN users u ON pe.created_by = u.id
        WHERE pe.id = ?
    ";

    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();

    if (!$extract) {
        echo "<div class='alert alert-danger'>المستخلص غير موجود</div>";
        echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
        exit();
    }

    // جلب أوامر العمل المرتبطة بالمستخلص مع بيانات المرفقات
    $workOrdersQuery = "
        SELECT pewo.*, wo.work_order_number,
               wo.actual_value,
               wo.estimated_value,
               wot.type_code, wot.description as work_order_type_description,
               -- شهادة الإنجاز
               cc.completion_certificate_confirmation,
               -- التخريد (demolition_form)
               df.status as demolition_status,
               -- التحقق من القيمة السالبة
               CASE
                   WHEN wo.actual_value > 0 AND pewo.extract_value > wo.actual_value
                   THEN 1
                   ELSE 0
               END as has_negative_value
        FROM partial_extract_work_orders pewo
        LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        -- شهادة الإنجاز
        LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
        -- نموذج التخريد
        LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
        WHERE pewo.partial_extract_id = ?
        ORDER BY wo.work_order_number
    ";

    $stmt = $db->prepare($workOrdersQuery);
    $stmt->execute([$extract_id]);
    $workOrders = $stmt->fetchAll();

    // جلب مراحل الاعتماد من قاعدة البيانات
    $approvalStagesQuery = "SELECT * FROM approval_stages WHERE is_active = 1 ORDER BY stage_order";
    $approvalStagesFromDB = $db->query($approvalStagesQuery)->fetchAll();

    // جلب بيانات الاعتمادات المبسطة
    $approvalsQuery = "
        SELECT
            pe.approval_stage,
            pe.disbursement_date,
            pe.approved_by,
            pe.approval_date,
            pe.approval_notes,
            u.full_name as approved_by_name
        FROM partial_extracts pe
        LEFT JOIN users u ON pe.approved_by = u.id
        WHERE pe.id = ?
    ";

    $stmt = $db->prepare($approvalsQuery);
    $stmt->execute([$extract_id]);
    $approvals = $stmt->fetch();

    // جلب مرفقات المستخلص
    $attachmentsQuery = "
        SELECT
            pea.*,
            u.full_name as uploaded_by_name
        FROM partial_extract_attachments pea
        LEFT JOIN users u ON pea.uploaded_by = u.id
        WHERE pea.partial_extract_id = ?
        ORDER BY pea.uploaded_at DESC
    ";

    $stmt = $db->prepare($attachmentsQuery);
    $stmt->execute([$extract_id]);
    $attachments = $stmt->fetchAll();

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>خطأ في جلب البيانات: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
    exit();
}

$pageTitle = 'عرض المستخلص الجزئي - ' . $extract['extract_number'];
$currentPage = 'extracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات الجزئية', 'url' => 'extracts/partial/index.php'],
    ['title' => 'عرض المستخلص', 'url' => '']
];

// بدء تخزين المحتوى
ob_start();
?>

<style>
/* Modern Dash Card Style */
.dash-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.dash-card:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
}
.bg-primary-soft { background-color: rgba(13, 110, 253, 0.05) !important; border: 1px solid rgba(13, 110, 253, 0.1); }
.bg-success-soft { background-color: rgba(25, 135, 84, 0.05) !important; border: 1px solid rgba(25, 135, 84, 0.1); }
.bg-warning-soft { background-color: rgba(255, 193, 7, 0.05) !important; border: 1px solid rgba(255, 193, 7, 0.1); }
.bg-danger-soft { background-color: rgba(220, 53, 69, 0.05) !important; border: 1px solid rgba(220, 53, 69, 0.1); }
.bg-info-soft { background-color: rgba(13, 202, 240, 0.05) !important; }
.bg-secondary-soft { background-color: rgba(108, 117, 125, 0.05) !important; }
.bg-dark-soft { background-color: rgba(33, 37, 41, 0.05) !important; }
.icon-circle {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.table-sm th, .table-sm td { padding: 0.75rem 0.5rem; }
</style>

<!-- Container for Bootstrap Alerts (Replaces SweetAlert) -->
<div id="alertContainer" class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1050; width: 100%; max-width: 600px;"></div>

<div class="container-fluid px-4">
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center mb-1">
                <h5 class="fw-bold text-dark mb-0 me-3">
                    <i class="fas fa-file-invoice text-primary me-2"></i>عرض المستخلص الجزئي
                </h5>
                <?php
                // تحديد حالة المستخلص
                $currentStageColor = 'secondary';
                $currentStageName = 'مسودة';
                foreach ($approvalStagesFromDB as $stage) {
                    if ($extract['approval_stage'] === $stage['stage_key']) {
                        $currentStageColor = $stage['stage_color'];
                        $currentStageName = $stage['stage_name'];
                        break;
                    }
                }
                ?>
                <span class="badge bg-<?php echo $currentStageColor; ?>-soft text-<?php echo $currentStageColor; ?> border rounded-pill px-3 py-1">
                    <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> <?php echo $currentStageName; ?>
                </span>
            </div>
            <p class="text-muted mb-0 small">رقم المستخلص: <span class="fw-bold text-dark"><?php echo htmlspecialchars($extract['extract_number']); ?></span></p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!isset($extract['qoyod_status']) || $extract['qoyod_status'] !== 'synced'): ?>
                <button onclick="syncWithQoyod(<?php echo $extract['id']; ?>, 'partial')" class="btn btn-info rounded-pill px-3 shadow-sm btn-sm text-white" id="btnSyncQoyod">
                    <i class="fas fa-cloud-upload-alt me-1"></i> رفع لقيود
                </button>
            <?php else: ?>
                <span class="badge bg-success-soft text-success border rounded-pill px-3 py-2 d-flex align-items-center" style="font-size: 0.85rem;">
                    <i class="fas fa-check-circle me-1"></i> متزامن مع قيود (<?php echo htmlspecialchars($extract['qoyod_invoice_reference'] ?? 'تم'); ?>)
                </span>
            <?php endif; ?>
            <a href="../../preview-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm" target="_blank">
                <i class="fas fa-eye me-1"></i> الفاتورة
            </a>
            <a href="export-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-success rounded-pill px-3 shadow-sm btn-sm" download>
                <i class="fas fa-download me-1"></i> تحميل
            </a>
            <a href="index.php" class="btn btn-light rounded-pill px-3 shadow-sm text-secondary fw-bold border-0 btn-sm">
                <i class="fas fa-arrow-right me-2"></i>العودة
            </a>
        </div>
    </div>
    <!-- Financial Summary (Horizontal) -->
    <div class="card dash-card bg-primary-soft mb-4 border-0">
        <div class="card-body py-3 d-flex flex-column justify-content-center">
            <div class="row text-center g-2 align-items-center">
                <div class="col-4 px-3">
                    <small class="text-muted fw-bold d-block mb-1">المبلغ الإجمالي</small>
                    <h4 class="text-dark fw-bold mb-0"><?php echo number_format($extract['total_amount'], 2); ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                </div>
                <div class="col-4 border-start border-end border-primary border-opacity-25 px-3">
                    <small class="text-muted fw-bold d-block mb-1">مبلغ الضريبة (<?php echo number_format($extract['tax_rate'], 0); ?>%)</small>
                    <h4 class="text-dark fw-bold mb-0"><?php echo number_format($extract['tax_amount'] ?? ($extract['total_amount'] * ($extract['tax_rate']/100)), 2); ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                </div>
                <div class="col-4 px-3">
                    <small class="text-muted fw-bold d-block mb-1">الصافي (بدون ضريبة)</small>
                    <h3 class="text-success fw-bold mb-0"><?php echo number_format($extract['net_amount'], 2); ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Basic Info -->
    <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-0 py-3" style="border-radius: 20px 20px 0 0;">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-info-circle text-primary opacity-75 me-2"></i>معلومات المستخلص الأساسية
            </h6>
        </div>
        <div class="card-body py-3 pt-0">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-hashtag me-1"></i>رقم المستخلص</label>
                    <p class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($extract['extract_number']); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-file-signature me-1"></i>رقم العقد</label>
                    <p class="mb-0">
                        <?php if (!empty($extract['contract_number'])): ?>
                            <span class="badge bg-dark-soft text-dark border px-2 py-1"><?php echo htmlspecialchars($extract['contract_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted small">لا يوجد</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-shopping-cart me-1"></i>رقم PO</label>
                    <p class="mb-0">
                        <?php if (!empty($extract['po_number'])): ?>
                            <span class="badge bg-info-soft text-info border px-2 py-1"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted small">لا يوجد</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-file-excel me-1"></i>صحيفة الإدخال</label>
                    <p class="mb-0">
                        <?php if (!empty($extract['entry_sheet_number'])): ?>
                            <span class="badge bg-secondary-soft text-secondary border px-2 py-1"><?php echo htmlspecialchars($extract['entry_sheet_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted small">لا يوجد</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-receipt me-1"></i>رقم الفاتورة</label>
                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($extract['invoice_number'] ?? 'لا يوجد'); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-code-branch me-1"></i>الفرع</label>
                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($extract['branch_name'] ?? 'غير محدد'); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-building me-1"></i>القسم</label>
                    <p class="mb-0 fw-bold">
                        <?php 
                        $departments = ['connections' => 'التوصيلات', 'projects' => 'المشاريع'];
                        echo $departments[$extract['department']] ?? $extract['department'];
                        ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-calendar-alt me-1"></i>تاريخ المستخلص</label>
                    <p class="mb-0 fw-bold"><?php echo date('Y-m-d', strtotime($extract['extract_date'])); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-calendar-check me-1"></i>تاريخ التقديم</label>
                    <p class="mb-0 fw-bold"><?php echo $extract['submission_date'] ? date('Y-m-d', strtotime($extract['submission_date'])) : '<span class="text-warning">لم يتم التقديم</span>'; ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-clock me-1"></i>تاريخ الإنشاء</label>
                    <p class="mb-0 fw-bold"><?php echo date('Y-m-d H:i', strtotime($extract['created_at'])); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-user me-1"></i>أنشئ بواسطة</label>
                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($extract['created_by_name'] ?? 'غير محدد'); ?></p>
                </div>
                <div class="col-md-12">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-sticky-note me-1"></i>ملاحظات المستخلص</label>
                    <p class="mb-0 p-2 bg-light rounded border text-dark"><?php echo htmlspecialchars($extract['description'] ?? 'لا توجد ملاحظات'); ?></p>
                </div>
            </div>
        </div>
    </div>

            <!-- تنبيه القيمة السالبة -->
            <?php
            $negativeValueCount = 0;
            foreach ($workOrders as $wo) {
                if ($wo['has_negative_value']) {
                    $negativeValueCount++;
                }
            }
            if ($negativeValueCount > 0):
            ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>تحذير: مستخلص سالب!</strong>
                <p class="mb-0">يوجد <strong><?php echo $negativeValueCount; ?></strong> أمر عمل بقيمة سالبة (قيمة المستخلص أعلى من القيمة الفعلية).</p>
                <p class="mb-0 mt-2"><small>⚠️ لا يمكن إنشاء مستخلص نهائي للجزئية حتى يتم تصحيح القيم السالبة.</small></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- أوامر العمل -->
            <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
                <div class="card-header bg-white border-0 py-3" style="border-radius: 20px 20px 0 0;">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-list text-info opacity-75 me-2"></i>أوامر العمل المرتبطة <span class="badge bg-secondary-soft text-secondary rounded-pill ms-1"><?php echo count($workOrders); ?></span>
                        <?php if ($negativeValueCount > 0): ?>
                            <span class="badge bg-warning text-dark ms-2 rounded-pill">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <?php echo $negativeValueCount; ?> قيمة سالبة
                            </span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body py-0 pb-3">
                    <?php if (empty($workOrders)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>لا توجد أوامر عمل مرتبطة بهذا المستخلص</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr class="table-light text-muted small">
                                        <th class="border-0 rounded-start">رقم أمر العمل</th>
                                        <th class="border-0 text-center">النوع</th>
                                        <th class="border-0">القيمة الفعلية</th>
                                        <th class="border-0">قيمة المستخلص</th>
                                        <th class="border-0">تاريخ الإنجاز</th>
                                        <th class="border-0 text-center">شهادة الإنجاز</th>
                                        <th class="border-0 text-center rounded-end">التخريد</th>
                                    </tr>
                                </thead>
                                <tbody class="border-top-0">
                                    <?php foreach ($workOrders as $wo): ?>
                                    <tr style="vertical-align: middle;">
                                        <td class="fw-bold">
                                            <?php echo htmlspecialchars($wo['work_order_number']); ?>
                                            <?php if ($wo['has_negative_value']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-1"
                                                   title="قيمة سالبة" style="cursor: help;"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary-soft text-primary border rounded-pill" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($wo['type_code']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $actualValue = $wo['actual_value'] ?? $wo['estimated_value'] ?? 0;
                                            echo number_format($actualValue, 2);
                                            ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span>
                                        </td>
                                        <td class="fw-bold <?php echo $wo['has_negative_value'] ? 'text-danger' : 'text-primary'; ?>">
                                            <?php echo number_format($wo['extract_value'], 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span>
                                        </td>
                                        <td>
                                            <?php if ($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null): ?>
                                                <!-- حقل قابل للتعديل للمستخلصات في حالة المسودة -->
                                                <input type="date" class="form-control form-control-sm completion-date-input rounded-3 shadow-none border-secondary text-dark px-2"
                                                       data-work-order-id="<?php echo $wo['work_order_id']; ?>"
                                                       value="<?php echo !empty($wo['completion_date']) ? date('Y-m-d', strtotime($wo['completion_date'])) : ''; ?>"
                                                       style="min-width: 140px; font-size: 0.85rem;">
                                            <?php else: ?>
                                                <!-- عرض للقراءة فقط للمستخلصات المقدمة -->
                                                <span class="text-muted fw-bold">
                                                    <?php echo !empty($wo['completion_date']) ? date('Y-m-d', strtotime($wo['completion_date'])) : '<span class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i>غير محدد</span>'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $confirmationStatus = $wo['completion_certificate_confirmation'] ?? 'empty';
                                            switch ($confirmationStatus) {
                                                case 'confirmed':
                                                    echo '<span class="badge bg-success-soft text-success border rounded-pill"><i class="fas fa-check me-1"></i>مؤكد</span>';
                                                    break;
                                                case 'accepted':
                                                    echo '<span class="badge bg-info-soft text-info border rounded-pill"><i class="fas fa-thumbs-up me-1"></i>مقبول</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge bg-danger-soft text-danger border rounded-pill"><i class="fas fa-times me-1"></i>مرفوض</span>';
                                                    break;
                                                case 'empty':
                                                default:
                                                    echo '<span class="badge bg-secondary-soft text-secondary border rounded-pill"><i class="fas fa-minus me-1"></i>فارغ</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $demolitionStatus = $wo['demolition_status'] ?? 'not_applicable';
                                            switch ($demolitionStatus) {
                                                case 'attached':
                                                    echo '<span class="badge bg-success-soft text-success border rounded-pill"><i class="fas fa-paperclip me-1"></i>مرفق</span>';
                                                    break;
                                                case 'not_applicable':
                                                    echo '<span class="badge bg-secondary-soft text-secondary border rounded-pill"><i class="fas fa-ban me-1"></i>لا ينطبق</span>';
                                                    break;
                                                case 'not_attached':
                                                default:
                                                    echo '<span class="badge bg-warning-soft text-warning border rounded-pill"><i class="fas fa-exclamation me-1"></i>غير مرفق</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if (($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null) && !empty($workOrders)): ?>
                                <!-- زر حفظ التغييرات للمستخلصات في حالة المسودة -->
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold" id="saveCompletionDates">
                                        <i class="fas fa-save me-2"></i>
                                        حفظ تواريخ الإنجاز
                                    </button>
                                    <small class="text-muted d-block mt-1">
                                        سيتم تحديث تواريخ الاستلام في أوامر العمل أيضاً
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- إدارة الاعتماد -->
            <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
                <div class="card-header bg-warning-soft border-0 py-3" style="border-radius: 20px 20px 0 0;">
                    <h6 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas fa-check-circle text-warning me-2"></i>إدارة الاعتماد
                    </h6>
                </div>
                <div class="card-body py-4">
                    <div class="table-responsive mb-4">
                        <table class="table table-borderless mb-0">
                            <thead class="text-muted small border-bottom">
                                <tr>
                                    <th width="25%" class="pb-2">مرحلة الاعتماد</th>
                                    <th width="20%" class="pb-2">تاريخ الصرف</th>
                                    <th width="30%" class="pb-2">ملاحظات الاعتماد</th>
                                    <th width="25%" class="pb-2">معلومات الاعتماد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="pt-3">
                                        <select class="form-select form-select-sm approval-stage-select rounded-3 shadow-none border-secondary text-dark fw-bold px-2"
                                                data-extract-id="<?php echo $extract_id; ?>"
                                                onchange="updateExtractField(<?php echo $extract_id; ?>, 'approval_stage', this.value, this)">
                                            <?php foreach ($approvalStagesFromDB as $stage): ?>
                                                <?php
                                                $isSelected = false;
                                                if ($extract['approval_stage'] === null && $stage['stage_key'] === 'draft') {
                                                    $isSelected = true; // المستخلصات القديمة
                                                } elseif ($extract['approval_stage'] === $stage['stage_key']) {
                                                    $isSelected = true;
                                                }
                                                ?>
                                                <option value="<?= htmlspecialchars($stage['stage_key']) ?>"
                                                        <?= $isSelected ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($stage['stage_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="pt-3">
                                        <input type="date" class="form-control form-control-sm disbursement-date-input rounded-3 shadow-none text-secondary px-2"
                                               data-extract-id="<?php echo $extract_id; ?>"
                                               value="<?php echo $extract['disbursement_date'] ?? ''; ?>"
                                               onchange="updateExtractField(<?php echo $extract_id; ?>, 'disbursement_date', this.value, this)">
                                    </td>
                                    <td class="pt-3">
                                        <input type="text" class="form-control form-control-sm approval-notes-input rounded-3 shadow-none text-secondary px-2"
                                               data-extract-id="<?php echo $extract_id; ?>"
                                               value="<?php echo htmlspecialchars($extract['approval_notes'] ?? ''); ?>"
                                               placeholder="أدخل ملاحظات الاعتماد..."
                                               onchange="updateExtractField(<?php echo $extract_id; ?>, 'approval_notes', this.value, this)">
                                    </td>
                                    <td class="pt-3">
                                        <div class="small">
                                            <div class="d-flex align-items-center mb-1">
                                                <i class="fas fa-user-circle text-muted me-2 fs-6"></i>
                                                <span id="approved_by_display" class="text-primary fw-bold">
                                                    <?php echo htmlspecialchars($approvals['approved_by_name'] ?? 'لم يتم الاعتماد بعد'); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-clock text-muted me-2 fs-6"></i>
                                                <span id="approval_date_display" class="text-muted">
                                                    <?php
                                                    if (!empty($approvals['approval_date'])) {
                                                        echo date('Y-m-d H:i', strtotime($approvals['approval_date']));
                                                    } else {
                                                        echo 'لم يتم الاعتماد بعد';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- شريط حالة الاعتماد -->
                    <div class="bg-light rounded-4 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fw-bold text-dark small">مرحلة الاعتماد الحالية:</span>
                            <span id="current_stage_badge" class="badge bg-<?php
                                // البحث عن المرحلة الحالية في قاعدة البيانات
                                $currentStageColor = 'secondary';
                                $currentStageName = 'غير محدد';
                                foreach ($approvalStagesFromDB as $stage) {
                                    if ($extract['approval_stage'] === $stage['stage_key'] ||
                                        ($extract['approval_stage'] === null && $stage['stage_key'] === 'draft')) {
                                        $currentStageColor = $stage['stage_color'];
                                        $currentStageName = $stage['stage_name'];
                                        break;
                                    }
                                }
                                echo $currentStageColor;
                            ?>-soft text-<?php echo $currentStageColor; ?> rounded-pill px-3 py-2 border">
                                <?php echo $currentStageName; ?>
                            </span>
                        </div>
                        <div class="progress rounded-pill bg-white border" style="height: 12px;">
                            <?php
                            // حساب التقدم بناءً على ترتيب المراحل من قاعدة البيانات
                            $currentStageOrder = 0;
                            $totalStages = count($approvalStagesFromDB);
                            foreach ($approvalStagesFromDB as $stage) {
                                if ($extract['approval_stage'] === $stage['stage_key'] ||
                                    ($extract['approval_stage'] === null && $stage['stage_key'] === 'draft')) {
                                    $currentStageOrder = $stage['stage_order'];
                                    break;
                                }
                            }
                            $progressPercentage = $totalStages > 0 ? round(($currentStageOrder / $totalStages) * 100) : 0;
                            ?>
                            <div id="approval_progress_bar" class="progress-bar bg-<?php echo $currentStageColor; ?> progress-bar-striped progress-bar-animated" role="progressbar"
                                 style="width: <?php echo $progressPercentage; ?>%"
                                 aria-valuenow="<?php echo $progressPercentage; ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        <div class="small text-muted mt-2 fw-bold text-end" id="progress_text">
                            <?php echo $progressPercentage; ?>% مكتمل (<?php echo $currentStageOrder; ?> من <?php echo $totalStages; ?> مراحل)
                        </div>
                    </div>
                </div>
            </div>

</div>

<?php
// حفظ المحتوى إدارة الاعتماد - تحديث مباشر

$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- إضافة CSS للتحديث المباشر -->
<style>
.updating-field {
    background-color: #fff3cd !important;
    border-color: #ffeaa7 !important;
    position: relative;
}

.updating-field::after {
    content: '';
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid #f39c12;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

.field-success {
    background-color: #d4edda !important;
    border-color: #c3e6cb !important;
    transition: all 0.3s ease;
}

.field-error {
    background-color: #f8d7da !important;
    border-color: #f5c6cb !important;
    transition: all 0.3s ease;
}
</style>

<script>
// بيانات مراحل الاعتماد من PHP
const approvalStagesData = <?php echo json_encode($approvalStagesFromDB); ?>;

// إنشاء كائنات للأسماء والألوان
const stageNames = {};
const stageColors = {};

approvalStagesData.forEach(stage => {
    stageNames[stage.stage_key] = stage.stage_name;
    stageColors[stage.stage_key] = stage.stage_color;
});

console.log('Approval Stages:', stageNames);
console.log('Stage Colors:', stageColors);

$(document).ready(function() {
    console.log('صفحة عرض المستخلص الجزئي جاهزة');
});

// دالة التحديث المباشر للحقول
function updateExtractField(extractId, field, value, element) {
    console.log('Updating field:', field, 'with value:', value, 'for extract:', extractId);

    // إظهار مؤشر التحميل
    const originalValue = $(element).val();
    $(element).prop('disabled', true);

    // إضافة مؤشر بصري للتحديث
    $(element).addClass('updating-field');

    $.ajax({
        url: 'update-approval-ajax.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            extract_id: extractId,
            approval_stage: field === 'approval_stage' ? value : $('.approval-stage-select').val(),
            disbursement_date: field === 'disbursement_date' ? value : $('.disbursement-date-input').val(),
            approval_notes: field === 'approval_notes' ? value : $('.approval-notes-input').val()
        }),
        dataType: 'json',
        success: function(response) {
            console.log('Success response:', response);

            if (response.success) {
                // إظهار نجاح التحديث
                $(element).removeClass('updating-field').addClass('field-success');

                // إظهار رسالة نجاح باستخدام SweetAlert2
                showToast('success', response.message || 'تم التحديث بنجاح');

                // إزالة تأثير النجاح بعد ثانيتين
                setTimeout(function() {
                    $(element).removeClass('field-success');
                }, 2000);

                // إعادة تحميل الصفحة بعد ثانيتين لتحديث العرض
                setTimeout(function() {
                    location.reload();
                }, 2000);

            } else {
                // إظهار خطأ
                $(element).removeClass('updating-field').addClass('field-error');
                $(element).val(originalValue); // إرجاع القيمة الأصلية

                showToast('error', response.message || 'حدث خطأ في التحديث');

                // إزالة تأثير الخطأ بعد 3 ثوانٍ
                setTimeout(function() {
                    $(element).removeClass('field-error');
                }, 3000);
            }
        },
        error: function(xhr, status, error) {
            console.log('Error:', xhr.responseText);

            // إظهار خطأ
            $(element).removeClass('updating-field').addClass('field-error');
            $(element).val(originalValue); // إرجاع القيمة الأصلية

            let errorMessage = 'حدث خطأ في الاتصال';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                // استخدام الرسالة الافتراضية
            }

            showToast('error', errorMessage);

            // إزالة تأثير الخطأ بعد 3 ثوانٍ
            setTimeout(function() {
                $(element).removeClass('field-error');
            }, 3000);
        },
        complete: function() {
            // إعادة تفعيل الحقل
            $(element).prop('disabled', false);
        }
    });
}

// تحديث عرض البيانات
function updateExtractDisplay(data) {
    // استخدام البيانات الديناميكية من قاعدة البيانات (تم تعريفها في أعلى الصفحة)

    // تحديث شارة المرحلة الحالية
    const currentStageBadge = $('#current_stage_badge');
    currentStageBadge.removeClass().addClass('badge bg-' + stageColors[data.approval_stage]);
    currentStageBadge.text(stageNames[data.approval_stage]);

    // تحديث المعتمد
    $('#approved_by_display').text(data.approved_by_name || 'لم يتم الاعتماد بعد');

    // تحديث تاريخ الاعتماد
    $('#approval_date_display').text(data.approval_date || 'لم يتم الاعتماد بعد');

    // تحديث شريط التقدم
    const stageKeys = ['technical_support', 'construction', 'department_manager', 'administration_manager', 'taif_finance', 'disbursed'];
    const currentStageIndex = stageKeys.indexOf(data.approval_stage);
    const progressPercentage = currentStageIndex !== -1 ? Math.round(((currentStageIndex + 1) / stageKeys.length) * 100) : 0;

    $('#approval_progress_bar').css('width', progressPercentage + '%').attr('aria-valuenow', progressPercentage);
    $('#progress_text').text(progressPercentage + '% مكتمل (' + (currentStageIndex + 1) + ' من ' + stageKeys.length + ' مراحل)');
}

// دالة عرض التنبيهات باستخدام Bootstrap Alerts
function showAlert(type, message) {
    const alertId = 'alert-' + Date.now();
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    
    // إنشاء حاوية التنبيهات إذا لم تكن موجودة
    if ($('#alerts-container').length === 0) {
        $('body').append('<div id="alerts-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px;"></div>');
    }
    
    const alertHtml = `
        <div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas ${iconClass} fs-5 me-2"></i>
                <div class="ms-1 fw-bold">${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    $('#alerts-container').append(alertHtml);
    
    // إخفاء التنبيه تلقائياً بعد 3 ثواني
    setTimeout(() => {
        $(`#${alertId}`).alert('close');
    }, 3000);
}

// توجيه دوال Toast القديمة لاستخدام التنبيه الجديد
function showToast(type, message) {
    showAlert(type, message);
}
function showToastOld(type, message) {
    showAlert(type, message);
}

// تحديث تواريخ الإنجاز
$(document).ready(function() {
    // حفظ تواريخ الإنجاز
    $('#saveCompletionDates').click(function() {
        const completionDates = {};
        let hasChanges = false;

        $('.completion-date-input').each(function() {
            const workOrderId = $(this).data('work-order-id');
            const newDate = $(this).val();
            const originalDate = $(this).data('original-value') || $(this).attr('data-original-value');

            if (!$(this).data('original-value')) {
                $(this).data('original-value', $(this).val());
            }

            if (newDate && newDate !== originalDate) {
                completionDates[workOrderId] = newDate;
                hasChanges = true;
            }
        });

        if (!hasChanges) {
            showToast('error', 'لم يتم تغيير أي تواريخ');
            return;
        }

        // تأكيد من المستخدم
        if (!confirm('هل أنت متأكد من تحديث تواريخ الإنجاز؟ سيتم تحديث تواريخ الاستلام في أوامر العمل أيضاً.')) {
            return;
        }

        // إظهار مؤشر التحميل
        const button = $(this);
        const originalText = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...');

        // إرسال طلب AJAX
        $.ajax({
            url: 'update-completion-dates-ajax.php',
            method: 'POST',
            data: {
                extract_id: <?php echo $extract_id; ?>,
                completion_dates: completionDates
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);

                    // تحديث القيم الأصلية
                    $('.completion-date-input').each(function() {
                        $(this).data('original-value', $(this).val());
                    });

                    // إضافة تأثير بصري للتأكيد
                    $('.completion-date-input').addClass('border-success').delay(2000).queue(function() {
                        $(this).removeClass('border-success').dequeue();
                    });
                } else {
                    showToast('error', response.message || 'حدث خطأ أثناء الحفظ');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('error', 'تعذر الاتصال بالخادم');
            },
            complete: function() {
                // إعادة تفعيل الزر
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    // تتبع التغييرات في حقول التاريخ
    $('.completion-date-input').on('change', function() {
        const originalValue = $(this).data('original-value') || $(this).attr('data-original-value');
        if (!$(this).data('original-value')) {
            $(this).data('original-value', $(this).val());
        }

        if ($(this).val() !== originalValue) {
            $(this).addClass('border-warning');
        } else {
            $(this).removeClass('border-warning');
        }
    });
});

function syncWithQoyod(extractId, extractType) {
    if (!confirm('هل أنت متأكد من رغبتك في رفع هذا المستخلص كفاتورة ضريبية في نظام قيود؟')) {
        return;
    }
    
    const btn = $('#btnSyncQoyod');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> جاري الرفع...');
    
    $.ajax({
        url: '<?php echo path("api/qoyod/sync_extract.php"); ?>',
        method: 'POST',
        data: JSON.stringify({
            extract_id: extractId,
            extract_type: extractType
        }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert(response.message || 'حدث خطأ أثناء الرفع');
                btn.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr) {
            let msg = 'تعذر الاتصال بالخادم';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            alert(msg);
            btn.prop('disabled', false).html(originalText);
        }
    });
}

</script>

<style>
/* تأثير نبض لعلامة التحذير */
@keyframes pulse-warning {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

/* تحسين مظهر badge القيمة السالبة */
.badge.bg-warning.text-dark {
    font-size: 0.75rem;
    font-weight: 600;
}
</style>
