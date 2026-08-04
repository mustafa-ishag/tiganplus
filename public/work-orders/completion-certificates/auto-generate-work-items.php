<?php
/**
 * API لتوليد بنود الأعمال تلقائياً بناءً على المواد المصروفة
 * Auto Generate Work Items Based on Dispensed Materials
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

// التحقق من البيانات المطلوبة
if (!isset($_POST['work_order_id']) || !is_numeric($_POST['work_order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف أمر العمل مطلوب']);
    exit();
}

$workOrderId = (int)$_POST['work_order_id'];
$userId = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // التحقق من وجود أمر العمل
    $workOrderStmt = $db->prepare("SELECT id, work_order_number, contract_id FROM work_orders WHERE id = ?");
    $workOrderStmt->execute([$workOrderId]);
    $workOrder = $workOrderStmt->fetch();
    
    if (!$workOrder) {
        throw new Exception('أمر العمل غير موجود');
    }
    
    if (empty($workOrder['contract_id'])) {
        throw new Exception('أمر العمل غير مرتبط بعقد، ولا يمكن توليد بنود أعمال له');
    }
    
    // جلب المواد المصروفة لأمر العمل
    $dispensedMaterialsQuery = "
        SELECT 
            mt.material_id,
            m.item_number as material_number,
            mc.description as material_description,
            mc.unit as material_unit,
            SUM(mt.quantity_out) as total_dispensed_quantity
        FROM material_transactions mt
        INNER JOIN materials m ON mt.material_id = m.id
        WHERE mt.work_order_id = ? 
        AND mt.transaction_type = 'out'
        AND mt.quantity_out > 0
        GROUP BY mt.material_id, m.item_number, mc.description, mc.unit
        HAVING total_dispensed_quantity > 0
        ORDER BY m.item_number
    ";
    
    $dispensedStmt = $db->prepare($dispensedMaterialsQuery);
    $dispensedStmt->execute([$workOrderId]);
    $dispensedMaterials = $dispensedStmt->fetchAll();
    
    if (empty($dispensedMaterials)) {
        throw new Exception('لا توجد مواد مصروفة لهذا أمر العمل');
    }
    
    // جلب العلاقات بين المواد وبنود الأعمال
    $materialWorkItemsQuery = "
        SELECT 
            mwi.*,
            wi.item_number as work_item_number,
            wi.description as work_item_description,
            wi.unit as work_item_unit,
            wi.price as work_item_unit_price
        FROM material_work_items mwi
        INNER JOIN contract_work_items wi ON mwi.contract_work_item_id = wi.id
        WHERE mwi.material_id IN (" . str_repeat('?,', count($dispensedMaterials) - 1) . "?)
        AND wi.contract_id = ?
        AND mwi.is_active = 1
        AND wi.is_active = 1
        ORDER BY mwi.is_primary DESC, wi.item_number
    ";
    
    $materialIds = array_column($dispensedMaterials, 'material_id');
    // إضافة معرف العقد إلى المعاملات
    $queryParams = $materialIds;
    $queryParams[] = $workOrder['contract_id'];
    
    $relationshipsStmt = $db->prepare($materialWorkItemsQuery);
    $relationshipsStmt->execute($queryParams);
    $relationships = $relationshipsStmt->fetchAll();
    
    if (empty($relationships)) {
        throw new Exception('لا توجد علاقات محددة بين المواد المصروفة وبنود الأعمال ضمن عقد أمر العمل هذا');
    }
    
    // تجميع العلاقات حسب المادة
    $materialRelationships = [];
    foreach ($relationships as $rel) {
        $materialRelationships[$rel['material_id']][] = $rel;
    }
    
    // حساب بنود الأعمال المطلوبة
    $workItemsToGenerate = [];
    $generationLog = [];
    
    foreach ($dispensedMaterials as $material) {
        $materialId = $material['material_id'];
        $dispensedQuantity = $material['total_dispensed_quantity'];
        
        if (!isset($materialRelationships[$materialId])) {
            $generationLog[] = "تحذير: لا توجد علاقة محددة للمادة {$material['material_number']} - {$material['material_description']}";
            continue;
        }
        
        foreach ($materialRelationships[$materialId] as $rel) {
            $workItemId = $rel['contract_work_item_id'];
            $quantityRatio = $rel['quantity_ratio'];
            $calculatedQuantity = $dispensedQuantity * $quantityRatio;
            
            if (!isset($workItemsToGenerate[$workItemId])) {
                $workItemsToGenerate[$workItemId] = [
                    'work_item_id' => $workItemId,
                    'work_item_number' => $rel['work_item_number'],
                    'work_item_description' => $rel['work_item_description'],
                    'work_item_unit' => $rel['work_item_unit'],
                    'work_item_unit_price' => $rel['work_item_unit_price'],
                    'total_quantity' => 0,
                    'total_amount' => 0,
                    'contributing_materials' => []
                ];
            }
            
            $workItemsToGenerate[$workItemId]['total_quantity'] += $calculatedQuantity;
            $workItemsToGenerate[$workItemId]['total_amount'] = 
                $workItemsToGenerate[$workItemId]['total_quantity'] * $rel['work_item_unit_price'];
            
            $workItemsToGenerate[$workItemId]['contributing_materials'][] = [
                'material_number' => $material['material_number'],
                'material_description' => $material['material_description'],
                'dispensed_quantity' => $dispensedQuantity,
                'quantity_ratio' => $quantityRatio,
                'calculated_quantity' => $calculatedQuantity,
                'is_primary' => $rel['is_primary']
            ];
            
            $generationLog[] = "تم حساب {$calculatedQuantity} {$rel['work_item_unit']} من بند العمل {$rel['work_item_number']} بناءً على {$dispensedQuantity} {$material['material_unit']} من المادة {$material['material_number']}";
        }
    }
    
    if (empty($workItemsToGenerate)) {
        throw new Exception('لم يتم العثور على بنود أعمال قابلة للتوليد');
    }
    
    // بدء المعاملة
    $db->beginTransaction();
    
    try {
        // التحقق من وجود شهادة إنجاز لأمر العمل
        $certificateStmt = $db->prepare("
            SELECT id FROM completion_certificates 
            WHERE work_order_id = ?
        ");
        $certificateStmt->execute([$workOrderId]);
        $certificate = $certificateStmt->fetch();
        
        $certificateId = null;
        
        if (!$certificate) {
            // إنشاء شهادة إنجاز جديدة
            $createCertificateStmt = $db->prepare("
                INSERT INTO completion_certificates 
                (work_order_id, certificate_number, certificate_date, status, created_by, created_at)
                VALUES (?, ?, CURDATE(), 'draft', ?, NOW())
            ");
            
            $certificateNumber = 'CC-' . $workOrder['work_order_number'] . '-' . date('Ymd');
            $createCertificateStmt->execute([$workOrderId, $certificateNumber, $userId]);
            $certificateId = $db->lastInsertId();
            
            $generationLog[] = "تم إنشاء شهادة إنجاز جديدة برقم: $certificateNumber";
        } else {
            $certificateId = $certificate['id'];
            $generationLog[] = "تم استخدام شهادة الإنجاز الموجودة";
        }
        
        // حذف بنود الأعمال الموجودة (إن وجدت) لإعادة التوليد
        $deleteExistingStmt = $db->prepare("
            DELETE FROM completion_certificate_work_items 
            WHERE completion_certificate_id = ?
        ");
        $deleteExistingStmt->execute([$certificateId]);
        
        // إدراج بنود الأعمال المحسوبة
        $insertWorkItemStmt = $db->prepare("
            INSERT INTO completion_certificate_work_items 
            (completion_certificate_id, work_item_id, quantity, unit_price, total_amount, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $totalCertificateAmount = 0;
        $insertedCount = 0;
        
        foreach ($workItemsToGenerate as $workItem) {
            $notes = "تم التوليد تلقائياً بناءً على المواد المصروفة:\n";
            foreach ($workItem['contributing_materials'] as $material) {
                $notes .= "- {$material['material_number']}: {$material['dispensed_quantity']} × {$material['quantity_ratio']} = {$material['calculated_quantity']}\n";
            }
            
            $insertWorkItemStmt->execute([
                $certificateId,
                $workItem['work_item_id'],
                $workItem['total_quantity'],
                $workItem['work_item_unit_price'],
                $workItem['total_amount'],
                $notes,
                $userId
            ]);
            
            $totalCertificateAmount += $workItem['total_amount'];
            $insertedCount++;
            
            $generationLog[] = "تم إدراج بند العمل {$workItem['work_item_number']} بكمية {$workItem['total_quantity']} {$workItem['work_item_unit']}";
        }
        
        // تحديث إجمالي مبلغ شهادة الإنجاز
        $updateCertificateStmt = $db->prepare("
            UPDATE completion_certificates 
            SET total_amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateCertificateStmt->execute([$totalCertificateAmount, $certificateId]);
        
        $db->commit();
        
        // إعداد الاستجابة
        $response = [
            'success' => true,
            'message' => "تم توليد $insertedCount بند عمل تلقائياً بإجمالي مبلغ " . number_format($totalCertificateAmount, 2) . " ريال",
            'data' => [
                'certificate_id' => $certificateId,
                'work_items_count' => $insertedCount,
                'total_amount' => $totalCertificateAmount,
                'generated_work_items' => array_values($workItemsToGenerate),
                'generation_log' => $generationLog
            ]
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
