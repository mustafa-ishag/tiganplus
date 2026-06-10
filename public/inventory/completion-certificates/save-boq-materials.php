<?php
/**
 * API لحفظ مواد المقايسة المستخرجة من OCR في شهادة الإنجاز
 * Save OCR-extracted BOQ materials to Completion Certificate
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once __DIR__ . '/../../../config/config.php';
    require_once __DIR__ . '/../../../includes/functions.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في تحميل الملفات'], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مصرح بالوصول'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة غير مدعومة'], JSON_UNESCAPED_UNICODE);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['work_order_id']) || empty($input['materials'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات ناقصة: يجب تحديد أمر العمل والمواد'], JSON_UNESCAPED_UNICODE);
    exit();
}

$workOrderId = (int) $input['work_order_id'];
$certificateId = $input['certificate_id'] ?? 'new';
$materials = $input['materials'];
$userId = $_SESSION['user_id'];

try {
    $db = getDB();
    $db->beginTransaction();

    // === 1) التحقق من أمر العمل ===
    $woStmt = $db->prepare("SELECT id, work_order_number FROM work_orders WHERE id = ?");
    $woStmt->execute([$workOrderId]);
    $workOrder = $woStmt->fetch();

    if (!$workOrder) {
        throw new Exception('أمر العمل غير موجود');
    }

    // === 2) إنشاء أو استخدام شهادة إنجاز ===
    if ($certificateId === 'new' || empty($certificateId)) {
        // إنشاء شهادة جديدة
        $certStmt = $db->prepare("
            INSERT INTO completion_certificates 
            (work_order_id, certificate_date, title, description, status, created_by, created_at, updated_at) 
            VALUES (?, CURDATE(), ?, ?, 'draft', ?, NOW(), NOW())
        ");
        $certTitle = 'مقايسة OCR - ' . $workOrder['work_order_number'];
        $certDesc = 'تم استيراد المواد من صور المقايسة باستخدام OCR - ' . date('Y-m-d H:i');
        $certStmt->execute([$workOrderId, $certTitle, $certDesc, $userId]);
        $certificateId = $db->lastInsertId();
    } else {
        // التحقق من الشهادة الموجودة
        $certId = (int) $certificateId;
        $checkStmt = $db->prepare("SELECT id FROM completion_certificates WHERE id = ? AND work_order_id = ?");
        $checkStmt->execute([$certId, $workOrderId]);
        if (!$checkStmt->fetch()) {
            throw new Exception('شهادة الإنجاز غير موجودة أو لا تنتمي لأمر العمل المحدد');
        }
        $certificateId = $certId;
    }

    // === 3) حفظ المواد ===
    $insertStmt = $db->prepare("
        INSERT INTO completion_certificate_materials 
        (certificate_id, material_id, material_code, material_description, material_group, unit, 
         estimated_quantity, actual_quantity, dispensed_quantity, returned_quantity, notes, created_at) 
        VALUES (?, ?, ?, ?, '', ?, ?, 0, 0, 0, ?, NOW())
    ");

    $savedCount = 0;
    $totalValue = 0;

    foreach ($materials as $mat) {
        $itemNumber = trim($mat['item_number'] ?? '');
        $description = trim($mat['description'] ?? '');
        $unit = trim($mat['unit'] ?? '');
        $estimatedQty = (float) ($mat['estimated_quantity'] ?? 0);

        // تخطي المواد بدون وصف
        if (empty($description) && empty($itemNumber))
            continue;

        // البحث عن المادة في قاعدة البيانات حسب رقم البند
        $materialId = 0;
        if (!empty($itemNumber)) {
            $matSearch = $db->prepare("SELECT id FROM materials WHERE item_number = ? LIMIT 1");
            $matSearch->execute([$itemNumber]);
            $found = $matSearch->fetch();
            if ($found) {
                $materialId = $found['id'];
            }
        }

        $notes = 'تم الاستيراد من OCR';

        $insertStmt->execute([
            $certificateId,
            $materialId,
            $itemNumber,
            $description,
            $unit,
            $estimatedQty,
            $notes,
        ]);

        $savedCount++;
        $totalValue += $totalVal;
    }

    $updateCert = $db->prepare("
        UPDATE completion_certificates 
        SET updated_at = NOW(),
            updated_by = ?
        WHERE id = ?
    ");
    $updateCert->execute([$userId, $certificateId]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'saved_count' => $savedCount,
        'certificate_id' => $certificateId,
        'message' => 'تم حفظ ' . $savedCount . ' مادة بنجاح',
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Save BOQ Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
?>