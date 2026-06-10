<?php
/**
 * تفعيل المواد - نسخة مبسطة للاختبار
 */

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// التحقق البسيط من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'يرجى تسجيل الدخول',
        'session_status' => session_status(),
        'session_data' => $_SESSION
    ]);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'يجب استخدام POST',
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    exit();
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['material_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'بيانات مفقودة',
        'input' => $input
    ]);
    exit();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    
    $db = Database::getInstance()->getConnection();
    
    $materialId = (int)$input['material_id'];
    
    // تفعيل المادة
    $stmt = $db->prepare("UPDATE materials SET is_active = 1, updated_at = NOW() WHERE id = ? AND is_active = 0");
    $result = $stmt->execute([$materialId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'تم تفعيل المادة بنجاح',
            'material_id' => $materialId,
            'rows_affected' => $stmt->rowCount()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم العثور على المادة أو أنها نشطة بالفعل',
            'material_id' => $materialId
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage(),
        'error' => $e->getTraceAsString()
    ]);
}
?>
