<?php
/**
 * نموذج شهادات الإنجاز
 * Completion Certificate Model
 */

require_once __DIR__ . '/BaseModel.php';

class CompletionCertificate extends BaseModel {
    protected $table = 'completion_certificates';
    
    /**
     * البحث عن شهادة بواسطة رقم الشهادة
     */
    public function findByCertificateNumber($certificateNumber) {
        return $this->findOneWhere('certificate_number = ?', [$certificateNumber]);
    }
    
    /**
     * الحصول على الشهادات بواسطة أمر العمل
     */
    public function findByWorkOrder($workOrderId) {
        return $this->fetchAll(
            "SELECT * FROM {$this->table} WHERE work_order_id = ? ORDER BY certificate_date DESC",
            [$workOrderId]
        );
    }
    
    /**
     * الحصول على الشهادات بواسطة الحالة
     */
    public function findByStatus($status, $branchId = null) {
        $condition = 'status = ?';
        $params = [$status];
        
        if ($branchId) {
            $condition .= ' AND branch_id = ?';
            $params[] = $branchId;
        }
        
        return $this->fetchAll(
            "SELECT * FROM {$this->table} WHERE {$condition} ORDER BY certificate_date DESC",
            $params
        );
    }
    
    /**
     * إنشاء شهادة إنجاز جديدة
     */
    public function createCertificate($data, $materials = [], $works = []) {
        try {
            $this->beginTransaction();
            
            // توليد رقم الشهادة
            $data['certificate_number'] = $this->generateCertificateNumber();
            $data['status'] = 'draft';
            $data['created_at'] = getCurrentDateTime();
            $data['updated_at'] = getCurrentDateTime();
            
            // إدراج الشهادة الرئيسية
            $certificateId = $this->insert($data);
            
            // إدراج المواد المستخدمة
            if (!empty($materials)) {
                $materialsResult = $this->insertCertificateMaterials($certificateId, $materials);
                if (!$materialsResult['success']) {
                    $this->rollback();
                    return $materialsResult;
                }
            }
            
            // إدراج الأعمال المنجزة
            if (!empty($works)) {
                $worksResult = $this->insertCertificateWorks($certificateId, $works);
                if (!$worksResult['success']) {
                    $this->rollback();
                    return $worksResult;
                }
            }
            
            // حساب القيم الإجمالية
            $this->calculateTotalValues($certificateId);
            
            $this->commit();
            logActivity('create_completion_certificate', "تم إنشاء شهادة إنجاز جديدة: {$data['certificate_number']}");
            
            return ['success' => true, 'certificate_id' => $certificateId, 'certificate_number' => $data['certificate_number']];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في إنشاء الشهادة: ' . $e->getMessage()];
        }
    }
    
