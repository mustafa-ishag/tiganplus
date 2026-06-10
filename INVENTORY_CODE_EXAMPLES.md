# أمثلة الأكواد التي توضح المشاكل
# Code Examples Showing the Issues

## 🔴 المشكلة 1: تناقض الموقع

### الكود الحالي (خاطئ):

**في Material.php:**
```php
// لا يوجد علاقة مع inventory_locations
protected $table = 'materials';
// العمود location هو VARCHAR فقط
```

**في InventoryLocation.php (السطر 172-185):**
```php
public function getMaterialsInLocation($locationId) {
    $location = $this->findById($locationId);
    if (!$location) {
        return [];
    }
    
    $sql = "
        SELECT m.*, 
               (m.current_stock * m.unit_price) as stock_value
        FROM materials m
        WHERE m.location = ? AND m.is_active = 1
        ORDER BY m.description
    ";
    return $this->fetchAll($sql, [$location['location_code']]);
}
```

### المشكلة:
- مادة واحدة لا يمكن أن تكون في عدة مواقع
- عند نقل مادة، يتم تحديث جميع الكميات

### الحل المقترح:
```sql
-- جدول جديد
CREATE TABLE material_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(10,3) NOT NULL DEFAULT 0,
    FOREIGN KEY (material_id) REFERENCES materials(id),
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id),
    UNIQUE KEY unique_material_location (material_id, location_id)
);

-- حذف العمود location من materials
ALTER TABLE materials DROP COLUMN location;
```

---

## 🔴 المشكلة 2: حالات الطلب غير المتسقة

### الكود الحالي (خاطئ):

**في create_inventory_system.php (السطر 162):**
```sql
status ENUM('draft', 'submitted', 'warehouse_approved', 
           'project_approved', 'branch_approved', 'completed', 
           'rejected', 'cancelled')
```

**في MaterialRequest.php (السطر 210-212):**
```php
$statusMap = [
    'warehouse' => ['from' => 'submitted', 'to' => 'warehouse_approved'],
    'project' => ['from' => 'warehouse_approved', 'to' => 'approved']
    // ❌ 'approved' غير موجود في الجدول!
];
```

**في MaterialRequest.php (السطر 249):**
```php
if ($approvalLevel === 'project' && $newStatus === 'approved') {
    // ❌ هذا الشرط لن يعمل أبداً
    $deductionResult = $this->deductMaterialsFromStock($requestId);
}
```

### المشكلة:
- الكود يستخدم حالة غير موجودة في الجدول
- المخزون قد لا يُخصم أبداً

### الحل المقترح:
```php
private $statusMap = [
    'warehouse' => [
        'from' => 'submitted',
        'to' => 'warehouse_approved'
    ],
    'project' => [
        'from' => 'warehouse_approved',
        'to' => 'project_approved'  // ✅ تصحيح
    ],
    'branch' => [
        'from' => 'project_approved',
        'to' => 'branch_approved'   // ✅ إضافة
    ]
];

// تحديث الشرط
if ($approvalLevel === 'branch' && $newStatus === 'branch_approved') {
    $deductionResult = $this->deductMaterialsFromStock($requestId);
}
```

---

## 🔴 المشكلة 3: خصم المخزون المزدوج

### الكود الحالي (خاطئ):

**في MaterialRequest.php (السطر 107-109):**
```php
// عند إنشاء الطلب - التحقق فقط
if ($material['current_stock'] < $detail['requested_quantity']) {
    return ['success' => false, 'message' => "الكمية المطلوبة غير متوفرة"];
}
// ❌ لا يتم حجز المخزون
```

**في MaterialRequest.php (السطر 249-254):**
```php
// عند الموافقة - الخصم الفعلي
if ($approvalLevel === 'project' && $newStatus === 'approved') {
    $deductionResult = $this->deductMaterialsFromStock($requestId);
}
```

### المشكلة:
```
الطلب الأول:
1. التحقق: المخزون = 100 ✓
2. الموافقة: خصم 100 ✓
3. المخزون الآن = 0

الطلب الثاني (في نفس الوقت):
1. التحقق: المخزون = 100 ✓ (لم يتم تحديثه بعد)
2. الموافقة: خصم 100 ✓
3. المخزون الآن = -100 ❌ (سالب!)
```

