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
if (empty($_POST['entity_id']) || !is_numeric($_POST['entity_id']) || empty($_POST['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الجهة واسم الجهة مطلوبان']);
    exit();
}

$entityId = (int) $_POST['entity_id'];
$name = trim($_POST['name']);
$code = !empty($_POST['code']) ? trim($_POST['code']) : null;
$description = !empty($_POST['description']) ? trim($_POST['description']) : null;
$isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

try {
    $db = getDB();
    
    // التحقق من وجود الجهة
    $stmt = $db->prepare("SELECT id FROM current_entities WHERE id = ?");
    $stmt->execute([$entityId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'الجهة غير موجودة']);
        exit();
    }
    
    // التحقق من عدم تكرار الاسم (باستثناء الجهة الحالية)
    $stmt = $db->prepare("SELECT COUNT(*) FROM current_entities WHERE name = ? AND id != ?");
    $stmt->execute([$name, $entityId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'اسم الجهة موجود مسبقاً']);
        exit();
    }
    
    // التحقق من عدم تكرار الكود إذا تم إدخاله (باستثناء الجهة الحالية)
    if ($code) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM current_entities WHERE code = ? AND id != ?");
        $stmt->execute([$code, $entityId]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'كود الجهة موجود مسبقاً']);
            exit();
        }
    }
    
    // تحديث الجهة
    $stmt = $db->prepare("
        UPDATE current_entities 
        SET name = ?, code = ?, description = ?, is_active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([$name, $code, $description, $isActive ? 1 : 0, $entityId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث الجهة بنجاح',
            'data' => [
                'id' => $entityId,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'is_active' => $isActive
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث الجهة']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث الجهة: ' . $e->getMessage()
    ]);
}
?>
