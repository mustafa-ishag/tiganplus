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
           END) as negative_value_count,
           -- رقم العقد (من أول أمر عمل)
           (SELECT con.contract_number FROM work_orders wo2 
            JOIN contracts con ON wo2.contract_id = con.id 
            JOIN partial_extract_work_orders pewo2 ON wo2.id = pewo2.work_order_id 
            WHERE pewo2.partial_extract_id = pe.id LIMIT 1) as contract_number
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

/* إخفاء صندوق البحث الافتراضي في DataTable */
.dataTables_filter {
    display: none !important;
}

/* تحسينات التصميم للهواتف */
@media (max-width: 767.98px) {
    /* تصغير عنوان الصفحة */
    .page-title {
        font-size: 1.25rem !important;
    }
    
    /* جعل أزرار الإجراءات في الأعلى تتمدد بشكل متناسق */
    .page-header-actions {
        width: 100%;
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem !important;
    }
    .page-header-actions .btn {
        width: 100%;
        padding-left: 0.25rem !important;
        padding-right: 0.25rem !important;
        font-size: 0.8rem;
    }
    /* زر الإضافة يأخذ سطر كامل */
    .page-header-actions .btn-primary {
        grid-column: 1 / -1;
    }
    
    /* تمرير أفقي للبطاقات الإحصائية بدلاً من التكديس العمودي المزعج */
    .stats-row {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 15px;
        scroll-snap-type: x mandatory;
    }
    .stats-row::-webkit-scrollbar {
        height: 5px;
    }
    .stats-row::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    .stats-row::-webkit-scrollbar-thumb {
        background: var(--primary-color, #4338ca);
        border-radius: 4px;
    }
    .stats-row > .col-xl-2 {
        flex: 0 0 75%;
        max-width: 75%;
        scroll-snap-align: center;
        padding-right: 0.5rem;
        padding-left: 0.5rem;
    }
    
    /* تصغير حجم البطاقات المالية */
    .financial-summary-card {
        padding: 1.25rem !important;
    }
    .financial-summary-card .h3 {
        font-size: 1.2rem;
    }
    .financial-summary-card .icon-circle {
        width: 45px !important;
        height: 45px !important;
        font-size: 1.2rem !important;
    }
    
    /* الجدول: منع التفاف النصوص في رأس الجدول */
    #extractsTable th {
        white-space: nowrap;
        font-size: 0.85rem;
    }
    #extractsTable td {
        font-size: 0.85rem;
    }
}

