<?php
/**
 * تفعيل المواد غير النشطة
 * Activate Inactive Materials
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تعيين header للاستجابة JSON
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    require_once __DIR__ . '/../../../models/Material.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الملفات المطلوبة',
        'debug' => $e->getMessage()
    ]);
    exit();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يرجى تسجيل الدخول أولاً',
        'debug' => 'Session user_id not found or empty'
    ]);
    exit();
}

// التحقق من الصلاحيات
if (!function_exists('hasPermission')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في النظام - دالة الصلاحيات غير موجودة',
        'debug' => 'hasPermission function not found'
    ]);
    exit();
}

// فحص الصلاحيات (أي صلاحية مواد تكفي)
$hasAnyMaterialPermission = hasPermission('inventory_materials_edit') || hasPermission('inventory_materials_view');

if (!$hasAnyMaterialPermission) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية للوصول لنظام المواد',
        'debug' => 'Any materials permission required',
        'user_permissions' => $_SESSION['permissions'] ?? []
    ]);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة طلب غير مدعومة - يجب استخدام POST',
        'debug' => 'Method: ' . $_SERVER['REQUEST_METHOD']
    ]);
    exit();
}

// قراءة البيانات المرسلة
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'بيانات JSON غير صحيحة',
        'debug' => 'JSON Error: ' . json_last_error_msg() . ' | Raw input: ' . substr($rawInput, 0, 100)
    ]);
    exit();
}

if (!$input || (!isset($input['material_id']) && !isset($input['material_ids']))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'بيانات مفقودة - يجب تحديد material_id أو material_ids',
        'debug' => 'Input: ' . print_r($input, true)
    ]);
    exit();
}

$materialModel = new Material();

try {
    $db = getDB();
    $db->beginTransaction();
    
    $activatedCount = 0;
    
    // تفعيل مادة واحدة
    if (isset($input['material_id'])) {
        $materialId = (int)$input['material_id'];
        
        // التحقق من وجود المادة
        $material = $materialModel->findById($materialId);
        if (!$material) {
            throw new Exception('المادة غير موجودة');
        }
        
        if ($material['is_active'] == 1) {
            throw new Exception('المادة نشطة بالفعل');
        }
        
        // تفعيل المادة
        $stmt = $db->prepare("UPDATE materials SET is_active = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$materialId]);
        
        $activatedCount = 1;
        
        // تسجيل العملية في السجل
        if (function_exists('logActivity')) {
            logActivity($_SESSION['user_id'], 'activate_material', "تم تفعيل المادة: {$material['item_number']} - {$material['description']}");
        }
        
    }
    // تفعيل عدة مواد
    elseif (isset($input['material_ids']) && is_array($input['material_ids'])) {
        $materialIds = array_map('intval', $input['material_ids']);
        
        if (empty($materialIds)) {
            throw new Exception('لم يتم تحديد أي مواد');
        }
        
        // التحقق من وجود المواد
        $placeholders = str_repeat('?,', count($materialIds) - 1) . '?';
        $stmt = $db->prepare("SELECT m.id, m.item_number, mc.description FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.id IN ($placeholders) AND m.is_active = 0");
        $stmt->execute($materialIds);
        $materials = $stmt->fetchAll();
        
        if (empty($materials)) {
            throw new Exception('لا توجد مواد غير نشطة للتفعيل');
        }
        
        // تفعيل المواد
        $stmt = $db->prepare("UPDATE materials SET is_active = 1, updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($materialIds);
        
        $activatedCount = $stmt->rowCount();
        
        // تسجيل العملية في السجل
        if (function_exists('logActivity')) {
            $materialsList = implode(', ', array_column($materials, 'item_number'));
            logActivity($_SESSION['user_id'], 'activate_materials', "تم تفعيل {$activatedCount} مادة: {$materialsList}");
        }
        
    } else {
        throw new Exception('بيانات غير صحيحة');
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "تم تفعيل {$activatedCount} مادة بنجاح",
        'activated_count' => $activatedCount
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
