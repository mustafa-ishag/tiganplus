<?php
// بدء الجلسة إذا لم تكن نشطة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * تحديث حالة المعاملة عبر AJAX
 * Update Transaction Status via AJAX
 */

// تعيين header للاستجابة JSON
header('Content-Type: application/json; charset=utf-8');

// تسجيل جميع الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 0); // إخفاء الأخطاء في الإخراج

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة - يجب استخدام POST'
    ]);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'غير مسجل دخول'
    ]);
    exit;
}

// التحقق من الصلاحيات
if (function_exists('hasPermission') && !hasPermission('inventory_transactions_approve')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ليس لديك صلاحية لاعتماد المعاملات'
    ]);
    exit;
}

// قراءة البيانات
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'بيانات JSON غير صحيحة',
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

$transactionId = (int) ($input['transaction_id'] ?? 0);
$status = $input['status'] ?? '';
$reason = $input['reason'] ?? '';

// التحقق من صحة البيانات
if ($transactionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'معرف المعاملة غير صحيح'
    ]);
    exit;
}

if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode([
        'success' => false,
        'message' => 'حالة المعاملة غير صحيحة'
    ]);
    exit;
}

try {
    // الاتصال بقاعدة البيانات
    $db = getDB();

    // التحقق من وجود المعاملة
    $stmt = $db->prepare("SELECT * FROM inventory_transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode([
            'success' => false,
            'message' => 'المعاملة غير موجودة'
        ]);
        exit;
    }

    // التحقق من حالة المعاملة
    if ($transaction['status'] !== 'pending') {
        echo json_encode([
            'success' => false,
            'message' => 'المعاملة ليست في حالة انتظار',
            'current_status' => $transaction['status']
        ]);
        exit;
    }

    // تحديث حالة المعاملة
    $userId = $_SESSION['user_id'];
    $now = date('Y-m-d H:i:s');

    if ($status === 'approved') {
        $sql = "UPDATE inventory_transactions SET
                status = 'approved',
                approved_at = ?,
                approved_by = ?
                WHERE id = ?";
        $params = [$now, $userId, $transactionId];
    } else {
        $sql = "UPDATE inventory_transactions SET
                status = 'rejected',
                approved_at = ?,
                approved_by = ?,
                rejection_reason = ?
                WHERE id = ?";
        $params = [$now, $userId, $reason, $transactionId];
    }

    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);

    if ($result && $stmt->rowCount() > 0) {
        $action = $status === 'approved' ? 'اعتماد' : 'رفض';

        // إذا تم اعتماد المعاملة، قم بتحديث المخزون
        if ($status === 'approved') {
            error_log("Starting inventory update for transaction $transactionId");
            $inventoryUpdateResult = updateInventoryFromTransaction($db, $transactionId, $transaction);
            error_log("Inventory update result: " . json_encode($inventoryUpdateResult));

            if (!$inventoryUpdateResult['success']) {
                // إذا فشل تحديث المخزون، تراجع عن اعتماد المعاملة
                error_log("Rolling back transaction approval due to inventory update failure");
                $rollbackStmt = $db->prepare("UPDATE inventory_transactions SET status = 'pending', approved_at = NULL, approved_by = NULL WHERE id = ?");
                $rollbackStmt->execute([$transactionId]);

                echo json_encode([
                    'success' => false,
                    'message' => 'فشل في تحديث المخزون: ' . $inventoryUpdateResult['message']
                ]);
                return;
            } else {
                error_log("Inventory updated successfully for transaction $transactionId");
            }
        }

        // تسجيل النشاط (اختياري)
        if (function_exists('logActivity')) {
            logActivity('update_transaction_status', "تم {$action} المعاملة: {$transaction['transaction_number']}");
        }

        echo json_encode([
            'success' => true,
            'message' => "تم {$action} المعاملة بنجاح" . ($status === 'approved' ? ' وتحديث المخزون' : ''),
            'transaction_id' => $transactionId,
            'new_status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'فشل في تحديث قاعدة البيانات'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in update-status-ajax.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات'
    ]);
} catch (Exception $e) {
    error_log("General error in update-status-ajax.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في النظام'
    ]);
}

