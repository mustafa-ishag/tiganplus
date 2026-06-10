<?php
/**
 * API لجلب المواد المستخدمة من طلبات الصرف المعتمدة لأمر عمل محدد
 * Get Work Order Materials from Approved Material Requests
 */

// تعيين نوع المحتوى أولاً
header('Content-Type: application/json; charset=utf-8');

// معالجة الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 0); // لا نريد عرض الأخطاء في JSON

// بدء الجلسة بشكل آمن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في تحميل الملفات المطلوبة', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح بالوصول'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من الصلاحيات (تعليق مؤقت للاختبار)
// if (!hasPermission('view_completion_certificates')) {
//     http_response_code(403);
//     echo json_encode(['error' => 'ليس لديك صلاحية للوصول'], JSON_UNESCAPED_UNICODE);
//     exit();
// }

// التحقق من طريقة الطلب ومعرف أمر العمل
$workOrderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // قراءة البيانات من POST body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['work_order_id']) && !empty($data['work_order_id'])) {
        $workOrderId = (int) $data['work_order_id'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // قراءة البيانات من GET parameters
    if (isset($_GET['work_order_id']) && !empty($_GET['work_order_id'])) {
        $workOrderId = (int) $_GET['work_order_id'];
    }
}

if (!$workOrderId) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف أمر العمل مطلوب'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db = getDB();

    if (!$db) {
        throw new Exception('فشل في الاتصال بقاعدة البيانات');
    }

    // التحقق من وجود أمر العمل
    $workOrderStmt = $db->prepare("
        SELECT wo.*, wot.type_code, b.name as branch_name 
        FROM work_orders wo 
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        WHERE wo.id = ? AND wo.status = 'active'
    ");
    $workOrderStmt->execute([$workOrderId]);
    $workOrder = $workOrderStmt->fetch();

    if (!$workOrder) {
        http_response_code(404);
        echo json_encode(['error' => 'أمر العمل غير موجود أو غير نشط'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // جلب المواد من طلبات الصرف المعتمدة
    $materialsStmt = $db->prepare("
        SELECT DISTINCT
            m.id as material_id,
            m.item_number,
            mc.description,
            mc.unit,
            mc.group_number,
            SUM(mrd.requested_quantity) as total_requested_quantity,
            mr.request_number,
            mr.status as request_status
        FROM material_requests mr
        INNER JOIN material_request_details mrd ON mr.id = mrd.request_id
        INNER JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE mr.work_order_id = ?
        AND mr.status IN ('approved', 'branch_approved', 'warehouse_approved', 'project_approved')
        AND m.is_active = 1
        GROUP BY m.id, m.item_number, mc.description, mc.unit, mc.group_number
        ORDER BY m.item_number
    ");

    $materialsStmt->execute([$workOrderId]);
    $requestMaterials = $materialsStmt->fetchAll();

    // جلب المواد من المعاملات الصادرة
    $txStmt = $db->prepare("
        SELECT
            m.id as material_id,
            m.item_number,
            mc.description,
            mc.unit,
            mc.group_number,
            SUM(td.quantity) as total_outgoing_quantity
        FROM transaction_details td
        JOIN inventory_transactions it ON td.transaction_id = it.id
        JOIN materials m ON td.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE it.work_order_id = ?
        AND it.transaction_type = 'outgoing'
        AND m.is_active = 1
        GROUP BY m.id, m.item_number, mc.description, mc.unit, mc.group_number
    ");
    $txStmt->execute([$workOrderId]);
    $txMaterials = $txStmt->fetchAll();

    // جلب معلومات طلبات الصرف المعتمدة
    $requestsStmt = $db->prepare("
        SELECT
            mr.id,
            mr.request_number,
            mr.status,
            mr.request_date,
            COUNT(mrd.id) as materials_count,
            SUM(mrd.requested_quantity) as total_quantity
        FROM material_requests mr
        LEFT JOIN material_request_details mrd ON mr.id = mrd.request_id
        WHERE mr.work_order_id = ?
        AND mr.status IN ('approved', 'branch_approved', 'warehouse_approved', 'project_approved')
        GROUP BY mr.id, mr.request_number, mr.status, mr.request_date
        ORDER BY mr.request_date DESC
    ");

    $requestsStmt->execute([$workOrderId]);
    $requests = $requestsStmt->fetchAll();

    // دمج المواد من المصدرين
    // الأولوية للمعاملات الصادرة (تشمل التلقائية من طلبات الصرف + اليدوية)
    // طلبات الصرف تُستخدم فقط كمرجع احتياطي للمواد التي ليس لها معاملات صادرة بعد
    $merged = [];

    // أولاً: إضافة المواد من المعاملات الصادرة (المصدر الرئيسي)
    foreach ($txMaterials as $tm) {
        $mid = $tm['material_id'];
        $merged[$mid] = [
            'material_id' => $mid,
            'item_number' => $tm['item_number'],
            'description' => $tm['description'],
            'unit' => $tm['unit'],
            'group_number' => $tm['group_number'] ?? '',
            'qty' => (float) $tm['total_outgoing_quantity'],
        ];
    }

    // ثانياً: إضافة المواد من طلبات الصرف التي ليس لها معاملات صادرة بعد
    foreach ($requestMaterials as $rm) {
        $mid = $rm['material_id'];
        if (!isset($merged[$mid])) {
            $merged[$mid] = [
                'material_id' => $mid,
                'item_number' => $rm['item_number'],
                'description' => $rm['description'],
                'unit' => $rm['unit'],
                'group_number' => $rm['group_number'] ?? '',
                'qty' => (float) $rm['total_requested_quantity'],
            ];
        }
    }

    // ترتيب حسب رقم المادة
    usort($merged, function ($a, $b) {
        return strcmp($a['item_number'], $b['item_number']);
    });

    // إعداد البيانات للإرسال
    $response = [
        'success' => true,
        'work_order' => [
            'id' => $workOrder['id'],
            'work_order_number' => $workOrder['work_order_number'],
            'type_code' => $workOrder['type_code'],
            'branch_name' => $workOrder['branch_name'],
            'department' => $workOrder['department']
        ],
        'materials' => [],
        'requests' => $requests,
        'summary' => [
            'total_materials' => count($merged),
            'total_requests' => count($requests),
            'total_quantity' => 0
        ]
    ];

    // معالجة المواد
    $totalQuantity = 0;
    foreach ($merged as $item) {
        $totalQuantity += $item['qty'];

        $response['materials'][] = [
            'material_id' => $item['material_id'],
            'item_number' => $item['item_number'],
            'material_description' => $item['description'],
            'material_group' => $item['group_number'],
            'description' => $item['description'],
            'unit' => $item['unit'],
            'group_number' => $item['group_number'],
            'total_dispensed_quantity' => round($item['qty'], 3),
            'estimated_quantity' => round($item['qty'], 3)
        ];
    }

    $response['summary']['total_quantity'] = round($totalQuantity, 3);

    // إرسال الاستجابة
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error in get-work-order-materials.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في جلب البيانات',
        'details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>