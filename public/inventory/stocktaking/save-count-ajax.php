<?php
// AJAX: حفظ عد مادة
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'غير مسجل']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($input['session_id'] ?? 0);
$materialId = (int)($input['material_id'] ?? 0);
$qty = (float)($input['counted_quantity'] ?? 0);
$method = $input['input_method'] ?? 'manual';

if ($sessionId <= 0 || $materialId <= 0) {
    echo json_encode(['success'=>false,'message'=>'بيانات غير صحيحة']); exit;
}

$model = new StocktakingSession();
$result = $model->saveCount($sessionId, $materialId, $qty, $method, $_SESSION['user_id']);
echo json_encode($result);