    /**
     * إدراج المواد المستخدمة في الشهادة
     */
    private function insertCertificateMaterials($certificateId, $materials) {
        try {
            $materialModel = new Material();
            
            foreach ($materials as $material) {
                // التحقق من توفر المادة
                $materialData = $materialModel->findById($material['material_id']);
                if (!$materialData) {
                    return ['success' => false, 'message' => "المادة غير موجودة: {$material['material_id']}"];
                }
                
                // إدراج المادة
                $materialRecord = [
                    'certificate_id' => $certificateId,
                    'material_id' => $material['material_id'],
                    'quantity_used' => $material['quantity_used'],
                    'notes' => $material['notes'] ?? '',
                    'created_at' => getCurrentDateTime(),
                    'updated_at' => getCurrentDateTime()
                ];
                
                $sql = "INSERT INTO completion_certificate_materials (certificate_id, material_id, quantity_used, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)";
                $this->query($sql, array_values($materialRecord));
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إدراج مواد الشهادة: ' . $e->getMessage()];
        }
    }
    
    /**
     * إدراج الأعمال المنجزة في الشهادة
     */
    private function insertCertificateWorks($certificateId, $works) {
        try {
            foreach ($works as $work) {
                // حساب القيمة
                $totalValue = $work['quantity_completed'] * $work['unit_price'];
                
                // إدراج العمل
                $workRecord = [
                    'certificate_id' => $certificateId,
                    'work_description' => $work['work_description'],
                    'unit' => $work['unit'],
                    'quantity_completed' => $work['quantity_completed'],
                    'unit_price' => $work['unit_price'],
                    'total_value' => $totalValue,
                    'notes' => $work['notes'] ?? '',
                    'created_at' => getCurrentDateTime(),
                    'updated_at' => getCurrentDateTime()
                ];
                
                $sql = "INSERT INTO completion_certificate_works (certificate_id, work_description, unit, quantity_completed, unit_price, total_value, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $this->query($sql, array_values($workRecord));
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إدراج أعمال الشهادة: ' . $e->getMessage()];
        }
    }
    
    /**
     * توليد رقم الشهادة
     */
    private function generateCertificateNumber() {
        $date = date('Ymd');
        
        // البحث عن آخر رقم لنفس اليوم
        $lastNumber = $this->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(certificate_number, -4) AS UNSIGNED)) 
             FROM completion_certificates 
             WHERE DATE(created_at) = CURDATE()"
        ) ?: 0;
        
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        return "CERT-{$date}-{$newNumber}";
    }
    
    /**
     * حساب القيم الإجمالية للشهادة
     */
    private function calculateTotalValues($certificateId) {
        // حساب إجمالي كميات المواد
        $totalMaterialsCount = $this->fetchColumn(
            "SELECT COUNT(*) FROM completion_certificate_materials WHERE certificate_id = ?",
            [$certificateId]
        ) ?: 0;
        
        // حساب إجمالي قيمة الأعمال
        $totalWorksValue = $this->fetchColumn(
            "SELECT SUM(total_value) FROM completion_certificate_works WHERE certificate_id = ?",
            [$certificateId]
        ) ?: 0;
        
        // تحديث الشهادة
        $this->update($certificateId, [
            'total_materials_value' => 0,
            'total_works_value' => $totalWorksValue,
            'total_certificate_value' => $totalWorksValue
        ]);
    }
    
    /**
     * اعتماد الشهادة
     */
    public function approveCertificate($certificateId, $approvedBy) {
        try {
            $this->beginTransaction();
            
            $certificate = $this->findById($certificateId);
            if (!$certificate) {
                $this->rollback();
                return ['success' => false, 'message' => 'الشهادة غير موجودة'];
            }
            
            if ($certificate['status'] !== 'draft') {
                $this->rollback();
                return ['success' => false, 'message' => 'الشهادة ليست في حالة مسودة'];
            }
            
            // تحديث حالة الشهادة
            $result = $this->update($certificateId, [
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => getCurrentDateTime(),
                'updated_at' => getCurrentDateTime()
            ]);
            
            if ($result) {
                // إنشاء علاقات المواد والأعمال
                $this->createMaterialWorkRelations($certificateId);
                
                $this->commit();
                logActivity('approve_completion_certificate', "تم اعتماد شهادة الإنجاز: {$certificate['certificate_number']}");
                return ['success' => true];
            }
            
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في اعتماد الشهادة'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في اعتماد الشهادة: ' . $e->getMessage()];
        }
    }
    
    /**
     * إنشاء علاقات المواد والأعمال
     */
    private function createMaterialWorkRelations($certificateId) {
        // الحصول على المواد والأعمال من الشهادة
        $materials = $this->fetchAll(
            "SELECT material_id, quantity_used FROM completion_certificate_materials WHERE certificate_id = ?",
            [$certificateId]
        );
        
        $works = $this->fetchAll(
            "SELECT id as work_id, quantity_completed FROM completion_certificate_works WHERE certificate_id = ?",
            [$certificateId]
        );
        
        // إنشاء العلاقات (توزيع المواد على الأعمال)
        foreach ($materials as $material) {
            foreach ($works as $work) {
                // توزيع نسبي للمواد على الأعمال
                $allocatedQuantity = ($material['quantity_used'] * $work['quantity_completed']) / array_sum(array_column($works, 'quantity_completed'));
                
                $relationData = [
                    'certificate_id' => $certificateId,
                    'material_id' => $material['material_id'],
                    'work_id' => $work['work_id'],
                    'allocated_quantity' => $allocatedQuantity,
                    'created_at' => getCurrentDateTime()
                ];
                
                $sql = "INSERT INTO material_work_relations (certificate_id, material_id, work_id, allocated_quantity, created_at) VALUES (?, ?, ?, ?, ?)";
                $this->query($sql, array_values($relationData));
            }
        }
    }
    
    /**
     * الحصول على الشهادة مع التفاصيل
     */
    public function getCertificateWithDetails($certificateId) {
        $certificate = $this->findById($certificateId);
        if (!$certificate) {
            return null;
        }
        
        // الحصول على المواد
        $materials = $this->fetchAll(
            "SELECT ccm.*, m.item_number, mc.description, mc.unit 
             FROM completion_certificate_materials ccm
             JOIN materials m ON ccm.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE ccm.certificate_id = ?
             ORDER BY mc.description",
            [$certificateId]
        );
        
        // الحصول على الأعمال
        $works = $this->fetchAll(
            "SELECT * FROM completion_certificate_works 
             WHERE certificate_id = ?
             ORDER BY work_description",
            [$certificateId]
        );
        
        $certificate['materials'] = $materials;
        $certificate['works'] = $works;
        
        return $certificate;
    }
    
    /**
     * الحصول على إحصائيات الشهادات
     */
    public function getCertificateStats($branchId = null, $dateFrom = null, $dateTo = null) {
        $whereConditions = [];
        $params = [];
        
        if ($branchId) {
            $whereConditions[] = 'branch_id = ?';
            $params[] = $branchId;
        }
        
        if ($dateFrom) {
            $whereConditions[] = 'certificate_date >= ?';
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereConditions[] = 'certificate_date <= ?';
            $params[] = $dateTo;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $stats = [];
        
        // إحصائيات حسب الحالة
        $sql = "
            SELECT status, 
                   COUNT(*) as count,
                   SUM(total_certificate_value) as total_value
            FROM completion_certificates 
            {$whereClause}
            GROUP BY status
        ";
        $stats['by_status'] = $this->fetchAll($sql, $params);
        
        // إجمالي القيم
        $sql = "
            SELECT 
                SUM(total_materials_value) as total_materials,
                SUM(total_works_value) as total_works,
                SUM(total_certificate_value) as total_certificates
            FROM completion_certificates 
            {$whereClause}
        ";
        $stats['totals'] = $this->fetchOne($sql, $params);
        
        return $stats;
    }
}
?>
