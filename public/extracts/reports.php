<?php
/**
 * صفحة تقارير المستخلصات الشاملة
 * Comprehensive Extracts Reports Page
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_reports')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}

$db = getDB();
$pageTitle = 'تقارير المستخلصات';
$currentPage = 'extracts-reports';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'المستخلصات', 'url' => 'extracts/index.php'],
    ['title' => 'التقارير الشاملة', 'url' => 'extracts/reports.php']
];

// الحصول على الفترة الزمنية من الطلب (افتراضياً: آخر 30 يوم)
$period = $_GET['period'] ?? 'month';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// تحديد الفترة الزمنية
switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        break;
    case 'q1':
    case 'q2':
    case 'q3':
    case 'q4':
        // الأرباع السنوية
        $currentYear = $_GET['year'] ?? date('Y');
        $quarterMap = [
            'q1' => [1, 3],  // يناير - مارس
            'q2' => [4, 6],  // أبريل - يونيو
            'q3' => [7, 9],  // يوليو - سبتمبر
            'q4' => [10, 12] // أكتوبر - ديسمبر
        ];

        $months = $quarterMap[$period];
        $startDate = date('Y-m-01', strtotime("$currentYear-{$months[0]}-01"));
        $endDate = date('Y-m-t', strtotime("$currentYear-{$months[1]}-01"));
        break;
    case 'year':
        $startDate = date('Y-m-01', strtotime('-11 months'));
        $endDate = date('Y-m-d');
        break;
    case 'all_yearly':
    case 'all_monthly':
        // كل الأوقات - من أقدم سجل إلى اليوم
        // سنحدد النطاق الزمني من قاعدة البيانات (extract_date و disbursement_date)
        $minDates = [];

        // جلب أقدم تاريخ إنشاء من كل جدول
        $minPartial = $db->query("SELECT MIN(extract_date) as min_date FROM partial_extracts")->fetch(PDO::FETCH_ASSOC);
        if ($minPartial['min_date']) $minDates[] = $minPartial['min_date'];

        $minFinalRegular = $db->query("SELECT MIN(extract_date) as min_date FROM final_regular_extracts")->fetch(PDO::FETCH_ASSOC);
        if ($minFinalRegular['min_date']) $minDates[] = $minFinalRegular['min_date'];

        $minFinalForPartial = $db->query("SELECT MIN(extract_date) as min_date FROM final_for_partial_extracts")->fetch(PDO::FETCH_ASSOC);
        if ($minFinalForPartial['min_date']) $minDates[] = $minFinalForPartial['min_date'];

        // جلب أقدم تاريخ صرف من كل جدول
        $minPartialDisb = $db->query("SELECT MIN(disbursement_date) as min_date FROM partial_extracts WHERE disbursement_date IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
        if ($minPartialDisb['min_date']) $minDates[] = $minPartialDisb['min_date'];

        $minFinalRegularDisb = $db->query("SELECT MIN(disbursed_date) as min_date FROM final_regular_extracts WHERE disbursed_date IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
        if ($minFinalRegularDisb['min_date']) $minDates[] = $minFinalRegularDisb['min_date'];

        $minFinalForPartialDisb = $db->query("SELECT MIN(disbursement_date) as min_date FROM final_for_partial_extracts WHERE disbursement_date IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
        if ($minFinalForPartialDisb['min_date']) $minDates[] = $minFinalForPartialDisb['min_date'];

        $startDate = !empty($minDates) ? min($minDates) : '2020-01-01';
        $endDate = date('Y-m-d');
        break;
    case 'custom':
        // استخدام التواريخ المخصصة
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
}

// ========== جمع البيانات ==========

// التحقق من وجود حقل department في الجداول
$hasDepartmentField = false;
try {
    $db->query("SELECT department FROM partial_extracts LIMIT 1");
    $hasDepartmentField = true;
} catch (PDOException $e) {
    // حقل department غير موجود
}

// 1. إحصائيات المستخلصات الجزئية
if ($hasDepartmentField) {
    $partialStats = $db->query("
        SELECT
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(tax_amount) as total_tax,
            SUM(net_amount) as total_net,
            approval_stage,
            department
        FROM partial_extracts
        WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY approval_stage, department
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $partialStats = $db->query("
        SELECT
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(tax_amount) as total_tax,
            SUM(net_amount) as total_net,
            approval_stage,
            NULL as department
        FROM partial_extracts
        WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY approval_stage
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// 2. إحصائيات المستخلصات النهائية العادية
if ($hasDepartmentField) {
    $finalRegularStats = $db->query("
        SELECT
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(tax_amount) as total_tax,
            SUM(total_penalty_amount) as total_penalties,
            SUM(net_amount) as total_net,
            approval_stage,
            department
        FROM final_regular_extracts
        WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY approval_stage, department
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $finalRegularStats = $db->query("
        SELECT
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(tax_amount) as total_tax,
            SUM(total_penalty_amount) as total_penalties,
            SUM(net_amount) as total_net,
            approval_stage,
            NULL as department
        FROM final_regular_extracts
        WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY approval_stage
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// 3. إحصائيات المستخلصات النهائية للجزئية
if ($hasDepartmentField) {
    $finalForPartialStats = $db->query("
        SELECT
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(tax_amount) as total_tax,
            SUM(total_penalty_amount) as total_penalties,
            SUM(net_amount) as total_net,
            approval_stage,
            department
        FROM final_for_partial_extracts
        WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY approval_stage, department
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $finalForPartialStats = $db->query("
        SELECT
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(tax_amount) as total_tax,
            SUM(total_penalty_amount) as total_penalties,
            SUM(net_amount) as total_net,
            approval_stage,
            NULL as department
        FROM final_for_partial_extracts
        WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY approval_stage
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// 4. إحصائيات حسب الفرع (ديناميكية من جدول branches)
$branchStats = $db->query("
    SELECT
        b.id as branch_id,
        b.name as branch_name,
        b.code as branch_code,
        'partial' as extract_type,
        COUNT(pe.id) as count,
        COALESCE(SUM(pe.net_amount), 0) as total_net,
        COALESCE(SUM(pe.total_amount), 0) as total_gross
    FROM branches b
    LEFT JOIN partial_extracts pe ON b.id = pe.branch_id
        AND pe.extract_date BETWEEN '$startDate' AND '$endDate'
    WHERE b.status = 'active'
    GROUP BY b.id, b.name, b.code

    UNION ALL

    SELECT
        b.id as branch_id,
        b.name as branch_name,
        b.code as branch_code,
        'final_regular' as extract_type,
        COUNT(fre.id) as count,
        COALESCE(SUM(fre.net_amount), 0) as total_net,
        COALESCE(SUM(fre.total_amount), 0) as total_gross
    FROM branches b
    LEFT JOIN final_regular_extracts fre ON b.id = fre.branch_id
        AND fre.extract_date BETWEEN '$startDate' AND '$endDate'
    WHERE b.status = 'active'
    GROUP BY b.id, b.name, b.code

    UNION ALL

    SELECT
        b.id as branch_id,
        b.name as branch_name,
        b.code as branch_code,
        'final_for_partial' as extract_type,
        COUNT(ffpe.id) as count,
        COALESCE(SUM(ffpe.net_amount), 0) as total_net,
        COALESCE(SUM(ffpe.total_amount), 0) as total_gross
    FROM branches b
    LEFT JOIN final_for_partial_extracts ffpe ON b.id = ffpe.branch_id
        AND ffpe.extract_date BETWEEN '$startDate' AND '$endDate'
    WHERE b.status = 'active'
    GROUP BY b.id, b.name, b.code

    ORDER BY branch_name, extract_type
")->fetchAll(PDO::FETCH_ASSOC);

// 5. إحصائيات حسب القسم
$departmentStats = [
    'connections' => [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0,
        'partial_disbursed' => 0,
        'final_regular_disbursed' => 0,
        'final_for_partial_disbursed' => 0,
        'partial_gross' => 0,
        'final_regular_gross' => 0,
        'final_for_partial_gross' => 0,
        'partial_disbursed_gross' => 0,
        'final_regular_disbursed_gross' => 0,
        'final_for_partial_disbursed_gross' => 0
    ],
    'projects' => [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0,
        'partial_disbursed' => 0,
        'final_regular_disbursed' => 0,
        'final_for_partial_disbursed' => 0,
        'partial_gross' => 0,
        'final_regular_gross' => 0,
        'final_for_partial_gross' => 0,
        'partial_disbursed_gross' => 0,
        'final_regular_disbursed_gross' => 0,
        'final_for_partial_disbursed_gross' => 0
    ]
];

foreach ($partialStats as $stat) {
    if (isset($stat['department']) && $stat['department'] && in_array($stat['department'], ['connections', 'projects'])) {
        $departmentStats[$stat['department']]['partial'] += floatval($stat['total_net'] ?? 0);
        $departmentStats[$stat['department']]['partial_gross'] += floatval($stat['total_amount'] ?? 0);
        // حساب المصروف فقط
        if (isset($stat['approval_stage']) && $stat['approval_stage'] === 'disbursed') {
            $departmentStats[$stat['department']]['partial_disbursed'] += floatval($stat['total_net'] ?? 0);
            $departmentStats[$stat['department']]['partial_disbursed_gross'] += floatval($stat['total_amount'] ?? 0);
        }
    }
}

foreach ($finalRegularStats as $stat) {
    if (isset($stat['department']) && $stat['department'] && in_array($stat['department'], ['connections', 'projects'])) {
        $departmentStats[$stat['department']]['final_regular'] += floatval($stat['total_net'] ?? 0);
        $departmentStats[$stat['department']]['final_regular_gross'] += floatval($stat['total_amount'] ?? 0);
        // حساب المصروف فقط
        if (isset($stat['approval_stage']) && $stat['approval_stage'] === 'disbursed') {
            $departmentStats[$stat['department']]['final_regular_disbursed'] += floatval($stat['total_net'] ?? 0);
            $departmentStats[$stat['department']]['final_regular_disbursed_gross'] += floatval($stat['total_amount'] ?? 0);
        }
    }
}

foreach ($finalForPartialStats as $stat) {
    if (isset($stat['department']) && $stat['department'] && in_array($stat['department'], ['connections', 'projects'])) {
        $departmentStats[$stat['department']]['final_for_partial'] += floatval($stat['total_net'] ?? 0);
        $departmentStats[$stat['department']]['final_for_partial_gross'] += floatval($stat['total_amount'] ?? 0);
        // حساب المصروف فقط
        if (isset($stat['approval_stage']) && $stat['approval_stage'] === 'disbursed') {
            $departmentStats[$stat['department']]['final_for_partial_disbursed'] += floatval($stat['total_net'] ?? 0);
            $departmentStats[$stat['department']]['final_for_partial_disbursed_gross'] += floatval($stat['total_amount'] ?? 0);
        }
    }
}

// 6. حساب الإجماليات (الصافي والإجمالي)
$totalPartialNet = 0;
$totalPartialGross = 0;
foreach ($partialStats as $stat) {
    $totalPartialNet += floatval($stat['total_net'] ?? 0);
    $totalPartialGross += floatval($stat['total_amount'] ?? 0);
}

$totalFinalRegularNet = 0;
$totalFinalRegularGross = 0;
foreach ($finalRegularStats as $stat) {
    $totalFinalRegularNet += floatval($stat['total_net'] ?? 0);
    $totalFinalRegularGross += floatval($stat['total_amount'] ?? 0);
}

$totalFinalForPartialNet = 0;
$totalFinalForPartialGross = 0;
foreach ($finalForPartialStats as $stat) {
    $totalFinalForPartialNet += floatval($stat['total_net'] ?? 0);
    $totalFinalForPartialGross += floatval($stat['total_amount'] ?? 0);
}

$grandTotalNet = $totalPartialNet + $totalFinalRegularNet + $totalFinalForPartialNet;
$grandTotalGross = $totalPartialGross + $totalFinalRegularGross + $totalFinalForPartialGross;

// للتوافق مع الكود القديم
$totalPartial = $totalPartialNet;
$totalFinalRegular = $totalFinalRegularNet;
$totalFinalForPartial = $totalFinalForPartialNet;
$grandTotal = $grandTotalNet;

$totalPenalties = 0;
foreach ($finalRegularStats as $stat) {
    $totalPenalties += floatval($stat['total_penalties'] ?? 0);
}
foreach ($finalForPartialStats as $stat) {
    $totalPenalties += floatval($stat['total_penalties'] ?? 0);
}

// 7. جلب مراحل الاعتماد من قاعدة البيانات
$approvalStagesFromDB = [];
$stageNames = [];
$stageColors = [];
$stageIcons = [
    'draft' => 'fas fa-edit',
    'technical_support' => 'fas fa-tools',
    'construction' => 'fas fa-hard-hat',
    'department_manager' => 'fas fa-user-tie',
    'administration_manager' => 'fas fa-crown',
    'finance' => 'fas fa-coins',
    'taif_finance' => 'fas fa-university',
    'disbursed' => 'fas fa-check-circle'
];

try {
    $approvalStagesFromDB = $db->query("
        SELECT stage_key, stage_name, stage_color, stage_order, is_active
        FROM approval_stages
        WHERE is_active = 1
        ORDER BY stage_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($approvalStagesFromDB as $stage) {
        $stageNames[$stage['stage_key']] = $stage['stage_name'];
        $stageColors[$stage['stage_key']] = $stage['stage_color'];
    }
} catch (Exception $e) {
    // في حالة عدم وجود جدول approval_stages، استخدم القيم الافتراضية
    $stageNames = [
        'technical_support' => 'الدعم الفني',
        'construction' => 'التنفيذ',
        'department_manager' => 'مدير القسم',
        'administration_manager' => 'مدير الإدارة',
        'finance' => 'المالية',
        'disbursed' => 'مصروف'
    ];
    $stageColors = [
        'technical_support' => 'primary',
        'construction' => 'warning',
        'department_manager' => 'info',
        'administration_manager' => 'secondary',
        'finance' => 'success',
        'disbursed' => 'success'
    ];
}

// إحصائيات المستخلصات المتوقفة على التخريد
$pendingDemolitionStats = [
    'final_regular' => [
        'count' => 0,
        'amount' => 0,
        'amount_gross' => 0,
        'extracts' => []
    ],
    'final_for_partial' => [
        'count' => 0,
        'amount' => 0,
        'amount_gross' => 0,
        'extracts' => []
    ]
];

// جلب المستخلصات النهائية العادية المتوقفة على التخريد
$pendingDemolitionFinalRegular = $db->query("
    SELECT fre.id, fre.extract_number, fre.net_amount, fre.total_amount, fre.approval_stage,
           COUNT(DISTINCT frewo.id) as total_work_orders,
           COUNT(DISTINCT CASE WHEN (woa.status = 'not_applicable' OR woa.status = 'attached') AND woa.form_type = 'demolition_form' THEN frewo.id END) as completed_demolition,
           COUNT(DISTINCT CASE WHEN woa.status = 'not_attached' AND woa.form_type = 'demolition_form' THEN frewo.id END) as pending_demolition
    FROM final_regular_extracts fre
    LEFT JOIN final_regular_extract_work_orders frewo ON fre.id = frewo.final_regular_extract_id
    LEFT JOIN work_orders wo ON frewo.work_order_id = wo.id
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id AND woa.form_type = 'demolition_form'
    WHERE fre.extract_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY fre.id, fre.extract_number, fre.net_amount, fre.total_amount, fre.approval_stage
    HAVING pending_demolition > 0
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendingDemolitionFinalRegular as $extract) {
    $pendingDemolitionStats['final_regular']['count']++;
    $pendingDemolitionStats['final_regular']['amount'] += floatval($extract['net_amount']);
    $pendingDemolitionStats['final_regular']['amount_gross'] += floatval($extract['total_amount']);
    $pendingDemolitionStats['final_regular']['extracts'][] = $extract;
}

// جلب المستخلصات النهائية للجزئية المتوقفة على التخريد
$pendingDemolitionFinalForPartial = $db->query("
    SELECT ffpe.id, ffpe.extract_number, ffpe.net_amount, ffpe.total_amount, ffpe.approval_stage,
           COUNT(DISTINCT ffpewo.id) as total_work_orders,
           COUNT(DISTINCT CASE WHEN (woa.status = 'not_applicable' OR woa.status = 'attached') AND woa.form_type = 'demolition_form' THEN ffpewo.id END) as completed_demolition,
           COUNT(DISTINCT CASE WHEN woa.status = 'not_attached' AND woa.form_type = 'demolition_form' THEN ffpewo.id END) as pending_demolition
    FROM final_for_partial_extracts ffpe
    LEFT JOIN final_for_partial_extract_work_orders ffpewo ON ffpe.id = ffpewo.final_for_partial_extract_id
    LEFT JOIN work_orders wo ON ffpewo.work_order_id = wo.id
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id AND woa.form_type = 'demolition_form'
    WHERE ffpe.extract_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY ffpe.id, ffpe.extract_number, ffpe.net_amount, ffpe.total_amount, ffpe.approval_stage
    HAVING pending_demolition > 0
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($pendingDemolitionFinalForPartial as $extract) {
    $pendingDemolitionStats['final_for_partial']['count']++;
    $pendingDemolitionStats['final_for_partial']['amount'] += floatval($extract['net_amount']);
    $pendingDemolitionStats['final_for_partial']['amount_gross'] += floatval($extract['total_amount']);
    $pendingDemolitionStats['final_for_partial']['extracts'][] = $extract;
}

// إحصائيات حسب مراحل الاعتماد (مع خصم المستخلصات المتوقفة على التخريد)
$stageStats = [];
$stageStatsGross = [];
foreach ($stageNames as $stageKey => $stageName) {
    $stageStats[$stageKey] = [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0,
        'count' => 0,
        'partial_count' => 0,
        'final_regular_count' => 0,
        'final_for_partial_count' => 0
    ];
    $stageStatsGross[$stageKey] = [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0
    ];
}

foreach ($partialStats as $stat) {
    if (isset($stat['approval_stage']) && $stat['approval_stage'] && isset($stageStats[$stat['approval_stage']])) {
        $stageStats[$stat['approval_stage']]['partial'] += floatval($stat['total_net'] ?? 0);
        $stageStatsGross[$stat['approval_stage']]['partial'] += floatval($stat['total_amount'] ?? 0);
        $stageStats[$stat['approval_stage']]['partial_count'] += intval($stat['total_count'] ?? 0);
        $stageStats[$stat['approval_stage']]['count'] += intval($stat['total_count'] ?? 0);
    }
}

foreach ($finalRegularStats as $stat) {
    if (isset($stat['approval_stage']) && $stat['approval_stage'] && isset($stageStats[$stat['approval_stage']])) {
        $stageStats[$stat['approval_stage']]['final_regular'] += floatval($stat['total_net'] ?? 0);
        $stageStatsGross[$stat['approval_stage']]['final_regular'] += floatval($stat['total_amount'] ?? 0);
        $stageStats[$stat['approval_stage']]['final_regular_count'] += intval($stat['total_count'] ?? 0);
        $stageStats[$stat['approval_stage']]['count'] += intval($stat['total_count'] ?? 0);
    }
}

foreach ($finalForPartialStats as $stat) {
    if (isset($stat['approval_stage']) && $stat['approval_stage'] && isset($stageStats[$stat['approval_stage']])) {
        $stageStats[$stat['approval_stage']]['final_for_partial'] += floatval($stat['total_net'] ?? 0);
        $stageStatsGross[$stat['approval_stage']]['final_for_partial'] += floatval($stat['total_amount'] ?? 0);
        $stageStats[$stat['approval_stage']]['final_for_partial_count'] += intval($stat['total_count'] ?? 0);
        $stageStats[$stat['approval_stage']]['count'] += intval($stat['total_count'] ?? 0);
    }
}

// خصم المستخلصات المتوقفة على التخريد من إحصائيات مراحل الاعتماد
foreach ($pendingDemolitionFinalRegular as $extract) {
    $stage = $extract['approval_stage'];
    if (isset($stageStats[$stage])) {
        $stageStats[$stage]['final_regular'] -= floatval($extract['net_amount']);
        $stageStats[$stage]['final_regular_count']--;
        $stageStats[$stage]['count']--;
    }
}

foreach ($pendingDemolitionFinalForPartial as $extract) {
    $stage = $extract['approval_stage'];
    if (isset($stageStats[$stage])) {
        $stageStats[$stage]['final_for_partial'] -= floatval($extract['net_amount']);
        $stageStats[$stage]['final_for_partial_count']--;
        $stageStats[$stage]['count']--;
    }
}

// 8. البيانات الأسبوعية/الشهرية/السنوية للرسوم البيانية
$timeSeriesData = [];
$timeSeriesLabels = [];

if ($period === 'week') {
    // بيانات يومية لآخر 7 أيام
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayNumber = date('j', strtotime("-$i days"));
        $timeSeriesData[$date] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $timeSeriesLabels[$date] = "يوم " . $dayNumber;
    }
} elseif ($period === 'month') {
    // بيانات أسبوعية لآخر 30 يوم
    for ($i = 4; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
        $weekNumber = 5 - $i;
        $timeSeriesData[$weekStart] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $timeSeriesLabels[$weekStart] = "أسبوع " . $weekNumber;
    }
} elseif (in_array($period, ['q1', 'q2', 'q3', 'q4'])) {
    // بيانات شهرية للربع المحدد (3 أشهر)
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    $currentYear = $_GET['year'] ?? date('Y');
    $quarterMap = [
        'q1' => [1, 2, 3],
        'q2' => [4, 5, 6],
        'q3' => [7, 8, 9],
        'q4' => [10, 11, 12]
    ];

    $months = $quarterMap[$period];
    foreach ($months as $monthNumber) {
        $month = date('Y-m', strtotime("$currentYear-$monthNumber-01"));
        $timeSeriesData[$month] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $timeSeriesLabels[$month] = $monthNames[$monthNumber] . ' ' . $currentYear;
    }
} elseif ($period === 'year') {
    // بيانات شهرية لآخر 12 شهر
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthNumber = (int)date('n', strtotime("-$i months"));
        $year = date('Y', strtotime("-$i months"));
        $timeSeriesData[$month] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $timeSeriesLabels[$month] = $monthNames[$monthNumber] . ' ' . $year;
    }
} elseif ($period === 'all_yearly') {
    // بيانات سنوية لكل الأوقات - فقط السنوات التي تحتوي على بيانات
    // جلب جميع السنوات الفريدة من البيانات
    $yearsQuery = $db->query("
        SELECT DISTINCT YEAR(extract_date) as year
        FROM (
            SELECT extract_date FROM partial_extracts
            UNION ALL
            SELECT extract_date FROM final_regular_extracts
            UNION ALL
            SELECT extract_date FROM final_for_partial_extracts
        ) as all_extracts
        WHERE extract_date IS NOT NULL
        ORDER BY year ASC
    ");

    $years = $yearsQuery->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($years)) {
        foreach ($years as $year) {
            $yearKey = (string)$year;
            $timeSeriesData[$yearKey] = [
                'partial' => 0,
                'final_regular' => 0,
                'final_for_partial' => 0,
                'partial_gross' => 0,
                'final_regular_gross' => 0,
                'final_for_partial_gross' => 0
            ];
            $timeSeriesLabels[$yearKey] = $year;
        }
    }
} elseif ($period === 'all_monthly') {
    // بيانات شهرية لكل الأوقات - جميع الأشهر من أول مستخلص حتى الآن
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    // جلب جميع الأشهر الفريدة من البيانات
    $monthsQuery = $db->query("
        SELECT DISTINCT DATE_FORMAT(extract_date, '%Y-%m') as month
        FROM (
            SELECT extract_date FROM partial_extracts
            UNION ALL
            SELECT extract_date FROM final_regular_extracts
            UNION ALL
            SELECT extract_date FROM final_for_partial_extracts
        ) as all_extracts
        WHERE extract_date IS NOT NULL
        ORDER BY month ASC
    ");

    $months = $monthsQuery->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($months)) {
        foreach ($months as $month) {
            $timeSeriesData[$month] = [
                'partial' => 0,
                'final_regular' => 0,
                'final_for_partial' => 0,
                'partial_gross' => 0,
                'final_regular_gross' => 0,
                'final_for_partial_gross' => 0
            ];
            $monthNumber = (int)date('n', strtotime($month . '-01'));
            $year = date('Y', strtotime($month . '-01'));
            $timeSeriesLabels[$month] = $monthNames[$monthNumber] . ' ' . $year;
        }
    }
}

// جلب البيانات الفعلية للفترة الزمنية
$partialTimeSeries = $db->query("
    SELECT
        DATE(extract_date) as date,
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY DATE(extract_date)
")->fetchAll(PDO::FETCH_ASSOC);

$finalRegularTimeSeries = $db->query("
    SELECT
        DATE(extract_date) as date,
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM final_regular_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY DATE(extract_date)
")->fetchAll(PDO::FETCH_ASSOC);

$finalForPartialTimeSeries = $db->query("
    SELECT
        DATE(extract_date) as date,
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM final_for_partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    GROUP BY DATE(extract_date)
")->fetchAll(PDO::FETCH_ASSOC);

// دمج البيانات في timeSeriesData
foreach ($partialTimeSeries as $row) {
    if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
        $key = substr($row['date'], 0, 7); // Y-m
    } elseif ($period === 'all_yearly') {
        $key = substr($row['date'], 0, 4); // Y (السنة فقط)
    } else {
        $key = $row['date']; // التاريخ الكامل
    }

    if (isset($timeSeriesData[$key])) {
        $timeSeriesData[$key]['partial'] += floatval($row['total'] ?? 0);
        $timeSeriesData[$key]['partial_gross'] += floatval($row['total_gross'] ?? 0);
    } else {
        // إضافة المفتاح إذا لم يكن موجوداً
        $timeSeriesData[$key] = [
            'partial' => floatval($row['total'] ?? 0),
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => floatval($row['total_gross'] ?? 0),
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
    }
}

foreach ($finalRegularTimeSeries as $row) {
    if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
        $key = substr($row['date'], 0, 7);
    } elseif ($period === 'all_yearly') {
        $key = substr($row['date'], 0, 4);
    } else {
        $key = $row['date'];
    }

    if (isset($timeSeriesData[$key])) {
        $timeSeriesData[$key]['final_regular'] += floatval($row['total'] ?? 0);
        $timeSeriesData[$key]['final_regular_gross'] += floatval($row['total_gross'] ?? 0);
    } else {
        $timeSeriesData[$key] = [
            'partial' => 0,
            'final_regular' => floatval($row['total'] ?? 0),
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => floatval($row['total_gross'] ?? 0),
            'final_for_partial_gross' => 0
        ];
    }
}

foreach ($finalForPartialTimeSeries as $row) {
    if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
        $key = substr($row['date'], 0, 7);
    } elseif ($period === 'all_yearly') {
        $key = substr($row['date'], 0, 4);
    } else {
        $key = $row['date'];
    }

    if (isset($timeSeriesData[$key])) {
        $timeSeriesData[$key]['final_for_partial'] += floatval($row['total'] ?? 0);
        $timeSeriesData[$key]['final_for_partial_gross'] += floatval($row['total_gross'] ?? 0);
    } else {
        $timeSeriesData[$key] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => floatval($row['total'] ?? 0),
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => floatval($row['total_gross'] ?? 0)
        ];
    }
}

// ترتيب البيانات حسب التاريخ
ksort($timeSeriesData);

// 9. بيانات صرف المستخلصات حسب تاريخ الإنشاء ومرحلة الاعتماد (disbursed)
// نستخدم نفس الهيكل الزمني من timeSeriesData
$disbursementTimeSeriesData = [];
$disbursementLabels = [];

if ($period === 'week') {
    // بيانات يومية لآخر 7 أيام
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayNumber = date('j', strtotime("-$i days"));
        $disbursementTimeSeriesData[$date] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $disbursementLabels[$date] = "يوم " . $dayNumber;
    }
} elseif ($period === 'month') {
    // بيانات أسبوعية لآخر 30 يوم
    for ($i = 4; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
        $weekNumber = 5 - $i;
        $disbursementTimeSeriesData[$weekStart] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $disbursementLabels[$weekStart] = "أسبوع " . $weekNumber;
    }
} elseif (in_array($period, ['q1', 'q2', 'q3', 'q4'])) {
    // بيانات شهرية للربع المحدد (3 أشهر)
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    $currentYear = $_GET['year'] ?? date('Y');
    $quarterMap = [
        'q1' => [1, 2, 3],
        'q2' => [4, 5, 6],
        'q3' => [7, 8, 9],
        'q4' => [10, 11, 12]
    ];

    $months = $quarterMap[$period];
    foreach ($months as $monthNumber) {
        $month = date('Y-m', strtotime("$currentYear-$monthNumber-01"));
        $disbursementTimeSeriesData[$month] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $disbursementLabels[$month] = $monthNames[$monthNumber] . ' ' . $currentYear;
    }
} elseif ($period === 'year') {
    // بيانات شهرية لآخر 12 شهر
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthNumber = (int)date('n', strtotime("-$i months"));
        $year = date('Y', strtotime("-$i months"));
        $disbursementTimeSeriesData[$month] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        $disbursementLabels[$month] = $monthNames[$monthNumber] . ' ' . $year;
    }
} elseif ($period === 'all_yearly') {
    // بيانات سنوية لكل الأوقات - فقط السنوات التي تحتوي على مستخلصات مصروفة (حسب تاريخ الصرف)
    $yearsQuery = $db->query("
        SELECT DISTINCT YEAR(disbursement_date) as year
        FROM (
            SELECT disbursement_date FROM partial_extracts WHERE disbursement_date IS NOT NULL AND approval_stage = 'disbursed'
            UNION ALL
            SELECT disbursed_date as disbursement_date FROM final_regular_extracts WHERE disbursed_date IS NOT NULL AND approval_stage = 'disbursed'
            UNION ALL
            SELECT disbursement_date FROM final_for_partial_extracts WHERE disbursement_date IS NOT NULL AND approval_stage = 'disbursed'
        ) as all_disbursements
        ORDER BY year ASC
    ");

    $years = $yearsQuery->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($years)) {
        foreach ($years as $year) {
            $yearKey = (string)$year;
            $disbursementTimeSeriesData[$yearKey] = [
                'partial' => 0,
                'final_regular' => 0,
                'final_for_partial' => 0,
                'partial_gross' => 0,
                'final_regular_gross' => 0,
                'final_for_partial_gross' => 0
            ];
            $disbursementLabels[$yearKey] = $year;
        }
    }
} elseif ($period === 'all_monthly') {
    // بيانات شهرية لكل الأوقات - جميع الأشهر التي تحتوي على مستخلصات مصروفة (حسب تاريخ الصرف)
    $monthNames = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    $monthsQuery = $db->query("
        SELECT DISTINCT DATE_FORMAT(disbursement_date, '%Y-%m') as month
        FROM (
            SELECT disbursement_date FROM partial_extracts WHERE disbursement_date IS NOT NULL AND approval_stage = 'disbursed'
            UNION ALL
            SELECT disbursed_date as disbursement_date FROM final_regular_extracts WHERE disbursed_date IS NOT NULL AND approval_stage = 'disbursed'
            UNION ALL
            SELECT disbursement_date FROM final_for_partial_extracts WHERE disbursement_date IS NOT NULL AND approval_stage = 'disbursed'
        ) as all_disbursements
        ORDER BY month ASC
    ");

    $months = $monthsQuery->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($months)) {
        foreach ($months as $month) {
            $disbursementTimeSeriesData[$month] = [
                'partial' => 0,
                'final_regular' => 0,
                'final_for_partial' => 0,
                'partial_gross' => 0,
                'final_regular_gross' => 0,
                'final_for_partial_gross' => 0
            ];
            $monthNumber = (int)date('n', strtotime($month . '-01'));
            $year = date('Y', strtotime($month . '-01'));
            $disbursementLabels[$month] = $monthNames[$monthNumber] . ' ' . $year;
        }
    }
}

// جلب بيانات المستخلصات المصروفة (approval_stage = 'disbursed') حسب تاريخ الصرف الفعلي
$partialDisbursementSeries = $db->query("
    SELECT
        DATE(disbursement_date) as date,
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM partial_extracts
    WHERE disbursement_date IS NOT NULL
        AND disbursement_date BETWEEN '$startDate' AND '$endDate'
        AND approval_stage = 'disbursed'
    GROUP BY DATE(disbursement_date)
")->fetchAll(PDO::FETCH_ASSOC);

// جلب بيانات المستخلصات النهائية العادية المصروفة حسب تاريخ الصرف الفعلي
$finalRegularDisbursementSeries = $db->query("
    SELECT
        DATE(disbursed_date) as date,
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM final_regular_extracts
    WHERE disbursed_date IS NOT NULL
        AND disbursed_date BETWEEN '$startDate' AND '$endDate'
        AND approval_stage = 'disbursed'
    GROUP BY DATE(disbursed_date)
")->fetchAll(PDO::FETCH_ASSOC);

// جلب بيانات المستخلصات النهائية للجزئية المصروفة حسب تاريخ الصرف الفعلي
$finalForPartialDisbursementSeries = $db->query("
    SELECT
        DATE(disbursement_date) as date,
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM final_for_partial_extracts
    WHERE disbursement_date IS NOT NULL
        AND disbursement_date BETWEEN '$startDate' AND '$endDate'
        AND approval_stage = 'disbursed'
    GROUP BY DATE(disbursement_date)
")->fetchAll(PDO::FETCH_ASSOC);

// جلب المستخلصات المصروفة بدون تاريخ صرف (للسطر الإضافي في الجدول)
$partialDisbursedNoDate = $db->query("
    SELECT
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        AND approval_stage = 'disbursed'
        AND disbursement_date IS NULL
")->fetch(PDO::FETCH_ASSOC);

$finalRegularDisbursedNoDate = $db->query("
    SELECT
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM final_regular_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        AND approval_stage = 'disbursed'
        AND disbursed_date IS NULL
")->fetch(PDO::FETCH_ASSOC);

$finalForPartialDisbursedNoDate = $db->query("
    SELECT
        SUM(net_amount) as total,
        SUM(total_amount) as total_gross
    FROM final_for_partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
        AND approval_stage = 'disbursed'
        AND disbursement_date IS NULL
")->fetch(PDO::FETCH_ASSOC);

// حساب إجمالي المصروف بدون تاريخ صرف
$disbursedNoDatePartial = floatval($partialDisbursedNoDate['total'] ?? 0);
$disbursedNoDateFinalRegular = floatval($finalRegularDisbursedNoDate['total'] ?? 0);
$disbursedNoDateFinalForPartial = floatval($finalForPartialDisbursedNoDate['total'] ?? 0);
$disbursedNoDateTotal = $disbursedNoDatePartial + $disbursedNoDateFinalRegular + $disbursedNoDateFinalForPartial;

$disbursedNoDatePartialGross = floatval($partialDisbursedNoDate['total_gross'] ?? 0);
$disbursedNoDateFinalRegularGross = floatval($finalRegularDisbursedNoDate['total_gross'] ?? 0);
$disbursedNoDateFinalForPartialGross = floatval($finalForPartialDisbursedNoDate['total_gross'] ?? 0);
$disbursedNoDateTotalGross = $disbursedNoDatePartialGross + $disbursedNoDateFinalRegularGross + $disbursedNoDateFinalForPartialGross;

// دمج البيانات في disbursementTimeSeriesData
foreach ($partialDisbursementSeries as $row) {
    if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
        // للفترة السنوية أو الربع أو كل الأوقات (شهري): تجميع حسب الشهر (Y-m)
        $key = substr($row['date'], 0, 7);
    } elseif ($period === 'all_yearly') {
        // لكل الأوقات (سنوي): تجميع حسب السنة (Y)
        $key = substr($row['date'], 0, 4);
    } elseif ($period === 'month') {
        // للفترة الشهرية: تجميع حسب الأسبوع
        $date = strtotime($row['date']);
        $weekStart = date('Y-m-d', strtotime('last monday', $date));
        if (date('N', $date) == 1) {
            $weekStart = date('Y-m-d', $date);
        }
        $key = $weekStart;
    } else {
        // للفترة الأسبوعية: حسب اليوم
        $key = $row['date'];
    }

    if (isset($disbursementTimeSeriesData[$key])) {
        $disbursementTimeSeriesData[$key]['partial'] += floatval($row['total'] ?? 0);
        $disbursementTimeSeriesData[$key]['partial_gross'] += floatval($row['total_gross'] ?? 0);
    } else {
        $disbursementTimeSeriesData[$key] = [
            'partial' => floatval($row['total'] ?? 0),
            'final_regular' => 0,
            'final_for_partial' => 0,
            'partial_gross' => floatval($row['total_gross'] ?? 0),
            'final_regular_gross' => 0,
            'final_for_partial_gross' => 0
        ];
        // إضافة التسمية إذا لم تكن موجودة
        if (!isset($disbursementLabels[$key])) {
            if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
                $monthNames = [
                    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
                ];
                $monthNumber = (int)date('n', strtotime($key . '-01'));
                $year = date('Y', strtotime($key . '-01'));
                $disbursementLabels[$key] = $monthNames[$monthNumber] . ' ' . $year;
            } else {
                $disbursementLabels[$key] = $key;
            }
        }
    }
}

foreach ($finalRegularDisbursementSeries as $row) {
    if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
        $key = substr($row['date'], 0, 7);
    } elseif ($period === 'all_yearly') {
        $key = substr($row['date'], 0, 4);
    } elseif ($period === 'month') {
        $date = strtotime($row['date']);
        $weekStart = date('Y-m-d', strtotime('last monday', $date));
        if (date('N', $date) == 1) {
            $weekStart = date('Y-m-d', $date);
        }
        $key = $weekStart;
    } else {
        $key = $row['date'];
    }

    if (isset($disbursementTimeSeriesData[$key])) {
        $disbursementTimeSeriesData[$key]['final_regular'] += floatval($row['total'] ?? 0);
        $disbursementTimeSeriesData[$key]['final_regular_gross'] += floatval($row['total_gross'] ?? 0);
    } else {
        $disbursementTimeSeriesData[$key] = [
            'partial' => 0,
            'final_regular' => floatval($row['total'] ?? 0),
            'final_for_partial' => 0,
            'partial_gross' => 0,
            'final_regular_gross' => floatval($row['total_gross'] ?? 0),
            'final_for_partial_gross' => 0
        ];
        if (!isset($disbursementLabels[$key])) {
            if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
                $monthNames = [
                    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
                ];
                $monthNumber = (int)date('n', strtotime($key . '-01'));
                $year = date('Y', strtotime($key . '-01'));
                $disbursementLabels[$key] = $monthNames[$monthNumber] . ' ' . $year;
            } else {
                $disbursementLabels[$key] = $key;
            }
        }
    }
}

foreach ($finalForPartialDisbursementSeries as $row) {
    if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
        $key = substr($row['date'], 0, 7);
    } elseif ($period === 'all_yearly') {
        $key = substr($row['date'], 0, 4);
    } elseif ($period === 'month') {
        $date = strtotime($row['date']);
        $weekStart = date('Y-m-d', strtotime('last monday', $date));
        if (date('N', $date) == 1) {
            $weekStart = date('Y-m-d', $date);
        }
        $key = $weekStart;
    } else {
        $key = $row['date'];
    }

    if (isset($disbursementTimeSeriesData[$key])) {
        $disbursementTimeSeriesData[$key]['final_for_partial'] += floatval($row['total'] ?? 0);
        $disbursementTimeSeriesData[$key]['final_for_partial_gross'] += floatval($row['total_gross'] ?? 0);
    } else {
        $disbursementTimeSeriesData[$key] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => floatval($row['total'] ?? 0),
            'partial_gross' => 0,
            'final_regular_gross' => 0,
            'final_for_partial_gross' => floatval($row['total_gross'] ?? 0)
        ];
        if (!isset($disbursementLabels[$key])) {
            if ($period === 'year' || in_array($period, ['q1', 'q2', 'q3', 'q4']) || $period === 'all_monthly') {
                $monthNames = [
                    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
                ];
                $monthNumber = (int)date('n', strtotime($key . '-01'));
                $year = date('Y', strtotime($key . '-01'));
                $disbursementLabels[$key] = $monthNames[$monthNumber] . ' ' . $year;
            } else {
                $disbursementLabels[$key] = $key;
            }
        }
    }
}

// ترتيب البيانات حسب التاريخ
ksort($disbursementTimeSeriesData);

// بدء المحتوى
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

.stats-card {
    border-radius: 10px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    overflow: hidden;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}

.stats-card .card-body {
    padding: 1.5rem;
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.chart-container {
    position: relative;
    height: 400px;
    margin-bottom: 2rem;
}

.period-selector {
    background: white;
    padding: 1rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
}

.badge-stage {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.gradient-primary {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
}

.gradient-success {
    background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
}

.gradient-warning {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.gradient-info {
    background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
}

.gradient-danger {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 3px solid #3498db;
    display: inline-block;
}

.custom-select {
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    padding: 0.5rem 1rem;
}

.custom-select:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
}

/* تنسيق مفتاح التبديل الاحترافي */
.tax-toggle-container {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
    padding: 4px;
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.tax-toggle-container:hover {
    box-shadow: 0 3px 12px rgba(102, 126, 234, 0.5);
    transform: translateY(-1px);
}

.tax-toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 4px 12px;
    border-radius: 50px;
}

