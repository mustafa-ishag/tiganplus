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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// التحقق من البيانات المطلوبة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الجهة مطلوب']);
    exit();
}

$entityId = (int) $_GET['id'];

try {
    $db = getDB();
    
    // جلب بيانات الجهة
    $stmt = $db->prepare("SELECT * FROM current_entities WHERE id = ?");
    $stmt->execute([$entityId]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$entity) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الجهة غير موجودة']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $entity
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
    ]);
}
?>
