<?php
/**
 * نموذج المواد
 * Material Model
 */

require_once __DIR__ . '/BaseModel.php';

class Material extends BaseModel
{
    protected $table = 'materials';

    /**
     * البحث عن مادة بواسطة رقم البند
     */
    public function findByItemNumber($itemNumber)
    {
        return $this->findOneWhere('item_number = ?', [$itemNumber]);
    }

    /**
     * البحث عن مادة بواسطة الباركود (يستخدم item_number كباركود)
     */
    public function findByBarcode($barcode)
    {
        return $this->findOneWhere('item_number = ? AND is_active = 1', [$barcode]);
    }

    /**
     * البحث عن المواد بواسطة رقم المجموعة (من الكتالوج)
     */
    public function findByGroupNumber($groupNumber)
    {
        return $this->fetchAll(
            "SELECT m.*, mc.description, mc.group_number, mc.unit
             FROM materials m
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE mc.group_number = ? AND m.is_active = 1",
            [$groupNumber]
        );
    }

    /**
     * جلب مادة بالمعرف مع بيانات الكتالوج
     */
    public function findByIdFull($id)
    {
        return $this->fetchOne(
            "SELECT m.*, mc.description, mc.group_number, mc.unit, mc.unit_price
             FROM materials m
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE m.id = ?",
            [$id]
        );
    }

    /**
     * البحث في المواد
     */
    public function search($searchTerm)
    {
        $searchPattern = "%{$searchTerm}%";
        return $this->fetchAll(
            "SELECT m.*, mc.description, mc.group_number, mc.unit
             FROM materials m
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE (m.item_number LIKE ? OR mc.description LIKE ?) AND m.is_active = 1",
            [$searchPattern, $searchPattern]
        );
    }

    /**
     * الحصول على المواد النشطة
     */
    public function getActiveMaterials()
    {
        return $this->fetchAll(
            "SELECT m.*, mc.description, mc.group_number, mc.unit
             FROM materials m
             LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
             WHERE m.is_active = 1
             ORDER BY mc.description ASC"
        );
    }

    /**
     * الحصول على المواد منخفضة المخزون
     */
    public function getLowStockMaterials()
    {
        return $this->findWhere('current_stock <= minimum_stock AND is_active = 1');
    }

