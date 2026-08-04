<?php
/**
 * نموذج بنود أوامر العمل للإنتاجية
 * Productivity Work Items Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

class ProductivityWorkItem
{
    private $db;
    
    public function __construct()
    {
        $this->db = getDB();
    }
    
    /**
     * إنشاء بند إنتاجية جديد
     */
    public function create($data)
    {
        try {
            $sql = "
                INSERT INTO productivity_work_items (
                    work_order_id, contract_work_item_id, target_quantity, unit_price,
                    start_date, target_end_date, status, priority, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['work_order_id'],
                $data['work_item_id'],
                $data['target_quantity'],
                $data['unit_price'],
                $data['start_date'] ?? null,
                $data['target_end_date'] ?? null,
                $data['status'] ?? 'active',
                $data['priority'] ?? 'medium',
                $data['notes'] ?? null,
                $data['created_by']
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                $this->logAudit('create', $id, null, $data, $data['created_by']);
                return $id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating productivity work item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تحديث بند إنتاجية
     */
    public function update($id, $data, $userId)
    {
        try {
            // جلب البيانات القديمة للمراجعة
            $oldData = $this->getById($id);
            if (!$oldData) {
                return false;
            }
            
            $sql = "
                UPDATE productivity_work_items SET
                    target_quantity = ?, unit_price = ?, start_date = ?,
                    target_end_date = ?, actual_end_date = ?, status = ?,
                    priority = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['target_quantity'],
                $data['unit_price'],
                $data['start_date'] ?? null,
                $data['target_end_date'] ?? null,
                $data['actual_end_date'] ?? null,
                $data['status'],
                $data['priority'],
                $data['notes'] ?? null,
                $id
            ]);
            
            if ($result) {
                $this->logAudit('update', $id, $oldData, $data, $userId);
                
                // تحديث الإحصائيات
                $this->updateStatistics($id);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error updating productivity work item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب بند إنتاجية بالمعرف
     */
    public function getById($id)
    {
        try {
            $sql = "
                SELECT pwi.*, wo.work_order_number, wi.item_number, wi.description as work_item_description,
                       wi.unit, b.name as branch_name, u.full_name as created_by_name
                FROM productivity_work_items pwi
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
                JOIN branches b ON wo.branch_id = b.id
                JOIN users u ON pwi.created_by = u.id
                WHERE pwi.id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting productivity work item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب بنود الإنتاجية لأمر عمل معين
     */
    public function getByWorkOrder($workOrderId, $filters = [])
    {
        try {
            $sql = "
                SELECT pwi.*, wi.item_number, wi.description as work_item_description,
                       wi.unit, u.full_name as created_by_name,
                       COALESCE(SUM(pdl.quantity_completed), 0) as total_completed,
                       CASE 
                           WHEN pwi.target_quantity > 0 THEN 
                               ROUND((COALESCE(SUM(pdl.quantity_completed), 0) / pwi.target_quantity) * 100, 2)
                           ELSE 0 
                       END as completion_percentage
                FROM productivity_work_items pwi
                JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
                JOIN users u ON pwi.created_by = u.id
                LEFT JOIN productivity_daily_logs pdl ON pwi.id = pdl.work_item_id 
                    AND pdl.status = 'approved'
                WHERE pwi.work_order_id = ?
            ";
            
            $params = [$workOrderId];
            
            // إضافة فلاتر
            if (!empty($filters['status'])) {
                $sql .= " AND pwi.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['priority'])) {
                $sql .= " AND pwi.priority = ?";
                $params[] = $filters['priority'];
            }
            
            $sql .= " GROUP BY pwi.id ORDER BY pwi.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting work order productivity items: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * جلب جميع بنود الإنتاجية مع فلاتر
     */
    public function getAll($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $sql = "
                SELECT pwi.*, wo.work_order_number, wi.item_number, wi.description as work_item_description,
                       wi.unit, b.name as branch_name, u.full_name as created_by_name,
                       COALESCE(SUM(pdl.quantity_completed), 0) as total_completed,
                       CASE 
                           WHEN pwi.target_quantity > 0 THEN 
                               ROUND((COALESCE(SUM(pdl.quantity_completed), 0) / pwi.target_quantity) * 100, 2)
                           ELSE 0 
                       END as completion_percentage
                FROM productivity_work_items pwi
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
                JOIN branches b ON wo.branch_id = b.id
                JOIN users u ON pwi.created_by = u.id
                LEFT JOIN productivity_daily_logs pdl ON pwi.id = pdl.work_item_id 
                    AND pdl.status = 'approved'
                WHERE 1=1
            ";
            
            $params = [];
            
            // إضافة فلاتر
            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND pwi.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['priority'])) {
                $sql .= " AND pwi.priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['work_order_id'])) {
                $sql .= " AND pwi.work_order_id = ?";
                $params[] = $filters['work_order_id'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (wo.work_order_number LIKE ? OR wi.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " GROUP BY pwi.id ORDER BY pwi.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting all productivity work items: " . $e->getMessage());
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
                SELECT COUNT(DISTINCT pwi.id) as total
                FROM productivity_work_items pwi
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                JOIN contract_work_items wi ON pwi.contract_work_item_id = wi.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // إضافة نفس الفلاتر
            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }
            
            if (!empty($filters['status'])) {
                $sql .= " AND pwi.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['priority'])) {
                $sql .= " AND pwi.priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['work_order_id'])) {
                $sql .= " AND pwi.work_order_id = ?";
                $params[] = $filters['work_order_id'];
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
            error_log("Error getting productivity work items count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * حذف بند إنتاجية
     */
    public function delete($id, $userId)
    {
        try {
            // التحقق من وجود سجلات يومية
            $logsCount = $this->db->prepare("SELECT COUNT(*) FROM productivity_daily_logs WHERE work_item_id = ?");
            $logsCount->execute([$id]);

            if ($logsCount->fetchColumn() > 0) {
                throw new Exception('لا يمكن حذف البند لوجود سجلات يومية مرتبطة به');
            }

            // جلب البيانات للمراجعة
            $oldData = $this->getById($id);
            if (!$oldData) {
                return false;
            }

            $sql = "DELETE FROM productivity_work_items WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);

            if ($result) {
                $this->logAudit('delete', $id, $oldData, null, $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error deleting productivity work item: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تحديث الإحصائيات
     */
    public function updateStatistics($workItemId)
    {
        try {
            // حساب الإحصائيات
            $sql = "
                SELECT
                    pwi.target_quantity,
                    pwi.start_date,
                    pwi.target_end_date,
                    COALESCE(SUM(pdl.quantity_completed), 0) as total_completed,
                    COUNT(DISTINCT pdl.log_date) as working_days,
                    AVG(pdl.work_quality) as avg_quality,
                    MAX(pdl.log_date) as last_activity_date
                FROM productivity_work_items pwi
                LEFT JOIN productivity_daily_logs pdl ON pwi.id = pdl.work_item_id
                    AND pdl.status = 'approved'
                WHERE pwi.id = ?
                GROUP BY pwi.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$workItemId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats) return false;

            // حساب النسب والمؤشرات
            $completionPercentage = $stats['target_quantity'] > 0 ?
                ($stats['total_completed'] / $stats['target_quantity']) * 100 : 0;

            $averageDailyRate = $stats['working_days'] > 0 ?
                $stats['total_completed'] / $stats['working_days'] : 0;

            // تقدير تاريخ الانتهاء
            $estimatedCompletionDate = null;
            if ($averageDailyRate > 0 && $stats['target_quantity'] > $stats['total_completed']) {
                $remainingQuantity = $stats['target_quantity'] - $stats['total_completed'];
                $remainingDays = ceil($remainingQuantity / $averageDailyRate);
                $estimatedCompletionDate = date('Y-m-d', strtotime("+{$remainingDays} days"));
            }

            // حساب درجة الكفاءة
            $efficiencyScore = 100; // افتراضي
            if ($stats['target_end_date'] && $stats['start_date']) {
                $plannedDays = (strtotime($stats['target_end_date']) - strtotime($stats['start_date'])) / (60*60*24);
                $actualDays = $stats['working_days'];
                if ($plannedDays > 0) {
                    $efficiencyScore = min(100, ($plannedDays / max($actualDays, 1)) * 100);
                }
            }

            // حساب أيام التأخير
            $delayDays = 0;
            if ($stats['target_end_date'] && $completionPercentage < 100) {
                $today = date('Y-m-d');
                if ($today > $stats['target_end_date']) {
                    $delayDays = (strtotime($today) - strtotime($stats['target_end_date'])) / (60*60*24);
                }
            }

            // حفظ الإحصائيات
            $insertSql = "
                INSERT INTO productivity_statistics (
                    work_item_id, calculation_date, total_completed, completion_percentage,
                    average_daily_rate, working_days_count, estimated_completion_date,
                    efficiency_score, quality_score, delay_days, last_activity_date
                ) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_completed = VALUES(total_completed),
                    completion_percentage = VALUES(completion_percentage),
                    average_daily_rate = VALUES(average_daily_rate),
                    working_days_count = VALUES(working_days_count),
                    estimated_completion_date = VALUES(estimated_completion_date),
                    efficiency_score = VALUES(efficiency_score),
                    quality_score = VALUES(quality_score),
                    delay_days = VALUES(delay_days),
                    last_activity_date = VALUES(last_activity_date),
                    updated_at = CURRENT_TIMESTAMP
            ";

            $insertStmt = $this->db->prepare($insertSql);
            return $insertStmt->execute([
                $workItemId,
                $stats['total_completed'],
                round($completionPercentage, 2),
                round($averageDailyRate, 3),
                $stats['working_days'],
                $estimatedCompletionDate,
                round($efficiencyScore, 2),
                round($stats['avg_quality'] ?? 0, 2),
                $delayDays,
                $stats['last_activity_date']
            ]);

        } catch (Exception $e) {
            error_log("Error updating statistics: " . $e->getMessage());
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
                'productivity_work_items',
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
            if (empty($data['work_order_id'])) {
                $errors[] = 'أمر العمل مطلوب';
            }

            if (empty($data['work_item_id'])) {
                $errors[] = 'بند العمل مطلوب';
            }
        }

        if (empty($data['target_quantity']) || $data['target_quantity'] <= 0) {
            $errors[] = 'الكمية المستهدفة مطلوبة ويجب أن تكون أكبر من صفر';
        }

        if (empty($data['unit_price']) || $data['unit_price'] < 0) {
            $errors[] = 'سعر الوحدة مطلوب ويجب أن يكون صفر أو أكبر';
        }

        if (!empty($data['start_date']) && !empty($data['target_end_date'])) {
            if (strtotime($data['start_date']) > strtotime($data['target_end_date'])) {
                $errors[] = 'تاريخ البداية يجب أن يكون قبل تاريخ الانتهاء المستهدف';
            }
        }

        return $errors;
    }

    /**
     * جلب الإحصائيات العامة
     */
    public function getOverallStatistics($filters = [])
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as total_items,
                    SUM(CASE WHEN pwi.status = 'active' THEN 1 ELSE 0 END) as active_items,
                    SUM(CASE WHEN pwi.status = 'completed' THEN 1 ELSE 0 END) as completed_items,
                    SUM(CASE WHEN pwi.status = 'paused' THEN 1 ELSE 0 END) as paused_items,
                    SUM(CASE WHEN pwi.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_items,
                    SUM(pwi.total_value) as total_value,
                    AVG(ps.completion_percentage) as avg_completion,
                    AVG(ps.efficiency_score) as avg_efficiency
                FROM productivity_work_items pwi
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                LEFT JOIN productivity_statistics ps ON pwi.id = ps.work_item_id
                    AND ps.calculation_date = CURDATE()
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND pwi.created_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND pwi.created_at <= ?";
                $params[] = $filters['date_to'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting overall statistics: " . $e->getMessage());
            return [];
        }
    }

}
