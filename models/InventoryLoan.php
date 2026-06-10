<?php
/**
 * نموذج إدارة السلف (Inventory Loans)
 * تمت إعادة الهيكلة ليرث من BaseModel وينشئ معاملات رسمية
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Material.php';

class InventoryLoan extends BaseModel
{
    protected $table = 'inventory_loans';

    /**
     * إنشاء سلفة جديدة
     */
    public function createLoan(array $data, array $details): array
    {
        try {
            $this->beginTransaction();
            $materialModel = new Material();

            // توليد رقم سلفة فريد
            $prefix = ($data['type'] === 'borrow') ? 'BRW-' : 'LND-';
            $datePrefix = date('ymd');
            $count = $this->fetchColumn(
                "SELECT COUNT(*) FROM inventory_loans WHERE type = ? AND DATE(created_at) = CURDATE()",
                [$data['type']]
            ) + 1;
            $loanNumber = $prefix . $datePrefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

            // إدخال السلفة
            $loanId = $this->insert([
                'loan_number' => $loanNumber,
                'type' => $data['type'],
                'client_id' => $data['client_id'],
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_identity' => $data['receiver_identity'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'],
                'status' => 'active'
            ]);

            // إدخال بنود السلفة وتحديث المخزون
            foreach ($details as $detail) {
                $materialId = !empty($detail['material_id']) ? $detail['material_id'] : null;
                $quantity = (float)$detail['quantity'];

                // التأثير على المخزون (إذا كانت المادة مسجلة)
                if ($materialId) {
                    if ($data['type'] === 'lend') {
                        // تسليف = خصم من المستودع
                        $check = $materialModel->checkAvailability($materialId, $quantity);
                        if (!$check['available']) {
                            throw new Exception("الكمية غير متوفرة للبند: " . $detail['item_number'] . " - " . $check['message']);
                        }
                        $materialModel->updateStock($materialId, $quantity, 'subtract');
                    } elseif ($data['type'] === 'borrow') {
                        // استلاف = إضافة للمستودع
                        $materialModel->updateStock($materialId, $quantity, 'add');
                    }
                }

                // إدخال تفصيل السلفة
                $this->query(
                    "INSERT INTO inventory_loan_details (loan_id, material_id, item_number, description, quantity) VALUES (?, ?, ?, ?, ?)",
                    [$loanId, $materialId, $detail['item_number'], $detail['description'], $quantity]
                );
            }

            // إنشاء معاملة رسمية في inventory_transactions
            $this->createLoanTransaction($data, $details, $loanId, $loanNumber);

            $this->commit();

            return [
                'success' => true,
                'loan_id' => $loanId,
                'loan_number' => $loanNumber
            ];

        } catch (Exception $e) {
            $this->rollback();
            error_log("Error creating loan: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء السلفة: ' . $e->getMessage()
            ];
        }
    }

    /**
     * إنشاء معاملة رسمية مرتبطة بالسلفة
     */
    private function createLoanTransaction(array $data, array $details, int $loanId, string $loanNumber): void
    {
        $transactionType = ($data['type'] === 'lend') ? 'loan_out' : 'loan_in';
        $typePrefix = ($data['type'] === 'lend') ? 'LOUT' : 'LIN';
        $transactionNumber = $this->generateLoanTransactionNumber($typePrefix);

        // إنشاء المعاملة الرئيسية
        $this->query(
            "INSERT INTO inventory_transactions
                (transaction_number, transaction_type, branch_id, reference_number,
                 transaction_date, notes, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, CURDATE(), ?, 'approved', ?, NOW(), NOW())",
            [
                $transactionNumber,
                $transactionType,
                $data['branch_id'] ?? 1,
                $loanNumber,
                "سلفة {$data['type']}: {$loanNumber}",
                $data['created_by']
            ]
        );

        $transactionId = $this->db->lastInsertId();

        // إضافة تفاصيل المعاملة (المواد المسجلة فقط)
        foreach ($details as $detail) {
            if (!empty($detail['material_id'])) {
                $this->query(
                    "INSERT INTO transaction_details (transaction_id, material_id, quantity, notes, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [
                        $transactionId,
                        $detail['material_id'],
                        (float)$detail['quantity'],
                        "سلفة: {$loanNumber}"
                    ]
                );
            }
        }
    }

    /**
     * توليد رقم معاملة سلفة
     */
    private function generateLoanTransactionNumber(string $typePrefix): string
    {
        $prefix = $typePrefix . date('Ymd');
        $lastNumber = $this->fetchOne(
            "SELECT transaction_number FROM inventory_transactions
             WHERE transaction_number LIKE ?
             ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );

        if ($lastNumber && isset($lastNumber['transaction_number'])) {
            $lastSequence = (int) substr($lastNumber['transaction_number'], -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * جلب قائمة السلف
     */
    public function getLoans(array $filters = []): array
    {
        $sql = "
            SELECT l.*, c.name as client_name, u.full_name as creator_name
            FROM inventory_loans l
            JOIN inventory_clients c ON l.client_id = c.id
            LEFT JOIN users u ON l.created_by = u.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= " AND l.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND l.client_id = ?";
            $params[] = $filters['client_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND l.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY l.created_at DESC";

        return $this->fetchAll($sql, $params);
    }

    /**
     * جلب تفاصيل سلفة محددة
     */
    public function getLoanDetails(int $loanId): ?array
    {
        $loan = $this->fetchOne(
            "SELECT l.*, c.name as client_name, u.full_name as creator_name
             FROM inventory_loans l
             JOIN inventory_clients c ON l.client_id = c.id
             LEFT JOIN users u ON l.created_by = u.id
             WHERE l.id = ?",
            [$loanId]
        );

        if (!$loan) return null;

        $loan['items'] = $this->fetchAll(
            "SELECT d.*, mc.unit 
             FROM inventory_loan_details d
             LEFT JOIN materials m ON d.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE d.loan_id = ?",
            [$loanId]
        );

        return $loan;
    }

    /**
     * تحديث حالة السلفة (مخالصة)
     */
    public function updateLoanStatus(int $loanId, string $status): array
    {
        try {
            $this->beginTransaction();

            if ($status === 'settled') {
                // عكس تأثير المخزون عند المخالصة
                $loan = $this->getLoanDetails($loanId);
                if ($loan) {
                    $materialModel = new Material();
                    foreach ($loan['items'] as $item) {
                        if (!empty($item['material_id'])) {
                            $materialId = $item['material_id'];
                            $quantity = (float)$item['quantity'];

                            if ($loan['type'] === 'lend') {
                                // إرجاع مواد التسليف للمستودع
                                $materialModel->updateStock($materialId, $quantity, 'add');
                            } elseif ($loan['type'] === 'borrow') {
                                // إرجاع مواد الاستلاف للمقاول
                                $check = $materialModel->checkAvailability($materialId, $quantity);
                                if (!$check['available']) {
                                    throw new Exception("لا يمكن عمل مخالصة: الرصيد المتوفر في المستودع غير كافٍ لإرجاع البند " . $item['item_number']);
                                }
                                $materialModel->updateStock($materialId, $quantity, 'subtract');
                            }
                        }
                    }

                    // إنشاء معاملة عكسية (إرجاع سلفة)
                    $this->createSettlementTransaction($loan);
                }

                // تحديث حالة السلفة
                $this->query(
                    "UPDATE inventory_loans SET status = 'settled', settled_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$loanId]
                );
            } else {
                $this->update($loanId, ['status' => $status]);
            }

            $this->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->rollback();
            error_log("Error updating loan status: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الحالة: ' . $e->getMessage()
            ];
        }
    }

    /**
     * إنشاء معاملة مخالصة (إرجاع سلفة)
     */
    private function createSettlementTransaction(array $loan): void
    {
        $transactionNumber = $this->generateLoanTransactionNumber('LRET');

        $this->query(
            "INSERT INTO inventory_transactions
                (transaction_number, transaction_type, branch_id, reference_number,
                 transaction_date, notes, status, created_by, created_at, updated_at)
             VALUES (?, 'loan_return', ?, ?, CURDATE(), ?, 'approved', ?, NOW(), NOW())",
            [
                $transactionNumber,
                1, // default branch
                $loan['loan_number'],
                "مخالصة سلفة: {$loan['loan_number']}",
                $loan['created_by'] ?? 1
            ]
        );

        $transactionId = $this->db->lastInsertId();

        foreach ($loan['items'] as $item) {
            if (!empty($item['material_id'])) {
                $this->query(
                    "INSERT INTO transaction_details (transaction_id, material_id, quantity, notes, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [
                        $transactionId,
                        $item['material_id'],
                        (float)$item['quantity'],
                        "مخالصة سلفة: {$loan['loan_number']}"
                    ]
                );
            }
        }
    }
}
