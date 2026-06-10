<?php
/**
 * نموذج معاملات المخزون
 * Inventory Transaction Model
 */

require_once __DIR__ . '/BaseModel.php';

class InventoryTransaction extends BaseModel
{
    protected $table = 'inventory_transactions';

    /**
     * البحث عن معاملة بواسطة رقم العملية
     */
    public function findByTransactionNumber($transactionNumber)
    {
        return $this->findOneWhere('transaction_number = ?', [$transactionNumber]);
    }

    /**
     * الحصول على المعاملات بواسطة النوع
     */
    public function findByType($type, $branchId = null)
    {
        $condition = 'transaction_type = ?';
        $params = [$type];

        if ($branchId) {
            $condition .= ' AND branch_id = ?';
            $params[] = $branchId;
        }

        return $this->fetchAll("SELECT * FROM {$this->table} WHERE {$condition} ORDER BY transaction_date DESC, created_at DESC", $params);
    }

    /**
     * الحصول على المعاملات المعلقة
     */
    public function getPendingTransactions($branchId = null)
    {
        $condition = "status = 'pending'";
        $params = [];

        if ($branchId) {
            $condition .= ' AND branch_id = ?';
            $params[] = $branchId;
        }

        return $this->fetchAll("SELECT * FROM {$this->table} WHERE {$condition} ORDER BY created_at ASC", $params);
    }

