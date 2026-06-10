<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح بالوصول']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// التحقق من البيانات المطلوبة
if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الجهة مطلوب']);
    exit();
}

$entityId = (int) $_POST['id'];

try {
    $db = getDB();
    
    // التحقق من وجود الجهة
    $stmt = $db->prepare("SELECT name FROM current_entities WHERE id = ?");
    $stmt->execute([$entityId]);
    $entity = $stmt->fetch();
    
    if (!$entity) {
        echo json_encode(['success' => false, 'message' => 'الجهة غير موجودة']);
        exit();
    }
    
    // التحقق من عدم وجود أوامر عمل مرتبطة بهذه الجهة
    $stmt = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE current_entity_id = ?");
    $stmt->execute([$entityId]);
    $workOrdersCount = $stmt->fetchColumn();
    
    if ($workOrdersCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "لا يمكن حذف الجهة لأنها مرتبطة بـ {$workOrdersCount} أمر عمل"
        ]);
        exit();
    }
    
    // حذف الجهة
    $stmt = $db->prepare("DELETE FROM current_entities WHERE id = ?");
    $result = $stmt->execute([$entityId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف الجهة "' . $entity['name'] . '" بنجاح'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حذف الجهة']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء حذف الجهة: ' . $e->getMessage()
    ]);
}
?>
