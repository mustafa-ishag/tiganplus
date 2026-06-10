<?php
/**
 * نموذج إدارة العملاء والمقاولين (Inventory Clients)
 */

class InventoryClient
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * جلب جميع العملاء
     */
    public function getAllClients(): array
    {
        $stmt = $this->db->query("SELECT * FROM inventory_clients ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * جلب عميل بالمعرف
     */
    public function getClientById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM inventory_clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        return $client ?: null;
    }

    /**
     * إضافة عميل جديد
     */
    public function createClient(array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_clients (name, type, phone, email) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['type'] ?? 'contractor',
                $data['phone'] ?? null,
                $data['email'] ?? null
            ]);
            
            return [
                'success' => true,
                'client_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            error_log("Error creating client: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة العميل: ' . $e->getMessage()
            ];
        }
    }

    /**
     * تحديث بيانات العميل
     */
    public function updateClient(int $id, array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_clients 
                SET name = ?, type = ?, phone = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['type'] ?? 'contractor',
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $id
            ]);
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Error updating client: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث بيانات العميل: ' . $e->getMessage()
            ];
        }
    }
}
