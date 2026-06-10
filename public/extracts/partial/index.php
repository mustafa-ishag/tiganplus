<?php
/**
 * صفحة فهرس المستخلصات الجزئية
 * Partial Extracts Index Page
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

$pageTitle = 'المستخلصات الجزئية';
$currentPage = 'extracts-partial';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'المستخلصات الجزئية', 'url' => 'extracts/partial/index.php']
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

// جلب المستخلصات الجزئية مع بيانات الفروع والمستخدمين وإحصائيات أوامر العمل
$extractsQuery = "
    SELECT pe.*,
           b.name as branch_name,
           u.full_name as created_by_name,
           COUNT(DISTINCT pewo.id) as work_orders_count,
           -- إحصائيات شهادات الإنجاز
           COUNT(DISTINCT CASE WHEN cc.completion_certificate_confirmation = 'confirmed' THEN pewo.id END) as confirmed_certificates,
           -- إحصائيات التخريد
           COUNT(DISTINCT CASE WHEN (df.status = 'not_applicable' OR df.status = 'attached') THEN pewo.id END) as completed_demolition,
           COUNT(DISTINCT CASE WHEN df.status = 'not_attached' THEN pewo.id END) as pending_demolition,
           -- التحقق من وجود المستخلص في مستخلص نهائي للجزئية
           CASE WHEN ffpe.id IS NOT NULL THEN 1 ELSE 0 END as has_final_extract,
           -- قيمة المستخلص النهائي (إذا كان موجود)
           ffpe.net_amount as final_extract_net_amount,
           ffpe.total_amount as final_extract_total_amount,
           -- التحقق من وجود قيمة سالبة (مستخلص سالب)
           -- القيمة السالبة: قيمة المستخلص الجزئي > القيمة الفعلية لأمر العمل (والقيمة الفعلية > 0)
           COUNT(DISTINCT CASE
               WHEN wo.actual_value > 0 AND pewo.extract_value > wo.actual_value
               THEN pewo.id
           END) as negative_value_count
    FROM partial_extracts pe
    LEFT JOIN branches b ON pe.branch_id = b.id
    LEFT JOIN users u ON pe.created_by = u.id
    LEFT JOIN partial_extract_work_orders pewo ON pe.id = pewo.partial_extract_id
    LEFT JOIN work_orders wo ON pewo.work_order_id = wo.id
    -- شهادة الإنجاز
    LEFT JOIN work_order_attachments cc ON wo.id = cc.work_order_id AND cc.form_type = 'completion_certificate'
    -- نموذج التخريد
    LEFT JOIN work_order_attachments df ON wo.id = df.work_order_id AND df.form_type = 'demolition_form'
    -- المستخلص النهائي للجزئية
    LEFT JOIN final_for_partial_extracts ffpe ON pe.id = ffpe.related_partial_extract_id
    GROUP BY pe.id
    ORDER BY pe.created_at DESC
";
$extracts = $db->query($extractsQuery)->fetchAll();

// حساب القيمة المتوقعة للمستخلص النهائي لكل مستخلص جزئي
foreach ($extracts as &$extract) {
    // إذا كان المستخلص دخل في نهائي، نستخدم القيمة الفعلية
    if ($extract['has_final_extract']) {
        $extract['expected_final_net_amount'] = $extract['final_extract_net_amount'];
        $extract['expected_final_total_amount'] = $extract['final_extract_total_amount'];
    } else {
        // حساب القيمة المتوقعة بناءً على القيمة المتبقية لأوامر العمل
        $workOrdersQuery = "
            SELECT
                SUM(COALESCE(wo.actual_value, wo.estimated_value) - pewo.extract_value) as remaining_value
            FROM partial_extract_work_orders pewo
            INNER JOIN work_orders wo ON pewo.work_order_id = wo.id
            WHERE pewo.partial_extract_id = ?
            AND COALESCE(wo.actual_value, wo.estimated_value) > pewo.extract_value
        ";
        $stmt = $db->prepare($workOrdersQuery);
        $stmt->execute([$extract['id']]);
        $result = $stmt->fetch();

        $remainingValue = floatval($result['remaining_value'] ?? 0);

        // حساب المبالغ بنفس طريقة المستخلص النهائي للجزئي
        // الصافي = مجموع القيم المتبقية + الضريبة (15%) + ضريبة المستخلص الجزئي - الغرامة (نفترض 0)
        $taxAmount = $remainingValue * 0.15;
        $partialExtractTaxAmount = floatval($extract['tax_amount'] ?? 0);
        $expectedNetAmount = $remainingValue + $taxAmount + $partialExtractTaxAmount;

        $extract['expected_final_net_amount'] = $expectedNetAmount;
        $extract['expected_final_total_amount'] = $remainingValue;
    }
}
unset($extract);

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
        SUM(CASE WHEN (approval_stage = 'draft' OR approval_stage IS NULL) THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN department = 'connections' THEN 1 ELSE 0 END) as connections_count,
        SUM(CASE WHEN department = 'projects' THEN 1 ELSE 0 END) as projects_count,
        COALESCE(SUM(net_amount), 0) as total_net_amount,
        COALESCE(AVG(net_amount), 0) as average_amount,
        -- المبالغ الصافية للمسودة والأقسام
        COALESCE(SUM(CASE WHEN (approval_stage = 'draft' OR approval_stage IS NULL) THEN net_amount ELSE 0 END), 0) as draft_net_amount,
        COALESCE(SUM(CASE WHEN department = 'connections' THEN net_amount ELSE 0 END), 0) as connections_net_amount,
        COALESCE(SUM(CASE WHEN department = 'projects' THEN net_amount ELSE 0 END), 0) as projects_net_amount";

// إضافة مراحل الاعتماد ديناميكياً
foreach ($dynamicApprovalStages as $stageKey) {
    $statsQuery .= ",
        SUM(CASE WHEN approval_stage = '{$stageKey}' THEN 1 ELSE 0 END) as {$stageKey},
        COALESCE(SUM(CASE WHEN approval_stage = '{$stageKey}' THEN net_amount ELSE 0 END), 0) as {$stageKey}_net_amount";
}

$statsQuery .= "
    FROM partial_extracts
";

$stats = $db->query($statsQuery)->fetch();

// تحويل البيانات إلى مصفوفة للاستخدام في العرض
$approvalStages = [];

// إضافة المراحل من قاعدة البيانات أو القيم الافتراضية
if (isset($approvalStagesFromDB) && !empty($approvalStagesFromDB)) {
    // استخدام البيانات من قاعدة البيانات (تشمل مرحلة المسودة)
    foreach ($approvalStagesFromDB as $stage) {
        // تحديد الأيقونة حسب نوع المرحلة
        $icon = 'fas fa-clipboard-check';
        if ($stage['stage_key'] === 'draft') {
            $icon = 'fas fa-edit';
        } elseif ($stage['stage_key'] === 'disbursed') {
            $icon = 'fas fa-money-bill-wave';
        }

        $approvalStages[] = [
            'key' => $stage['stage_key'],
            'name' => $stage['stage_name'],
            'color' => $stage['stage_color'],
            'icon' => $icon
        ];
    }
} else {
    // استخدام القيم الافتراضية (في حالة عدم وجود جدول approval_stages)
    $defaultStages = [
        ['key' => 'draft', 'name' => 'مسودة', 'color' => 'secondary', 'icon' => 'fas fa-edit'],
        ['key' => 'technical_support', 'name' => 'الدعم الفني', 'color' => 'primary', 'icon' => 'fas fa-tools'],
        ['key' => 'construction', 'name' => 'الإنشاءات', 'color' => 'warning', 'icon' => 'fas fa-building'],
        ['key' => 'department_manager', 'name' => 'مدير القسم', 'color' => 'info', 'icon' => 'fas fa-user-tie'],
        ['key' => 'administration_manager', 'name' => 'مدير الإدارة', 'color' => 'secondary', 'icon' => 'fas fa-user-cog'],
        ['key' => 'taif_finance', 'name' => 'مالية الطائف', 'color' => 'success', 'icon' => 'fas fa-university'],
        ['key' => 'disbursed', 'name' => 'تم الصرف', 'color' => 'success', 'icon' => 'fas fa-money-bill-wave']
    ];

    foreach ($defaultStages as $stage) {
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
/* رمز الريال السعودي */
.sar-icon {
    display: inline-block;
    width: 14px;
    height: 14px;
    margin-left: 3px;
    vertical-align: middle;
}

