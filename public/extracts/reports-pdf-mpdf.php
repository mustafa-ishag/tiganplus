<?php
/**
 * تصدير تقرير المستخلصات الشامل إلى PDF باستخدام mPDF
 * Export Comprehensive Extracts Report to PDF using mPDF
 * يتضمن جميع الرسوم البيانية والإحصائيات
 */

// منع أي مخرجات قبل PDF
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('يجب تسجيل الدخول أولاً');
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من الصلاحيات
if (!hasPermission('extracts_reports')) {
    http_response_code(403);
    die('ليس لديك صلاحية لعرض التقارير');
}

require_once __DIR__ . '/../../vendor/autoload.php';

// إعداد الاتصال بقاعدة البيانات
$db = getDB();

// الحصول على الفترة الزمنية من الطلب (افتراضياً: آخر 30 يوم)
$period = $_GET['period'] ?? 'month';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$customStart = $_GET['custom_start'] ?? null;
$customEnd = $_GET['custom_end'] ?? null;
$year = $_GET['year'] ?? date('Y');

// تحديد الفترة الزمنية (نفس الكود من reports.php)
switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        $periodName = 'آخر أسبوع';
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        $periodName = 'آخر شهر';
        break;
    case 'q1':
    case 'q2':
    case 'q3':
    case 'q4':
        $quarterMap = [
            'q1' => [1, 3],
            'q2' => [4, 6],
            'q3' => [7, 9],
            'q4' => [10, 12]
        ];
        $months = $quarterMap[$period];
        $startDate = date('Y-m-01', strtotime("$year-{$months[0]}-01"));
        $endDate = date('Y-m-t', strtotime("$year-{$months[1]}-01"));
        $periodName = "الربع " . ['q1' => 'الأول', 'q2' => 'الثاني', 'q3' => 'الثالث', 'q4' => 'الرابع'][$period] . " $year";
        break;
    case 'year':
        $startDate = date('Y-m-01', strtotime('-11 months'));
        $endDate = date('Y-m-d');
        $periodName = 'آخر سنة';
        break;
    case 'all_yearly':
    case 'all_monthly':
        $minDates = [];
        $minPartial = $db->query("SELECT MIN(extract_date) as min_date FROM partial_extracts")->fetch(PDO::FETCH_ASSOC);
        if ($minPartial['min_date']) $minDates[] = $minPartial['min_date'];
        $minFinalRegular = $db->query("SELECT MIN(extract_date) as min_date FROM final_regular_extracts")->fetch(PDO::FETCH_ASSOC);
        if ($minFinalRegular['min_date']) $minDates[] = $minFinalRegular['min_date'];
        $minFinalForPartial = $db->query("SELECT MIN(extract_date) as min_date FROM final_for_partial_extracts")->fetch(PDO::FETCH_ASSOC);
        if ($minFinalForPartial['min_date']) $minDates[] = $minFinalForPartial['min_date'];
        $startDate = !empty($minDates) ? min($minDates) : '2020-01-01';
        $endDate = date('Y-m-d');
        $periodName = $period === 'all_yearly' ? 'كل الأوقات (سنوي)' : 'كل الأوقات (شهري)';
        break;
    case 'custom':
        $startDate = $customStart ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $customEnd ?? date('Y-m-d');
        $periodName = "من $startDate إلى $endDate";
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        $periodName = 'آخر شهر';
}

// ========== جمع البيانات ==========

// التحقق من وجود حقل department
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

// حساب الإجماليات
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

// 4. إحصائيات حسب الفرع
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
        'partial_gross' => 0,
        'final_regular_gross' => 0,
        'final_for_partial_gross' => 0
    ],
    'projects' => [
        'partial' => 0,
        'final_regular' => 0,
        'final_for_partial' => 0,
        'partial_gross' => 0,
        'final_regular_gross' => 0,
        'final_for_partial_gross' => 0
    ]
];

foreach ($partialStats as $stat) {
    if (isset($stat['department']) && $stat['department'] && in_array($stat['department'], ['connections', 'projects'])) {
        $departmentStats[$stat['department']]['partial'] += floatval($stat['total_net'] ?? 0);
        $departmentStats[$stat['department']]['partial_gross'] += floatval($stat['total_amount'] ?? 0);
    }
}