/**
 * تحديث المخزون بناءً على المعاملة المعتمدة
 */
function updateInventoryFromTransaction($db, $transactionId, $transaction)
{
    try {
        // الحصول على تفاصيل المعاملة (المواد)
        // التحقق من وجود العمود name في جدول materials
        $materialsColumns = $db->query("DESCRIBE materials")->fetchAll(PDO::FETCH_COLUMN);
        $hasNameColumn = in_array('name', $materialsColumns);
        $materialNameColumn = $hasNameColumn ? 'm.name' : "CONCAT('مادة رقم ', m.id)";

        $stmt = $db->prepare("
            SELECT td.*, $materialNameColumn as material_name
            FROM transaction_details td
            JOIN materials m ON td.material_id = m.id
            WHERE td.transaction_id = ?
        ");
        $stmt->execute([$transactionId]);
        $transactionDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($transactionDetails)) {
            return ['success' => false, 'message' => 'لا توجد تفاصيل للمعاملة'];
        }

        // تحديث المخزون لكل مادة
        foreach ($transactionDetails as $detail) {
            $materialId = $detail['material_id'];
            $quantity = $detail['quantity'];

            // تحديد نوع العملية بناءً على نوع المعاملة
            switch ($transaction['transaction_type']) {
                case 'incoming':
                    // إضافة للمخزون
                    $result = updateMaterialInventory($db, $materialId, $quantity, 'add');
                    break;

                case 'outgoing':
                    // خصم من المخزون
                    $result = updateMaterialInventory($db, $materialId, $quantity, 'subtract');
                    break;

                case 'transfer':
                    // تحويل بين المواقع (يحتاج تطوير إضافي)
                    $result = ['success' => true, 'message' => 'تحويل المواد (قيد التطوير)'];
                    break;

                case 'return':
                    // إرجاع للمخزون
                    $result = updateMaterialInventory($db, $materialId, $quantity, 'add');
                    break;

                default:
                    return ['success' => false, 'message' => 'نوع معاملة غير مدعوم: ' . $transaction['transaction_type']];
            }

            if (!$result['success']) {
                return ['success' => false, 'message' => "فشل في تحديث المخزون للمادة: {$detail['material_name']} - {$result['message']}"];
            }
        }

        return ['success' => true, 'message' => 'تم تحديث المخزون بنجاح'];

    } catch (Exception $e) {
        error_log("Error updating inventory: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطأ في تحديث المخزون: ' . $e->getMessage()];
    }
}

/**
 * تحديث كمية مادة في المخزون (مباشرة في جدول materials)
 */
function updateMaterialInventory($db, $materialId, $quantity, $operation)
{
    try {
        // الحصول على الكمية الحالية من جدول materials
        $stmt = $db->prepare("SELECT current_stock FROM materials WHERE id = ?");
        $stmt->execute([$materialId]);
        $currentStock = $stmt->fetchColumn();

        if ($currentStock === false) {
            return ['success' => false, 'message' => 'المادة غير موجودة'];
        }

        // حساب الكمية الجديدة
        if ($operation === 'add') {
            $newStock = $currentStock + $quantity;
        } else { // subtract
            if ($currentStock < $quantity) {
                return ['success' => false, 'message' => "الكمية المطلوبة ($quantity) أكبر من المتوفر ($currentStock)"];
            }
            $newStock = $currentStock - $quantity;
        }

        // تحديث الكمية في جدول materials
        $updateStmt = $db->prepare("
            UPDATE materials
            SET current_stock = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $result = $updateStmt->execute([$newStock, $materialId]);

        if ($result) {
            error_log("Updated material $materialId stock from $currentStock to $newStock (operation: $operation, quantity: $quantity)");
            return ['success' => true, 'message' => "تم تحديث المخزون من $currentStock إلى $newStock"];
        } else {
            return ['success' => false, 'message' => 'فشل في تحديث قاعدة البيانات'];
        }

    } catch (Exception $e) {
        error_log("Error in updateMaterialInventory: " . $e->getMessage());
        return ['success' => false, 'message' => 'خطأ في تحديث المخزون: ' . $e->getMessage()];
    }
}


?>