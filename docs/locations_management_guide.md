# دليل نظام إدارة مواقع التخزين

## 📋 نظرة عامة

تم إنشاء نظام شامل لإدارة مواقع التخزين والمستودعات في نظام تقان ERP. يوفر هذا النظام إمكانيات كاملة لإدارة المواقع مع ربطها بالفروع والمواد.

## 🎯 الميزات الرئيسية

### ✅ إدارة المواقع
- **إضافة مواقع جديدة** مع كود فريد واسم وصفي
- **تعديل بيانات المواقع** الموجودة
- **عرض تفاصيل شاملة** لكل موقع
- **حذف أو إلغاء تفعيل** المواقع غير المستخدمة
- **ربط المواقع بالفروع** لتنظيم أفضل

### 📊 الإحصائيات والتقارير
- **إحصائيات سريعة** لكل موقع
- **عدد المواد المخزنة** في كل موقع
- **قيمة المخزون** الإجمالية
- **المواد منخفضة المخزون** في كل موقع
- **تاريخ المعاملات** الأخيرة

### 🔍 البحث والفلترة
- **البحث النصي** في كود أو اسم الموقع
- **فلترة حسب الفرع** المرتبط
- **فلترة حسب الحالة** (نشط/غير نشط)
- **ترتيب النتائج** حسب معايير مختلفة

## 🗂️ هيكل الملفات

```
public/inventory/locations/
├── index.php          # قائمة المواقع الرئيسية
├── create.php         # إضافة موقع جديد
├── edit.php           # تعديل موقع موجود
├── view.php           # عرض تفاصيل الموقع
└── delete.php         # حذف الموقع (AJAX)
```

## 🔐 نظام الصلاحيات

### الصلاحيات المطلوبة

| الصلاحية | الوصف | الاستخدام |
|---------|-------|----------|
| `inventory_locations_view` | عرض مواقع التخزين | الوصول لقائمة المواقع |
| `inventory_locations_create` | إنشاء مواقع التخزين | إضافة مواقع جديدة |
| `inventory_locations_edit` | تعديل مواقع التخزين | تحرير بيانات المواقع |
| `inventory_locations_delete` | حذف مواقع التخزين | حذف المواقع غير المستخدمة |
| `inventory_locations_view_details` | عرض تفاصيل الموقع | صفحة التفاصيل الشاملة |
| `inventory_locations_manage` | إدارة مواقع التخزين | إدارة شاملة للمواقع |

### ربط الصلاحيات بالوظائف

```php
// مثال على فحص الصلاحيات
if (!hasPermission('inventory_locations_view')) {
    header('Location: ' . path('dashboard.php'));
    exit();
}
```

## 🗄️ قاعدة البيانات

### جدول inventory_locations

```sql
CREATE TABLE inventory_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_code VARCHAR(20) UNIQUE NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    branch_id INT,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);
```

### البيانات الافتراضية

```sql
INSERT INTO inventory_locations (location_code, location_name, branch_id, description) VALUES
('TAF-MAIN', 'المستودع الرئيسي - الطائف', 1, 'المستودع الرئيسي لفرع الطائف'),
('TAF-SEC', 'المستودع الثانوي - الطائف', 1, 'المستودع الثانوي لفرع الطائف'),
('RAN-MAIN', 'المستودع الرئيسي - رنية', 2, 'المستودع الرئيسي لفرع رنية'),
('RAN-SEC', 'المستودع الثانوي - رنية', 2, 'المستودع الثانوي لفرع رنية');
```

## 🔧 نموذج البيانات (InventoryLocation)

### الوظائف الرئيسية

```php
// البحث عن موقع بالكود
$location = $locationModel->findByLocationCode('TAF-MAIN');

// الحصول على المواقع النشطة
$activeLocations = $locationModel->getActiveLocations();

// الحصول على مواقع فرع معين
$branchLocations = $locationModel->findByBranch($branchId);

// إنشاء موقع جديد
$result = $locationModel->createLocation($data);

// تحديث موقع
$result = $locationModel->update($locationId, $data);

// حذف موقع (إلغاء تفعيل)
$result = $locationModel->deleteLocation($locationId);

// الحصول على الإحصائيات
$stats = $locationModel->getLocationStats();
```

## 🎨 واجهة المستخدم

### الصفحة الرئيسية (index.php)

- **رأس الصفحة** مع عنوان وزر إضافة موقع جديد
- **إحصائيات سريعة** في بطاقات ملونة
- **نموذج البحث والفلترة** مع خيارات متعددة
- **جدول المواقع** مع معلومات شاملة
- **أزرار الإجراءات** (عرض، تعديل، حذف)

### صفحة الإضافة (create.php)

- **نموذج إدخال** مع التحقق من صحة البيانات
- **اقتراح تلقائي** لكود الموقع
- **ربط بالفروع** من قائمة منسدلة
- **تحويل تلقائي** لكود الموقع إلى أحرف كبيرة

### صفحة التعديل (edit.php)

- **نموذج محمل** ببيانات الموقع الحالية
- **معلومات إضافية** عن استخدام الموقع
- **تحذيرات** عند تعديل مواقع تحتوي على مواد

