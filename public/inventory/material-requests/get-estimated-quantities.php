<?php
/**
 * API لجلب الكميات المقدرة (المقايسة) للمواد في طلب صرف
 * للمقارنة مع الكميات المطلوبة وإظهار الفروقات
 */

// تنظيف أي مخرجات سابقة
if (ob_get_level()) {
    ob_end_clean();
}

// إيقاف عرض الأخطاء في الإنتاج
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
    if (!$workOrderId) {
        // إذا لم يكن هناك أمر عمل، أرجع البيانات الأساسية فقط
        $materialsStmt = $db->prepare("
            SELECT
                mrd.material_id,
                mrd.requested_quantity,
                m.item_number,
                mc.description,
                mc.unit,
                m.current_stock
            FROM material_request_details mrd
            INNER JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            WHERE mrd.request_id = ?
            ORDER BY m.item_number
        ");
        $materialsStmt->execute([$requestId]);
        $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

        $processedMaterials = [];
        foreach ($materials as $material) {
            $processedMaterials[] = [
                'material_id' => $material['material_id'],
                'item_number' => $material['item_number'],
                'description' => $material['description'],
                'unit' => $material['unit'],
                'requested_quantity' => floatval($material['requested_quantity']),
                'estimated_quantity' => 0,
                'current_stock' => floatval($material['current_stock']),
                'difference' => floatval($material['requested_quantity']),
                'difference_type' => 'none',
                'difference_class' => 'bg-secondary',
                'difference_text' => '-',
                'stock_status' => $material['current_stock'] > 0 ? 'available' : 'unavailable',
                'stock_class' => $material['current_stock'] > 0 ? 'bg-success' : 'bg-danger',
                'row_class' => $material['current_stock'] == 0 ? 'table-danger' : ''
            ];
        }

        echo json_encode([
            'success' => true,
            'request' => $request,
            'materials' => $processedMaterials,
            'statistics' => [
                'total_materials' => count($processedMaterials),
                'excess_requests' => 0,
                'unavailable_materials' => count(array_filter($processedMaterials, fn($m) => $m['stock_status'] === 'unavailable')),
                'insufficient_stock' => 0,
                'has_warnings' => false
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // فحص بنية جدول completion_certificate_materials
    $quantityField = 'ccm.estimated_quantity'; // افتراض وجود العمود

    try {
        $checkColumns = $db->query("SHOW COLUMNS FROM completion_certificate_materials");
        $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);

        $hasEstimatedQuantity = in_array('estimated_quantity', $columns);
        $hasActualQuantity = in_array('actual_quantity', $columns);
        $hasQuantity = in_array('quantity', $columns);

        $quantityField = $hasEstimatedQuantity ? 'ccm2.estimated_quantity' :
                        ($hasActualQuantity ? 'ccm2.actual_quantity' :
                        ($hasQuantity ? 'ccm2.quantity' : '0'));
    } catch (Exception $e) {
        // في حالة عدم وجود الجدول، استخدم قيمة افتراضية
        $quantityField = '0';
    }
    
    // جلب المواد من طلب الصرف مع المقايسة من شهادات الإنجاز
    $materialsStmt = $db->prepare("
        SELECT
            mrd.material_id,
            mrd.requested_quantity,
            m.item_number,
            mc.description,
            mc.unit,
            m.current_stock,
            COALESCE(
                (SELECT SUM($quantityField)
                 FROM completion_certificate_materials ccm2
                 INNER JOIN completion_certificates cc2 ON ccm2.certificate_id = cc2.id
                 WHERE ccm2.material_id = mrd.material_id
                   AND cc2.work_order_id = ?
                   AND cc2.status IN ('in_progress', 'completed')
                ), 0
            ) as estimated_quantity
        FROM material_request_details mrd
        INNER JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE mrd.request_id = ?
        ORDER BY m.item_number
    ");
    $materialsStmt->execute([$workOrderId, $requestId]);
    $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // معالجة البيانات وحساب الفروقات
    $processedMaterials = [];
    foreach ($materials as $material) {
        $requestedQuantity = floatval($material['requested_quantity']);
        $estimatedQuantity = floatval($material['estimated_quantity']);
        $currentStock = floatval($material['current_stock']);
        
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
