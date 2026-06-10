<?php
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = getDB();

    // قراءة البيانات من POST (JSON أو form data)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // إذا كانت البيانات JSON، استخدمها، وإلا استخدم $_POST
    if ($data) {
        $attachmentId = isset($data['attachment_id']) ? (int) $data['attachment_id'] : null;
        $formType = trim($data['form_type'] ?? '');
        $workOrderId = (int) ($data['work_order_id'] ?? 0);
        $status = trim($data['status'] ?? '');
        $completionConfirmation = trim($data['completion_confirmation'] ?? '');
        $notes = isset($data['notes']) ? trim($data['notes']) : null;
    } else {
        $attachmentId = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : null;
        $formType = trim($_POST['form_type'] ?? '');
        $workOrderId = (int) ($_POST['work_order_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $completionConfirmation = trim($_POST['completion_confirmation'] ?? '');
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    }
    
    // إذا كان التحديث للملاحظات فقط (لا يحتاج form_type و work_order_id)
    if ($attachmentId && $notes !== null && empty($formType) && empty($workOrderId)) {
        // تحديث الملاحظات فقط
        $updateSql = "UPDATE work_order_attachments SET notes = ?, updated_at = NOW() WHERE id = ?";
        $updateStmt = $db->prepare($updateSql);
        $result = $updateStmt->execute([$notes, $attachmentId]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'تم تحديث الملاحظات بنجاح'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception('فشل في تحديث الملاحظات');
        }
        exit;
    }

    // التحقق من البيانات الأساسية
    if (empty($formType)) {
        throw new InvalidArgumentException('نوع النموذج مطلوب');
    }

    if ($workOrderId <= 0) {
        throw new InvalidArgumentException('معرف أمر العمل غير صحيح');
    }

    // التحقق من نوع النموذج
    $validFormTypes = ['precise_drilling_form', 'excavation_form', 'demolition_form', 'f1_form', 'assets_receipt_form', 'completion_certificate', 'other_document'];
    if (!in_array($formType, $validFormTypes)) {
        throw new InvalidArgumentException('نوع النموذج غير صحيح');
    }

    // التحقق من وجود أمر العمل
    $workOrderStmt = $db->prepare("SELECT id FROM work_orders WHERE id = ?");
    $workOrderStmt->execute([$workOrderId]);
    if (!$workOrderStmt->fetch()) {
        throw new InvalidArgumentException('أمر العمل غير موجود');
    }
    
    $updateFields = [];
    $updateValues = [];
    
    // تحديث الحالة
    if (!empty($status)) {
        if (!in_array($status, ['attached', 'not_attached', 'not_applicable'])) {
            throw new InvalidArgumentException('حالة المرفق غير صحيحة');
        }
        $updateFields[] = 'status = ?';
        $updateValues[] = $status;
    }
    
    // تحديث تأكيد شهادة الإنجاز
    if (!empty($completionConfirmation)) {
        if (!in_array($completionConfirmation, ['empty', 'accepted', 'rejected', 'confirmed'])) {
            throw new InvalidArgumentException('تأكيد شهادة الإنجاز غير صحيح');
        }
        $updateFields[] = 'completion_certificate_confirmation = ?';
        $updateValues[] = $completionConfirmation;
    }

    // تحديث الملاحظات
    if ($notes !== null) {
        $updateFields[] = 'notes = ?';
        $updateValues[] = $notes;
    }

    if (empty($updateFields)) {
        throw new InvalidArgumentException('لا توجد بيانات للتحديث');
    }
    
    // إضافة تاريخ التحديث
    $updateFields[] = 'updated_at = NOW()';
    
    if ($attachmentId && $attachmentId > 0) {
        // تحديث السجل الموجود
        $checkStmt = $db->prepare("SELECT id FROM work_order_attachments WHERE id = ? AND work_order_id = ? AND form_type = ?");
        $checkStmt->execute([$attachmentId, $workOrderId, $formType]);
        
        if (!$checkStmt->fetch()) {
            throw new InvalidArgumentException('المرفق غير موجود');
        }
        
        $updateValues[] = $attachmentId;
        $updateSql = "UPDATE work_order_attachments SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $updateStmt = $db->prepare($updateSql);
        $result = $updateStmt->execute($updateValues);
        
    } else {
        // إنشاء سجل جديد
        if (!empty($status)) {
            $insertSql = "INSERT INTO work_order_attachments (work_order_id, form_type, status, created_at) VALUES (?, ?, ?, NOW())";
            $insertStmt = $db->prepare($insertSql);
            $result = $insertStmt->execute([$workOrderId, $formType, $status]);
        } elseif (!empty($completionConfirmation)) {
            $insertSql = "INSERT INTO work_order_attachments (work_order_id, form_type, completion_certificate_confirmation, created_at) VALUES (?, ?, ?, NOW())";
            $insertStmt = $db->prepare($insertSql);
            $result = $insertStmt->execute([$workOrderId, $formType, $completionConfirmation]);
        } else {
            throw new InvalidArgumentException('لا توجد بيانات للإدراج');
        }
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'تم التحديث بنجاح'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('فشل في تحديث المرفق');
    }

} catch (InvalidArgumentException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('خطأ في تحديث المرفق: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ غير متوقع'
    ], JSON_UNESCAPED_UNICODE);
}
?>
