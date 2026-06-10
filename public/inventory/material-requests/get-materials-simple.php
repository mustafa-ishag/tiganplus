<?php
/**
 * نسخة مبسطة من API لجلب المواد من شهادات الإنجاز
 */

// تنظيف أي مخرجات سابقة
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

try {
    // تحميل الإعدادات
    require_once __DIR__ . '/../../../config/config.php';
    
    // التحقق من المعاملات
    if (!isset($_GET['work_order_id']) || empty($_GET['work_order_id'])) {
        throw new Exception('معرف أمر العمل مطلوب');
    }
    
    $workOrderId = (int)$_GET['work_order_id'];
    $db = getDB();
    
    // التحقق من وجود أمر العمل
    $workOrderStmt = $db->prepare("SELECT * FROM work_orders WHERE id = ?");
    $workOrderStmt->execute([$workOrderId]);
    $workOrder = $workOrderStmt->fetch();
    
    if (!$workOrder) {
        throw new Exception('أمر العمل غير موجود');
    }
    
    // جلب المواد بطريقة مبسطة
    $materialsStmt = $db->prepare("
        SELECT 
            ccm.material_id,
            m.item_number,
            mc.description,
            mc.unit,
            ccm.quantity as estimated_quantity,
            m.unit_price,
            m.current_stock,
            mc.group_number
        FROM completion_certificates cc
        INNER JOIN completion_certificate_materials ccm ON cc.id = ccm.certificate_id
        INNER JOIN materials m ON ccm.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE cc.work_order_id = ?
        AND cc.status IN ('in_progress', 'completed')
        AND m.is_active = 1
        AND ccm.quantity > 0
        ORDER BY m.item_number
        LIMIT 50
    ");
    
    $materialsStmt->execute([$workOrderId]);
    $materials = $materialsStmt->fetchAll();
    
    // إعداد الاستجابة
    $response = [
        'success' => true,
        'work_order' => [
            'id' => $workOrder['id'],
            'work_order_number' => $workOrder['work_order_number']
        ],
        'materials' => [],
        'summary' => [
            'total_materials' => count($materials)
        ]
    ];
    
    // معالجة المواد
    foreach ($materials as $material) {
        $response['materials'][] = [
            'material_id' => $material['material_id'],
            'item_number' => $material['item_number'],
            'description' => $material['description'],
            'unit' => $material['unit'],
            'estimated_quantity' => round($material['estimated_quantity'], 3),
            'unit_price' => round($material['unit_price'], 2),
            'current_stock' => round($material['current_stock'], 3),
            'group_number' => $material['group_number'] ?? ''
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
