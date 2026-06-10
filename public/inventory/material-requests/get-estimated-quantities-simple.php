<?php
// تنظيف أي مخرجات سابقة
if (ob_get_level()) {
    ob_end_clean();
}

// إيقاف عرض الأخطاء
ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';

    header('Content-Type: application/json; charset=utf-8');
    
    $requestId = intval($_GET['request_id'] ?? 0);
    
    if ($requestId <= 0) {
        throw new Exception('معرف طلب الصرف مطلوب');
    }
    
    $db = getDB();

    // جلب معلومات طلب الصرف وأمر العمل
    $requestStmt = $db->prepare("
        SELECT mr.id, mr.work_order_id, wo.work_order_number
        FROM material_requests mr
        LEFT JOIN work_orders wo ON mr.work_order_id = wo.id
        WHERE mr.id = ?
    ");
    $requestStmt->execute([$requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new Exception('طلب الصرف غير موجود');
    }

    $workOrderId = $request['work_order_id'];

    // جلب المواد من طلب الصرف مع المقايسة
    if ($workOrderId) {
        $materialsStmt = $db->prepare("
            SELECT
                mrd.material_id,
                mrd.requested_quantity,
                m.item_number,
                mc.description,
                mc.unit,
                m.current_stock,
                COALESCE(SUM(ccm.estimated_quantity), 0) as estimated_quantity
            FROM material_request_details mrd
            INNER JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            LEFT JOIN completion_certificate_materials ccm ON m.id = ccm.material_id
            LEFT JOIN completion_certificates cc ON ccm.certificate_id = cc.id
                AND cc.work_order_id = ?
                AND cc.status IN ('in_progress', 'completed')
            WHERE mrd.request_id = ?
            GROUP BY mrd.material_id, mrd.requested_quantity, m.item_number, mc.description, mc.unit, m.current_stock
            ORDER BY m.item_number
        ");
        $materialsStmt->execute([$workOrderId, $requestId]);
    } else {
        $materialsStmt = $db->prepare("
            SELECT
                mrd.material_id,
                mrd.requested_quantity,
                m.item_number,
                mc.description,
                mc.unit,
                m.current_stock,
                0 as estimated_quantity
            FROM material_request_details mrd
            INNER JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            WHERE mrd.request_id = ?
            ORDER BY m.item_number
        ");
        $materialsStmt->execute([$requestId]);
    }

    $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // معالجة البيانات
    $processedMaterials = [];
    foreach ($materials as $material) {
        $requestedQuantity = floatval($material['requested_quantity']);
        $estimatedQuantity = floatval($material['estimated_quantity']);
        $currentStock = floatval($material['current_stock']);

        // حساب الفرق
        $difference = $requestedQuantity - $estimatedQuantity;

        // تحديد نوع الفرق
        $differenceType = 'none';
        $differenceClass = 'bg-secondary';
        $differenceText = '-';

        if ($estimatedQuantity > 0) {
            if ($difference > 0) {
                $differenceType = 'excess';
                $differenceClass = 'bg-warning text-dark';
                $differenceText = '+' . number_format($difference, 3);
            } elseif ($difference < 0) {
                $differenceType = 'shortage';
                $differenceClass = 'bg-info';
                $differenceText = number_format($difference, 3);
            } else {
                $differenceType = 'exact';
                $differenceClass = 'bg-success';
                $differenceText = '0';
            }
        }

        // تحديد حالة المخزون
        $stockStatus = 'available';
        $stockClass = 'bg-success';
        $rowClass = '';

        if ($currentStock == 0) {
            $stockStatus = 'unavailable';
            $stockClass = 'bg-danger';
            $rowClass = 'table-danger';
        } elseif ($requestedQuantity > $currentStock) {
            $stockStatus = 'insufficient';
            $stockClass = 'bg-warning';
            $rowClass = 'table-warning';
        }

        // إضافة تحذير إضافي للصرف الزائد
        if ($differenceType === 'excess') {
            $rowClass = $rowClass ? $rowClass . ' excess-request' : 'table-warning excess-request';
        }

        $processedMaterials[] = [
            'material_id' => $material['material_id'],
            'item_number' => $material['item_number'],
            'description' => $material['description'],
            'unit' => $material['unit'],
            'requested_quantity' => $requestedQuantity,
            'estimated_quantity' => $estimatedQuantity,
            'current_stock' => $currentStock,
            'difference' => $difference,
            'difference_type' => $differenceType,
            'difference_class' => $differenceClass,
            'difference_text' => $differenceText,
            'stock_status' => $stockStatus,
            'stock_class' => $stockClass,
            'row_class' => $rowClass
        ];
    }
    
    // إحصائيات
    $totalMaterials = count($processedMaterials);
    $excessCount = count(array_filter($processedMaterials, fn($m) => $m['difference_type'] === 'excess'));
    $unavailableCount = count(array_filter($processedMaterials, fn($m) => $m['stock_status'] === 'unavailable'));
    $insufficientCount = count(array_filter($processedMaterials, fn($m) => $m['stock_status'] === 'insufficient'));

    $response = [
        'success' => true,
        'request' => [
            'id' => $request['id'],
            'work_order_id' => $request['work_order_id'],
            'work_order_number' => $request['work_order_number']
        ],
        'materials' => $processedMaterials,
        'statistics' => [
            'total_materials' => $totalMaterials,
            'excess_requests' => $excessCount,
            'unavailable_materials' => $unavailableCount,
            'insufficient_stock' => $insufficientCount,
            'has_warnings' => ($excessCount > 0 || $unavailableCount > 0 || $insufficientCount > 0)
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'Exception',
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'request_id' => $_GET['request_id'] ?? 'غير محدد',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
