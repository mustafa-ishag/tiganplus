<?php
/**
 * نموذج جلسات الجرد
 * Stocktaking Session Model
 */

require_once __DIR__ . '/BaseModel.php';

class StocktakingSession extends BaseModel
{
    protected $table = 'stocktaking_sessions';

    /**
     * توليد رقم جلسة فريد
     */
    public function generateSessionNumber()
    {
        $date = date('Ymd');
        $lastNumber = $this->fetchColumn(
            "SELECT MAX(CAST(SUBSTRING(session_number, -4) AS UNSIGNED)) 
             FROM stocktaking_sessions 
             WHERE DATE(created_at) = CURDATE()"
        ) ?: 0;
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        return "ST-{$date}-{$newNumber}";
    }

    /**
     * إنشاء جلسة جرد جديدة
     */
    public function createSession($data)
    {
        try {
            $this->beginTransaction();

            $data['session_number'] = $this->generateSessionNumber();
            $data['created_at'] = getCurrentDateTime();
            $data['updated_at'] = getCurrentDateTime();

            if (!isset($data['status'])) {
                $data['status'] = 'draft';
            }

            $sessionId = $this->insert($data);

            $this->commit();
            logActivity('create_stocktaking', "تم إنشاء جلسة جرد جديدة: {$data['session_number']}");

            return [
                'success' => true,
                'session_id' => $sessionId,
                'session_number' => $data['session_number']
            ];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في إنشاء جلسة الجرد: ' . $e->getMessage()];
        }
    }

    /**
     * بدء الجرد: تحميل جميع المواد النشطة مع كمياتها الحالية
     */
    public function startSession($sessionId, $materialIds = null)
    {
        try {
            $this->beginTransaction();

            $session = $this->findById($sessionId);
            if (!$session) {
                $this->rollback();
                return ['success' => false, 'message' => 'الجلسة غير موجودة'];
            }
            if ($session['status'] !== 'draft') {
                $this->rollback();
                return ['success' => false, 'message' => 'لا يمكن بدء الجلسة - الحالة الحالية: ' . $session['status']];
            }

            // تحديد المواد
            if ($session['session_type'] === 'full' || empty($materialIds)) {
                $materials = $this->fetchAll("SELECT id, current_stock FROM materials WHERE is_active = 1");
            } else {
                $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
                $materials = $this->fetchAll(
                    "SELECT id, current_stock FROM materials WHERE id IN ({$placeholders}) AND is_active = 1",
                    $materialIds
                );
            }

            // إدراج بنود الجرد
            $insertStmt = $this->db->prepare(
                "INSERT INTO stocktaking_items (session_id, material_id, system_quantity, status, created_at) 
                 VALUES (?, ?, ?, 'pending', NOW())"
            );

            foreach ($materials as $material) {
                $insertStmt->execute([$sessionId, $material['id'], $material['current_stock']]);
            }

            // تحديث حالة الجلسة
            $this->update($sessionId, [
                'status' => 'in_progress',
                'total_items' => count($materials),
                'not_counted_items' => count($materials),
                'updated_at' => getCurrentDateTime()
            ]);

            $this->commit();
            logActivity('start_stocktaking', "تم بدء جلسة الجرد: {$session['session_number']} ({$session['session_type']}) - " . count($materials) . " مادة");

            return ['success' => true, 'items_count' => count($materials)];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في بدء الجلسة: ' . $e->getMessage()];
        }
    }