    /**
     * إنشاء مادة جديدة
     */
    public function createMaterial($data)
    {
        // التحقق من عدم وجود رقم البند
        if ($this->findByItemNumber($data['item_number'])) {
            return ['success' => false, 'message' => 'رقم البند موجود بالفعل'];
        }

        $data['created_at'] = getCurrentDateTime();
        $data['updated_at'] = getCurrentDateTime();

        try {
            $materialId = $this->insert($data);
            logActivity('create_material', "تم إنشاء مادة جديدة: {$data['item_number']}");
            return ['success' => true, 'material_id' => $materialId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في إنشاء المادة: ' . $e->getMessage()];
        }
    }

    /**
     * تحديث بيانات المادة
     */
    public function updateMaterial($id, $data)
    {
        $data['updated_at'] = getCurrentDateTime();

        try {
            $result = $this->update($id, $data);
            if ($result) {
                logActivity('update_material', "تم تحديث المادة ID: $id");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'فشل في تحديث البيانات'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث البيانات: ' . $e->getMessage()];
        }
    }

    /**
     * تحديث المخزون
     */
    public function updateStock($materialId, $quantity, $operation = 'add')
    {
        try {
            if ($operation === 'add') {
                // تحديث ذري: إضافة للمخزون
                $sql = "UPDATE materials SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$quantity, $materialId]);

                if ($stmt->rowCount() === 0) {
                    return ['success' => false, 'message' => 'المادة غير موجودة'];
                }
            } elseif ($operation === 'subtract') {
                // تحديث ذري: خصم من المخزون مع التحقق من التوفر
                $sql = "UPDATE materials SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ? AND current_stock >= ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$quantity, $materialId, $quantity]);

                if ($stmt->rowCount() === 0) {
                    // التحقق هل المادة غير موجودة أم الكمية غير كافية
                    $material = $this->findById($materialId);
                    if (!$material) {
                        return ['success' => false, 'message' => 'المادة غير موجودة'];
                    }
                    return ['success' => false, 'message' => 'الكمية المطلوبة أكبر من المخزون المتاح (المتوفر: ' . $material['current_stock'] . ')'];
                }
            } else {
                return ['success' => false, 'message' => 'نوع العملية غير صحيح'];
            }

            // جلب المخزون الجديد للسجل
            $material = $this->findById($materialId);
            $newStock = $material ? $material['current_stock'] : 0;

            logActivity('update_stock', "تم تحديث مخزون المادة ID: $materialId إلى $newStock");
            return ['success' => true, 'new_stock' => $newStock];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في تحديث المخزون: ' . $e->getMessage()];
        }
    }

    /**
     * حذف مادة (إلغاء تفعيل)
     */
    public function deleteMaterial($id)
    {
        try {
            // التحقق من عدم وجود معاملات مرتبطة
            $transactionCount = $this->fetchColumn(
                "SELECT COUNT(*) FROM transaction_details WHERE material_id = ?",
                [$id]
            );

            if ($transactionCount > 0) {
                return ['success' => false, 'message' => 'لا يمكن حذف المادة لوجود معاملات مرتبطة بها'];
            }

            $result = $this->update($id, [
                'is_active' => 0,
                'updated_at' => getCurrentDateTime()
            ]);

            if ($result) {
                logActivity('delete_material', "تم حذف المادة ID: $id");
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'فشل في حذف المادة'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'فشل في حذف المادة: ' . $e->getMessage()];
        }
    }

    /**
     * الحصول على المواد مجمعة حسب رقم المجموعة
     */
    public function getMaterialsGroupedByNumber()
    {
        $sql = "
            SELECT mc.group_number,
                   COUNT(*) as material_count
            FROM materials m
            LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            WHERE m.is_active = 1
            GROUP BY mc.group_number
            ORDER BY mc.group_number
        ";
        return $this->fetchAll($sql);
    }

    /**
     * الحصول على أرقام المجاميع المستخدمة
     */
    public function getUsedGroupNumbers()
    {
        $sql = "
            SELECT DISTINCT mc.group_number
            FROM materials m
            LEFT JOIN material_catalog mc ON m.item_number = mc.item_number
            WHERE m.is_active = 1
            ORDER BY mc.group_number
        ";
        return $this->fetchAll($sql);
    }

    /**
     * الحصول على إحصائيات المواد
     */
    public function getMaterialStats()
    {
        $stats = [];

        // إحصائيات المواد النشطة
        $stats['active_materials'] = $this->count('is_active = 1');
        $stats['total_materials'] = $this->count('1=1'); // جميع المواد (نشطة وغير نشطة)
        $stats['inactive_materials'] = $this->count('is_active = 0');

        // إحصائيات المخزون (للمواد النشطة فقط)
        $stats['low_stock_materials'] = $this->count('current_stock <= minimum_stock AND current_stock > 0 AND is_active = 1');
        $stats['out_of_stock_materials'] = $this->count('current_stock = 0 AND is_active = 1');

        return $stats;
    }

    /**
     * التحقق من توفر الكمية المطلوبة
     */
    public function checkAvailability($materialId, $requiredQuantity)
    {
        $material = $this->findById($materialId);
        if (!$material) {
            return ['available' => false, 'message' => 'المادة غير موجودة'];
        }

        if (!$material['is_active']) {
            return ['available' => false, 'message' => 'المادة غير نشطة'];
        }

        if ($material['current_stock'] < $requiredQuantity) {
            return [
                'available' => false,
                'message' => 'الكمية المطلوبة غير متوفرة',
                'available_quantity' => $material['current_stock']
            ];
        }

        return ['available' => true, 'material' => $material];
    }
}
?>