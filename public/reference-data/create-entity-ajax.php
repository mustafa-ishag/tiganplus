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
if (empty($_POST['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'اسم الجهة مطلوب']);
    exit();
}

$name = trim($_POST['name']);
$code = !empty($_POST['code']) ? trim($_POST['code']) : null;
$description = !empty($_POST['description']) ? trim($_POST['description']) : null;
$isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

try {
    $db = getDB();
    
    // التحقق من عدم تكرار الاسم
    $stmt = $db->prepare("SELECT COUNT(*) FROM current_entities WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'اسم الجهة موجود مسبقاً']);
        exit();
    }
    
    // التحقق من عدم تكرار الكود إذا تم إدخاله
    if ($code) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM current_entities WHERE code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'كود الجهة موجود مسبقاً']);
            exit();
        }
    }
    
    // إدراج الجهة الجديدة
    $stmt = $db->prepare("
        INSERT INTO current_entities (name, code, description, is_active, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $stmt->execute([$name, $code, $description, $isActive ? 1 : 0]);
    
    if ($result) {
        $entityId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة الجهة بنجاح',
            'data' => [
                'id' => $entityId,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'is_active' => $isActive
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في إضافة الجهة']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء إضافة الجهة: ' . $e->getMessage()
    ]);
}
?>
