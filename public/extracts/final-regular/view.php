<?php
/**
 * صفحة عرض تفاصيل المستخلص النهائي العادي
 * Final Regular Extract View Page
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

// جلب تفاصيل المستخلص النهائي العادي
$extract = null;
$workOrders = [];

try {
    $extractQuery = "
        SELECT fre.*,
               b.name as branch_name,
               b.code as branch_code,
               u.full_name as created_by_name,
               (SELECT con.contract_number FROM work_orders wo2 
                JOIN contracts con ON wo2.contract_id = con.id 
                JOIN final_regular_extract_work_orders frewo2 ON wo2.id = frewo2.work_order_id 
                WHERE frewo2.final_regular_extract_id = fre.id LIMIT 1) as contract_number
        FROM final_regular_extracts fre
        LEFT JOIN branches b ON fre.branch_id = b.id
        LEFT JOIN users u ON fre.created_by = u.id
        WHERE fre.id = ?
    ";

    $stmt = $db->prepare($extractQuery);
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();

    if (!$extract) {
        header('Location: index.php');
        exit();
    }

    // جلب أوامر العمل المرتبطة بالمستخلص مع تفاصيل التخريد
    $workOrdersQuery = "
        SELECT frewo.*,
               wo.work_order_number,
               wo.estimated_value,
               wo.actual_value,
               wo.assignment_date,
               wo.receipt_date,
               wo.department,
               wot.type_code,
               wot.description as work_order_type_name,
               ce.name as current_entity_name,
               -- حالة التخريد
               COALESCE(df.status, 'not_attached') as demolition_status,
               df.file_path as demolition_file_path,
               df.uploaded_at as demolition_uploaded_at
        FROM final_regular_extract_work_orders frewo
        INNER JOIN work_orders wo ON frewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
        LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
        WHERE frewo.final_regular_extract_id = ?
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
            fre.approval_stage,
            DATE(fre.disbursed_date) as disbursement_date,
            fre.disbursed_by as approved_by,
            fre.disbursed_date as approval_date,
            fre.disbursed_notes as approval_notes,
            u.full_name as approved_by_name
        FROM final_regular_extracts fre
        LEFT JOIN users u ON fre.disbursed_by = u.id
        WHERE fre.id = ?
    ";

    $stmt = $db->prepare($approvalsQuery);
    $stmt->execute([$extract_id]);
    $approvals = $stmt->fetch();

} catch (Exception $e) {
    echo "خطأ في جلب البيانات: " . $e->getMessage();
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
            'color' => $stage['stage_color']
        ];
    }

} catch (Exception $e) {
    // في حالة عدم وجود جدول approval_stages، استخدم القيم الافتراضية
    $stageNames = [
        null => "مسودة",
        "technical_support" => "الدعم الفني",
        "construction" => "الإنشاءات",
        "department_manager" => "مدير القسم",
        "administration_manager" => "مدير الإدارة",
        "taif_finance" => "مالية الطائف",
        "disbursed" => "تم الصرف"
    ];

    $approvalStages = [
        ['key' => null, 'name' => 'مسودة', 'icon' => 'fas fa-edit', 'color' => 'secondary'],
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

// حساب إحصائيات التخريد
$totalWorkOrders = count($workOrders);
$completedDemolition = 0;
$pendingDemolition = 0;

foreach ($workOrders as $wo) {
    if ($wo['demolition_status'] === 'attached' || $wo['demolition_status'] === 'not_applicable') {
        $completedDemolition++;
    } else {
        $pendingDemolition++;
    }
}

$pageTitle = 'تفاصيل المستخلص النهائي العادي - ' . $extract['extract_number'];
$currentPage = 'extracts';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية العادية', 'url' => 'extracts/final-regular/index.php'],
    ['title' => 'تفاصيل المستخلص', 'url' => '']
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
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center mb-1">
                <h5 class="fw-bold text-dark mb-0 me-3">
                    <i class="fas fa-file-invoice text-primary me-2"></i>عرض المستخلص النهائي العادي
                </h5>
                <?php
                // تحديد حالة المستخلص
                $currentStageColor = 'secondary';
                $currentStageName = 'مسودة';
                foreach ($approvalStages as $stage) {
                    if ($extract['approval_stage'] === $stage['key']) {
                        $currentStageColor = $stage['color'];
                        $currentStageName = $stage['name'];
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
                <button onclick="syncWithQoyod(<?php echo $extract['id']; ?>, 'final_regular')" class="btn btn-info rounded-pill px-3 shadow-sm btn-sm text-white" id="btnSyncQoyod">
                    <i class="fas fa-cloud-upload-alt me-1"></i> رفع لقيود
                </button>
            <?php else: ?>
                <span class="badge bg-success-soft text-success border rounded-pill px-3 py-2 d-flex align-items-center" style="font-size: 0.85rem;">
                    <i class="fas fa-check-circle me-1"></i> متزامن مع قيود (<?php echo htmlspecialchars($extract['qoyod_invoice_reference'] ?? 'تم'); ?>)
                </span>
            <?php endif; ?>
            <a href="export-invoice.php?id=<?php echo $extract_id; ?>" class="btn btn-success rounded-pill px-3 shadow-sm btn-sm" download>
                <i class="fas fa-file-excel me-1"></i> تصدير الفاتورة
            </a>
            <?php if ($extract['approval_stage'] === null): ?>
            <a href="create.php?id=<?php echo $extract['id']; ?>" class="btn btn-warning rounded-pill px-3 shadow-sm btn-sm">
                <i class="fas fa-edit me-1"></i> تعديل
            </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-light rounded-pill px-3 shadow-sm text-secondary fw-bold border-0 btn-sm">
                <i class="fas fa-arrow-right me-2"></i>العودة
            </a>
        </div>
    </div>

    <!-- Financial Summary (Horizontal) -->
    <div class="card dash-card bg-primary-soft mb-4 border-0">
        <div class="card-body py-3 d-flex flex-column justify-content-center">
            <div class="row text-center g-2 align-items-center">
                <div class="col-3 px-3">
                    <small class="text-muted fw-bold d-block mb-1">المبلغ الإجمالي</small>
                    <h4 class="text-dark fw-bold mb-0"><?php echo number_format($extract['total_amount'], 2); ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                </div>
                <div class="col-3 border-start border-primary border-opacity-25 px-3">
                    <small class="text-muted fw-bold d-block mb-1">مبلغ الضريبة (<?php echo number_format($extract['tax_rate'], 0); ?>%)</small>
                    <h4 class="text-dark fw-bold mb-0"><?php echo number_format($extract['tax_amount'], 2); ?> <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span></h4>
                </div>
                <div class="col-3 border-start border-end border-danger border-opacity-25 px-3">
                    <small class="text-muted fw-bold d-block mb-1 text-danger">إجمالي الغرامات</small>
                    <h4 class="text-danger fw-bold mb-0"><?php echo number_format($extract['total_penalty_amount'], 2); ?> <span class="sar-icon-lg text-danger opacity-75"><svg><use href="#sar-symbol"/></svg></span></h4>
                </div>
                <div class="col-3 px-3">
                    <small class="text-muted fw-bold d-block mb-1">الصافي (بدون ضريبة)</small>
                    <h3 class="text-success fw-bold mb-0"><?php echo number_format($extract['net_amount'], 2); ?> <span class="sar-icon-lg text-success opacity-75"><svg><use href="#sar-symbol"/></svg></span></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Extract Details Card -->
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



    <!-- Work Orders Table -->
    <div class="card dash-card mb-4 border-0 shadow-sm bg-white">
        <div class="card-header bg-white border-0 py-3" style="border-radius: 20px 20px 0 0;">
            <h6 class="card-title mb-0 fw-bold text-dark">
                <i class="fas fa-list text-info opacity-75 me-2"></i>أوامر العمل المرتبطة <span class="badge bg-secondary-soft text-secondary rounded-pill ms-1"><?php echo count($workOrders); ?></span>
            </h6>
        </div>
        <div class="card-body py-0 pb-3">
            <?php if (count($workOrders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover premium-table align-middle mb-0" id="workOrdersTable">
                    <thead>
                        <tr class="table-light text-muted small">
                            <th class="border-0 rounded-start ps-3">رقم الأمر</th>
                            <th class="border-0 text-center">النوع</th>
                            <th class="border-0 text-center">الجهة الحالية</th>
                            <th class="border-0 text-center">القسم</th>
                            <th class="border-0">تاريخ الإنجاز</th>
                            <th class="border-0">قيمة المستخلص</th>
                            <th class="border-0">الغرامة</th>
                            <th class="border-0 text-center rounded-end pe-3">التخريد</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php foreach ($workOrders as $wo): ?>
                        <tr style="vertical-align: middle;">
                            <td class="fw-bold ps-3">
                                <?php echo htmlspecialchars($wo['work_order_number']); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-soft text-primary border rounded-pill" style="font-size: 0.7rem;">
                                    <?php echo htmlspecialchars($wo['type_code'] ?? 'غير محدد'); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($wo['current_entity_name'] ?? 'غير محدد'); ?></span>
                            </td>
                            <td class="text-center">
                                <?php
                                switch($wo['department']) {
                                    case 'connections':
                                        echo '<span class="badge bg-info-soft text-info border">التوصيلات</span>';
                                        break;
                                    case 'projects':
                                        echo '<span class="badge bg-warning-soft text-warning border">المشاريع</span>';
                                        break;
                                    default:
                                        echo '<span class="badge bg-secondary-soft text-secondary border">' . htmlspecialchars($wo['department']) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($extract['approval_stage'] === null): ?>
                                    <!-- قابل للتحرير في حالة المسودة -->
                                    <input type="date" class="form-control form-control-sm completion-date-input rounded-3 shadow-none border-secondary text-dark px-2"
                                           data-work-order-id="<?php echo $wo['work_order_id']; ?>"
                                           value="<?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?>"
                                           onchange="updateCompletionDate(<?php echo $wo['work_order_id']; ?>, this.value)" style="min-width: 140px; font-size: 0.85rem;">
                                <?php else: ?>
                                    <!-- للقراءة فقط بعد التقديم -->
                                    <span class="text-muted fw-bold"><?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-primary">
                                <?php echo number_format($wo['extract_value'], 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td>
                                <?php if ($wo['penalty_amount'] > 0): ?>
                                    <span class="fw-bold text-danger"><?php echo number_format($wo['penalty_amount'], 2); ?> <span class="sar-icon text-muted"><svg><use href="#sar-symbol"/></svg></span></span>
                                <?php else: ?>
                                    <span class="text-muted small">لا يوجد</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3">
                                <?php
                                switch($wo['demolition_status']) {
                                    case 'attached':
                                        echo '<span class="badge bg-success"><i class="fas fa-check"></i> مرفق</span>';
                                        if ($wo['demolition_file_path']) {
                                            echo '<br><small class="text-muted">تم الرفع: ' . date('Y-m-d', strtotime($wo['demolition_uploaded_at'])) . '</small>';
                                        }
                                        break;
                                    case 'not_applicable':
                                        echo '<span class="badge bg-secondary"><i class="fas fa-minus"></i> غير قابل للتطبيق</span>';
                                        break;
                                    case 'not_attached':
                                    default:
                                        echo '<span class="badge bg-danger"><i class="fas fa-times"></i> غير مرفق</span>';
                                        break;
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">لا توجد أوامر عمل مرتبطة</h5>
                <p class="text-muted">لم يتم ربط أي أوامر عمل بهذا المستخلص بعد.</p>
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
                                       value="<?php echo $approvals['disbursement_date'] ?? ''; ?>"
                                       onchange="updateExtractField(<?php echo $extract_id; ?>, 'disbursement_date', this.value, this)">
                            </td>
                            <td class="pt-3">
                                <input type="text" class="form-control form-control-sm approval-notes-input rounded-3 shadow-none text-secondary px-2"
                                       data-extract-id="<?php echo $extract_id; ?>"
                                       value="<?php echo htmlspecialchars($approvals['approval_notes'] ?? ''); ?>"
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
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<script>
// إعداد بيانات مراحل الاعتماد
const approvalStagesData = <?php echo json_encode($approvalStagesFromDB); ?>;
const stageNames = {};
const stageColors = {};

approvalStagesData.forEach(stage => {
    stageNames[stage.stage_key] = stage.stage_name;
    stageColors[stage.stage_key] = stage.stage_color;
});

$(document).ready(function() {
    // Initialize DataTable for work orders
    if ($('#workOrdersTable').length && !$.fn.DataTable.isDataTable('#workOrdersTable')) {
        $('#workOrdersTable').DataTable({
            "language": {
                "sProcessing": "جارٍ التحميل...",
                "sLengthMenu": "أظهر _MENU_ مدخلات",
                "sZeroRecords": "لم يعثر على أية سجلات",
                "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
                "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
                "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
                "sInfoPostFix": "",
                "sSearch": "ابحث:",
                "sUrl": "",
                "oPaginate": {
                    "sFirst": "الأول",
                    "sPrevious": "السابق",
                    "sNext": "التالي",
                    "sLast": "الأخير"
                }
            },
            "responsive": true,
            "order": [[ 0, "asc" ]],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": -1 }
            ]
        });
    }

    // حفظ القيمة الأصلية عند تحميل الصفحة
    $('.approval-stage-select').each(function() {
        $(this).attr('data-original-value', $(this).val());
    });
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

                // إظهار رسالة نجاح
                Swal.fire({
                    icon: 'success',
                    title: 'تم التحديث بنجاح',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });

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

                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: response.message || 'حدث خطأ في التحديث'
                });

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

            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: errorMessage
            });

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

/**
 * تحديث تاريخ الإنجاز لأمر العمل
 */
