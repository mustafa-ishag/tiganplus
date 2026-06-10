<?php
/**
 * backend.php
 * ===========
 * نقطة الوصول لجميع العمليات المباشرة لقاعدة البيانات (CRUD)
 */

ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['error' => ['message' => "$errstr in $errfile on line $errline"]]);
    exit;
});
set_exception_handler(function($e) {
    echo json_encode(['error' => ['message' => $e->getMessage()]]);
    exit;
});

require 'db.php';

// قراءة البيانات المرسلة
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->action)) {
    echo json_encode(['error' => 'No action specified']);
    exit;
}

$action = $data->action;
$payload = $data->payload ?? null;

// دالة لتوليد UUID (بما أن قاعدة البيانات تستخدم char(36) كـ ID)
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

function parse_removed_item($item) {
    if ($item && !empty($item['image_url'])) {
        $decoded = json_decode($item['image_url']);
        if (json_last_error() === JSON_ERROR_NONE) {
            $item['image_url'] = $decoded;
        }
    }
    return $item;
}

try {
    switch ($action) {
        // ==========================
        // Work Orders
        // ==========================
        case 'create_work_order':
            $id = generate_uuid();
            $stmt = $pdo->prepare("INSERT INTO work_orders (id, wo_number, wo_type, location, department) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $id,
                $payload->wo_number,
                $payload->wo_type,
                $payload->location ?? null,
                $payload->department ?? null
            ]);
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['data' => $stmt->fetch()]);
            break;

        case 'fetch_work_orders':
            $stmt = $pdo->query("SELECT * FROM work_orders ORDER BY created_at DESC");
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'get_work_order':
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['data' => $stmt->fetch()]);
            break;

        case 'delete_work_order':
            $stmt = $pdo->prepare("DELETE FROM work_orders WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['error' => null]);
            break;

        case 'update_work_order_status':
            $stmt = $pdo->prepare("UPDATE work_orders SET status = ? WHERE id = ?");
            $stmt->execute([$payload->status, $payload->id]);
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['data' => $stmt->fetch()]);
            break;

        case 'update_work_order_location':
            $stmt = $pdo->prepare("UPDATE work_orders SET location = ? WHERE id = ?");
            $stmt->execute([$payload->location, $payload->id]);
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['data' => $stmt->fetch()]);
            break;

        // ==========================
        // Materials
        // ==========================
        case 'fetch_materials':
            $stmt = $pdo->query("SELECT * FROM master_materials ORDER BY item_number ASC");
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'search_materials':
            $q = '%' . $payload->query . '%';
            $stmt = $pdo->prepare("SELECT * FROM master_materials WHERE item_number LIKE ? OR description LIKE ? OR assembly_number LIKE ? LIMIT 15");
            $stmt->execute([$q, $q, $q]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'get_material_by_item':
            $stmt = $pdo->prepare("SELECT * FROM master_materials WHERE item_number = ?");
            $stmt->execute([$payload->item_number]);
            echo json_encode(['data' => $stmt->fetch()]);
            break;

        case 'upsert_materials':
            $materials = $payload->materials;
            $stmt = $pdo->prepare("INSERT INTO master_materials (id, item_number, description, unit) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description), unit=VALUES(unit)");
            foreach ($materials as $mat) {
                $id = $mat->id ?? generate_uuid();
                $stmt->execute([$id, $mat->item_number, $mat->description ?? null, $mat->unit ?? null]);
            }
            echo json_encode(['data' => $materials, 'error' => null]);
            break;

        case 'delete_material':
            $stmt = $pdo->prepare("DELETE FROM master_materials WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['error' => null]);
            break;

        // ==========================
        // Removed Items
        // ==========================
        case 'create_removed_item':
            $id = generate_uuid();
            $stmt = $pdo->prepare("INSERT INTO removed_items (id, wo_id, item_number, assembly_number, description, manufacture_year, serial_number, status, disposal_reason, material_condition, remarks, image_url, quantity, unit, functional_location, capacity_kva, manufacturer, equipment, prim_sec_volt, is_completed, item_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $id,
                $payload->wo_id,
                $payload->item_number,
                $payload->assembly_number ?? null,
                $payload->description ?? null,
                $payload->manufacture_year ?? null,
                $payload->serial_number ?? null,
                $payload->status ?? null,
                $payload->disposal_reason ?? null,
                $payload->material_condition ?? null,
                $payload->remarks ?? null,
                isset($payload->image_url) ? (is_array($payload->image_url) || is_object($payload->image_url) ? json_encode($payload->image_url, JSON_UNESCAPED_UNICODE) : $payload->image_url) : null,
                $payload->quantity ?? 1,
                $payload->unit ?? null,
                $payload->functional_location ?? null,
                $payload->capacity_kva ?? null,
                $payload->manufacturer ?? null,
                $payload->equipment ?? null,
                $payload->prim_sec_volt ?? null,
                $payload->is_completed ?? 0,
                $payload->item_type ?? null
            ]);
            $stmt = $pdo->prepare("SELECT * FROM removed_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            echo json_encode(['data' => parse_removed_item($item)]);
            break;

        case 'fetch_removed_items':
            $stmt = $pdo->prepare("SELECT * FROM removed_items WHERE wo_id = ? ORDER BY created_at DESC");
            $stmt->execute([$payload->wo_id]);
            $items = $stmt->fetchAll();
            foreach ($items as &$item) {
                $item = parse_removed_item($item);
            }
            echo json_encode(['data' => $items]);
            break;

        case 'delete_removed_item':
            $stmt = $pdo->prepare("DELETE FROM removed_items WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['error' => null]);
            break;

        case 'update_removed_item':
            $updates = (array)$payload->updates;
            $setClause = [];
            $values = [];
            foreach ($updates as $key => $value) {
                $setClause[] = "$key = ?";
                if (is_array($value) || is_object($value)) {
                    $values[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                } elseif (is_bool($value)) {
                    $values[] = $value ? 1 : 0;
                } else {
                    $values[] = $value;
                }
            }
            $values[] = $payload->id;
            
            if (!empty($setClause)) {
                $stmt = $pdo->prepare("UPDATE removed_items SET " . implode(", ", $setClause) . " WHERE id = ?");
                $stmt->execute($values);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM removed_items WHERE id = ?");
            $stmt->execute([$payload->id]);
            $item = $stmt->fetch();
            echo json_encode(['data' => parse_removed_item($item)]);
            break;

        case 'toggle_complete_removed_item':
            $stmt = $pdo->prepare("UPDATE removed_items SET is_completed = ? WHERE id = ?");
            $stmt->execute([$payload->is_completed, $payload->id]);
            $stmt = $pdo->prepare("SELECT * FROM removed_items WHERE id = ?");
            $stmt->execute([$payload->id]);
            $item = $stmt->fetch();
            echo json_encode(['data' => parse_removed_item($item)]);
            break;

        // ==========================
        // Attachments
        // ==========================
        case 'create_attachment':
            $id = generate_uuid();
            $stmt = $pdo->prepare("INSERT INTO wo_attachments (id, wo_id, file_name, file_url, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $id,
                $payload->wo_id,
                $payload->file_name,
                $payload->file_url,
                $payload->file_size
            ]);
            $stmt = $pdo->prepare("SELECT * FROM wo_attachments WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['data' => $stmt->fetch()]);
            break;

        case 'fetch_attachments':
            $stmt = $pdo->prepare("SELECT * FROM wo_attachments WHERE wo_id = ? ORDER BY created_at DESC");
            $stmt->execute([$payload->wo_id]);
            echo json_encode(['data' => $stmt->fetchAll()]);
            break;

        case 'delete_attachment':
            $stmt = $pdo->prepare("SELECT file_url FROM wo_attachments WHERE id = ?");
            $stmt->execute([$payload->id]);
            $att = $stmt->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM wo_attachments WHERE id = ?");
            $stmt->execute([$payload->id]);
            echo json_encode(['data' => $att, 'error' => null]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
            break;
    }
} catch (\PDOException $e) {
    echo json_encode(['error' => ['message' => $e->getMessage()]]);
}