    /**
     * تسجيل كمية محصاة لمادة
     */
    public function saveCount($sessionId, $materialId, $countedQty, $inputMethod = 'manual', $userId = null, $notes = '')
    {
        try {
            $session = $this->findById($sessionId);
            if (!$session || $session['status'] !== 'in_progress') {
                return ['success' => false, 'message' => 'الجلسة غير متاحة للعد'];
            }

            $item = $this->fetchOne(
                "SELECT * FROM stocktaking_items WHERE session_id = ? AND material_id = ?",
                [$sessionId, $materialId]
            );

            if (!$item) {
                return ['success' => false, 'message' => 'المادة غير موجودة في هذه الجلسة'];
            }

            $wasAlreadyCounted = ($item['status'] === 'counted');

            $this->query(
                "UPDATE stocktaking_items 
                 SET counted_quantity = ?, status = 'counted', input_method = ?, 
                     counted_by = ?, counted_at = NOW(), notes = ?, updated_at = NOW()
                 WHERE session_id = ? AND material_id = ?",
                [$countedQty, $inputMethod, $userId, $notes, $sessionId, $materialId]
            );

            // تحديث إحصائيات الجلسة
            $this->updateSessionStats($sessionId);

            $difference = $countedQty - $item['system_quantity'];
            return [
                'success' => true,
                'system_quantity' => $item['system_quantity'],
                'counted_quantity' => $countedQty,
                'difference' => $difference,
                'was_update' => $wasAlreadyCounted
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في حفظ العد: ' . $e->getMessage()];
        }
    }

    /**
     * البحث عن مادة بالباركود (item_number) وإرجاع بياناتها في سياق الجلسة
     */
    public function findItemByBarcode($sessionId, $barcode)
    {
        return $this->fetchOne(
            "SELECT si.*, m.item_number, mc.description, mc.unit, m.current_stock, mc.group_number
             FROM stocktaking_items si
             JOIN materials m ON si.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE si.session_id = ? AND m.item_number = ?",
            [$sessionId, $barcode]
        );
    }

    /**
     * تحديث إحصائيات الجلسة
     */
    public function updateSessionStats($sessionId)
    {
        $stats = $this->fetchOne(
            "SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'counted' AND COALESCE(counted_quantity, 0) = system_quantity THEN 1 ELSE 0 END) as matched_items,
                SUM(CASE WHEN status = 'counted' AND counted_quantity > system_quantity THEN 1 ELSE 0 END) as surplus_items,
                SUM(CASE WHEN status = 'counted' AND counted_quantity < system_quantity THEN 1 ELSE 0 END) as deficit_items,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as not_counted_items
             FROM stocktaking_items WHERE session_id = ?",
            [$sessionId]
        );

        $this->update($sessionId, [
            'total_items' => (int) $stats['total_items'],
            'matched_items' => (int) $stats['matched_items'],
            'surplus_items' => (int) $stats['surplus_items'],
            'deficit_items' => (int) $stats['deficit_items'],
            'not_counted_items' => (int) $stats['not_counted_items'],
            'updated_at' => getCurrentDateTime()
        ]);

        return $stats;
    }

    /**
     * إكمال الجلسة
     */
    public function completeSession($sessionId)
    {
        $session = $this->findById($sessionId);
        if (!$session || $session['status'] !== 'in_progress') {
            return ['success' => false, 'message' => 'لا يمكن إكمال هذه الجلسة'];
        }

        // التحقق من عد جميع المواد
        $notCounted = $this->fetchColumn(
            "SELECT COUNT(*) FROM stocktaking_items WHERE session_id = ? AND status = 'pending'",
            [$sessionId]
        );

        if ($notCounted > 0) {
            return ['success' => false, 'message' => "لا يزال هناك {$notCounted} مادة لم يتم عدها"];
        }

        $this->updateSessionStats($sessionId);
        $this->update($sessionId, [
            'status' => 'completed',
            'end_date' => date('Y-m-d'),
            'updated_at' => getCurrentDateTime()
        ]);

        logActivity('complete_stocktaking', "تم إكمال جلسة الجرد: {$session['session_number']}");
        return ['success' => true];
    }

    /**
     * اعتماد الجرد وتسوية المخزون
     */
    public function approveSession($sessionId, $approvedBy)
    {
        try {
            $this->beginTransaction();

            $session = $this->findById($sessionId);
            if (!$session || $session['status'] !== 'completed') {
                $this->rollback();
                return ['success' => false, 'message' => 'لا يمكن اعتماد هذه الجلسة'];
            }

            // جلب المواد التي بها فروقات
            $discrepancies = $this->fetchAll(
                "SELECT si.*, m.item_number, mc.description 
                 FROM stocktaking_items si
                 JOIN materials m ON si.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
                 WHERE si.session_id = ? AND si.counted_quantity != si.system_quantity",
                [$sessionId]
            );

            $transactionId = null;

            if (!empty($discrepancies)) {
                // إنشاء معاملة تسوية
                require_once __DIR__ . '/InventoryTransaction.php';
                $transactionModel = new InventoryTransaction();

                $txData = [
                    'transaction_type' => 'stocktake_adjustment',
                    'transaction_date' => date('Y-m-d'),
                    'branch_id' => $_SESSION['branch_id'] ?? 1,
                    'notes' => "تسوية جرد رقم: {$session['session_number']}",
                    'created_by' => $approvedBy,
                    'status' => 'approved',
                    'approved_by' => $approvedBy,
                    'approved_at' => getCurrentDateTime()
                ];

                $txData['transaction_number'] = $transactionModel->fetchColumn(
                    "SELECT CONCAT('ADJ-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(COALESCE(MAX(CAST(SUBSTRING(transaction_number, -4) AS UNSIGNED)), 0) + 1, 4, '0'))
                     FROM inventory_transactions WHERE transaction_type = 'stocktake_adjustment' AND DATE(created_at) = CURDATE()"
                ) ?: 'ADJ-' . date('Ymd') . '-0001';

                $txData['created_at'] = getCurrentDateTime();
                $txData['updated_at'] = getCurrentDateTime();

                // إدراج المعاملة
                $transactionId = $this->db->prepare(
                    "INSERT INTO inventory_transactions (transaction_number, transaction_type, transaction_date, branch_id, notes, created_by, status, approved_by, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $transactionId->execute([
                    $txData['transaction_number'], $txData['transaction_type'], $txData['transaction_date'],
                    $txData['branch_id'], $txData['notes'], $txData['created_by'], $txData['status'],
                    $txData['approved_by'], $txData['approved_at'], $txData['created_at'], $txData['updated_at']
                ]);
                $transactionId = $this->db->lastInsertId();

                // تسوية كل مادة
                require_once __DIR__ . '/Material.php';
                $materialModel = new Material();

                foreach ($discrepancies as $item) {
                    $diff = $item['counted_quantity'] - $item['system_quantity'];

                    // إدراج تفاصيل المعاملة
                    $this->query(
                        "INSERT INTO transaction_details (transaction_id, material_id, quantity, notes, created_at) VALUES (?, ?, ?, ?, NOW())",
                        [$transactionId, $item['material_id'], abs($diff), "تسوية جرد: الفرق = {$diff}"]
                    );

                    // تحديث المخزون مباشرة للكمية المحصاة
                    $this->query(
                        "UPDATE materials SET current_stock = ?, updated_at = NOW() WHERE id = ?",
                        [$item['counted_quantity'], $item['material_id']]
                    );
                }
            }

            // تحديث حالة الجلسة
            $this->update($sessionId, [
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => getCurrentDateTime(),
                'adjustment_transaction_id' => $transactionId,
                'updated_at' => getCurrentDateTime()
            ]);

            $this->commit();
            logActivity('approve_stocktaking', "تم اعتماد جلسة الجرد: {$session['session_number']} - " . count($discrepancies) . " تسوية");

            return [
                'success' => true,
                'adjustments_count' => count($discrepancies),
                'transaction_id' => $transactionId
            ];
        } catch (Exception $e) {
            $this->rollback();
            return ['success' => false, 'message' => 'فشل في اعتماد الجلسة: ' . $e->getMessage()];
        }
    }

