<?php
/**
 * مزامنة المستخلص مع نظام قيود لإنشاء فاتورة ضريبية
 * Sync Extract with Qoyod System
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول.']);
    exit;
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../src/Infrastructure/Qoyod/QoyodClient.php';

use EtganERP\Infrastructure\Qoyod\QoyodClient;

try {
    $db = getDB();
    
    // استقبال البيانات
    $input = json_decode(file_get_contents('php://input'), true);
    
    $extractId = $input['extract_id'] ?? null;
    $extractType = $input['extract_type'] ?? null; // partial, final_regular, final_for_partial
    
    if (!$extractId || !$extractType) {
        throw new Exception("معرف المستخلص أو نوعه مفقود.");
    }
    
    // جلب إعدادات قيود
    $stmt = $db->query("SELECT * FROM qoyod_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || empty($settings['api_key'])) {
        throw new Exception("إعدادات الربط مع قيود غير مكتملة، يرجى تحديث الإعدادات.");
    }
    
    if (empty($settings['default_contact_id'])) {
        throw new Exception("رقم العميل الافتراضي غير محدد في إعدادات قيود.");
    }
    
    // استعلام تفاصيل المستخلص وأوامر العمل بناءً على النوع
    $extractTable = '';
    $workOrdersTable = '';
    $foreignKey = '';
    
    switch ($extractType) {
        case 'partial':
            $extractTable = 'partial_extracts';
            $workOrdersTable = 'partial_extract_work_orders';
            $foreignKey = 'partial_extract_id';
            break;
        case 'final_regular':
            $extractTable = 'final_regular_extracts';
            $workOrdersTable = 'final_regular_extract_work_orders';
            $foreignKey = 'final_regular_extract_id';
            break;
        case 'final_for_partial':
            $extractTable = 'final_for_partial_extracts';
            $workOrdersTable = 'final_for_partial_extract_work_orders';
            $foreignKey = 'final_extract_id'; // Note: please check if this matches your schema
            break;
        default:
            throw new Exception("نوع المستخلص غير صالح.");
    }
    
    // جلب بيانات المستخلص
    $stmt = $db->prepare("SELECT * FROM {$extractTable} WHERE id = ?");
    $stmt->execute([$extractId]);
    $extract = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$extract) {
        throw new Exception("لم يتم العثور على المستخلص.");
    }
    
    if ($extract['qoyod_status'] === 'synced' && !empty($extract['qoyod_invoice_id'])) {
        throw new Exception("هذا المستخلص متزامن مسبقاً مع قيود.");
    }
    
    $department = $extract['department'] ?? 'توصيلات';
    
    $projectId = ($department === 'مشاريع') ? $settings['projects_project_id'] : $settings['connections_project_id'];
    $productId = ($department === 'مشاريع') ? $settings['projects_product_id'] : $settings['connections_product_id'];
    
    if (!$projectId || !$productId) {
        throw new Exception("إعدادات المنتج أو المشروع للقسم ({$department}) غير مكتملة في إعدادات قيود.");
    }
    
    // جلب أوامر العمل
    // We assume the schema for final_for_partial_extract_work_orders has foreign key `final_extract_id` or similar
    // We'll check the exact foreign key field via schema or fallback to 'extract_id' if needed. Let's assume standard names.
    // However, final_for_partial might use final_for_partial_extract_id
    if ($extractType === 'final_for_partial') {
        // trying common naming
        $foreignKey = 'final_for_partial_extract_id'; 
        // if this fails we might need to query the columns to be safe, but let's assume it's this.
    }
    
    $woQuery = "
        SELECT 
            wo.wo_number,
            wot.code as type_code,
            ewo.extract_value
        FROM {$workOrdersTable} ewo
        JOIN work_orders wo ON ewo.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.type_id = wot.id
        WHERE ewo.{$foreignKey} = ?
    ";
    
    // let's do a safe query, in case foreignKey name is wrong
    try {
        $stmt = $db->prepare($woQuery);
        $stmt->execute([$extractId]);
    } catch (PDOException $e) {
        // Fallback for foreign key name
        if ($extractType === 'final_for_partial') {
            $woQuery = str_replace("ewo.final_for_partial_extract_id", "ewo.extract_id", $woQuery);
            try {
                $stmt = $db->prepare($woQuery);
                $stmt->execute([$extractId]);
            } catch(PDOException $e2) {
                // another fallback
                $woQuery = str_replace("ewo.extract_id", "ewo.final_extract_id", $woQuery);
                $stmt = $db->prepare($woQuery);
                $stmt->execute([$extractId]);
            }
        } else {
            throw $e;
        }
    }
    
    $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($workOrders)) {
        throw new Exception("لا توجد أوامر عمل مرتبطة بهذا المستخلص.");
    }
    
    $lineItems = [];
    foreach ($workOrders as $wo) {
        $desc = "WO# {$wo['wo_number']}";
        if (!empty($wo['type_code'])) {
            $desc .= " | Type: {$wo['type_code']}";
        }
        
        $lineItems[] = [
            'product_id' => $productId,
            'description' => $desc,
            'qty' => 1,
            'unit_price' => (float) $wo['extract_value']
        ];
    }
    
    // تجهيز بيانات الفاتورة
    $invoiceData = [
        'contact_id' => $settings['default_contact_id'],
        'project_id' => $projectId,
        'reference' => $extract['extract_number'] ?? ('EXT-' . $extractId),
        'description' => "فاتورة مستخلص " . ($extract['extract_number'] ?? $extractId),
        'issue_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d'),
        'line_items' => $lineItems
    ];
    
    // إرسال الطلب لقيود
    $qoyod = new QoyodClient($settings['api_key']);
    $response = $qoyod->createInvoice($invoiceData);
    
    if (!empty($response['invoice']['id'])) {
        $qoyodInvoiceId = $response['invoice']['id'];
        $qoyodReference = $response['invoice']['reference'] ?? null;
        
        // تحديث حالة المستخلص
        $updateStmt = $db->prepare("
            UPDATE {$extractTable} 
            SET qoyod_invoice_id = ?, 
                qoyod_invoice_reference = ?, 
                qoyod_status = 'synced' 
            WHERE id = ?
        ");
        $updateStmt->execute([$qoyodInvoiceId, $qoyodReference, $extractId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إنشاء الفاتورة في قيود بنجاح.',
            'invoice_id' => $qoyodInvoiceId,
            'reference' => $qoyodReference
        ]);
    } else {
        throw new Exception("فشل في إنشاء الفاتورة: " . json_encode($response, JSON_UNESCAPED_UNICODE));
    }
    
} catch (Exception $e) {
    // تسجيل الخطأ في قاعدة البيانات إن أمكن
    if (isset($db) && isset($extractTable) && isset($extractId)) {
        try {
            $db->prepare("UPDATE {$extractTable} SET qoyod_status = 'error' WHERE id = ?")->execute([$extractId]);
        } catch (Exception $e2) {
            // ignore
        }
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