/* End of specific CSS */
</style>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary page-title">
                <i class="fas fa-file-alt text-primary me-2"></i>
                المستخلصات الجزئية
            </h1>
            <p class="text-muted mb-0">إدارة المستخلصات الجزئية (PE-YYYY-XXX) - بدون غرامات</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-start justify-content-lg-end page-header-actions">
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
                <span>مستخلص جزئي جديد</span>
            </a>
        </div>
    </div>

    <!-- Statistics Cards - Dynamic -->
    <div class="row mb-4 flex-nowrap overflow-auto stats-row">
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
                        <span class="fw-bold text-dark"><?php echo number_format($stats['total_net_amount'] ?? 0, 0); ?></span>
                        <span class="sar-icon text-muted" style="width: 10px; height: 10px;"><svg><use href="#sar-symbol"/></svg></span>
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
    <div class="row mb-4 flex-nowrap overflow-auto stats-row">
        <?php foreach ($departments as $department): ?>
            <?php
            $deptKey = $department['key'];
            $count = $stats[$deptKey . '_count'] ?? 0;
            $netAmount = $stats[$deptKey . '_net_amount'] ?? 0;

            // إخفاء البطاقات التي لا تحتوي على مستخلصات
            if ($count == 0) continue;
            
            $colorClass = $department['color'];
            $softBg = 'bg-' . $colorClass . '-soft';
            ?>
            <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                <div class="dash-card h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="text-muted fw-bold mb-1" style="font-size: 0.7rem; line-height: 1.2;"><?php echo htmlspecialchars($department['name']); ?></div>
                            <div class="h4 mb-0 fw-bold text-dark"><?php echo $count; ?></div>
                        </div>
                        <div class="icon-circle <?php echo $softBg; ?>">
                            <i class="<?php echo $department['icon']; ?>"></i>
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

    <!-- Financial Summary -->
    <div class="row mb-4 flex-nowrap overflow-auto stats-row">
        <div class="col-md-4 mb-3">
            <div class="dash-card h-100 p-4 financial-summary-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-2" style="font-size: 0.75rem;">إجمالي المبالغ الصافية</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php echo number_format($stats['total_net_amount'] ?? 0, 2); ?>
                            <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                    <div class="icon-circle bg-success-soft" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="dash-card h-100 p-4 financial-summary-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-2" style="font-size: 0.75rem;">متوسط قيمة المستخلص</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php echo number_format($stats['average_amount'] ?? 0, 2); ?>
                            <span class="sar-icon-lg text-muted"><svg><use href="#sar-symbol"/></svg></span>
                        </div>
                    </div>
                    <div class="icon-circle bg-info-soft" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="dash-card h-100 p-4 financial-summary-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted fw-bold mb-2" style="font-size: 0.75rem;">نسبة الإنجاز (تم الصرف)</div>
                        <div class="h3 mb-0 fw-bold text-dark">
                            <?php
                            $completionRate = $stats['total'] > 0 ? round((($stats['disbursed'] ?? 0) / $stats['total']) * 100, 1) : 0;
                            echo $completionRate; ?>%
                        </div>
                    </div>
                    <div class="icon-circle bg-primary-soft" style="width: 60px; height: 60px; font-size: 1.8rem;">
                        <i class="fas fa-percentage"></i>
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
                            <option value="<?php echo htmlspecialchars($stage['key']); ?>">
                                <?php echo htmlspecialchars($stage['name']); ?>
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
                    <label for="finalExtractFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-check-double me-1"></i>نهائي
                    </label>
                    <select class="form-select form-select-sm" id="finalExtractFilter">
                        <option value="">الكل</option>
                        <option value="yes">نعم (✓)</option>
                        <option value="yes-warning">نعم - كان سالب (⚠ أخضر)</option>
                        <option value="no">لا (✗)</option>
                        <option value="warning">قيمة سالبة (⚠ أصفر)</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="dateFromFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-calendar-alt me-1"></i>من تاريخ
                    </label>
                    <input type="date" class="form-control form-control-sm" id="dateFromFilter">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="dateToFilter" class="form-label small fw-bold mb-2">
                        <i class="fas fa-calendar-alt me-1"></i>إلى تاريخ
                    </label>
                    <input type="date" class="form-control form-control-sm" id="dateToFilter">
                </div>
                <div class="col-lg-1 col-md-2">
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
                    <i class="fas fa-list text-primary me-2"></i>
                    قائمة المستخلصات الجزئية
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
                <table class="table table-hover premium-table" id="extractsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>رقم المستخلص</th>
                            <th>رقم PO</th>
                            <th>رقم الفاتورة</th>
                            <th>القسم</th>
                            <th class="text-nowrap">تاريخ المستخلص</th>
                            <th class="text-nowrap">تاريخ الصرف</th>
                            <th>المبلغ الصافي</th>
                            <th>قيمة النهائي</th>
                            <th>أوامر العمل</th>
                            <th>شهادات الإنجاز</th>
                            <th>التخريد</th>
                            <th>نهائي</th>
                            <th>مرحلة الاعتماد</th>
                            <th class="text-center">الإجراءات</th>
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
                        <tr class="<?php echo $certificatesRowClass; ?>" onclick="handleRowClick(event, <?php echo $extract['id']; ?>)" style="cursor: pointer;" title="انقر لعرض تفاصيل المستخلص">
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
                            <td class="text-nowrap"><?php echo date('Y-m-d', strtotime($extract['extract_date'])); ?></td>
                            <td class="text-nowrap">
                                <?php if (!empty($extract['disbursement_date'])): ?>
                                    <?php echo date('Y-m-d', strtotime($extract['disbursement_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
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
    $('#approvalStageFilter, #departmentFilter, #finalExtractFilter, #dateFromFilter, #dateToFilter').off('change').on('change', function() {
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

    // Collapse Filters Logic
    $('#filtersHeader').off('click').on('click', function() {
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
    $('#resetFilters').off('click').on('click', function() {
        // إعادة تعيين جميع الفلاتر
        $('#approvalStageFilter').val(null).trigger('change');
        $('#departmentFilter').val('');
        $('#finalExtractFilter').val('');
        $('#dateFromFilter').val('');
        $('#dateToFilter').val('');

        // إزالة الفلاتر المخصصة
        $.fn.dataTable.ext.search = [];
        
        // إعادة الرسم
        $('#extractsTable').DataTable().search('').columns().search('').draw();
        
        Swal.fire({
            position: 'top-end',
            icon: 'success',
            title: 'تم إعادة تعيين الفلاتر',
            showConfirmButton: false,
            timer: 1500,
            toast: true
        });
    });

    // ربط مربع البحث المخصص بـ DataTable
    $('#customTableSearch').off('keyup').on('keyup', function() {
        $('#extractsTable').DataTable().search(this.value).draw();
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

// دالة لمعالجة النقر على صف الجدول
function handleRowClick(event, id) {
    // تجاهل النقر إذا كان على القوائم المنسدلة، الأزرار، أو الروابط
    if (event.target.closest('select, button, a')) {
        return;
    }
    // فتح صفحة عرض المستخلص
    window.location.href = 'view.php?id=' + id;
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
