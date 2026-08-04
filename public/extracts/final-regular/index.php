<?php
/**
 * صفحة فهرس المستخلصات النهائية العادية
 * Final Regular Extracts Index Page
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'المستخلصات النهائية العادية';
$currentPage = 'extracts-final-regular';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات النهائية العادية', 'url' => 'extracts/final-regular/index.php']
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

// جلب المستخلصات النهائية العادية مع بيانات الفروع والمستخدمين
$extractsQuery = "
    SELECT fre.*,
           b.name as branch_name,
           u.full_name as created_by_name,
           COUNT(DISTINCT frewo.id) as work_orders_count,
           -- إحصائيات التخريد
           COUNT(DISTINCT CASE WHEN (df.status = 'not_applicable' OR df.status = 'attached') THEN frewo.id END) as completed_demolition,
           COUNT(DISTINCT CASE WHEN df.status = 'not_attached' THEN frewo.id END) as pending_demolition,
           -- رقم العقد (من أول أمر عمل)
           (SELECT con.contract_number FROM work_orders wo2 
            JOIN contracts con ON wo2.contract_id = con.id 
            JOIN final_regular_extract_work_orders frewo2 ON wo2.id = frewo2.work_order_id 
            WHERE frewo2.final_regular_extract_id = fre.id LIMIT 1) as contract_number
    FROM final_regular_extracts fre
    LEFT JOIN branches b ON fre.branch_id = b.id
    LEFT JOIN users u ON fre.created_by = u.id
    LEFT JOIN final_regular_extract_work_orders frewo ON fre.id = frewo.final_regular_extract_id
    LEFT JOIN work_orders wo ON frewo.work_order_id = wo.id
    -- نموذج التخريد
    LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
    GROUP BY fre.id
    ORDER BY fre.created_at DESC
";
$extracts = $db->query($extractsQuery)->fetchAll();

// جلب مراحل الاعتماد من قاعدة البيانات أولاً لبناء الاستعلام ديناميكياً
try {
    $approvalStagesFromDB = $db->query("
        SELECT stage_key, stage_name, stage_color, stage_order, is_active
        FROM approval_stages
        WHERE is_active = 1
        ORDER BY stage_order
    ")->fetchAll();

    $dynamicApprovalStages = [];
    foreach ($approvalStagesFromDB as $stage) {
        $dynamicApprovalStages[] = $stage['stage_key'];
    }
} catch (Exception $e) {
    // في حالة عدم وجود جدول approval_stages، استخدم القيم الافتراضية
    $dynamicApprovalStages = ['technical_support', 'construction', 'department_manager', 'administration_manager', 'taif_finance', 'disbursed'];
}

// بناء الاستعلام ديناميكياً
$statsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN department = 'connections' THEN 1 ELSE 0 END) as connections_count,
        SUM(CASE WHEN department = 'projects' THEN 1 ELSE 0 END) as projects_count,
        SUM(total_amount) as total_amount,
        SUM(total_penalty_amount) as total_penalty_amount,
        SUM(net_amount) as net_amount,
        -- المبالغ الصافية للأقسام
        SUM(CASE WHEN department = 'connections' THEN net_amount ELSE 0 END) as connections_net_amount,
        SUM(CASE WHEN department = 'projects' THEN net_amount ELSE 0 END) as projects_net_amount";

// إضافة مراحل الاعتماد ديناميكياً
foreach ($dynamicApprovalStages as $stageKey) {
    $statsQuery .= ",
        SUM(CASE WHEN approval_stage = '{$stageKey}' THEN 1 ELSE 0 END) as {$stageKey},
        SUM(CASE WHEN approval_stage = '{$stageKey}' THEN net_amount ELSE 0 END) as {$stageKey}_net_amount";
}

$statsQuery .= "
    FROM final_regular_extracts
";

$stats = $db->query($statsQuery)->fetch();

// جلب الفروع للفلترة
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY name")->fetchAll();

// تحويل البيانات إلى مصفوفة للاستخدام في العرض
$stageNames = [];
$approvalStages = [];

// إضافة الأيقونات المناسبة لكل مرحلة
$stageIcons = [
    'technical_support' => 'fas fa-tools',
    'construction' => 'fas fa-hard-hat',
    'department_manager' => 'fas fa-user-tie',
    'administration_manager' => 'fas fa-crown',
    'taif_finance' => 'fas fa-coins',
    'disbursed' => 'fas fa-check-circle'
];

// إضافة باقي المراحل من قاعدة البيانات أو القيم الافتراضية
if (isset($approvalStagesFromDB) && !empty($approvalStagesFromDB)) {
    // استخدام البيانات من قاعدة البيانات
    foreach ($approvalStagesFromDB as $stage) {
        $stageNames[$stage['stage_key']] = $stage['stage_name'];
        $approvalStages[] = [
            'key' => $stage['stage_key'],
            'name' => $stage['stage_name'],
            'icon' => $stageIcons[$stage['stage_key']] ?? 'fas fa-clipboard-check',
            'color' => $stage['stage_color']
        ];
    }
} else {
    // استخدام القيم الافتراضية
    $defaultStages = [
        ['key' => 'technical_support', 'name' => 'الدعم الفني', 'icon' => 'fas fa-tools', 'color' => 'info'],
        ['key' => 'construction', 'name' => 'الإنشاءات', 'icon' => 'fas fa-hard-hat', 'color' => 'warning'],
        ['key' => 'department_manager', 'name' => 'مدير القسم', 'icon' => 'fas fa-user-tie', 'color' => 'primary'],
        ['key' => 'administration_manager', 'name' => 'مدير الإدارة', 'icon' => 'fas fa-crown', 'color' => 'dark'],
        ['key' => 'taif_finance', 'name' => 'مالية الطائف', 'icon' => 'fas fa-coins', 'color' => 'warning'],
        ['key' => 'disbursed', 'name' => 'تم الصرف', 'icon' => 'fas fa-check-circle', 'color' => 'success']
    ];

    foreach ($defaultStages as $stage) {
        $stageNames[$stage['key']] = $stage['name'];
        $approvalStages[] = $stage;
    }
}

// معلومات الأقسام
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

// دالة مساعدة لأسماء مراحل الاعتماد
function getApprovalStageName($stage)
{
    global $stageNames;
    return $stageNames[$stage] ?? "غير محدد";
}

// بدء تخزين المحتوى
ob_start();
?>


<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-0 text-success">
                <i class="fas fa-file-invoice text-success me-2"></i>
                المستخلصات النهائية العادية
            </h1>
            <p class="text-muted mb-0">إدارة المستخلصات النهائية العادية (FRE-YYYY-XXX) - مع الغرامات</p>
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
                <span>مستخلص نهائي عادي جديد</span>
            </a>
        </div>
    </div>

    <!-- Statistics Cards - Dynamic -->
    <div class="row flex-nowrap overflow-auto hide-scrollbar pb-2 mb-4">
        <!-- إجمالي المستخلصات -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="dash-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;">إجمالي المستخلصات</div>
                        <div class="h4 mb-0 fw-bold text-dark"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="icon-circle bg-success-soft">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
                <div class="mt-auto border-top pt-2">
                    <div class="text-muted" style="font-size: 0.8rem;">
                        <i class="fas fa-coins me-1 text-success"></i> صافي: 
                        <span class="fw-bold text-dark"><?php echo number_format($stats['net_amount'] ?? 0, 0); ?></span>
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
            if ($count == 0)
                continue;
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
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="dash-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;"><?php echo htmlspecialchars($stage['name']); ?></div>
                            <div class="h4 mb-0 fw-bold text-dark"><?php echo $count; ?></div>
                        </div>
                        <div class="icon-circle <?php echo $softBg; ?>">
                            <i class="<?php echo $stage['icon']; ?>"></i>
                        </div>
                    </div>
                    <div class="mt-auto border-top pt-2">
                        <div class="text-muted" style="font-size: 0.8rem;">
                            <i class="fas fa-coins me-1 text-<?php echo $colorClass; ?>"></i> صافي: 
                            <span class="fw-bold text-dark"><?php echo number_format($netAmount, 0); ?></span>
                            <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- بطاقات الأقسام الديناميكية -->
    <div class="row flex-nowrap overflow-auto hide-scrollbar pb-2 mb-4">
        <?php foreach ($departments as $department): ?>
            <?php
            $deptKey = $department['key'];
            $count = $stats[$deptKey . '_count'] ?? 0;
            $netAmount = $stats[$deptKey . '_net_amount'] ?? 0;

            // إخفاء البطاقات التي لا تحتوي على مستخلصات
            if ($count == 0)
                continue;
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
    <div class="row flex-nowrap overflow-auto hide-scrollbar pb-2 mb-4">
        <div class="col-md-4">
            <div class="dash-card h-100 p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-1" style="font-size: 0.75rem;">إجمالي المبالغ</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php echo number_format($stats['total_amount'] ?? 0, 2); ?>
                            <span class="sar-icon-lg text-success"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                    <div class="icon-circle bg-success-soft" style="width: 65px; height: 65px; font-size: 1.8rem;">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
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
        <div class="col-md-4">
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
                            <option value="<?php echo $stage['key'] ?? ''; ?>">
                                <?php echo htmlspecialchars($stage['name']); ?></option>
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
                            <option value="<?php echo $branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?>
                            </option>
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
                    <i class="fas fa-list text-success me-2"></i>
                    قائمة المستخلصات النهائية العادية
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
                <table class="table premium-table" id="extractsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم PO</th>
                            <th>رقم الفاتورة</th>
                            <th>الفرع</th>
                            <th>القسم</th>
                            <th class="text-nowrap">تاريخ المستخلص</th>
                            <th class="text-nowrap">تاريخ الصرف</th>
                            <th>التخريد</th>
                            <th>الغرامات</th>
                            <th>الصافي</th>
                            <th>أوامر العمل</th>
                            <th>مرحلة الاعتماد</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extracts as $extract): ?>
                            <tr>
                                <td>
                                    <span
                                        class="badge bg-success"><?php echo htmlspecialchars($extract['extract_number']); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($extract['po_number'])): ?>
                                        <span
                                            class="badge bg-info"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($extract['invoice_number'] ?? 'لا يوجد'); ?></td>
                                <td><?php echo htmlspecialchars($extract['branch_name'] ?? 'غير محدد'); ?></td>
                                <td>
                                    <?php
                                    $departmentName = $extract['department'] === 'connections' ? 'التوصيلات' :
                                        ($extract['department'] === 'projects' ? 'المشاريع' : 'غير محدد');
                                    echo htmlspecialchars($departmentName);
                                    ?>
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
                                    <?php if ($extract['work_orders_count'] > 0): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($extract['pending_demolition'] > 0): ?>
                                                <span class="badge bg-warning badge-completion">
                                                    <?php echo $extract['completed_demolition']; ?>/<?php echo $extract['work_orders_count']; ?>
                                                </span>
                                                <span class="text-warning small">
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
                                <td>
                                    <?php if ($extract['total_penalty_amount'] > 0): ?>
                                        <span
                                            class="text-danger"><?php echo number_format($extract['total_penalty_amount'], 2); ?></span>
                                        <span class="sar-icon"><svg>
                                                <use href="#sar-symbol" />
                                            </svg></span>
                                    <?php else: ?>
                                        <span class="text-muted">لا يوجد</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo number_format($extract['net_amount'], 2); ?>
                                    <span class="sar-icon"><svg>
                                            <use href="#sar-symbol" />
                                        </svg></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $extract['work_orders_count']; ?> أوامر</span>
                                </td>
                                <td data-approval-stage="<?= htmlspecialchars($extract['approval_stage'] ?? '') ?>">
                                    <select
                                        class="form-select form-select-sm approval-stage-select approval-stage-<?= $extract['approval_stage'] ?>"
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
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                            onclick="viewExtract(<?php echo $extract['id']; ?>)" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="export-invoice.php?id=<?php echo $extract['id']; ?>"
                                            class="btn btn-sm btn-outline-info" target="_blank"
                                            title="تصدير كفاتورة ضريبية">
                                            <i class="fas fa-file-excel"></i>
                                        </a>
                                        <?php if ($extract['approval_stage'] === 'draft'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                onclick="editExtract(<?php echo $extract['id']; ?>)" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteExtract(<?php echo $extract['id']; ?>)" title="حذف">
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
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
    rel="stylesheet" />

<style>
    /* تنسيق مراحل الاعتماد */
    .approval-stage-select {
        border: 2px solid;
        font-weight: 600;
        font-size: 0.85em;
        min-width: 140px;
    }

    .approval-stage-technical_support {
        border-color: #17a2b8 !important;
        background-color: #d1ecf1 !important;
        color: #0c5460 !important;
    }

    .approval-stage-construction {
        border-color: #ffc107 !important;
        background-color: #fff3cd !important;
        color: #856404 !important;
    }

    .approval-stage-department_manager {
        border-color: #007bff !important;
        background-color: #d1ecf1 !important;
        color: #004085 !important;
    }

    .approval-stage-administration_manager {
        border-color: #343a40 !important;
        background-color: #d6d8db !important;
        color: #1d2124 !important;
    }

    .approval-stage-taif_finance {
        border-color: #fd7e14 !important;
        background-color: #ffeaa7 !important;
        color: #8a4412 !important;
    }

    .approval-stage-disbursed {
        border-color: #28a745 !important;
        background-color: #d4edda !important;
        color: #155724 !important;
    }

    .approval-stage- {
        border-color: #6c757d !important;
        background-color: #f8f9fa !important;
        color: #495057 !important;
    }

    /* تأثيرات التحديث */
    .updating-approval {
        opacity: 0.6;
        pointer-events: none;
    }

    .approval-updated {
        animation: pulse-success 0.6s ease-in-out;
    }

    @keyframes pulse-success {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }

        100% {
            transform: scale(1);
        }
    }

    /* تنسيق badges الإكمال */
    .badge-completion {
        font-size: 0.75em;
        padding: 0.25em 0.5em;
    }

    .text-danger {
        color: #dc3545 !important;
    }
