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
if (empty($_POST['stage_name']) || empty($_POST['stage_key']) || empty($_POST['stage_order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'اسم المرحلة ومفتاح المرحلة وترتيب المرحلة مطلوبة']);
    exit();
}

$stageName = trim($_POST['stage_name']);
$stageKey = trim($_POST['stage_key']);
$stageDescription = !empty($_POST['stage_description']) ? trim($_POST['stage_description']) : null;
$stageOrder = (int) $_POST['stage_order'];
$stageColor = !empty($_POST['stage_color']) ? trim($_POST['stage_color']) : 'primary';
$isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';
$isFinal = isset($_POST['is_final']) && $_POST['is_final'] === '1';

// التحقق من صحة مفتاح المرحلة
if (!preg_match('/^[a-z_]+$/', $stageKey)) {
    echo json_encode(['success' => false, 'message' => 'مفتاح المرحلة يجب أن يحتوي على أحرف إنجليزية صغيرة وشرطة سفلية فقط']);
    exit();
}

// التحقق من صحة ترتيب المرحلة
if ($stageOrder < 1 || $stageOrder > 99) {
    echo json_encode(['success' => false, 'message' => 'ترتيب المرحلة يجب أن يكون بين 1 و 99']);
    exit();
}

try {
    $db = getDB();
    
    // التحقق من عدم تكرار اسم المرحلة
    $stmt = $db->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_name = ?");
    $stmt->execute([$stageName]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'اسم المرحلة موجود مسبقاً']);
        exit();
    }
    
    // التحقق من عدم تكرار مفتاح المرحلة
    $stmt = $db->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_key = ?");
    $stmt->execute([$stageKey]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'مفتاح المرحلة موجود مسبقاً']);
        exit();
    }
    
    // التحقق من عدم تكرار ترتيب المرحلة
    $stmt = $db->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_order = ?");
    $stmt->execute([$stageOrder]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'ترتيب المرحلة موجود مسبقاً']);
        exit();
    }
    
    // إدراج المرحلة الجديدة
    $stmt = $db->prepare("
        INSERT INTO approval_stages 
        (stage_key, stage_name, stage_description, stage_order, stage_color, is_active, is_final, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $stmt->execute([
        $stageKey, 
        $stageName, 
        $stageDescription, 
        $stageOrder, 
        $stageColor, 
        $isActive ? 1 : 0, 
        $isFinal ? 1 : 0
    ]);
    
    if ($result) {
        $stageId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إضافة مرحلة الاعتماد بنجاح',
            'data' => [
                'id' => $stageId,
                'stage_name' => $stageName,
                'stage_key' => $stageKey,
                'stage_description' => $stageDescription,
                'stage_order' => $stageOrder,
                'stage_color' => $stageColor,
                'is_active' => $isActive,
                'is_final' => $isFinal
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في إضافة مرحلة الاعتماد']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء إضافة مرحلة الاعتماد: ' . $e->getMessage()
    ]);
}
?>