    /**
     * إنشاء معاملة جديدة
     */
    public function createTransaction($data, $details = [])
    {
        try {
            $this->beginTransaction();

            // توليد رقم العملية
            $data['transaction_number'] = $this->generateTransactionNumber($data['transaction_type']);
            $data['created_at'] = getCurrentDateTime();
            $data['updated_at'] = getCurrentDateTime();

            // تعيين الحالة الافتراضية إذا لم تكن محددة
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = 'pending';
            }

            // إدراج المعاملة الرئيسية
            $transactionId = $this->insert($data);

            // إدراج تفاصيل المعاملة
            if (!empty($details)) {
                $detailsResult = $this->insertTransactionDetails($transactionId, $details);
                if (!$detailsResult['success']) {
                    $this->rollback();
                    return $detailsResult;
                }
            }

            $this->commit();
            logActivity('create_inventory_transaction', "تم إنشاء معاملة مخزون جديدة: {$data['transaction_number']}");

            return ['success' => true, 'transaction_id' => $transactionId, 'transaction_number' => $data['transaction_number']];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في إنشاء المعاملة: ' . $e->getMessage()];
        }
    }

    /**
     * إدراج تفاصيل المعاملة
     */
    private function insertTransactionDetails($transactionId, $details)
    {
        try {
            $materialModel = new Material();

            foreach ($details as $detail) {
                // التحقق من توفر المادة
                $material = $materialModel->findById($detail['material_id']);
                if (!$material) {
                    return ['success' => false, 'message' => "المادة غير موجودة: {$detail['material_id']}"];
                }

                // إدراج التفصيل
                $sql = "INSERT INTO transaction_details (transaction_id, material_id, quantity, notes, created_at) VALUES (?, ?, ?, ?, ?)";
                $this->query($sql, [
                    $transactionId,
                    $detail['material_id'],
                    $detail['quantity'],
                    $detail['notes'] ?? '',
                    getCurrentDateTime()
                ]);
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إدراج تفاصيل المعاملة: ' . $e->getMessage()];
        }
    }

    /**
     * توليد رقم العملية
     */
    private function generateTransactionNumber($type)
    {
        $prefix = [
            'incoming' => 'IN',
            'outgoing' => 'OUT',
            'transfer' => 'TRF',
            'return' => 'RET',
            'initial_balance' => 'INIT',
            'loan_out' => 'LOUT',
            'loan_in' => 'LIN',
            'loan_return' => 'LRET'
        ];

        $typePrefix = $prefix[$type] ?? 'TXN';
        $date = date('Ymd');

        // البحث عن آخر رقم لنفس النوع واليوم
        $lastNumber = $this->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(transaction_number, -4) AS UNSIGNED)) 
             FROM inventory_transactions 
             WHERE transaction_type = ? AND DATE(created_at) = CURDATE()",
            [$type]
        ) ?: 0;

        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

        return "{$typePrefix}-{$date}-{$newNumber}";
    }

    /**
     * اعتماد المعاملة
     */
    public function approveTransaction($transactionId, $approvedBy)
    {
        try {
            $this->beginTransaction();

            $transaction = $this->findById($transactionId);
            if (!$transaction) {
                $this->rollback();
                return ['success' => false, 'message' => 'المعاملة غير موجودة'];
            }

            if ($transaction['status'] !== 'pending') {
                $this->rollback();
                return ['success' => false, 'message' => 'المعاملة ليست في حالة انتظار'];
            }

            // تحديث حالة المعاملة
            $result = $this->update($transactionId, [
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => getCurrentDateTime(),
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                // تحديث المخزون
                $stockResult = $this->updateStockFromTransaction($transactionId);
                if (!$stockResult['success']) {
                    $this->rollback();
                    return $stockResult;
                }

                $this->commit();
                logActivity('approve_inventory_transaction', "تم اعتماد معاملة المخزون: {$transaction['transaction_number']}");
                return ['success' => true];
            }

            $this->rollback();
            return ['success' => false, 'message' => 'فشل في اعتماد المعاملة'];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في اعتماد المعاملة: ' . $e->getMessage()];
        }
    }

    /**
     * تحديث المخزون من المعاملة
     */
    private function updateStockFromTransaction($transactionId)
    {
        try {
            $materialModel = new Material();

            // الحصول على تفاصيل المعاملة
            $details = $this->fetchAll(
                "SELECT * FROM transaction_details WHERE transaction_id = ?",
                [$transactionId]
            );

            $transaction = $this->findById($transactionId);

            foreach ($details as $detail) {
                $addTypes = ['incoming', 'return', 'initial_balance', 'loan_in', 'loan_return'];
                $operation = in_array($transaction['transaction_type'], $addTypes) ? 'add' : 'subtract';

                $result = $materialModel->updateStock(
                    $detail['material_id'],
                    $detail['quantity'],
                    $operation
                );

                if (!$result['success']) {
                    return $result;
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث المخزون: ' . $e->getMessage()];
        }
    }

    /**
     * رفض المعاملة
     */
    public function rejectTransaction($transactionId, $rejectedBy, $reason = '')
    {
        try {
            $this->beginTransaction();

            $transaction = $this->findById($transactionId);
            if (!$transaction) {
                $this->rollback();
                return ['success' => false, 'message' => 'المعاملة غير موجودة'];
            }

            if ($transaction['status'] !== 'pending') {
                $this->rollback();
                return ['success' => false, 'message' => 'المعاملة ليست في حالة انتظار'];
            }

            // تحديث حالة المعاملة
            $result = $this->update($transactionId, [
                'status' => 'rejected',
                'rejected_by' => $rejectedBy,
                'rejected_at' => getCurrentDateTime(),
                'rejection_reason' => $reason,
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                $this->commit();
                logActivity('reject_inventory_transaction', "تم رفض معاملة المخزون: {$transaction['transaction_number']} - السبب: {$reason}");
                return ['success' => true, 'message' => 'تم رفض المعاملة بنجاح'];
            } else {
                $this->rollback();
                return ['success' => false, 'message' => 'فشل في رفض المعاملة'];
            }

        } catch (Exception $e) {
            $this->rollback();
            error_log("Error rejecting transaction: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ في النظام'];
        }
    }

    /**
     * الحصول على المعاملة مع التفاصيل
     */
    public function getTransactionWithDetails($transactionId)
    {
        $transaction = $this->fetchOne(
            "SELECT it.*,
                    u1.full_name as created_by_name,
                    u2.full_name as approved_by_name,
                    wo.work_order_number,
                    wot.type_code as work_order_type_code
             FROM inventory_transactions it
             LEFT JOIN users u1 ON it.created_by = u1.id
             LEFT JOIN users u2 ON it.approved_by = u2.id
             LEFT JOIN work_orders wo ON it.work_order_id = wo.id
             LEFT JOIN work_order_types wot ON wo.work_order_type_id = wot.id
             WHERE it.id = ?",
            [$transactionId]
        );

        if (!$transaction) {
            return null;
        }

        $details = $this->fetchAll(
            "SELECT td.*, mc.description as material_name, m.item_number, mc.description, mc.unit
             FROM transaction_details td
             JOIN materials m ON td.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE td.transaction_id = ?
             ORDER BY mc.description",
            [$transactionId]
        );

        $transaction['details'] = $details;
        return $transaction;
    }

    /**
     * الحصول على إحصائيات المعاملات
     */
    public function getTransactionStats($branchId = null, $dateFrom = null, $dateTo = null)
    {
        $whereConditions = [];
        $params = [];

        if ($branchId) {
            $whereConditions[] = 'branch_id = ?';
            $params[] = $branchId;
        }

        if ($dateFrom) {
            $whereConditions[] = 'transaction_date >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $whereConditions[] = 'transaction_date <= ?';
            $params[] = $dateTo;
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // إحصائيات أساسية
        $basicStats = $this->fetchOne(
            "SELECT
                COUNT(*) as total_transactions,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_transactions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_transactions
             FROM inventory_transactions {$whereClause}",
            $params
        );

        $stats = [
            'total_transactions' => (int) ($basicStats['total_transactions'] ?? 0),
            'approved_transactions' => (int) ($basicStats['approved_transactions'] ?? 0),
            'pending_transactions' => (int) ($basicStats['pending_transactions'] ?? 0),
            'rejected_transactions' => (int) ($basicStats['rejected_transactions'] ?? 0),
        ];

        // إحصائيات حسب النوع
        $sql = "
            SELECT transaction_type,
                   COUNT(*) as count
            FROM inventory_transactions
            {$whereClause}
            GROUP BY transaction_type
        ";
        $stats['by_type'] = $this->fetchAll($sql, $params);

        // إحصائيات حسب الحالة
        $sql = "
            SELECT status,
                   COUNT(*) as count
            FROM inventory_transactions
            {$whereClause}
            GROUP BY status
        ";
        $stats['by_status'] = $this->fetchAll($sql, $params);

        return $stats;
    }

    /**
     * تحديث المعاملة
     */
    public function updateTransaction($transactionId, $transactionData, $materials)
    {
        try {
            $this->beginTransaction();

            // التحقق من وجود المعاملة وحالتها
            $transaction = $this->findById($transactionId);
            if (!$transaction) {
                $this->rollback();
                return ['success' => false, 'message' => 'المعاملة غير موجودة'];
            }

            if ($transaction['status'] !== 'pending') {
                $this->rollback();
                return ['success' => false, 'message' => 'لا يمكن تعديل المعاملة بعد اعتمادها أو رفضها'];
            }

            // تحديث بيانات المعاملة الأساسية
            $result = $this->update($transactionId, $transactionData);
            if (!$result) {
                $this->rollback();
                return ['success' => false, 'message' => 'فشل في تحديث بيانات المعاملة'];
            }

            // حذف تفاصيل المواد القديمة
            $deleteResult = $this->query(
                "DELETE FROM transaction_details WHERE transaction_id = ?",
                [$transactionId]
            );

            if (!$deleteResult) {
                $this->rollback();
                return ['success' => false, 'message' => 'فشل في حذف التفاصيل القديمة'];
            }

            // إضافة تفاصيل المواد الجديدة
            foreach ($materials as $material) {
                if (empty($material['material_id']) || empty($material['quantity'])) {
                    continue;
                }

                $quantity = (float) $material['quantity'];

                $detailResult = $this->query(
                    "INSERT INTO transaction_details
                     (transaction_id, material_id, quantity, created_at)
                     VALUES (?, ?, ?, ?)",
                    [
                        $transactionId,
                        $material['material_id'],
                        $quantity,
                        getCurrentDateTime()
                    ]
                );

                if (!$detailResult) {
                    $this->rollback();
                    return ['success' => false, 'message' => 'فشل في إضافة تفاصيل المادة'];
                }
            }

            // تحديث تاريخ التعديل
            $this->update($transactionId, [
                'updated_at' => getCurrentDateTime()
            ]);

            $this->commit();
            logActivity('update_inventory_transaction', "تم تحديث معاملة المخزون: {$transaction['transaction_number']}");

            return ['success' => true, 'message' => 'تم تحديث المعاملة بنجاح'];

        } catch (Exception $e) {
            $this->rollback();
            error_log("Error updating transaction: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ في النظام'];
        }
    }
}
?>