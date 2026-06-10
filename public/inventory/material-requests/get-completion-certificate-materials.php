<?php
/**
 * جلب المواد من شهادات الإنجاز لأمر العمل
 * للاستخدام في إنشاء طلب صرف جديد
 */

// تنظيف أي مخرجات سابقة
ob_clean();

header('Content-Type: application/json; charset=utf-8');

// إيقاف عرض الأخطاء في الإنتاج
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

try {
    // التحقق من المعاملات
    if (!isset($_GET['work_order_id']) || empty($_GET['work_order_id'])) {
        throw new Exception('معرف أمر العمل مطلوب');
    }
    
    $workOrderId = (int)$_GET['work_order_id'];
    $db = getDB();

    // التحقق من وجود الجداول المطلوبة
    $tables = ['completion_certificates', 'completion_certificate_materials', 'work_orders', 'materials'];
    foreach ($tables as $table) {
        $checkTable = $db->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->rowCount() == 0) {
            throw new Exception("الجدول $table غير موجود في قاعدة البيانات");
        }
    }
    
    // التحقق من وجود أمر العمل
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
        throw new Exception('أمر العمل غير موجود');
    }

    // التحقق من وجود شهادات إنجاز لهذا أمر العمل
    $certificatesCheckStmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM completion_certificates
        WHERE work_order_id = ? AND status IN ('in_progress', 'completed')
    ");
    $certificatesCheckStmt->execute([$workOrderId]);
    $certificatesCount = $certificatesCheckStmt->fetch()['count'];

    if ($certificatesCount == 0) {
        throw new Exception('لا توجد شهادات إنجاز (جاري الإعداد أو مكتملة) لهذا أمر العمل');
    }
    
    // أولاً: فحص بنية جدول completion_certificate_materials
    $checkColumns = $db->query("SHOW COLUMNS FROM completion_certificate_materials");
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);

    // تحديد الأعمدة المتاحة
    $hasEstimatedQuantity = in_array('estimated_quantity', $columns);
    $hasMaterialCode = in_array('material_code', $columns);
    $hasMaterialDescription = in_array('material_description', $columns);
    $hasActualQuantity = in_array('actual_quantity', $columns);
    $hasQuantity = in_array('quantity', $columns);

    // بناء الاستعلام بناءً على الأعمدة المتاحة
    $quantityField = $hasEstimatedQuantity ? 'ccm.estimated_quantity' :
                    ($hasActualQuantity ? 'ccm.actual_quantity' :
                    ($hasQuantity ? 'ccm.quantity' : '0'));

    $materialCodeField = $hasMaterialCode ? 'ccm.material_code' : 'm.item_number';
    $materialDescField = $hasMaterialDescription ? 'ccm.material_description' : 'mc.description';

    // جلب المواد من شهادات الإنجاز المعتمدة لهذا أمر العمل
    $materialsStmt = $db->prepare("
        SELECT DISTINCT
            ccm.material_id,
            m.item_number,
            mc.description,
            ccmc.unit,
            $quantityField as estimated_quantity,
            m.current_stock,
            mc.group_number,
            cc.id as certificate_id,
            cc.certificate_date,
            cc.status as certificate_status
        FROM completion_certificates cc
        INNER JOIN completion_certificate_materials ccm ON cc.id = ccm.certificate_id
        INNER JOIN materials m ON ccm.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE cc.work_order_id = ?
        AND cc.status IN ('in_progress', 'completed')
        AND m.is_active = 1
        AND $quantityField > 0
        ORDER BY m.item_number
    ");
    
    $materialsStmt->execute([$workOrderId]);
    $materials = $materialsStmt->fetchAll();

    // تسجيل معلومات للتشخيص
    error_log("Materials query executed for work_order_id: $workOrderId");
    error_log("Materials found: " . count($materials));
    
    // جلب معلومات شهادات الإنجاز
    $certificatesStmt = $db->prepare("
        SELECT
            cc.id,
            cc.certificate_date,
            cc.status,
            cc.title,
            COUNT(ccm.id) as materials_count
        FROM completion_certificates cc
        LEFT JOIN completion_certificate_materials ccm ON cc.id = ccm.certificate_id
        WHERE cc.work_order_id = ?
        AND cc.status IN ('in_progress', 'completed')
        GROUP BY cc.id, cc.certificate_date, cc.status, cc.title
        ORDER BY cc.certificate_date DESC
    ");
    
    $certificatesStmt->execute([$workOrderId]);
    $certificates = $certificatesStmt->fetchAll();
    
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
        'certificates' => $certificates,
        'summary' => [
            'total_materials' => count($materials),
            'total_certificates' => count($certificates)
        ]
    ];
    
    // معالجة المواد
    foreach ($materials as $material) {
        $response['materials'][] = [
            'material_id' => $material['material_id'],
            'item_number' => $material['item_number'],
            'description' => $material['description'],
            'unit' => $material['unit'],
            'group_number' => $material['group_number'] ?? '',
            'estimated_quantity' => round($material['estimated_quantity'], 3),
            'current_stock' => round($material['current_stock'], 3),
            'certificate_id' => $material['certificate_id'],
            'certificate_date' => $material['certificate_date']
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);

    // تسجيل الخطأ للتشخيص
    error_log("Error in get-completion-certificate-materials.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Work Order ID: " . ($_GET['work_order_id'] ?? 'not set'));

    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'Exception',
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'work_order_id' => $_GET['work_order_id'] ?? 'not set',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);

} catch (Error $e) {
    http_response_code(500);

    error_log("Fatal error in get-completion-certificate-materials.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    $errorResponse = [
        'success' => false,
        'message' => 'خطأ في الخادم: ' . $e->getMessage(),
        'error_type' => 'Fatal Error',
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}
?>