function updateCompletionDate(workOrderId, newDate) {
    if (!newDate) {
        Swal.fire({
            icon: 'warning',
            title: 'تاريخ غير صحيح',
            text: 'يرجى اختيار تاريخ صحيح'
        });
        return;
    }

    // تأكيد التحديث
    Swal.fire({
        title: 'تأكيد التحديث',
        text: `هل تريد تحديث تاريخ الإنجاز إلى ${newDate}؟`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'نعم، حدث',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            performCompletionDateUpdate(workOrderId, newDate);
        } else {
            // إعادة القيمة السابقة
            const input = document.querySelector(`input[data-work-order-id="${workOrderId}"]`);
            if (input) {
                input.value = input.getAttribute('data-original-value') || input.value;
            }
        }
    });
}

/**
 * تنفيذ تحديث تاريخ الإنجاز
 */
function performCompletionDateUpdate(workOrderId, newDate) {
    const input = document.querySelector(`input[data-work-order-id="${workOrderId}"]`);
    if (!input) return;

    // حفظ القيمة الأصلية
    if (!input.getAttribute('data-original-value')) {
        input.setAttribute('data-original-value', input.value);
    }

    // تعطيل الحقل أثناء التحديث
    input.disabled = true;

    // إعداد البيانات
    const updateData = {
        extract_id: <?php echo $extract_id; ?>,
        completion_dates: {
            [workOrderId]: newDate
        }
    };

    // إرسال الطلب
    fetch('update-completion-dates-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(updateData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم التحديث بنجاح',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });

            // تحديث القيمة الأصلية
            input.setAttribute('data-original-value', newDate);

            // إضافة تأثير بصري للنجاح
            input.style.borderColor = '#28a745';
            setTimeout(() => {
                input.style.borderColor = '';
            }, 2000);

        } else {
            Swal.fire({
                icon: 'error',
                title: 'خطأ في التحديث',
                text: data.message || 'حدث خطأ غير متوقع'
            });

            // إعادة القيمة السابقة
            input.value = input.getAttribute('data-original-value') || input.value;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ في الاتصال',
            text: 'تعذر الاتصال بالخادم'
        });

        // إعادة القيمة السابقة
        input.value = input.getAttribute('data-original-value') || input.value;
    })
    .finally(() => {
        input.disabled = false;
    });
}

function syncWithQoyod(extractId, extractType) {
    if (!confirm('هل أنت متأكد من رغبتك في رفع هذا المستخلص كفاتورة ضريبية في نظام قيود؟')) {
        return;
    }
    
    const btn = document.getElementById('btnSyncQoyod');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> جاري الرفع...';
    
    fetch('<?php echo path("api/qoyod/sync_extract.php"); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            extract_id: extractId,
            extract_type: extractType
        })
    })
    .then(response => response.json().then(data => ({status: response.status, body: data})))
    .then(({status, body}) => {
        if (body.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم الرفع بنجاح',
                text: body.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: body.message || 'حدث خطأ أثناء الرفع'
            });
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'خطأ في الاتصال',
            text: 'تعذر الاتصال بالخادم'
        });
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}


// تهيئة القيم الأصلية عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.completion-date-input').forEach(input => {
        input.setAttribute('data-original-value', input.value);
    });
});
</script>
