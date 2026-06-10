<?php

declare(strict_types=1);

// منع عرض الأخطاء في المتصفح
ini_set('display_errors', '0');
error_reporting(0);

use EtganERP\Infrastructure\Persistence\WorkOrderRepository;
use EtganERP\Domain\Shared\ValueObjects\Id;

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // تحميل التطبيق
    require_once __DIR__ . '/../../bootstrap/app.php';

    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // قراءة البيانات من JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'البيانات المرسلة غير صحيحة'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = (int) $input['id'];
    
    if ($id <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    // البحث عن أمر العمل
    $workOrderRepository = new WorkOrderRepository();
    $workOrder = $workOrderRepository->findById(new Id($id));
    
    if (!$workOrder) {
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }

    // التحقق من إمكانية الحذف
    if ($workOrder->isAssignedToExtract()) {
        throw new InvalidArgumentException('لا يمكن حذف أمر العمل لأنه مرتبط بمستخلص. يجب إلغاء ربطه بالمستخلص أولاً.');
    }

    // حفظ رقم أمر العمل للرسالة
    $workOrderNumber = $workOrder->workOrderNumber()->value();

    // حذف أمر العمل
    $workOrderRepository->delete($workOrder);

    echo json_encode([
        'success' => true,
        'message' => "تم حذف أمر العمل '{$workOrderNumber}' بنجاح"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
