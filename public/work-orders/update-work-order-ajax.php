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

    $db = getDB();

    // التحقق من البيانات المطلوبة
    $workOrderId = (int) ($_POST['id'] ?? 0);
    $workOrderNumber = trim($_POST['work_order_number'] ?? '');
    $workOrderTypeId = (int) ($_POST['work_order_type_id'] ?? 0);
    $department = trim($_POST['department'] ?? '');
    $branchId = (int) ($_POST['branch_id'] ?? 0);
    $location = trim($_POST['location'] ?? '') ?: null;
    $assignmentDate = !empty($_POST['assignment_date']) ? $_POST['assignment_date'] : null;
    $receiptDate = !empty($_POST['receipt_date']) ? $_POST['receipt_date'] : null;
    $estimatedValue = !empty($_POST['estimated_value']) ? (float) $_POST['estimated_value'] : null;
    $actualValue = !empty($_POST['actual_value']) ? (float) $_POST['actual_value'] : null;
    $disbursementStatus = trim($_POST['disbursement_status'] ?? 'none');
    $status = trim($_POST['status'] ?? 'active');
    $notes = trim($_POST['notes'] ?? '');

    // التحقق من صحة البيانات
    if ($workOrderId <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    if (empty($workOrderNumber)) {
        throw new InvalidArgumentException('رقم أمر العمل مطلوب');
    }

    if ($workOrderTypeId <= 0) {
        throw new InvalidArgumentException('نوع أمر العمل مطلوب');
    }

    if (!in_array($department, ['connections', 'projects'])) {
        throw new InvalidArgumentException('القسم غير صحيح');
    }

    if ($branchId <= 0) {
        throw new InvalidArgumentException('الفرع مطلوب');
    }

    // التحقق من وجود أمر العمل
    $stmt = $db->prepare("SELECT id FROM work_orders WHERE id = ?");
    $stmt->execute([$workOrderId]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }

    // التحقق من عدم تكرار رقم أمر العمل
    $stmt = $db->prepare("SELECT id FROM work_orders WHERE work_order_number = ? AND id != ?");
    $stmt->execute([$workOrderNumber, $workOrderId]);
    if ($stmt->fetch()) {
        throw new InvalidArgumentException('رقم أمر العمل موجود مسبقاً');
    }

    // التحقق من وجود نوع أمر العمل
    $stmt = $db->prepare("SELECT id FROM work_order_types WHERE id = ? AND status = 'active'");
    $stmt->execute([$workOrderTypeId]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('نوع أمر العمل غير صحيح');
    }

    // التحقق من وجود الفرع
    $stmt = $db->prepare("SELECT id FROM branches WHERE id = ?");
    $stmt->execute([$branchId]);
    if (!$stmt->fetch()) {
        throw new InvalidArgumentException('الفرع غير صحيح');
    }

    // تحديث أمر العمل
    $sql = "UPDATE work_orders SET
                work_order_number = ?,
                work_order_type_id = ?,
                department = ?,
                branch_id = ?,
                location = ?,
                assignment_date = ?,
                receipt_date = ?,
                estimated_value = ?,
                actual_value = ?,
                disbursement_status = ?,
                status = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?";

    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $workOrderNumber,
        $workOrderTypeId,
        $department,
        $branchId,
        $location,
        $assignmentDate,
        $receiptDate,
        $estimatedValue,
        $actualValue,
        $disbursementStatus,
        $status,
        $notes,
        $workOrderId
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث أمر العمل بنجاح',
            'work_order_id' => $workOrderId
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('فشل في تحديث أمر العمل');
    }

} catch (InvalidArgumentException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('خطأ في تحديث أمر العمل: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى'
    ], JSON_UNESCAPED_UNICODE);
}
?>
