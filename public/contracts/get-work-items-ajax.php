<?php
/**
 * جلب بنود العقد - AJAX
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

$db = getDB();

try {
    $contract_id = 0;
    
    // إذا تم تمرير معرف أمر العمل، نجلب معرف العقد أولاً
    if (!empty($_GET['work_order_id'])) {
        $work_order_id = (int)$_GET['work_order_id'];
        $woStmt = $db->prepare("SELECT contract_id FROM work_orders WHERE id = ?");
        $woStmt->execute([$work_order_id]);
        $contract_id = (int)$woStmt->fetchColumn();
    } elseif (!empty($_GET['contract_id'])) {
        $contract_id = (int)$_GET['contract_id'];
    }

    if ($contract_id <= 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }

    $stmt = $db->prepare("SELECT id, item_number, description, unit, price, category FROM contract_work_items WHERE contract_id = ? AND is_active = 1 ORDER BY item_number");
    $stmt->execute([$contract_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
