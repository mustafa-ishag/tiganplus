<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('ETGAN_SYSTEM')) {
    define('ETGAN_SYSTEM', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../models/Material.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// التحقق من الصلاحية
if (!hasPermission('inventory_materials_edit')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل المواد']);
    exit;
}

try {
    $materialId = (int) ($_POST['material_id'] ?? 0);
    $newStatus  = (int) ($_POST['is_active'] ?? 0); // 1 = تفعيل، 0 = إلغاء تفعيل

    if ($materialId <= 0) {
        echo json_encode(['success' => false, 'message' => 'معرف المادة غير صحيح']);
        exit;
    }

    $materialModel = new Material();
    $material = $materialModel->findById($materialId);

    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'المادة غير موجودة']);
        exit;
    }

    $result = $materialModel->update($materialId, [
        'is_active'  => $newStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    if ($result) {
        $statusText = $newStatus ? 'تفعيل' : 'إلغاء تفعيل';
        try {
            logActivity($_SESSION['user_id'], 'toggle_material_status',
                "{$statusText} المادة: {$material['description']} (ID: {$materialId})");
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'message' => $newStatus ? 'تم تفعيل المادة بنجاح' : 'تم إلغاء تفعيل المادة بنجاح',
            'is_active' => $newStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل في تحديث حالة المادة']);
    }

} catch (Exception $e) {
    error_log('[toggle-status-ajax] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
