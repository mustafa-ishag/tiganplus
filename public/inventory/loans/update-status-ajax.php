<?php
if (ob_get_level()) ob_clean();
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

if (!defined('ETGAN_SYSTEM')) define('ETGAN_SYSTEM', true);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/InventoryLoan.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

$loanId = (int)($input['loan_id'] ?? 0);
$status = $input['status'] ?? '';

if ($loanId <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'بيانات مفقودة']);
    exit;
}

$loanModel = new InventoryLoan();
$result = $loanModel->updateLoanStatus($loanId, $status);

if ($result['success']) {
    try {
        logActivity('update_loan_status', "تم تحديث حالة السلفة رقم {$loanId} إلى {$status}");
    } catch (Exception $e) {}

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
