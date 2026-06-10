<?php
/**
 * نموذج أوامر العمل
 * Work Order Model
 */

require_once __DIR__ . '/BaseModel.php';

class WorkOrder extends BaseModel {
    protected $table = 'work_orders';
    
    /**
     * الحصول على أوامر العمل النشطة
     */
    public function getActiveWorkOrders($branchId = null) {
        $whereConditions = ["wo.status IN ('active', 'completed')"];
        $params = [];
        
        if ($branchId) {
            $whereConditions[] = 'wo.branch_id = ?';
            $params[] = $branchId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->fetchAll(
            "SELECT wo.*, 
                    wot.type_code, wot.description as work_order_type_description,
                    b.name as branch_name, b.code as branch_code
             FROM work_orders wo
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN branches b ON wo.branch_id = b.id
             {$whereClause}
             ORDER BY wo.work_order_number DESC",
            $params
        );
    }
    
    /**
     * الحصول على أوامر العمل المتاحة للصرف
     */
    public function getWorkOrdersForMaterialRequest($branchId = null) {
        $whereConditions = [
            "wo.status = 'active'",
            "wo.disbursement_status IN ('none', 'partial_disbursement', 'pending_disbursement')"
        ];
        $params = [];
        
        if ($branchId) {
            $whereConditions[] = 'wo.branch_id = ?';
            $params[] = $branchId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->fetchAll(
            "SELECT wo.id, wo.work_order_number, wo.estimated_value, wo.actual_value,
                    wo.disbursement_status, wo.assignment_date, wo.receipt_date,
                    wot.type_code, wot.description as work_order_type_description,
                    b.name as branch_name, b.code as branch_code,
                    wo.notes
             FROM work_orders wo
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN branches b ON wo.branch_id = b.id
             {$whereClause}
             ORDER BY wo.assignment_date DESC, wo.work_order_number DESC",
            $params
        );
    }
    
    /**
     * البحث في أوامر العمل
     */
    public function searchWorkOrders($searchTerm, $branchId = null) {
        $whereConditions = [
            "(wo.work_order_number LIKE ? OR wo.notes LIKE ? OR wot.description LIKE ?)"
        ];
        $searchPattern = "%{$searchTerm}%";
        $params = [$searchPattern, $searchPattern, $searchPattern];
        
        if ($branchId) {
            $whereConditions[] = 'wo.branch_id = ?';
            $params[] = $branchId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->fetchAll(
            "SELECT wo.id, wo.work_order_number, wo.estimated_value,
                    wot.type_code, wot.description as work_order_type_description,
                    b.name as branch_name, b.code as branch_code,
                    wo.assignment_date, wo.disbursement_status
             FROM work_orders wo
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN branches b ON wo.branch_id = b.id
             {$whereClause}
             AND wo.status = 'active'
             ORDER BY wo.work_order_number DESC
             LIMIT 20",
            $params
        );
    }
    
    /**
     * الحصول على أمر عمل بالتفاصيل
     */
    public function getWorkOrderWithDetails($workOrderId) {
        return $this->fetchOne(
            "SELECT wo.*, 
                    wot.type_code, wot.description as work_order_type_description,
                    b.name as branch_name, b.code as branch_code,
                    ce.name as current_entity_name
             FROM work_orders wo
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN branches b ON wo.branch_id = b.id
             LEFT JOIN current_entities ce ON wo.current_entity_id = ce.id
             WHERE wo.id = ?",
            [$workOrderId]
        );
    }
    
    /**
     * تحديث حالة الصرف لأمر العمل
     */
    public function updateDisbursementStatus($workOrderId, $status) {
        return $this->update($workOrderId, [
            'disbursement_status' => $status,
            'updated_at' => getCurrentDateTime()
        ]);
    }
    
    /**
     * الحصول على إحصائيات أوامر العمل
     */
    public function getWorkOrderStats($branchId = null) {
        $whereConditions = ['1=1'];
        $params = [];
        
        if ($branchId) {
            $whereConditions[] = 'branch_id = ?';
            $params[] = $branchId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $stats = $this->fetchOne(
            "SELECT 
                COUNT(*) as total_work_orders,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_work_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_work_orders,
                SUM(CASE WHEN disbursement_status = 'none' THEN 1 ELSE 0 END) as no_disbursement,
                SUM(CASE WHEN disbursement_status = 'pending_disbursement' THEN 1 ELSE 0 END) as pending_disbursement,
                SUM(CASE WHEN disbursement_status = 'partial_disbursement' THEN 1 ELSE 0 END) as partial_disbursement,
                SUM(CASE WHEN disbursement_status = 'completed' THEN 1 ELSE 0 END) as completed_disbursement,
                SUM(estimated_value) as total_estimated_value,
                SUM(actual_value) as total_actual_value
             FROM work_orders {$whereClause}",
            $params
        );
        
        return [
            'total_work_orders' => (int)($stats['total_work_orders'] ?? 0),
            'active_work_orders' => (int)($stats['active_work_orders'] ?? 0),
            'completed_work_orders' => (int)($stats['completed_work_orders'] ?? 0),
            'no_disbursement' => (int)($stats['no_disbursement'] ?? 0),
            'pending_disbursement' => (int)($stats['pending_disbursement'] ?? 0),
            'partial_disbursement' => (int)($stats['partial_disbursement'] ?? 0),
            'completed_disbursement' => (int)($stats['completed_disbursement'] ?? 0),
            'total_estimated_value' => (float)($stats['total_estimated_value'] ?? 0),
            'total_actual_value' => (float)($stats['total_actual_value'] ?? 0)
        ];
    }
    
    /**
     * الحصول على أوامر العمل حسب القسم
     */
    public function getWorkOrdersByDepartment($department, $branchId = null) {
        $whereConditions = ['wo.department = ?'];
        $params = [$department];
        
        if ($branchId) {
            $whereConditions[] = 'wo.branch_id = ?';
            $params[] = $branchId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->fetchAll(
            "SELECT wo.*, 
                    wot.type_code, wot.description as work_order_type_description,
                    b.name as branch_name, b.code as branch_code
             FROM work_orders wo
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN branches b ON wo.branch_id = b.id
             {$whereClause}
             AND wo.status = 'active'
             ORDER BY wo.assignment_date DESC, wo.work_order_number DESC",
            $params
        );
    }
    
    /**
     * الحصول على أوامر العمل المرتبطة بطلبات الصرف
     */
    public function getWorkOrdersWithMaterialRequests($branchId = null) {
        $whereConditions = ['1=1'];
        $params = [];
        
        if ($branchId) {
            $whereConditions[] = 'wo.branch_id = ?';
            $params[] = $branchId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        return $this->fetchAll(
            "SELECT wo.*, 
                    wot.type_code, wot.description as work_order_type_description,
                    b.name as branch_name, b.code as branch_code,
                    COUNT(mr.id) as material_requests_count,
                    SUM(CASE WHEN mr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN mr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests
             FROM work_orders wo
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN branches b ON wo.branch_id = b.id
             LEFT JOIN material_requests mr ON wo.id = mr.work_order_id
             {$whereClause}
             GROUP BY wo.id
             ORDER BY wo.assignment_date DESC, wo.work_order_number DESC",
            $params
        );
    }
    
    /**
     * التحقق من إمكانية ربط طلب صرف بأمر العمل
     */
    public function canCreateMaterialRequest($workOrderId, $excludeRequestId = null) {
        $workOrder = $this->findById($workOrderId);

        if (!$workOrder) {
            return ['can_create' => false, 'reason' => 'أمر العمل غير موجود'];
        }

        if ($workOrder['status'] !== 'active') {
            return ['can_create' => false, 'reason' => 'أمر العمل غير نشط'];
        }

        if ($workOrder['disbursement_status'] === 'completed') {
            return ['can_create' => false, 'reason' => 'تم اكتمال صرف المواد لهذا الأمر'];
        }

        return ['can_create' => true, 'reason' => ''];
    }
}
?>
