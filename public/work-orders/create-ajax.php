<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/WhatsAppService.php';

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// Log the request for debugging
error_log("Work Order Creation Request - Method: " . $_SERVER['REQUEST_METHOD'] . ", Session User: " . ($_SESSION['user_id'] ?? 'Not set'));

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        error_log("User not logged in - session data: " . print_r($_SESSION, true));
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // التحقق من الصلاحيات
    if (!hasPermission('work_orders_create')) {
        error_log("User " . $_SESSION['user_id'] . " does not have work_orders_create permission");
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لإنشاء أوامر العمل'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    error_log("User logged in: " . $_SESSION['user_id']);

    // Log POST data for debugging
    error_log("POST data: " . print_r($_POST, true));

    $db = getDB();
    error_log("Database connection successful");

    // التحقق من البيانات المطلوبة
    $workOrderNumber = trim($_POST['work_order_number'] ?? '');
    $workOrderTypeId = (int) ($_POST['work_order_type_id'] ?? 0);
    $department = trim($_POST['department'] ?? '');
    $branchId = (int) ($_POST['branch_id'] ?? 0);
    $location = trim($_POST['location'] ?? '') ?: null;
    $assignmentDate = !empty($_POST['assignment_date']) ? $_POST['assignment_date'] : null;
    $receiptDate = !empty($_POST['receipt_date']) ? $_POST['receipt_date'] : null;
    $estimatedValue = !empty($_POST['estimated_value']) ? (float) $_POST['estimated_value'] : 0.00;
    $actualValue = !empty($_POST['actual_value']) ? (float) $_POST['actual_value'] : 0.00;
    $disbursementStatus = trim($_POST['disbursement_status'] ?? 'none');
    $status = trim($_POST['status'] ?? 'active');
    $notes = trim($_POST['notes'] ?? '') ?: null;

    // التحقق من البيانات الأساسية
    if (empty($workOrderNumber)) {
        throw new InvalidArgumentException('يجب إدخال رقم أمر العمل');
    }

    if (!preg_match('/^\d{9}$/', $workOrderNumber)) {
        throw new InvalidArgumentException('يجب أن يكون رقم أمر العمل مكون من 9 أرقام بالضبط');
    }

    if ($workOrderTypeId <= 0) {
        throw new InvalidArgumentException('يجب اختيار نوع أمر العمل');
    }

    if (empty($department)) {
        throw new InvalidArgumentException('يجب اختيار القسم');
    }

    if (!in_array($department, ['connections', 'projects'])) {
        throw new InvalidArgumentException('القسم المحدد غير صحيح');
    }

    if ($branchId <= 0) {
        throw new InvalidArgumentException('يجب اختيار الفرع');
    }

    // التحقق من صحة التواريخ
    if ($assignmentDate && !DateTime::createFromFormat('Y-m-d', $assignmentDate)) {
        throw new InvalidArgumentException('تاريخ التكليف غير صحيح');
    }

    if ($receiptDate && !DateTime::createFromFormat('Y-m-d', $receiptDate)) {
        throw new InvalidArgumentException('تاريخ الاستلام غير صحيح');
    }

    // التحقق من أن تاريخ الاستلام بعد تاريخ التكليف
    if ($assignmentDate && $receiptDate && $receiptDate < $assignmentDate) {
        throw new InvalidArgumentException('تاريخ الاستلام يجب أن يكون بعد تاريخ التكليف');
    }

    // التحقق من القيم المالية
    if ($estimatedValue < 0) {
        throw new InvalidArgumentException('القيمة المقدرة يجب أن تكون أكبر من أو تساوي صفر');
    }

    if ($actualValue < 0) {
        throw new InvalidArgumentException('القيمة الفعلية يجب أن تكون أكبر من أو تساوي صفر');
    }

    // التحقق من طول الملاحظات
    if ($notes && strlen($notes) > 1000) {
        throw new InvalidArgumentException('الملاحظات يجب أن تكون أقل من 1000 حرف');
    }

    // التحقق من عدم تكرار رقم أمر العمل مع نفس النوع
    error_log("Checking for duplicate work order number: " . $workOrderNumber . " with type: " . $workOrderTypeId);
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM work_orders WHERE work_order_number = ? AND work_order_type_id = ?");
    $checkStmt->execute([$workOrderNumber, $workOrderTypeId]);
    $duplicateCount = $checkStmt->fetchColumn();
    error_log("Duplicate check result: " . $duplicateCount);
    if ($duplicateCount > 0) {
        throw new InvalidArgumentException('رقم أمر العمل موجود مسبقاً مع نفس النوع، يرجى استخدام رقم آخر أو نوع مختلف');
    }

    // التحقق من وجود الفرع ونوع أمر العمل
    error_log("Checking branch ID: " . $branchId);
    $branchStmt = $db->prepare("SELECT COUNT(*) FROM branches WHERE id = ? AND status = 'active'");
    $branchStmt->execute([$branchId]);
    $branchCount = $branchStmt->fetchColumn();
    error_log("Branch check result: " . $branchCount);
    if ($branchCount == 0) {
        throw new InvalidArgumentException('الفرع المحدد غير موجود أو غير نشط');
    }

    error_log("Checking work order type ID: " . $workOrderTypeId);
    $typeStmt = $db->prepare("SELECT COUNT(*) FROM work_order_types WHERE id = ? AND status = 'active'");
    $typeStmt->execute([$workOrderTypeId]);
    $typeCount = $typeStmt->fetchColumn();
    error_log("Work order type check result: " . $typeCount);
    if ($typeCount == 0) {
        throw new InvalidArgumentException('نوع أمر العمل المحدد غير موجود أو غير نشط');
    }

    // إدراج أمر العمل الجديد
    error_log("Preparing to insert work order with data: " . json_encode([
        'work_order_number' => $workOrderNumber,
        'work_order_type_id' => $workOrderTypeId,
        'department' => $department,
        'branch_id' => $branchId,
        'assignment_date' => $assignmentDate,
        'receipt_date' => $receiptDate,
        'estimated_value' => $estimatedValue,
        'actual_value' => $actualValue,
        'disbursement_status' => $disbursementStatus,
        'notes' => $notes,
        'status' => $status
    ]));

    $insertStmt = $db->prepare("
        INSERT INTO work_orders (
            work_order_number, work_order_type_id, department, branch_id, location,
            assignment_date, receipt_date, estimated_value, actual_value,
            disbursement_status, notes, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $result = $insertStmt->execute([
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
        $notes,
        $status
    ]);

    error_log("Insert result: " . ($result ? 'success' : 'failed'));

    if ($result) {
        $workOrderId = $db->lastInsertId();
        error_log("Work order created successfully with ID: " . $workOrderId);

        // إرسال إشعارات الواتساب
        try {
            $eventName = $department === 'connections' ? 'work_order_created_connections' : 'work_order_created_projects';
            $settingsStmt = $db->prepare("SELECT * FROM notification_settings WHERE event_name = ? AND is_active = 1");
            $settingsStmt->execute([$eventName]);
            $notifications = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($notifications) > 0) {
                $whatsappService = new WhatsAppService();
                $workOrderData = [
                    'work_order_number' => $workOrderNumber,
                    'department' => $department,
                    'location' => $location,
                    'estimated_value' => $estimatedValue,
                    'assignment_date' => $assignmentDate,
                    'notes' => $notes
                ];

                foreach ($notifications as $notification) {
                    $recipient = $notification['recipient'];
                    $isGroup = ($notification['notification_type'] === 'whatsapp_group');
                    
                    if (in_array($notification['notification_type'], ['whatsapp_personal', 'whatsapp_group'])) {
                        $waSent = $whatsappService->sendWorkOrderNotification($workOrderData, $recipient, $isGroup);
                        error_log("[WorkOrder Create] WhatsApp result: " . ($waSent ? 'SENT' : 'FAILED') . " to " . $recipient);
                    }
                }
            }
        } catch (Exception $notifEx) {
            error_log("Failed to send WhatsApp notifications for Work Order $workOrderId: " . $notifEx->getMessage());
        }

        // إرسال استجابة النجاح
        echo json_encode([
            'success' => true,
            'message' => 'تم إنشاء أمر العمل بنجاح',
            'data' => [
                'id' => $workOrderId,
                'work_order_number' => $workOrderNumber,
                'department' => $department,
                'estimated_value' => $estimatedValue,
                'status' => $status
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        error_log("Failed to insert work order - PDO error info: " . print_r($insertStmt->errorInfo(), true));
        throw new Exception('فشل في حفظ أمر العمل');
    }

} catch (InvalidArgumentException $e) {
    error_log('Validation error in work order creation: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'validation'
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Database error in work order creation: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى',
        'error_type' => 'database'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // تسجيل الخطأ
    error_log('خطأ في إنشاء أمر العمل: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى',
        'error_type' => 'general'
    ], JSON_UNESCAPED_UNICODE);
}
?>
