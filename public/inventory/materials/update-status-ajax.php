<?php
/**
 * تحديث حالة المادة عبر AJAX
 * Update Material Status via AJAX
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
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في تحميل الملفات المطلوبة',
        'debug' => $e->getMessage()
    ]);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يرجى تسجيل الدخول أولاً',
        'debug' => 'Session user_id not found or empty'
    ]);
    exit;
}

// التحقق من الصلاحيات
if (!function_exists('hasPermission')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في النظام - دالة الصلاحيات غير موجودة',
        'debug' => 'hasPermission function not found'
    ]);
    exit;
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
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

$materialId = (int)($input['material_id'] ?? 0);
$isActive = (int)($input['is_active'] ?? 0);

if ($materialId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المادة غير صحيح']);
    exit;
}

try {
    $db = getDB();

    // التحقق من وجود المادة
    $stmt = $db->prepare("SELECT m.id, m.item_number, mc.description FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.id = ?");
    $stmt->execute([$materialId]);
    $material = $stmt->fetch();

    if (!$material) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المادة غير موجودة']);
        exit;
    }

    // تحديث حالة المادة
    $stmt = $db->prepare("UPDATE materials SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$isActive, $materialId]);

    if ($result && $stmt->rowCount() > 0) {
        $action = $isActive ? 'تفعيل' : 'إلغاء تفعيل';

        // تسجيل العملية في السجل (اختياري)
        if (function_exists('logActivity')) {
            logActivity($_SESSION['user_id'], 'update_material_status', "تم {$action} المادة: {$material['item_number']} - {$material['description']}");
        }

        echo json_encode([
            'success' => true,
            'message' => "تم {$action} المادة بنجاح",
            'material_id' => $materialId,
            'new_status' => $isActive
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'فشل في تحديث حالة المادة',
            'debug' => 'No rows affected'
        ]);
    }

} catch (Exception $e) {
    error_log("Error updating material status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
}
?>
