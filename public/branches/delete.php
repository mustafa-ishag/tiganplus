<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('branches_delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لحذف الفروع'], JSON_UNESCAPED_UNICODE);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit();
}

// قراءة البيانات من POST أو JSON
$input = $_POST;
if (empty($input)) {
    $input = json_decode(file_get_contents('php://input'), true);
}

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit();
}

$branchId = $input['id'] ?? null;

if (!$branchId) {
    echo json_encode(['success' => false, 'message' => 'معرف الفرع مطلوب']);
    exit();
}

try {
    $db = getDB();

    // التحقق من وجود الفرع
    $stmt = $db->prepare("SELECT id, name, status FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        echo json_encode(['success' => false, 'message' => 'الفرع غير موجود']);
        exit();
    }

    // التحقق من إمكانية الحذف (يجب أن يكون غير نشط)
    if ($branch['status'] === 'active') {
        echo json_encode(['success' => false, 'message' => 'لا يمكن حذف فرع نشط. يجب إلغاء تفعيله أولاً']);
        exit();
    }

    // التحقق من عدم وجود أوامر عمل مرتبطة بهذا الفرع
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM work_orders WHERE branch_id = ?");
    $stmt->execute([$branchId]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'لا يمكن حذف هذا الفرع لأنه مرتبط بأوامر عمل موجودة']);
        exit();
    }

    // حذف الفرع
    $stmt = $db->prepare("DELETE FROM branches WHERE id = ?");
    $result = $stmt->execute([$branchId]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف الفرع بنجاح'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حذف الفرع']);
    }

} catch (Exception $e) {
    error_log("Error deleting branch: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء حذف الفرع: ' . $e->getMessage()]);
}
?>
