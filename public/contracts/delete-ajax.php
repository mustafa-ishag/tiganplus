<?php
/**
 * معالج حذف العقد عبر AJAX
 * Contract Delete AJAX Handler
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
    
    $contractId = (int) ($_POST['contract_id'] ?? 0);
    
    if (!$contractId) {
        throw new Exception('معرف العقد مطلوب');
    }
    
    // التحقق من وجود العقد
    $stmt = $db->prepare("SELECT * FROM contracts WHERE id = ?");
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();
    
    if (!$contract) {
        throw new Exception('العقد غير موجود');
    }
    
    $db->beginTransaction();
    
    // إلغاء ربط أوامر العمل المرتبطة
    $stmt = $db->prepare("UPDATE work_orders SET contract_id = NULL WHERE contract_id = ?");
    $stmt->execute([$contractId]);
    $unlinkedCount = $stmt->rowCount();
    
    // حذف العقد
    $stmt = $db->prepare("DELETE FROM contracts WHERE id = ?");
    $stmt->execute([$contractId]);
    
    $db->commit();
    
    $message = "تم حذف العقد رقم {$contract['contract_number']} بنجاح.";
    if ($unlinkedCount > 0) {
        $message .= " تم إلغاء ربط {$unlinkedCount} أمر عمل.";
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
