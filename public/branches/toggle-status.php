<?php
/**
 * تفعيل/إلغاء تفعيل الفرع
 */

session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('branches_toggle_status')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتغيير حالة الفروع'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit();
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);
$branchId = (int)($input['id'] ?? 0);

if ($branchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف الفرع غير صحيح'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $db = getDB();

    // التحقق من وجود الفرع
    $stmt = $db->prepare("SELECT id, name, status FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        echo json_encode(['success' => false, 'message' => 'الفرع غير موجود'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // تحديد الحالة الجديدة
    $newStatus = $branch['status'] === 'active' ? 'inactive' : 'active';

    // تحديث حالة الفرع
    $stmt = $db->prepare("UPDATE branches SET status = ?, updated_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$newStatus, $branchId]);

    if ($result) {
        $statusText = $newStatus === 'active' ? 'نشط' : 'غير نشط';
        echo json_encode([
            'success' => true,
            'message' => "تم تغيير حالة الفرع إلى: $statusText",
            'new_status' => $newStatus,
            'status_text' => $statusText
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة الفرع'], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("خطأ في تغيير حالة الفرع: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تحديث الحالة'], JSON_UNESCAPED_UNICODE);
}
?>
