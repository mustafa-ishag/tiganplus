<?php
/**
 * نموذج المواد المزالة
 * Removed Material Model
 */

require_once __DIR__ . '/BaseModel.php';

class RemovedMaterial extends BaseModel
{
    protected $table = 'removed_material_transactions';

    /**
     * البحث عن عملية بواسطة رقم العملية
     */
    public function findByTransactionNumber($transactionNumber)
    {
        return $this->findOneWhere('transaction_number = ?', [$transactionNumber]);
    }

    /**
     * إنشاء عملية جديدة
     */
    public function createTransaction($data, $details = [])
    {
        try {
            $this->beginTransaction();

            $data['transaction_number'] = $this->generateTransactionNumber($data['transaction_type']);
            $data['created_at'] = getCurrentDateTime();
            $data['updated_at'] = getCurrentDateTime();

            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = 'pending';
            }

            $transactionId = $this->insert($data);

            if (!empty($details)) {
                $detailsResult = $this->insertTransactionDetails($transactionId, $details);
                if (!$detailsResult['success']) {
                    $this->rollback();
                    return $detailsResult;
                }
            }

            $this->commit();
            logActivity('create_removed_material', "تم إنشاء عملية مواد مزالة جديدة: {$data['transaction_number']}");

            return ['success' => true, 'transaction_id' => $transactionId, 'transaction_number' => $data['transaction_number']];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في إنشاء العملية: ' . $e->getMessage()];
        }
    }

    /**
     * إدراج تفاصيل العملية
     */
    private function insertTransactionDetails($transactionId, $details)
    {
        try {
            foreach ($details as $detail) {
                if (empty($detail['material_id']) || empty($detail['quantity'])) {
                    continue;
                }

                $quantity = (float) $detail['quantity'];

                $sql = "INSERT INTO removed_material_transaction_details 
                        (transaction_id, material_id, item_type, status, disposal_reason, material_condition, remarks, 
                         functional_location, equipment, capacity_kva, manufacturer, prim_sec_volt, manufacture_year, serial_number, images, quantity, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $imagesJson = null;
                if (!empty($detail['images']) && is_array($detail['images'])) {
                    $imagesJson = json_encode($detail['images']);
                } elseif (!empty($detail['images']) && is_string($detail['images'])) {
                    $imagesJson = $detail['images'];
                }

                $this->query($sql, [
                    $transactionId,
                    $detail['material_id'],
                    $detail['item_type'] ?? 'تشغيلي',
                    $detail['status'] ?? 'تخريد',
                    $detail['disposal_reason'] ?? null,
                    $detail['material_condition'] ?? null,
                    $detail['remarks'] ?? null,
                    $detail['functional_location'] ?? null,
                    $detail['equipment'] ?? null,
                    $detail['capacity_kva'] ?? null,
                    $detail['manufacturer'] ?? null,
                    $detail['prim_sec_volt'] ?? null,
                    $detail['manufacture_year'] ?? null,
                    $detail['serial_number'] ?? null,
                    $imagesJson,
                    $quantity,
                    $detail['notes'] ?? '',
                    getCurrentDateTime()
                ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إدراج تفاصيل العملية: ' . $e->getMessage()];
        }
    }

    /**
     * توليد رقم العملية
     */
    private function generateTransactionNumber($type)
    {
        $prefix = $type === 'incoming' ? 'RMI' : 'RMO';
        $date = date('Ymd');

        $lastNumber = $this->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(transaction_number, -4) AS UNSIGNED)) 
             FROM removed_material_transactions 
             WHERE transaction_type = ? AND DATE(created_at) = CURDATE()",
            [$type]
        ) ?: 0;

        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$date}-{$newNumber}";
    }

    /**
     * اعتماد العملية
     */
    public function approveTransaction($transactionId, $approvedBy)
    {
        try {
            $this->beginTransaction();

            $transaction = $this->findById($transactionId);
            if (!$transaction) {
                $this->rollback();
                return ['success' => false, 'message' => 'العملية غير موجودة'];
            }

            if ($transaction['status'] !== 'pending') {
                $this->rollback();
                return ['success' => false, 'message' => 'العملية ليست في حالة انتظار'];
            }

            $result = $this->update($transactionId, [
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => getCurrentDateTime(),
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                // تحديث المخزون حسب نوع العملية
                $stockResult = $this->updateStockFromRemovedMaterials($transactionId, $transaction['transaction_type']);
                if (!$stockResult['success']) {
                    $this->rollback();
                    return $stockResult;
                }

                $this->commit();
                logActivity('approve_removed_material', "تم اعتماد عملية المواد المزالة: {$transaction['transaction_number']}");
                return ['success' => true, 'message' => 'تم اعتماد العملية بنجاح'];
            }

            $this->rollback();
            return ['success' => false, 'message' => 'فشل في اعتماد العملية'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
        }
    }

    /**
     * تحديث المخزون من عملية المواد المزالة
     */
    private function updateStockFromRemovedMaterials($transactionId, $transactionType)
    {
        try {
            $materialModel = new Material();

            $details = $this->fetchAll(
                "SELECT rmtd.material_id, rmtd.quantity, mc.description
                 FROM removed_material_transaction_details rmtd
                 JOIN materials m ON rmtd.material_id = m.id
                 LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                 WHERE rmtd.transaction_id = ?",
                [$transactionId]
            );

            if (empty($details)) {
                return ['success' => true];
            }

            foreach ($details as $detail) {
                if ($transactionType === 'incoming') {
                    // استلام مواد مزالة من الموقع = إضافة للمستودع
                    $result = $materialModel->updateStock($detail['material_id'], $detail['quantity'], 'add');
                } else {
                    // تسليم مواد مزالة = خصم من المستودع
                    $result = $materialModel->updateStock($detail['material_id'], $detail['quantity'], 'subtract');
                }

                if (!$result['success']) {
                    return ['success' => false, 'message' => "فشل تحديث مخزون المادة: {$detail['description']} - {$result['message']}"];
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث المخزون: ' . $e->getMessage()];
        }
    }

    /**
     * رفض العملية
     */
    public function rejectTransaction($transactionId, $rejectedBy, $reason = '')
    {
        try {
            $transaction = $this->findById($transactionId);
            if (!$transaction) {
                return ['success' => false, 'message' => 'العملية غير موجودة'];
            }

            if ($transaction['status'] !== 'pending') {
                return ['success' => false, 'message' => 'العملية ليست في حالة انتظار'];
            }

            $result = $this->update($transactionId, [
                'status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => getCurrentDateTime(),
                'rejection_reason' => $reason,
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                logActivity('reject_removed_material', "تم رفض عملية المواد المزالة: {$transaction['transaction_number']}");
                return ['success' => true, 'message' => 'تم رفض العملية بنجاح'];
            }

            return ['success' => false, 'message' => 'فشل في رفض العملية'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
        }
    }

    /**
     * الحصول على العملية مع التفاصيل
     */
    public function getTransactionWithDetails($transactionId)
    {
        $transaction = $this->fetchOne(
            "SELECT rmt.*,
                    wo.work_order_number,
                    wo.location,
                    wo.department,
                    wot.description as work_order_type,
                    wot.type_code as wo_type_code,
                    u1.full_name as created_by_name,
                    u2.full_name as approved_by_name,
                    u3.full_name as rejected_by_name
             FROM removed_material_transactions rmt
             LEFT JOIN work_orders wo ON rmt.work_order_id = wo.id
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             LEFT JOIN users u1 ON rmt.created_by = u1.id
             LEFT JOIN users u2 ON rmt.approved_by = u2.id
             LEFT JOIN users u3 ON rmt.rejected_by = u3.id
             WHERE rmt.id = ?",
            [$transactionId]
        );

        if (!$transaction) {
            return null;
        }

        $details = $this->fetchAll(
            "SELECT rmtd.*, m.item_number, mc.description, mc.unit
             FROM removed_material_transaction_details rmtd
             JOIN materials m ON rmtd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE rmtd.transaction_id = ?
             ORDER BY m.item_number",
            [$transactionId]
        );

        $transaction['details'] = $details;
        return $transaction;
    }

    /**
     * الحصول على إحصائيات العمليات
     */
    public function getTransactionStats($branchId = null)
    {
        $whereConditions = [];
        $params = [];

        if ($branchId) {
            $whereConditions[] = 'branch_id = ?';
            $params[] = $branchId;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $stats = $this->fetchOne(
            "SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_transactions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_transactions,
                SUM(CASE WHEN material_category = 'scrap' THEN 1 ELSE 0 END) as scrap_count,
                SUM(CASE WHEN material_category = 'return' THEN 1 ELSE 0 END) as return_count
             FROM removed_material_transactions {$whereClause}",
            $params
        );

        return [
            'total_transactions' => (int) ($stats['total_transactions'] ?? 0),
            'approved_transactions' => (int) ($stats['approved_transactions'] ?? 0),
            'pending_transactions' => (int) ($stats['pending_transactions'] ?? 0),
            'rejected_transactions' => (int) ($stats['rejected_transactions'] ?? 0),
            'scrap_count' => (int) ($stats['scrap_count'] ?? 0),
            'return_count' => (int) ($stats['return_count'] ?? 0)
        ];
    }

    /**
     * جلب المواد المزالة لأمر عمل محدد (للتحليل)
     */
    public function getMaterialsByWorkOrder($workOrderId)
    {
        // المواد الواردة (تم إزالتها من الموقع)
        $incoming = $this->fetchAll(
            "SELECT 
                rmtd.material_id,
                m.item_number,
                mc.description,
                mc.unit,
                rmt.material_category,
                SUM(rmtd.quantity) as incoming_qty
             FROM removed_material_transaction_details rmtd
             JOIN removed_material_transactions rmt ON rmtd.transaction_id = rmt.id
             JOIN materials m ON rmtd.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE rmt.work_order_id = ? AND rmt.transaction_type = 'incoming'
             GROUP BY rmtd.material_id, m.item_number, mc.description, mc.unit, rmt.material_category",
            [$workOrderId]
        );

        // المواد الصادرة (تم تسليمها)
        $outgoing = $this->fetchAll(
            "SELECT 
                rmtd.material_id,
                rmt.material_category,
                SUM(rmtd.quantity) as outgoing_qty
             FROM removed_material_transaction_details rmtd
             JOIN removed_material_transactions rmt ON rmtd.transaction_id = rmt.id
             WHERE rmt.work_order_id = ? AND rmt.transaction_type = 'outgoing'
             GROUP BY rmtd.material_id, rmt.material_category",
            [$workOrderId]
        );

        // الدمج
        $merged = [];
        foreach ($incoming as $item) {
            $key = $item['material_id'] . '_' . $item['material_category'];
            $merged[$key] = [
                'material_id' => $item['material_id'],
                'item_number' => $item['item_number'],
                'description' => $item['description'],
                'unit' => $item['unit'],
                'material_category' => $item['material_category'],
                'incoming_qty' => (float) $item['incoming_qty'],
                'outgoing_qty' => 0
            ];
        }

        foreach ($outgoing as $item) {
            $key = $item['material_id'] . '_' . $item['material_category'];
            if (isset($merged[$key])) {
                $merged[$key]['outgoing_qty'] = (float) $item['outgoing_qty'];
            } else {
                // مادة صادرة بدون وارد (حالة نادرة)
                $matInfo = $this->fetchOne(
                    "SELECT m.item_number, mc.description, mc.unit FROM materials m LEFT JOIN material_catalog mc ON m.item_number = mc.item_number WHERE m.id = ?",
                    [$item['material_id']]
                );
                if ($matInfo) {
                    $merged[$key] = [
                        'material_id' => $item['material_id'],
                        'item_number' => $matInfo['item_number'],
                        'description' => $matInfo['description'],
                        'unit' => $matInfo['unit'],
                        'material_category' => $item['material_category'],
                        'incoming_qty' => 0,
                        'outgoing_qty' => (float) $item['outgoing_qty']
                    ];
                }
            }
        }

        // ترتيب حسب رقم المادة
        usort($merged, function ($a, $b) {
            return strcmp($a['item_number'], $b['item_number']);
        });

        return array_values($merged);
    }

    /**
     * تحديث العملية
     */
    public function updateTransaction($transactionId, $transactionData, $materials)
    {
        try {
            $this->beginTransaction();

            $transaction = $this->findById($transactionId);
            if (!$transaction) {
                $this->rollback();
                return ['success' => false, 'message' => 'العملية غير موجودة'];
            }

            if ($transaction['status'] !== 'pending') {
                $this->rollback();
                return ['success' => false, 'message' => 'لا يمكن تعديل العملية بعد اعتمادها أو رفضها'];
            }

            $result = $this->update($transactionId, $transactionData);
            if (!$result) {
                $this->rollback();
                return ['success' => false, 'message' => 'فشل في تحديث بيانات العملية'];
            }

            // حذف التفاصيل القديمة
            $this->query("DELETE FROM removed_material_transaction_details WHERE transaction_id = ?", [$transactionId]);

            // إضافة التفاصيل الجديدة
            foreach ($materials as $material) {
                if (empty($material['material_id']) || empty($material['quantity'])) {
                    continue;
                }

                $quantity = (float) $material['quantity'];

                $imagesJson = null;
                if (!empty($material['images']) && is_array($material['images'])) {
                    $imagesJson = json_encode($material['images']);
                } elseif (!empty($material['images']) && is_string($material['images'])) {
                    $imagesJson = $material['images'];
                }

                $this->query(
                    "INSERT INTO removed_material_transaction_details 
                     (transaction_id, material_id, item_type, status, disposal_reason, material_condition, remarks, 
                      functional_location, equipment, capacity_kva, manufacturer, prim_sec_volt, manufacture_year, serial_number, images, quantity, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $transactionId, 
                        $material['material_id'], 
                        $material['item_type'] ?? 'تشغيلي',
                        $material['status'] ?? 'تخريد',
                        $material['disposal_reason'] ?? null,
                        $material['material_condition'] ?? null,
                        $material['remarks'] ?? null,
                        $material['functional_location'] ?? null,
                        $material['equipment'] ?? null,
                        $material['capacity_kva'] ?? null,
                        $material['manufacturer'] ?? null,
                        $material['prim_sec_volt'] ?? null,
                        $material['manufacture_year'] ?? null,
                        $material['serial_number'] ?? null,
                        $imagesJson,
                        $quantity, 
                        getCurrentDateTime()
                    ]
                );
            }

            $this->update($transactionId, [
                'updated_at' => getCurrentDateTime()
            ]);

            $this->commit();
            logActivity('update_removed_material', "تم تحديث عملية المواد المزالة: {$transaction['transaction_number']}");

            return ['success' => true, 'message' => 'تم تحديث العملية بنجاح'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
        }
    }
}
?>