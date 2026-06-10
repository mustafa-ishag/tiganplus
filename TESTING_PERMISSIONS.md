# دليل اختبار نظام الصلاحيات

## مقدمة
هذا الدليل يشرح كيفية اختبار نظام الصلاحيات الجديد للتأكد من أنه يعمل بشكل صحيح.

## الخطوات الأساسية للاختبار

### 1. اختبار صلاحيات super_admin
1. سجل دخول باستخدام حساب super_admin (admin)
2. اذهب إلى صفحة المستخلصات: `/etganplus/public/extracts/index.php`
3. تحقق من أنك تستطيع:
   - ✅ عرض المستخلصات
   - ✅ إنشاء مستخلص جديد
   - ✅ تعديل المستخلصات
   - ✅ حذف المستخلصات
   - ✅ الموافقة على المستخلصات
   - ✅ تصدير المستخلصات
   - ✅ استيراد المستخلصات

### 2. اختبار صلاحيات regular_user
1. سجل دخول باستخدام حساب regular_user (saad)
2. اذهب إلى صفحة المستخلصات: `/etganplus/public/extracts/index.php`
3. تحقق من أنك تستطيع:
   - ✅ عرض المستخلصات
   - ❌ إنشاء مستخلص جديد (يجب أن يتم إعادة التوجيه)
   - ❌ تعديل المستخلصات (يجب أن يتم إعادة التوجيه)
   - ❌ حذف المستخلصات (يجب أن يتم إعادة التوجيه)

### 3. اختبار صلاحيات admin_manager
1. سجل دخول باستخدام حساب admin_manager (test_user)
2. اذهب إلى صفحة المستخلصات: `/etganplus/public/extracts/index.php`
3. تحقق من أنك تستطيع:
   - ✅ عرض المستخلصات
   - ✅ إنشاء مستخلص جديد
   - ✅ تعديل المستخلصات
   - ✅ حذف المستخلصات
   - ✅ الموافقة على المستخلصات
   - ✅ تصدير المستخلصات
   - ✅ استيراد المستخلصات

## اختبار AJAX

### 1. اختبار إنشاء مستخلص عبر AJAX
```bash
curl -X POST http://localhost/etganplus/public/extracts/partial/create-ajax.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "extract_number=TEST001&branch_id=1&department=test&extract_date=2025-10-23&work_order_ids=1&extract_values=1000&completion_dates=2025-10-23"
```

### 2. اختبار حذف مستخلص عبر AJAX
```bash
curl -X POST http://localhost/etganplus/public/extracts/partial/delete-ajax.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "extract_id=1"
```

## اختبار الصلاحيات المباشرة

### 1. منح صلاحية مباشرة للمستخدم
```php
grantPermission(6, 'extracts_create', 1);
```

### 2. إلغاء صلاحية مباشرة من المستخدم
```php
revokePermission(6, 'extracts_create');
```

## النتائج المتوقعة

### ✅ النجاح
- المستخدم يستطيع الوصول إلى الصفحات التي يملك صلاحيات لها
- المستخدم لا يستطيع الوصول إلى الصفحات التي لا يملك صلاحيات لها
- الصلاحيات تُحمّل بشكل صحيح من الدور والصلاحيات المباشرة

### ❌ الفشل
- المستخدم يستطيع الوصول إلى صفحات لا يملك صلاحيات لها
- الصلاحيات لا تُحمّل بشكل صحيح
- رسائل الخطأ غير واضحة

## استكشاف الأخطاء

### 1. التحقق من الصلاحيات المحملة
```php
echo "الصلاحيات المحملة: ";
print_r($_SESSION['permissions']);
```

### 2. التحقق من صلاحيات الدور
```sql
SELECT r.name, p.name 
FROM roles r 
JOIN role_permissions rp ON r.id = rp.role_id 
JOIN permissions p ON rp.permission_id = p.id 
WHERE r.name = 'regular_user' 
ORDER BY p.name;
```

### 3. التحقق من الصلاحيات المباشرة
```sql
SELECT u.username, p.name 
FROM users u 
JOIN user_permissions up ON u.id = up.user_id 
JOIN permissions p ON up.permission_id = p.id 
WHERE u.id = 6 
ORDER BY p.name;
```

