<?php
/**
 * ملف البيانات المشترك لتقارير المستخلصات
 * Shared Data File for Extracts Reports
 * يستخدم من قبل reports.php و reports-pdf.php
 */

// التأكد من أن هذا الملف لا يتم الوصول إليه مباشرة
if (!isset($db) || !isset($startDate) || !isset($endDate)) {
    die('هذا الملف لا يمكن الوصول إليه مباشرة');
}

// ===== 1. الإحصائيات العامة =====

// إجمالي المستخلصات
$totalPartialExtracts = $db->query("
    SELECT COUNT(*) as count 
    FROM partial_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch()['count'];

$totalFinalRegularExtracts = $db->query("
    SELECT COUNT(*) as count 
    FROM final_regular_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch()['count'];

$totalFinalForPartialExtracts = $db->query("
    SELECT COUNT(*) as count 
    FROM final_for_partial_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch()['count'];

$totalExtracts = $totalPartialExtracts + $totalFinalRegularExtracts + $totalFinalForPartialExtracts;

// إجمالي الغرامات
// ملاحظة: جدول partial_extracts لا يحتوي على عمود total_penalty_amount
// الغرامات موجودة فقط في الجداول النهائية

$partialPenalties = 0; // المستخلصات الجزئية لا تحتوي على غرامات

$finalRegularPenalties = $db->query("
    SELECT COALESCE(SUM(total_penalty_amount), 0) as total
    FROM final_regular_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch()['total'];

$finalForPartialPenalties = $db->query("
    SELECT COALESCE(SUM(total_penalty_amount), 0) as total
    FROM final_for_partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch()['total'];

$totalPenalties = $finalRegularPenalties + $finalForPartialPenalties;

// المبالغ المصروفة (مرحلة مصروف أو مالية الطائف)
$partialDisbursed = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM partial_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    AND approval_stage IN ('disbursed', 'taif_finance')
")->fetch()['total'];

$finalRegularDisbursed = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM final_regular_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    AND approval_stage IN ('disbursed', 'taif_finance')
")->fetch()['total'];

$finalForPartialDisbursed = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM final_for_partial_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    AND approval_stage IN ('disbursed', 'taif_finance')
")->fetch()['total'];

$totalDisbursed = $partialDisbursed + $finalRegularDisbursed + $finalForPartialDisbursed;

// المبالغ المتبقية (غير مصروفة)
$partialRemaining = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM partial_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    AND approval_stage NOT IN ('disbursed', 'taif_finance')
")->fetch()['total'];

$finalRegularRemaining = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM final_regular_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    AND approval_stage NOT IN ('disbursed', 'taif_finance')
")->fetch()['total'];

$finalForPartialRemaining = $db->query("
    SELECT COALESCE(SUM(net_amount), 0) as total 
    FROM final_for_partial_extracts 
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
    AND approval_stage NOT IN ('disbursed', 'taif_finance')
")->fetch()['total'];

$totalRemaining = $partialRemaining + $finalRegularRemaining + $finalForPartialRemaining;

// ===== 2. مراحل الاعتماد الديناميكية =====

// جلب مراحل الاعتماد من قاعدة البيانات
$approvalStagesFromDB = $db->query("
    SELECT stage_key, stage_name, stage_color, stage_order, is_active
    FROM approval_stages
    WHERE is_active = 1
    ORDER BY stage_order
")->fetchAll(PDO::FETCH_ASSOC);

$stageNames = [];
$stageColors = [];
foreach ($approvalStagesFromDB as $stage) {
    $stageNames[$stage['stage_key']] = $stage['stage_name'];
    $stageColors[$stage['stage_key']] = $stage['stage_color'];
}

// إحصائيات المراحل
$stageStats = [];
$totalPartial = 0;
$totalFinalRegular = 0;
$totalFinalForPartial = 0;

foreach ($stageNames as $stageKey => $stageName) {
    // المستخلصات الجزئية
    $partialAmount = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total, COUNT(*) as count
        FROM partial_extracts
        WHERE approval_stage = '$stageKey'
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();
    
    // المستخلصات النهائية العادية
    $finalRegularAmount = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM final_regular_extracts
        WHERE approval_stage = '$stageKey'
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();
    
    // المستخلصات النهائية للجزئية
    $finalForPartialAmount = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM final_for_partial_extracts
        WHERE approval_stage = '$stageKey'
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();
    
    $stageStats[$stageKey] = [
        'partial' => floatval($partialAmount['total']),
        'final_regular' => floatval($finalRegularAmount['total']),
        'final_for_partial' => floatval($finalForPartialAmount['total']),
        'count' => intval($partialAmount['count'])
    ];
    
    $totalPartial += $stageStats[$stageKey]['partial'];
    $totalFinalRegular += $stageStats[$stageKey]['final_regular'];
    $totalFinalForPartial += $stageStats[$stageKey]['final_for_partial'];
}

$grandTotal = $totalPartial + $totalFinalRegular + $totalFinalForPartial;

// ===== 3. إحصائيات الأقسام =====

$departmentStats = [
    'connections' => [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0
    ],
    'projects' => [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0
    ]
];

// المستخلصات الجزئية
$partialDeptStats = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN department = 'connections' THEN net_amount ELSE 0 END), 0) as connections,
        COALESCE(SUM(CASE WHEN department = 'projects' THEN net_amount ELSE 0 END), 0) as projects
    FROM partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch();

$departmentStats['connections']['partial'] = floatval($partialDeptStats['connections']);
$departmentStats['projects']['partial'] = floatval($partialDeptStats['projects']);

// المستخلصات النهائية العادية
$finalRegularDeptStats = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN department = 'connections' THEN net_amount ELSE 0 END), 0) as connections,
        COALESCE(SUM(CASE WHEN department = 'projects' THEN net_amount ELSE 0 END), 0) as projects
    FROM final_regular_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch();

$departmentStats['connections']['final_regular'] = floatval($finalRegularDeptStats['connections']);
$departmentStats['projects']['final_regular'] = floatval($finalRegularDeptStats['projects']);

// المستخلصات النهائية للجزئية
$finalForPartialDeptStats = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN department = 'connections' THEN net_amount ELSE 0 END), 0) as connections,
        COALESCE(SUM(CASE WHEN department = 'projects' THEN net_amount ELSE 0 END), 0) as projects
    FROM final_for_partial_extracts
    WHERE extract_date BETWEEN '$startDate' AND '$endDate'
")->fetch();

$departmentStats['connections']['final_for_partial'] = floatval($finalForPartialDeptStats['connections']);
$departmentStats['projects']['final_for_partial'] = floatval($finalForPartialDeptStats['projects']);

// ===== 4. إحصائيات الفروع الديناميكية =====

// جلب الفروع النشطة
$branchesFromDB = $db->query("
    SELECT id, name, code
    FROM branches
    WHERE status = 'active'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$branchStats = [];

foreach ($branchesFromDB as $branch) {
    $branchId = $branch['id'];
    
    // المستخلصات الجزئية
    $partialBranchAmount = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total, COUNT(*) as count
        FROM partial_extracts
        WHERE branch_id = $branchId
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();
    
    // المستخلصات النهائية العادية
    $finalRegularBranchAmount = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total, COUNT(*) as count
        FROM final_regular_extracts
        WHERE branch_id = $branchId
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();
    
    // المستخلصات النهائية للجزئية
    $finalForPartialBranchAmount = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total, COUNT(*) as count
        FROM final_for_partial_extracts
        WHERE branch_id = $branchId
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();
    
    $branchStats[$branchId] = [
        'name' => $branch['name'],
        'code' => $branch['code'],
        'partial' => floatval($partialBranchAmount['total']),
        'partial_count' => intval($partialBranchAmount['count']),
        'final_regular' => floatval($finalRegularBranchAmount['total']),
        'final_regular_count' => intval($finalRegularBranchAmount['count']),
        'final_for_partial' => floatval($finalForPartialBranchAmount['total']),
        'final_for_partial_count' => intval($finalForPartialBranchAmount['count'])
    ];
}

// ===== 5. التوزيع الشهري =====

$monthlyStats = [];
for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    $monthName = date('Y-m', strtotime("-$i months"));
    
    $partialMonthly = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM partial_extracts
        WHERE extract_date BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetch()['total'];
    
    $finalRegularMonthly = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM final_regular_extracts
        WHERE extract_date BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetch()['total'];
    
    $finalForPartialMonthly = $db->query("
        SELECT COALESCE(SUM(net_amount), 0) as total
        FROM final_for_partial_extracts
        WHERE extract_date BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetch()['total'];
    
    $monthlyStats[$monthName] = [
        'partial' => floatval($partialMonthly),
        'final_regular' => floatval($finalRegularMonthly),
        'final_for_partial' => floatval($finalForPartialMonthly)
    ];
}

