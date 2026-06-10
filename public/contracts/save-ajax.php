<?php
/**
 * معالج حفظ/تعديل العقد عبر AJAX
 * Contract Save AJAX Handler
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    $contractId = !empty($_POST['contract_id']) ? (int) $_POST['contract_id'] : null;
    $contractNumber = trim($_POST['contract_number'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // التحقق من صحة البيانات
    if (empty($contractNumber) || empty($startDate) || empty($endDate)) {
        throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
    }
    
    if (!preg_match('/^\d{10}$/', $contractNumber)) {
        throw new Exception('رقم العقد يجب أن يكون مكون من 10 أرقام فقط');
    }
    
    if ($endDate <= $startDate) {
        throw new Exception('تاريخ النهاية يجب أن يكون بعد تاريخ البداية');
    }
    
    // التحقق من عدم تكرار رقم العقد
    $checkQuery = "SELECT id FROM contracts WHERE contract_number = ?";
    $checkParams = [$contractNumber];
    if ($contractId) {
        $checkQuery .= " AND id != ?";
        $checkParams[] = $contractId;
    }
    $stmt = $db->prepare($checkQuery);
    $stmt->execute($checkParams);
    if ($stmt->fetch()) {
        throw new Exception('رقم العقد مستخدم مسبقاً');
    }
    
    // التحقق من عدم تداخل فترات العقود
    $overlapQuery = "SELECT id, contract_number FROM contracts WHERE 
        ((start_date <= ? AND end_date >= ?) OR 
         (start_date <= ? AND end_date >= ?) OR 
         (start_date >= ? AND end_date <= ?))";
    $overlapParams = [$endDate, $startDate, $startDate, $startDate, $startDate, $endDate];
    if ($contractId) {
        $overlapQuery .= " AND id != ?";
        $overlapParams[] = $contractId;
    }
    $stmt = $db->prepare($overlapQuery);
    $stmt->execute($overlapParams);
    $overlap = $stmt->fetch();
    if ($overlap) {
        throw new Exception('فترة العقد تتداخل مع العقد رقم: ' . $overlap['contract_number']);
    }
    
    $db->beginTransaction();
    
    if ($contractId) {
        // تعديل عقد موجود
        $stmt = $db->prepare("
            UPDATE contracts SET 
                contract_number = ?,
                start_date = ?,
                end_date = ?,
                description = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$contractNumber, $startDate, $endDate, $description ?: null, $contractId]);
        
        // إعادة ربط أوامر العمل: أولاً إلغاء ربط أوامر العمل القديمة لهذا العقد
        $db->prepare("UPDATE work_orders SET contract_id = NULL WHERE contract_id = ?")->execute([$contractId]);
        
        // ثم ربط أوامر العمل التي يقع assignment_date ضمن الفترة الجديدة
        $stmt = $db->prepare("
            UPDATE work_orders SET contract_id = ? 
            WHERE assignment_date BETWEEN ? AND ? 
            AND (contract_id IS NULL OR contract_id = ?)
        ");
        $stmt->execute([$contractId, $startDate, $endDate, $contractId]);
        
        $message = 'تم تعديل العقد بنجاح';
    } else {
        // إضافة عقد جديد
        $stmt = $db->prepare("
            INSERT INTO contracts (contract_number, start_date, end_date, description, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$contractNumber, $startDate, $endDate, $description ?: null, $user_id]);
        $newContractId = $db->lastInsertId();
        
        // ربط أوامر العمل التي يقع assignment_date ضمن فترة هذا العقد
        $stmt = $db->prepare("
            UPDATE work_orders SET contract_id = ? 
            WHERE assignment_date BETWEEN ? AND ? 
            AND contract_id IS NULL
        ");
        $stmt->execute([$newContractId, $startDate, $endDate]);
        $linkedCount = $stmt->rowCount();
        
        $message = "تم إضافة العقد بنجاح. تم ربط {$linkedCount} أمر عمل تلقائياً.";
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
