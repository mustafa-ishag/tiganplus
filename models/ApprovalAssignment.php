<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * نموذج تعيين المعتمدين - نظام ديناميكي
 * Approval Assignment Model - Dynamic System
 */
class ApprovalAssignment extends BaseModel {
    
    protected $table = 'approval_assignments';

    // =====================================================
    // دوال إدارة خطوات الاعتماد (Approval Steps)
    // =====================================================

    /**
     * الحصول على جميع خطوات الاعتماد مرتبة
     */
    public function getAllSteps($activeOnly = false) {
        try {
            $condition = $activeOnly ? "WHERE is_active = 1" : "";
            return $this->fetchAll(
                "SELECT * FROM approval_steps $condition ORDER BY step_order ASC"
            );
        } catch (Exception $e) {
            error_log("Error in getAllSteps: " . $e->getMessage());
            return [];
        }
    }

    /**
     * الحصول على خطوة بالمعرف
     */
    public function getStepById($stepId) {
        try {
            return $this->fetchOne(
                "SELECT * FROM approval_steps WHERE id = ?",
                [$stepId]
            );
        } catch (Exception $e) {
            error_log("Error in getStepById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على خطوة بالمفتاح
     */
    public function getStepByKey($stepKey) {
        try {
            return $this->fetchOne(
                "SELECT * FROM approval_steps WHERE step_key = ?",
                [$stepKey]
            );
        } catch (Exception $e) {
            error_log("Error in getStepByKey: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على أول خطوة فعالة
     */
    public function getFirstActiveStep() {
        try {
            return $this->fetchOne(
                "SELECT * FROM approval_steps WHERE is_active = 1 ORDER BY step_order ASC LIMIT 1"
            );
        } catch (Exception $e) {
            error_log("Error in getFirstActiveStep: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على الخطوة التالية بعد خطوة معينة
     */
    public function getNextStep($currentStepId) {
        try {
            $currentStep = $this->getStepById($currentStepId);
            if (!$currentStep) return null;

            return $this->fetchOne(
                "SELECT * FROM approval_steps 
                 WHERE step_order > ? AND is_active = 1 
                 ORDER BY step_order ASC LIMIT 1",
                [$currentStep['step_order']]
            );
        } catch (Exception $e) {
            error_log("Error in getNextStep: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على الخطوة النهائية
     */
    public function getFinalStep() {
        try {
            // أولاً نبحث عن خطوة مؤشرة كنهائية
            $step = $this->fetchOne(
                "SELECT * FROM approval_steps WHERE is_final = 1 AND is_active = 1 LIMIT 1"
            );
            if ($step) return $step;

            // إذا لم توجد، نأخذ آخر خطوة فعالة
            return $this->fetchOne(
                "SELECT * FROM approval_steps WHERE is_active = 1 ORDER BY step_order DESC LIMIT 1"
            );
        } catch (Exception $e) {
            error_log("Error in getFinalStep: " . $e->getMessage());
            return null;
        }
    }

    /**
     * الحصول على عدد الخطوات الفعالة
     */
    public function getActiveStepsCount() {
        try {
            return (int) $this->fetchColumn(
                "SELECT COUNT(*) FROM approval_steps WHERE is_active = 1"
            );
        } catch (Exception $e) {
            error_log("Error in getActiveStepsCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * إضافة خطوة اعتماد جديدة
     */
    public function addStep($stepName, $stepKey, $description = '', $isFinal = false) {
        try {
            // الحصول على أعلى ترتيب حالي
            $maxOrder = (int) $this->fetchColumn(
                "SELECT MAX(step_order) FROM approval_steps"
            );

            // إذا كانت الخطوة نهائية، نزيل علامة النهائية من الخطوات الأخرى
            if ($isFinal) {
                $this->query("UPDATE approval_steps SET is_final = 0");
            }

            $this->query(
                "INSERT INTO approval_steps (step_order, step_name, step_key, description, is_active, is_final) 
                 VALUES (?, ?, ?, ?, 1, ?)",
                [$maxOrder + 1, $stepName, $stepKey, $description, $isFinal ? 1 : 0]
            );

            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in addStep: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث خطوة اعتماد
     */
    public function updateStep($stepId, $data) {
        try {
            // إذا كانت الخطوة ستصبح نهائية
            if (isset($data['is_final']) && $data['is_final']) {
                $this->query("UPDATE approval_steps SET is_final = 0 WHERE id != ?", [$stepId]);
            }

            $sets = [];
            $params = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['step_name', 'step_key', 'description', 'is_active', 'is_final', 'step_order'])) {
                    $sets[] = "$key = ?";
                    $params[] = $value;
                }
            }

            if (empty($sets)) return false;

            $params[] = $stepId;
            return $this->query(
                "UPDATE approval_steps SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?",
                $params
            );
        } catch (Exception $e) {
            error_log("Error in updateStep: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف خطوة اعتماد
     */
    public function deleteStep($stepId) {
        try {
            // التحقق من عدم وجود تعيينات مرتبطة
            $count = (int) $this->fetchColumn(
                "SELECT COUNT(*) FROM approval_assignments WHERE step_id = ?",
                [$stepId]
            );
            if ($count > 0) {
                return ['success' => false, 'message' => 'لا يمكن حذف الخطوة لوجود تعيينات مرتبطة بها. قم بحذف التعيينات أولاً.'];
            }

            // التحقق من عدم وجود سجلات اعتماد
            $logCount = (int) $this->fetchColumn(
                "SELECT COUNT(*) FROM request_approval_logs WHERE step_id = ?",
                [$stepId]
            );
            if ($logCount > 0) {
                return ['success' => false, 'message' => 'لا يمكن حذف الخطوة لوجود سجلات اعتماد مرتبطة بها.'];
            }

            $this->query("DELETE FROM approval_steps WHERE id = ?", [$stepId]);

            // إعادة ترقيم الخطوات
            $this->reorderSteps();

            return ['success' => true];
        } catch (Exception $e) {
            error_log("Error in deleteStep: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * إعادة ترتيب الخطوات
     */
    public function reorderSteps() {
        try {
            $steps = $this->fetchAll(
                "SELECT id FROM approval_steps ORDER BY step_order ASC"
            );
            foreach ($steps as $index => $step) {
                $this->query(
                    "UPDATE approval_steps SET step_order = ? WHERE id = ?",
                    [$index + 1, $step['id']]
                );
            }
        } catch (Exception $e) {
            error_log("Error in reorderSteps: " . $e->getMessage());
        }
    }

    /**
     * تفعيل/إلغاء تفعيل خطوة
     */
    public function toggleStep($stepId, $isActive) {
        try {
            return $this->query(
                "UPDATE approval_steps SET is_active = ?, updated_at = NOW() WHERE id = ?",
                [$isActive ? 1 : 0, $stepId]
            );
        } catch (Exception $e) {
            error_log("Error in toggleStep: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // دوال إدارة تعيينات المعتمدين (Assignments)
    // =====================================================

    /**
     * الحصول على المعتمدين لخطوة محددة
     */
    public function getApproversForStep($stepId, $branchId = null, $workOrderId = null) {
        try {
            $conditions = ["aa.step_id = ?", "aa.is_active = 1"];
            $params = [$stepId];

            $orderClauses = [];

            if ($workOrderId) {
                $orderClauses[] = "CASE WHEN aa.scope_type = 'work_order' AND aa.scope_id = $workOrderId THEN 1 ELSE 4 END";
            }

            if ($branchId) {
                $orderClauses[] = "CASE WHEN aa.scope_type = 'branch' AND aa.scope_id = $branchId THEN 2 ELSE 4 END";
            }

            $orderClauses[] = "CASE WHEN aa.scope_type = 'global' THEN 3 ELSE 4 END";
            $orderClauses[] = "aa.priority ASC";

            $sql = "
                SELECT aa.*, u.full_name as approver_name, u.username as approver_username,
                       u.email as approver_email, u.phone as approver_phone,
                       ast.step_name, ast.step_key
                FROM approval_assignments aa
                INNER JOIN users u ON aa.approver_user_id = u.id
                INNER JOIN approval_steps ast ON aa.step_id = ast.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY " . implode(', ', $orderClauses) . "
            ";

            return $this->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error in getApproversForStep: " . $e->getMessage());
            return [];
        }
    }

    /**
     * التحقق من صلاحية المستخدم للموافقة على خطوة معينة
     */
    public function canUserApproveStep($userId, $stepId, $branchId = null, $workOrderId = null) {
        try {
            $approvers = $this->getApproversForStep($stepId, $branchId, $workOrderId);
            foreach ($approvers as $approver) {
                if ($approver['approver_user_id'] == $userId) {
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Error in canUserApproveStep: " . $e->getMessage());
            return false;
        }
    }

    /**
     * التحقق من صلاحية المستخدم للموافقة (بواسطة step_key للتوافق)
     */
    public function canUserApprove($userId, $approvalType, $branchId = null, $workOrderId = null) {
        try {
            $step = $this->getStepByKey($approvalType);
            if (!$step) return false;
            return $this->canUserApproveStep($userId, $step['id'], $branchId, $workOrderId);
        } catch (Exception $e) {
            error_log("Error in canUserApprove: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * إضافة تعيين معتمد جديد
     */
    public function addAssignment($stepId, $approverUserId, $scopeType = 'global', $scopeId = null, $assignedBy = null, $notes = '', $priority = 1) {
        try {
            // الحصول على step_key للتوافقية
            $step = $this->getStepById($stepId);
            $approvalType = $step ? $step['step_key'] : '';

            $data = [
                'approval_type' => $approvalType,
                'step_id' => $stepId,
                'approver_user_id' => $approverUserId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'priority' => $priority,
                'assigned_by' => $assignedBy ?: $_SESSION['user_id'],
                'notes' => $notes
            ];
            
            return $this->insert($data);
        } catch (Exception $e) {
            error_log("Error in addAssignment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * جلب تعيين معتمد بالمعرف
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT aa.*,
                       u.full_name as approver_name, u.username as approver_username,
                       u2.full_name as assigned_by_name,
                       ast.step_name, ast.step_key, ast.step_order,
                       CASE
                           WHEN aa.scope_type = 'branch' THEN b.name
                           WHEN aa.scope_type = 'work_order' THEN wo.work_order_number
                           ELSE 'عام'
                       END as scope_name
                FROM approval_assignments aa
                INNER JOIN users u ON aa.approver_user_id = u.id
                LEFT JOIN users u2 ON aa.assigned_by = u2.id
                LEFT JOIN approval_steps ast ON aa.step_id = ast.id
                LEFT JOIN branches b ON aa.scope_type = 'branch' AND aa.scope_id = b.id
                LEFT JOIN work_orders wo ON aa.scope_type = 'work_order' AND aa.scope_id = wo.id
                WHERE aa.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * تحديث تعيين معتمد
     */
    public function updateAssignment($id, $stepId, $approverUserId, $scopeType, $scopeId, $notes, $priority) {
        try {
            // الحصول على step_key
            $step = $this->getStepById($stepId);
            $approvalType = $step ? $step['step_key'] : '';

            $stmt = $this->db->prepare("
                UPDATE approval_assignments
                SET approval_type = ?,
                    step_id = ?,
                    approver_user_id = ?,
                    scope_type = ?,
                    scope_id = ?,
                    notes = ?,
                    priority = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            return $stmt->execute([
                $approvalType,
                $stepId,
                $approverUserId,
                $scopeType,
                $scopeId,
                $notes,
                $priority,
                $id
            ]);
        } catch (Exception $e) {
            error_log("Error in updateAssignment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف تعيين معتمد
     */
    public function removeAssignment($id) {
        try {
            return $this->delete($id);
        } catch (Exception $e) {
            error_log("Error in removeAssignment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * تفعيل/إلغاء تفعيل تعيين
     */
    public function toggleAssignment($id, $isActive) {
        try {
            return $this->update($id, ['is_active' => $isActive ? 1 : 0]);
        } catch (Exception $e) {
            error_log("Error in toggleAssignment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على جميع التعيينات مع تفاصيل المستخدمين
     */
    public function getAllAssignments($filters = []) {
        try {
            $conditions = [];
            $params = [];
            
            if (!empty($filters['step_id'])) {
                $conditions[] = "aa.step_id = ?";
                $params[] = $filters['step_id'];
            }
            
            if (!empty($filters['scope_type'])) {
                $conditions[] = "aa.scope_type = ?";
                $params[] = $filters['scope_type'];
            }
            
            if (isset($filters['is_active'])) {
                $conditions[] = "aa.is_active = ?";
                $params[] = $filters['is_active'] ? 1 : 0;
            }
            
            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            
            $sql = "
                SELECT aa.*, 
                       u.full_name as approver_name, u.username as approver_username,
                       u2.full_name as assigned_by_name,
                       ast.step_name, ast.step_key, ast.step_order,
                       CASE 
                           WHEN aa.scope_type = 'branch' THEN b.name
                           WHEN aa.scope_type = 'work_order' THEN wo.work_order_number
                           ELSE 'عام'
                       END as scope_name
                FROM approval_assignments aa
                INNER JOIN users u ON aa.approver_user_id = u.id
                LEFT JOIN users u2 ON aa.assigned_by = u2.id
                LEFT JOIN approval_steps ast ON aa.step_id = ast.id
                LEFT JOIN branches b ON aa.scope_type = 'branch' AND aa.scope_id = b.id
                LEFT JOIN work_orders wo ON aa.scope_type = 'work_order' AND aa.scope_id = wo.id
                $whereClause
                ORDER BY ast.step_order, aa.scope_type, aa.priority
            ";
            
            return $this->fetchAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error in getAllAssignments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * الحصول على إحصائيات التعيينات
     */
    public function getAssignmentStats() {
        try {
            $sql = "
                SELECT 
                    ast.step_name,
                    ast.step_key,
                    ast.step_order,
                    aa.scope_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN aa.is_active = 1 THEN 1 ELSE 0 END) as active
                FROM approval_assignments aa
                INNER JOIN approval_steps ast ON aa.step_id = ast.id
                GROUP BY ast.id, aa.scope_type
                ORDER BY ast.step_order, aa.scope_type
            ";
            
            return $this->fetchAll($sql);
        } catch (Exception $e) {
            error_log("Error in getAssignmentStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * التحقق من وجود تعيين مكرر
     */
    public function isDuplicateAssignment($stepId, $approverUserId, $scopeType, $scopeId, $excludeId = null) {
        try {
            $conditions = [
                "step_id = ?",
                "approver_user_id = ?",
                "scope_type = ?",
                "scope_id " . ($scopeId ? "= ?" : "IS NULL")
            ];
            
            $params = [$stepId, $approverUserId, $scopeType];
            if ($scopeId) {
                $params[] = $scopeId;
            }
            
            if ($excludeId) {
                $conditions[] = "id != ?";
                $params[] = $excludeId;
            }
            
            $sql = "SELECT COUNT(*) FROM approval_assignments WHERE " . implode(' AND ', $conditions);
            
            return $this->fetchColumn($sql, $params) > 0;
        } catch (Exception $e) {
            error_log("Error in isDuplicateAssignment: " . $e->getMessage());
            return false;
        }
    }

    // =====================================================
    // دوال سجل الاعتمادات (Approval Logs)
    // =====================================================

    /**
     * تسجيل عملية اعتماد
     */
    public function logApproval($requestId, $stepId, $action, $approvedBy, $notes = '') {
        try {
            $this->query(
                "INSERT INTO request_approval_logs (request_id, step_id, action, approved_by, notes) 
                 VALUES (?, ?, ?, ?, ?)",
                [$requestId, $stepId, $action, $approvedBy, $notes]
            );
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Error in logApproval: " . $e->getMessage());
            return false;
        }
    }

    /**
     * الحصول على سجل الاعتمادات لطلب
     */
    public function getApprovalLogs($requestId) {
        try {
            return $this->fetchAll(
                "SELECT ral.*, 
                        ast.step_name, ast.step_key, ast.step_order,
                        u.full_name as approver_name
                 FROM request_approval_logs ral
                 INNER JOIN approval_steps ast ON ral.step_id = ast.id
                 INNER JOIN users u ON ral.approved_by = u.id
                 WHERE ral.request_id = ?
                 ORDER BY ral.created_at ASC",
                [$requestId]
            );
        } catch (Exception $e) {
            error_log("Error in getApprovalLogs: " . $e->getMessage());
            return [];
        }
    }
}
