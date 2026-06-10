<?php
/**
 * صفحة تقارير أوامر العمل الشاملة
 * Comprehensive Work Orders Reports Page
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

$db = getDB();
$pageTitle = 'تقارير أوامر العمل';
$currentPage = 'work-orders-reports';

$breadcrumbs = [
    ['title' => 'الرئيسية', 'url' => 'dashboard.php'],
    ['title' => 'أوامر العمل', 'url' => 'work-orders/index.php'],
    ['title' => 'التقارير الشاملة', 'url' => 'work-orders/reports.php']
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
        $currentYear = $_GET['year'] ?? date('Y');
        $quarterMap = [
            'q1' => [1, 3],
            'q2' => [4, 6],
            'q3' => [7, 9],
            'q4' => [10, 12]
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
        // استخدام تاريخ التكليف أو تاريخ الإنشاء كبديل
        $minDate = $db->query("SELECT MIN(COALESCE(assignment_date, created_at)) as min_date FROM work_orders")->fetch(PDO::FETCH_ASSOC);
        $startDate = $minDate['min_date'] ? date('Y-m-d', strtotime($minDate['min_date'])) : '2020-01-01';
        $endDate = date('Y-m-d');
        break;
    case 'custom':
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
}

// ========== جمع البيانات ==========

// 1. إحصائيات أوامر العمل الكلية (حسب تاريخ التكليف)
$totalWorkOrdersQuery = "
    SELECT
        COUNT(*) as total_count,
        SUM(estimated_value) as total_estimated,
        SUM(actual_value) as total_actual
    FROM work_orders
    WHERE DATE(COALESCE(assignment_date, created_at)) BETWEEN '$startDate' AND '$endDate'
";
$totalStats = $db->query($totalWorkOrdersQuery)->fetch(PDO::FETCH_ASSOC);

// 1.1 أوامر العمل التي شهادة الإنجاز مرفقة (للقيم الفعلية)
$withCompletionCertQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(wo.actual_value) as total_actual
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
    WHERE woa.form_type = 'completion_certificate'
    AND woa.status = 'attached'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$withCompletionCertStats = $db->query($withCompletionCertQuery)->fetch(PDO::FETCH_ASSOC);

// 1.2 أوامر العمل التي شهادة الإنجاز غير مرفقة (للقيم التقديرية)
$withoutCompletionCertQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(wo.estimated_value) as total_estimated
    FROM work_orders wo
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    WHERE woa.id IS NULL
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$withoutCompletionCertStats = $db->query($withoutCompletionCertQuery)->fetch(PDO::FETCH_ASSOC);

// 2. أوامر العمل المفوترة (التي دخلت مستخلص نهائي - حسب تاريخ التكليف)
// حساب القيمة الكاملة لأمر العمل
$invoicedWorkOrdersQuery = "
    SELECT
        COUNT(DISTINCT id) as count,
        SUM(total_value) as total_value
    FROM (
        -- أوامر العمل في المستخلصات النهائية العادية
        SELECT DISTINCT wo.id,
               frewo.extract_value as total_value
        FROM work_orders wo
        INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'

        UNION

        -- أوامر العمل في المستخلصات النهائية للجزئية
        -- القيمة = قيمة أمر العمل في الجزئي المرتبط + قيمة أمر العمل في النهائي للجزئية
        SELECT DISTINCT wo.id,
               (COALESCE(pewo.extract_value, 0) + COALESCE(ffpewo.extract_value, 0)) as total_value
        FROM work_orders wo
        INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
            AND pewo.partial_extract_id = ffpe.related_partial_extract_id
        WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    ) as combined
";
$invoicedStats = $db->query($invoicedWorkOrdersQuery)->fetch(PDO::FETCH_ASSOC);

// 2.1 أوامر العمل المفوترة - المصروفة (في مستخلصات نهائية مصروفة)
// حساب القيمة الكاملة لأمر العمل:
// - النهائي العادي: مجموع قيم أوامر العمل في المستخلص
// - النهائي للجزئية: قيمة الجزئي + قيمة النهائي للجزئية
$invoicedDisbursedQuery = "
    SELECT
        COUNT(DISTINCT id) as count,
        SUM(total_value) as total_value
    FROM (
        -- أوامر العمل في المستخلصات النهائية العادية المصروفة
        SELECT DISTINCT wo.id,
               frewo.extract_value as total_value
        FROM work_orders wo
        INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE fre.approval_stage = 'disbursed'
        AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'

        UNION

        -- أوامر العمل في المستخلصات النهائية للجزئية المصروفة
        -- القيمة = قيمة أمر العمل في الجزئي المرتبط + قيمة أمر العمل في النهائي للجزئية
        SELECT DISTINCT wo.id,
               (COALESCE(pewo.extract_value, 0) + COALESCE(ffpewo.extract_value, 0)) as total_value
        FROM work_orders wo
        INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
            AND pewo.partial_extract_id = ffpe.related_partial_extract_id
        WHERE ffpe.approval_stage = 'disbursed'
        AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    ) as combined
";
$invoicedDisbursedStats = $db->query($invoicedDisbursedQuery)->fetch(PDO::FETCH_ASSOC);

// 2.2 أوامر العمل المفوترة - غير المصروفة (في مستخلصات نهائية غير مصروفة)
// حساب القيمة الكاملة لأمر العمل
$invoicedNotDisbursedQuery = "
    SELECT
        COUNT(DISTINCT id) as count,
        SUM(total_value) as total_value
    FROM (
        -- أوامر العمل في المستخلصات النهائية العادية غير المصروفة
        SELECT DISTINCT wo.id,
               frewo.extract_value as total_value
        FROM work_orders wo
        INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE fre.approval_stage != 'disbursed'
        AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
        AND NOT EXISTS (
            SELECT 1 FROM final_regular_extract_work_orders frewo2
            INNER JOIN final_regular_extracts fre2 ON frewo2.final_regular_extract_id = fre2.id
            WHERE frewo2.work_order_id = wo.id AND fre2.approval_stage = 'disbursed'
        )
        AND NOT EXISTS (
            SELECT 1 FROM final_for_partial_extract_work_orders ffpewo2
            INNER JOIN final_for_partial_extracts ffpe2 ON ffpewo2.final_for_partial_extract_id = ffpe2.id
            WHERE ffpewo2.work_order_id = wo.id AND ffpe2.approval_stage = 'disbursed'
        )

        UNION

        -- أوامر العمل في المستخلصات النهائية للجزئية غير المصروفة
        -- القيمة = قيمة أمر العمل في الجزئي المرتبط + قيمة أمر العمل في النهائي للجزئية
        SELECT DISTINCT wo.id,
               (COALESCE(pewo.extract_value, 0) + COALESCE(ffpewo.extract_value, 0)) as total_value
        FROM work_orders wo
        INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        LEFT JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
            AND pewo.partial_extract_id = ffpe.related_partial_extract_id
        WHERE ffpe.approval_stage != 'disbursed'
        AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
        AND NOT EXISTS (
            SELECT 1 FROM final_regular_extract_work_orders frewo2
            INNER JOIN final_regular_extracts fre2 ON frewo2.final_regular_extract_id = fre2.id
            WHERE frewo2.work_order_id = wo.id AND fre2.approval_stage = 'disbursed'
        )
        AND NOT EXISTS (
            SELECT 1 FROM final_for_partial_extract_work_orders ffpewo2
            INNER JOIN final_for_partial_extracts ffpe2 ON ffpewo2.final_for_partial_extract_id = ffpe2.id
            WHERE ffpewo2.work_order_id = wo.id AND ffpe2.approval_stage = 'disbursed'
        )
    ) as combined
";
$invoicedNotDisbursedStats = $db->query($invoicedNotDisbursedQuery)->fetch(PDO::FETCH_ASSOC);

// 3. أوامر العمل الجزئية - بشهادة إنجاز مرفقة (حسب تاريخ التكليف)
$partialWithCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(COALESCE(pewo.extract_value, 0)) as partial_value,
        COUNT(DISTINCT wo.id) as partial_count,
        SUM(CASE
            WHEN wo.actual_value > 0 THEN
                wo.actual_value - COALESCE(pewo.extract_value, 0)
            WHEN wo.estimated_value > 0 THEN
                wo.estimated_value - COALESCE(pewo.extract_value, 0)
            ELSE 0
        END) as remaining_value,
        COUNT(DISTINCT CASE
            WHEN (wo.actual_value > 0 AND wo.actual_value - COALESCE(pewo.extract_value, 0) != 0)
                OR (wo.actual_value = 0 AND wo.estimated_value > 0 AND wo.estimated_value - COALESCE(pewo.extract_value, 0) != 0)
            THEN wo.id
        END) as remaining_count
    FROM work_orders wo
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.form_type = 'completion_certificate'
    AND woa.status = 'attached'
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        WHERE ffpewo.work_order_id = wo.id AND ffpe.approval_stage = 'disbursed'
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE frewo.work_order_id = wo.id AND fre.approval_stage = 'disbursed'
    )
";
$partialWithCompletionStats = $db->query($partialWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);

// 4. أوامر العمل الجزئية - بدون شهادة إنجاز (حسب تاريخ التكليف)
$partialWithoutCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(COALESCE(pewo.extract_value, 0)) as partial_value,
        COUNT(DISTINCT wo.id) as partial_count,
        SUM(CASE
            WHEN wo.actual_value > 0 THEN
                wo.actual_value - COALESCE(pewo.extract_value, 0)
            WHEN wo.estimated_value > 0 THEN
                wo.estimated_value - COALESCE(pewo.extract_value, 0)
            ELSE 0
        END) as remaining_value,
        COUNT(DISTINCT CASE
            WHEN (wo.actual_value > 0 AND wo.actual_value - COALESCE(pewo.extract_value, 0) != 0)
                OR (wo.actual_value = 0 AND wo.estimated_value > 0 AND wo.estimated_value - COALESCE(pewo.extract_value, 0) != 0)
            THEN wo.id
        END) as remaining_count
    FROM work_orders wo
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        WHERE ffpewo.work_order_id = wo.id AND ffpe.approval_stage = 'disbursed'
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE frewo.work_order_id = wo.id AND fre.approval_stage = 'disbursed'
    )
";
$partialWithoutCompletionStats = $db->query($partialWithoutCompletionQuery)->fetch(PDO::FETCH_ASSOC);

// 5. أوامر العمل بدون مستخلص - بشهادة إنجاز مرفقة (حسب تاريخ التكليف)
// الجزء الأول: شهادة إنجاز مرفقة ومؤكدة
// الجزء الثاني: شهادة إنجاز مرفقة وغير مؤكدة
$noExtractWithCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(CASE WHEN wo.actual_value > 0 THEN wo.actual_value ELSE wo.estimated_value END) as total_value,
        COUNT(DISTINCT CASE
            WHEN woa.completion_certificate_confirmation = 'confirmed'
            THEN wo.id
        END) as confirmed_count,
        SUM(CASE
            WHEN woa.completion_certificate_confirmation = 'confirmed' THEN
                CASE WHEN wo.actual_value > 0 THEN wo.actual_value ELSE wo.estimated_value END
            ELSE 0
        END) as confirmed_value,
        COUNT(DISTINCT CASE
            WHEN woa.completion_certificate_confirmation != 'confirmed'
            THEN wo.id
        END) as not_confirmed_count,
        SUM(CASE
            WHEN woa.completion_certificate_confirmation != 'confirmed' THEN
                CASE WHEN wo.actual_value > 0 THEN wo.actual_value ELSE wo.estimated_value END
            ELSE 0
        END) as not_confirmed_value
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.form_type = 'completion_certificate'
    AND woa.status = 'attached'
    AND NOT EXISTS (
        SELECT 1 FROM partial_extract_work_orders pewo
        WHERE pewo.work_order_id = wo.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        WHERE frewo.work_order_id = wo.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        WHERE ffpewo.work_order_id = wo.id
    )
";
$noExtractWithCompletionStats = $db->query($noExtractWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);

// 6. أوامر العمل بدون مستخلص - بدون شهادة إنجاز (حسب تاريخ التكليف)
$noExtractNoCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(wo.estimated_value) as total_estimated
    FROM work_orders wo
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM partial_extract_work_orders pewo
        WHERE pewo.work_order_id = wo.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        WHERE frewo.work_order_id = wo.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        WHERE ffpewo.work_order_id = wo.id
    )
";
$noExtractNoCompletionStats = $db->query($noExtractNoCompletionQuery)->fetch(PDO::FETCH_ASSOC);

// 7. إجمالي المصروف وغير المصروف
// المصروف = المصروف في المفوتر + المصروف في الجزئي
$totalDisbursedAmount = ($invoicedDisbursedStats['total_value'] ?? 0);
$totalDisbursedCount = ($invoicedDisbursedStats['count'] ?? 0);

// حساب المصروف في المستخلصات الجزئية (فقط التي لم تدخل في مستخلص نهائي مصروف)
// لأن المفوتر يشمل المستخلصات النهائية للجزئية المصروفة التي تحتوي على قيمة الجزئي + النهائي
$partialDisbursedQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(pewo.extract_value) as total_disbursed
    FROM work_orders wo
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE pe.approval_stage = 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        WHERE ffpewo.work_order_id = wo.id AND ffpe.approval_stage = 'disbursed'
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE frewo.work_order_id = wo.id AND fre.approval_stage = 'disbursed'
    )
";
$partialDisbursedStats = $db->query($partialDisbursedQuery)->fetch(PDO::FETCH_ASSOC);
$partialDisbursedAmount = $partialDisbursedStats['total_disbursed'] ?? 0;
$partialDisbursedCount = $partialDisbursedStats['count'] ?? 0;

// حساب عدد أوامر العمل في الجزئي فقط - بشهادة إنجاز
$partialOnlyWithCompletionQuery = "
    SELECT COUNT(DISTINCT wo.id) as count
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE pe.approval_stage = 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        WHERE ffpewo.work_order_id = wo.id AND ffpe.approval_stage = 'disbursed'
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE frewo.work_order_id = wo.id AND fre.approval_stage = 'disbursed'
    )
";
$partialOnlyWithCompletionStats = $db->query($partialOnlyWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$partialOnlyWithCompletionCount = $partialOnlyWithCompletionStats['count'] ?? 0;

// حساب عدد أوامر العمل في الجزئي فقط - بدون شهادة إنجاز
$partialOnlyWithoutCompletionQuery = "
    SELECT COUNT(DISTINCT wo.id) as count
    FROM work_orders wo
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE pe.approval_stage = 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        WHERE ffpewo.work_order_id = wo.id AND ffpe.approval_stage = 'disbursed'
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE frewo.work_order_id = wo.id AND fre.approval_stage = 'disbursed'
    )
";
$partialOnlyWithoutCompletionStats = $db->query($partialOnlyWithoutCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$partialOnlyWithoutCompletionCount = $partialOnlyWithoutCompletionStats['count'] ?? 0;

$totalDisbursedAmount += $partialDisbursedAmount;
$totalDisbursedCount += $partialDisbursedCount;

// حساب إجمالي القيم الفعلية لجميع أوامر العمل بشهادة إنجاز
$totalWithCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(CASE WHEN wo.actual_value > 0 THEN wo.actual_value ELSE wo.estimated_value END) as total_value
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.form_type = 'completion_certificate'
    AND woa.status = 'attached'
";
$totalWithCompletionStats = $db->query($totalWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$totalWithCompletionValue = $totalWithCompletionStats['total_value'] ?? 0;
$totalWithCompletionCount = $totalWithCompletionStats['count'] ?? 0;

// حساب المصروف من أوامر العمل بشهادة إنجاز مرفقة (في المستخلصات المصروفة فقط)
// الجزئي المصروف (بشهادة إنجاز فقط)
$partialDisbursedWithCompletionQuery = "
    SELECT
        COALESCE(SUM(pewo.extract_value), 0) as total
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND pe.approval_stage = 'disbursed'
";
$partialDisbursedWithCompletionStats = $db->query($partialDisbursedWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$partialDisbursedWithCompletion = $partialDisbursedWithCompletionStats['total'] ?? 0;

// النهائي العادي المصروف (بشهادة إنجاز فقط)
$finalRegularDisbursedWithCompletionQuery = "
    SELECT
        COALESCE(SUM(frewo.extract_value), 0) as total
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
    INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND fre.approval_stage = 'disbursed'
";
$finalRegularDisbursedWithCompletionStats = $db->query($finalRegularDisbursedWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$finalRegularDisbursedWithCompletion = $finalRegularDisbursedWithCompletionStats['total'] ?? 0;

// النهائي للجزئية المصروف (بشهادة إنجاز فقط)
$finalForPartialDisbursedWithCompletionQuery = "
    SELECT
        COALESCE(SUM(ffpewo.extract_value), 0) as total
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
    INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND ffpe.approval_stage = 'disbursed'
";
$finalForPartialDisbursedWithCompletionStats = $db->query($finalForPartialDisbursedWithCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$finalForPartialDisbursedWithCompletion = $finalForPartialDisbursedWithCompletionStats['total'] ?? 0;

$disbursedWithCompletionTotal = $partialDisbursedWithCompletion + $finalRegularDisbursedWithCompletion + $finalForPartialDisbursedWithCompletion;

// حساب عدد أوامر العمل غير المصروفة (بشهادة إنجاز)
// غير المصروف = أوامر لها شهادة إنجاز ولم تدخل في مستخلص نهائي مصروف
// يشمل: أوامر لم تدخل مستخلص، أوامر في جزئي فقط، أوامر في مستخلصات غير مصروفة
$notDisbursedWithCompletionCountQuery = "
    SELECT COUNT(DISTINCT wo.id) as count
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND wo.id NOT IN (
        -- استثناء أوامر العمل في النهائي العادي المصروف
        SELECT DISTINCT frewo.work_order_id
        FROM final_regular_extract_work_orders frewo
        INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
        WHERE fre.approval_stage = 'disbursed'

        UNION

        -- استثناء أوامر العمل في النهائي للجزئية المصروف
        SELECT DISTINCT ffpewo.work_order_id
        FROM final_for_partial_extract_work_orders ffpewo
        INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
        WHERE ffpe.approval_stage = 'disbursed'
    )
";
$notDisbursedWithCompletionCountStats = $db->query($notDisbursedWithCompletionCountQuery)->fetch(PDO::FETCH_ASSOC);
$notDisbursedWithCompletionCount = $notDisbursedWithCompletionCountStats['count'] ?? 0;

// غير المصروف بشهادة إنجاز = إجمالي القيم الفعلية - المصروف
$notDisbursedWithCompletion = $totalWithCompletionValue - $disbursedWithCompletionTotal;

// حساب إجمالي القيم التقديرية لجميع أوامر العمل بدون شهادة إنجاز
$totalWithoutCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(wo.estimated_value) as total_value
    FROM work_orders wo
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.id IS NULL
";
$totalWithoutCompletionStats = $db->query($totalWithoutCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$totalWithoutCompletionValue = $totalWithoutCompletionStats['total_value'] ?? 0;
$totalWithoutCompletionCount = $totalWithoutCompletionStats['count'] ?? 0;

// حساب المصروف من أوامر العمل بدون شهادة إنجاز (في المستخلصات الجزئية المصروفة فقط)
$partialDisbursedWithoutCompletionQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        COALESCE(SUM(pewo.extract_value), 0) as total
    FROM work_orders wo
    LEFT JOIN work_order_attachments woa ON wo.id = woa.work_order_id
        AND woa.form_type = 'completion_certificate'
        AND woa.status = 'attached'
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND woa.id IS NULL
    AND pe.approval_stage = 'disbursed'
";
$partialDisbursedWithoutCompletionStats = $db->query($partialDisbursedWithoutCompletionQuery)->fetch(PDO::FETCH_ASSOC);
$partialDisbursedWithoutCompletion = $partialDisbursedWithoutCompletionStats['total'] ?? 0;
$partialDisbursedWithoutCompletionCount = $partialDisbursedWithoutCompletionStats['count'] ?? 0;

// غير المصروف بدون شهادة إنجاز = إجمالي القيم التقديرية - المصروف في الجزئي
$notDisbursedWithoutCompletion = $totalWithoutCompletionValue - $partialDisbursedWithoutCompletion;
$notDisbursedWithoutCompletionCount = $totalWithoutCompletionCount - $partialDisbursedWithoutCompletionCount;

// غير المصروف = بشهادة إنجاز + بدون شهادة إنجاز
$totalNotDisbursedAmount = $notDisbursedWithCompletion + $notDisbursedWithoutCompletion;
$totalNotDisbursedCount = $notDisbursedWithCompletionCount + $notDisbursedWithoutCompletionCount;

// 8. المبالغ المصروفة (من المستخلصات المصروفة فقط) - حسب تاريخ التكليف
// نحسب مجموع قيم أوامر العمل في المستخلصات المصروفة
$disbursedAmountQuery = "
    SELECT
        COALESCE(SUM(DISTINCT pewo.extract_value), 0) as partial_disbursed
    FROM work_orders wo
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE pe.approval_stage = 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$partialDisbursed = $db->query($disbursedAmountQuery)->fetch(PDO::FETCH_ASSOC)['partial_disbursed'] ?? 0;

$finalRegularDisbursedQuery = "
    SELECT
        COALESCE(SUM(DISTINCT frewo.extract_value), 0) as final_regular_disbursed
    FROM work_orders wo
    INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
    INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
    WHERE fre.approval_stage = 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$finalRegularDisbursed = $db->query($finalRegularDisbursedQuery)->fetch(PDO::FETCH_ASSOC)['final_regular_disbursed'] ?? 0;

$finalForPartialDisbursedQuery = "
    SELECT
        COALESCE(SUM(DISTINCT ffpewo.extract_value), 0) as final_for_partial_disbursed
    FROM work_orders wo
    INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
    INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    WHERE ffpe.approval_stage = 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$finalForPartialDisbursed = $db->query($finalForPartialDisbursedQuery)->fetch(PDO::FETCH_ASSOC)['final_for_partial_disbursed'] ?? 0;

$totalDisbursed = $partialDisbursed + $finalRegularDisbursed + $finalForPartialDisbursed;

// 6. أوامر العمل في مستخلصات غير مصروفة - حسب تاريخ التكليف
// نحسب قيمة أوامر العمل التي دخلت مستخلصات ولكن المستخلصات لم تُصرف بعد
$inPendingExtractsQuery = "
    SELECT
        COALESCE(SUM(DISTINCT pewo.extract_value), 0) as partial_pending
    FROM work_orders wo
    INNER JOIN partial_extract_work_orders pewo ON wo.id = pewo.work_order_id
    INNER JOIN partial_extracts pe ON pewo.partial_extract_id = pe.id
    WHERE pe.approval_stage != 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$partialPending = $db->query($inPendingExtractsQuery)->fetch(PDO::FETCH_ASSOC)['partial_pending'] ?? 0;

$finalRegularPendingQuery = "
    SELECT
        COALESCE(SUM(DISTINCT frewo.extract_value), 0) as final_regular_pending
    FROM work_orders wo
    INNER JOIN final_regular_extract_work_orders frewo ON wo.id = frewo.work_order_id
    INNER JOIN final_regular_extracts fre ON frewo.final_regular_extract_id = fre.id
    WHERE fre.approval_stage != 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$finalRegularPending = $db->query($finalRegularPendingQuery)->fetch(PDO::FETCH_ASSOC)['final_regular_pending'] ?? 0;

$finalForPartialPendingQuery = "
    SELECT
        COALESCE(SUM(DISTINCT ffpewo.extract_value), 0) as final_for_partial_pending
    FROM work_orders wo
    INNER JOIN final_for_partial_extract_work_orders ffpewo ON wo.id = ffpewo.work_order_id
    INNER JOIN final_for_partial_extracts ffpe ON ffpewo.final_for_partial_extract_id = ffpe.id
    WHERE ffpe.approval_stage != 'disbursed'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$finalForPartialPending = $db->query($finalForPartialPendingQuery)->fetch(PDO::FETCH_ASSOC)['final_for_partial_pending'] ?? 0;

$totalInPendingExtracts = $partialPending + $finalRegularPending + $finalForPartialPending;

// 7. أوامر العمل التي لم تدخل أي مستخلص - حسب تاريخ التكليف
// نحسب قيمة أوامر العمل التي لم تدخل في أي مستخلص
$notInExtractsQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(CASE
            WHEN wo.actual_value > 0 THEN wo.actual_value
            ELSE wo.estimated_value
        END) as total
    FROM work_orders wo
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND NOT EXISTS (
        SELECT 1 FROM partial_extract_work_orders pewo WHERE pewo.work_order_id = wo.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_regular_extract_work_orders frewo WHERE frewo.work_order_id = wo.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM final_for_partial_extract_work_orders ffpewo WHERE ffpewo.work_order_id = wo.id
    )
";
$notInExtractsStats = $db->query($notInExtractsQuery)->fetch(PDO::FETCH_ASSOC);
$totalNotInExtracts = $notInExtractsStats['total'] ?? 0;
$countNotInExtracts = $notInExtractsStats['count'] ?? 0;

// 8. أوامر العمل حسب الجهة الحالية - حسب تاريخ التكليف
// أولاً: حساب الأوامر المكتملة بشكل منفصل
$completedWorkOrdersQuery = "
    SELECT
        COUNT(wo.id) as count,
        SUM(wo.estimated_value) as total_estimated,
        SUM(wo.actual_value) as total_actual
    FROM work_orders wo
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND wo.status = 'completed'
";
$completedStats = $db->query($completedWorkOrdersQuery)->fetch(PDO::FETCH_ASSOC);

// حساب الأوامر النشطة
$activeWorkOrdersQuery = "
    SELECT
        COUNT(wo.id) as count,
        SUM(wo.estimated_value) as total_estimated,
        SUM(wo.actual_value) as total_actual
    FROM work_orders wo
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND wo.status = 'active'
";
$activeStats = $db->query($activeWorkOrdersQuery)->fetch(PDO::FETCH_ASSOC);

// ثانياً: حساب توزيع الجهات (باستثناء الأوامر المكتملة)
$currentEntityStatsQuery = "
    SELECT
        ce.name as entity_name,
        ce.code as entity_code,
        COUNT(wo.id) as count,
        SUM(wo.estimated_value) as total_estimated,
        SUM(wo.actual_value) as total_actual
    FROM work_orders wo
    LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    AND wo.status != 'completed'
    GROUP BY ce.id, ce.name, ce.code
    ORDER BY count DESC
";
$currentEntityStats = $db->query($currentEntityStatsQuery)->fetchAll(PDO::FETCH_ASSOC);

// 9. أوامر العمل التي عليها تخريد - حسب تاريخ التكليف
$demolitionWorkOrdersQuery = "
    SELECT
        COUNT(DISTINCT wo.id) as count,
        SUM(wo.estimated_value) as total_estimated,
        SUM(wo.actual_value) as total_actual
    FROM work_orders wo
    INNER JOIN work_order_attachments woa ON wo.id = woa.work_order_id
    WHERE woa.form_type = 'demolition_form'
    AND woa.status = 'not_attached'
    AND DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
";
$demolitionStats = $db->query($demolitionWorkOrdersQuery)->fetch(PDO::FETCH_ASSOC);

// 10. أوامر العمل حسب القسم - حسب تاريخ التكليف
$departmentStatsQuery = "
    SELECT
        department,
        COUNT(*) as count,
        SUM(estimated_value) as total_estimated,
        SUM(actual_value) as total_actual
    FROM work_orders
    WHERE DATE(COALESCE(assignment_date, created_at)) BETWEEN '$startDate' AND '$endDate'
    GROUP BY department
";
$departmentStats = $db->query($departmentStatsQuery)->fetchAll(PDO::FETCH_ASSOC);

// 11. أوامر العمل حسب الفرع - حسب تاريخ التكليف
$branchStatsQuery = "
    SELECT
        b.name as branch_name,
        b.code as branch_code,
        COUNT(wo.id) as count,
        SUM(wo.estimated_value) as total_estimated,
        SUM(wo.actual_value) as total_actual
    FROM work_orders wo
    LEFT JOIN branches b ON wo.branch_id = b.id
    WHERE DATE(COALESCE(wo.assignment_date, wo.created_at)) BETWEEN '$startDate' AND '$endDate'
    GROUP BY b.id, b.name, b.code
    ORDER BY count DESC
";
$branchStats = $db->query($branchStatsQuery)->fetchAll(PDO::FETCH_ASSOC);

// 12. التوزيع الزمني (للرسم البياني)
$timeSeriesData = [];
$timeSeriesLabels = [];

$monthNames = [
    '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل',
    '05' => 'مايو', '06' => 'يونيو', '07' => 'يوليو', '08' => 'أغسطس',
    '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'
];

if ($period === 'week') {
    // يومي
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $timeSeriesLabels[] = date('d/m', strtotime($date));
        $timeSeriesData[$date] = ['count' => 0, 'estimated' => 0, 'actual' => 0];
    }
} elseif ($period === 'month') {
    // أسبوعي
    for ($i = 3; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-$i weeks monday"));
        $weekEnd = date('Y-m-d', strtotime("-$i weeks sunday"));
        $timeSeriesLabels[] = date('d/m', strtotime($weekStart)) . ' - ' . date('d/m', strtotime($weekEnd));
        $timeSeriesData[$weekStart] = ['count' => 0, 'estimated' => 0, 'actual' => 0];
    }
} elseif ($period === 'all_yearly') {
    // سنوي - من أول سنة إلى السنة الحالية
    $startYear = (int)date('Y', strtotime($startDate));
    $endYear = (int)date('Y', strtotime($endDate));

    for ($year = $startYear; $year <= $endYear; $year++) {
        $timeSeriesLabels[] = (string)$year;
        $timeSeriesData[$year] = ['count' => 0, 'estimated' => 0, 'actual' => 0];
    }
} elseif ($period === 'all_monthly') {
    // شهري - من أول شهر إلى الشهر الحالي
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = new DateInterval('P1M');
    $dateRange = new DatePeriod($start, $interval, $end->modify('+1 month'));

    foreach ($dateRange as $date) {
        $month = $date->format('Y-m');
        $monthNum = $date->format('m');
        $year = $date->format('Y');
        $timeSeriesLabels[] = $monthNames[$monthNum] . ' ' . $year;
        $timeSeriesData[$month] = ['count' => 0, 'estimated' => 0, 'actual' => 0];
    }
} else {
    // شهري (للربع السنوي والسنة)
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthNum = date('m', strtotime($month . '-01'));
        $year = date('Y', strtotime($month . '-01'));
        $timeSeriesLabels[] = $monthNames[$monthNum] . ' ' . $year;
        $timeSeriesData[$month] = ['count' => 0, 'estimated' => 0, 'actual' => 0];
    }
}

// جلب البيانات الفعلية (حسب تاريخ التكليف)
$timeSeriesQuery = "
    SELECT
        DATE(COALESCE(assignment_date, created_at)) as date,
        COUNT(*) as count,
        SUM(estimated_value) as estimated,
        SUM(actual_value) as actual
    FROM work_orders
    WHERE DATE(COALESCE(assignment_date, created_at)) BETWEEN '$startDate' AND '$endDate'
    GROUP BY DATE(COALESCE(assignment_date, created_at))
";
$timeSeriesResults = $db->query($timeSeriesQuery)->fetchAll(PDO::FETCH_ASSOC);

foreach ($timeSeriesResults as $row) {
    $date = $row['date'];

    if ($period === 'week') {
        // يومي
        $key = $date;
    } elseif ($period === 'month') {
        // أسبوعي
        $weekStart = date('Y-m-d', strtotime('last monday', strtotime($date)));
        if (date('N', strtotime($date)) == 1) {
            $weekStart = $date;
        }
        $key = $weekStart;
    } elseif ($period === 'all_yearly') {
        // سنوي
        $key = (int)date('Y', strtotime($date));
    } elseif ($period === 'all_monthly') {
        // شهري
        $key = substr($date, 0, 7); // YYYY-MM
    } else {
        // شهري (للربع السنوي والسنة)
        $key = substr($date, 0, 7); // YYYY-MM
    }

    if (isset($timeSeriesData[$key])) {
        $timeSeriesData[$key]['count'] += intval($row['count']);
        $timeSeriesData[$key]['estimated'] += floatval($row['estimated']);
        $timeSeriesData[$key]['actual'] += floatval($row['actual']);
    }
}

// بدء المحتوى
ob_start();
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
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

        .gradient-primary {
            background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%);
        }

        .gradient-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .gradient-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .gradient-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .gradient-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .gradient-secondary {
            background: linear-gradient(135deg, #5f72bd 0%, #9b23ea 100%);
        }

        .gradient-dark {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
    </style>

<div class="container-fluid px-4 py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-primary">
                    <i class="fas fa-chart-line me-2"></i>
                    تقارير أوامر العمل الشاملة
                </h1>
                <p class="text-muted mb-0">تقارير تفصيلية لجميع أوامر العمل مع رسوم بيانية احترافية</p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-danger">
                    <i class="fas fa-file-pdf me-1"></i>
                    طباعة / PDF
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    العودة
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
                    نظرة عامة
                </h2>
            </div>
        </div>

        <!-- الصف الأول: الإحصائيات الرئيسية -->
        <div class="row mb-4">
            <!-- إجمالي أوامر العمل -->
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    إجمالي أوامر العمل
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($totalStats['total_count'] ?? 0) ?>
                                </div>
                                <div class="mt-2" style="font-size: 1.2rem; font-weight: 700;">
                                    <?php
                                    $totalAmount = ($withoutCompletionCertStats['total_estimated'] ?? 0) + ($withCompletionCertStats['total_actual'] ?? 0);
                                    echo number_format($totalAmount, 2);
                                    ?> ر.س
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- بدون شهادة إنجاز (تقديري) -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-file-alt me-2"></i>بدون شهادة إنجاز</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($withoutCompletionCertStats['count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($withoutCompletionCertStats['total_estimated'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- بشهادة إنجاز (فعلي) -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-certificate me-2"></i>بشهادة إنجاز</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($withCompletionCertStats['count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($withCompletionCertStats['total_actual'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clipboard-list stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إجمالي المصروف -->
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    إجمالي المصروف
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($totalDisbursedCount) ?>
                                </div>
                                <div class="mt-2" style="font-size: 1.2rem; font-weight: 700;">
                                    <?= number_format($totalDisbursedAmount, 2) ?> ر.س
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- المصروف في المفوتر -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-file-invoice-dollar me-2"></i>المفوتر</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($invoicedDisbursedStats['count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($invoicedDisbursedStats['total_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- المصروف في الجزئي -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-tasks me-2"></i>دخلت جزئي فقط</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($partialDisbursedCount) ?></strong>
                                        </div>

                                        <!-- تفصيل بشهادة وبدون شهادة -->
                                        <div class="mb-2" style="padding-right: 1.5rem; font-size: 0.9rem;">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="text-muted"><i class="fas fa-certificate me-1" style="font-size: 0.75rem;"></i>بشهادة إنجاز</span>
                                                <span class="badge bg-success"><?= number_format($partialOnlyWithCompletionCount) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted"><i class="fas fa-file-alt me-1" style="font-size: 0.75rem;"></i>بدون شهادة</span>
                                                <span class="badge bg-secondary"><?= number_format($partialOnlyWithoutCompletionCount) ?></span>
                                            </div>
                                        </div>

                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($partialDisbursedAmount, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- إجمالي غير المصروف -->
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-warning text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    إجمالي غير المصروف
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($totalNotDisbursedCount) ?>
                                </div>
                                <div class="mt-2" style="font-size: 1.2rem; font-weight: 700;">
                                    <?= number_format($totalNotDisbursedAmount, 2) ?> ر.س
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- بشهادة إنجاز -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-certificate me-2"></i>بشهادة إنجاز</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($notDisbursedWithCompletionCount) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($notDisbursedWithCompletion, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- بدون شهادة إنجاز -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-file-alt me-2"></i>بدون شهادة إنجاز</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($notDisbursedWithoutCompletionCount) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($notDisbursedWithoutCompletion, 2) ?> ر.س
                                        </div>
                                    </div>
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

        <!-- الصف الثاني: أوامر العمل المفوترة -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="section-title">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    أوامر العمل المفوترة
                </h2>
            </div>
        </div>

        <div class="row mb-4">
            <!-- أوامر العمل المفوترة -->
            <div class="col-xl-6 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-info text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    أوامر العمل المفوترة
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($invoicedStats['count'] ?? 0) ?>
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- مصروفة -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-money-bill-wave me-2"></i>مصروفة</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($invoicedDisbursedStats['count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($invoicedDisbursedStats['total_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- غير مصروفة -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-hourglass-half me-2"></i>غير مصروفة</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($invoicedNotDisbursedStats['count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($invoicedNotDisbursedStats['total_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-invoice-dollar stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الصف الثالث: أوامر العمل الجزئية -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="section-title">
                    <i class="fas fa-tasks me-2"></i>
                    أوامر العمل الجزئية
                </h2>
            </div>
        </div>

        <div class="row mb-4">
            <!-- أوامر العمل الجزئية - بشهادة إنجاز -->
            <div class="col-xl-6 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    جزئية - بشهادة إنجاز
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($partialWithCompletionStats['count'] ?? 0) ?>
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- قيمة المستخلص الجزئي -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-file-invoice me-2"></i>قيمة الجزئي</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($partialWithCompletionStats['partial_count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($partialWithCompletionStats['partial_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- المبلغ المتبقي -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-coins me-2"></i>المتبقي</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($partialWithCompletionStats['remaining_count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($partialWithCompletionStats['remaining_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-certificate stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أوامر العمل الجزئية - بدون شهادة إنجاز -->
            <div class="col-xl-6 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-warning text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    جزئية - بدون شهادة إنجاز
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($partialWithoutCompletionStats['count'] ?? 0) ?>
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- قيمة المستخلص الجزئي -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-file-invoice me-2"></i>قيمة الجزئي</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($partialWithoutCompletionStats['partial_count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($partialWithoutCompletionStats['partial_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- المبلغ المتبقي -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-coins me-2"></i>المتبقي</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($partialWithoutCompletionStats['remaining_count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($partialWithoutCompletionStats['remaining_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الصف الرابع: أوامر العمل بدون مستخلص -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="section-title">
                    <i class="fas fa-folder-open me-2"></i>
                    أوامر العمل بدون مستخلص
                </h2>
            </div>
        </div>

        <div class="row mb-4">
            <!-- أوامر العمل بدون مستخلص - بشهادة إنجاز -->
            <div class="col-xl-6 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    بدون مستخلص - بشهادة إنجاز
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($noExtractWithCompletionStats['count'] ?? 0) ?>
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <!-- شهادة إنجاز مؤكدة -->
                                    <div class="mb-3 pb-2" style="border-bottom: 2px solid rgba(255,255,255,0.3);">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-check-circle me-2"></i>مؤكدة</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($noExtractWithCompletionStats['confirmed_count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($noExtractWithCompletionStats['confirmed_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>

                                    <!-- شهادة إنجاز غير مؤكدة -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-clock me-2"></i>غير مؤكدة</span>
                                            <strong style="font-size: 1.1rem;"><?= number_format($noExtractWithCompletionStats['not_confirmed_count'] ?? 0) ?></strong>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($noExtractWithCompletionStats['not_confirmed_value'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-contract stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أوامر العمل بدون مستخلص - بدون شهادة إنجاز -->
            <div class="col-xl-6 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-dark text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-2" style="font-size: 0.9rem; font-weight: 700;">
                                    بدون مستخلص - بدون شهادة
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($noExtractNoCompletionStats['count'] ?? 0) ?>
                                </div>
                                <div class="mt-3" style="font-size: 0.95rem; line-height: 1.8;">
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span style="font-weight: 600;"><i class="fas fa-calculator me-2"></i>القيمة التقديرية</span>
                                        </div>
                                        <div class="text-end" style="font-size: 1.15rem; font-weight: 700;">
                                            <?= number_format($noExtractNoCompletionStats['total_estimated'] ?? 0, 2) ?> ر.س
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-folder-open stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الصف الخامس: إحصائيات إضافية -->
        <div class="row mb-4" style="display: none;">
            <!-- المبالغ المصروفة (القديمة) -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                    المبالغ المصروفة
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($totalDisbursed, 2) ?> ر.س
                                </div>
                                <div class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                                    من المستخلصات المصروفة
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill-wave stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- في مستخلصات غير مصروفة -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-warning text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                    في مستخلصات غير مصروفة
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($totalInPendingExtracts, 2) ?> ر.س
                                </div>
                                <div class="mt-2" style="font-size: 0.75rem; opacity: 0.9;">
                                    في مراحل الاعتماد
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- لم تدخل مستخلصات -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-danger text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                    لم تدخل مستخلصات
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($countNotInExtracts) ?>
                                </div>
                                <div class="mt-2" style="font-size: 0.9rem;">
                                    <div>القيمة: <?= number_format($totalNotInExtracts, 2) ?> ر.س</div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-excel stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- أوامر العمل التي عليها تخريد -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card shadow gradient-secondary text-dark">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-uppercase mb-1" style="font-size: 0.85rem; font-weight: 600;">
                                    أوامر عمل تحتاج تخريد
                                </div>
                                <div class="h2 mb-0 fw-bold">
                                    <?= number_format($demolitionStats['count'] ?? 0) ?>
                                </div>
                                <div class="mt-2" style="font-size: 0.9rem;">
                                    <div>القيمة: <?= number_format(($demolitionStats['total_actual'] ?? 0) > 0 ? $demolitionStats['total_actual'] : ($demolitionStats['total_estimated'] ?? 0), 2) ?> ر.س</div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الرسوم البيانية -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="section-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    الرسوم البيانية
                </h2>
            </div>
        </div>

        <!-- الرسم البياني الخطي - تطور أوامر العمل عبر الزمن -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            تطور أوامر العمل عبر الزمن
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

        <!-- الرسوم الدائرية -->
        <div class="row mb-4">
            <!-- توزيع أوامر العمل حسب الحالة -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            توزيع أوامر العمل حسب الحالة
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- توزيع أوامر العمل حسب القسم -->
            <div class="col-md-6 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            توزيع أوامر العمل حسب القسم
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

        <!-- الرسم البياني العمودي - توزيع حسب الفرع -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            توزيع أوامر العمل حسب الفرع
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="branchChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الرسم البياني العمودي - توزيع حسب الجهة الحالية -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            توزيع أوامر العمل حسب الجهة الحالية
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="currentEntityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الجداول التفصيلية -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="section-title">
                    <i class="fas fa-table me-2"></i>
                    الجداول التفصيلية
                </h2>
            </div>
        </div>

        <!-- جدول الفروع -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2"></i>
                            توزيع أوامر العمل حسب الفرع
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>الفرع</th>
                                        <th>الكود</th>
                                        <th>عدد أوامر العمل</th>
                                        <th>القيمة التقديرية</th>
                                        <th>القيمة الفعلية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branchStats as $branch): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($branch['branch_name'] ?? 'غير محدد') ?></td>
                                        <td><?= htmlspecialchars($branch['branch_code'] ?? '-') ?></td>
                                        <td><span class="badge bg-primary"><?= number_format($branch['count']) ?></span></td>
                                        <td><?= number_format($branch['total_estimated'], 2) ?> ر.س</td>
                                        <td><?= number_format($branch['total_actual'], 2) ?> ر.س</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول الجهات الحالية -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-sitemap me-2"></i>
                            توزيع أوامر العمل حسب الجهة الحالية
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>الجهة</th>
                                        <th>الكود</th>
                                        <th>عدد أوامر العمل</th>
                                        <th>القيمة التقديرية</th>
                                        <th>القيمة الفعلية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- صف ثابت للأوامر المكتملة -->
                                    <tr class="table-success fw-bold">
                                        <td><i class="fas fa-check-circle me-2"></i>أوامر العمل المكتملة</td>
                                        <td>-</td>
                                        <td><span class="badge bg-success"><?= number_format($completedStats['count'] ?? 0) ?></span></td>
                                        <td><?= number_format($completedStats['total_estimated'] ?? 0, 2) ?> ر.س</td>
                                        <td><?= number_format($completedStats['total_actual'] ?? 0, 2) ?> ر.س</td>
                                    </tr>
                                    <!-- باقي الجهات (بدون الأوامر المكتملة) -->
                                    <?php foreach ($currentEntityStats as $entity): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($entity['entity_name'] ?? 'غير محدد') ?></td>
                                        <td><?= htmlspecialchars($entity['entity_code'] ?? '-') ?></td>
                                        <td><span class="badge bg-info"><?= number_format($entity['count']) ?></span></td>
                                        <td><?= number_format($entity['total_estimated'], 2) ?> ر.س</td>
                                        <td><?= number_format($entity['total_actual'], 2) ?> ر.س</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول الأقسام -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-layer-group me-2"></i>
                            توزيع أوامر العمل حسب القسم
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>القسم</th>
                                        <th>عدد أوامر العمل</th>
                                        <th>القيمة التقديرية</th>
                                        <th>القيمة الفعلية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departmentStats as $dept): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $deptName = $dept['department'] === 'connections' ? 'التوصيلات' : 'المشاريع';
                                            echo htmlspecialchars($deptName);
                                            ?>
                                        </td>
                                        <td><span class="badge bg-info"><?= number_format($dept['count']) ?></span></td>
                                        <td><?= number_format($dept['total_estimated'], 2) ?> ر.س</td>
                                        <td><?= number_format($dept['total_actual'], 2) ?> ر.س</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
        // إظهار/إخفاء حقول التاريخ المخصص
        document.getElementById('periodSelect').addEventListener('change', function() {
            const period = this.value;
            const customDateStart = document.getElementById('customDateStart');
            const customDateEnd = document.getElementById('customDateEnd');
            const quarterYear = document.getElementById('quarterYear');

            if (period === 'custom') {
                customDateStart.style.display = 'block';
                customDateEnd.style.display = 'block';
            } else {
                customDateStart.style.display = 'none';
                customDateEnd.style.display = 'none';
            }

            if (['q1', 'q2', 'q3', 'q4'].includes(period)) {
                quarterYear.style.display = 'block';
            } else {
                quarterYear.style.display = 'none';
            }
        });

        // إعداد Chart.js
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.font.size = 14;

        // 1. الرسم البياني الخطي - تطور أوامر العمل عبر الزمن
        const timeSeriesCtx = document.getElementById('timeSeriesChart').getContext('2d');
        const timeSeriesChart = new Chart(timeSeriesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_values($timeSeriesLabels), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    {
                        label: 'عدد أوامر العمل',
                        data: <?= json_encode(array_column($timeSeriesData, 'count')) ?>,
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'القيمة التقديرية',
                        data: <?= json_encode(array_column($timeSeriesData, 'estimated')) ?>,
                        borderColor: 'rgb(132, 250, 176)',
                        backgroundColor: 'rgba(132, 250, 176, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'القيمة الفعلية',
                        data: <?= json_encode(array_column($timeSeriesData, 'actual')) ?>,
                        borderColor: 'rgb(250, 112, 154)',
                        backgroundColor: 'rgba(250, 112, 154, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 0) {
                                        label += context.parsed.y;
                                    } else {
                                        label += new Intl.NumberFormat('ar-SA').format(context.parsed.y) + ' ر.س';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'عدد أوامر العمل',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'القيمة (ر.س)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return new Intl.NumberFormat('ar-SA').format(value);
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // 2. الرسم الدائري - توزيع حسب الحالة
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['نشطة', 'مكتملة', 'ملغاة'],
                datasets: [{
                    data: [
                        <?= $activeStats['count'] ?? 0 ?>,
                        <?= $completedStats['count'] ?? 0 ?>,
                        <?= ($totalStats['total_count'] ?? 0) - ($activeStats['count'] ?? 0) - ($completedStats['count'] ?? 0) ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgb(255, 193, 7)',
                        'rgb(40, 167, 69)',
                        'rgb(220, 53, 69)'
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
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // 3. الرسم الدائري - توزيع حسب القسم
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(departmentCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(function($d) {
                    return $d['department'] === 'connections' ? 'التوصيلات' : 'المشاريع';
                }, $departmentStats), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($departmentStats, 'count')) ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(132, 250, 176, 0.8)'
                    ],
                    borderColor: [
                        'rgb(102, 126, 234)',
                        'rgb(132, 250, 176)'
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
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // 4. الرسم البياني العمودي - توزيع حسب الفرع
        const branchCtx = document.getElementById('branchChart').getContext('2d');
        const branchChart = new Chart(branchCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($branchStats, 'branch_name'), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    {
                        label: 'عدد أوامر العمل',
                        data: <?= json_encode(array_column($branchStats, 'count')) ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgb(102, 126, 234)',
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'القيمة التقديرية',
                        data: <?= json_encode(array_column($branchStats, 'total_estimated')) ?>,
                        backgroundColor: 'rgba(132, 250, 176, 0.8)',
                        borderColor: 'rgb(132, 250, 176)',
                        borderWidth: 2,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'القيمة الفعلية',
                        data: <?= json_encode(array_column($branchStats, 'total_actual')) ?>,
                        backgroundColor: 'rgba(250, 112, 154, 0.8)',
                        borderColor: 'rgb(250, 112, 154)',
                        borderWidth: 2,
                        yAxisID: 'y1'
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
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 0) {
                                        label += context.parsed.y;
                                    } else {
                                        label += new Intl.NumberFormat('ar-SA').format(context.parsed.y) + ' ر.س';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'عدد أوامر العمل',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'القيمة (ر.س)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return new Intl.NumberFormat('ar-SA').format(value);
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // 5. الرسم البياني العمودي - توزيع حسب الجهة الحالية
        const currentEntityCtx = document.getElementById('currentEntityChart').getContext('2d');

        // إضافة الأوامر المكتملة في الأعلى
        const entityLabels = ['أوامر العمل المكتملة', ...<?= json_encode(array_column($currentEntityStats, 'entity_name'), JSON_UNESCAPED_UNICODE) ?>];
        const entityCounts = [<?= $completedStats['count'] ?? 0 ?>, ...<?= json_encode(array_column($currentEntityStats, 'count')) ?>];
        const entityEstimated = [<?= $completedStats['total_estimated'] ?? 0 ?>, ...<?= json_encode(array_column($currentEntityStats, 'total_estimated')) ?>];
        const entityActual = [<?= $completedStats['total_actual'] ?? 0 ?>, ...<?= json_encode(array_column($currentEntityStats, 'total_actual')) ?>];

        const currentEntityChart = new Chart(currentEntityCtx, {
            type: 'bar',
            data: {
                labels: entityLabels,
                datasets: [
                    {
                        label: 'عدد أوامر العمل',
                        data: entityCounts,
                        backgroundColor: function(context) {
                            // لون مختلف للأوامر المكتملة
                            return context.dataIndex === 0 ? 'rgba(40, 167, 69, 0.8)' : 'rgba(102, 126, 234, 0.8)';
                        },
                        borderColor: function(context) {
                            return context.dataIndex === 0 ? 'rgb(40, 167, 69)' : 'rgb(102, 126, 234)';
                        },
                        borderWidth: 2,
                        yAxisID: 'y'
                    },
                    {
                        label: 'القيمة التقديرية',
                        data: entityEstimated,
                        backgroundColor: function(context) {
                            return context.dataIndex === 0 ? 'rgba(40, 167, 69, 0.6)' : 'rgba(132, 250, 176, 0.8)';
                        },
                        borderColor: function(context) {
                            return context.dataIndex === 0 ? 'rgb(40, 167, 69)' : 'rgb(132, 250, 176)';
                        },
                        borderWidth: 2,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'القيمة الفعلية',
                        data: entityActual,
                        backgroundColor: function(context) {
                            return context.dataIndex === 0 ? 'rgba(40, 167, 69, 0.4)' : 'rgba(250, 112, 154, 0.8)';
                        },
                        borderColor: function(context) {
                            return context.dataIndex === 0 ? 'rgb(40, 167, 69)' : 'rgb(250, 112, 154)';
                        },
                        borderWidth: 2,
                        yAxisID: 'y1'
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
                                size: 14,
                                weight: 'bold'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.x !== null) {
                                    if (context.datasetIndex === 0) {
                                        label += context.parsed.x;
                                    } else {
                                        label += new Intl.NumberFormat('ar-SA').format(context.parsed.x) + ' ر.س';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'عدد أوامر العمل',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'القيمة (ر.س)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return new Intl.NumberFormat('ar-SA').format(value);
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
</script>

<?php
// حفظ المحتوى
$content = ob_get_clean();

// تضمين layout
include __DIR__ . '/../includes/layout.php';
?>

أوامر العمل المفوترة