### الحل المقترح:
```php
// عند إنشاء الطلب - حجز المخزون
foreach ($details as $detail) {
    $this->query(
        "UPDATE materials 
         SET reserved_quantity = reserved_quantity + ? 
         WHERE id = ?",
        [$detail['requested_quantity'], $detail['material_id']]
    );
}

// عند الموافقة - خصم من المخزون الفعلي
foreach ($requestDetails as $detail) {
    $this->query(
        "UPDATE materials 
         SET current_stock = current_stock - ?,
             reserved_quantity = reserved_quantity - ?
         WHERE id = ?",
        [$detail['approved_quantity'], 
         $detail['approved_quantity'], 
         $detail['material_id']]
    );
}
```

---

## 🔴 المشكلة 4: عدم وجود آلية الرجوع

### الكود الحالي (خاطئ):

**في MaterialRequest.php (السطر 273-297):**
```php
public function rejectRequest($requestId, $rejectedBy, $rejectionReason) {
    try {
        $request = $this->findById($requestId);
        if (!$request) {
            return ['success' => false, 'message' => 'الطلب غير موجود'];
        }
        
        $result = $this->update($requestId, [
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejected_at' => getCurrentDateTime(),
            'rejection_reason' => $rejectionReason,
            'updated_at' => getCurrentDateTime()
        ]);
        // ❌ لا يوجد تحديث للمخزون!
        
        if ($result) {
            logActivity('reject_material_request', "تم رفض طلب الصرف: {$request['request_number']}");
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'فشل في رفض الطلب'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'فشل في رفض الطلب: ' . $e->getMessage()];
    }
}
```

### المشكلة:
- المخزون المحجوز لا يُرجع
- فقدان المخزون بشكل دائم

### الحل المقترح:
```php
public function rejectRequest($requestId, $rejectedBy, $reason) {
    try {
        $this->beginTransaction();
        
        $request = $this->findById($requestId);
        if (!$request) {
            $this->rollback();
            return ['success' => false, 'message' => 'الطلب غير موجود'];
        }
        
        // الحصول على تفاصيل الطلب
        $requestDetails = $this->fetchAll(
            "SELECT * FROM material_request_details WHERE request_id = ?",
            [$requestId]
        );
        
        // إرجاع الحجز
        foreach ($requestDetails as $detail) {
            $this->query(
                "UPDATE materials 
                 SET reserved_quantity = reserved_quantity - ? 
                 WHERE id = ?",
                [$detail['requested_quantity'], $detail['material_id']]
            );
        }
        
        // تحديث الحالة
        $this->update($requestId, [
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejection_reason' => $reason
        ]);
        
        $this->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $this->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

---

## 🔴 المشكلة 5: عدم تطابق أسماء الأعمدة

### الكود الحالي (خاطئ):

**في create_inventory_system.php (السطر 75):**
```sql
transaction_type ENUM('incoming', 'outgoing', 'transfer', 'return')
```

**في MaterialRequest.php (السطر 579):**
```php
$transactionData = [
    'transaction_number' => $this->generateTransactionNumber('OUT'),
    'type' => 'outgoing',  // ❌ اسم العمود خاطئ
    'work_order_id' => $request['work_order_id'],
    // ...
];
```

### المشكلة:
- اسم العمود مختلف
- قد يسبب أخطاء في الإدراج

### الحل المقترح:
```php
$transactionData = [
    'transaction_number' => $this->generateTransactionNumber('OUT'),
    'transaction_type' => 'outgoing',  // ✅ تصحيح
    'work_order_id' => $request['work_order_id'],
    // ...
];
```

---

## 📋 ملخص الأكواد

| المشكلة | الملف | السطر | الحل |
|--------|------|------|-----|
| تناقض الموقع | Material.php | متعدد | جدول وسيط |
| حالات الطلب | MaterialRequest.php | 210-249 | توحيد الحالات |
| خصم مزدوج | MaterialRequest.php | 107-254 | حجز المخزون |
| عدم الرجوع | MaterialRequest.php | 273 | إرجاع الحجز |
| أسماء الأعمدة | MaterialRequest.php | 579 | توحيد الأسماء |

