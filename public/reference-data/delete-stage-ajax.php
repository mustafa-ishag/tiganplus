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
    echo json_encode(['success' => false, 'message' => 'معرف المرحلة مطلوب']);
    exit();
}

$stageId = (int) $_POST['id'];

try {
    $db = getDB();
    
    // التحقق من وجود المرحلة
    $stmt = $db->prepare("SELECT stage_name, stage_key FROM approval_stages WHERE id = ?");
    $stmt->execute([$stageId]);
    $stage = $stmt->fetch();
    
    if (!$stage) {
        echo json_encode(['success' => false, 'message' => 'المرحلة غير موجودة']);
        exit();
    }
    
    // التحقق من عدم وجود مستخلصات مرتبطة بهذه المرحلة
    $stmt = $db->prepare("SELECT COUNT(*) FROM partial_extracts WHERE approval_stage = ?");
    $stmt->execute([$stage['stage_key']]);
    $extractsCount = $stmt->fetchColumn();
    
    if ($extractsCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "لا يمكن حذف المرحلة لأنها مرتبطة بـ {$extractsCount} مستخلص جزئي"
        ]);
        exit();
    }
    
    // حذف المرحلة
    $stmt = $db->prepare("DELETE FROM approval_stages WHERE id = ?");
    $result = $stmt->execute([$stageId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف مرحلة الاعتماد "' . $stage['stage_name'] . '" بنجاح'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في حذف مرحلة الاعتماد']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء حذف مرحلة الاعتماد: ' . $e->getMessage()
    ]);
}
?>
