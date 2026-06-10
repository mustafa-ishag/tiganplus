<?php
// AJAX: تحديث حالة جلسة الجرد (إكمال / اعتماد / إلغاء)
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/StocktakingSession.php';

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'غير مسجل']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($input['session_id'] ?? 0);
$action = $input['action'] ?? '';

if ($sessionId <= 0 || empty($action)) {
    echo json_encode(['success'=>false,'message'=>'بيانات غير صحيحة']); exit;
}

$model = new StocktakingSession();

switch ($action) {
    case 'complete':
        if (!hasPermission('inventory_stocktaking_count')) {
            echo json_encode(['success'=>false,'message'=>'ليس لديك صلاحية']); exit;
        }
        $result = $model->completeSession($sessionId);
        break;
    case 'approve':
        if (!hasPermission('inventory_stocktaking_approve')) {
            echo json_encode(['success'=>false,'message'=>'ليس لديك صلاحية']); exit;
        }
        $result = $model->approveSession($sessionId, $_SESSION['user_id']);
        break;
    case 'cancel':
        if (!hasPermission('inventory_stocktaking_create')) {
            echo json_encode(['success'=>false,'message'=>'ليس لديك صلاحية']); exit;
        }
        $result = $model->cancelSession($sessionId);
        break;
    default:
        $result = ['success'=>false,'message'=>'إجراء غير معروف'];
}

echo json_encode($result);
