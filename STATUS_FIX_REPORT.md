# 🔧 تقرير إصلاح مشكلة الحالة
# Status Fix Report

**التاريخ**: 2025-10-31  
**الإصدار**: 2.4  
**الحالة**: ✅ مكتمل

---

## 🎯 المشكلة

عند إنشاء طلب صرف وتتم الموافقة عليه:
- ❌ لا يتم خصم المخزون
- ❌ يظهر الطلب بحالة "مسودة" بدلاً من "معتمد"
- ❌ الحالة تُحفظ كـ NULL بدلاً من القيمة الصحيحة

---

## 🔍 السبب الجذري

### المشكلة الأساسية:
عمود `status` في جدول `material_requests` هو نوع `ENUM` بالقيم المسموحة:
```
'draft', 'submitted', 'warehouse_approved', 'approved', 'rejected', 'cancelled'
```

لكن الكود كان يحاول تعيين قيمة غير موجودة:
```php
'project' => ['from' => 'warehouse_approved', 'to' => 'project_approved']  // ❌ خطأ
```

القيمة `project_approved` ليست من القيم المسموحة في ENUM، لذلك MySQL ترفضها وتعيّن NULL بدلاً منها.

---

## ✅ الحل المطبق

### 1️⃣ تحديث statusMap في MaterialRequest.php

**الملف**: `models/MaterialRequest.php`  
**السطور**: 275-281

```php
// ❌ قبل الإصلاح:
$statusMap = [
    'warehouse' => ['from' => 'submitted', 'to' => 'warehouse_approved'],
    'project' => ['from' => 'warehouse_approved', 'to' => 'project_approved'],
    'branch' => ['from' => 'project_approved', 'to' => 'branch_approved']
];

// ✅ بعد الإصلاح:
$statusMap = [
    'warehouse' => ['from' => 'submitted', 'to' => 'warehouse_approved'],
    'project' => ['from' => 'warehouse_approved', 'to' => 'approved'],
    'branch' => ['from' => 'approved', 'to' => 'approved']
];
```

### 2️⃣ تحديث شرط الخصم

**الملف**: `models/MaterialRequest.php`  
**السطر**: 318

```php
// ❌ قبل الإصلاح:
if ($approvalLevel === 'project' && $newStatus === 'project_approved') {

// ✅ بعد الإصلاح:
if ($approvalLevel === 'project' && $newStatus === 'approved') {
```

### 3️⃣ تحديث الاستعلام الإحصائي

**الملف**: `models/MaterialRequest.php`  
**السطور**: 460-471

```php
// ❌ قبل الإصلاح:
SUM(CASE WHEN mr.status = 'project_approved' THEN 1 ELSE 0 END) as project_approved_requests,
SUM(CASE WHEN mr.status IN ('submitted', 'warehouse_approved', 'project_approved') THEN 1 ELSE 0 END) as pending_requests

// ✅ بعد الإصلاح:
SUM(CASE WHEN mr.status = 'approved' THEN 1 ELSE 0 END) as project_approved_requests,
SUM(CASE WHEN mr.status IN ('submitted', 'warehouse_approved', 'approved') THEN 1 ELSE 0 END) as pending_requests
```

### 4️⃣ تحديث statusLabels في الواجهة

**الملف**: `public/inventory/material-requests/index.php`  
**السطور**: 376-383

```php
// ❌ قبل الإصلاح:
$statusLabels = [
    'draft' => ['مسودة', 'secondary'],
    'submitted' => ['مرسل', 'info'],
    'warehouse_approved' => ['موافقة المستودع', 'primary'],
    'approved' => ['معتمد نهائياً', 'success'],
    'project_approved' => ['معتمد نهائياً', 'success'],  // ❌ غير موجود في ENUM
    'branch_approved' => ['معتمد نهائياً', 'success'],   // ❌ غير موجود في ENUM
    'rejected' => ['مرفوض', 'danger'],
    'cancelled' => ['ملغي', 'warning']
];

// ✅ بعد الإصلاح:
$statusLabels = [
    'draft' => ['مسودة', 'secondary'],
    'submitted' => ['مرسل', 'info'],
    'warehouse_approved' => ['موافقة المستودع', 'primary'],
    'approved' => ['معتمد نهائياً', 'success'],
    'rejected' => ['مرفوض', 'danger'],
    'cancelled' => ['ملغي', 'warning']
];
```

### 5️⃣ إصلاح الطلبات القديمة

تم تشغيل `fix_null_statuses.php` لإصلاح 6 طلبات بحالة NULL:
```sql
UPDATE material_requests SET status = 'draft' WHERE status IS NULL OR status = ''
```

**النتيجة**: تم إصلاح 6 طلبات بنجاح

---

## 📊 النتائج

### قبل الإصلاح:
```
NULL/فارغ: 6 طلب
draft: 20 طلب
submitted: 1 طلب
rejected: 4 طلب
```

### بعد الإصلاح:
```
draft: 26 طلب
submitted: 1 طلب
approved: 1 طلب
rejected: 4 طلب
```

---

## 🧪 الاختبارات

### اختبار سير العمل الكامل:
```
✅ إنشاء طلب: ID = 48
✅ إرسال الطلب: status = submitted
✅ موافقة المستودع: status = warehouse_approved
✅ موافقة المشروع: status = approved
✅ خصم المخزون: تم بنجاح
```

---

## 📝 الملفات المعدلة

1. ✅ `models/MaterialRequest.php` - تحديث statusMap والشروط
2. ✅ `public/inventory/material-requests/index.php` - تحديث statusLabels
3. ✅ `fix_null_statuses.php` - إصلاح الطلبات القديمة

---

## 🎉 الخلاصة

✅ **المشكلة محلولة بنجاح**
- الحالات تُحفظ بشكل صحيح
- الخصم يتم عند الموافقة النهائية
- الواجهة تعرض الحالات الصحيحة
- جميع الطلبات القديمة تم إصلاحها

---

**آخر تحديث**: 2025-10-31  
**الإصدار**: 2.4  
**الحالة**: ✅ جاهز للإنتاج

