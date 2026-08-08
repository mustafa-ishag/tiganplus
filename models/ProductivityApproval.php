<?php
/**
 * نموذج اعتمادات الإنتاجية
 * Productivity Approvals Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

class ProductivityApproval
{
    private $db;
    
    public function __construct()
    {
        $this->db = getDB();
    }
    
    /**
     * اعتماد سجل يومي
     */
    public function approve($dailyLogId, $approverId, $comments = null, $approvalValue = null)
    {
        try {
            $this->db->beginTransaction();
            
            // جلب معلومات السجل اليومي
            $logSql = "
                SELECT pdl.*, pwi.unit_price, pwi.work_order_id
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                WHERE pdl.id = ? AND pdl.status = 'submitted'
            ";
            $logStmt = $this->db->prepare($logSql);
            $logStmt->execute([$dailyLogId]);
            $log = $logStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$log) {
                throw new Exception('السجل غير موجود أو غير قابل للاعتماد');
            }
            
            // التحقق من صلاحية المعتمد
            $approverLevel = $this->getApproverLevel($approverId, $log['work_order_id']);
            if (!$approverLevel) {
                throw new Exception('ليس لديك صلاحية اعتماد هذا السجل');
            }
            
            // حساب قيمة الاعتماد
            $calculatedValue = $log['quantity_completed'] * $log['unit_price'];
            $finalApprovalValue = $approvalValue ?? $calculatedValue;
            
            // التحقق من حدود الاعتماد
            if (!$this->checkApprovalLimits($approverId, $finalApprovalValue)) {
                throw new Exception('تجاوزت قيمة الاعتماد الحد المسموح لك');
            }
            
            // تحديث حالة السجل اليومي
            $updateLogSql = "
                UPDATE productivity_daily_logs 
                SET status = 'approved', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            $updateLogStmt = $this->db->prepare($updateLogSql);
            $updateLogStmt->execute([$dailyLogId]);
            
            // إدراج سجل الاعتماد
            $approvalSql = "
                INSERT INTO productivity_approvals (
                    daily_log_id, approver_id, action, comments, approval_level, approval_value
                ) VALUES (?, ?, 'approved', ?, ?, ?)
            ";
            $approvalStmt = $this->db->prepare($approvalSql);
            $approvalStmt->execute([
                $dailyLogId,
                $approverId,
                $comments,
                $approverLevel,
                $finalApprovalValue
            ]);
            
            $approvalId = $this->db->lastInsertId();
            
            // تحديث إحصائيات بند العمل
            $this->updateWorkItemStatistics($log['work_item_id']);
            
            // تسجيل في سجل المراجعة
            $this->logAudit('approve', $approvalId, null, [
                'daily_log_id' => $dailyLogId,
                'action' => 'approved',
                'approval_value' => $finalApprovalValue
            ], $approverId);
            
            $this->db->commit();
            return $approvalId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error approving daily log: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * رفض سجل يومي
     */
    public function reject($dailyLogId, $approverId, $comments)
    {
        try {
            $this->db->beginTransaction();
            
            // جلب معلومات السجل اليومي
            $logSql = "
                SELECT pdl.*, pwi.work_order_id
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                WHERE pdl.id = ? AND pdl.status = 'submitted'
            ";
            $logStmt = $this->db->prepare($logSql);
            $logStmt->execute([$dailyLogId]);
            $log = $logStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$log) {
                throw new Exception('السجل غير موجود أو غير قابل للرفض');
            }
            
            // التحقق من صلاحية المعتمد
            $approverLevel = $this->getApproverLevel($approverId, $log['work_order_id']);
            if (!$approverLevel) {
                throw new Exception('ليس لديك صلاحية رفض هذا السجل');
            }
            
            // تحديث حالة السجل اليومي
            $updateLogSql = "
                UPDATE productivity_daily_logs 
                SET status = 'rejected', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            $updateLogStmt = $this->db->prepare($updateLogSql);
            $updateLogStmt->execute([$dailyLogId]);
            
            // إدراج سجل الرفض
            $approvalSql = "
                INSERT INTO productivity_approvals (
                    daily_log_id, approver_id, action, comments, approval_level
                ) VALUES (?, ?, 'rejected', ?, ?)
            ";
            $approvalStmt = $this->db->prepare($approvalSql);
            $approvalStmt->execute([
                $dailyLogId,
                $approverId,
                $comments,
                $approverLevel
            ]);
            
            $approvalId = $this->db->lastInsertId();
            
            // تسجيل في سجل المراجعة
            $this->logAudit('reject', $approvalId, null, [
                'daily_log_id' => $dailyLogId,
                'action' => 'rejected',
                'comments' => $comments
            ], $approverId);
            
            $this->db->commit();
            return $approvalId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error rejecting daily log: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * إرجاع سجل يومي للتعديل
     */
    public function returnForRevision($dailyLogId, $approverId, $comments)
    {
        try {
            $this->db->beginTransaction();
            
            // جلب معلومات السجل اليومي
            $logSql = "
                SELECT pdl.*, pwi.work_order_id
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                WHERE pdl.id = ? AND pdl.status = 'submitted'
            ";
            $logStmt = $this->db->prepare($logSql);
            $logStmt->execute([$dailyLogId]);
            $log = $logStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$log) {
                throw new Exception('السجل غير موجود أو غير قابل للإرجاع');
            }
            
            // التحقق من صلاحية المعتمد
            $approverLevel = $this->getApproverLevel($approverId, $log['work_order_id']);
            if (!$approverLevel) {
                throw new Exception('ليس لديك صلاحية إرجاع هذا السجل');
            }
            
            // تحديث حالة السجل اليومي
            $updateLogSql = "
                UPDATE productivity_daily_logs 
                SET status = 'returned', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            $updateLogStmt = $this->db->prepare($updateLogSql);
            $updateLogStmt->execute([$dailyLogId]);
            
            // إدراج سجل الإرجاع
            $approvalSql = "
                INSERT INTO productivity_approvals (
                    daily_log_id, approver_id, action, comments, approval_level
                ) VALUES (?, ?, 'returned', ?, ?)
            ";
            $approvalStmt = $this->db->prepare($approvalSql);
            $approvalStmt->execute([
                $dailyLogId,
                $approverId,
                $comments,
                $approverLevel
            ]);
            
            $approvalId = $this->db->lastInsertId();
            
            // تسجيل في سجل المراجعة
            $this->logAudit('return', $approvalId, null, [
                'daily_log_id' => $dailyLogId,
                'action' => 'returned',
                'comments' => $comments
            ], $approverId);
            
            $this->db->commit();
            return $approvalId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error returning daily log: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * جلب السجلات المعلقة للاعتماد
     */
    public function getPendingApprovals($approverId, $filters = [], $limit = 50, $offset = 0)
    {
        try {
            $sql = "
                SELECT pdl.*, pwi.target_quantity, pwi.unit_price,
                       wo.work_order_number, pwi.work_item_description,
                       pwi.unit, b.name as branch_name, u.full_name as created_by_name,
                       (pdl.quantity_completed * pwi.unit_price) as calculated_value
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                LEFT JOIN branches b ON wo.branch_id = b.id
                LEFT JOIN users u ON pdl.created_by = u.id
                WHERE pdl.status = 'submitted'
                AND EXISTS (
                    SELECT 1 FROM productivity_approvers pa
                    WHERE pa.user_id = ? AND pa.is_active = 1
                    AND (pa.branch_id IS NULL OR pa.branch_id = 0 OR pa.branch_id = wo.branch_id)
                    AND (pa.department = 'all' OR pa.department = wo.department)
                    AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')
                )
            ";
            
            $params = [$approverId];
            
            // إضافة فلاتر
            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
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
            
            $sql .= " ORDER BY pdl.submitted_at ASC LIMIT ? OFFSET ?";
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
            error_log("Error getting pending approvals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب تاريخ الاعتمادات لسجل يومي
     */
    public function getApprovalHistory($dailyLogId)
    {
        try {
            $sql = "
                SELECT pa.*, u.full_name as approver_name
                FROM productivity_approvals pa
                JOIN users u ON pa.approver_id = u.id
                WHERE pa.daily_log_id = ?
                ORDER BY pa.approved_at DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$dailyLogId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approval history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من مستوى المعتمد
     */
    private function getApproverLevel($approverId, $workOrderId)
    {
        try {
            $sql = "
                SELECT pa.approval_level
                FROM productivity_approvers pa
                JOIN work_orders wo ON (pa.branch_id IS NULL OR pa.branch_id = 0 OR pa.branch_id = wo.branch_id)
                WHERE pa.user_id = ? AND wo.id = ? AND pa.is_active = 1
                AND (pa.department = 'all' OR pa.department = wo.department)
                AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')
                ORDER BY
                    CASE pa.approval_level
                        WHEN 'general_manager' THEN 4
                        WHEN 'director' THEN 3
                        WHEN 'manager' THEN 2
                        WHEN 'supervisor' THEN 1
                    END DESC
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$approverId, $workOrderId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['approval_level'] ?? null;
        } catch (Exception $e) {
            error_log("Error getting approver level: " . $e->getMessage());
            return null;
        }
    }

    /**
     * التحقق من حدود الاعتماد
     */
    private function checkApprovalLimits($approverId, $approvalValue)
    {
        try {
            $sql = "
                SELECT MAX(pa.max_amount_limit) as max_limit
                FROM productivity_approvers pa
                WHERE pa.user_id = ? AND pa.is_active = 1
                AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$approverId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $maxLimit = $result['max_limit'] ?? 0;

            // إذا كان الحد NULL فهذا يعني لا يوجد حد أقصى
            return $maxLimit === null || $approvalValue <= $maxLimit;
        } catch (Exception $e) {
            error_log("Error checking approval limits: " . $e->getMessage());
            return false;
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
                'productivity_approvals',
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
     * جلب إحصائيات الاعتمادات
     */
    public function getApprovalStatistics($approverId, $filters = [])
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as total_approvals,
                    SUM(CASE WHEN pa.action = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN pa.action = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN pa.action = 'returned' THEN 1 ELSE 0 END) as returned_count,
                    SUM(CASE WHEN pa.action = 'approved' THEN pa.approval_value ELSE 0 END) as total_approved_value,
                    AVG(CASE WHEN pa.action = 'approved' THEN pa.approval_value ELSE NULL END) as avg_approved_value
                FROM productivity_approvals pa
                JOIN productivity_daily_logs pdl ON pa.daily_log_id = pdl.id
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                JOIN work_orders wo ON pwi.work_order_id = wo.id
                WHERE pa.approver_id = ?
            ";

            $params = [$approverId];

            if (!empty($filters['date_from'])) {
                $sql .= " AND pa.approved_at >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND pa.approved_at <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['branch_id'])) {
                $sql .= " AND wo.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approval statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب المعتمدين المتاحين لأمر عمل
     */
    public function getAvailableApprovers($workOrderId)
    {
        try {
            $sql = "
                SELECT DISTINCT u.id, u.full_name, pa.approval_level, pa.max_amount_limit
                FROM users u
                JOIN productivity_approvers pa ON u.id = pa.user_id
                JOIN work_orders wo ON (pa.branch_id IS NULL OR pa.branch_id = 0 OR pa.branch_id = wo.branch_id)
                WHERE wo.id = ? AND pa.is_active = 1 AND u.status = 'active'
                AND (pa.department = 'all' OR pa.department = wo.department)
                AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')
                ORDER BY
                    CASE pa.approval_level
                        WHEN 'general_manager' THEN 4
                        WHEN 'director' THEN 3
                        WHEN 'manager' THEN 2
                        WHEN 'supervisor' THEN 1
                    END DESC,
                    u.full_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$workOrderId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting available approvers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب عدد السجلات المعلقة للاعتماد
     * Get pending approvals count
     */
    public function getPendingApprovalsCount($approverId, $filters = []) {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM productivity_daily_logs pdl
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                LEFT JOIN work_orders wo ON pwi.work_order_id = wo.id
                LEFT JOIN branches b ON wo.branch_id = b.id
                LEFT JOIN users u ON pdl.created_by = u.id
                JOIN productivity_approvers pa ON (
                    (pa.branch_id IS NULL OR pa.branch_id = 0 OR pa.branch_id = wo.branch_id) AND
                    (pa.department = 'all' OR
                     (pa.department = 'connections' AND wo.department = 'connections') OR
                     (pa.department = 'projects' AND wo.department = 'projects'))
                )
                WHERE pdl.status = 'submitted'
                AND pa.user_id = ?
                AND pa.is_active = 1
                AND (pa.effective_from IS NULL OR pa.effective_from <= CURDATE())
                AND (pa.effective_to IS NULL OR pa.effective_to >= CURDATE())
            ";

            $params = [$approverId];

            // تطبيق الفلاتر
            if (!empty($filters['search'])) {
                $sql .= " AND (wo.work_order_number LIKE ? OR pwi.work_item_description LIKE ? OR pwi.contract_work_item_id LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

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

            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting pending approvals count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * جلب تاريخ الاعتمادات الشامل مع فلاتر
     */
    public function getAllApprovalHistory($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $sql = "
                SELECT pa.*, pdl.quantity_completed, pdl.log_date,
                       wo.work_order_number, pwi.contract_work_item_id as item_number, pwi.work_item_description,
                       pwi.unit, b.name as branch_name, u.full_name as approver_name
                FROM productivity_approvals pa
                JOIN productivity_daily_logs pdl ON pa.daily_log_id = pdl.id
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                LEFT JOIN work_orders wo ON pwi.work_order_id = wo.id
                LEFT JOIN branches b ON wo.branch_id = b.id
                LEFT JOIN users u ON pa.approver_id = u.id
                WHERE 1=1
            ";

            $params = [];

            // إضافة فلاتر
            if (!empty($filters['action'])) {
                $sql .= " AND pa.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['approver_id'])) {
                $sql .= " AND pa.approver_id = ?";
                $params[] = $filters['approver_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(pa.approved_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(pa.approved_at) <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (wo.work_order_number LIKE ? OR pwi.work_item_description LIKE ? OR pwi.contract_work_item_id LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY pa.approved_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting all approval history: " . $e->getMessage());
            return [];
        }
    }


    /**
     * جلب عدد سجلات تاريخ الاعتمادات
     */
    public function getApprovalHistoryCount($filters = [])
    {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM productivity_approvals pa
                JOIN productivity_daily_logs pdl ON pa.daily_log_id = pdl.id
                JOIN productivity_work_items pwi ON pdl.work_item_id = pwi.id
                LEFT JOIN work_orders wo ON pwi.work_order_id = wo.id
                LEFT JOIN branches b ON wo.branch_id = b.id
                LEFT JOIN users u ON pa.approver_id = u.id
                WHERE 1=1
            ";

            $params = [];

            // إضافة فلاتر
            if (!empty($filters['action'])) {
                $sql .= " AND pa.action = ?";
                $params[] = $filters['action'];
            }

            if (!empty($filters['approver_id'])) {
                $sql .= " AND pa.approver_id = ?";
                $params[] = $filters['approver_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(pa.approved_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(pa.approved_at) <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (wo.work_order_number LIKE ? OR pwi.work_item_description LIKE ? OR pwi.contract_work_item_id LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting approval history count: " . $e->getMessage());
            return 0;
        }
    }
}