    /**
     * الحصول على جلسة مع بنودها
     */
    public function getSessionWithItems($sessionId)
    {
        $session = $this->fetchOne(
            "SELECT ss.*, u1.full_name as created_by_name, u2.full_name as approved_by_name
             FROM stocktaking_sessions ss
             LEFT JOIN users u1 ON ss.created_by = u1.id
             LEFT JOIN users u2 ON ss.approved_by = u2.id
             WHERE ss.id = ?",
            [$sessionId]
        );

        if (!$session) return null;

        $session['items'] = $this->fetchAll(
            "SELECT si.*, m.item_number, mc.description, mc.unit, mc.group_number, m.current_stock as live_stock,
                    u.full_name as counted_by_name
             FROM stocktaking_items si
             JOIN materials m ON si.material_id = m.id
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             LEFT JOIN users u ON si.counted_by = u.id
             WHERE si.session_id = ?
             ORDER BY m.item_number ASC",
            [$sessionId]
        );

        return $session;
    }

    /**
     * إحصائيات عامة للجرد
     */
    public function getStocktakingStats()
    {
        return $this->fetchOne(
            "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_sessions,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_sessions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_sessions
             FROM stocktaking_sessions"
        );
    }

    /**
     * إلغاء جلسة
     */
    public function cancelSession($sessionId)
    {
        $session = $this->findById($sessionId);
        if (!$session) return ['success' => false, 'message' => 'الجلسة غير موجودة'];
        if (in_array($session['status'], ['approved'])) {
            return ['success' => false, 'message' => 'لا يمكن إلغاء جلسة معتمدة'];
        }

        $this->update($sessionId, [
            'status' => 'cancelled',
            'updated_at' => getCurrentDateTime()
        ]);

        logActivity('cancel_stocktaking', "تم إلغاء جلسة الجرد: {$session['session_number']}");
        return ['success' => true];
    }
}
?>