</style>

<!-- Select2 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        // تهيئة Select2 لفلتر مرحلة الاعتماد
        $('#approvalStageFilter').select2({
            theme: 'bootstrap-5',
            placeholder: 'اختر مرحلة أو أكثر...',
            allowClear: true,
            dir: 'rtl',
            language: {
                noResults: function () {
                    return "لا توجد نتائج";
                }
            },
            width: '100%',
            closeOnSelect: false // لا يغلق القائمة عند اختيار عنصر
        });

        // التحقق من عدم تهيئة DataTable مسبقاً
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
                "order": [[0, "desc"]],
                "pageLength": 25,
                "columnDefs": [
                    { "orderable": false, "targets": -1 }
                ]
            });


        // Filter functionality
        $('#approvalStageFilter, #branchFilter, #departmentFilter, #dateFromFilter, #dateToFilter, #demolitionFilter').on('change', function () {
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
                function (settings, data, dataIndex) {
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
                        var demolitionCell = $(row).find('td').eq(6); // العمود 6 هو التخريد
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

            // تحديث عداد النتائج
            updateFilterResults();
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
        $('#resetFilters').on('click', function () {
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

            // تحديث عداد النتائج
            updateFilterResults();

            // إظهار رسالة تأكيد
            Swal.fire({
                position: 'top-end',
                icon: 'success',
                title: 'تم إعادة تعيين الفلاتر',
                showConfirmButton: false,
                timer: 1500
            });
        });

        // ربط مربع البحث المخصص بـ DataTable
        $('#customTableSearch').on('keyup', function() {
            $('#extractsTable').DataTable().search(this.value).draw();
        });

        // تطبيق ألوان مراحل الاعتماد
        applyApprovalStageColors();

        // تحديث عداد النتائج الأولي
        updateFilterResults();
    });

    // دالة تحديث عداد النتائج المفلترة
    function updateFilterResults() {
        var table = $('#extractsTable').DataTable();
        var info = table.page.info();
        var totalRecords = info.recordsTotal;
        var filteredRecords = info.recordsDisplay;

        var resultText = '';
        if (filteredRecords < totalRecords) {
            resultText = `عرض ${filteredRecords} من أصل ${totalRecords} مستخلص`;
        } else {
            resultText = `عرض جميع المستخلصات (${totalRecords})`;
        }

        $('#filterResults').text(resultText);
    }

    // دالة تطبيق ألوان مراحل الاعتماد
    function applyApprovalStageColors() {
        $('.approval-stage-select').each(function () {
            var value = $(this).val();
            // إزالة جميع classes مراحل الاعتماد
            var classesToRemove = '';
            <?php foreach ($approvalStages as $stage): ?>
                classesToRemove += 'approval-stage-<?= $stage['key'] ?> ';
            <?php endforeach; ?>
            classesToRemove += 'approval-stage- ';

            $(this).removeClass(classesToRemove.trim());

            // إضافة class المرحلة الحالية
            if (value) {
                $(this).addClass('approval-stage-' + value);
            } else {
                $(this).addClass('approval-stage-');
            }
        });
    }

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

        // إرسال طلب AJAX
        $.ajax({
            url: 'update-approval-ajax.php',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                extract_id: extractId,
                approval_stage: newStage
            }),
            success: function (response) {
                console.log('Raw response:', response);
                console.log('Response type:', typeof response);

                // التأكد من أن الاستجابة هي كائن JSON
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', e);
                        console.error('Raw response was:', response);
                        return;
                    }
                }

                console.log('Parsed response:', response);

                if (response.success) {
                    // إظهار رسالة نجاح
                    Swal.fire({
                        position: 'top-end',
                        icon: 'success',
                        title: 'تم تحديث مرحلة الاعتماد بنجاح',
                        showConfirmButton: false,
                        timer: 2000
                    });

                    // تحديث الألوان
                    updateApprovalStageColors(element, newStage);

                    // تحديث data attribute للفلترة
                    $(element).closest('td').attr('data-approval-stage', newStage);

                    // إضافة تأثير بصري للنجاح
                    $(element).removeClass('updating-approval').addClass('approval-updated');
                    setTimeout(function () {
                        $(element).removeClass('approval-updated');
                    }, 600);

                } else {
                    // إظهار رسالة خطأ
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في التحديث',
                        text: response.message || 'حدث خطأ غير متوقع'
                    });

                    // إعادة القيمة السابقة
                    $(element).val(originalValue);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);

                // إظهار رسالة خطأ
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال',
                    text: 'تعذر الاتصال بالخادم'
                });

                // إعادة القيمة السابقة
                $(element).val(originalValue);
            },
            complete: function () {
                // إزالة مؤشر التحميل
                $(element).prop('disabled', false);
                $(element).removeClass('updating-approval');
            }
        });
    }

    // دالة تحديث ألوان مرحلة الاعتماد
    function updateApprovalStageColors(element, stage) {
        $(element).removeClass('approval-stage-technical_support approval-stage-construction approval-stage-department_manager approval-stage-administration_manager approval-stage-taif_finance approval-stage-disbursed approval-stage-');
        if (stage) {
            $(element).addClass('approval-stage-' + stage);
        } else {
            $(element).addClass('approval-stage-');
        }
    }
</script>