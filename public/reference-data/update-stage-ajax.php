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
if (empty($_POST['stage_id']) || !is_numeric($_POST['stage_id']) || 
    empty($_POST['stage_name']) || empty($_POST['stage_key']) || empty($_POST['stage_order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المرحلة واسم المرحلة ومفتاح المرحلة وترتيب المرحلة مطلوبة']);
    exit();
}

$stageId = (int) $_POST['stage_id'];
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
    
    // التحقق من وجود المرحلة
    $stmt = $db->prepare("SELECT id FROM approval_stages WHERE id = ?");
    $stmt->execute([$stageId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'المرحلة غير موجودة']);
        exit();
    }
    
    // التحقق من عدم تكرار اسم المرحلة (باستثناء المرحلة الحالية)
    $stmt = $db->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_name = ? AND id != ?");
    $stmt->execute([$stageName, $stageId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'اسم المرحلة موجود مسبقاً']);
        exit();
    }
    
    // التحقق من عدم تكرار مفتاح المرحلة (باستثناء المرحلة الحالية)
    $stmt = $db->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_key = ? AND id != ?");
    $stmt->execute([$stageKey, $stageId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'مفتاح المرحلة موجود مسبقاً']);
        exit();
    }
    
    // التحقق من عدم تكرار ترتيب المرحلة (باستثناء المرحلة الحالية)
    $stmt = $db->prepare("SELECT COUNT(*) FROM approval_stages WHERE stage_order = ? AND id != ?");
    $stmt->execute([$stageOrder, $stageId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'ترتيب المرحلة موجود مسبقاً']);
        exit();
    }
    
    // تحديث المرحلة
    $stmt = $db->prepare("
        UPDATE approval_stages 
        SET stage_key = ?, stage_name = ?, stage_description = ?, stage_order = ?, 
            stage_color = ?, is_active = ?, is_final = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $stageKey, 
        $stageName, 
        $stageDescription, 
        $stageOrder, 
        $stageColor, 
        $isActive ? 1 : 0, 
        $isFinal ? 1 : 0, 
        $stageId
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث مرحلة الاعتماد بنجاح',
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
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث مرحلة الاعتماد']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث مرحلة الاعتماد: ' . $e->getMessage()
    ]);
}
?>