.tax-toggle-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #95a5a6;
    transition: all 0.3s ease;
    white-space: nowrap;
    user-select: none;
    cursor: pointer;
}

.tax-toggle-label.active {
    color: #2c3e50;
    font-weight: 700;
}

/* المفتاح نفسه */
.tax-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 22px;
    margin: 0;
}

.tax-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.tax-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 22px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

.tax-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background: white;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

.tax-switch input:checked + .tax-slider {
    background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
}

.tax-switch input:checked + .tax-slider:before {
    transform: translateX(22px);
    box-shadow: 0 1px 6px rgba(102, 126, 234, 0.4);
}

.tax-switch input:focus + .tax-slider {
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1), 0 0 0 2px rgba(102, 126, 234, 0.2);
}

/* تأثير النبض عند التفعيل */
.tax-switch input:checked + .tax-slider:after {
    content: "";
    position: absolute;
    top: 50%;
    right: 6px;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
    opacity: 0.8;
}

.amount-display {
    transition: all 0.3s ease;
}

/* تأثيرات إضافية للشاشات الصغيرة */
@media (max-width: 768px) {
    .tax-toggle-container {
        padding: 3px;
    }

    .tax-toggle-wrapper {
        padding: 3px 10px;
        gap: 6px;
    }

    .tax-toggle-label {
        font-size: 0.75rem;
    }

    .tax-switch {
        width: 40px;
        height: 20px;
    }

    .tax-slider {
        border-radius: 20px;
    }

    .tax-slider:before {
        height: 16px;
        width: 16px;
    }

    .tax-switch input:checked + .tax-slider:before {
        transform: translateX(20px);
    }

    .tax-switch input:checked + .tax-slider:after {
        right: 5px;
        width: 5px;
        height: 5px;
    }
}
</style>

