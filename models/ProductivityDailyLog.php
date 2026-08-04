<?php
/**
 * نموذج السجلات اليومية للإنتاجية
 * Productivity Daily Logs Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

class ProductivityDailyLog
{
    private $db;
    
    public function __construct()
    {
        $this->db = getDB();
    }
    
    /**
     * إنشاء سجل يومي جديد
     */
    public function create($data)
    {
        try {
            $sql = "
                INSERT INTO productivity_daily_logs (
                    work_item_id, log_date, quantity_completed, work_hours, workers_count,
                    equipment_used, weather_condition, work_quality, obstacles, notes,
                    attachments, location_coordinates, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['work_item_id'],
                $data['log_date'],
                $data['quantity_completed'],
                $data['work_hours'] ?? 0,
                $data['workers_count'] ?? 0,
                $data['equipment_used'] ?? null,
                $data['weather_condition'] ?? null,
                $data['work_quality'] ?? 'good',
                $data['obstacles'] ?? null,
                $data['notes'] ?? null,
                !empty($data['attachments']) ? json_encode($data['attachments']) : null,
                $data['location_coordinates'] ?? null,
                $data['status'] ?? 'draft',
                $data['created_by']
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                $this->logAudit('create', $id, null, $data, $data['created_by']);
                
                // تحديث الإحصائيات إذا كان السجل معتمد
                if (($data['status'] ?? 'draft') === 'approved') {
                    $this->updateWorkItemStatistics($data['work_item_id']);
                }
                
                return $id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating daily log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تحديث سجل يومي
     */
    public function update($id, $data, $userId = null)
    {
        try {
            // جلب البيانات القديمة
            $oldData = $this->getById($id);
            if (!$oldData) {
                return false;
            }
            
            $sql = "
                UPDATE productivity_daily_logs SET
                    quantity_completed = ?, work_hours = ?, workers_count = ?,
                    equipment_used = ?, weather_condition = ?, work_quality = ?,
                    obstacles = ?, notes = ?, attachments = ?, location_coordinates = ?,
                    status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['quantity_completed'],
                $data['work_hours'] ?? 0,
                $data['workers_count'] ?? 0,
                $data['equipment_used'] ?? null,
                $data['weather_condition'] ?? null,
                $data['work_quality'] ?? 'good',
                $data['obstacles'] ?? null,
                $data['notes'] ?? null,
                !empty($data['attachments']) ? json_encode($data['attachments']) : null,
                $data['location_coordinates'] ?? null,
                $data['status'] ?? $oldData['status'],
                $id
            ]);
            
            if ($result) {
                $this->logAudit('update', $id, $oldData, $data, $userId);
                
                // تحديث الإحصائيات إذا تغيرت الحالة إلى معتمد
                $newStatus = $data['status'] ?? $oldData['status'];
                if ($newStatus === 'approved' || $oldData['status'] === 'approved') {
                    $this->updateWorkItemStatistics($oldData['work_item_id']);
                }
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error updating daily log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب سجل يومي بالمعرف
     */
    public function getById($id)
    {
        try {
            $sql = "
                SELECT pdl.*, pwi.target_quantity, pwi.unit_price,
                       wo.work_order_number, wi.item_number, wi.description as work_item_description,
                       wi.unit, b.name as branch_name, u.full_name as created_by_name
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
                JOIN branches b ON wo.branch_id = b.id
                JOIN users u ON pdl.created_by = u.id
                WHERE pdl.id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // فك تشفير المرفقات
            if ($result && $result['attachments']) {
                $result['attachments'] = json_decode($result['attachments'], true);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting daily log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب السجلات اليومية لبند إنتاجية معين
     */
    public function getByWorkItem($workItemId, $filters = [])
    {
        try {
            $sql = "
                SELECT pdl.*, u.full_name as created_by_name,
                       CASE 
                           WHEN pdl.status = 'approved' THEN pdl.quantity_completed * pwi.unit_price
                           ELSE 0 
                       END as approved_value
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN users u ON pdl.created_by = u.id
                WHERE pdl.work_item_id = ?
            ";
            
            $params = [$workItemId];
            
            // إضافة فلاتر
            if (!empty($filters['status'])) {
                $sql .= " AND pdl.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND pdl.log_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND pdl.log_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql .= " ORDER BY pdl.log_date DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // فك تشفير المرفقات
            foreach ($results as &$result) {
                if ($result['attachments']) {
                    $result['attachments'] = json_decode($result['attachments'], true);
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error getting work item daily logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب جميع السجلات اليومية مع فلاتر
     */
    public function getAll($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $sql = "
                SELECT pdl.*, pwi.target_quantity, pwi.unit_price,
                       wo.work_order_number, wi.item_number, wi.description as work_item_description,
                       wi.unit, b.name as branch_name, u.full_name as created_by_name,
                       CASE 
                           WHEN pdl.status = 'approved' THEN pdl.quantity_completed * pwi.unit_price
                           ELSE 0 
                       END as approved_value
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
                JOIN branches b ON wo.branch_id = b.id
                JOIN users u ON pdl.created_by = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // إضافة فلاتر
            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND pdl.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['work_item_id'])) {
                $sql .= " AND pdl.work_item_id = ?";
                $params[] = $filters['work_item_id'];
            }
            
            if (!empty($filters['work_order_id'])) {
                $sql .= " AND pwi.work_order_id = ?";
                $params[] = $filters['work_order_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND pdl.log_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND pdl.log_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['created_by'])) {
                $sql .= " AND pdl.created_by = ?";
                $params[] = $filters['created_by'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (wo.work_order_number LIKE ? OR wi.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY pdl.log_date DESC, pdl.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // فك تشفير المرفقات
            foreach ($results as &$result) {
                if ($result['attachments']) {
                    $result['attachments'] = json_decode($result['attachments'], true);
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error getting all daily logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * حساب إجمالي السجلات
     */
    public function getTotalCount($filters = [])
    {
        try {
            $sql = "
                SELECT COUNT(*) as total
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                WHERE 1=1
            ";

            $params = [];

            // إضافة نفس الفلاتر
            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND pdl.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['work_item_id'])) {
                $sql .= " AND pdl.work_item_id = ?";
                $params[] = $filters['work_item_id'];
            }

            if (!empty($filters['work_order_id'])) {
                $sql .= " AND pwi.work_order_id = ?";
                $params[] = $filters['work_order_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND pdl.log_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND pdl.log_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['created_by'])) {
                $sql .= " AND pdl.created_by = ?";
                $params[] = $filters['created_by'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (wo.work_order_number LIKE ? OR wi.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting daily logs count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * إرسال سجل للاعتماد
     */
    public function submitForApproval($id, $userId)
    {
        try {
            // جلب الحالة الحالية للسجل
            $currentLog = $this->getById($id);
            if (!$currentLog) {
                return false;
            }

            $sql = "
                UPDATE productivity_daily_logs
                SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND created_by = ? AND status IN ('draft', 'rejected', 'returned')
            ";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id, $userId]);

            if ($result && $stmt->rowCount() > 0) {
                $this->logAudit('update', $id, ['status' => $currentLog['status']], ['status' => 'submitted'], $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error submitting daily log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف سجل يومي
     */
    public function delete($id, $userId)
    {
        try {
            // التحقق من الحالة - لا يمكن حذف السجلات المعتمدة
            $log = $this->getById($id);
            if (!$log) {
                return false;
            }

            if ($log['status'] === 'approved') {
                throw new Exception('لا يمكن حذف السجل المعتمد');
            }

            $sql = "DELETE FROM productivity_daily_logs WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);

            if ($result) {
                $this->logAudit('delete', $id, $log, null, $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error deleting daily log: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث إحصائيات بند العمل
     */
    private function updateWorkItemStatistics($workItemId)
    {
        try {
            require_once __DIR__ . '/ProductivityWorkItem.php';
            $workItemModel = new ProductivityWorkItem();
            return $workItemModel->updateStatistics($workItemId);
        } catch (Exception $e) {
            error_log("Error updating work item statistics: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تسجيل عملية في سجل المراجعة
     */
    private function logAudit($action, $recordId, $oldValues, $newValues, $userId)
    {
        try {
            $sql = "
                INSERT INTO productivity_audit_logs (
                    table_name, record_id, action, old_values, new_values, user_id, ip_address
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'productivity_daily_logs',
                $recordId,
                $action,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error logging audit: " . $e->getMessage());
        }
    }

    /**
     * التحقق من صحة البيانات
     */
    public function validate($data, $isUpdate = false)
    {
        $errors = [];

        if (!$isUpdate) {
            if (empty($data['work_item_id'])) {
                $errors[] = 'بند الإنتاجية مطلوب';
            }

            if (empty($data['log_date'])) {
                $errors[] = 'تاريخ التسجيل مطلوب';
            }
        }

        if (empty($data['quantity_completed']) || $data['quantity_completed'] < 0) {
            $errors[] = 'الكمية المنجزة مطلوبة ويجب أن تكون صفر أو أكبر';
        }

        if (!empty($data['work_hours']) && $data['work_hours'] < 0) {
            $errors[] = 'ساعات العمل يجب أن تكون صفر أو أكبر';
        }

        if (!empty($data['workers_count']) && $data['workers_count'] < 0) {
            $errors[] = 'عدد العمال يجب أن يكون صفر أو أكبر';
        }

        if (!empty($data['log_date'])) {
            $logDate = strtotime($data['log_date']);
            $today = strtotime(date('Y-m-d'));

            if ($logDate > $today) {
                $errors[] = 'لا يمكن تسجيل إنتاجية لتاريخ مستقبلي';
            }

            // التحقق من عدم تجاوز 30 يوم في الماضي
            $thirtyDaysAgo = strtotime('-30 days');
            if ($logDate < $thirtyDaysAgo) {
                $errors[] = 'لا يمكن تسجيل إنتاجية لتاريخ أقدم من 30 يوم';
            }
        }

        return $errors;
    }

    /**
     * جلب إحصائيات السجلات اليومية
     */
    public function getDailyStatistics($filters = [])
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as total_logs,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_logs,
                    SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_logs,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_logs,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_logs,
                    SUM(CASE WHEN status = 'approved' THEN quantity_completed ELSE 0 END) as total_quantity,
                    SUM(CASE WHEN status = 'approved' THEN work_hours ELSE 0 END) as total_hours,
                    AVG(CASE WHEN status = 'approved' THEN work_hours ELSE NULL END) as avg_work_hours,
                    AVG(CASE WHEN status = 'approved' THEN workers_count ELSE NULL END) as avg_workers_count
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND pdl.log_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND pdl.log_date <= ?";
                $params[] = $filters['date_to'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // التأكد من وجود جميع الحقول المطلوبة
            if ($result) {
                return [
                    'total_logs' => (int)($result['total_logs'] ?? 0),
                    'draft_logs' => (int)($result['draft_logs'] ?? 0),
                    'submitted_logs' => (int)($result['submitted_logs'] ?? 0),
                    'approved_logs' => (int)($result['approved_logs'] ?? 0),
                    'rejected_logs' => (int)($result['rejected_logs'] ?? 0),
                    'total_quantity' => (float)($result['total_quantity'] ?? 0),
                    'total_hours' => (float)($result['total_hours'] ?? 0),
                    'avg_work_hours' => (float)($result['avg_work_hours'] ?? 0),
                    'avg_workers_count' => (float)($result['avg_workers_count'] ?? 0)
                ];
            }

            return [
                'total_logs' => 0,
                'draft_logs' => 0,
                'submitted_logs' => 0,
                'approved_logs' => 0,
                'rejected_logs' => 0,
                'total_quantity' => 0,
                'total_hours' => 0,
                'avg_work_hours' => 0,
                'avg_workers_count' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting daily statistics: " . $e->getMessage());
            return [
                'total_logs' => 0,
                'draft_logs' => 0,
                'submitted_logs' => 0,
                'approved_logs' => 0,
                'rejected_logs' => 0,
                'total_quantity' => 0,
                'total_hours' => 0,
                'avg_work_hours' => 0,
                'avg_workers_count' => 0
            ];
        }
    }

    /**
     * التحقق من توفر التاريخ لبند العمل
     */
    public function isDateAvailable($workItemId, $date, $excludeId = null)
    {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM productivity_daily_logs
                WHERE work_item_id = ? AND log_date = ?
            ";

            $params = [$workItemId, $date];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn() == 0;
        } catch (Exception $e) {
            error_log("Error checking date availability: " . $e->getMessage());
            return false;
        }
    }

}
