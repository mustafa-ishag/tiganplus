<?php
/**
 * API مبسط لجلب المواد المستخدمة من طلبات الصرف المعتمدة
 */

// تعيين نوع المحتوى
header('Content-Type: application/json; charset=utf-8');

// بدء الجلسة
session_start();

// التحقق من معرف أمر العمل
if (!isset($_GET['work_order_id']) || empty($_GET['work_order_id'])) {
    echo json_encode(['success' => false, 'error' => 'معرف أمر العمل مطلوب'], JSON_UNESCAPED_UNICODE);
    exit();
}

$workOrderId = (int) $_GET['work_order_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    $db = getDB();

    // فحص أمر العمل
    $workOrderStmt = $db->prepare("
        SELECT wo.*, wot.type_code, b.name as branch_name 
        FROM work_orders wo 
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        LEFT JOIN branches b ON wo.branch_id = b.id
        WHERE wo.id = ?
    ");
    $workOrderStmt->execute([$workOrderId]);
    $workOrder = $workOrderStmt->fetch();

    if (!$workOrder) {
        echo json_encode(['success' => false, 'error' => 'أمر العمل غير موجود'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // جلب طلبات الصرف المعتمدة
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

    // جلب المواد من الطلبات المعتمدة
    $materials = [];
    $totalQuantity = 0;

    // 1) المواد من طلبات الصرف المعتمدة
    $materialsStmt = $db->prepare("
        SELECT DISTINCT
            m.id as material_id,
            m.item_number,
            mc.description,
            mc.unit,
            mc.group_number,
            SUM(mrd.requested_quantity) as total_requested_quantity
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

    // 2) المواد من المعاملات الصادرة
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

    // 3) دمج المواد من المصدرين
    // الأولوية للمعاملات الصادرة (تشمل التلقائية من طلبات الصرف + اليدوية)
    $merged = [];

    // أولاً: المعاملات الصادرة (المصدر الرئيسي)
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

    // ثانياً: طلبات الصرف التي ليس لها معاملات صادرة بعد
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

    foreach ($merged as $item) {
        $totalQuantity += $item['qty'];
        $materials[] = [
            'material_id' => $item['material_id'],
            'item_number' => $item['item_number'],
            'description' => $item['description'],
            'unit' => $item['unit'],
            'group_number' => $item['group_number'],
            'estimated_quantity' => round($item['qty'], 3),
        ];
    }

    // إعداد الاستجابة
    $response = [
        'success' => true,
        'work_order' => [
            'id' => $workOrder['id'],
            'work_order_number' => $workOrder['work_order_number'],
            'type_code' => $workOrder['type_code'] ?? 'غير محدد',
            'branch_name' => $workOrder['branch_name'] ?? 'غير محدد',
            'department' => $workOrder['department'] ?? 'غير محدد'
        ],
        'materials' => $materials,
        'requests' => $requests,
        'summary' => [
            'total_materials' => count($materials),
            'total_requests' => count($requests),
            'total_quantity' => round($totalQuantity, 3)
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في جلب البيانات',
        'details' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>