.sar-icon-lg {
    display: inline-block;
    width: 18px;
    height: 18px;
    margin-left: 4px;
    vertical-align: middle;
}

.sar-icon svg,
.sar-icon-lg svg {
    width: 100%;
    height: 100%;
}
</style>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-file-alt text-primary me-2"></i>
                المستخلصات الجزئية
            </h1>
            <p class="text-muted mb-0">إدارة المستخلصات الجزئية (PE-YYYY-XXX) - بدون غرامات</p>
        </div>
        <div>
            <a href="../index.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left me-1"></i>
                العودة للرئيسية
            </a>
            <div class="btn-group me-2" role="group">
                <a href="export.php" class="btn btn-success">
                    <i class="fas fa-download me-1"></i>
                    تصدير
                </a>
                <a href="import.php" class="btn btn-info">
                    <i class="fas fa-upload me-1"></i>
                    استيراد
                </a>
                <a href="update-sap-entry-number.php" class="btn btn-warning">
                    <i class="fas fa-file-import me-1"></i>
                    تحديث SAP
                </a>
            </div>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                مستخلص جزئي جديد
            </a>
        </div>
    </div>

    <!-- Statistics Cards - Dynamic -->
    <div class="row mb-4">
        <!-- إجمالي المستخلصات -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">إجمالي المستخلصات</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total']; ?> مستخلص</div>

                            <!-- المبلغ الصافي -->
                            <div class="mt-2">
                                <div class="text-xs text-muted">
                                    <i class="fas fa-coins fa-sm"></i>
                                    صافي: <span class="font-weight-bold text-primary"><?php echo number_format($stats['total_net_amount'] ?? 0, 0); ?></span>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- بطاقات مراحل الاعتماد الديناميكية -->
        <?php foreach ($approvalStages as $stage): ?>
            <?php
            $stageKey = $stage['key'];
            $count = $stats[$stageKey] ?? 0;
            $netAmount = $stats[$stageKey . '_net_amount'] ?? 0;

            // إخفاء البطاقات التي لا تحتوي على مستخلصات
            if ($count == 0) continue;
            ?>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card border-left-<?php echo $stage['color']; ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?php echo $stage['color']; ?> text-uppercase mb-1">
                                    <?php echo $stage['name']; ?>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count; ?> مستخلص</div>

                                <!-- المبلغ الصافي -->
                                <div class="mt-2">
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-coins fa-sm"></i>
                                        صافي: <span class="font-weight-bold text-primary"><?php echo number_format($netAmount, 0); ?></span>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="<?php echo $stage['icon']; ?> fa-2x text-<?php echo $stage['color']; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- بطاقات الأقسام الديناميكية -->
    <div class="row mb-4">
        <?php foreach ($departments as $department): ?>
            <?php
            $deptKey = $department['key'];
            $count = $stats[$deptKey . '_count'] ?? 0;
            $netAmount = $stats[$deptKey . '_net_amount'] ?? 0;

            // إخفاء البطاقات التي لا تحتوي على مستخلصات
            if ($count == 0) continue;
            ?>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="card border-left-<?php echo $department['color']; ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?php echo $department['color']; ?> text-uppercase mb-1">
                                    <?php echo $department['name']; ?>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $count; ?> مستخلص</div>

                                <!-- المبلغ الصافي -->
                                <div class="mt-2">
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-coins fa-sm"></i>
                                        صافي: <span class="font-weight-bold text-primary"><?php echo number_format($netAmount, 0); ?></span>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="<?php echo $department['icon']; ?> fa-2x text-<?php echo $department['color']; ?>"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Financial Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-left-success shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                إجمالي المبالغ الصافية
                            </div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_net_amount'] ?? 0, 2); ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-info shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                متوسط قيمة المستخلص
                            </div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['average_amount'] ?? 0, 2); ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-primary shadow">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                نسبة الإنجاز
                            </div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $completionRate = $stats['total'] > 0 ? round(($stats['disbursed'] / $stats['total']) * 100, 1) : 0;
                                echo $completionRate; ?>%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-percentage fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter me-2"></i>
                فلترة المستخلصات الجزئية
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label for="approvalStageFilter" class="form-label">
                        مرحلة الاعتماد
                        <small class="text-muted">(يمكن اختيار أكثر من مرحلة)</small>
                    </label>
                    <select class="form-select" id="approvalStageFilter" multiple>
                        <?php foreach ($approvalStages as $stage): ?>
                            <option value="<?php echo htmlspecialchars($stage['key']); ?>">
                                <?php echo htmlspecialchars($stage['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="departmentFilter" class="form-label">القسم</label>
                    <select class="form-select" id="departmentFilter">
                        <option value="">جميع الأقسام</option>
                        <option value="connections">التوصيلات</option>
                        <option value="projects">المشاريع</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="finalExtractFilter" class="form-label">نهائي</label>
                    <select class="form-select" id="finalExtractFilter">
                        <option value="">الكل</option>
                        <option value="yes">نعم (✓)</option>
                        <option value="yes-warning">نعم - كان سالب (⚠ أخضر)</option>
                        <option value="no">لا (✗)</option>
                        <option value="warning">قيمة سالبة (⚠ أصفر)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="dateFromFilter" class="form-label">من تاريخ</label>
                    <input type="date" class="form-control" id="dateFromFilter">
                </div>
                <div class="col-md-2">
                    <label for="dateToFilter" class="form-label">إلى تاريخ</label>
                    <input type="date" class="form-control" id="dateToFilter">
                </div>
            </div>
        </div>
    </div>

    <!-- Extracts Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>
                قائمة المستخلصات الجزئية
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="extractsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم PO</th>
                            <th>رقم الفاتورة</th>
                            <th>القسم</th>
                            <th>تاريخ المستخلص</th>
                            <th>المبلغ الصافي</th>
                            <th>قيمة النهائي</th>
                            <th>أوامر العمل</th>
                            <th>شهادات الإنجاز</th>
                            <th>التخريد</th>
                            <th>نهائي</th>
                            <th>مرحلة الاعتماد</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extracts as $extract):
                            // حساب حالة اكتمال شهادات الإنجاز
                            $certificatesComplete = ($extract['work_orders_count'] > 0 && $extract['confirmed_certificates'] == $extract['work_orders_count']);
                            $certificatesRowClass = $certificatesComplete ? 'table-success' : '';

                            // حساب حالة التخريد
                            $demolitionComplete = ($extract['work_orders_count'] > 0 && $extract['pending_demolition'] == 0);

                            // تحديد القسم
                            $departmentName = '';
                            switch($extract['department']) {
                                case 'connections':
                                    $departmentName = 'التوصيلات';
                                    break;
                                case 'projects':
                                    $departmentName = 'المشاريع';
                                    break;
                                default:
                                    $departmentName = 'غير محدد';
                            }
                        ?>
                        <tr class="<?php echo $certificatesRowClass; ?>">
                            <td>
                                <span class="badge bg-primary extract-number"><?php echo htmlspecialchars($extract['extract_number']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($extract['po_number'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($extract['po_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($extract['invoice_number'] ?? 'لا يوجد'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $extract['department'] === 'connections' ? 'info' : 'warning'; ?> department-badge">
                                    <?php echo $departmentName; ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($extract['extract_date'])); ?></td>
                            <td>
                                <strong><?php echo number_format($extract['net_amount'], 2); ?></strong>
                                <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                            </td>
                            <td>
                                <?php if ($extract['has_final_extract']): ?>
                                    <!-- قيمة فعلية من المستخلص النهائي -->
                                    <strong class="text-success"><?php echo number_format($extract['expected_final_net_amount'], 2); ?></strong>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    <i class="fas fa-check-circle text-success ms-1" title="قيمة فعلية"></i>
                                <?php elseif ($extract['expected_final_net_amount'] > 0): ?>
                                    <!-- قيمة متوقعة -->
                                    <strong class="text-primary"><?php echo number_format($extract['expected_final_net_amount'], 2); ?></strong>
                                    <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    <i class="fas fa-calculator text-primary ms-1" title="قيمة متوقعة"></i>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info work-orders-count"><?php echo $extract['work_orders_count']; ?> أوامر</span>
                            </td>
                            <td>
                                <?php if ($extract['work_orders_count'] > 0): ?>
                                    <div class="completion-status justify-content-center">
                                        <span class="badge bg-<?php echo $certificatesComplete ? 'success' : 'warning'; ?> badge-completion">
                                            <?php echo $extract['confirmed_certificates']; ?>/<?php echo $extract['work_orders_count']; ?>
                                        </span>
                                        <?php if ($certificatesComplete): ?>
                                            <i class="fas fa-check-circle text-success" title="جميع الشهادات مؤكدة"></i>
                                        <?php else: ?>
                                            <i class="fas fa-clock text-warning" title="في انتظار تأكيد الشهادات"></i>
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
                            <td class="text-center">
                                <?php if ($extract['has_final_extract'] && $extract['negative_value_count'] > 0): ?>
                                    <!-- دخل في مستخلص نهائي وكان به قيمة سالبة -->
                                    <i class="fas fa-exclamation-triangle text-success fa-lg"
                                       title="دخل في مستخلص نهائي للجزئية (كان يحتوي على قيمة سالبة في <?= $extract['negative_value_count'] ?> أمر عمل)"
                                       style="cursor: help;"></i>
                                <?php elseif ($extract['has_final_extract']): ?>
                                    <!-- دخل في مستخلص نهائي بدون قيمة سالبة -->
                                    <i class="fas fa-check-circle text-success fa-lg" title="دخل في مستخلص نهائي للجزئية"></i>
                                <?php elseif ($extract['negative_value_count'] > 0): ?>
                                    <!-- يوجد قيمة سالبة - تعذر إنشاء مستخلص نهائي -->
                                    <i class="fas fa-exclamation-triangle text-warning fa-lg"
                                       title="تعذر إنشاء مستخلص نهائي بسبب وجود قيمة سالبة في (<?= $extract['negative_value_count'] ?>) أمر عمل"
                                       style="cursor: help;"></i>
                                <?php else: ?>
                                    <!-- لم يدخل في مستخلص نهائي -->
                                    <i class="fas fa-times-circle text-danger fa-lg" title="لم يدخل في مستخلص نهائي للجزئية"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="form-select form-select-sm approval-stage-select approval-stage-<?= $extract['approval_stage'] ?? 'draft' ?>"
                                        data-extract-id="<?= $extract['id'] ?>"
                                        onchange="updateApprovalStage(<?= $extract['id'] ?>, this.value, this)">
                                    <?php foreach ($approvalStages as $stage): ?>
                                        <?php
                                        // التعامل مع المستخلصات القديمة التي لها approval_stage = null
                                        $isSelected = false;
                                        if ($extract['approval_stage'] === null && $stage['key'] === 'draft') {
                                            $isSelected = true;
                                        } elseif ($extract['approval_stage'] === $stage['key']) {
                                            $isSelected = true;
                                        }
                                        ?>
                                        <option value="<?= htmlspecialchars($stage['key']) ?>"
                                                <?= $isSelected ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($stage['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewExtract(<?php echo $extract['id']; ?>)" title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="export-invoice.php?id=<?php echo $extract['id']; ?>" class="btn btn-sm btn-outline-success" target="_blank" title="تصدير كفاتورة ضريبية">
                                        <i class="fas fa-file-excel"></i>
                                    </a>
                                    <?php if ($extract['approval_stage'] === 'draft' || $extract['approval_stage'] === null): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="editExtract(<?php echo $extract['id']; ?>)" title="تعديل">
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

<!-- إضافة CSS مخصص -->
<style>
/* تحسين مظهر Select2 */
.select2-container--default .select2-selection--multiple {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    min-height: 38px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color: #0d6efd;
    border: none;
    color: white;
    padding: 3px 8px;
    margin: 3px;
    border-radius: 4px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    color: white;
    margin-left: 5px;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #ffcccc;
}

.select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.select2-dropdown {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #0d6efd;
}

.table-success {
    background-color: #d1e7dd !important;
}

.badge-completion {
    font-size: 0.75em;
    padding: 0.25em 0.5em;
}

.completion-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.completion-status .badge {
    min-width: 50px;
    text-align: center;
}

.demolition-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.demolition-status .badge {
    min-width: 60px;
    text-align: center;
}

.department-badge {
    font-weight: 500;
}

.extract-number {
    font-weight: bold;
    font-size: 0.9em;
}

.work-orders-count {
    font-weight: 500;
}

.approval-stage {
    font-size: 0.8em;
    font-weight: 500;
}

/* تحسين مظهر الجدول */
.table th {
    background-color: #f8f9fa;
    border-top: 2px solid #dee2e6;
    font-weight: 600;
    font-size: 0.9em;
    text-align: center;
    vertical-align: middle;
}

.table td {
    vertical-align: middle;
    text-align: center;
    font-size: 0.9em;
}

/* تأثير الظل عند تمرير المؤشر على الصف */
.table tbody tr {
    transition: all 0.3s ease;
    cursor: pointer;
}

.table tbody tr:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
    position: relative;
    z-index: 10;
}

.table tbody tr:hover td {
    background-color: #e3f2fd !important;
    transition: background-color 0.3s ease;
}

/* الحفاظ على لون الصفوف الخضراء عند التمرير */
.table tbody tr.table-success:hover td {
    background-color: #d4edda !important;
}

/* تحسين الأيقونات */
.fas.fa-check-circle {
    font-size: 1.1em;
}

.fas.fa-clock,
.fas.fa-exclamation-triangle {
    font-size: 1em;
}

/* أيقونات عمود النهائي */
.fas.fa-check-circle.fa-lg {
    font-size: 1.5em;
    cursor: help;
}

.fas.fa-times-circle.fa-lg {
    font-size: 1.5em;
    cursor: help;
}

.fas.fa-exclamation-triangle.fa-lg {
    font-size: 1.5em;
    cursor: help;
    animation: pulse-warning 2s infinite;
}

/* تأثير نبض للتحذير */
@keyframes pulse-warning {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

/* تحسين الألوان */
.text-success {
    color: #198754 !important;
}

.text-warning {
    color: #fd7e14 !important;
}

.text-danger {
    color: #dc3545 !important;
}

/* تنسيق مراحل الاعتماد */
.approval-stage-select {
    border: 2px solid;
    font-weight: 600;
    font-size: 0.85em;
    min-width: 140px;
}

<?php
// إنشاء CSS classes ديناميكية لمراحل الاعتماد
$bootstrapColors = [
    'primary' => '#0d6efd',
    'secondary' => '#6c757d',
    'success' => '#198754',
    'danger' => '#dc3545',
    'warning' => '#fd7e14',
    'info' => '#0dcaf0',
    'dark' => '#212529'
];

foreach ($approvalStages as $stage):
    $color = $bootstrapColors[$stage['color']] ?? '#6c757d';
    $textColor = in_array($stage['color'], ['warning', 'info']) ? 'black' : 'white';
?>
.approval-stage-<?= $stage['key'] ?> {
    background-color: <?= $color ?>;
    color: <?= $textColor ?>;
    border-color: <?= $color ?>;
}
<?php endforeach; ?>

/* تأثيرات التحديث المباشر */
.updating-approval {
    background-color: #fff3cd !important;
    border-color: #ffc107 !important;
    position: relative;
}

.updating-approval::after {
    content: '';
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid #ffc107;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.approval-updated {
    background-color: #d1e7dd !important;
    border-color: #28a745 !important;
    transition: all 0.3s ease;
}

.approval-error {
    background-color: #f8d7da !important;
    border-color: #dc3545 !important;
    transition: all 0.3s ease;
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}
</style>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../../includes/layout.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

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

    // التحقق من عدم تهيئة DataTable مسبقاً
    if (!$.fn.DataTable.isDataTable('#extractsTable')) {
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
    } else {
        console.log('DataTable already initialized');
    }

    // Filter functionality
    $('#approvalStageFilter, #departmentFilter, #finalExtractFilter, #dateFromFilter, #dateToFilter').on('change', function() {
        var table = $('#extractsTable').DataTable();

        // إزالة جميع الفلاتر المخصصة القديمة
        $.fn.dataTable.ext.search = [];

        // الحصول على قيم الفلاتر
        var approvalStages = $('#approvalStageFilter').val(); // مصفوفة من المراحل المختارة
        var department = $('#departmentFilter').val();
        var finalExtract = $('#finalExtractFilter').val();
        var dateFrom = $('#dateFromFilter').val();
        var dateTo = $('#dateToFilter').val();

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

                // فلتر القسم
                if (department) {
                    var departmentText = department === 'connections' ? 'التوصيلات' : 'المشاريع';
                    if (data[3].indexOf(departmentText) === -1) {
                        return false;
                    }
                }

                // فلتر النهائي (العمود 10 بعد إضافة عمود "قيمة النهائي")
                if (finalExtract) {
                    var hasFinalCheckIcon = $(row).find('td:eq(10) .fa-check-circle.text-success').length > 0;
                    var hasWarningGreenIcon = $(row).find('td:eq(10) .fa-exclamation-triangle.text-success').length > 0;
                    var hasWarningYellowIcon = $(row).find('td:eq(10) .fa-exclamation-triangle.text-warning').length > 0;
                    var hasNoIcon = $(row).find('td:eq(10) .fa-times-circle.text-danger').length > 0;

                    if (finalExtract === 'yes' && !hasFinalCheckIcon) {
                        return false; // فقط علامة صح خضراء
                    }
                    if (finalExtract === 'yes-warning' && !hasWarningGreenIcon) {
                        return false; // فقط علامة تحذير خضراء
                    }
                    if (finalExtract === 'no' && !hasNoIcon) {
                        return false; // فقط علامة خطأ حمراء
                    }
                    if (finalExtract === 'warning' && !hasWarningYellowIcon) {
                        return false; // فقط علامة تحذير صفراء
                    }
                }

                // فلتر التاريخ
                if (dateFrom || dateTo) {
                    var dateStr = data[4]; // عمود التاريخ (تغير من 3 إلى 4 بعد إضافة عمود قيمة النهائي)
                    if (dateStr) {
                        var date = new Date(dateStr);
                        var min = dateFrom ? new Date(dateFrom) : null;
                        var max = dateTo ? new Date(dateTo) : null;

                        if (min && date < min) return false;
                        if (max && date > max) return false;
                    }
                }

                return true;
            }
        );

        table.draw();
    });

    // تطبيق الألوان الأولية لمراحل الاعتماد
    applyApprovalStageColors();
});

// دالة تطبيق ألوان مراحل الاعتماد
function applyApprovalStageColors() {
    $('.approval-stage-select').each(function() {
        var value = $(this).val();
        // إزالة جميع classes مراحل الاعتماد
        var classesToRemove = '';
        <?php foreach ($approvalStages as $stage): ?>
        classesToRemove += 'approval-stage-<?= $stage['key'] ?> ';
        <?php endforeach; ?>
        $(this).removeClass(classesToRemove.trim());
        $(this).addClass('approval-stage-' + value);
    });
}

function viewExtract(id) {
    window.location.href = 'view.php?id=' + id;
}

function editExtract(id) {
    window.location.href = 'create.php?id=' + id;
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
    console.log('Updating approval stage:', newStage, 'for extract:', extractId);

    // إظهار مؤشر التحميل
    const originalValue = $(element).val();
    $(element).prop('disabled', true);

    // إضافة مؤشر بصري للتحديث
    $(element).addClass('updating-approval');

    // إظهار تنبيه التحديث
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    Toast.fire({
        icon: 'info',
        title: 'جاري تحديث مرحلة الاعتماد...'
    });

    $.ajax({
        url: 'update-approval-ajax.php',
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({
            extract_id: extractId,
            approval_stage: newStage
        }),
        success: function(response) {
            console.log('Update response:', response);

            if (response.success) {
                // إظهار رسالة نجاح
                Toast.fire({
                    icon: 'success',
                    title: 'تم تحديث مرحلة الاعتماد بنجاح'
                });

                // تحديث الألوان
                updateApprovalStageColors(element, newStage);

                // إضافة تأثير بصري للنجاح
                $(element).removeClass('updating-approval').addClass('approval-updated');
                setTimeout(function() {
                    $(element).removeClass('approval-updated');
                }, 2000);

            } else {
                // إظهار رسالة خطأ
                Toast.fire({
                    icon: 'error',
                    title: response.message || 'حدث خطأ أثناء التحديث'
                });

                // إرجاع القيمة الأصلية
                $(element).val(originalValue);

                // إضافة تأثير بصري للخطأ
                $(element).removeClass('updating-approval').addClass('approval-error');
                setTimeout(function() {
                    $(element).removeClass('approval-error');
                }, 2000);
            }
        },
        error: function(xhr, status, error) {
            console.error('Update error:', error);

            Toast.fire({
                icon: 'error',
                title: 'حدث خطأ في الاتصال بالخادم'
            });

            // إرجاع القيمة الأصلية
            $(element).val(originalValue);

            // إضافة تأثير بصري للخطأ
            $(element).removeClass('updating-approval').addClass('approval-error');
            setTimeout(function() {
                $(element).removeClass('approval-error');
            }, 2000);
        },
        complete: function() {
            // إعادة تفعيل العنصر
            $(element).prop('disabled', false);
        }
    });
}

// دالة تحديث ألوان مرحلة الاعتماد
function updateApprovalStageColors(element, stage) {
    $(element).removeClass('approval-stage-technical_support approval-stage-construction approval-stage-department_manager approval-stage-administration_manager approval-stage-taif_finance approval-stage-disbursed');
    $(element).addClass('approval-stage-' + stage);
}
</script>
