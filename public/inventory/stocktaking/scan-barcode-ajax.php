<?php
// AJAX: البحث عن مادة بالباركود (item_number)
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'غير مسجل']); exit; }

$sessionId = (int)($_GET['session_id'] ?? 0);
$barcode = trim($_GET['barcode'] ?? '');

if ($sessionId <= 0 || empty($barcode)) {
    echo json_encode(['success'=>false,'message'=>'بيانات غير صحيحة']); exit;
}

$model = new StocktakingSession();
$item = $model->findItemByBarcode($sessionId, $barcode);

if ($item) {
    echo json_encode([
        'success' => true,
        'material_id' => $item['material_id'],
        'item_number' => $item['item_number'],
        'description' => $item['description'],
        'unit' => $item['unit'],
        'system_quantity' => $item['system_quantity'],
        'counted_quantity' => $item['counted_quantity'],
        'status' => $item['status']
    ]);
} else {
    echo json_encode(['success'=>false,'message'=>'المادة غير موجودة في هذه الجلسة']);
}
