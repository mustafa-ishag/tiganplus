<?php
/**
 * API لتوليد بنود الأعمال تلقائياً بناءً على المواد المستخدمة في الشهادة
 * Auto Generate Work Items Based on Certificate Materials
 */

session_start();

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير مدعومة']);
    exit();
}

// قراءة البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);

// التحقق من البيانات المطلوبة
if (!isset($input['materials']) || !is_array($input['materials']) || empty($input['materials'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات المواد مطلوبة']);
    exit();
}

$certificateMaterials = $input['materials'];
$userId = $_SESSION['user_id'];

try {
    $db = getDB();

    // تسجيل للتشخيص
    error_log("auto-generate-from-materials.php: Starting with materials: " . json_encode($certificateMaterials));

    // التحقق من صحة بيانات المواد
    $materialIds = array_column($certificateMaterials, 'material_id');
    $materialIds = array_filter($materialIds, 'is_numeric');

    error_log("auto-generate-from-materials.php: Material IDs: " . json_encode($materialIds));
    
    if (empty($materialIds)) {
        throw new Exception('لا توجد مواد صحيحة في البيانات المرسلة');
    }
    
    // جلب بيانات المواد
    $materialsQuery = "
        SELECT id, item_number, description, unit
        FROM materials 
        WHERE id IN (" . str_repeat('?,', count($materialIds) - 1) . "?)
        AND is_active = 1
    ";
    
    $materialsStmt = $db->prepare($materialsQuery);
    $materialsStmt->execute($materialIds);
    $materialsData = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($materialsData)) {
        throw new Exception('لم يتم العثور على المواد المحددة');
    }
    
    // تحويل بيانات المواد إلى مصفوفة مفهرسة
    $materialsById = [];
    foreach ($materialsData as $material) {
        $materialsById[$material['id']] = $material;
    }
    
    // جلب العلاقات بين المواد وبنود الأعمال
    $relationshipsQuery = "
        SELECT 
            mwi.*,
            wi.item_number as work_item_number,
            wi.description as work_item_description,
            wi.unit as work_item_unit
        FROM material_work_items mwi
        INNER JOIN work_items wi ON mwi.work_item_id = wi.id
        WHERE mwi.material_id IN (" . str_repeat('?,', count($materialIds) - 1) . "?)
        AND mwi.is_active = 1
        AND wi.is_active = 1
        ORDER BY mwi.is_primary DESC, wi.item_number
    ";
    
    $relationshipsStmt = $db->prepare($relationshipsQuery);
    $relationshipsStmt->execute($materialIds);
    $relationships = $relationshipsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($relationships)) {
        throw new Exception('لا توجد علاقات محددة بين المواد المحددة وبنود الأعمال');
    }
    
    // تجميع العلاقات حسب المادة
    $materialRelationships = [];
    foreach ($relationships as $rel) {
        $materialRelationships[$rel['material_id']][] = $rel;
    }
    
    // حساب بنود الأعمال المطلوبة
    $workItemsToGenerate = [];
    $generationLog = [];
    
    foreach ($certificateMaterials as $certMaterial) {
        $materialId = $certMaterial['material_id'];
        $usedQuantity = $certMaterial['quantity'];
        
        if (!isset($materialsById[$materialId])) {
            $generationLog[] = "تحذير: المادة بمعرف $materialId غير موجودة";
            continue;
        }
        
        $materialData = $materialsById[$materialId];
        
        if (!isset($materialRelationships[$materialId])) {
            $generationLog[] = "تحذير: لا توجد علاقة محددة للمادة {$materialData['item_number']} - {$materialData['description']}";
            continue;
        }
        
        foreach ($materialRelationships[$materialId] as $rel) {
            $workItemId = $rel['work_item_id'];
            $quantityRatio = $rel['quantity_ratio'];
            $calculatedQuantity = $usedQuantity * $quantityRatio;
            
            if (!isset($workItemsToGenerate[$workItemId])) {
                error_log("auto-generate-from-materials.php: Work item {$rel['work_item_number']}");

                $workItemsToGenerate[$workItemId] = [
                    'work_item_id' => $workItemId,
                    'work_item_number' => $rel['work_item_number'],
                    'work_item_description' => $rel['work_item_description'],
                    'work_item_unit' => $rel['work_item_unit'],
                    'total_quantity' => 0,
                    'contributing_materials' => []
                ];
            }
            
            $workItemsToGenerate[$workItemId]['total_quantity'] += $calculatedQuantity;
            
            $workItemsToGenerate[$workItemId]['contributing_materials'][] = [
                'material_number' => $materialData['item_number'],
                'material_description' => $materialData['description'],
                'used_quantity' => $usedQuantity,
                'quantity_ratio' => $quantityRatio,
                'calculated_quantity' => $calculatedQuantity,
                'is_primary' => $rel['is_primary']
            ];
            
            $generationLog[] = "تم حساب {$calculatedQuantity} {$rel['work_item_unit']} من بند العمل {$rel['work_item_number']} بناءً على {$usedQuantity} {$materialData['unit']} من المادة {$materialData['item_number']}";
        }
    }
    
    if (empty($workItemsToGenerate)) {
        throw new Exception('لم يتم العثور على بنود أعمال قابلة للتوليد');
    }
    
    // حساب الإجمالي
    $workItemsCount = count($workItemsToGenerate);
    
    // إعداد الاستجابة
    $response = [
        'success' => true,
        'message' => "تم توليد $workItemsCount بند عمل تلقائياً",
        'data' => [
            'work_items_count' => $workItemsCount,
            'generated_work_items' => array_values($workItemsToGenerate),
            'generation_log' => $generationLog
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("auto-generate-from-materials.php: Error - " . $e->getMessage());
    error_log("auto-generate-from-materials.php: Stack trace - " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