foreach ($finalRegularStats as $stat) {
    if (isset($stat['department']) && $stat['department'] && in_array($stat['department'], ['connections', 'projects'])) {
        $departmentStats[$stat['department']]['final_regular'] += floatval($stat['total_net'] ?? 0);
        $departmentStats[$stat['department']]['final_regular_gross'] += floatval($stat['total_amount'] ?? 0);
    }
}

foreach ($finalForPartialStats as $stat) {
    if (isset($stat['department']) && $stat['department'] && in_array($stat['department'], ['connections', 'projects'])) {
        $departmentStats[$stat['department']]['final_for_partial'] += floatval($stat['total_net'] ?? 0);
        $departmentStats[$stat['department']]['final_for_partial_gross'] += floatval($stat['total_amount'] ?? 0);
    }
}

// مراحل الاعتماد
$approvalStagesFromDB = $db->query("
    SELECT stage_key, stage_name, stage_color, stage_order, is_active
    FROM approval_stages
    WHERE is_active = 1
    ORDER BY stage_order
")->fetchAll(PDO::FETCH_ASSOC);

$approvalStages = [];
foreach ($approvalStagesFromDB as $stage) {
    // المستخلصات الجزئية
    $partialData = $db->query("
        SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total
        FROM partial_extracts
        WHERE approval_stage = '{$stage['stage_key']}'
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();

    // المستخلصات النهائية العادية
    $finalRegularData = $db->query("
        SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total
        FROM final_regular_extracts
        WHERE approval_stage = '{$stage['stage_key']}'
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();

    // المستخلصات النهائية للجزئية
    $finalForPartialData = $db->query("
        SELECT COUNT(*) as count, COALESCE(SUM(net_amount), 0) as total
        FROM final_for_partial_extracts
        WHERE approval_stage = '{$stage['stage_key']}'
        AND extract_date BETWEEN '$startDate' AND '$endDate'
    ")->fetch();

    $approvalStages[] = [
        'stage_name' => $stage['stage_name'],
        'stage_color' => $stage['stage_color'],
        'partial_count' => $partialData['count'],
        'partial_amount' => $partialData['total'],
        'final_regular_count' => $finalRegularData['count'],
        'final_regular_amount' => $finalRegularData['total'],
        'final_for_partial_count' => $finalForPartialData['count'],
        'final_for_partial_amount' => $finalForPartialData['total'],
        'total_count' => $partialData['count'] + $finalRegularData['count'] + $finalForPartialData['count'],
        'total_amount' => $partialData['total'] + $finalRegularData['total'] + $finalForPartialData['total']
    ];
}

// 6. جلب مراحل الاعتماد من قاعدة البيانات
$stageNames = [];
$stageColors = [];
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
    // في حالة عدم وجود جدول approval_stages
    $stageNames = [
        'technical_support' => 'الدعم الفني',
        'construction' => 'التنفيذ',
        'department_manager' => 'مدير القسم',
        'administration_manager' => 'مدير الإدارة',
        'finance' => 'المالية',
        'disbursed' => 'مصروف'
    ];
}

// إنشاء كائن mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_header' => 10,
    'margin_footer' => 10,
    'default_font' => 'dejavusans',
    'directionality' => 'rtl',
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
]);

// تعيين العنوان
$mpdf->SetTitle('تقرير المستخلصات الشامل');
$mpdf->SetAuthor('نظام إدارة المستخلصات');
$mpdf->SetCreator('نظام إدارة المستخلصات');

// تنظيف أي مخرجات سابقة
ob_end_clean();

// بدء محتوى HTML
ob_start();
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'dejavusans';
            direction: rtl;
            text-align: right;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3498db;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header .period {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .header .date {
            color: #95a5a6;
            font-size: 12px;
        }
        
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: linear-gradient(135deg, #176cb4 0%, #4fa5e6 50%, #176cb4 100%);
            color: white;
            padding: 12px 20px;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .stats-row {
            display: table-row;
        }
        
        .stat-card {
            display: table-cell;
            padding: 15px;
            margin: 5px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid;
            width: 33%;
        }
        
        .stat-card.blue { border-color: #3498db; background: #ebf5fb; }
        .stat-card.green { border-color: #2ecc71; background: #eafaf1; }
        .stat-card.purple { border-color: #9b59b6; background: #f4ecf7; }
        .stat-card.orange { border-color: #e67e22; background: #fef5e7; }
        .stat-card.red { border-color: #e74c3c; background: #fadbd8; }
        .stat-card.yellow { border-color: #f39c12; background: #fef9e7; }
        
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            font-size: 12px;
            color: #555;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        table th {
            background: #34495e;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        table td {
            padding: 8px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        
        table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #bdc3c7;
            font-size: 10px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <!-- الترويسة -->
    <div class="header">
        <h1>تقرير المستخلصات الشامل</h1>
        <div class="period">الفترة: <?php echo $periodName; ?></div>
        <div class="date">تاريخ الإنشاء: <?php echo date('Y-m-d H:i:s'); ?></div>
    </div>

    <!-- الصفحة 1: الإحصائيات العامة -->
    <div class="section">
        <div class="section-title">الإحصائيات العامة</div>

        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td class="stat-card blue" style="width: 33%;">
                    <div class="value"><?php echo number_format($grandTotalNet, 2); ?></div>
                    <div class="label">إجمالي المستخلصات (صافي)</div>
                </td>
                <td class="stat-card green" style="width: 33%;">
                    <div class="value"><?php echo number_format($totalPartialNet, 2); ?></div>
                    <div class="label">المستخلصات الجزئية</div>
                </td>
                <td class="stat-card purple" style="width: 33%;">
                    <div class="value"><?php echo number_format($totalFinalRegularNet, 2); ?></div>
                    <div class="label">المستخلصات النهائية العادية</div>
                </td>
            </tr>
        </table>

        <table style="width: 100%;">
            <tr>
                <td class="stat-card orange" style="width: 33%;">
                    <div class="value"><?php echo number_format($totalFinalForPartialNet, 2); ?></div>
                    <div class="label">المستخلصات النهائية للجزئية</div>
                </td>
                <td class="stat-card red" style="width: 33%;">
                    <div class="value"><?php echo number_format($totalPenalties, 2); ?> ر.س</div>
                    <div class="label">إجمالي الغرامات</div>
                </td>
                <td class="stat-card yellow" style="width: 33%;">
                    <div class="value"><?php echo number_format($grandTotalGross, 2); ?> ر.س</div>
                    <div class="label">إجمالي المبالغ (إجمالي)</div>
                </td>
            </tr>
        </table>

    </div>

    <!-- الصفحة 2: مراحل الاعتماد -->
    <div class="section">
        <div class="section-title">توزيع المستخلصات حسب مراحل الاعتماد</div>

        <h3 style="margin: 15px 0 10px 0; color: #2c3e50;">المبالغ (ر.س)</h3>
        <table>
            <thead>
                <tr>
                    <th>المرحلة</th>
                    <th>جزئي</th>
                    <th>نهائي عادي</th>
                    <th>نهائي للجزئية</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stagePartialTotal = 0;
                $stageFinalRegularTotal = 0;
                $stageFinalForPartialTotal = 0;
                $stageGrandTotal = 0;

                foreach ($stageNames as $stageKey => $stageName):
                    // حساب المبالغ لكل مرحلة
                    $stagePartial = 0;
                    $stageFinalRegular = 0;
                    $stageFinalForPartial = 0;

                    foreach ($partialStats as $stat) {
                        if (isset($stat['approval_stage']) && $stat['approval_stage'] === $stageKey) {
                            $stagePartial += floatval($stat['total_net'] ?? 0);
                        }
                    }

                    foreach ($finalRegularStats as $stat) {
                        if (isset($stat['approval_stage']) && $stat['approval_stage'] === $stageKey) {
                            $stageFinalRegular += floatval($stat['total_net'] ?? 0);
                        }
                    }

                    foreach ($finalForPartialStats as $stat) {
                        if (isset($stat['approval_stage']) && $stat['approval_stage'] === $stageKey) {
                            $stageFinalForPartial += floatval($stat['total_net'] ?? 0);
                        }
                    }

                    $stageTotal = $stagePartial + $stageFinalRegular + $stageFinalForPartial;
                    $stagePartialTotal += $stagePartial;
                    $stageFinalRegularTotal += $stageFinalRegular;
                    $stageFinalForPartialTotal += $stageFinalForPartial;
                    $stageGrandTotal += $stageTotal;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($stageName); ?></td>
                    <td><?php echo number_format($stagePartial, 2); ?></td>
                    <td><?php echo number_format($stageFinalRegular, 2); ?></td>
                    <td><?php echo number_format($stageFinalForPartial, 2); ?></td>
                    <td><strong><?php echo number_format($stageTotal, 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #ecf0f1; font-weight: bold;">
                    <td>الإجمالي</td>
                    <td><?php echo number_format($stagePartialTotal, 2); ?></td>
                    <td><?php echo number_format($stageFinalRegularTotal, 2); ?></td>
                    <td><?php echo number_format($stageFinalForPartialTotal, 2); ?></td>
                    <td><?php echo number_format($stageGrandTotal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- الصفحة 3: الأقسام -->
    <div class="section">
        <div class="section-title">توزيع المستخلصات حسب القسم</div>

        <h3 style="margin: 15px 0 10px 0; color: #2c3e50;">المبالغ (ر.س)</h3>
        <table>
            <thead>
                <tr>
                    <th>القسم</th>
                    <th>جزئي</th>
                    <th>نهائي عادي</th>
                    <th>نهائي للجزئية</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>التوصيلات</td>
                    <td><?php echo number_format($departmentStats['connections']['partial'] ?? 0, 2); ?></td>
                    <td><?php echo number_format($departmentStats['connections']['final_regular'] ?? 0, 2); ?></td>
                    <td><?php echo number_format($departmentStats['connections']['final_for_partial'] ?? 0, 2); ?></td>
                    <td><strong><?php
                        $connectionsAmountTotal = ($departmentStats['connections']['partial'] ?? 0) +
                                          ($departmentStats['connections']['final_regular'] ?? 0) +
                                          ($departmentStats['connections']['final_for_partial'] ?? 0);
                        echo number_format($connectionsAmountTotal, 2);
                    ?></strong></td>
                </tr>
                <tr>
                    <td>المشاريع</td>
                    <td><?php echo number_format($departmentStats['projects']['partial'] ?? 0, 2); ?></td>
                    <td><?php echo number_format($departmentStats['projects']['final_regular'] ?? 0, 2); ?></td>
                    <td><?php echo number_format($departmentStats['projects']['final_for_partial'] ?? 0, 2); ?></td>
                    <td><strong><?php
                        $projectsAmountTotal = ($departmentStats['projects']['partial'] ?? 0) +
                                       ($departmentStats['projects']['final_regular'] ?? 0) +
                                       ($departmentStats['projects']['final_for_partial'] ?? 0);
                        echo number_format($projectsAmountTotal, 2);
                    ?></strong></td>
                </tr>
                <tr style="background: #ecf0f1; font-weight: bold;">
                    <td>الإجمالي</td>
                    <td><?php echo number_format(($departmentStats['connections']['partial'] ?? 0) + ($departmentStats['projects']['partial'] ?? 0), 2); ?></td>
                    <td><?php echo number_format(($departmentStats['connections']['final_regular'] ?? 0) + ($departmentStats['projects']['final_regular'] ?? 0), 2); ?></td>
                    <td><?php echo number_format(($departmentStats['connections']['final_for_partial'] ?? 0) + ($departmentStats['projects']['final_for_partial'] ?? 0), 2); ?></td>
                    <td><?php echo number_format($connectionsAmountTotal + $projectsAmountTotal, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- الصفحة 4: الفروع -->
    <div class="section">
        <div class="section-title">توزيع المستخلصات حسب الفرع</div>

        <h3 style="margin: 15px 0 10px 0; color: #2c3e50;">المبالغ (ر.س)</h3>
        <table>
            <thead>
                <tr>
                    <th>الفرع</th>
                    <th>جزئي</th>
                    <th>نهائي عادي</th>
                    <th>نهائي للجزئية</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // تجميع البيانات حسب الفرع
                $branchSummary = [];
                foreach ($branchStats as $branch) {
                    $branchId = $branch['branch_id'];
                    $branchName = $branch['branch_name'];

                    if (!isset($branchSummary[$branchId])) {
                        $branchSummary[$branchId] = [
                            'name' => $branchName,
                            'partial' => 0,
                            'final_regular' => 0,
                            'final_for_partial' => 0
                        ];
                    }

                    if ($branch['extract_type'] === 'partial') {
                        $branchSummary[$branchId]['partial'] = floatval($branch['total_net']);
                    } elseif ($branch['extract_type'] === 'final_regular') {
                        $branchSummary[$branchId]['final_regular'] = floatval($branch['total_net']);
                    } elseif ($branch['extract_type'] === 'final_for_partial') {
                        $branchSummary[$branchId]['final_for_partial'] = floatval($branch['total_net']);
                    }
                }

                $totalBranchPartialAmount = 0;
                $totalBranchFinalRegularAmount = 0;
                $totalBranchFinalForPartialAmount = 0;
                $totalBranchAmount = 0;

                foreach ($branchSummary as $branchId => $branch):
                    $branchAmountTotal = $branch['partial'] + $branch['final_regular'] + $branch['final_for_partial'];
                    $totalBranchPartialAmount += $branch['partial'];
                    $totalBranchFinalRegularAmount += $branch['final_regular'];
                    $totalBranchFinalForPartialAmount += $branch['final_for_partial'];
                    $totalBranchAmount += $branchAmountTotal;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($branch['name']); ?></td>
                    <td><?php echo number_format($branch['partial'], 2); ?></td>
                    <td><?php echo number_format($branch['final_regular'], 2); ?></td>
                    <td><?php echo number_format($branch['final_for_partial'], 2); ?></td>
                    <td><strong><?php echo number_format($branchAmountTotal, 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background: #ecf0f1; font-weight: bold;">
                    <td>الإجمالي</td>
                    <td><?php echo number_format($totalBranchPartialAmount, 2); ?></td>
                    <td><?php echo number_format($totalBranchFinalRegularAmount, 2); ?></td>
                    <td><?php echo number_format($totalBranchFinalForPartialAmount, 2); ?></td>
                    <td><?php echo number_format($totalBranchAmount, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- الصفحة 5: الرسوم البيانية والمخططات -->
    <div class="section" style="page-break-before: always;">
        <div class="section-title">الرسوم البيانية والمخططات</div>

        <h3 style="margin: 15px 0 10px 0; color: #2c3e50;">توزيع المستخلصات حسب النوع</h3>
        <table style="width: 100%; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>نوع المستخلص</th>
                    <th>النسبة المئوية</th>
                    <th>المبلغ (ر.س)</th>
                    <th>التمثيل البياني</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalAllExtracts = $totalPartialNet + $totalFinalRegularNet + $totalFinalForPartialNet;
                $partialPercent = $totalAllExtracts > 0 ? ($totalPartialNet / $totalAllExtracts) * 100 : 0;
                $finalRegularPercent = $totalAllExtracts > 0 ? ($totalFinalRegularNet / $totalAllExtracts) * 100 : 0;
                $finalForPartialPercent = $totalAllExtracts > 0 ? ($totalFinalForPartialNet / $totalAllExtracts) * 100 : 0;

                $barLength = 30;
                ?>
                <tr>
                    <td>المستخلصات الجزئية</td>
                    <td><?php echo number_format($partialPercent, 1); ?>%</td>
                    <td><?php echo number_format($totalPartialNet, 2); ?></td>
                    <td><?php echo str_repeat('█', (int)($partialPercent / 100 * $barLength)); ?></td>
                </tr>
                <tr>
                    <td>المستخلصات النهائية العادية</td>
                    <td><?php echo number_format($finalRegularPercent, 1); ?>%</td>
                    <td><?php echo number_format($totalFinalRegularNet, 2); ?></td>
                    <td><?php echo str_repeat('█', (int)($finalRegularPercent / 100 * $barLength)); ?></td>
                </tr>
                <tr>
                    <td>المستخلصات النهائية للجزئية</td>
                    <td><?php echo number_format($finalForPartialPercent, 1); ?>%</td>
                    <td><?php echo number_format($totalFinalForPartialNet, 2); ?></td>
                    <td><?php echo str_repeat('█', (int)($finalForPartialPercent / 100 * $barLength)); ?></td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin: 20px 0 10px 0; color: #2c3e50;">توزيع المستخلصات حسب القسم</h3>
        <table style="width: 100%; margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>القسم</th>
                    <th>النسبة المئوية</th>
                    <th>المبلغ (ر.س)</th>
                    <th>التمثيل البياني</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $connectionsTotal = ($departmentStats['connections']['partial'] ?? 0) +
                                   ($departmentStats['connections']['final_regular'] ?? 0) +
                                   ($departmentStats['connections']['final_for_partial'] ?? 0);
                $projectsTotal = ($departmentStats['projects']['partial'] ?? 0) +
                                ($departmentStats['projects']['final_regular'] ?? 0) +
                                ($departmentStats['projects']['final_for_partial'] ?? 0);
                $departmentGrandTotal = $connectionsTotal + $projectsTotal;

                $connectionsPercent = $departmentGrandTotal > 0 ? ($connectionsTotal / $departmentGrandTotal) * 100 : 0;
                $projectsPercent = $departmentGrandTotal > 0 ? ($projectsTotal / $departmentGrandTotal) * 100 : 0;
                ?>
                <tr>
                    <td>التوصيلات</td>
                    <td><?php echo number_format($connectionsPercent, 1); ?>%</td>
                    <td><?php echo number_format($connectionsTotal, 2); ?></td>
                    <td><?php echo str_repeat('█', (int)($connectionsPercent / 100 * $barLength)); ?></td>
                </tr>
                <tr>
                    <td>المشاريع</td>
                    <td><?php echo number_format($projectsPercent, 1); ?>%</td>
                    <td><?php echo number_format($projectsTotal, 2); ?></td>
                    <td><?php echo str_repeat('█', (int)($projectsPercent / 100 * $barLength)); ?></td>
                </tr>
            </tbody>
        </table>

        <h3 style="margin: 20px 0 10px 0; color: #2c3e50;">أعلى 5 فروع من حيث المبالغ</h3>
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>الفرع</th>
                    <th>المبلغ (ر.س)</th>
                    <th>النسبة المئوية</th>
                    <th>التمثيل البياني</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ترتيب الفروع حسب المبالغ
                $branchTotals = [];
                foreach ($branchSummary as $branchId => $branch) {
                    $branchTotal = $branch['partial'] + $branch['final_regular'] + $branch['final_for_partial'];
                    $branchTotals[] = [
                        'name' => $branch['name'],
                        'total' => $branchTotal
                    ];
                }

                usort($branchTotals, function($a, $b) {
                    return $b['total'] <=> $a['total'];
                });

                $topBranches = array_slice($branchTotals, 0, 5);
                $maxBranchAmount = !empty($topBranches) ? $topBranches[0]['total'] : 1;

                foreach ($topBranches as $branch):
                    $branchPercent = $maxBranchAmount > 0 ? ($branch['total'] / $maxBranchAmount) * 100 : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($branch['name']); ?></td>
                    <td><?php echo number_format($branch['total'], 2); ?></td>
                    <td><?php echo number_format($branchPercent, 1); ?>%</td>
                    <td><?php echo str_repeat('█', (int)($branchPercent / 100 * $barLength)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- التذييل -->
    <div class="footer">
        تم إنشاء هذا التقرير تلقائياً بواسطة نظام إدارة المستخلصات | <?php echo date('Y-m-d H:i:s'); ?>
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

// كتابة HTML إلى PDF
$mpdf->WriteHTML($html);

// إخراج PDF
$mpdf->Output('تقرير_المستخلصات_' . date('Y-m-d') . '.pdf', 'D');
?>

