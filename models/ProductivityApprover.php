<?php
/**
 * نموذج المعتمدين للإنتاجية
 * Productivity Approvers Model
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

class ProductivityApprover
{
    private $db;
    
    public function __construct()
    {
        $this->db = getDB();
    }
    
    /**
     * إضافة معتمد جديد
     */
    public function create($data)
    {
        try {
            // التحقق من عدم وجود تعيين مكرر
            if ($this->isDuplicateAssignment($data)) {
                throw new Exception('يوجد تعيين مماثل لهذا المستخدم');
            }
            
            $sql = "
                INSERT INTO productivity_approvers (
                    user_id, branch_id, department, approval_level, max_amount_limit,
                    can_approve_own_branch, can_approve_other_branches, is_active,
                    effective_from, effective_to, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['user_id'],
                $data['branch_id'] ?? null,
                $data['department'] ?? 'all',
                $data['approval_level'],
                $data['max_amount_limit'] ?? null,
                $data['can_approve_own_branch'] ?? true,
                $data['can_approve_other_branches'] ?? false,
                $data['is_active'] ?? true,
                $data['effective_from'],
                $data['effective_to'] ?? null,
                $data['created_by']
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                $this->logAudit('create', $id, null, $data, $data['created_by']);
                return $id;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating approver: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * تحديث معتمد
     */
    public function update($id, $data, $userId)
    {
        try {
            // جلب البيانات القديمة
            $oldData = $this->getById($id);
            if (!$oldData) {
                return false;
            }
            
            $sql = "
                UPDATE productivity_approvers SET
                    branch_id = ?, department = ?, approval_level = ?, max_amount_limit = ?,
                    can_approve_own_branch = ?, can_approve_other_branches = ?, is_active = ?,
                    effective_from = ?, effective_to = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['branch_id'] ?? null,
                $data['department'] ?? 'all',
                $data['approval_level'],
                $data['max_amount_limit'] ?? null,
                $data['can_approve_own_branch'] ?? true,
                $data['can_approve_other_branches'] ?? false,
                $data['is_active'] ?? true,
                $data['effective_from'],
                $data['effective_to'] ?? null,
                $id
            ]);
            
            if ($result) {
                $this->logAudit('update', $id, $oldData, $data, $userId);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error updating approver: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب معتمد بالمعرف
     */
    public function getById($id)
    {
        try {
            $sql = "
                SELECT pa.*, u.username, u.full_name, u.email,
                       b.name as branch_name, creator.full_name as created_by_name
                FROM productivity_approvers pa
                JOIN users u ON pa.user_id = u.id
                LEFT JOIN branches b ON pa.branch_id = b.id
                JOIN users creator ON pa.created_by = creator.id
                WHERE pa.id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approver: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * جلب جميع المعتمدين مع فلاتر
     */
    public function getAll($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $sql = "
                SELECT pa.*, u.username, u.full_name, u.email, u.status as user_status,
                       b.name as branch_name, creator.full_name as created_by_name,
                       CASE 
                           WHEN pa.effective_to IS NULL OR pa.effective_to >= CURDATE() THEN 'نشط'
                           ELSE 'منتهي الصلاحية'
                       END as status_text
                FROM productivity_approvers pa
                JOIN users u ON pa.user_id = u.id
                LEFT JOIN branches b ON pa.branch_id = b.id
                JOIN users creator ON pa.created_by = creator.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // إضافة فلاتر
            if (!empty($filters['user_id'])) {
                $sql .= " AND pa.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['branch_id'])) {
                $sql .= " AND pa.branch_id = ?";
                $params[] = $filters['branch_id'];
            }
            
            if (!empty($filters['department'])) {
                $sql .= " AND pa.department = ?";
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['approval_level'])) {
                $sql .= " AND pa.approval_level = ?";
                $params[] = $filters['approval_level'];
            }
            
            if (isset($filters['is_active'])) {
                $sql .= " AND pa.is_active = ?";
                $params[] = $filters['is_active'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // فلتر الصلاحيات النشطة فقط
            if (!empty($filters['active_only'])) {
                $sql .= " AND pa.is_active = 1 AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')";
            }
            
            $sql .= " ORDER BY pa.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting all approvers: " . $e->getMessage());
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
                FROM productivity_approvers pa
                JOIN users u ON pa.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // إضافة نفس الفلاتر
            if (!empty($filters['user_id'])) {
                $sql .= " AND pa.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['branch_id'])) {
                $sql .= " AND pa.branch_id = ?";
                $params[] = $filters['branch_id'];
            }
            
            if (!empty($filters['department'])) {
                $sql .= " AND pa.department = ?";
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['approval_level'])) {
                $sql .= " AND pa.approval_level = ?";
                $params[] = $filters['approval_level'];
            }
            
            if (isset($filters['is_active'])) {
                $sql .= " AND pa.is_active = ?";
                $params[] = $filters['is_active'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($filters['active_only'])) {
                $sql .= " AND pa.is_active = 1 AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error getting approvers count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * حذف معتمد
     */
    public function delete($id, $userId)
    {
        try {
            // التحقق من وجود اعتمادات مرتبطة
            $approvalsCount = $this->db->prepare("
                SELECT COUNT(*) FROM productivity_approvals WHERE approver_id = (
                    SELECT user_id FROM productivity_approvers WHERE id = ?
                )
            ");
            $approvalsCount->execute([$id]);
            
            if ($approvalsCount->fetchColumn() > 0) {
                throw new Exception('لا يمكن حذف المعتمد لوجود اعتمادات مرتبطة به');
            }
            
            // جلب البيانات للمراجعة
            $oldData = $this->getById($id);
            if (!$oldData) {
                return false;
            }
            
            $sql = "DELETE FROM productivity_approvers WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                $this->logAudit('delete', $id, $oldData, null, $userId);
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error deleting approver: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * تفعيل/إلغاء تفعيل معتمد
     */
    public function toggleStatus($id, $userId)
    {
        try {
            $oldData = $this->getById($id);
            if (!$oldData) {
                return false;
            }

            $newStatus = !$oldData['is_active'];

            $sql = "UPDATE productivity_approvers SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$newStatus, $id]);

            if ($result) {
                $this->logAudit('update', $id, ['is_active' => $oldData['is_active']], ['is_active' => $newStatus], $userId);
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error toggling approver status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من التعيين المكرر
     */
    private function isDuplicateAssignment($data)
    {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM productivity_approvers
                WHERE user_id = ?
                AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))
                AND department = ?
                AND approval_level = ?
                AND is_active = 1
                AND CURDATE() BETWEEN effective_from AND COALESCE(effective_to, '9999-12-31')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['user_id'],
                $data['branch_id'] ?? null,
                $data['branch_id'] ?? null,
                $data['department'] ?? 'all',
                $data['approval_level']
            ]);

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error checking duplicate assignment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب المستخدمين المؤهلين ليكونوا معتمدين
     */
    public function getEligibleUsers($filters = [])
    {
        try {
            $sql = "
                SELECT u.id, u.username, u.full_name, u.email,
                       r.name as role_name, b.name as branch_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.status = 'active'
            ";

            $params = [];

            if (!empty($filters['branch_id'])) {
                $sql .= " AND u.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            if (!empty($filters['role_id'])) {
                $sql .= " AND u.role_id = ?";
                $params[] = $filters['role_id'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY u.full_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting eligible users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * جلب إحصائيات المعتمدين
     */
    public function getApproversStatistics($filters = [])
    {
        try {
            $sql = "
                SELECT
                    COUNT(*) as total_approvers,
                    SUM(CASE WHEN pa.is_active = 1 THEN 1 ELSE 0 END) as active_approvers,
                    SUM(CASE WHEN pa.approval_level = 'supervisor' THEN 1 ELSE 0 END) as supervisors,
                    SUM(CASE WHEN pa.approval_level = 'manager' THEN 1 ELSE 0 END) as managers,
                    SUM(CASE WHEN pa.approval_level = 'director' THEN 1 ELSE 0 END) as directors,
                    SUM(CASE WHEN pa.approval_level = 'general_manager' THEN 1 ELSE 0 END) as general_managers,
                    COUNT(DISTINCT pa.user_id) as unique_users
                FROM productivity_approvers pa
                JOIN users u ON pa.user_id = u.id
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['branch_id'])) {
                $sql .= " AND pa.branch_id = ?";
                $params[] = $filters['branch_id'];
            }

            if (!empty($filters['department'])) {
                $sql .= " AND pa.department = ?";
                $params[] = $filters['department'];
            }

            if (!empty($filters['active_only'])) {
                $sql .= " AND pa.is_active = 1 AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approvers statistics: " . $e->getMessage());
            return [];
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
                'productivity_approvers',
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
            if (empty($data['user_id'])) {
                $errors[] = 'المستخدم مطلوب';
            }
        }

        if (empty($data['approval_level'])) {
            $errors[] = 'مستوى الاعتماد مطلوب';
        } elseif (!in_array($data['approval_level'], ['supervisor', 'manager', 'director', 'general_manager'])) {
            $errors[] = 'مستوى الاعتماد غير صحيح';
        }

        if (empty($data['department'])) {
            $errors[] = 'القسم مطلوب';
        } elseif (!in_array($data['department'], ['connections', 'projects', 'all'])) {
            $errors[] = 'القسم غير صحيح';
        }

        if (empty($data['effective_from'])) {
            $errors[] = 'تاريخ بداية الصلاحية مطلوب';
        }

        if (!empty($data['effective_to']) && !empty($data['effective_from'])) {
            if (strtotime($data['effective_from']) > strtotime($data['effective_to'])) {
                $errors[] = 'تاريخ بداية الصلاحية يجب أن يكون قبل تاريخ انتهاء الصلاحية';
            }
        }

        if (!empty($data['max_amount_limit']) && $data['max_amount_limit'] < 0) {
            $errors[] = 'الحد الأقصى للقيمة يجب أن يكون صفر أو أكبر';
        }

        return $errors;
    }

    /**
     * جلب المعتمدين حسب المستخدم
     */
    public function getByUserId($userId)
    {
        try {
            $sql = "
                SELECT pa.*, b.name as branch_name
                FROM productivity_approvers pa
                LEFT JOIN branches b ON pa.branch_id = b.id
                WHERE pa.user_id = ? AND pa.is_active = 1
                AND CURDATE() BETWEEN pa.effective_from AND COALESCE(pa.effective_to, '9999-12-31')
                ORDER BY
                    CASE pa.approval_level
                        WHEN 'general_manager' THEN 4
                        WHEN 'director' THEN 3
                        WHEN 'manager' THEN 2
                        WHEN 'supervisor' THEN 1
                    END DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting approvers by user: " . $e->getMessage());
            return [];
        }
    }
}
