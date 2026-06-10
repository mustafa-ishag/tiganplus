<?php

declare(strict_types=1);

// تحميل التطبيق
require_once __DIR__ . '/../../bootstrap/app.php';

use EtganERP\Infrastructure\Persistence\WorkOrderTypeRepository;
use EtganERP\Application\WorkOrderType\UpdateWorkOrderType\UpdateWorkOrderTypeCommand;
use EtganERP\Application\WorkOrderType\UpdateWorkOrderType\UpdateWorkOrderTypeHandler;
use EtganERP\Domain\Shared\ValueObjects\Id;

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

try {
    // التحقق من البيانات المطلوبة
    $id = $_POST['id'] ?? null;
    $description = trim($_POST['description'] ?? '') ?: null;
    $status = $_POST['status'] ?? 'inactive';

    if (!$id || !is_numeric($id)) {
        throw new InvalidArgumentException('معرف نوع أمر العمل مطلوب');
    }

    // التحقق من وجود نوع أمر العمل
    $workOrderTypeRepository = new WorkOrderTypeRepository();
    $workOrderType = $workOrderTypeRepository->findById(new Id((int) $id));
    
    if (!$workOrderType) {
        throw new InvalidArgumentException('نوع أمر العمل غير موجود');
    }

    // إنشاء الأمر والمعالج
    $command = new UpdateWorkOrderTypeCommand((int) $id, $description, $status);
    $updateWorkOrderTypeHandler = new UpdateWorkOrderTypeHandler($workOrderTypeRepository);

    // تنفيذ تحديث نوع أمر العمل
    $response = $updateWorkOrderTypeHandler->handle($command);

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
}
?>