### صفحة التفاصيل (view.php)

- **معلومات شاملة** عن الموقع
- **إحصائيات مفصلة** للمواد والمعاملات
- **جدول المواد المخزنة** في الموقع
- **آخر المعاملات** المسجلة

## 🔗 التكامل مع النظام

### ربط مع المواد

```php
// في نموذج المواد
$locations = $locationModel->getActiveLocations();

// في نموذج إضافة المواد
<select name="location" class="form-select">
    <?php foreach ($locations as $location): ?>
    <option value="<?= $location['location_code'] ?>">
        <?= $location['location_name'] ?>
    </option>
    <?php endforeach; ?>
</select>
```

### ربط مع المعاملات

```php
// في نموذج المعاملات
$locationId = $_POST['location_id'];
$location = $locationModel->findById($locationId);
```

### القائمة الجانبية

```php
<?php if (hasPermission('inventory_locations_view')): ?>
<li class="nav-item">
    <a class="nav-link <?= $currentPage === 'inventory-locations' ? 'active' : '' ?>" 
       href="<?= path('inventory/locations/index.php') ?>">
        <i class="fas fa-warehouse"></i>
        إدارة مواقع التخزين
    </a>
</li>
<?php endif; ?>
```

## 🛡️ الأمان والتحقق

### التحقق من البيانات

```php
// التحقق من كود الموقع
if (!preg_match('/^[A-Z0-9-_]+$/', $locationCode)) {
    $errors[] = 'كود الموقع يجب أن يحتوي على أحرف إنجليزية كبيرة وأرقام وشرطات فقط';
}

// التحقق من عدم التكرار
$existingLocation = $locationModel->findByLocationCode($locationCode);
if ($existingLocation) {
    $errors[] = 'كود الموقع موجود بالفعل';
}
```

### الحماية من الحذف

```php
// التحقق من وجود مواد مرتبطة
$materialCount = $db->prepare("SELECT COUNT(*) FROM materials WHERE location = ?");
$materialCount->execute([$locationCode]);

if ($materialCount->fetchColumn() > 0) {
    return ['success' => false, 'message' => 'لا يمكن حذف الموقع لوجود مواد مخزنة فيه'];
}
```

## 📱 الاستجابة والتفاعل

### JavaScript المستخدم

```javascript
// حذف موقع مع تأكيد
function deleteLocation(locationId) {
    Swal.fire({
        title: 'تأكيد الحذف',
        text: 'هل أنت متأكد من حذف هذا الموقع؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم، احذف',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            // إرسال طلب AJAX للحذف
        }
    });
}

// اقتراح كود الموقع تلقائياً
function generateLocationCode() {
    const branchSelect = document.getElementById('branch_id');
    const locationName = document.getElementById('location_name').value;
    // منطق اقتراح الكود
}
```

## 🧪 الاختبار

### ملف الاختبار

```bash
# تشغيل اختبار النظام
http://localhost/etganplus/test_locations_system.php
```

### اختبارات الوحدة

```php
// اختبار إنشاء موقع
$result = $locationModel->createLocation([
    'location_code' => 'TEST-001',
    'location_name' => 'موقع اختبار',
    'branch_id' => 1,
    'description' => 'موقع للاختبار'
]);

assert($result['success'] === true);
```

## 🚀 التطوير المستقبلي

### ميزات مقترحة

1. **خرائط المواقع** - إضافة إحداثيات GPS
2. **إدارة الرفوف** - تقسيم المواقع إلى رفوف
3. **تتبع الحركة** - تتبع حركة المواد بين المواقع
4. **تقارير متقدمة** - تقارير استخدام المواقع
5. **إشعارات** - تنبيهات عند امتلاء المواقع

### التحسينات التقنية

1. **فهرسة قاعدة البيانات** لتحسين الأداء
2. **ذاكرة التخزين المؤقت** للمواقع المستخدمة بكثرة
3. **API RESTful** للتكامل مع أنظمة خارجية
4. **تطبيق جوال** لإدارة المواقع

## 📞 الدعم والصيانة

### المشاكل الشائعة

1. **خطأ في كود الموقع** - تأكد من استخدام أحرف إنجليزية كبيرة فقط
2. **عدم ظهور الموقع في القوائم** - تأكد من أن الموقع نشط
3. **فشل الحذف** - تأكد من عدم وجود مواد مرتبطة بالموقع

### سجلات النشاط

```php
// تسجيل العمليات
logActivity('create_inventory_location', "تم إنشاء موقع تخزين جديد: $locationCode");
logActivity('update_inventory_location', "تم تحديث موقع التخزين: $locationCode");
logActivity('delete_inventory_location', "تم حذف موقع التخزين: $locationCode");
```

---

## 📝 ملاحظات المطور

- تم إنشاء النظام بتاريخ: 2025-09-27
- متوافق مع: PHP 8.0+, MySQL 8.0+, Bootstrap 5.1+
- يتطلب: صلاحيات إدارة المخزون
- مطور بواسطة: Augment Agent

**تم إنجاز المشروع بنجاح! 🎉**
