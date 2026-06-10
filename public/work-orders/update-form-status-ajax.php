<?php
/**
 * تحديث حالة النموذج المرفق بأمر العمل
 * Update Work Order Form Status via AJAX
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح بالوصول']);
    exit();
}

// التحقق من الصلاحيات
if (!hasPermission('work_orders_update_fields')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتحديث أوامر العمل']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير مسموحة']);
    exit();
}

// الحصول على البيانات
$workOrderId = isset($_POST['work_order_id']) ? intval($_POST['work_order_id']) : 0;
$formType = isset($_POST['form_type']) ? trim($_POST['form_type']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// التحقق من صحة البيانات
if (!$workOrderId) {
    echo json_encode(['success' => false, 'message' => 'رقم أمر العمل مطلوب']);
    exit();
}

if (empty($formType)) {
    echo json_encode(['success' => false, 'message' => 'نوع النموذج مطلوب']);
    exit();
}

if (empty($status)) {
    echo json_encode(['success' => false, 'message' => 'حالة النموذج مطلوبة']);
    exit();
}

try {
    $db = getDB();

    // التحقق من وجود أمر العمل
    $stmt = $db->prepare("SELECT id FROM work_orders WHERE id = ?");
    $stmt->execute([$workOrderId]);

    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'أمر العمل غير موجود']);
        exit();
    }

    // تحديد أنواع النماذج المسموحة
    $allowedFormTypes = [
        'precise_drilling_form',
        'excavation_form',
        'demolition_form',
        'f1_form',
        'assets_receipt_form'
    ];

    if (!in_array($formType, $allowedFormTypes)) {
        echo json_encode(['success' => false, 'message' => 'نوع النموذج غير مسموح']);
        exit();
    }

    // التحقق من صحة الحالة
    $validStatuses = ['not_attached', 'attached', 'not_applicable'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'حالة النموذج غير صحيحة']);
        exit();
    }

    // التحقق من وجود سجل النموذج
    $stmt = $db->prepare("SELECT id FROM work_order_attachments WHERE work_order_id = ? AND form_type = ?");
    $stmt->execute([$workOrderId, $formType]);
    $attachment = $stmt->fetch();

    if ($attachment) {
        // تحديث السجل الموجود
        $sql = "UPDATE work_order_attachments SET status = ?, updated_at = NOW() WHERE work_order_id = ? AND form_type = ?";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$status, $workOrderId, $formType]);
    } else {
        // إنشاء سجل جديد
        $sql = "INSERT INTO work_order_attachments (work_order_id, form_type, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$workOrderId, $formType, $status]);
    }

    if ($result) {
        // تحديث تاريخ آخر تعديل لأمر العمل
        $updateStmt = $db->prepare("UPDATE work_orders SET updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$workOrderId]);

        // تحضير أسماء النماذج للرسائل
        $formNames = [
            'precise_drilling_form' => 'الحفر الدقيق',
            'excavation_form' => 'الكشط',
            'demolition_form' => 'التخريد',
            'f1_form' => 'F1',
            'assets_receipt_form' => 'استلام الأصول (211)'
        ];

        $formName = $formNames[$formType] ?? 'النموذج';

        echo json_encode([
            'success' => true,
            'message' => "تم تحديث حالة نموذج {$formName} بنجاح"
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة النموذج']);
    }

} catch (PDOException $e) {
    error_log('Database Error in update-form-status-ajax.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في قاعدة البيانات'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Error in update-form-status-ajax.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء معالجة الطلب'
    ], JSON_UNESCAPED_UNICODE);
}
?>

