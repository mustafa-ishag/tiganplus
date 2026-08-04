<?php
/**
 * صفحة عرض تفاصيل المستخلص النهائي للجزئي
 * Final For Partial Extract View Page
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

// جلب تفاصيل المستخلص النهائي للجزئي
$extract = null;
$workOrders = [];

try {
    $extractQuery = "
        SELECT ffpe.*,
               b.name as branch_name,
               b.code as branch_code,
               u.full_name as created_by_name,
               pe.extract_number as related_partial_extract_number,
               pe.description as partial_extract_description,
               pe.tax_amount as partial_extract_tax_amount,
               (SELECT con.contract_number FROM work_orders wo2 
                JOIN contracts con ON wo2.contract_id = con.id 
                JOIN final_for_partial_extract_work_orders ffpewo2 ON wo2.id = ffpewo2.work_order_id 
                WHERE ffpewo2.final_for_partial_extract_id = ffpe.id LIMIT 1) as contract_number
        FROM final_for_partial_extracts ffpe
        LEFT JOIN branches b ON ffpe.branch_id = b.id
        LEFT JOIN users u ON ffpe.created_by = u.id
        LEFT JOIN partial_extracts pe ON ffpe.related_partial_extract_id = pe.id
        WHERE ffpe.id = ?
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
        SELECT ffpewo.*, wo.work_order_number,
               wot.type_code, wot.description as work_order_type_description,
               -- شهادة الإنجاز
               cc.completion_certificate_confirmation,
               -- التخريد (demolition_form)
               df.status as demolition_status
        FROM final_for_partial_extract_work_orders ffpewo
        LEFT JOIN work_orders wo ON ffpewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        -- شهادة الإنجاز
        LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
        -- نموذج التخريد
        LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
        WHERE ffpewo.final_for_partial_extract_id = ?
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
            ffpe.approval_stage,
            ffpe.disbursement_date,
            ffpe.approved_by,
            ffpe.approval_date,
            ffpe.approval_notes,
            u.full_name as approved_by_name
        FROM final_for_partial_extracts ffpe
        LEFT JOIN users u ON ffpe.approved_by = u.id
        WHERE ffpe.id = ?
    ";

    $stmt = $db->prepare($approvalsQuery);
    $stmt->execute([$extract_id]);
    $approvals = $stmt->fetch();

    // جلب مرفقات المستخلص
    $attachmentsQuery = "
        SELECT
            ffpea.*,
            u.full_name as uploaded_by_name
        FROM final_for_partial_extract_attachments ffpea
        LEFT JOIN users u ON ffpea.uploaded_by = u.id
        WHERE ffpea.final_for_partial_extract_id = ?
        ORDER BY ffpea.uploaded_at DESC
    ";

    $stmt = $db->prepare($attachmentsQuery);
    $stmt->execute([$extract_id]);
    $attachments = $stmt->fetchAll();

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>خطأ في جلب البيانات: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='index.php' class='btn btn-primary'>العودة للقائمة</a>";
    exit();
}

// جلب مراحل الاعتماد
try {
    $approvalStagesFromDB = $db->query("
        SELECT stage_key, stage_name, stage_color, stage_order, is_active
        FROM approval_stages
        WHERE is_active = 1
        ORDER BY stage_order
    ")->fetchAll();

    $stageNames = [];
    $approvalStages = [];

    $stageIcons = [
        'technical_support' => 'fas fa-tools',
        'construction' => 'fas fa-hard-hat',
        'department_manager' => 'fas fa-user-tie',
        'administration_manager' => 'fas fa-crown',
        'taif_finance' => 'fas fa-coins',
        'disbursed' => 'fas fa-check-circle'
    ];

    foreach ($approvalStagesFromDB as $stage) {
        $stageNames[$stage['stage_key']] = $stage['stage_name'];
        $approvalStages[] = [
            'key' => $stage['stage_key'],
            'name' => $stage['stage_name'],
            'icon' => $stageIcons[$stage['stage_key']] ?? 'fas fa-check',
            'color' => $stage['stage_color'] ?? 'primary'
        ];
    }

} catch (Exception $e) {
    // في حالة عدم وجود جدول approval_stages، استخدم القيم الافتراضية
    $stageNames = [
        'draft' => "مسودة",
        "technical_support" => "الدعم الفني",
        "construction" => "الإنشاءات",
        "department_manager" => "مدير القسم",
        "administration_manager" => "مدير الإدارة",
        "taif_finance" => "مالية الطائف",
        "disbursed" => "تم الصرف"
    ];

    $approvalStages = [
        ['key' => 'draft', 'name' => 'مسودة', 'icon' => 'fas fa-edit', 'color' => 'secondary'],
        ['key' => 'technical_support', 'name' => 'الدعم الفني', 'icon' => 'fas fa-tools', 'color' => 'info'],
        ['key' => 'construction', 'name' => 'الإنشاءات', 'icon' => 'fas fa-hard-hat', 'color' => 'warning'],
        ['key' => 'department_manager', 'name' => 'مدير القسم', 'icon' => 'fas fa-user-tie', 'color' => 'primary'],
        ['key' => 'administration_manager', 'name' => 'مدير الإدارة', 'icon' => 'fas fa-crown', 'color' => 'dark'],
        ['key' => 'taif_finance', 'name' => 'مالية الطائف', 'icon' => 'fas fa-coins', 'color' => 'warning'],
        ['key' => 'disbursed', 'name' => 'تم الصرف', 'icon' => 'fas fa-check-circle', 'color' => 'success']
    ];
}

// دالة مساعدة لأسماء مراحل الاعتماد
function getApprovalStageName($stage) {
    global $stageNames;
    return $stageNames[$stage] ?? "غير محدد";
}

$pageTitle = 'عرض المستخلص النهائي للجزئي - ' . $extract['extract_number'];
$currentPage = 'extracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية للجزئية', 'url' => 'extracts/final-for-partial/index.php'],
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

<!-- تعريف رمز الريال السعودي SVG -->
<svg style="display: none;">
    <symbol id="sar-symbol" viewBox="0 0 1124.14 1256.39">
        <path d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z"/>
        <path d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z"/>
    </symbol>
</svg>

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
                    <i class="fas fa-file-invoice-dollar text-primary me-2"></i>عرض المستخلص النهائي للجزئي
                </h5>
                <?php
                // تحديد حالة المستخلص
                $currentStageColor = 'secondary';
                $currentStageName = 'مسودة';
                $stageNames = [
                    'draft' => ['name' => 'مسودة', 'color' => 'secondary'],
                    'technical_support' => ['name' => 'المساندة الفنية', 'color' => 'info'],
                    'construction' => ['name' => 'الإنشاءات', 'color' => 'primary'],
                    'department_manager' => ['name' => 'مدير الدائرة', 'color' => 'warning'],
                    'administration_manager' => ['name' => 'مدير الإدارة', 'color' => 'warning'],
                    'taif_finance' => ['name' => 'مالية الطائف', 'color' => 'info'],
                    'disbursed' => ['name' => 'مصروف', 'color' => 'success']
                ];
                if (isset($stageNames[$extract['approval_stage']])) {
                    $currentStageColor = $stageNames[$extract['approval_stage']]['color'];
                    $currentStageName = $stageNames[$extract['approval_stage']]['name'];
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
                <button onclick="syncWithQoyod(<?php echo $extract['id']; ?>, 'final_for_partial')" class="btn btn-info rounded-pill px-3 shadow-sm btn-sm text-white" id="btnSyncQoyod">
                    <i class="fas fa-cloud-upload-alt me-1"></i> رفع لقيود
                </button>
            <?php else: ?>
                <span class="badge bg-success-soft text-success border rounded-pill px-3 py-2 d-flex align-items-center" style="font-size: 0.85rem;">
                    <i class="fas fa-check-circle me-1"></i> متزامن مع قيود (<?php echo htmlspecialchars($extract['qoyod_invoice_reference'] ?? 'تم'); ?>)
                </span>
            <?php endif; ?>
            <a href="export-invoice.php?id=<?php echo $extract_id; ?>" class="btn btn-success rounded-pill px-3 shadow-sm btn-sm" target="_blank">
                <i class="fas fa-file-excel me-1"></i> تصدير الفاتورة
            </a>
            <?php if ($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null): ?>
                <a href="edit.php?id=<?php echo $extract_id; ?>" class="btn btn-warning rounded-pill px-3 shadow-sm btn-sm text-dark">
                    <i class="fas fa-edit me-1"></i> تعديل
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-white border rounded-pill px-3 shadow-sm btn-sm text-secondary">
                <i class="fas fa-arrow-left me-1"></i> العودة
            </a>
        </div>
    </div>

    <!-- Financial Summary (Horizontal) -->
    <div class="card dash-card bg-primary-soft mb-4 border-0">
        <div class="card-body py-3 d-flex flex-column justify-content-center">
            <div class="row text-center g-2 align-items-center">
                <div class="col px-1">
                    <small class="text-muted fw-bold d-block mb-1">الإجمالي</small>
                    <h5 class="text-dark fw-bold mb-0 fs-6"><?php echo number_format($extract['total_amount'], 2); ?></h5>
                </div>
                <div class="col-auto text-muted small"><i class="fas fa-plus"></i></div>
                <div class="col px-1 border-start border-primary border-opacity-25">
                    <small class="text-muted fw-bold d-block mb-1">الضريبة</small>
                    <h5 class="text-dark fw-bold mb-0 fs-6"><?php echo number_format($extract['tax_amount'] ?? ($extract['total_amount'] * ($extract['tax_rate']/100)), 2); ?></h5>
                </div>
                <div class="col-auto text-muted small"><i class="fas fa-plus"></i></div>
                <div class="col px-1 border-start border-primary border-opacity-25">
                    <small class="text-muted fw-bold d-block mb-1">ضريبة الجزئي</small>
                    <h5 class="text-dark fw-bold mb-0 fs-6"><?php echo number_format($extract['partial_extract_tax_amount'] ?? 0, 2); ?></h5>
                </div>
                <div class="col-auto text-muted small"><i class="fas fa-minus"></i></div>
                <div class="col px-1 border-start border-primary border-opacity-25">
                    <small class="text-muted fw-bold d-block mb-1">إجمالي الغرامات</small>
                    <h5 class="text-danger fw-bold mb-0 fs-6"><?php echo number_format($extract['total_penalty_amount'], 2); ?></h5>
                </div>
            </div>
            <div class="row mt-3 text-center">
                <div class="col-12 px-2 border-top border-primary border-opacity-25 pt-2">
                    <small class="text-muted fw-bold d-block mb-1">الصافي النهائي</small>
                    <h4 class="text-success fw-bold mb-0"><?php echo number_format($extract['net_amount'], 2); ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
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
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-file-invoice me-1"></i>رقم PO</label>
                    <p class="mb-0">
                        <?php if (!empty($extract['po_number'])): ?>
                            <span class="badge bg-info-soft text-info border px-2 py-1"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted small">لا يوجد</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-file-alt me-1"></i>صحيفة الإدخال</label>
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
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-building me-1"></i>الفرع</label>
                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($extract['branch_name'] ?? 'غير محدد'); ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-network-wired me-1"></i>القسم</label>
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
                    <p class="mb-0 fw-bold text-success"><?php echo $extract['submission_date'] ? date('Y-m-d', strtotime($extract['submission_date'])) : 'لم يتم التقديم'; ?></p>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-link me-1"></i>المستخلص الجزئي</label>
                    <p class="mb-0">
                        <?php if ($extract['related_partial_extract_number']): ?>
                            <span class="badge bg-info-soft text-info border px-2 py-1"><?php echo htmlspecialchars($extract['related_partial_extract_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted small">غير مرتبط</span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="col-12 mt-3 pt-3 border-top">
                    <label class="form-label small fw-bold mb-1 text-muted"><i class="fas fa-align-right me-1"></i>ملاحظات المستخلص</label>
                    <p class="mb-0 text-dark"><?php echo htmlspecialchars($extract['description'] ?? 'لا توجد ملاحظات'); ?></p>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <div class="d-flex align-items-center gap-4 text-muted small bg-light p-2 rounded">
                        <div><i class="fas fa-user-edit me-1"></i> أنشئ بواسطة: <span class="fw-bold text-dark"><?php echo htmlspecialchars($extract['created_by_name'] ?? 'غير محدد'); ?></span></div>
                        <div><i class="fas fa-clock me-1"></i> تاريخ الإنشاء: <span class="fw-bold text-dark"><?php echo date('Y-m-d H:i', strtotime($extract['created_at'])); ?></span></div>
                        <div><i class="fas fa-history me-1"></i> آخر تحديث: <span class="fw-bold text-dark"><?php echo date('Y-m-d H:i', strtotime($extract['updated_at'])); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Work Orders Table -->
    <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-list text-primary opacity-75 me-2"></i>أوامر العمل المرتبطة 
                <span class="badge bg-primary-soft text-primary rounded-pill ms-2"><?php echo count($workOrders); ?></span>
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($workOrders)): ?>
                <div class="text-center text-muted py-5">
                    <div class="icon-circle bg-light mx-auto mb-3 text-muted">
                        <i class="fas fa-inbox fa-2x"></i>
                    </div>
                    <p class="mb-0 fw-bold">لا توجد أوامر عمل مرتبطة</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table premium-table table-hover table-sm align-middle mb-0" style="font-size: 0.85rem;">
                        <thead style="background-color: #f8fafc; color: #64748b;">
                            <tr>
                                <th class="ps-3 border-0">رقم الأمر</th>
                                <th class="border-0">النوع</th>
                                <th class="border-0">قيمة المستخلص</th>
                                <th class="border-0">تاريخ الإنجاز</th>
                                <th class="border-0 text-center">شهادة الإنجاز</th>
                                <th class="pe-3 border-0 text-center">التخريد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workOrders as $wo): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-dark"><?php echo htmlspecialchars($wo['work_order_number']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary border border-primary border-opacity-25 px-2 py-1"><?php echo htmlspecialchars($wo['type_code']); ?></span></td>
                                <td class="text-success fw-bold"><?php echo number_format($wo['extract_value'], 2); ?> <span class="sar-icon text-muted" style="width:12px;height:12px;"><svg><use href="#sar-symbol"/></svg></span></td>
                                <td>
                                    <?php if ($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null): ?>
                                        <input type="date" class="form-control form-control-sm completion-date-input bg-light border-0"
                                               data-work-order-id="<?php echo $wo['work_order_id']; ?>"
                                               value="<?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?>"
                                               style="min-width: 130px;">
                                    <?php else: ?>
                                        <span class="fw-bold text-dark"><?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $confirmationStatus = $wo['completion_certificate_confirmation'] ?? 'empty';
                                    switch ($confirmationStatus) {
                                        case 'confirmed': echo '<span class="badge bg-success-soft text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-check me-1"></i>مؤكد</span>'; break;
                                        case 'accepted': echo '<span class="badge bg-info-soft text-info border border-info border-opacity-25 px-2 py-1"><i class="fas fa-thumbs-up me-1"></i>مقبول</span>'; break;
                                        case 'rejected': echo '<span class="badge bg-danger-soft text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fas fa-times me-1"></i>مرفوض</span>'; break;
                                        case 'empty': default: echo '<span class="badge bg-secondary-soft text-secondary border border-secondary border-opacity-25 px-2 py-1"><i class="fas fa-minus me-1"></i>فارغ</span>'; break;
                                    }
                                    ?>
                                </td>
                                <td class="pe-3 text-center">
                                    <?php
                                    $demolitionStatus = $wo['demolition_status'] ?? 'not_applicable';
                                    switch ($demolitionStatus) {
                                        case 'attached': echo '<span class="badge bg-success-soft text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-paperclip me-1"></i>مرفق</span>'; break;
                                        case 'not_applicable': echo '<span class="badge bg-success-soft text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-ban me-1"></i>لا ينطبق</span>'; break;
                                        case 'not_attached': default: echo '<span class="badge bg-warning-soft text-warning border border-warning border-opacity-25 px-2 py-1"><i class="fas fa-exclamation-triangle me-1"></i>غير مرفق</span>'; break;
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null) && !empty($workOrders)): ?>
                        <div class="p-3 text-end bg-light border-top">
                            <small class="text-muted me-3"><i class="fas fa-info-circle me-1"></i>سيتم تحديث تواريخ الاستلام في أوامر العمل أيضاً</small>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" id="saveCompletionDates">
                                <i class="fas fa-save me-1"></i>حفظ تواريخ الإنجاز
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- إدارة الاعتماد -->
    <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-tasks text-warning opacity-75 me-2"></i>إدارة الاعتماد
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table premium-table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead style="background-color: #f8fafc; color: #64748b;">
                        <tr>
                            <th class="ps-4 border-0" width="25%">المرحلة</th>
                            <th class="border-0" width="20%">تاريخ الصرف</th>
                            <th class="border-0" width="30%">الملاحظات</th>
                            <th class="pe-4 border-0 text-end" width="25%">تفاصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="ps-4">
                                <select class="form-select form-select-sm approval-stage-select bg-light border-0 fw-bold"
                                        data-extract-id="<?php echo $extract_id; ?>"
                                        onchange="updateExtractField(<?php echo $extract_id; ?>, 'approval_stage', this.value, this)">
                                    <?php foreach ($approvalStages as $stage): ?>
                                        <?php
                                        $isSelected = false;
                                        if ($extract['approval_stage'] === null && $stage['key'] === 'draft') {
                                            $isSelected = true; // للافتراضي مسودة
                                        } elseif ($extract['approval_stage'] === $stage['key']) {
                                            $isSelected = true;
                                        }
                                        ?>
                                        <option value="<?= htmlspecialchars($stage['key']) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($stage['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="date" class="form-control form-control-sm disbursement-date-input bg-light border-0"
                                       data-extract-id="<?php echo $extract_id; ?>"
                                       value="<?php echo $extract['disbursement_date'] ?? ''; ?>"
                                       onchange="updateExtractField(<?php echo $extract_id; ?>, 'disbursement_date', this.value, this)">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm approval-notes-input bg-light border-0"
                                       data-extract-id="<?php echo $extract_id; ?>"
                                       value="<?php echo htmlspecialchars($extract['approval_notes'] ?? ''); ?>"
                                       placeholder="أدخل ملاحظات..."
                                       onchange="updateExtractField(<?php echo $extract_id; ?>, 'approval_notes', this.value, this)">
                            </td>
                            <td class="pe-4 text-end">
                                <div class="small">
                                    <div class="mb-1">
                                        <span class="text-muted">المعتمد:</span>
                                        <span id="approved_by_display" class="fw-bold text-dark ms-1">
                                            <?php echo htmlspecialchars($approvals['approved_by_name'] ?? '---'); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-muted">التاريخ:</span>
                                        <span id="approval_date_display" class="fw-bold text-dark ms-1">
                                            <?php echo !empty($approvals['approval_date']) ? date('Y-m-d H:i', strtotime($approvals['approval_date'])) : '---'; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 bg-light border-top">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold small text-muted">تقدم المرحلة</span>
                    <span id="current_stage_badge" class="badge bg-warning-soft text-warning border px-2 py-1">
                        <?php
                        $stageNames = ['draft'=>'مسودة','technical_support'=>'المساندة الفنية','construction'=>'الإنشاءات','department_manager'=>'مدير الدائرة','administration_manager'=>'مدير الإدارة','taif_finance'=>'مالية الطائف','disbursed'=>'مصروف'];
                        echo $stageNames[$extract['approval_stage'] ?? 'draft'] ?? 'غير محدد';
                        ?>
                    </span>
                </div>
                <div class="progress" style="height: 6px; border-radius: 10px;">
                    <?php
                    $stageKeys = ['draft', 'technical_support', 'construction', 'department_manager', 'administration_manager', 'taif_finance', 'disbursed'];
                    $currentStageIndex = array_search($extract['approval_stage'] ?? 'draft', $stageKeys);
                    $progressPercentage = $currentStageIndex !== false ? round((($currentStageIndex + 1) / count($stageKeys)) * 100) : 0;
                    ?>
                    <div id="approval_progress_bar" class="progress-bar bg-success" role="progressbar"
                         style="width: <?php echo $progressPercentage; ?>%"
                         aria-valuenow="<?php echo $progressPercentage; ?>"
                         aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attachments -->
    <?php if (!empty($attachments)): ?>
    <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-radius: 20px 20px 0 0;">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-paperclip text-info opacity-75 me-2"></i>المرفقات 
                <span class="badge bg-info-soft text-info rounded-pill ms-2"><?php echo count($attachments); ?></span>
            </h6>
        </div>
        <div class="card-body pt-0">
            <div class="row g-3">
                <?php foreach ($attachments as $attachment): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex align-items-center p-3 border rounded bg-light hover-shadow" style="transition: all 0.2s;">
                        <div class="me-3">
                            <i class="fas fa-file fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark">
                                <?php echo htmlspecialchars($attachment['file_name'] ?? 'مرفق'); ?>
                            </div>
                            <div class="text-muted small">
                                <?php echo isset($attachment['uploaded_at']) ? date('Y-m-d H:i', strtotime($attachment['uploaded_at'])) : 'تاريخ غير محدد'; ?>
                            </div>
                            <div class="text-muted small">
                                بواسطة: <?php echo htmlspecialchars($attachment['uploaded_by_name'] ?? 'غير محدد'); ?>
                            </div>
                        </div>
                        <div>
                            <a href="download-attachment.php?id=<?php echo $attachment['id']; ?>"
                               class="btn btn-sm btn-outline-info" target="_blank">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// حفظ المحتوى
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
$(document).ready(function() {
    console.log('صفحة عرض المستخلص النهائي للجزئي جاهزة');
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
        url: 'update-approval-stage-ajax.php',
        type: 'POST',
        data: {
            extract_id: extractId,
            approval_stage: field === 'approval_stage' ? value : $('.approval-stage-select').val(),
            disbursement_date: field === 'disbursement_date' ? value : $('.disbursement-date-input').val(),
            approval_notes: field === 'approval_notes' ? value : $('.approval-notes-input').val()
        },
        dataType: 'json',
        success: function(response) {
            console.log('Success response:', response);

            if (response.success) {
                // إظهار نجاح التحديث
                $(element).removeClass('updating-field').addClass('field-success');

                // تحديث العرض
                updateExtractDisplay(response.data);

                // إظهار رسالة نجاح
                showToast('success', response.message || 'تم التحديث بنجاح');

                // إزالة تأثير النجاح بعد ثانيتين
                setTimeout(function() {
                    $(element).removeClass('field-success');
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
    // أسماء المراحل
    const stageNames = <?php echo json_encode(array_combine(array_column($approvalStages, 'key'), array_column($approvalStages, 'name'))); ?>;

    // ألوان المراحل
    const stageColors = <?php echo json_encode(array_combine(array_column($approvalStages, 'key'), array_column($approvalStages, 'color'))); ?>;

    // تحديث شارة المرحلة الحالية
    const currentStageBadge = $('#current_stage_badge');
    currentStageBadge.removeClass().addClass('badge bg-' + stageColors[data.approval_stage]);
    currentStageBadge.text(stageNames[data.approval_stage]);

    // تحديث المعتمد
    $('#approved_by_display').text(data.approved_by_name || 'لم يتم الاعتماد بعد');

    // تحديث تاريخ الاعتماد
    $('#approval_date_display').text(data.approval_date || 'لم يتم الاعتماد بعد');

    // تحديث شريط التقدم
    const stageKeys = <?php echo json_encode(array_column($approvalStages, 'key')); ?>;
    const currentStageIndex = stageKeys.indexOf(data.approval_stage || 'draft');
    const progressPercentage = currentStageIndex !== -1 ? Math.round(((currentStageIndex + 1) / stageKeys.length) * 100) : 0;

    $('#approval_progress_bar').css('width', progressPercentage + '%').attr('aria-valuenow', progressPercentage);
    $('#progress_text').text(progressPercentage + '% مكتمل (' + (currentStageIndex + 1) + ' من ' + stageKeys.length + ' مراحل)');
}

// دالة إظهار الرسائل المنبثقة
function showToast(type, message) {
    const toastId = 'toast-' + Date.now();
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';

    const toast = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert"
             style="position: fixed; top: 20px; left: 20px; z-index: 9999; min-width: 300px;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${iconClass} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    $('body').append(toast);

    const toastElement = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: type === 'success' ? 3000 : 5000
    });

    toastElement.show();

    // إزالة العنصر بعد الإخفاء
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
        $(this).remove();
    });
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
