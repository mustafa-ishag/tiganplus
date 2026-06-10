<?php
/**
 * API Server-Side Processing لجدول المعاملات (DataTables)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

// معاملات DataTables
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 25);
$searchValue = $_GET['search']['value'] ?? '';
$orderColumn = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = ($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

// فلاتر إضافية
$transactionType = $_GET['transaction_type'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// بناء شروط البحث
$whereConditions = ['1=1'];
$params = [];

if (!empty($searchValue)) {
    $whereConditions[] = '(it.transaction_number LIKE ? OR it.notes LIKE ? OR u.full_name LIKE ? OR wo.work_order_number LIKE ?)';
    $p = "%{$searchValue}%";
    $params = array_merge($params, [$p, $p, $p, $p]);
}

if (!empty($transactionType)) {
    $whereConditions[] = 'it.transaction_type = ?';
    $params[] = $transactionType;
}

if (!empty($status)) {
    $whereConditions[] = 'it.status = ?';
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'it.transaction_date >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'it.transaction_date <= ?';
    $params[] = $dateTo;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// عدد السجلات الإجمالي (بدون فلاتر)
$totalStmt = $db->query("SELECT COUNT(*) FROM inventory_transactions");
$recordsTotal = (int)$totalStmt->fetchColumn();

// عدد السجلات بعد الفلترة
$filteredStmt = $db->prepare("SELECT COUNT(DISTINCT it.id) FROM inventory_transactions it LEFT JOIN users u ON it.created_by = u.id LEFT JOIN work_orders wo ON it.work_order_id = wo.id {$whereClause}");
$filteredStmt->execute($params);
$recordsFiltered = (int)$filteredStmt->fetchColumn();

// ترتيب الأعمدة
$columns = ['it.transaction_number', 'it.transaction_type', 'wo.work_order_number', 'it.transaction_date', 'item_count', 'it.status', 'u.full_name', 'it.id'];
$orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'it.created_at';
if ($orderBy === 'item_count') {
    $orderBy = 'COUNT(td.id)';
}

// جلب البيانات
$sql = "SELECT it.id, it.transaction_number, it.transaction_type, it.transaction_date, 
               it.status, it.notes, it.reference_number,
               COUNT(td.id) as item_count,
               u.full_name as created_by_name,
               wo.work_order_number,
               wot.type_code as work_order_type_code
        FROM inventory_transactions it
        LEFT JOIN transaction_details td ON it.id = td.transaction_id
        LEFT JOIN users u ON it.created_by = u.id
        LEFT JOIN work_orders wo ON it.work_order_id = wo.id
        LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
        {$whereClause}
        GROUP BY it.id
        ORDER BY {$orderBy} {$orderDir}, it.created_at DESC
        LIMIT {$length} OFFSET {$start}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تسميات الأنواع
$typeLabels = [
    'incoming' => ['وارد', 'success', 'arrow-down'],
    'outgoing' => ['صادر', 'danger', 'arrow-up'],
    'transfer' => ['تحويل', 'info', 'exchange-alt'],
    'return' => ['مرتجع', 'warning', 'undo'],
    'initial_balance' => ['رصيد افتتاحي', 'primary', 'balance-scale'],
    'loan_out' => ['سلفة صادرة', 'dark', 'hand-holding'],
    'loan_in' => ['سلفة واردة', 'secondary', 'hands-helping'],
    'loan_return' => ['إرجاع سلفة', 'info', 'handshake'],
    'stocktake_adjustment' => ['تسوية جرد', 'dark', 'clipboard-check']
];

$statusLabels = [
    'pending' => ['معلق', 'warning'],
    'approved' => ['معتمد', 'success'],
    'rejected' => ['مرفوض', 'danger']
];

// تحويل البيانات لصيغة DataTables
$data = [];
foreach ($transactions as $tx) {
    $typeInfo = $typeLabels[$tx['transaction_type']] ?? ['غير معروف', 'secondary', 'question'];
    $statusInfo = $statusLabels[$tx['status']] ?? ['غير معروف', 'secondary'];

    $woHtml = '—';
    if (!empty($tx['work_order_number'])) {
        $woTypeCode = !empty($tx['work_order_type_code']) 
            ? '<span class="badge bg-info bg-opacity-10 text-info me-1">' . htmlspecialchars($tx['work_order_type_code']) . '</span>' 
            : '';
        $woHtml = $woTypeCode . htmlspecialchars($tx['work_order_number']);
    }

    // أزرار الإجراءات
    $actions = '<div class="btn-group" role="group">';
    $actions .= '<a href="view.php?id=' . $tx['id'] . '" class="btn btn-sm btn-outline-primary" title="عرض"><i class="fas fa-eye"></i></a>';
    if ($tx['status'] === 'pending') {
        if (hasPermission('inventory_transactions_edit')) {
            $actions .= '<a href="edit.php?id=' . $tx['id'] . '" class="btn btn-sm btn-outline-warning" title="تعديل"><i class="fas fa-edit"></i></a>';
        }
        if (hasPermission('inventory_transactions_approve')) {
            $actions .= '<button type="button" class="btn btn-sm btn-outline-success" onclick="approveTransaction(' . $tx['id'] . ')" title="اعتماد"><i class="fas fa-check"></i></button>';
            $actions .= '<button type="button" class="btn btn-sm btn-outline-danger" onclick="rejectTransaction(' . $tx['id'] . ')" title="رفض"><i class="fas fa-times"></i></button>';
        }
    }
    $actions .= '</div>';

    $data[] = [
        '<a href="view.php?id=' . $tx['id'] . '" class="text-decoration-none font-monospace">' . htmlspecialchars($tx['transaction_number']) . '</a>',
        '<span class="badge bg-' . $typeInfo[1] . '"><i class="fas fa-' . $typeInfo[2] . ' me-1"></i>' . $typeInfo[0] . '</span>',
        $woHtml,
        formatDate($tx['transaction_date']),
        '<span class="badge bg-secondary">' . number_format($tx['item_count']) . '</span>',
        '<span class="badge bg-' . $statusInfo[1] . '">' . $statusInfo[0] . '</span>',
        '<small>' . htmlspecialchars($tx['created_by_name'] ?? 'غير معروف') . '</small>',
        $actions
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
], JSON_UNESCAPED_UNICODE);
