<?php
require_once '../../../config/config.php';
require_once '../../../includes/functions.php';

// إخفاء الأخطاء من العرض وتعيين رأس الـ JSON
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $db->query("SELECT * FROM notification_settings ORDER BY id DESC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $data]);
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $eventName = $_POST['event_name'] ?? '';
            $type = $_POST['notification_type'] ?? '';
            $recipient = $_POST['recipient'] ?? '';
            $name = $_POST['name'] ?? '';
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if (empty($eventName) || empty($type) || empty($recipient)) {
                throw new Exception('جميع الحقول الإلزامية مطلوبة');
            }

            if ($id) {
                $stmt = $db->prepare("UPDATE notification_settings SET event_name=?, notification_type=?, recipient=?, name=?, is_active=? WHERE id=?");
                $stmt->execute([$eventName, $type, $recipient, $name, $isActive, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO notification_settings (event_name, notification_type, recipient, name, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$eventName, $type, $recipient, $name, $isActive, $_SESSION['user_id']]);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'toggle_status':
            $id = $_POST['id'] ?? null;
            $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
            
            if (!$id) throw new Exception('معرف الإشعار مطلوب');
            
            $stmt = $db->prepare("UPDATE notification_settings SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception('معرف الإشعار مطلوب');
            
            $stmt = $db->prepare("DELETE FROM notification_settings WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('إجراء غير معروف');
    }
} catch (Exception $e) {
    error_log("Notification Settings Ajax Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
