# تفاصيل المشاكل المنطقية في نظام المخزن
# Detailed Logical Issues in Inventory System

## 🔴 المشكلة الأولى: تناقض في تخزين الموقع

### الوصف:
المواد لها موقع واحد فقط (VARCHAR) لكن النظام يدعم عدة مواقع

### الأكواد المتناقضة:

**في Material.php:**
```php
// لا يوجد foreign key إلى inventory_locations
// المادة تملك فقط location VARCHAR
```

**في InventoryLocation.php (السطر 172-185):**
```php
public function getMaterialsInLocation($locationId) {
    $location = $this->findById($locationId);
    $sql = "SELECT m.* FROM materials m
            WHERE m.location = ? AND m.is_active = 1";
    return $this->fetchAll($sql, [$location['location_code']]);
}
```

### المشكلة:
- مادة واحدة لا يمكن أن تكون في عدة مواقع
- عند نقل مادة، يتم تحديث جميع الكميات
- **الحل المطلوب**: جدول وسيط `material_locations` يربط المواد بالمواقع

---

## 🔴 المشكلة الثانية: حالات الطلب غير المتسقة

### الوصف:
الكود يستخدم حالة `'approved'` لكن الجدول يحتوي على `'branch_approved'`

### الأكواد المتناقضة:

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
    // ❌ هذا الشرط لن يعمل أبداً إذا كانت الحالة 'branch_approved'
    $deductionResult = $this->deductMaterialsFromStock($requestId);
}
```

### التأثير:
- المخزون قد لا يُخصم أبداً
- الطلبات قد تبقى معلقة

---

## 🔴 المشكلة الثالثة: خصم المخزون المزدوج

### الوصف:
المخزون يُخصم مرتين: عند الإنشاء والموافقة

### الأكواد المتناقضة:

**في MaterialRequest.php (السطر 107-109):**
```php
// عند إنشاء الطلب
if ($material['current_stock'] < $detail['requested_quantity']) {
    return ['success' => false, 'message' => "الكمية المطلوبة غير متوفرة"];
}
```

**في MaterialRequest.php (السطر 249-254):**
```php
// عند الموافقة
if ($approvalLevel === 'project' && $newStatus === 'approved') {
    $deductionResult = $this->deductMaterialsFromStock($requestId);
}
```

### المشكلة:
- المخزون يُتحقق منه عند الإنشاء
- لكن لا يُحجز (reserved)
- إذا طلب شخصان نفس المادة:
  1. الطلب الأول: يُعتمد ويخصم المخزون ✓
  2. الطلب الثاني: كان معتمداً بالفعل (لم يُرفض) ✗
  3. لكن المخزون غير كافي! ✗

### الحل المطلوب:
```php
// عند الإنشاء: حجز المخزون
reserved_quantity = requested_quantity

// عند الموافقة: تأكيد الخصم
current_stock -= approved_quantity

// عند الرفض: إرجاع الحجز
reserved_quantity = 0
```

---

## 🔴 المشكلة الرابعة: عدم وجود آلية الرجوع

### الوصف:
عند رفض طلب، المخزون لا يُرجع

### الكود:

**في MaterialRequest.php (السطر 273-297):**
```php
public function rejectRequest($requestId, $rejectedBy, $rejectionReason) {
    // ❌ لا يوجد تحديث للمخزون
    $result = $this->update($requestId, [
        'status' => 'rejected',
        'rejected_by' => $rejectedBy,
        'rejected_at' => getCurrentDateTime(),
        'rejection_reason' => $rejectionReason,
        'updated_at' => getCurrentDateTime()
    ]);
}
```

### المشكلة:
- إذا تم رفض طلب بعد موافقة المستودع
- المخزون المخصوم لن يُرجع
- **فقدان المخزون**

---

## 🔴 المشكلة الخامسة: عدم تطابق أسماء الأعمدة

### الوصف:
الكود يستخدم `'type'` لكن الجدول يستخدم `'transaction_type'`

### الأكواد المتناقضة:

**في create_inventory_system.php (السطر 75):**
```sql
transaction_type ENUM('incoming', 'outgoing', 'transfer', 'return')
```

**في MaterialRequest.php (السطر 579):**
```php
$transactionData = [
    'type' => 'outgoing',  // ❌ اسم العمود خاطئ
    // يجب أن يكون 'transaction_type'
];
```

### التأثير:
- قد يسبب أخطاء في الإدراج
- البيانات قد لا تُحفظ بشكل صحيح

---

## 📋 ملخص المشاكل

| # | المشكلة | الملف | السطر | الخطورة |
|---|--------|------|------|--------|
| 1 | تخزين الموقع | Material.php + InventoryLocation.php | متعدد | 🔴 |
| 2 | حالات الطلب | MaterialRequest.php | 210-249 | 🔴 |
| 3 | خصم المخزون | MaterialRequest.php | 107-254 | 🔴 |
| 4 | عدم الرجوع | MaterialRequest.php | 273 | 🔴 |
| 5 | أسماء الأعمدة | MaterialRequest.php | 579 | 🟠 |

