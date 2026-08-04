<?php
/**
 * معالج حفظ/حذف بنود العقد - AJAX
 */
session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صالحة']);
    exit();
}

$db = getDB();
$action = $_POST['action'] ?? 'save';

try {
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // التحقق من عدم استخدام البند في شهادات إنجاز
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM completion_certificate_works WHERE contract_work_item_id = ?");
        $checkStmt->execute([$id]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'لا يمكن حذف هذا البند لأنه مستخدم في شهادات إنجاز. يمكنك إيقاف تفعيله بدلاً من ذلك.']);
            exit();
        }

        $stmt = $db->prepare("DELETE FROM contract_work_items WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'تم الحذف بنجاح']);
        exit();
    } 
    
    // حفظ أو تعديل
    $item_id = !empty($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $contract_id = (int)$_POST['contract_id'];
    $item_number = trim($_POST['item_number']);
    $description = trim($_POST['description']);
    $unit = trim($_POST['unit']);
    $price = (float)$_POST['price'];
    $category = trim($_POST['category']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // التحقق من عدم تكرار رقم البند في نفس العقد
    $checkStmt = $db->prepare("SELECT id FROM contract_work_items WHERE contract_id = ? AND item_number = ? AND id != ?");
    $checkStmt->execute([$contract_id, $item_number, $item_id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'رقم البند هذا موجود مسبقاً في هذا العقد']);
        exit();
    }

    if ($item_id > 0) {
        // تعديل
        $stmt = $db->prepare("
            UPDATE contract_work_items 
            SET item_number = ?, description = ?, unit = ?, price = ?, category = ?, is_active = ?
            WHERE id = ? AND contract_id = ?
        ");
        $stmt->execute([$item_number, $description, $unit, $price, $category, $is_active, $item_id, $contract_id]);
    } else {
        // إضافة
        $stmt = $db->prepare("
            INSERT INTO contract_work_items (contract_id, item_number, description, unit, price, category, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$contract_id, $item_number, $description, $unit, $price, $category, $is_active]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage()]);
}
