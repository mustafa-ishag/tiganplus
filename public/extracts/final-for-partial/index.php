<?php
/**
 * صفحة فهرس المستخلصات النهائية للجزئية
 * Final For Partial Extracts Index Page
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'المستخلصات النهائية للجزئية';
$currentPage = 'extracts-final-for-partial';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية للجزئية', 'url' => 'extracts/final-for-partial/index.php']
];

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_view')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// جلب المستخلصات النهائية للجزئية مع بيانات الفروع والمستخدمين والمستخلصات الجزئية المرتبطة
$extractsQuery = "
    SELECT ffpe.*,
           b.name as branch_name,
           u.full_name as created_by_name,
           pe.extract_number as related_partial_extract_number,
           ffpe.department as department_name,
           COUNT(DISTINCT ffpewo.id) as work_orders_count,
           -- إحصائيات شهادات الإنجاز
           COUNT(DISTINCT CASE WHEN cc.completion_certificate_confirmation = 'confirmed' THEN ffpewo.id END) as confirmed_certificates,
           -- إحصائيات التخريد
           COUNT(DISTINCT CASE WHEN (df.status = 'not_applicable' OR df.status = 'attached') THEN ffpewo.id END) as completed_demolition,
           COUNT(DISTINCT CASE WHEN df.status = 'not_attached' THEN ffpewo.id END) as pending_demolition,
           -- رقم العقد (من أول أمر عمل)
           (SELECT con.contract_number FROM work_orders wo2 
            JOIN contracts con ON wo2.contract_id = con.id 
            JOIN final_for_partial_extract_work_orders ffpewo2 ON wo2.id = ffpewo2.work_order_id 
            WHERE ffpewo2.final_for_partial_extract_id = ffpe.id LIMIT 1) as contract_number
    FROM final_for_partial_extracts ffpe
    LEFT JOIN branches b ON ffpe.branch_id = b.id
    LEFT JOIN users u ON ffpe.created_by = u.id
    LEFT JOIN partial_extracts pe ON ffpe.related_partial_extract_id = pe.id
    LEFT JOIN final_for_partial_extract_work_orders ffpewo ON ffpe.id = ffpewo.final_for_partial_extract_id
    LEFT JOIN work_orders wo ON ffpewo.work_order_id = wo.id
    -- شهادة الإنجاز
    LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
    -- نموذج التخريد
    LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
    GROUP BY ffpe.id
    ORDER BY ffpe.created_at DESC
";
$extracts = $db->query($extractsQuery)->fetchAll();

// جلب الفروع للفلترة
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();

// جلب مراحل الاعتماد من قاعدة البيانات أولاً لبناء الاستعلام ديناميكياً
try {
    $approvalStagesFromDB = $db->query("
        SELECT stage_key, stage_name, stage_color, stage_order, is_active
        FROM approval_stages
        WHERE is_active = 1
        ORDER BY stage_order
    ")->fetchAll();

    $dynamicApprovalStages = [];
    $approvalStages = [];
    foreach ($approvalStagesFromDB as $stage) {
        $dynamicApprovalStages[] = $stage['stage_key'];
        $approvalStages[] = [
            'key' => $stage['stage_key'],
            'name' => $stage['stage_name'],
            'color' => $stage['stage_color']
        ];
    }
} catch (Exception $e) {
    // في حالة عدم وجود جدول approval_stages، استخدم القيم الافتراضية
    $dynamicApprovalStages = ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'disbursed'];
    $approvalStages = [
        ['key' => 'draft', 'name' => 'مسودة', 'color' => 'secondary'],
        ['key' => 'submitted', 'name' => 'مقدمة', 'color' => 'info'],
        ['key' => 'under_review', 'name' => 'قيد المراجعة', 'color' => 'warning'],
        ['key' => 'approved', 'name' => 'معتمدة', 'color' => 'success'],
        ['key' => 'rejected', 'name' => 'مرفوضة', 'color' => 'danger'],
        ['key' => 'disbursed', 'name' => 'مصروفة', 'color' => 'primary']
    ];
}

// بناء الاستعلام ديناميكياً للإحصائيات
$statsQuery = "SELECT COUNT(*) as total, SUM(net_amount) as net_amount";

// إضافة عدادات لكل مرحلة اعتماد مع المبالغ
if (!empty($dynamicApprovalStages)) {
    foreach ($dynamicApprovalStages as $stage) {
        $statsQuery .= ", SUM(CASE WHEN approval_stage = '$stage' THEN 1 ELSE 0 END) as $stage";
        $statsQuery .= ", SUM(CASE WHEN approval_stage = '$stage' THEN net_amount ELSE 0 END) as {$stage}_net_amount";
    }
}

$statsQuery .= ", SUM(total_amount) as total_amount,
        SUM(total_penalty_amount) as total_penalty_amount,
        COUNT(CASE WHEN related_partial_extract_id IS NOT NULL THEN 1 END) as linked_to_partial
    FROM final_for_partial_extracts";

$stats = $db->query($statsQuery)->fetch();

// معلومات الأقسام (نفس الطريقة المستخدمة في المستخلصات الجزئية)
$departments = [
    [
        'key' => 'connections',
        'name' => 'التوصيلات',
        'color' => 'info',
        'icon' => 'fas fa-plug'
    ],
    [
        'key' => 'projects',
        'name' => 'المشاريع',
        'color' => 'warning',
        'icon' => 'fas fa-project-diagram'
    ]
];

// جلب إحصائيات الأقسام من قاعدة البيانات
$departmentStatsQuery = "
    SELECT
        SUM(CASE WHEN department = 'connections' THEN 1 ELSE 0 END) as connections_count,
        SUM(CASE WHEN department = 'projects' THEN 1 ELSE 0 END) as projects_count,
        COALESCE(SUM(CASE WHEN department = 'connections' THEN net_amount ELSE 0 END), 0) as connections_net_amount,
        COALESCE(SUM(CASE WHEN department = 'projects' THEN net_amount ELSE 0 END), 0) as projects_net_amount
    FROM final_for_partial_extracts
";
$departmentStatsData = $db->query($departmentStatsQuery)->fetch();

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

<style>
/* Modern Dash Card Style */
.dash-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    background-color: #ffffff;
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
</style>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-0 text-warning">
                <i class="fas fa-file-contract text-warning me-2"></i>
                المستخلصات النهائية للجزئية
            </h1>
            <p class="text-muted mb-0">إدارة المستخلصات النهائية للجزئية (FFPE-YYYY-XXX) - مرتبطة بالمستخلصات الجزئية</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-start justify-content-lg-end">
            <a href="../index.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-secondary fw-bold text-nowrap">
                <i class="fas fa-arrow-right me-1"></i>
                <span>العودة</span>
            </a>
            <a href="export.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-success fw-bold text-nowrap">
                <i class="fas fa-file-excel me-1"></i>
                <span>تصدير</span>
            </a>
            <a href="import.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-info fw-bold text-nowrap">
                <i class="fas fa-upload me-1"></i>
                <span>استيراد</span>
            </a>
            <a href="update-sap-entry-number.php" class="btn btn-light rounded-pill px-3 shadow-sm border-0 text-warning fw-bold text-nowrap">
                <i class="fas fa-sync-alt me-1"></i>
                <span>تحديث SAP</span>
            </a>
            <a href="create.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold text-nowrap">
                <i class="fas fa-plus me-1"></i>
                <span>مستخلص نهائي جديد</span>
            </a>
        </div>
    </div>

    <!-- Statistics Cards - Dynamic -->
    <div class="row mb-4 flex-nowrap overflow-auto stats-row" id="statisticsCards">
        <!-- إجمالي المستخلصات -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="dash-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">إجمالي المستخلصات</div>
                        <div class="h4 mb-0 fw-bold text-dark" id="stat-total"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="icon-circle bg-success-soft">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <div class="mt-auto border-top pt-2">
                    <div class="text-muted" style="font-size: 0.8rem;">
                        <i class="fas fa-coins me-1 text-success"></i> صافي: 
                        <span class="fw-bold text-dark" id="stat-total-net"><?php echo number_format($stats['net_amount'] ?? 0, 0); ?></span>
                        <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- بطاقات مراحل الاعتماد الديناميكية -->
        <?php foreach ($approvalStages as $stage): ?>
            <?php
            $stageKey = $stage['key'] === null ? 'draft' : $stage['key'];
            $count = $stats[$stageKey] ?? 0;
            $netAmount = $stats[$stageKey . '_net_amount'] ?? 0;

            // إخفاء البطاقات التي لا تحتوي على مستخلصات
            if ($count == 0) continue;

            // تحديد الأيقونة
            $icons = [
                'draft' => 'fas fa-edit',
                'submitted' => 'fas fa-paper-plane',
                'under_review' => 'fas fa-clock',
                'approved' => 'fas fa-check',
                'rejected' => 'fas fa-times',
                'disbursed' => 'fas fa-money-bill-wave',
                'technical_support' => 'fas fa-tools',
                'construction' => 'fas fa-hard-hat',
                'department_manager' => 'fas fa-user-tie',
                'administration_manager' => 'fas fa-user-cog',
                'taif_finance' => 'fas fa-calculator'
            ];
            $icon = $icons[$stageKey] ?? 'fas fa-file';
            ?>
            <?php
            $colorClass = $stage['color'];
            if ($colorClass == 'primary') $softBg = 'bg-primary-soft';
            elseif ($colorClass == 'success') $softBg = 'bg-success-soft';
            elseif ($colorClass == 'warning') $softBg = 'bg-warning-soft';
            elseif ($colorClass == 'info') $softBg = 'bg-info-soft';
            elseif ($colorClass == 'danger') $softBg = 'bg-danger-soft';
            else $softBg = 'bg-secondary-soft';
            ?>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3" id="card-<?= $stageKey ?>">
                <div class="dash-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;"><?php echo htmlspecialchars($stage['name']); ?></div>
                            <div class="h4 mb-0 fw-bold text-dark" id="stat-<?= $stageKey ?>"><?php echo $count; ?></div>
                        </div>
                        <div class="icon-circle <?php echo $softBg; ?>">
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                    </div>
                    <div class="mt-auto border-top pt-2">
                        <div class="text-muted" style="font-size: 0.8rem;">
                            <i class="fas fa-coins me-1 text-<?php echo $colorClass; ?>"></i> صافي: 
                            <span class="fw-bold text-dark" id="stat-<?= $stageKey ?>-net"><?php echo number_format($netAmount ?? 0, 0); ?></span>
                            <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- بطاقات الأقسام الديناميكية (نفس الطريقة المستخدمة في المستخلصات الجزئية) -->
    <div class="row mb-4 flex-nowrap overflow-auto stats-row">
        <?php foreach ($departments as $department): ?>
            <?php
            $deptKey = $department['key'];
            $count = $departmentStatsData[$deptKey . '_count'] ?? 0;
            $netAmount = $departmentStatsData[$deptKey . '_net_amount'] ?? 0;

            // إخفاء البطاقات التي لا تحتوي على مستخلصات
            if ($count == 0) continue;
            ?>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="dash-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;"><?php echo $department['name']; ?></div>
                            <div class="h4 mb-0 fw-bold text-dark"><?php echo $count; ?></div>
                        </div>
                        <div class="icon-circle bg-<?php echo $department['color']; ?>-soft">
                            <i class="<?php echo $department['icon']; ?>"></i>
                        </div>
                    </div>
                    <div class="mt-auto border-top pt-2">
                        <div class="text-muted" style="font-size: 0.8rem;">
                            <i class="fas fa-coins me-1 text-<?php echo $department['color']; ?>"></i> صافي: 
                            <span class="fw-bold text-dark"><?php echo number_format($netAmount, 0); ?></span>
                            <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Financial Summary -->
    <div class="row mb-4 flex-nowrap overflow-auto stats-row">
        <div class="col-md-4 mb-3">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-1" style="font-size: 0.75rem;">إجمالي المبالغ</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php echo number_format($stats['total_amount'] ?? 0, 2); ?>
                            <span class="sar-icon-lg text-warning"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                    <div class="icon-circle bg-warning-soft" style="width: 65px; height: 65px; font-size: 1.8rem;">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-1" style="font-size: 0.75rem;">إجمالي الغرامات</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php echo number_format($stats['total_penalty_amount'] ?? 0, 2); ?>
                            <span class="sar-icon-lg text-danger"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                    <div class="icon-circle bg-danger-soft" style="width: 65px; height: 65px; font-size: 1.8rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-1" style="font-size: 0.75rem;">صافي المبالغ</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php echo number_format($stats['net_amount'] ?? 0, 2); ?>
                            <span class="sar-icon-lg text-primary"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                    <div class="icon-circle bg-primary-soft" style="width: 65px; height: 65px; font-size: 1.8rem;">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Filters Section - Compact Design -->
    <div class="card dash-card mb-4 border-0">
        <div class="card-header py-3 border-0" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); cursor: pointer; border-radius: 20px 20px 0 0;" id="filtersHeader" title="إظهار/إخفاء الفلاتر">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-white fw-bold">
                    <i class="fas fa-filter me-2"></i>
                    الفلاتر والبحث
                </h6>
                <div class="text-white opacity-75">
                    <span id="toggleFiltersText" class="me-2 small fw-bold">إظهار</span>
                    <i class="fas fa-chevron-down" style="transition: transform 0.3s ease; transform: rotate(180deg);"></i>
                </div>
            </div>
        </div>
        <div class="card-body py-3" id="filtersContainer">
            <div class="row g-3 mb-3">
                <div class="col-lg-3 col-md-4">
                    <label for="approvalStageFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-layer-group me-1"></i>مرحلة الاعتماد
                    </label>
                    <select class="form-select form-select-sm" id="approvalStageFilter" multiple>
                        <?php foreach ($approvalStages as $stage): ?>
                            <option value="<?= htmlspecialchars($stage['key']) ?>"><?= htmlspecialchars($stage['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="branchFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-code-branch me-1"></i>الفرع
                    </label>
                    <select class="form-select form-select-sm" id="branchFilter">
                        <option value="">جميع الفروع</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="departmentFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-building me-1"></i>القسم
                    </label>
                    <select class="form-select form-select-sm" id="departmentFilter">
                        <option value="">الكل</option>
                        <option value="connections">التوصيلات</option>
                        <option value="projects">المشاريع</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="demolitionFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-recycle me-1"></i>حالة التخريد
                    </label>
                    <select class="form-select form-select-sm" id="demolitionFilter">
                        <option value="">الكل</option>
                        <option value="pending">متبقي تخريد</option>
                        <option value="completed">مكتمل التخريد</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="dateFromFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-calendar-alt me-1"></i>من تاريخ
                    </label>
                    <input type="date" class="form-control form-control-sm" id="dateFromFilter">
                </div>
            </div>
            
            <div class="row g-3">
                <div class="col-lg-2 col-md-3">
                    <label for="dateToFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-calendar-alt me-1"></i>إلى تاريخ
                    </label>
                    <input type="date" class="form-control form-control-sm" id="dateToFilter">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label small fw-bold mb-2 d-block">&nbsp;</label>
                    <div class="d-flex gap-2 w-100">
                        <button type="button" class="btn btn-light btn-sm shadow-sm rounded-pill border-0 text-danger px-3 w-100" id="resetFilters" title="إعادة تعيين جميع الفلاتر">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Extracts Table -->
    <div class="card dash-card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-0 py-3" style="border-radius: 20px 20px 0 0;">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list text-warning me-2"></i>
                    قائمة المستخلصات النهائية للجزئية
                </h5>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3 border-bottom">
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="customTableSearch" placeholder="ابحث في الجدول...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table premium-table table-hover table-bordered" id="extractsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم PO</th>
                            <th>رقم الفاتورة</th>
                            <th>الفرع</th>
                            <th>القسم</th>
                            <th class="text-nowrap">تاريخ المستخلص</th>
                            <th class="text-nowrap">تاريخ الصرف</th>
                            <th>المبلغ الصافي</th>
                            <th>أوامر العمل</th>
                            <th>شهادات الإنجاز</th>
                            <th>التخريد</th>
                            <th>مرحلة الاعتماد</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extracts as $extract):
                            // حساب حالة اكتمال شهادات الإنجاز
                            $certificatesComplete = ($extract['work_orders_count'] > 0 && $extract['confirmed_certificates'] == $extract['work_orders_count']);

                            // حساب حالة التخريد
                            $demolitionComplete = ($extract['work_orders_count'] > 0 && $extract['pending_demolition'] == 0);

                            // تحديد القسم (نفس الطريقة المستخدمة في المستخلصات الجزئية)
                            $departmentName = '';
                            $departmentColor = 'secondary';
                            switch($extract['department']) {
                                case 'connections':
                                    $departmentName = 'التوصيلات';
                                    $departmentColor = 'info';
                                    break;
                                case 'projects':
                                    $departmentName = 'المشاريع';
                                    $departmentColor = 'warning';
                                    break;
                                default:
                                    $departmentName = $extract['department'] ?? 'غير محدد';
                                    $departmentColor = 'secondary';
                            }
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($extract['extract_number']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($extract['po_number'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($extract['invoice_number'] ?? 'لا يوجد'); ?></td>
                            <td><?php echo htmlspecialchars($extract['branch_name'] ?? 'غير محدد'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $departmentColor; ?> department-badge"><?php echo htmlspecialchars($departmentName); ?></span>
                            </td>
                            <td class="text-nowrap"><?php echo date('Y-m-d', strtotime($extract['extract_date'])); ?></td>
                            <td class="text-nowrap">
                                <?php if (!empty($extract['disbursement_date'])): ?>
                                    <?php echo date('Y-m-d', strtotime($extract['disbursement_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-success fw-bold"><?php echo number_format($extract['net_amount'] ?? 0, 2); ?></span>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td>
                                <?php if ($extract['work_orders_count'] > 0): ?>
                                    <span class="badge bg-info"><?php echo $extract['work_orders_count']; ?> أوامر</span>
                                <?php else: ?>
                                    <span class="text-muted small">لا توجد أوامر</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($extract['work_orders_count'] > 0): ?>
                                    <div class="completion-status justify-content-center">
                                        <?php if ($extract['confirmed_certificates'] == $extract['work_orders_count']): ?>
                                            <span class="badge bg-success badge-completion">مكتمل</span>
                                            <i class="fas fa-check-circle text-success" title="شهادات الإنجاز مكتملة"></i>
                                        <?php else: ?>
                                            <span class="badge bg-warning badge-completion">
                                                <?php echo $extract['confirmed_certificates']; ?>/<?php echo $extract['work_orders_count']; ?>
                                            </span>
                                            <i class="fas fa-exclamation-triangle text-warning" title="شهادات الإنجاز غير مكتملة"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">لا توجد أوامر</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($extract['work_orders_count'] > 0): ?>
                                    <div class="demolition-status justify-content-center">
                                        <?php if ($extract['pending_demolition'] > 0): ?>
                                            <span class="badge bg-danger badge-completion">
                                                <?php echo $extract['pending_demolition']; ?> متبقي
                                            </span>
                                            <i class="fas fa-exclamation-triangle text-danger" title="يوجد تخريد متبقي"></i>
                                        <?php else: ?>
                                            <span class="badge bg-success badge-completion">مكتمل</span>
                                            <i class="fas fa-check-circle text-success" title="التخريد مكتمل"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">لا توجد أوامر</span>
                                <?php endif; ?>
                            </td>
                            <td data-approval-stage="<?= htmlspecialchars($extract['approval_stage'] ?? '') ?>">
                                <select class="form-select form-select-sm approval-stage-select approval-stage-<?= $extract['approval_stage'] ?>"
                                        data-extract-id="<?= $extract['id'] ?>"
                                        onchange="updateApprovalStage(<?= $extract['id'] ?>, this.value, this)">
                                    <?php foreach ($approvalStages as $stage): ?>
                                        <option value="<?= $stage['key'] === null ? '' : htmlspecialchars($stage['key']) ?>"
                                                <?= $extract['approval_stage'] === $stage['key'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($stage['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="viewExtract(<?php echo $extract['id']; ?>)" title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="export-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-sm btn-outline-success" target="_blank" title="تصدير كفاتورة ضريبية">
                                        <i class="fas fa-file-excel"></i>
                                    </a>
                                    <?php if ($extract['approval_stage'] === 'draft'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editExtract(<?php echo $extract['id']; ?>)" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteExtract(<?php echo $extract['id']; ?>)" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Select2 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // تهيئة Select2 لفلتر مرحلة الاعتماد
    $('#approvalStageFilter').select2({
        theme: 'bootstrap-5',
        placeholder: 'اختر مرحلة أو أكثر...',
        allowClear: true,
        dir: 'rtl',
        language: {
            noResults: function() {
                return "لا توجد نتائج";
            }
        },
        width: '100%',
        closeOnSelect: false // لا يغلق القائمة عند اختيار عنصر
    });

    // التحقق من تهيئة DataTable مسبقاً وتدميرها لتتوافق مع HTMX
    if ($.fn.DataTable.isDataTable('#extractsTable')) {
        $('#extractsTable').DataTable().destroy();
    }
    
    // Initialize DataTable
    $('#extractsTable').DataTable({
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
            "order": [[ 0, "desc" ]],
            "pageLength": 25,
            "columnDefs": [
                { "orderable": false, "targets": -1 }
            ]
        });

    // Filter functionality
    $('#approvalStageFilter, #branchFilter, #departmentFilter, #dateFromFilter, #dateToFilter, #demolitionFilter').on('change', function() {
        var table = $('#extractsTable').DataTable();

        // إزالة جميع الفلاتر المخصصة القديمة
        $.fn.dataTable.ext.search = [];

        // الحصول على قيم الفلاتر
        var approvalStages = $('#approvalStageFilter').val(); // مصفوفة من المراحل المختارة
        var branchId = $('#branchFilter').val();
        var department = $('#departmentFilter').val();
        var dateFrom = $('#dateFromFilter').val();
        var dateTo = $('#dateToFilter').val();
        var demolitionFilter = $('#demolitionFilter').val();

        // إضافة فلتر مخصص واحد يجمع كل الشروط
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                var row = table.row(dataIndex).node();

                // فلتر مرحلة الاعتماد (دعم تحديد متعدد)
                if (approvalStages && approvalStages.length > 0) {
                    var selectValue = $(row).find('.approval-stage-select').val();
                    // التحقق من أن قيمة المرحلة موجودة في المراحل المختارة
                    if (approvalStages.indexOf(selectValue) === -1) {
                        return false;
                    }
                }

                // فلتر الفرع
                if (branchId) {
                    var branchName = $('#branchFilter option:selected').text();
                    if (data[3].indexOf(branchName) === -1) { // العمود 3 هو الفرع
                        return false;
                    }
                }

                // فلتر القسم
                if (department) {
                    var departmentText = department === 'connections' ? 'التوصيلات' : 'المشاريع';
                    if (data[4].indexOf(departmentText) === -1) { // العمود 4 هو القسم
                        return false;
                    }
                }

                // فلتر التاريخ
                if (dateFrom || dateTo) {
                    var dateStr = data[5]; // العمود 5 هو تاريخ المستخلص
                    if (dateStr) {
                        var date = new Date(dateStr);
                        var min = dateFrom ? new Date(dateFrom) : null;
                        var max = dateTo ? new Date(dateTo) : null;

                        if (min && date < min) return false;
                        if (max && date > max) return false;
                    }
                }

                // فلتر التخريد
                if (demolitionFilter) {
                    var demolitionCell = $(row).find('td').eq(9); // العمود 9 هو التخريد
                    var hasPendingDemolition = demolitionCell.find('.fa-exclamation-triangle').length > 0;
                    var isCompleted = demolitionCell.find('.fa-check-circle').length > 0;

                    if (demolitionFilter === 'pending' && !hasPendingDemolition) {
                        return false; // فقط المتبقي تخريد
                    }
                    if (demolitionFilter === 'completed' && !isCompleted) {
                        return false; // فقط المكتمل
                    }
                }

                return true;
            }
        );

        table.draw();
    });

    // ربط مربع البحث المخصص بـ DataTable
    $('#customTableSearch').on('keyup', function() {
        $('#extractsTable').DataTable().search(this.value).draw();
    });

    // Collapse Filters Logic
    $('#filtersHeader').on('click', function() {
        $('#filtersContainer').slideToggle(300);
        const icon = $(this).find('i.fa-chevron-down');
        const text = $('#toggleFiltersText');
        
        if (icon.css('transform') !== 'none' && icon.css('transform') !== 'matrix(1, 0, 0, 1, 0, 0)') {
            icon.css('transform', 'rotate(0deg)');
            text.text('إخفاء');
        } else {
            icon.css('transform', 'rotate(180deg)');
            text.text('إظهار');
        }
    });

    // إغلاق الفلاتر افتراضياً
    $('#filtersContainer').hide();

    // زر إعادة تعيين الفلاتر
    $('#resetFilters').on('click', function() {
        // إعادة تعيين جميع الفلاتر
        $('#approvalStageFilter').val(null).trigger('change'); // إعادة تعيين Select2
        $('#branchFilter').val('');
        $('#departmentFilter').val('');
        $('#dateFromFilter').val('');
        $('#dateToFilter').val('');
        $('#demolitionFilter').val('');

        // إزالة جميع الفلاتر المخصصة
        $.fn.dataTable.ext.search = [];

        // إعادة تعيين فلاتر DataTable
        var table = $('#extractsTable').DataTable();
        table.search('').columns().search('').draw();

        // إظهار رسالة تأكيد
        Swal.fire({
            position: 'top-end',
            icon: 'success',
            title: 'تم إعادة تعيين الفلاتر',
            showConfirmButton: false,
            timer: 1500,
            toast: true
        });
    });
});

function viewExtract(id) {
    window.location.href = 'view.php?id=' + id;
}

function editExtract(id) {
    window.location.href = 'edit.php?id=' + id;
}

function deleteExtract(id) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذا المستخلص؟ سيتم حذف جميع البيانات المرتبطة به.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إظهار مؤشر التحميل
            Swal.fire({
                title: 'جاري الحذف...',
                text: 'يرجى الانتظار',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // إرسال طلب الحذف
            fetch('delete-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'extract_id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'تم الحذف بنجاح',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // إعادة تحميل الصفحة لتحديث القائمة
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الحذف',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال',
                    text: 'تعذر الاتصال بالخادم'
                });
            });
        }
    });
}

// دالة التحديث المباشر لمرحلة الاعتماد
function updateApprovalStage(extractId, newStage, element) {
    // إظهار مؤشر التحميل
    const originalValue = $(element).val();
    $(element).prop('disabled', true);
    $(element).addClass('updating-approval');

    $.ajax({
        url: 'update-approval-stage-ajax.php',
        type: 'POST',
        data: {
            extract_id: extractId,
            approval_stage: newStage
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // إظهار رسالة نجاح
                Swal.fire({
                    icon: 'success',
                    title: 'تم التحديث!',
                    text: response.message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });

                // تحديث الألوان
                updateApprovalStageColors(element, newStage);

                // تحديث data attribute للفلترة
                $(element).closest('td').attr('data-approval-stage', newStage);

                // تحديث الإحصائيات
                updateStatistics();

                // إضافة تأثير بصري للنجاح
                $(element).closest('tr').addClass('table-success');
                setTimeout(() => {
                    $(element).closest('tr').removeClass('table-success');
                }, 2000);

            } else {
                // إظهار رسالة خطأ
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: response.message || 'حدث خطأ أثناء التحديث',
                    confirmButtonText: 'موافق'
                });

                // إعادة القيمة الأصلية
                $(element).val(originalValue);
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

            // إعادة القيمة الأصلية
            $(element).val(originalValue);
        },
        complete: function() {
            // إعادة تفعيل العنصر
            $(element).prop('disabled', false);
            $(element).removeClass('updating-approval');
        }
    });
}

// دالة تحديث ألوان مرحلة الاعتماد
function updateApprovalStageColors(element, stage) {
    // إزالة جميع فئات مراحل الاعتماد
    const stageClasses = [
        <?php foreach ($approvalStages as $stage): ?>
            'approval-stage-<?= $stage['key'] ?>',
        <?php endforeach; ?>
        'approval-stage-'
    ];

    $(element).removeClass(stageClasses.join(' '));

    if (stage) {
        $(element).addClass('approval-stage-' + stage);
    } else {
        $(element).addClass('approval-stage-');
    }
}

// دالة تحديث الإحصائيات
function updateStatistics() {
    $.ajax({
        url: 'get-statistics-ajax.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // تحديث بطاقة الإجمالي
                $('#stat-total').text(response.stats.total + ' مستخلص');
                $('#stat-total-net').text(new Intl.NumberFormat('ar-SA').format(response.stats.net_amount || 0));

                // تحديث بطاقات مراحل الاعتماد ديناميكياً
                <?php foreach ($approvalStages as $stage): ?>
                    (function() {
                        var stageKey = '<?= $stage['key'] ?>';
                        var count = response.stats[stageKey] || 0;
                        var netAmount = response.stats[stageKey + '_net_amount'] || 0;

                        var cardElement = $('#card-' + stageKey);

                        if (count > 0) {
                            // إظهار البطاقة وتحديث القيم
                            if (cardElement.length === 0) {
                                // إنشاء البطاقة إذا لم تكن موجودة
                                createStageCard(stageKey, '<?= $stage['name'] ?>', '<?= $stage['color'] ?>', count, netAmount);
                            } else {
                                cardElement.show();
                                $('#stat-' + stageKey).text(count + ' مستخلص');
                                $('#stat-' + stageKey + '-net').text(new Intl.NumberFormat('ar-SA').format(netAmount));
                            }
                        } else {
                            // إخفاء البطاقة
                            cardElement.hide();
                        }
                    })();
                <?php endforeach; ?>

                // تحديث إحصائيات الأقسام
                if (response.departments) {
                    response.departments.forEach(function(dept) {
                        var deptCardElement = $('#dept-card-' + dept.id);

                        if (dept.count > 0) {
                            if (deptCardElement.length === 0) {
                                // إنشاء بطاقة القسم إذا لم تكن موجودة
                                createDepartmentCard(dept.id, dept.name, dept.color, dept.count, dept.net_amount);
                            } else {
                                deptCardElement.show();
                                $('#dept-stat-' + dept.id).text(dept.count + ' مستخلص');
                                $('#dept-stat-' + dept.id + '-net').text(new Intl.NumberFormat('ar-SA').format(dept.net_amount));
                            }
                        } else {
                            deptCardElement.hide();
                        }
                    });
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Statistics update error:', error);
        }
    });
}

// دالة إنشاء بطاقة مرحلة جديدة
function createStageCard(stageKey, stageName, stageColor, count, netAmount) {
    var icons = {
        'draft': 'fas fa-edit',
        'submitted': 'fas fa-paper-plane',
        'under_review': 'fas fa-clock',
        'approved': 'fas fa-check',
        'rejected': 'fas fa-times',
        'disbursed': 'fas fa-money-bill-wave',
        'technical_support': 'fas fa-tools',
        'construction': 'fas fa-hard-hat',
        'department_manager': 'fas fa-user-tie',
        'administration_manager': 'fas fa-user-cog',
        'taif_finance': 'fas fa-calculator'
    };

    var icon = icons[stageKey] || 'fas fa-file';

    var cardHtml = `
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3" id="card-${stageKey}">
            <div class="card border-left-${stageColor} shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-${stageColor} text-uppercase mb-1">
                                ${stageName}
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="stat-${stageKey}">${count} مستخلص</div>
                            <div class="mt-2">
                                <div class="text-xs text-muted">
                                    <i class="fas fa-coins fa-sm"></i>
                                    صافي: <span class="font-weight-bold text-primary" id="stat-${stageKey}-net">${new Intl.NumberFormat('ar-SA').format(netAmount)}</span>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="${icon} fa-2x text-${stageColor}"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#statisticsCards').append(cardHtml);
}

// دالة إنشاء بطاقة قسم جديدة
function createDepartmentCard(deptId, deptName, deptColor, count, netAmount) {
    var cardHtml = `
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3" id="dept-card-${deptId}">
            <div class="card border-left-${deptColor} shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-${deptColor} text-uppercase mb-1">
                                ${deptName}
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="dept-stat-${deptId}">${count} مستخلص</div>
                            <div class="mt-2">
                                <div class="text-xs text-muted">
                                    <i class="fas fa-coins fa-sm"></i>
                                    صافي: <span class="font-weight-bold text-primary" id="dept-stat-${deptId}-net">${new Intl.NumberFormat('ar-SA').format(netAmount)}</span>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-${deptColor}"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    $('#departmentCards').append(cardHtml);
}
</script>

<style>
/* تنسيق مراحل الاعتماد */
.approval-stage-select {
    border: 2px solid;
    font-weight: 600;
    font-size: 0.85em;
    min-width: 140px;
}

<?php foreach ($approvalStages as $stage): ?>
    <?php
    // تحديد الألوان بناءً على نوع المرحلة
    $colors = [
        'secondary' => ['border' => '#6c757d', 'bg' => '#f8f9fa', 'text' => '#495057'],
        'info' => ['border' => '#17a2b8', 'bg' => '#d1ecf1', 'text' => '#0c5460'],
        'warning' => ['border' => '#ffc107', 'bg' => '#fff3cd', 'text' => '#856404'],
        'success' => ['border' => '#28a745', 'bg' => '#d4edda', 'text' => '#155724'],
        'danger' => ['border' => '#dc3545', 'bg' => '#f8d7da', 'text' => '#721c24'],
        'primary' => ['border' => '#007bff', 'bg' => '#d1ecf1', 'text' => '#004085']
    ];

    $color = $colors[$stage['color']] ?? $colors['secondary'];
    ?>
.approval-stage-<?= $stage['key'] ?> {
    border-color: <?= $color['border'] ?> !important;
    background-color: <?= $color['bg'] ?> !important;
    color: <?= $color['text'] ?> !important;
}

<?php endforeach; ?>

.approval-stage- {
    border-color: #6c757d !important;
    background-color: #f8f9fa !important;
    color: #495057 !important;
}

/* تأثير التحديث */
.updating-approval {
    opacity: 0.6;
    pointer-events: none;
}

/* تأثير النجاح */
.table-success {
    background-color: #d4edda !important;
    transition: background-color 0.3s ease;
}

/* تنسيق شهادات الإنجاز */
.completion-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.completion-status .badge {
    min-width: 50px;
    text-align: center;
}

/* تنسيق التخريد */
.demolition-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.demolition-status .badge {
    min-width: 60px;
    text-align: center;
}

/* تنسيق شارة القسم */
.department-badge {
    font-weight: 500;
}
</style>