<div class="container-fluid px-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-primary">
                <i class="fas fa-chart-line me-2"></i>
                تقارير المستخلصات الشاملة
            </h1>
            <p class="text-muted mb-0">تقارير تفصيلية لجميع أنواع المستخلصات مع رسوم بيانية احترافية</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <!-- مفتاح التبديل بين شامل ضريبة وبدون ضريبة -->
            <div class="tax-toggle-container">
                <div class="tax-toggle-wrapper">
                    <span class="tax-toggle-label" id="taxLabelLeft">بدون ضريبة</span>
                    <label class="tax-switch">
                        <input type="checkbox" id="amountToggle" autocomplete="off">
                        <span class="tax-slider"></span>
                    </label>
                    <span class="tax-toggle-label active" id="taxLabelRight">شامل ضريبة</span>
                </div>
            </div>
            <button onclick="exportToPDF()" class="btn btn-danger">
                <i class="fas fa-file-pdf me-1"></i>
                تحميل PDF
            </button>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                العودة للرئيسية
            </a>
        </div>
    </div>

    <!-- Period Selector -->
    <div class="period-selector">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">
                    <i class="fas fa-calendar-alt me-1"></i>
                    الفترة الزمنية
                </label>
                <select name="period" id="periodSelect" class="form-select custom-select">
                    <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>آخر أسبوع</option>
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>آخر شهر</option>
                    <optgroup label="الأرباع السنوية">
                        <option value="q1" <?= $period === 'q1' ? 'selected' : '' ?>>الربع الأول (يناير - مارس)</option>
                        <option value="q2" <?= $period === 'q2' ? 'selected' : '' ?>>الربع الثاني (أبريل - يونيو)</option>
                        <option value="q3" <?= $period === 'q3' ? 'selected' : '' ?>>الربع الثالث (يوليو - سبتمبر)</option>
                        <option value="q4" <?= $period === 'q4' ? 'selected' : '' ?>>الربع الرابع (أكتوبر - ديسمبر)</option>
                    </optgroup>
                    <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>آخر سنة</option>
                    <optgroup label="كل الأوقات">
                        <option value="all_yearly" <?= $period === 'all_yearly' ? 'selected' : '' ?>>كل الأوقات (سنوي)</option>
                        <option value="all_monthly" <?= $period === 'all_monthly' ? 'selected' : '' ?>>كل الأوقات (شهري)</option>
                    </optgroup>
                    <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>فترة مخصصة</option>
                </select>
            </div>
            <div class="col-md-2" id="quarterYear" style="display: <?= in_array($period, ['q1', 'q2', 'q3', 'q4']) ? 'block' : 'none' ?>;">
                <label class="form-label fw-bold">السنة</label>
                <select name="year" class="form-select">
                    <?php
                    $currentYear = date('Y');
                    $selectedYear = $_GET['year'] ?? $currentYear;
                    for ($y = $currentYear; $y >= 2020; $y--) {
                        $selected = ($y == $selectedYear) ? 'selected' : '';
                        echo "<option value='$y' $selected>$y</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3" id="customDateStart" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label fw-bold">من تاريخ</label>
                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
            </div>
            <div class="col-md-3" id="customDateEnd" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label fw-bold">إلى تاريخ</label>
                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>
                    عرض التقرير
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="section-title">
                <i class="fas fa-chart-pie me-2"></i>
                الإحصائيات العامة
            </h2>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Total Extracts -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card shadow gradient-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                إجمالي المستخلصات
                            </div>
                            <div class="h3 mb-0 font-weight-bold amount-display"
                                 data-net="<?= number_format($grandTotalNet, 2) ?>"
                                 data-gross="<?= number_format($grandTotalGross, 2) ?>">
                                <?= number_format($grandTotal, 2) ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice-dollar stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Partial Extracts -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card shadow" style="background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                المستخلصات الجزئية
                            </div>
                            <div class="h3 mb-0 font-weight-bold amount-display"
                                 data-net="<?= number_format($totalPartialNet, 2) ?>"
                                 data-gross="<?= number_format($totalPartialGross, 2) ?>">
                                <?= number_format($totalPartial, 2) ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Final Regular Extracts -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card shadow" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                المستخلصات النهائية العادية
                            </div>
                            <div class="h3 mb-0 font-weight-bold amount-display"
                                 data-net="<?= number_format($totalFinalRegularNet, 2) ?>"
                                 data-gross="<?= number_format($totalFinalRegularGross, 2) ?>">
                                <?= number_format($totalFinalRegular, 2) ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Final For Partial Extracts -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stats-card shadow" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                المستخلصات النهائية للجزئية
                            </div>
                            <div class="h3 mb-0 font-weight-bold amount-display"
                                 data-net="<?= number_format($totalFinalForPartialNet, 2) ?>"
                                 data-gross="<?= number_format($totalFinalForPartialGross, 2) ?>">
                                <?= number_format($totalFinalForPartial, 2) ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-contract stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="row mb-4">
        <!-- Total Penalties -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stats-card shadow" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                إجمالي الغرامات
                            </div>
                            <div class="h3 mb-0 font-weight-bold">
                                <?= number_format($totalPenalties, 2) ?>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disbursed Amount -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stats-card shadow" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                المبالغ المصروفة
                            </div>
                            <div class="h3 mb-0 font-weight-bold">
                                <?php
                                // حساب المبالغ المصروفة (من جدول المقارنة + المصروف بدون تاريخ)
                                $disbursedAmountNet = 0;
                                $disbursedAmountGross = 0;

                                // جمع المصروف من جدول المقارنة (حسب تاريخ الصرف)
                                foreach ($disbursementTimeSeriesData as $date => $data) {
                                    $disbursedAmountNet += $data['partial'] + $data['final_regular'] + $data['final_for_partial'];
                                    $disbursedAmountGross += $data['partial_gross'] + $data['final_regular_gross'] + $data['final_for_partial_gross'];
                                }

                                // إضافة المصروف بدون تاريخ صرف
                                $disbursedAmountNet += $disbursedNoDateTotal;
                                $disbursedAmountGross += $disbursedNoDateTotalGross;

                                $disbursedAmount = $disbursedAmountNet;
                                ?>
                                <span class="amount-display" data-net="<?= number_format($disbursedAmountNet, 2) ?>"
                                      data-gross="<?= number_format($disbursedAmountGross, 2) ?>">
                                    <?= number_format($disbursedAmount, 2) ?>
                                </span>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Amount -->
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card stats-card shadow" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                المبالغ المتبقية (غير مصروفة)
                            </div>
                            <div class="h3 mb-0 font-weight-bold">
                                <?php
                                $pendingAmountNet = $grandTotalNet - $disbursedAmountNet;
                                $pendingAmountGross = $grandTotalGross - $disbursedAmountGross;
                                ?>
                                <span class="amount-display" data-net="<?= number_format($pendingAmountNet, 2) ?>"
                                      data-gross="<?= number_format($pendingAmountGross, 2) ?>">
                                    <?= number_format($pendingAmountNet, 2) ?>
                                </span>
                                <span class="sar-icon-lg"><svg><use href="#sar-symbol"/></svg></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hourglass-half stats-icon"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="section-title">
                <i class="fas fa-chart-bar me-2"></i>
                الرسوم البيانية
            </h2>
        </div>
    </div>

    <!-- Time Series Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        تطور المستخلصات عبر الزمن (حسب تاريخ الإنشاء)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="timeSeriesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disbursement Time Series Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        تطور صرف المستخلصات عبر الزمن (المستخلصات المصروفة)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="disbursementTimeSeriesChart"></canvas>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> هذا الرسم يعرض المستخلصات المصروفة (approval_stage = disbursed) حسب تاريخ الصرف الفعلي.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison Chart (Created vs Disbursed) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        مقارنة المستخلصات المُنشأة مقابل المصروفة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> هذا الرسم يقارن بين إجمالي المستخلصات المُنشأة (حسب تاريخ الإنشاء) وإجمالي المستخلصات المصروفة (approval_stage = disbursed حسب تاريخ الصرف الفعلي).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pie Charts Row -->
    <div class="row mb-4">
        <!-- Extract Types Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        توزيع المستخلصات حسب النوع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="extractTypesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        توزيع المستخلصات حسب القسم
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bar Charts Row -->
    <div class="row mb-4">
        <!-- Branch Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        توزيع المستخلصات حسب الفرع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="branchChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approval Stages Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        توزيع المستخلصات حسب مراحل الاعتماد
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="approvalStagesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Tables Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="section-title">
                <i class="fas fa-table me-2"></i>
                التقارير التفصيلية
            </h2>
        </div>
    </div>

    <!-- Approval Stages Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        تفاصيل المستخلصات حسب مراحل الاعتماد
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th rowspan="2" class="align-middle">مرحلة الاعتماد</th>
                                    <th colspan="2" class="text-center bg-success">جزئي</th>
                                    <th colspan="2" class="text-center bg-primary">نهائي عادي</th>
                                    <th colspan="2" class="text-center bg-warning">نهائي للجزئية</th>
                                    <th colspan="2" class="text-center bg-secondary">الإجمالي</th>
                                </tr>
                                <tr>
                                    <th class="bg-success bg-opacity-75">العدد</th>
                                    <th class="bg-success bg-opacity-75">المبلغ (ر.س)</th>
                                    <th class="bg-primary bg-opacity-75">العدد</th>
                                    <th class="bg-primary bg-opacity-75">المبلغ (ر.س)</th>
                                    <th class="bg-warning bg-opacity-75">العدد</th>
                                    <th class="bg-warning bg-opacity-75">المبلغ (ر.س)</th>
                                    <th class="bg-secondary bg-opacity-75">العدد</th>
                                    <th class="bg-secondary bg-opacity-75">المبلغ (ر.س)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalPartialCount = 0;
                                $totalFinalRegularCount = 0;
                                $totalFinalForPartialCount = 0;
                                $totalAllCount = 0;

                                // استخدام المراحل الديناميكية من قاعدة البيانات
                                foreach ($stageNames as $stageKey => $stageName):
                                    $stageData = $stageStats[$stageKey] ?? ['partial' => 0, 'final_regular' => 0, 'final_for_partial' => 0, 'count' => 0, 'partial_count' => 0, 'final_regular_count' => 0, 'final_for_partial_count' => 0];
                                    $stageDataGross = $stageStatsGross[$stageKey] ?? ['partial' => 0, 'final_regular' => 0, 'final_for_partial' => 0];
                                    $stageColor = $stageColors[$stageKey] ?? 'secondary';

                                    // حساب عدد المستخلصات لكل نوع
                                    $partialCount = $stageData['partial_count'] ?? 0;
                                    $finalRegularCount = $stageData['final_regular_count'] ?? 0;
                                    $finalForPartialCount = $stageData['final_for_partial_count'] ?? 0;
                                    $stageCountTotal = $partialCount + $finalRegularCount + $finalForPartialCount;

                                    $totalPartialCount += $partialCount;
                                    $totalFinalRegularCount += $finalRegularCount;
                                    $totalFinalForPartialCount += $finalForPartialCount;
                                    $totalAllCount += $stageCountTotal;

                                    // حساب المبالغ
                                    $stageTotal = $stageData['partial'] + $stageData['final_regular'] + $stageData['final_for_partial'];
                                    $stageTotalGross = $stageDataGross['partial'] + $stageDataGross['final_regular'] + $stageDataGross['final_for_partial'];
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <i class="<?= $stageIcons[$stageKey] ?? 'fas fa-check' ?> text-<?= $stageColor ?> me-2"></i>
                                            <?= $stageName ?>
                                        </strong>
                                    </td>
                                    <!-- جزئي -->
                                    <td class="text-center"><span class="badge bg-success"><?= $partialCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($stageData['partial'], 2) ?>" data-gross="<?= number_format($stageDataGross['partial'], 2) ?>"><?= number_format($stageData['partial'], 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <!-- نهائي عادي -->
                                    <td class="text-center"><span class="badge bg-primary"><?= $finalRegularCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($stageData['final_regular'], 2) ?>" data-gross="<?= number_format($stageDataGross['final_regular'], 2) ?>"><?= number_format($stageData['final_regular'], 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <!-- نهائي للجزئية -->
                                    <td class="text-center"><span class="badge bg-warning"><?= $finalForPartialCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($stageData['final_for_partial'], 2) ?>" data-gross="<?= number_format($stageDataGross['final_for_partial'], 2) ?>"><?= number_format($stageData['final_for_partial'], 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <!-- الإجمالي -->
                                    <td class="text-center"><strong><span class="badge bg-<?= $stageColor ?>"><?= $stageCountTotal ?></span></strong></td>
                                    <td class="amount-display" data-net="<?= number_format($stageTotal, 2) ?>" data-gross="<?= number_format($stageTotalGross, 2) ?>"><strong><?= number_format($stageTotal, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary fw-bold">
                                    <td><strong>الإجمالي الكلي</strong></td>
                                    <!-- جزئي -->
                                    <td class="text-center"><span class="badge bg-success"><?= $totalPartialCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($totalPartial, 2) ?>" data-gross="<?= number_format($totalPartialGross, 2) ?>"><?= number_format($totalPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <!-- نهائي عادي -->
                                    <td class="text-center"><span class="badge bg-primary"><?= $totalFinalRegularCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($totalFinalRegular, 2) ?>" data-gross="<?= number_format($totalFinalRegularGross, 2) ?>"><?= number_format($totalFinalRegular, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <!-- نهائي للجزئية -->
                                    <td class="text-center"><span class="badge bg-warning"><?= $totalFinalForPartialCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($totalFinalForPartial, 2) ?>" data-gross="<?= number_format($totalFinalForPartialGross, 2) ?>"><?= number_format($totalFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <!-- الإجمالي -->
                                    <td class="text-center"><span class="badge bg-dark"><?= $totalAllCount ?></span></td>
                                    <td class="amount-display" data-net="<?= number_format($grandTotal, 2) ?>" data-gross="<?= number_format($grandTotalGross, 2) ?>"><?= number_format($grandTotal, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Demolition Table -->
    <?php if ($pendingDemolitionStats['final_regular']['count'] > 0 || $pendingDemolitionStats['final_for_partial']['count'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        المستخلصات المتوقفة على التخريد
                        <span class="badge bg-white text-danger ms-2">
                            <?= $pendingDemolitionStats['final_regular']['count'] + $pendingDemolitionStats['final_for_partial']['count'] ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> هذه المستخلصات تم خصمها من جدول مراحل الاعتماد أعلاه لتجنب التكرار في المبالغ.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-danger">
                                <tr>
                                    <th rowspan="2" class="align-middle">النوع</th>
                                    <th colspan="2" class="text-center">العدد والمبلغ</th>
                                </tr>
                                <tr>
                                    <th>العدد</th>
                                    <th>المبلغ (ر.س)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pendingDemolitionStats['final_regular']['count'] > 0): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <i class="fas fa-file-invoice text-primary me-2"></i>
                                            المستخلصات النهائية العادية
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            <?= $pendingDemolitionStats['final_regular']['count'] ?>
                                        </span>
                                    </td>
                                    <td class="amount-display" data-net="<?= number_format($pendingDemolitionStats['final_regular']['amount'], 2) ?>" data-gross="<?= number_format($pendingDemolitionStats['final_regular']['amount_gross'], 2) ?>">
                                        <?= number_format($pendingDemolitionStats['final_regular']['amount'], 2) ?>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($pendingDemolitionStats['final_for_partial']['count'] > 0): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <i class="fas fa-file-invoice text-warning me-2"></i>
                                            المستخلصات النهائية للجزئية
                                        </strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">
                                            <?= $pendingDemolitionStats['final_for_partial']['count'] ?>
                                        </span>
                                    </td>
                                    <td class="amount-display" data-net="<?= number_format($pendingDemolitionStats['final_for_partial']['amount'], 2) ?>" data-gross="<?= number_format($pendingDemolitionStats['final_for_partial']['amount_gross'], 2) ?>">
                                        <?= number_format($pendingDemolitionStats['final_for_partial']['amount'], 2) ?>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <tr class="table-danger fw-bold">
                                    <td><strong>الإجمالي</strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-dark">
                                            <?= $pendingDemolitionStats['final_regular']['count'] + $pendingDemolitionStats['final_for_partial']['count'] ?>
                                        </span>
                                    </td>
                                    <td class="amount-display" data-net="<?= number_format($pendingDemolitionStats['final_regular']['amount'] + $pendingDemolitionStats['final_for_partial']['amount'], 2) ?>" data-gross="<?= number_format($pendingDemolitionStats['final_regular']['amount_gross'] + $pendingDemolitionStats['final_for_partial']['amount_gross'], 2) ?>">
                                        <?= number_format($pendingDemolitionStats['final_regular']['amount'] + $pendingDemolitionStats['final_for_partial']['amount'], 2) ?>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Department Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i>
                        تفاصيل المستخلصات حسب القسم
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th rowspan="2" class="align-middle">القسم</th>
                                    <th colspan="3" class="text-center">المستخلصات</th>
                                    <th rowspan="2" class="align-middle">الإجمالي</th>
                                    <th rowspan="2" class="align-middle bg-success bg-opacity-25">المصروف</th>
                                    <th rowspan="2" class="align-middle bg-warning bg-opacity-25">المتبقي</th>
                                </tr>
                                <tr>
                                    <th>الجزئية</th>
                                    <th>النهائية العادية</th>
                                    <th>النهائية للجزئية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $deptNames = [
                                    'connections' => 'التوصيلات',
                                    'projects' => 'المشاريع'
                                ];

                                $totalPartialAll = 0;
                                $totalFinalRegularAll = 0;
                                $totalFinalForPartialAll = 0;
                                $totalAll = 0;
                                $totalDisbursedAll = 0;
                                $totalRemainingAll = 0;

                                $totalPartialAllGross = 0;
                                $totalFinalRegularAllGross = 0;
                                $totalFinalForPartialAllGross = 0;
                                $totalAllGross = 0;
                                $totalDisbursedAllGross = 0;
                                $totalRemainingAllGross = 0;

                                foreach ($deptNames as $deptKey => $deptName):
                                    $deptData = $departmentStats[$deptKey];
                                    $deptTotal = $deptData['partial'] + $deptData['final_regular'] + $deptData['final_for_partial'];
                                    $deptDisbursed = $deptData['partial_disbursed'] + $deptData['final_regular_disbursed'] + $deptData['final_for_partial_disbursed'];
                                    $deptRemaining = $deptTotal - $deptDisbursed;

                                    $deptTotalGross = $deptData['partial_gross'] + $deptData['final_regular_gross'] + $deptData['final_for_partial_gross'];
                                    $deptDisbursedGross = $deptData['partial_disbursed_gross'] + $deptData['final_regular_disbursed_gross'] + $deptData['final_for_partial_disbursed_gross'];
                                    $deptRemainingGross = $deptTotalGross - $deptDisbursedGross;

                                    $totalPartialAll += $deptData['partial'];
                                    $totalFinalRegularAll += $deptData['final_regular'];
                                    $totalFinalForPartialAll += $deptData['final_for_partial'];
                                    $totalAll += $deptTotal;
                                    $totalDisbursedAll += $deptDisbursed;
                                    $totalRemainingAll += $deptRemaining;

                                    $totalPartialAllGross += $deptData['partial_gross'];
                                    $totalFinalRegularAllGross += $deptData['final_regular_gross'];
                                    $totalFinalForPartialAllGross += $deptData['final_for_partial_gross'];
                                    $totalAllGross += $deptTotalGross;
                                    $totalDisbursedAllGross += $deptDisbursedGross;
                                    $totalRemainingAllGross += $deptRemainingGross;
                                ?>
                                <tr>
                                    <td><strong><?= $deptName ?></strong></td>
                                    <td><span class="amount-display" data-net="<?= number_format($deptData['partial'], 2) ?>" data-gross="<?= number_format($deptData['partial_gross'], 2) ?>"><?= number_format($deptData['partial'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <td><span class="amount-display" data-net="<?= number_format($deptData['final_regular'], 2) ?>" data-gross="<?= number_format($deptData['final_regular_gross'], 2) ?>"><?= number_format($deptData['final_regular'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <td><span class="amount-display" data-net="<?= number_format($deptData['final_for_partial'], 2) ?>" data-gross="<?= number_format($deptData['final_for_partial_gross'], 2) ?>"><?= number_format($deptData['final_for_partial'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></td>
                                    <td><strong><span class="amount-display" data-net="<?= number_format($deptTotal, 2) ?>" data-gross="<?= number_format($deptTotalGross, 2) ?>"><?= number_format($deptTotal, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="bg-success bg-opacity-10"><strong><span class="amount-display" data-net="<?= number_format($deptDisbursed, 2) ?>" data-gross="<?= number_format($deptDisbursedGross, 2) ?>"><?= number_format($deptDisbursed, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="bg-warning bg-opacity-10"><strong><span class="amount-display" data-net="<?= number_format($deptRemaining, 2) ?>" data-gross="<?= number_format($deptRemainingGross, 2) ?>"><?= number_format($deptRemaining, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary fw-bold">
                                    <td><strong>الإجمالي</strong></td>
                                    <td><strong><span class="amount-display" data-net="<?= number_format($totalPartialAll, 2) ?>" data-gross="<?= number_format($totalPartialAllGross, 2) ?>"><?= number_format($totalPartialAll, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td><strong><span class="amount-display" data-net="<?= number_format($totalFinalRegularAll, 2) ?>" data-gross="<?= number_format($totalFinalRegularAllGross, 2) ?>"><?= number_format($totalFinalRegularAll, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td><strong><span class="amount-display" data-net="<?= number_format($totalFinalForPartialAll, 2) ?>" data-gross="<?= number_format($totalFinalForPartialAllGross, 2) ?>"><?= number_format($totalFinalForPartialAll, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td><strong><span class="amount-display" data-net="<?= number_format($totalAll, 2) ?>" data-gross="<?= number_format($totalAllGross, 2) ?>"><?= number_format($totalAll, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="bg-success bg-opacity-25"><strong><span class="amount-display" data-net="<?= number_format($totalDisbursedAll, 2) ?>" data-gross="<?= number_format($totalDisbursedAllGross, 2) ?>"><?= number_format($totalDisbursedAll, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="bg-warning bg-opacity-25"><strong><span class="amount-display" data-net="<?= number_format($totalRemainingAll, 2) ?>" data-gross="<?= number_format($totalRemainingAllGross, 2) ?>"><?= number_format($totalRemainingAll, 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        تفاصيل المستخلصات حسب الفرع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>الفرع</th>
                                    <th>المستخلصات الجزئية</th>
                                    <th>المستخلصات النهائية العادية</th>
                                    <th>المستخلصات النهائية للجزئية</th>
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // تجميع البيانات حسب الفرع
                                $branchGrouped = [];
                                foreach ($branchStats as $stat) {
                                    $branchKey = $stat['branch_name'];
                                    if (!isset($branchGrouped[$branchKey])) {
                                        $branchGrouped[$branchKey] = [
                                            'code' => $stat['branch_code'],
                                            'partial' => ['count' => 0, 'amount' => 0, 'amount_gross' => 0],
                                            'final_regular' => ['count' => 0, 'amount' => 0, 'amount_gross' => 0],
                                            'final_for_partial' => ['count' => 0, 'amount' => 0, 'amount_gross' => 0]
                                        ];
                                    }
                                    $branchGrouped[$branchKey][$stat['extract_type']]['count'] = $stat['count'];
                                    $branchGrouped[$branchKey][$stat['extract_type']]['amount'] = $stat['total_net'];
                                    $branchGrouped[$branchKey][$stat['extract_type']]['amount_gross'] = $stat['total_gross'];
                                }

                                // عرض البيانات المجمعة
                                $grandTotalPartial = 0;
                                $grandTotalFinalRegular = 0;
                                $grandTotalFinalForPartial = 0;
                                $grandTotalPartialGross = 0;
                                $grandTotalFinalRegularGross = 0;
                                $grandTotalFinalForPartialGross = 0;

                                foreach ($branchGrouped as $branchName => $data):
                                    $branchTotal = $data['partial']['amount'] + $data['final_regular']['amount'] + $data['final_for_partial']['amount'];
                                    $branchTotalGross = $data['partial']['amount_gross'] + $data['final_regular']['amount_gross'] + $data['final_for_partial']['amount_gross'];
                                    $grandTotalPartial += $data['partial']['amount'];
                                    $grandTotalFinalRegular += $data['final_regular']['amount'];
                                    $grandTotalFinalForPartial += $data['final_for_partial']['amount'];
                                    $grandTotalPartialGross += $data['partial']['amount_gross'];
                                    $grandTotalFinalRegularGross += $data['final_regular']['amount_gross'];
                                    $grandTotalFinalForPartialGross += $data['final_for_partial']['amount_gross'];
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <i class="fas fa-map-marker-alt text-warning me-2"></i>
                                            <?= htmlspecialchars($branchName) ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($data['code']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary mb-1"><?= $data['partial']['count'] ?> مستخلص</span>
                                        <br>
                                        <span class="amount-display" data-net="<?= number_format($data['partial']['amount'], 2) ?>" data-gross="<?= number_format($data['partial']['amount_gross'], 2) ?>"><?= number_format($data['partial']['amount'], 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success mb-1"><?= $data['final_regular']['count'] ?> مستخلص</span>
                                        <br>
                                        <span class="amount-display" data-net="<?= number_format($data['final_regular']['amount'], 2) ?>" data-gross="<?= number_format($data['final_regular']['amount_gross'], 2) ?>"><?= number_format($data['final_regular']['amount'], 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger mb-1"><?= $data['final_for_partial']['count'] ?> مستخلص</span>
                                        <br>
                                        <span class="amount-display" data-net="<?= number_format($data['final_for_partial']['amount'], 2) ?>" data-gross="<?= number_format($data['final_for_partial']['amount_gross'], 2) ?>"><?= number_format($data['final_for_partial']['amount'], 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></span>
                                    </td>
                                    <td class="amount-display" data-net="<?= number_format($branchTotal, 2) ?>" data-gross="<?= number_format($branchTotalGross, 2) ?>">
                                        <strong><?= number_format($branchTotal, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- صف الإجمالي -->
                                <tr class="table-warning">
                                    <td><strong>الإجمالي الكلي</strong></td>
                                    <td class="amount-display" data-net="<?= number_format($grandTotalPartial, 2) ?>" data-gross="<?= number_format($grandTotalPartialGross, 2) ?>"><strong><?= number_format($grandTotalPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="amount-display" data-net="<?= number_format($grandTotalFinalRegular, 2) ?>" data-gross="<?= number_format($grandTotalFinalRegularGross, 2) ?>"><strong><?= number_format($grandTotalFinalRegular, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="amount-display" data-net="<?= number_format($grandTotalFinalForPartial, 2) ?>" data-gross="<?= number_format($grandTotalFinalForPartialGross, 2) ?>"><strong><?= number_format($grandTotalFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                    <td class="amount-display" data-net="<?= number_format($grandTotalPartial + $grandTotalFinalRegular + $grandTotalFinalForPartial, 2) ?>" data-gross="<?= number_format($grandTotalPartialGross + $grandTotalFinalRegularGross + $grandTotalFinalForPartialGross, 2) ?>"><strong><?= number_format($grandTotalPartial + $grandTotalFinalRegular + $grandTotalFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Disbursement Time Series Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        مقارنة تطور المستخلصات عبر الزمن (الإنشاء مقابل الصرف)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> هذا الجدول يقارن بين المستخلصات المُنشأة (حسب تاريخ الإنشاء) والمستخلصات المصروفة (approval_stage = disbursed حسب تاريخ الصرف الفعلي).
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" style="border-collapse: separate; border-spacing: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);">
                                    <th rowspan="2" class="align-middle text-center" style="border: 2px solid #95a5a6; padding: 14px; font-weight: bold; color: #ffffff; font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
                                        <i class="fas fa-calendar-alt me-2"></i>الفترة الزمنية
                                    </th>
                                    <th colspan="3" class="text-center" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: #ffffff; font-weight: bold; font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
                                        <i class="fas fa-plus-circle me-2"></i>المستخلصات المُنشأة
                                    </th>
                                    <th colspan="3" class="text-center" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: #ffffff; font-weight: bold; font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
                                        <i class="fas fa-check-circle me-2"></i>المستخلصات المصروفة
                                    </th>
                                    <th colspan="2" class="text-center" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #f39c12 0%, #d68910 100%); color: #ffffff; font-weight: bold; font-size: 1rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
                                        <i class="fas fa-chart-bar me-2"></i>المقارنة
                                    </th>
                                </tr>
                                <tr style="background: linear-gradient(135deg, #ecf0f1 0%, #bdc3c7 100%);">
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #1a5490; background-color: #d6eaf8; font-weight: bold;">الجزئية</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #1a5490; background-color: #d6eaf8; font-weight: bold;">النهائية العادية</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #1a5490; background-color: #d6eaf8; font-weight: bold;">النهائية للجزئية</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #186a3b; background-color: #d5f4e6; font-weight: bold;">الجزئية</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #186a3b; background-color: #d5f4e6; font-weight: bold;">النهائية العادية</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #186a3b; background-color: #d5f4e6; font-weight: bold;">النهائية للجزئية</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #9a5c0f; background-color: #fdebd0; font-weight: bold;">إجمالي الإنشاء</th>
                                    <th class="text-center" style="border: 2px solid #95a5a6; padding: 11px; font-size: 0.95rem; color: #9a5c0f; background-color: #fdebd0; font-weight: bold;">إجمالي الصرف</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalCreatedPartial = 0;
                                $totalCreatedFinalRegular = 0;
                                $totalCreatedFinalForPartial = 0;
                                $totalDisbursedPartial = 0;
                                $totalDisbursedFinalRegular = 0;
                                $totalDisbursedFinalForPartial = 0;
                                $totalCreatedPartialGross = 0;
                                $totalCreatedFinalRegularGross = 0;
                                $totalCreatedFinalForPartialGross = 0;
                                $totalDisbursedPartialGross = 0;
                                $totalDisbursedFinalRegularGross = 0;
                                $totalDisbursedFinalForPartialGross = 0;

                                // دمج المفاتيح من كلا المصفوفتين
                                $allDates = array_unique(array_merge(
                                    array_keys($timeSeriesData),
                                    array_keys($disbursementTimeSeriesData)
                                ));
                                sort($allDates);

                                foreach ($allDates as $date):
                                    $periodLabel = $timeSeriesLabels[$date] ?? $disbursementLabels[$date] ?? $date;

                                    // بيانات الإنشاء
                                    $createdData = $timeSeriesData[$date] ?? ['partial' => 0, 'final_regular' => 0, 'final_for_partial' => 0, 'partial_gross' => 0, 'final_regular_gross' => 0, 'final_for_partial_gross' => 0];
                                    $createdTotal = $createdData['partial'] + $createdData['final_regular'] + $createdData['final_for_partial'];
                                    $createdTotalGross = $createdData['partial_gross'] + $createdData['final_regular_gross'] + $createdData['final_for_partial_gross'];

                                    // بيانات الصرف
                                    $disbursedData = $disbursementTimeSeriesData[$date] ?? ['partial' => 0, 'final_regular' => 0, 'final_for_partial' => 0, 'partial_gross' => 0, 'final_regular_gross' => 0, 'final_for_partial_gross' => 0];
                                    $disbursedTotal = $disbursedData['partial'] + $disbursedData['final_regular'] + $disbursedData['final_for_partial'];
                                    $disbursedTotalGross = $disbursedData['partial_gross'] + $disbursedData['final_regular_gross'] + $disbursedData['final_for_partial_gross'];

                                    $totalCreatedPartial += $createdData['partial'];
                                    $totalCreatedFinalRegular += $createdData['final_regular'];
                                    $totalCreatedFinalForPartial += $createdData['final_for_partial'];
                                    $totalDisbursedPartial += $disbursedData['partial'];
                                    $totalDisbursedFinalRegular += $disbursedData['final_regular'];
                                    $totalDisbursedFinalForPartial += $disbursedData['final_for_partial'];
                                    $totalCreatedPartialGross += $createdData['partial_gross'];
                                    $totalCreatedFinalRegularGross += $createdData['final_regular_gross'];
                                    $totalCreatedFinalForPartialGross += $createdData['final_for_partial_gross'];
                                    $totalDisbursedPartialGross += $disbursedData['partial_gross'];
                                    $totalDisbursedFinalRegularGross += $disbursedData['final_regular_gross'];
                                    $totalDisbursedFinalForPartialGross += $disbursedData['final_for_partial_gross'];

                                    // تخطي الصفوف التي لا تحتوي على بيانات
                                    if ($createdTotal == 0 && $disbursedTotal == 0) continue;
                                ?>
                                <tr style="transition: all 0.2s ease;">
                                    <td style="border: 2px solid #95a5a6; padding: 11px; background-color: #ffffff;">
                                        <strong style="color: #2c3e50; font-weight: bold;">
                                            <i class="fas fa-calendar-alt me-2" style="color: #7f8c8d;"></i>
                                            <?= htmlspecialchars($periodLabel ?? '') ?>
                                        </strong>
                                    </td>
                                    <!-- المستخلصات المُنشأة -->
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #ebf5fb;" data-net="<?= number_format($createdData['partial'], 2) ?>" data-gross="<?= number_format($createdData['partial_gross'], 2) ?>">
                                        <?php if ($createdData['partial'] > 0): ?>
                                            <span style="color: #1a5490; font-weight: bold; font-size: 0.95rem;"><?= number_format($createdData['partial'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #ebf5fb;" data-net="<?= number_format($createdData['final_regular'], 2) ?>" data-gross="<?= number_format($createdData['final_regular_gross'], 2) ?>">
                                        <?php if ($createdData['final_regular'] > 0): ?>
                                            <span style="color: #1a5490; font-weight: bold; font-size: 0.95rem;"><?= number_format($createdData['final_regular'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #ebf5fb;" data-net="<?= number_format($createdData['final_for_partial'], 2) ?>" data-gross="<?= number_format($createdData['final_for_partial_gross'], 2) ?>">
                                        <?php if ($createdData['final_for_partial'] > 0): ?>
                                            <span style="color: #1a5490; font-weight: bold; font-size: 0.95rem;"><?= number_format($createdData['final_for_partial'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- المستخلصات المصروفة -->
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #eafaf1;" data-net="<?= number_format($disbursedData['partial'], 2) ?>" data-gross="<?= number_format($disbursedData['partial_gross'], 2) ?>">
                                        <?php if ($disbursedData['partial'] > 0): ?>
                                            <span style="color: #186a3b; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedData['partial'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #eafaf1;" data-net="<?= number_format($disbursedData['final_regular'], 2) ?>" data-gross="<?= number_format($disbursedData['final_regular_gross'], 2) ?>">
                                        <?php if ($disbursedData['final_regular'] > 0): ?>
                                            <span style="color: #186a3b; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedData['final_regular'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #eafaf1;" data-net="<?= number_format($disbursedData['final_for_partial'], 2) ?>" data-gross="<?= number_format($disbursedData['final_for_partial_gross'], 2) ?>">
                                        <?php if ($disbursedData['final_for_partial'] > 0): ?>
                                            <span style="color: #186a3b; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedData['final_for_partial'], 2) ?></span> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- المقارنة -->
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fef9e7;" data-net="<?= number_format($createdTotal, 2) ?>" data-gross="<?= number_format($createdTotalGross, 2) ?>">
                                        <strong style="color: #9a5c0f; font-weight: bold; font-size: 0.95rem;"><?= number_format($createdTotal, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fef9e7;" data-net="<?= number_format($disbursedTotal, 2) ?>" data-gross="<?= number_format($disbursedTotalGross, 2) ?>">
                                        <strong style="color: #9a5c0f; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedTotal, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- سطر المستخلصات المصروفة بدون تاريخ صرف -->
                                <?php if ($disbursedNoDateTotal > 0): ?>
                                <tr style="background-color: #fff3cd; border: 2px solid #ffc107;">
                                    <td style="border: 2px solid #95a5a6; padding: 11px; background-color: #fff3cd;">
                                        <strong style="color: #856404; font-weight: bold;">
                                            <i class="fas fa-exclamation-triangle me-2" style="color: #ffc107;"></i>مصروفة بدون تاريخ صرف
                                        </strong>
                                    </td>
                                    <!-- المُنشأة - فارغة -->
                                    <td class="text-end" style="border: 2px solid #95a5a6; padding: 11px; background-color: #ebf5fb;">
                                        <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                    </td>
                                    <td class="text-end" style="border: 2px solid #95a5a6; padding: 11px; background-color: #ebf5fb;">
                                        <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                    </td>
                                    <td class="text-end" style="border: 2px solid #95a5a6; padding: 11px; background-color: #ebf5fb;">
                                        <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                    </td>
                                    <!-- المصروفة بدون تاريخ -->
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fff3cd;" data-net="<?= number_format($disbursedNoDatePartial, 2) ?>" data-gross="<?= number_format($disbursedNoDatePartialGross, 2) ?>">
                                        <span style="color: #856404; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedNoDatePartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></span>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fff3cd;" data-net="<?= number_format($disbursedNoDateFinalRegular, 2) ?>" data-gross="<?= number_format($disbursedNoDateFinalRegularGross, 2) ?>">
                                        <span style="color: #856404; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedNoDateFinalRegular, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></span>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fff3cd;" data-net="<?= number_format($disbursedNoDateFinalForPartial, 2) ?>" data-gross="<?= number_format($disbursedNoDateFinalForPartialGross, 2) ?>">
                                        <span style="color: #856404; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedNoDateFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></span>
                                    </td>
                                    <!-- المقارنة -->
                                    <td class="text-end" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fef9e7;">
                                        <span class="text-muted" style="font-size: 0.9rem;">-</span>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 11px; background-color: #fff3cd;" data-net="<?= number_format($disbursedNoDateTotal, 2) ?>" data-gross="<?= number_format($disbursedNoDateTotalGross, 2) ?>">
                                        <strong style="color: #856404; font-weight: bold; font-size: 0.95rem;"><?= number_format($disbursedNoDateTotal, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <!-- صف الإجمالي -->
                                <tr style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); border-top: 4px solid #e74c3c; box-shadow: 0 4px 6px rgba(0,0,0,0.2);">
                                    <td style="border: 2px solid #95a5a6; padding: 14px;">
                                        <strong style="color: #ffffff; font-size: 1.1rem; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">
                                            <i class="fas fa-calculator me-2"></i>الإجمالي الكلي
                                        </strong>
                                    </td>
                                    <!-- إجمالي المُنشأة -->
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #aed6f1 0%, #85c1e9 100%);" data-net="<?= number_format($totalCreatedPartial, 2) ?>" data-gross="<?= number_format($totalCreatedPartialGross, 2) ?>">
                                        <strong style="color: #1a5490; font-weight: bold; font-size: 1.05rem;"><?= number_format($totalCreatedPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #aed6f1 0%, #85c1e9 100%);" data-net="<?= number_format($totalCreatedFinalRegular, 2) ?>" data-gross="<?= number_format($totalCreatedFinalRegularGross, 2) ?>">
                                        <strong style="color: #1a5490; font-weight: bold; font-size: 1.05rem;"><?= number_format($totalCreatedFinalRegular, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #aed6f1 0%, #85c1e9 100%);" data-net="<?= number_format($totalCreatedFinalForPartial, 2) ?>" data-gross="<?= number_format($totalCreatedFinalForPartialGross, 2) ?>">
                                        <strong style="color: #1a5490; font-weight: bold; font-size: 1.05rem;"><?= number_format($totalCreatedFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <!-- إجمالي المصروفة (مع المصروف بدون تاريخ) -->
                                    <?php
                                    $grandTotalDisbursedPartial = $totalDisbursedPartial + $disbursedNoDatePartial;
                                    $grandTotalDisbursedFinalRegular = $totalDisbursedFinalRegular + $disbursedNoDateFinalRegular;
                                    $grandTotalDisbursedFinalForPartial = $totalDisbursedFinalForPartial + $disbursedNoDateFinalForPartial;
                                    $grandTotalDisbursedPartialGross = $totalDisbursedPartialGross + $disbursedNoDatePartialGross;
                                    $grandTotalDisbursedFinalRegularGross = $totalDisbursedFinalRegularGross + $disbursedNoDateFinalRegularGross;
                                    $grandTotalDisbursedFinalForPartialGross = $totalDisbursedFinalForPartialGross + $disbursedNoDateFinalForPartialGross;
                                    ?>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #a9dfbf 0%, #7dcea0 100%);" data-net="<?= number_format($grandTotalDisbursedPartial, 2) ?>" data-gross="<?= number_format($grandTotalDisbursedPartialGross, 2) ?>">
                                        <strong style="color: #186a3b; font-weight: bold; font-size: 1.05rem;"><?= number_format($grandTotalDisbursedPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #a9dfbf 0%, #7dcea0 100%);" data-net="<?= number_format($grandTotalDisbursedFinalRegular, 2) ?>" data-gross="<?= number_format($grandTotalDisbursedFinalRegularGross, 2) ?>">
                                        <strong style="color: #186a3b; font-weight: bold; font-size: 1.05rem;"><?= number_format($grandTotalDisbursedFinalRegular, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #a9dfbf 0%, #7dcea0 100%);" data-net="<?= number_format($grandTotalDisbursedFinalForPartial, 2) ?>" data-gross="<?= number_format($grandTotalDisbursedFinalForPartialGross, 2) ?>">
                                        <strong style="color: #186a3b; font-weight: bold; font-size: 1.05rem;"><?= number_format($grandTotalDisbursedFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <!-- إجمالي المقارنة -->
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #f9e79f 0%, #f7dc6f 100%);" data-net="<?= number_format($totalCreatedPartial + $totalCreatedFinalRegular + $totalCreatedFinalForPartial, 2) ?>" data-gross="<?= number_format($totalCreatedPartialGross + $totalCreatedFinalRegularGross + $totalCreatedFinalForPartialGross, 2) ?>">
                                        <strong style="color: #9a5c0f; font-weight: bold; font-size: 1.1rem;"><?= number_format($totalCreatedPartial + $totalCreatedFinalRegular + $totalCreatedFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                    <td class="text-end amount-display" style="border: 2px solid #95a5a6; padding: 14px; background: linear-gradient(135deg, #f9e79f 0%, #f7dc6f 100%);" data-net="<?= number_format($grandTotalDisbursedPartial + $grandTotalDisbursedFinalRegular + $grandTotalDisbursedFinalForPartial, 2) ?>" data-gross="<?= number_format($grandTotalDisbursedPartialGross + $grandTotalDisbursedFinalRegularGross + $grandTotalDisbursedFinalForPartialGross, 2) ?>">
                                        <strong style="color: #9a5c0f; font-weight: bold; font-size: 1.1rem;"><?= number_format($grandTotalDisbursedPartial + $grandTotalDisbursedFinalRegular + $grandTotalDisbursedFinalForPartial, 2) ?> <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- إحصائيات إضافية -->
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <div class="card bg-primary bg-opacity-10 border-primary">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">إجمالي المُنشأة</h6>
                                    <h4 class="text-primary mb-0">
                                        <?= number_format($totalCreatedPartial + $totalCreatedFinalRegular + $totalCreatedFinalForPartial, 2) ?>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success bg-opacity-10 border-success">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">إجمالي المصروفة</h6>
                                    <h4 class="text-success mb-0">
                                        <?= number_format($totalDisbursedPartial + $totalDisbursedFinalRegular + $totalDisbursedFinalForPartial, 2) ?>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning bg-opacity-10 border-warning">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">الفرق (المعلقة)</h6>
                                    <h4 class="text-warning mb-0">
                                        <?php
                                        $totalCreated = $totalCreatedPartial + $totalCreatedFinalRegular + $totalCreatedFinalForPartial;
                                        $totalDisbursed = $totalDisbursedPartial + $totalDisbursedFinalRegular + $totalDisbursedFinalForPartial;
                                        $difference = $totalCreated - $totalDisbursed;
                                        echo number_format($difference, 2);
                                        ?>
                                        <span class="sar-icon"><svg><use href="#sar-symbol"/></svg></span>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info bg-opacity-10 border-info">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">نسبة الصرف</h6>
                                    <h4 class="text-info mb-0">
                                        <?php
                                        $disbursementRate = $totalCreated > 0 ? ($totalDisbursed / $totalCreated) * 100 : 0;
                                        echo number_format($disbursementRate, 1);
                                        ?>%
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Period selector handler
document.getElementById('periodSelect').addEventListener('change', function() {
    const customDateStart = document.getElementById('customDateStart');
    const customDateEnd = document.getElementById('customDateEnd');
    const quarterYear = document.getElementById('quarterYear');

    if (this.value === 'custom') {
        customDateStart.style.display = 'block';
        customDateEnd.style.display = 'block';
        quarterYear.style.display = 'none';
    } else if (['q1', 'q2', 'q3', 'q4'].includes(this.value)) {
        customDateStart.style.display = 'none';
        customDateEnd.style.display = 'none';
        quarterYear.style.display = 'block';
    } else {
        customDateStart.style.display = 'none';
        customDateEnd.style.display = 'none';
        quarterYear.style.display = 'none';
    }
});

// Chart.js Global Configuration
Chart.defaults.font.family = 'Arial, sans-serif';
Chart.defaults.font.size = 14;
Chart.defaults.color = '#333';

// Store both net and gross data for charts
const timeSeriesDataNet = {
    partial: <?= json_encode(array_column($timeSeriesData, 'partial')) ?>,
    finalRegular: <?= json_encode(array_column($timeSeriesData, 'final_regular')) ?>,
    finalForPartial: <?= json_encode(array_column($timeSeriesData, 'final_for_partial')) ?>
};

const timeSeriesDataGross = {
    partial: <?= json_encode(array_column($timeSeriesData, 'partial_gross')) ?>,
    finalRegular: <?= json_encode(array_column($timeSeriesData, 'final_regular_gross')) ?>,
    finalForPartial: <?= json_encode(array_column($timeSeriesData, 'final_for_partial_gross')) ?>
};

const disbursementDataNet = {
    partial: <?= json_encode(array_column($disbursementTimeSeriesData, 'partial')) ?>,
    finalRegular: <?= json_encode(array_column($disbursementTimeSeriesData, 'final_regular')) ?>,
    finalForPartial: <?= json_encode(array_column($disbursementTimeSeriesData, 'final_for_partial')) ?>
};

const disbursementDataGross = {
    partial: <?= json_encode(array_column($disbursementTimeSeriesData, 'partial_gross')) ?>,
    finalRegular: <?= json_encode(array_column($disbursementTimeSeriesData, 'final_regular_gross')) ?>,
    finalForPartial: <?= json_encode(array_column($disbursementTimeSeriesData, 'final_for_partial_gross')) ?>
};

// 1. Time Series Chart
const timeSeriesCtx = document.getElementById('timeSeriesChart').getContext('2d');
const timeSeriesChart = new Chart(timeSeriesCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_values($timeSeriesLabels), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            {
                label: 'المستخلصات الجزئية',
                data: timeSeriesDataNet.partial,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'المستخلصات النهائية العادية',
                data: timeSeriesDataNet.finalRegular,
                borderColor: 'rgb(132, 250, 176)',
                backgroundColor: 'rgba(132, 250, 176, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'المستخلصات النهائية للجزئية',
                data: timeSeriesDataNet.finalForPartial,
                borderColor: 'rgb(250, 112, 154)',
                backgroundColor: 'rgba(250, 112, 154, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: {
                        size: 14,
                        weight: 'bold'
                    },
                    padding: 20
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            }
        }
    }
});

// 2. Disbursement Time Series Chart
const disbursementTimeSeriesCtx = document.getElementById('disbursementTimeSeriesChart').getContext('2d');
const disbursementTimeSeriesChart = new Chart(disbursementTimeSeriesCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_values($disbursementLabels), JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            {
                label: 'المستخلصات الجزئية',
                data: disbursementDataNet.partial,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'المستخلصات النهائية العادية',
                data: disbursementDataNet.finalRegular,
                borderColor: 'rgb(132, 250, 176)',
                backgroundColor: 'rgba(132, 250, 176, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            },
            {
                label: 'المستخلصات النهائية للجزئية',
                data: disbursementDataNet.finalForPartial,
                borderColor: 'rgb(250, 112, 154)',
                backgroundColor: 'rgba(250, 112, 154, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    font: {
                        size: 14,
                        weight: 'bold'
                    },
                    padding: 20
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            },
            title: {
                display: true,
                text: 'المبالغ المصروفة حسب تاريخ الصرف الفعلي',
                font: {
                    size: 16,
                    weight: 'bold'
                },
                padding: {
                    bottom: 20
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('ar-SA') + ' ر.س';
                    }
                },
                title: {
                    display: true,
                    text: 'المبلغ (ر.س)',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'تاريخ الصرف',
                    font: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            }
        }
    }
});

// 3. Extract Types Pie Chart
const extractTypesCtx = document.getElementById('extractTypesChart').getContext('2d');
const extractTypesChart = new Chart(extractTypesCtx, {
    type: 'doughnut',
    data: {
        labels: ['المستخلصات الجزئية', 'المستخلصات النهائية العادية', 'المستخلصات النهائية للجزئية'],
        datasets: [{
            data: [
                <?= $totalPartial ?>,
                <?= $totalFinalRegular ?>,
                <?= $totalFinalForPartial ?>
            ],
            backgroundColor: [
                'rgba(102, 126, 234, 0.8)',
                'rgba(132, 250, 176, 0.8)',
                'rgba(250, 112, 154, 0.8)'
            ],
            borderColor: [
                'rgb(102, 126, 234)',
                'rgb(132, 250, 176)',
                'rgb(250, 112, 154)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value.toLocaleString('ar-SA') + ' ر.س (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// 4. Department Distribution Chart
const departmentCtx = document.getElementById('departmentChart').getContext('2d');
const departmentChart = new Chart(departmentCtx, {
    type: 'pie',
    data: {
        labels: ['التوصيلات', 'المشاريع'],
        datasets: [{
            data: [
                <?= $departmentStats['connections']['partial'] + $departmentStats['connections']['final_regular'] + $departmentStats['connections']['final_for_partial'] ?>,
                <?= $departmentStats['projects']['partial'] + $departmentStats['projects']['final_regular'] + $departmentStats['projects']['final_for_partial'] ?>
            ],
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)'
            ],
            borderColor: [
                'rgb(54, 162, 235)',
                'rgb(255, 206, 86)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value.toLocaleString('ar-SA') + ' ر.س (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// 5. Branch Distribution Chart
const branchCtx = document.getElementById('branchChart').getContext('2d');
<?php
// تجميع البيانات حسب الفرع
$branchData = [];
foreach ($branchStats as $stat) {
    if (!isset($branchData[$stat['branch_name']])) {
        $branchData[$stat['branch_name']] = [
            'partial' => 0,
            'final_regular' => 0,
            'final_for_partial' => 0
        ];
    }
    $branchData[$stat['branch_name']][$stat['extract_type']] = $stat['total_net'];
}
?>
const branchChart = new Chart(branchCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($branchData)) ?>,
        datasets: [
            {
                label: 'المستخلصات الجزئية',
                data: <?= json_encode(array_column($branchData, 'partial')) ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: 'rgb(102, 126, 234)',
                borderWidth: 2
            },
            {
                label: 'المستخلصات النهائية العادية',
                data: <?= json_encode(array_column($branchData, 'final_regular')) ?>,
                backgroundColor: 'rgba(132, 250, 176, 0.8)',
                borderColor: 'rgb(132, 250, 176)',
                borderWidth: 2
            },
            {
                label: 'المستخلصات النهائية للجزئية',
                data: <?= json_encode(array_column($branchData, 'final_for_partial')) ?>,
                backgroundColor: 'rgba(250, 112, 154, 0.8)',
                borderColor: 'rgb(250, 112, 154)',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: false,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            },
            x: {
                stacked: false
            }
        }
    }
});

// 3. Comparison Chart (Created vs Disbursed)
const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');

<?php
// حساب الإجماليات - دمج المفاتيح من كلا المصفوفتين
$createdTotalsDataNet = [];
$disbursedTotalsDataNet = [];
$createdTotalsDataGross = [];
$disbursedTotalsDataGross = [];
$comparisonLabelsArray = [];

// دمج جميع التواريخ من كلا المصفوفتين
$allDatesForComparison = array_unique(array_merge(
    array_keys($timeSeriesData),
    array_keys($disbursementTimeSeriesData)
));
sort($allDatesForComparison);

// المرور على جميع التواريخ
foreach ($allDatesForComparison as $date) {
    // إضافة التسمية
    $comparisonLabelsArray[] = $timeSeriesLabels[$date] ?? $disbursementLabels[$date] ?? $date;

    // بيانات الإنشاء
    $createdData = $timeSeriesData[$date] ?? [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0,
        'partial_gross' => 0,
        'final_regular_gross' => 0,
        'final_for_partial_gross' => 0
    ];

    // بيانات الصرف
    $disbursedData = $disbursementTimeSeriesData[$date] ?? [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0,
        'partial_gross' => 0,
        'final_regular_gross' => 0,
        'final_for_partial_gross' => 0
    ];

    $createdTotalsDataNet[] = $createdData['partial'] + $createdData['final_regular'] + $createdData['final_for_partial'];
    $createdTotalsDataGross[] = $createdData['partial_gross'] + $createdData['final_regular_gross'] + $createdData['final_for_partial_gross'];

    $disbursedTotalsDataNet[] = $disbursedData['partial'] + $disbursedData['final_regular'] + $disbursedData['final_for_partial'];
    $disbursedTotalsDataGross[] = $disbursedData['partial_gross'] + $disbursedData['final_regular_gross'] + $disbursedData['final_for_partial_gross'];
}
?>

// حساب الإجماليات لكل فترة
const comparisonLabels = <?= json_encode($comparisonLabelsArray, JSON_UNESCAPED_UNICODE) ?>;

const comparisonDataNet = {
    created: <?= json_encode($createdTotalsDataNet) ?>,
    disbursed: <?= json_encode($disbursedTotalsDataNet) ?>
};

const comparisonDataGross = {
    created: <?= json_encode($createdTotalsDataGross) ?>,
    disbursed: <?= json_encode($disbursedTotalsDataGross) ?>
};

const comparisonChart = new Chart(comparisonCtx, {
    type: 'bar',
    data: {
        labels: comparisonLabels,
        datasets: [
            {
                label: 'المستخلصات المُنشأة',
                data: comparisonDataNet.created,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 2
            },
            {
                label: 'المستخلصات المصروفة',
                data: comparisonDataNet.disbursed,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'مقارنة إجمالي المستخلصات المُنشأة مقابل المصروفة',
                font: {
                    size: 16,
                    weight: 'bold'
                },
                padding: 20
            },
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('ar-SA') + ' ر.س';
                    }
                },
                title: {
                    display: true,
                    text: 'المبلغ (ر.س)',
                    font: {
                        size: 12,
                        weight: 'bold'
                    }
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'الفترة الزمنية',
                    font: {
                        size: 12,
                        weight: 'bold'
                    }
                }
            }
        }
    }
});

// 6. Approval Stages Chart (Dynamic from database)
const approvalStagesCtx = document.getElementById('approvalStagesChart').getContext('2d');
<?php
// تجهيز البيانات الديناميكية للرسم البياني
$stageLabels = [];
$partialData = [];
$finalRegularData = [];
$finalForPartialData = [];

foreach ($stageNames as $stageKey => $stageName) {
    $stageLabels[] = $stageName;
    $partialData[] = $stageStats[$stageKey]['partial'] ?? 0;
    $finalRegularData[] = $stageStats[$stageKey]['final_regular'] ?? 0;
    $finalForPartialData[] = $stageStats[$stageKey]['final_for_partial'] ?? 0;
}
?>
const approvalStagesChart = new Chart(approvalStagesCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($stageLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [
            {
                label: 'المستخلصات الجزئية',
                data: <?= json_encode($partialData) ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: 'rgb(102, 126, 234)',
                borderWidth: 2
            },
            {
                label: 'المستخلصات النهائية العادية',
                data: <?= json_encode($finalRegularData) ?>,
                backgroundColor: 'rgba(132, 250, 176, 0.8)',
                borderColor: 'rgb(132, 250, 176)',
                borderWidth: 2
            },
            {
                label: 'المستخلصات النهائية للجزئية',
                data: <?= json_encode($finalForPartialData) ?>,
                backgroundColor: 'rgba(250, 112, 154, 0.8)',
                borderColor: 'rgb(250, 112, 154)',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    font: {
                        size: 13,
                        weight: 'bold'
                    },
                    padding: 15
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.x.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                stacked: false,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('ar-SA') + ' ر.س';
                    }
                }
            },
            y: {
                stacked: false
            }
        }
    }
});

// وظيفة التبديل بين شامل ضريبة (صافي) وبدون ضريبة (إجمالي)
document.addEventListener('DOMContentLoaded', function() {
    const amountToggle = document.getElementById('amountToggle');
    const taxLabelLeft = document.getElementById('taxLabelLeft');
    const taxLabelRight = document.getElementById('taxLabelRight');

    // استرجاع الحالة المحفوظة من localStorage
    const savedState = localStorage.getItem('taxToggleState');
    let showingGross = savedState === 'gross';

    // تطبيق الحالة المحفوظة
    if (showingGross) {
        amountToggle.checked = true;
        taxLabelLeft.classList.add('active');
        taxLabelRight.classList.remove('active');
    } else {
        amountToggle.checked = false;
        taxLabelLeft.classList.remove('active');
        taxLabelRight.classList.add('active');
    }

    // تطبيق القيم عند تحميل الصفحة
    updateAllAmounts(showingGross);

    amountToggle.addEventListener('change', function() {
        showingGross = this.checked;

        // حفظ الحالة في localStorage
        localStorage.setItem('taxToggleState', showingGross ? 'gross' : 'net');

        // تحديث النصوص
        if (showingGross) {
            taxLabelLeft.classList.add('active');
            taxLabelRight.classList.remove('active');
        } else {
            taxLabelLeft.classList.remove('active');
            taxLabelRight.classList.add('active');
        }

        // تحديث القيم
        updateAllAmounts(showingGross);
    });

    // إضافة حدث النقر على النصوص
    taxLabelLeft.addEventListener('click', function() {
        if (amountToggle.checked) {
            amountToggle.checked = false;
            amountToggle.dispatchEvent(new Event('change'));
        }
    });

    taxLabelRight.addEventListener('click', function() {
        if (!amountToggle.checked) {
            amountToggle.checked = true;
            amountToggle.dispatchEvent(new Event('change'));
        }
    });

    // دالة لتحديث جميع المبالغ
    function updateAllAmounts(showGross) {
        // تحديث جميع عناصر العرض
        const amountDisplays = document.querySelectorAll('.amount-display');
        amountDisplays.forEach(function(element) {
            const netValue = element.getAttribute('data-net');
            const grossValue = element.getAttribute('data-gross');

            if (netValue && grossValue) {
                // تحديث القيمة المعروضة
                const valueToShow = showGross ? grossValue : netValue;

                // البحث عن أيقونة الريال
                const sarIcon = element.querySelector('.sar-icon');
                const sarIconLg = element.querySelector('.sar-icon-lg');
                const strongTag = element.querySelector('strong');

                if (strongTag) {
                    // إذا كان هناك strong tag، نحدث محتواه
                    if (sarIcon) {
                        strongTag.innerHTML = valueToShow + ' ';
                        strongTag.appendChild(sarIcon.cloneNode(true));
                    } else if (sarIconLg) {
                        strongTag.innerHTML = valueToShow + ' ';
                        strongTag.appendChild(sarIconLg.cloneNode(true));
                    } else {
                        strongTag.textContent = valueToShow;
                    }
                } else {
                    // إذا لم يكن هناك strong tag
                    if (sarIcon) {
                        element.innerHTML = valueToShow + ' ';
                        element.appendChild(sarIcon.cloneNode(true));
                    } else if (sarIconLg) {
                        element.innerHTML = valueToShow + ' ';
                        element.appendChild(sarIconLg.cloneNode(true));
                    } else {
                        element.textContent = valueToShow;
                    }
                }
            }
        });

        // إضافة تأثير انتقالي
        amountDisplays.forEach(function(element) {
            element.style.transition = 'all 0.3s ease';
            element.style.transform = 'scale(1.05)';
            setTimeout(function() {
                element.style.transform = 'scale(1)';
            }, 300);
        });

        // تحديث الرسوم البيانية
        // 1. تحديث رسم المستخلصات المُنشأة
        if (showGross) {
            timeSeriesChart.data.datasets[0].data = timeSeriesDataGross.partial;
            timeSeriesChart.data.datasets[1].data = timeSeriesDataGross.finalRegular;
            timeSeriesChart.data.datasets[2].data = timeSeriesDataGross.finalForPartial;
        } else {
            timeSeriesChart.data.datasets[0].data = timeSeriesDataNet.partial;
            timeSeriesChart.data.datasets[1].data = timeSeriesDataNet.finalRegular;
            timeSeriesChart.data.datasets[2].data = timeSeriesDataNet.finalForPartial;
        }
        timeSeriesChart.update('none'); // تحديث بدون رسوم متحركة

        // 2. تحديث رسم المستخلصات المصروفة
        if (showGross) {
            disbursementTimeSeriesChart.data.datasets[0].data = disbursementDataGross.partial;
            disbursementTimeSeriesChart.data.datasets[1].data = disbursementDataGross.finalRegular;
            disbursementTimeSeriesChart.data.datasets[2].data = disbursementDataGross.finalForPartial;
        } else {
            disbursementTimeSeriesChart.data.datasets[0].data = disbursementDataNet.partial;
            disbursementTimeSeriesChart.data.datasets[1].data = disbursementDataNet.finalRegular;
            disbursementTimeSeriesChart.data.datasets[2].data = disbursementDataNet.finalForPartial;
        }
        disbursementTimeSeriesChart.update('none');

        // 3. تحديث رسم المقارنة
        if (showGross) {
            comparisonChart.data.datasets[0].data = comparisonDataGross.created;
            comparisonChart.data.datasets[1].data = comparisonDataGross.disbursed;
        } else {
            comparisonChart.data.datasets[0].data = comparisonDataNet.created;
            comparisonChart.data.datasets[1].data = comparisonDataNet.disbursed;
        }
        comparisonChart.update('none');
    }
});

// دالة تصدير PDF باستخدام mPDF مع جميع الفلاتر
function exportToPDF() {
    // الحصول على المعاملات الحالية
    const urlParams = new URLSearchParams(window.location.search);
    const period = urlParams.get('period') || 'month';
    const startDate = urlParams.get('start_date') || '';
    const endDate = urlParams.get('end_date') || '';
    const year = urlParams.get('year') || new Date().getFullYear();

    // بناء رابط التصدير مع جميع المعاملات
    let exportUrl = 'reports-pdf-mpdf.php?period=' + encodeURIComponent(period);

    // إضافة المعاملات حسب نوع الفترة
    if (period === 'custom') {
        exportUrl += '&custom_start=' + encodeURIComponent(startDate) + '&custom_end=' + encodeURIComponent(endDate);
    } else if (['q1', 'q2', 'q3', 'q4'].includes(period)) {
        exportUrl += '&year=' + encodeURIComponent(year);
    }

    // إضافة start_date و end_date للفترات الأخرى أيضاً
    if (startDate) {
        exportUrl += '&start_date=' + encodeURIComponent(startDate);
    }
    if (endDate) {
        exportUrl += '&end_date=' + encodeURIComponent(endDate);
    }

    // عرض رسالة تحميل
    Swal.fire({
        title: 'جاري إنشاء التقرير...',
        html: 'يرجى الانتظار بينما يتم إنشاء ملف PDF الشامل مع جميع الإحصائيات',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // فتح رابط التحميل
    window.location.href = exportUrl;

    // إغلاق رسالة التحميل بعد ثانيتين
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            title: 'تم!',
            text: 'تم إنشاء التقرير بنجاح',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        });
    }, 2000);
}
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

