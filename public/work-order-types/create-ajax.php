<?php

declare(strict_types=1);

// منع عرض الأخطاء في المتصفح
ini_set('display_errors', '0');
error_reporting(0);

use EtganERP\Application\WorkOrderType\CreateWorkOrderType\CreateWorkOrderTypeCommand;
use EtganERP\Application\WorkOrderType\CreateWorkOrderType\CreateWorkOrderTypeHandler;
use EtganERP\Infrastructure\Persistence\WorkOrderTypeRepository;

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

    // التحقق من البيانات المطلوبة
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $status = $_POST['status'] ?? 'active';

    if (empty($code)) {
        throw new InvalidArgumentException('كود النوع مطلوب');
    }

    if (strlen($code) < 2 || strlen($code) > 10) {
        throw new InvalidArgumentException('يجب أن يكون طول الكود بين 2-10 أحرف');
    }

    if (!preg_match('/^[A-Za-z0-9]+$/', $code)) {
        throw new InvalidArgumentException('يجب أن يحتوي الكود على أحرف إنجليزية وأرقام فقط');
    }

    // إنشاء الأمر والمعالج
    $workOrderTypeRepository = new WorkOrderTypeRepository();
    $command = new CreateWorkOrderTypeCommand($code, $description, $status);
    $createWorkOrderTypeHandler = new CreateWorkOrderTypeHandler($workOrderTypeRepository);

    // تنفيذ إنشاء نوع أمر العمل
    $response = $createWorkOrderTypeHandler->handle($command);

    echo json_encode([
        'success' => true,
        'message' => $response->message,
        'data' => [
            'id' => $response->id,
            'code' => $response->code
        ]
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
