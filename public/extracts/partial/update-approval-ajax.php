<?php
/**
 * تحديث مرحلة الاعتماد للمستخلصات الجزئية عبر AJAX
 */

session_start();

// تعيين Content-Type للاستجابة
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح بالوصول']);
    exit();
}

// التحقق من الصلاحيات
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
if (!hasPermission('extracts_approve')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لاعتماد المستخلصات']);
    exit();
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit();
}

// قراءة البيانات من JSON
$input = json_decode(file_get_contents('php://input'), true);

// تسجيل البيانات المستلمة للتشخيص
error_log("Received data: " . print_r($input, true));

// التحقق من البيانات المطلوبة
if (!isset($input['extract_id'])) {
    error_log("Missing extract_id in input");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المستخلص مطلوب']);
    exit();
}

$extract_id = (int) $input['extract_id'];
$new_stage = $input['approval_stage'] ?? null;
$disbursement_date = $input['disbursement_date'] ?? null;
$approval_notes = $input['approval_notes'] ?? null;

// تحويل القيم الفارغة إلى 'draft' (للمسودة)
if ($new_stage === '' || $new_stage === 'null' || $new_stage === null) {
    $new_stage = 'draft';
}

// تنظيف تاريخ الصرف
if ($disbursement_date === '' || $disbursement_date === 'null') {
    $disbursement_date = null;
}

$user_id = $_SESSION['user_id'];

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
    $db = getDB();

    // التحقق من وجود المستخلص
    $stmt = $db->prepare("SELECT * FROM partial_extracts WHERE id = ?");
    $stmt->execute([$extract_id]);
    $extract = $stmt->fetch();

    if (!$extract) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المستخلص غير موجود']);
        exit();
    }

    // بدء المعاملة
    $db->beginTransaction();
    error_log("Starting transaction for extract_id: $extract_id, new_stage: $new_stage, disbursement_date: $disbursement_date");

    // تحديث مرحلة الاعتماد وتاريخ الصرف والملاحظات
    $updateQuery = "UPDATE partial_extracts SET
                    approval_stage = ?,
                    disbursement_date = ?,
                    approval_notes = ?,
                    approved_by = ?,
                    approval_date = NOW(),
                    updated_at = NOW()
                    WHERE id = ?";
    $stmt = $db->prepare($updateQuery);
    $result = $stmt->execute([$new_stage, $disbursement_date, $approval_notes, $user_id, $extract_id]);
    error_log("Update query result: " . ($result ? 'SUCCESS' : 'FAILED') . ", affected rows: " . $stmt->rowCount());

    // إذا تم تحديد تاريخ التقديم وكانت المرحلة ليست مسودة
    if ($new_stage !== 'draft' && $new_stage !== null && $extract['submission_date'] === null) {
        $submissionUpdateQuery = "UPDATE partial_extracts SET submission_date = NOW() WHERE id = ?";
        $stmt = $db->prepare($submissionUpdateQuery);
        $stmt->execute([$extract_id]);
    }

    // إذا تم إلغاء المستخلص (إعادته لمسودة)، إزالة تاريخ التقديم
    if ($new_stage === 'draft' || $new_stage === null) {
        $submissionUpdateQuery = "UPDATE partial_extracts SET submission_date = NULL WHERE id = ?";
        $stmt = $db->prepare($submissionUpdateQuery);
        $stmt->execute([$extract_id]);
    }

    // تأكيد المعاملة
    $db->commit();

    // جلب اسم المرحلة الجديدة من قاعدة البيانات
    $stageQuery = "SELECT stage_name FROM approval_stages WHERE stage_key = ?";
    $stageStmt = $db->prepare($stageQuery);
    $stageStmt->execute([$new_stage]);
    $stageData = $stageStmt->fetch();
    $stageName = $stageData ? $stageData['stage_name'] : "غير محدد";

    // جلب اسم المستخدم
    $userQuery = "SELECT full_name FROM users WHERE id = ?";
    $userStmt = $db->prepare($userQuery);
    $userStmt->execute([$user_id]);
    $userData = $userStmt->fetch();
    $userName = $userData ? $userData['full_name'] : "غير محدد";

    $response = [
        'success' => true,
        'message' => "تم تحديث بيانات الاعتماد بنجاح",
        'new_stage' => $new_stage,
        'stage_name' => $stageName,
        'disbursement_date' => $disbursement_date,
        'approval_notes' => $approval_notes,
        'approved_by_name' => $userName,
        'approval_date' => date('Y-m-d H:i:s')
    ];

    error_log("Sending success response: " . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    // إلغاء المعاملة في حالة الخطأ
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Error updating approval stage: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء تحديث مرحلة الاعتماد: ' . $e->getMessage()
    ]);
}
?>
