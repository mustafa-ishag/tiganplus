# دليل تحديث ملفات المستخلصات لاستخدام الصلاحيات
## Guide to Update Extracts Files to Use Permissions

---

## 📋 الملفات التي تحتاج تحديث

### 1. ملفات المستخلصات الجزئية
**المسار**: `public/extracts/partial/`

- `index.php` - إضافة `hasPermission('extracts_view')`
- `create.php` - إضافة `hasPermission('extracts_create')`
- `create-ajax.php` - إضافة `hasPermission('extracts_create')`
- `edit.php` - إضافة `hasPermission('extracts_edit')`
- `edit-ajax.php` - إضافة `hasPermission('extracts_edit')`
- `delete-ajax.php` - إضافة `hasPermission('extracts_delete')`
- `view.php` - إضافة `hasPermission('extracts_view_details')`
- `export.php` - إضافة `hasPermission('extracts_export')`
- `import.php` - إضافة `hasPermission('extracts_import')`
- `import-preview.php` - إضافة `hasPermission('extracts_import')`
- `import-confirm.php` - إضافة `hasPermission('extracts_import')`
- `upload-attachment-ajax.php` - إضافة `hasPermission('extracts_attachments')`
- `delete-attachment-ajax.php` - إضافة `hasPermission('extracts_attachments')`
- `update-approval-ajax.php` - إضافة `hasPermission('extracts_approve')`
- `update-completion-dates-ajax.php` - إضافة `hasPermission('extracts_update_fields')`

### 2. ملفات المستخلصات النهائية العادية
**المسار**: `public/extracts/final-regular/`

- نفس الملفات كالمستخلصات الجزئية

### 3. ملفات المستخلصات النهائية للجزئية
**المسار**: `public/extracts/final-for-partial/`

- نفس الملفات كالمستخلصات الجزئية

---

## 🔧 نمط التحديث

### في ملفات الصفحات الرئيسية (index.php, create.php, view.php, edit.php):

```php
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . path('auth/login.php'));
    exit();
}

// التحقق من الصلاحيات ⭐ إضافة هذا
if (!hasPermission('extracts_view')) {  // أو الصلاحية المناسبة
    header('Location: ' . path('dashboard.php'));
    exit();
}
```

### في ملفات AJAX:

```php
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit();
}

// التحقق من الصلاحيات ⭐ إضافة هذا
if (!hasPermission('extracts_create')) {  // أو الصلاحية المناسبة
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لهذا الإجراء']);
    exit();
}
```

### في الواجهة (عرض الأزرار):

```php
<!-- إضافة أمر عمل جديد -->
<?php if (hasPermission('extracts_create')): ?>
<button type="button" class="btn btn-primary" onclick="openCreateModal()">
    <i class="fas fa-plus me-2"></i>
    إضافة مستخلص جديد
</button>
<?php endif; ?>

<!-- تصدير -->
<?php if (hasPermission('extracts_export')): ?>
<button type="button" class="btn btn-success" onclick="openExportModal()">
    <i class="fas fa-file-export me-2"></i>
    تصدير
</button>
<?php endif; ?>

<!-- استيراد -->
<?php if (hasPermission('extracts_import')): ?>
<a href="import.php" class="btn btn-info">
    <i class="fas fa-file-import me-2"></i>
    استيراد
</a>
<?php endif; ?>
```

---

## 📊 جدول الصلاحيات المطلوبة لكل ملف

| الملف | الصلاحية | الوصف |
|------|---------|-------|
| index.php | extracts_view | عرض قائمة المستخلصات |
| create.php | extracts_create | إنشاء مستخلص جديد |
| create-ajax.php | extracts_create | معالج إنشاء المستخلص |
| edit.php | extracts_edit | تعديل المستخلص |
| edit-ajax.php | extracts_edit | معالج تعديل المستخلص |
| delete-ajax.php | extracts_delete | حذف المستخلص |
| view.php | extracts_view_details | عرض تفاصيل المستخلص |
| export.php | extracts_export | تصدير البيانات |
| import.php | extracts_import | استيراد البيانات |
| import-preview.php | extracts_import | معاينة الاستيراد |
| import-confirm.php | extracts_import | تأكيد الاستيراد |
| upload-attachment-ajax.php | extracts_attachments | رفع المرفقات |
| delete-attachment-ajax.php | extracts_attachments | حذف المرفقات |
| update-approval-ajax.php | extracts_approve | تحديث مرحلة الاعتماد |
| update-completion-dates-ajax.php | extracts_update_fields | تحديث التواريخ |

---

## ✅ الخطوات التنفيذية

1. **تشغيل السكريبت**: `php database/redesign_extracts_permissions_v2.php`
2. **تحديث ملفات المستخلصات الجزئية**
3. **تحديث ملفات المستخلصات النهائية العادية**
4. **تحديث ملفات المستخلصات النهائية للجزئية**
5. **اختبار النظام**

---

## 🎯 الفوائد

✅ صلاحيات موحدة وسهلة الإدارة
✅ نفس النمط المستخدم في أوامر العمل
✅ حماية أفضل للبيانات
✅ تحكم دقيق على من يمكنه الوصول لكل ميزة
✅ سهولة إضافة صلاحيات جديدة في المستقبل

