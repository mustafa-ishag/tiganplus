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
    echo json_encode(['success' => false, 'message' => 'معرف المرحلة مطلوب']);
    exit();
}

$stageId = (int) $_GET['id'];

try {
    $db = getDB();
    
    // جلب بيانات المرحلة
    $stmt = $db->prepare("SELECT * FROM approval_stages WHERE id = ?");
    $stmt->execute([$stageId]);
    $stage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المرحلة غير موجودة']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stage
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
    ]);
}
?>
