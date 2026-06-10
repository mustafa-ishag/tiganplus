# الحلول المقترحة لمشاكل نظام المخزن
# Proposed Solutions for Inventory System Issues

## ✅ الحل 1: إضافة جدول وسيط للمواقع

### المشكلة:
مادة واحدة لا يمكن أن تكون في عدة مواقع

### الحل:
```sql
CREATE TABLE material_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 0,
    reserved_quantity DECIMAL(10,3) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (material_id) REFERENCES materials(id),
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id),
    UNIQUE KEY unique_material_location (material_id, location_id)
);

-- حذف العمود location من materials
ALTER TABLE materials DROP COLUMN location;
```

### الفائدة:
- مادة واحدة يمكن أن تكون في عدة مواقع
- تتبع دقيق للكميات في كل موقع
- حجز المخزون بشكل صحيح

---

## ✅ الحل 2: توحيد حالات الطلب

### المشكلة:
الكود يستخدم `'approved'` لكن الجدول يحتوي على `'branch_approved'`

### الحل:
```php
// في MaterialRequest.php
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

### الفائدة:
- توافق كامل بين الكود والجدول
- معالجة صحيحة للحالات

---

## ✅ الحل 3: نظام حجز المخزون

### المشكلة:
خصم المخزون المزدوج والتضارب

### الحل:
```php
// عند إنشاء الطلب
public function createRequest($data, $details = []) {
    // 1. التحقق من توفر المخزون
    foreach ($details as $detail) {
        $material = $materialModel->findById($detail['material_id']);
        $available = $material['current_stock'] - $material['reserved_quantity'];
        
        if ($available < $detail['requested_quantity']) {
            return ['success' => false, 'message' => 'المخزون غير كافي'];
        }
    }
    
    // 2. حجز المخزون
    foreach ($details as $detail) {
        $this->query(
            "UPDATE materials 
             SET reserved_quantity = reserved_quantity + ? 
             WHERE id = ?",
            [$detail['requested_quantity'], $detail['material_id']]
        );
    }
}

// عند الموافقة النهائية
public function approveRequest($requestId, $approvalLevel, $approvedBy) {
    if ($approvalLevel === 'branch' && $newStatus === 'branch_approved') {
        // خصم من المخزون الفعلي
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
    }
}

// عند الرفض
public function rejectRequest($requestId, $rejectedBy, $reason) {
    $requestDetails = $this->getRequestDetails($requestId);
    
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
}
```

### الفائدة:
- لا يمكن بيع مخزون غير متوفر
- إرجاع صحيح عند الرفض
- تتبع دقيق للمخزون

---

## ✅ الحل 4: توحيد أسماء الأعمدة

### المشكلة:
الكود يستخدم `'type'` لكن الجدول يستخدم `'transaction_type'`

### الحل:
```php
// في MaterialRequest.php - السطر 579
$transactionData = [
    'transaction_number' => $this->generateTransactionNumber('OUT'),
    'transaction_type' => 'outgoing',  // ✅ تصحيح
    'work_order_id' => $request['work_order_id'],
    // ... باقي البيانات
];
```

### الفائدة:
- توافق كامل مع الجدول
- لا توجد أخطاء في الإدراج

---

## ✅ الحل 5: إضافة جدول تاريخ المخزون

### الفائدة الإضافية:
```sql
CREATE TABLE stock_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    location_id INT,
    transaction_type ENUM('incoming', 'outgoing', 'transfer', 'adjustment'),
    quantity_change DECIMAL(10,3),
    previous_quantity DECIMAL(10,3),
    new_quantity DECIMAL(10,3),
    reference_id INT,
    reference_type VARCHAR(50),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (material_id) REFERENCES materials(id),
    FOREIGN KEY (location_id) REFERENCES inventory_locations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### الفائدة:
- تتبع كامل لحركة المخزون
- تدقيق سهل
- تقارير دقيقة

---

## 📊 ملخص الحلول

| الحل | المشكلة | التأثير | الأولوية |
|-----|--------|--------|---------|
| جدول وسيط | تناقض الموقع | تتبع دقيق | 🔴 عالية |
| توحيد الحالات | عدم التطابق | معالجة صحيحة | 🔴 عالية |
| حجز المخزون | خصم مزدوج | منع البيع الزائد | 🔴 عالية |
| توحيد الأعمدة | أخطاء الإدراج | استقرار | 🟠 متوسطة |
| تاريخ المخزون | تتبع | تدقيق | 🟡 منخفضة |

---

## 🚀 خطة التنفيذ

1. **المرحلة 1**: إضافة جدول `material_locations` وحجز المخزون
2. **المرحلة 2**: توحيد حالات الطلب وتصحيح الأعمدة
3. **المرحلة 3**: إضافة جدول تاريخ المخزون
4. **المرحلة 4**: اختبار شامل والتحقق من البيانات القديمة

