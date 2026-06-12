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
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-file-alt text-primary me-2"></i>
                عرض المستخلص الجزئي
            </h1>
            <p class="text-muted mb-0">تفاصيل المستخلص الجزئي رقم: <?php echo htmlspecialchars($extract['extract_number']); ?></p>
        </div>
        <div>
            <a href="../../preview-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-primary me-2" target="_blank">
                <i class="fas fa-eye me-1"></i>
                معاينة الفاتورة
            </a>
            <a href="export-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-success me-2" download>
                <i class="fas fa-file-download me-1"></i>
                تحميل الفاتورة
            </a>
            <a href="index.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>
                العودة للقائمة
            </a>
        </div>
    </div>

    <div class="row">
        <!-- معلومات المستخلص الأساسية -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات المستخلص الأساسية
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">رقم المستخلص:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($extract['extract_number']); ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">رقم العقد:</label>
                            <p class="mb-0">
                                <?php if (!empty($extract['contract_number'])): ?>
                                    <span class="badge bg-dark"><?php echo htmlspecialchars($extract['contract_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">رقم PO:</label>
                            <p class="mb-0">
                                <?php if (!empty($extract['po_number'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">رقم صحيفة الإدخال:</label>
                            <p class="mb-0">
                                <?php if (!empty($extract['entry_sheet_number'])): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($extract['entry_sheet_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">لا يوجد</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">رقم الفاتورة:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($extract['invoice_number'] ?? 'لا يوجد'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">الفرع:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($extract['branch_name'] ?? 'غير محدد'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">القسم:</label>
                            <p class="mb-0">
                                <?php 
                                $departments = [
                                    'connections' => 'التوصيلات',
                                    'projects' => 'المشاريع'
                                ];
                                echo $departments[$extract['department']] ?? $extract['department'];
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">تاريخ المستخلص:</label>
                            <p class="mb-0"><?php echo date('Y-m-d', strtotime($extract['extract_date'])); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">تاريخ التقديم:</label>
                            <p class="mb-0"><?php echo $extract['submission_date'] ? date('Y-m-d', strtotime($extract['submission_date'])) : 'لم يتم التقديم'; ?></p>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">ملاحظات المستخلص:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($extract['description'] ?? 'لا توجد ملاحظات'); ?></p>
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
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list me-2"></i>
                        أوامر العمل المرتبطة (<?php echo count($workOrders); ?>)
                        <?php if ($negativeValueCount > 0): ?>
                            <span class="badge bg-warning text-dark ms-2">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <?php echo $negativeValueCount; ?> قيمة سالبة
                            </span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($workOrders)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>لا توجد أوامر عمل مرتبطة بهذا المستخلص</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>رقم أمر العمل</th>
                                        <th>كود النوع</th>
                                        <th>القيمة الفعلية</th>
                                        <th>قيمة المستخلص</th>
                                        <th>تاريخ الإنجاز</th>
                                        <th>تأكيد شهادة الإنجاز</th>
                                        <th>التخريد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workOrders as $wo): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($wo['work_order_number']); ?></span>
                                            <?php if ($wo['has_negative_value']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-2"
                                                   title="قيمة سالبة: قيمة المستخلص (<?php echo number_format($wo['extract_value'], 2); ?>) أعلى من القيمة الفعلية (<?php echo number_format($wo['actual_value'], 2); ?>)"
                                                   style="cursor: help; animation: pulse-warning 2s infinite;"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($wo['type_code']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $actualValue = $wo['actual_value'] ?? $wo['estimated_value'] ?? 0;
                                            echo number_format($actualValue, 2);
                                            ?> ريال
                                            <?php if ($actualValue == 0): ?>
                                                <span class="badge bg-secondary ms-2">غير محدد</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo number_format($wo['extract_value'], 2); ?> ريال
                                            <?php if ($wo['has_negative_value']): ?>
                                                <span class="badge bg-warning text-dark ms-2">قيمة سالبة</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null): ?>
                                                <!-- حقل قابل للتعديل للمستخلصات في حالة المسودة -->
                                                <input type="date" class="form-control form-control-sm completion-date-input"
                                                       data-work-order-id="<?php echo $wo['work_order_id']; ?>"
                                                       value="<?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?>"
                                                       style="min-width: 150px;">
                                            <?php else: ?>
                                                <!-- عرض للقراءة فقط للمستخلصات المقدمة -->
                                                <span class="text-muted">
                                                    <?php echo date('Y-m-d', strtotime($wo['completion_date'])); ?>
                                                    <small class="d-block">مقفل بعد التقديم</small>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $confirmationStatus = $wo['completion_certificate_confirmation'] ?? 'empty';
                                            switch ($confirmationStatus) {
                                                case 'confirmed':
                                                    echo '<span class="badge bg-success"><i class="fas fa-check me-1"></i>مؤكد</span>';
                                                    break;
                                                case 'accepted':
                                                    echo '<span class="badge bg-info"><i class="fas fa-thumbs-up me-1"></i>مقبول</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>مرفوض</span>';
                                                    break;
                                                case 'empty':
                                                default:
                                                    echo '<span class="badge bg-secondary"><i class="fas fa-minus me-1"></i>فارغ</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $demolitionStatus = $wo['demolition_status'] ?? 'not_applicable';
                                            switch ($demolitionStatus) {
                                                case 'attached':
                                                    echo '<span class="badge bg-success"><i class="fas fa-paperclip me-1"></i>مرفق</span>';
                                                    break;
                                                case 'not_applicable':
                                                    echo '<span class="badge bg-success"><i class="fas fa-ban me-1"></i>لا ينطبق</span>';
                                                    break;
                                                case 'not_attached':
                                                default:
                                                    echo '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i>غير مرفق</span>';
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
                                    <button type="button" class="btn btn-success" id="saveCompletionDates">
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
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        إدارة الاعتماد - تحديث مباشر
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th width="25%">مرحلة الاعتماد</th>
                                    <th width="20%">تاريخ الصرف</th>
                                    <th width="30%">ملاحظات الاعتماد</th>
                                    <th width="25%">معلومات الاعتماد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select class="form-select form-select-sm approval-stage-select"
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
                                    <td>
                                        <input type="date" class="form-control form-control-sm disbursement-date-input"
                                               data-extract-id="<?php echo $extract_id; ?>"
                                               value="<?php echo $extract['disbursement_date'] ?? ''; ?>"
                                               onchange="updateExtractField(<?php echo $extract_id; ?>, 'disbursement_date', this.value, this)">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm approval-notes-input"
                                               data-extract-id="<?php echo $extract_id; ?>"
                                               value="<?php echo htmlspecialchars($extract['approval_notes'] ?? ''); ?>"
                                               placeholder="أدخل ملاحظات الاعتماد..."
                                               onchange="updateExtractField(<?php echo $extract_id; ?>, 'approval_notes', this.value, this)">
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div class="mb-1">
                                                <strong>المعتمد:</strong><br>
                                                <span id="approved_by_display" class="text-primary">
                                                    <?php echo htmlspecialchars($approvals['approved_by_name'] ?? 'لم يتم الاعتماد بعد'); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <strong>تاريخ الاعتماد:</strong><br>
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
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">مرحلة الاعتماد:</span>
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
                            ?>">
                                <?php echo $currentStageName; ?>
                            </span>
                        </div>
                        <div class="progress" style="height: 8px;">
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
                            <div id="approval_progress_bar" class="progress-bar bg-success" role="progressbar"
                                 style="width: <?php echo $progressPercentage; ?>%"
                                 aria-valuenow="<?php echo $progressPercentage; ?>"
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        <div class="small text-muted mt-1" id="progress_text">
                            <?php echo $progressPercentage; ?>% مكتمل (<?php echo $currentStageOrder; ?> من <?php echo $totalStages; ?> مراحل)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الملخص المالي والمعلومات -->
        <div class="col-lg-4">
            <!-- الملخص المالي -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calculator me-2"></i>
                        الملخص المالي
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">المبلغ الإجمالي:</label>
                        <p class="mb-0 h5 text-primary"><?php echo number_format($extract['total_amount'], 2); ?> ريال</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">معدل الضريبة:</label>
                        <p class="mb-0"><?php echo number_format($extract['tax_rate'], 2); ?>%</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">مبلغ الضريبة:</label>
                        <p class="mb-0"><?php echo number_format($extract['tax_amount'], 2); ?> ريال</p>
                    </div>
                    <hr>
                    <div class="mb-0">
                        <label class="form-label fw-bold">الصافي (بدون ضريبة):</label>
                        <p class="mb-0 h4 text-success"><?php echo number_format($extract['net_amount'], 2); ?> ريال</p>
                    </div>
                </div>
            </div>

            <!-- معلومات المستخلص -->
            <div class="card shadow mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        معلومات المستخلص
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">تاريخ الإنشاء:</label>
                        <p class="mb-0"><?php echo date('Y-m-d H:i', strtotime($extract['created_at'])); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">أنشئ بواسطة:</label>
                        <p class="mb-0"><?php echo htmlspecialchars($extract['created_by_name'] ?? 'غير محدد'); ?></p>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">آخر تحديث:</label>
                        <p class="mb-0"><?php echo date('Y-m-d H:i', strtotime($extract['updated_at'])); ?></p>
                    </div>
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

// دالة إظهار الرسائل المنبثقة باستخدام SweetAlert2
function showToast(type, message) {
    const iconType = type === 'success' ? 'success' : 'error';
    const title = type === 'success' ? 'نجح!' : 'خطأ!';

    Swal.fire({
        icon: iconType,
        title: title,
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
}

// دالة قديمة للتوافق (إذا كانت مستخدمة في أماكن أخرى)
function showToastOld(type, message) {
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
