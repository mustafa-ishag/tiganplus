<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // التحقق من الصلاحيات
    if (!hasPermission('work_orders_delete')) {
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لحذف أوامر العمل'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = getDB();
    $workOrderId = (int) ($_POST['id'] ?? 0);

    if ($workOrderId <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    // التحقق من وجود أمر العمل
    $stmt = $db->prepare("SELECT work_order_number FROM work_orders WHERE id = ?");
    $stmt->execute([$workOrderId]);
    $workOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$workOrder) {
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }

    // بدء المعاملة
    $db->beginTransaction();

    try {
        // حذف المرفقات المرتبطة
        $stmt = $db->prepare("DELETE FROM work_order_attachments WHERE work_order_id = ?");
        $stmt->execute([$workOrderId]);

        // حذف أمر العمل
        $stmt = $db->prepare("DELETE FROM work_orders WHERE id = ?");
        $result = $stmt->execute([$workOrderId]);

        if ($result) {
            $db->commit();
            echo json_encode([
                'success' => true,
                'message' => 'تم حذف أمر العمل بنجاح'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception('فشل في حذف أمر العمل');
        }

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (InvalidArgumentException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('خطأ في حذف أمر العمل: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى'
    ], JSON_UNESCAPED_UNICODE);
}
?>
