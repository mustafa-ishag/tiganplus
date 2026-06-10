<?php
/**
 * تبديل حالة المفضلة لأمر العمل
 * Toggle Work Order Favorite Status via AJAX
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير مسموحة']);
    exit();
}

// الحصول على البيانات
$workOrderId = isset($_POST['work_order_id']) ? intval($_POST['work_order_id']) : 0;

// التحقق من صحة البيانات
if (!$workOrderId) {
    echo json_encode(['success' => false, 'message' => 'رقم أمر العمل مطلوب']);
    exit();
}

try {
    $db = getDB();

    // التحقق من وجود أمر العمل والحصول على حالته الحالية
    $stmt = $db->prepare("SELECT id, is_favorite FROM work_orders WHERE id = ?");
    $stmt->execute([$workOrderId]);
    $workOrder = $stmt->fetch();

    if (!$workOrder) {
        echo json_encode(['success' => false, 'message' => 'أمر العمل غير موجود']);
        exit();
    }

    // تبديل حالة المفضلة
    $newFavoriteStatus = $workOrder['is_favorite'] ? 0 : 1;

    // تحديث حالة المفضلة
    $sql = "UPDATE work_orders SET is_favorite = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$newFavoriteStatus, $workOrderId]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'is_favorite' => $newFavoriteStatus,
            'message' => $newFavoriteStatus ? 'تمت إضافة أمر العمل إلى المفضلة' : 'تمت إزالة أمر العمل من المفضلة'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة المفضلة']);
    }

} catch (PDOException $e) {
    error_log('Database Error in toggle-favorite-ajax.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في قاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Error in toggle-favorite-ajax.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء معالجة الطلب'
    ], JSON_UNESCAPED_UNICODE);
}
?>

