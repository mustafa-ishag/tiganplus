<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    
    $db = getDB();
    
    if (!isset($_GET['work_order_id']) || empty($_GET['work_order_id'])) {
        echo json_encode(['success' => false, 'error' => 'معرف أمر العمل مطلوب'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $workOrderId = (int)$_GET['work_order_id'];
    
    // التحقق من وجود شهادة إنجاز موجودة
    $stmt = $db->prepare("SELECT id FROM completion_certificates WHERE work_order_id = ?");
    $stmt->execute([$workOrderId]);
    $certificate = $stmt->fetch();
    
    if ($certificate) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'certificate_id' => $certificate['id']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في التحقق من الشهادة',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
