<?php
/**
 * WhatsApp Bot API - Material Analysis Endpoint
 * 
 * يستقبل رقم أمر العمل ويعيد تحليل المواد المنصرفة
 * بصيغة JSON + رسالة واتساب منسقة جاهزة للإرسال
 * 
 * Usage: GET /api/whatsapp/material-analysis.php?wo=4000012345&key=API_KEY
 */

// منع عرض الأخطاء في الـ API
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ترويسات CORS و JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// =============================================
// ⚙️ إعدادات API
// =============================================

// مفتاح API للأمان - غيّره لمفتاح سري خاص بك
define('WHATSAPP_API_KEY', 'tiqan_wa_bot_2026_secure_key');

// =============================================
// 🔒 التحقق من مفتاح API
// =============================================
$apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== WHATSAPP_API_KEY) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'مفتاح API غير صحيح'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// 📥 استقبال رقم أمر العمل
// =============================================
$workOrderNumber = trim($_GET['wo'] ?? '');

if (empty($workOrderNumber)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'يرجى إرسال رقم أمر العمل (wo)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// 🗄️ الاتصال بقاعدة البيانات
// =============================================
try {
    // تحميل إعدادات قاعدة البيانات
    // نستخدم الثوابت مباشرة بدلاً من config.php لتجنب مشاكل الجلسات
    $dbHost = 'localhost';
    $dbName = 'etgan_erp';
    $dbUser = 'root';
    $dbPass = '';
    
    $db = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    error_log('[WhatsApp API] DB Connection Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في الاتصال بقاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// 🔍 البحث عن أمر العمل
// =============================================
try {
    $stmtWO = $db->prepare("
        SELECT wo.*, wot.description as type_description, wot.type_code
        FROM work_orders wo
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        WHERE wo.work_order_number = ?
        LIMIT 1
    ");
    $stmtWO->execute([$workOrderNumber]);
    $workOrder = $stmtWO->fetch();

    if (!$workOrder) {
        echo json_encode([
            'success' => false,
            'message' => "لم يتم العثور على أمر عمل بالرقم: {$workOrderNumber}"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $workOrderId = $workOrder['id'];

    // =============================================
    // 📦 جلب المواد من طلبات الصرف
    // =============================================
    $stmtReq = $db->prepare("
        SELECT
            mrd.material_id,
            m.item_number,
            mc.description,
            mc.unit,
            SUM(mrd.requested_quantity) as request_qty
        FROM material_request_details mrd
        JOIN material_requests mr ON mrd.request_id = mr.id
        JOIN materials m ON mrd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE mr.work_order_id = ?
        GROUP BY mrd.material_id, m.item_number, mc.description, mc.unit
    ");
    $stmtReq->execute([$workOrderId]);
    $requestMaterials = $stmtReq->fetchAll();

    // =============================================
    // 📤 جلب المواد من المعاملات الصادرة
    // =============================================
    $stmtTx = $db->prepare("
        SELECT
            td.material_id,
            m.item_number,
            mc.description,
            mc.unit,
            SUM(td.quantity) as transaction_qty
        FROM transaction_details td
        JOIN inventory_transactions it ON td.transaction_id = it.id
        JOIN materials m ON td.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
        WHERE it.work_order_id = ?
          AND it.transaction_type = 'outgoing'
        GROUP BY td.material_id, m.item_number, mc.description, mc.unit
    ");
    $stmtTx->execute([$workOrderId]);
    $transactionMaterials = $stmtTx->fetchAll();

    // =============================================
    // 📐 جلب كميات المقايسة من شهادات الإنجاز
    // =============================================
    $stmtEst = $db->prepare("
        SELECT
            ccm.material_id,
            SUM(ccm.estimated_quantity) as estimated_qty,
            SUM(ccm.dispensed_quantity)  as dispensed_qty,
            SUM(ccm.returned_quantity)   as returned_qty
        FROM completion_certificate_materials ccm
        JOIN completion_certificates cc ON ccm.certificate_id = cc.id
        WHERE cc.work_order_id = ?
        GROUP BY ccm.material_id
    ");
    $stmtEst->execute([$workOrderId]);
    $estimateMaterials = [];
    foreach ($stmtEst->fetchAll() as $em) {
        $estimateMaterials[$em['material_id']] = [
            'estimated_qty' => (float) $em['estimated_qty'],
            'dispensed_qty' => (float) $em['dispensed_qty'],
            'returned_qty'  => (float) $em['returned_qty'],
        ];
    }

    // =============================================
    // 🔄 دمج البيانات
    // =============================================
    $merged = [];

    foreach ($requestMaterials as $rm) {
        $mid = $rm['material_id'];
        $merged[$mid] = [
            'material_id'     => $mid,
            'item_number'     => $rm['item_number'],
            'description'     => $rm['description'],
            'unit'            => $rm['unit'],
            'request_qty'     => (float) $rm['request_qty'],
            'transaction_qty' => 0,
            'estimated_qty'   => $estimateMaterials[$mid]['estimated_qty'] ?? 0,
            'dispensed_qty'   => $estimateMaterials[$mid]['dispensed_qty'] ?? 0,
            'returned_qty'    => $estimateMaterials[$mid]['returned_qty']  ?? 0,
        ];
    }

    foreach ($transactionMaterials as $tm) {
        $mid = $tm['material_id'];
        if (isset($merged[$mid])) {
            $merged[$mid]['transaction_qty'] = (float) $tm['transaction_qty'];
        } else {
            $merged[$mid] = [
                'material_id'     => $mid,
                'item_number'     => $tm['item_number'],
                'description'     => $tm['description'],
                'unit'            => $tm['unit'],
                'request_qty'     => 0,
                'transaction_qty' => (float) $tm['transaction_qty'],
                'estimated_qty'   => $estimateMaterials[$mid]['estimated_qty'] ?? 0,
                'dispensed_qty'   => $estimateMaterials[$mid]['dispensed_qty'] ?? 0,
                'returned_qty'    => $estimateMaterials[$mid]['returned_qty']  ?? 0,
            ];
        }
    }

    // إضافة مواد المقايسة غير الموجودة في المصدرين الآخرين
    foreach ($estimateMaterials as $mid => $estData) {
        if (!isset($merged[$mid])) {
            $matInfo = $db->prepare("SELECT m.item_number, mc.description, mc.unit FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.id = ?");
            $matInfo->execute([$mid]);
            $mat = $matInfo->fetch();
            if ($mat) {
                $merged[$mid] = [
                    'material_id'     => $mid,
                    'item_number'     => $mat['item_number'],
                    'description'     => $mat['description'],
                    'unit'            => $mat['unit'],
                    'request_qty'     => 0,
                    'transaction_qty' => 0,
                    'estimated_qty'   => $estData['estimated_qty'],
                    'dispensed_qty'   => $estData['dispensed_qty'],
                    'returned_qty'    => $estData['returned_qty'],
                ];
            }
        }
    }

    // ترتيب حسب رقم المادة
    usort($merged, function ($a, $b) {
        return strcmp($a['item_number'], $b['item_number']);
    });

    // =============================================
    // 📊 حساب الإجماليات
    // =============================================
    $totalDispensedQty  = 0;
    $totalEstimatedQty  = 0;
    $totalReturnedQty   = 0;
    $materials = [];

    foreach ($merged as $item) {
        $item['total_qty'] = $item['transaction_qty'];
        $materials[] = $item;
        $totalDispensedQty += $item['transaction_qty'];
        $totalEstimatedQty += $item['estimated_qty'];
        $totalReturnedQty  += $item['returned_qty'];
    }

    // =============================================
    // 💬 بناء رسالة واتساب المنسقة
    // =============================================
    $formattedMessage = buildWhatsAppMessage($workOrder, $materials, [
        'total_materials'    => count($materials),
        'total_dispensed'    => $totalDispensedQty,
        'total_estimated'    => $totalEstimatedQty,
        'total_returned'     => $totalReturnedQty,
    ]);

    // =============================================
    // ✅ إرجاع النتيجة
    // =============================================
    echo json_encode([
        'success'           => true,
        'work_order'        => [
            'id'               => $workOrder['id'],
            'number'           => $workOrder['work_order_number'],
            'type'             => $workOrder['type_description'] ?? '',
            'type_code'        => $workOrder['type_code'] ?? '',
            'status'           => $workOrder['status'] ?? '',
            'assignment_date'  => $workOrder['assignment_date'] ?? '',
        ],
        'materials'         => $materials,
        'summary'           => [
            'total_materials'  => count($materials),
            'total_dispensed'  => $totalDispensedQty,
            'total_estimated'  => $totalEstimatedQty,
            'total_returned'   => $totalReturnedQty,
        ],
        'formatted_message' => $formattedMessage,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[WhatsApp API] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب البيانات'
    ], JSON_UNESCAPED_UNICODE);
}

// =============================================
// 🛠️ دوال مساعدة
// =============================================

/**
 * بناء رسالة واتساب منسقة لتحليل المواد
 */
function buildWhatsAppMessage(array $workOrder, array $materials, array $summary): string
{
    $woNumber = $workOrder['work_order_number'];
    $woType   = $workOrder['type_description'] ?? '';
    $woCode   = $workOrder['type_code'] ?? '';
    $woDate   = !empty($workOrder['assignment_date']) 
                ? date('d/m/Y', strtotime($workOrder['assignment_date'])) 
                : '';
    $woStatus = $workOrder['status'] ?? '';

    // ترويسة الرسالة
    $msg  = "📊 *تحليل المواد - أمر عمل: {$woNumber}*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!empty($woType)) {
        $msg .= "📋 *النوع:* {$woType}";
        if (!empty($woCode)) $msg .= " ({$woCode})";
        $msg .= "\n";
    }
    if (!empty($woDate)) {
        $msg .= "📅 *التاريخ:* {$woDate}\n";
    }
    if (!empty($woStatus)) {
        $msg .= "📌 *الحالة:* {$woStatus}\n";
    }

    if (count($materials) === 0) {
        $msg .= "\n❌ لا توجد مواد منصرفة على أمر العمل هذا.\n";
        $msg .= "\n🤖 _نظام تِقان - Etgan ERP_";
        return $msg;
    }

    $msg .= "\n📦 *المواد المنصرفة:*\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";

    // أيقونات الأرقام
    $numbers = ['1️⃣','2️⃣','3️⃣','4️⃣','5️⃣','6️⃣','7️⃣','8️⃣','9️⃣','🔟'];
    
    // عرض أول 15 مادة كحد أقصى (لتجنب رسالة طويلة جداً)
    $maxItems = 15;
    $displayMaterials = array_slice($materials, 0, $maxItems);

    foreach ($displayMaterials as $i => $item) {
        $icon = isset($numbers[$i]) ? $numbers[$i] : '🔸';
        $itemNum  = $item['item_number'] ?? '-';
        $desc     = $item['description'] ?? '-';
        $unit     = $item['unit'] ?? '';
        $totalQty = (float)($item['total_qty'] ?? 0);
        $estQty   = (float)($item['estimated_qty'] ?? 0);
        $retQty   = (float)($item['returned_qty'] ?? 0);

        $msg .= "{$icon} *{$itemNum}* - {$desc}\n";
        $msg .= "   📤 المصروف: " . number_format($totalQty, 0) . " {$unit}\n";
        
        if ($estQty > 0) {
            $msg .= "   📐 المقايسة: " . number_format($estQty, 0) . " {$unit}\n";
        }
        if ($retQty > 0) {
            $msg .= "   🔄 المرتجع: " . number_format($retQty, 0) . " {$unit}\n";
        }
        $msg .= "--------------------\n";
    }

    // إذا كان هناك مواد أكثر من الحد الأقصى
    if (count($materials) > $maxItems) {
        $remaining = count($materials) - $maxItems;
        $msg .= "⚡ _و {$remaining} مادة أخرى..._\n";
        $msg .= "--------------------\n";
    }

    // الملخص
    $msg .= "\n📊 *الملخص:*\n";
    $msg .= "▪️ عدد المواد: {$summary['total_materials']}\n";
    $msg .= "▪️ إجمالي المصروف: " . number_format($summary['total_dispensed'], 0) . "\n";
    
    if ($summary['total_estimated'] > 0) {
        $msg .= "▪️ إجمالي المقايسة: " . number_format($summary['total_estimated'], 0) . "\n";
    }
    if ($summary['total_returned'] > 0) {
        $msg .= "▪️ إجمالي المرتجع: " . number_format($summary['total_returned'], 0) . "\n";
    }

    $msg .= "\n🤖 _نظام تِقان - Etgan ERP_";

    return $msg;
}
