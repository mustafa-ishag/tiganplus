<?php
/**
 * جلب إحصائيات المستخلصات النهائية للجزئي
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('extracts_view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لعرض المستخلصات']);
    exit();
}

try {
    $db = getDB();

    // جلب مراحل الاعتماد من قاعدة البيانات أولاً
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
        $dynamicApprovalStages = ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'disbursed'];
    }

    // بناء الاستعلام ديناميكياً
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

    // تحويل القيم إلى أرقام مع الحفاظ على المبالغ كأرقام عشرية
    $processedStats = [];
    foreach ($stats as $key => $value) {
        if (strpos($key, '_net_amount') !== false || in_array($key, ['net_amount', 'total_amount', 'total_penalty_amount'])) {
            // المبالغ تبقى كأرقام عشرية
            $processedStats[$key] = $value === null ? 0 : (float) $value;
        } else {
            // العدادات تحول لأرقام صحيحة
            $processedStats[$key] = $value === null ? 0 : (int) $value;
        }
    }
    $stats = $processedStats;

    // جلب إحصائيات الأقسام من عمود department
    $departmentStatsQuery = "
        SELECT ffpe.department as name,
               'info' as color,
               COUNT(ffpe.id) as count,
               SUM(ffpe.net_amount) as net_amount
        FROM final_for_partial_extracts ffpe
        WHERE ffpe.department IS NOT NULL AND ffpe.department != ''
        GROUP BY ffpe.department
        HAVING count > 0
        ORDER BY ffpe.department
    ";
    $departmentStats = $db->query($departmentStatsQuery)->fetchAll();

    // معالجة إحصائيات الأقسام
    $processedDepartmentStats = [];
    foreach ($departmentStats as $index => $dept) {
        $processedDepartmentStats[] = [
            'id' => $index,
            'name' => $dept['name'],
            'color' => $dept['color'] ?? 'info',
            'count' => (int) $dept['count'],
            'net_amount' => (float) ($dept['net_amount'] ?? 0)
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'departments' => $processedDepartmentStats
    ]);
    
} catch (Exception $e) {
    error_log("Final for Partial Extract Statistics Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب الإحصائيات'
    ]);
}
?>
