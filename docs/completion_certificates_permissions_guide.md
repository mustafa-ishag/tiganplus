# دليل صلاحيات شهادات الإنجاز المحدثة
## Updated Completion Certificates Permissions Guide

تم تحديث وتوحيد نظام صلاحيات شهادات الإنجاز لتحسين التنظيم وإزالة التكرار.

## الصلاحيات الجديدة الموحدة

جميع صلاحيات شهادات الإنجاز تحت تصنيف: `inventory/certificates`

### 1. صلاحيات العرض
- **`inventory_certificates_view`** - عرض شهادات الإنجاز
  - عرض قائمة شهادات الإنجاز
  - مستخدمة في: `index.php`

- **`inventory_certificates_view_details`** - عرض تفاصيل شهادة الإنجاز
  - عرض التفاصيل الكاملة لشهادة إنجاز محددة
  - مستخدمة في: `view.php`

### 2. صلاحيات الإنشاء والتعديل
- **`inventory_certificates_create`** - إنشاء شهادات الإنجاز
  - إنشاء شهادات إنجاز جديدة
  - مستخدمة في: `create.php`, `index.php`

- **`inventory_certificates_edit`** - تعديل شهادات الإنجاز
  - تعديل بيانات شهادات الإنجاز الموجودة
  - مستخدمة في: `edit.php`, `index.php`, `view.php`

- **`inventory_certificates_auto_generate`** - التوليد التلقائي للشهادات
  - توليد شهادات الإنجاز تلقائياً من بيانات أوامر العمل
  - مستخدمة في: ملفات التوليد التلقائي

### 3. صلاحيات الحذف
- **`inventory_certificates_delete`** - حذف شهادات الإنجاز
  - حذف شهادات الإنجاز من النظام
  - مستخدمة في: `delete.php`, `index.php`

### 4. صلاحيات الإدارة
- **`inventory_certificates_manage`** - إدارة شهادات الإنجاز
  - إدارة عامة لشهادات الإنجاز
  - صلاحية شاملة للمديرين

- **`inventory_certificates_status_update`** - تحديث حالة الشهادات
  - تحديث حالة شهادات الإنجاز (مسودة، مكتملة، معتمدة)
  - مستخدمة في: `update-status-ajax.php`, `index.php`, `view.php`

### 5. صلاحيات الاعتماد
- **`inventory_certificates_approve`** - اعتماد شهادات الإنجاز
  - اعتماد ورفض شهادات الإنجاز
  - للمديرين والمعتمدين

### 6. صلاحيات الطباعة والتصدير
- **`inventory_certificates_print`** - طباعة شهادات الإنجاز
  - طباعة شهادات الإنجاز

- **`inventory_certificates_export`** - تصدير شهادات الإنجاز
  - تصدير بيانات شهادات الإنجاز

## الصلاحيات المحذوفة

تم حذف الصلاحيات التالية لإزالة التكرار:

### صلاحيات قديمة متكررة:
- `completion_certificates_view` → `inventory_certificates_view`
- `completion_certificates_create` → `inventory_certificates_create`
- `completion_certificates_edit` → `inventory_certificates_edit`
- `completion_certificates_delete` → `inventory_certificates_delete`
- `create_completion_certificates` → `inventory_certificates_create`
- `edit_completion_certificates` → `inventory_certificates_edit`
- `delete_completion_certificates` → `inventory_certificates_delete`

### صلاحيات غير موجودة في قاعدة البيانات:
- `view_completion_certificates` → `inventory_certificates_view_details`
- `manage_completion_certificates` → `inventory_certificates_status_update`

### صلاحيات أخرى محذوفة:
- `work_orders_manage_completion_certificates`
- `work_orders_upload_certificate_files`
- `approve_completion_certificates`
- `print_completion_certificates`
- `completion_certificates_auto_generate`
- `completion_certificates_view_details`

## الملفات المحدثة

تم تحديث الملفات التالية لاستخدام الصلاحيات الجديدة:

1. **`public/inventory/completion-certificates/index.php`**
   - `completion_certificates_view` → `inventory_certificates_view`
   - `create_completion_certificates` → `inventory_certificates_create`
   - `edit_completion_certificates` → `inventory_certificates_edit`
   - `manage_completion_certificates` → `inventory_certificates_status_update`
   - `delete_completion_certificates` → `inventory_certificates_delete`

2. **`public/inventory/completion-certificates/create.php`**
   - `create_completion_certificates` → `inventory_certificates_create`

3. **`public/inventory/completion-certificates/edit.php`**
   - `edit_completion_certificates` → `inventory_certificates_edit`

4. **`public/inventory/completion-certificates/view.php`**
   - `view_completion_certificates` → `inventory_certificates_view_details`
   - `edit_completion_certificates` → `inventory_certificates_edit`
   - `manage_completion_certificates` → `inventory_certificates_status_update`

5. **`public/inventory/completion-certificates/delete.php`**
   - `delete_completion_certificates` → `inventory_certificates_delete`

6. **`public/inventory/completion-certificates/update-status-ajax.php`**
   - `manage_completion_certificates` → `inventory_certificates_status_update`

## التصنيف في صفحة إدارة الصلاحيات

في صفحة إدارة الصلاحيات (`public/roles/permissions.php`)، تظهر صلاحيات شهادات الإنجاز تحت فئة **"شهادات الإنجاز"** ضمن وحدة المخزون.

## الإحصائيات النهائية

- **إجمالي صلاحيات شهادات الإنجاز:** 11 صلاحية
- **الصلاحيات المحذوفة:** 15 صلاحية متكررة/متضاربة
- **الملفات المحدثة:** 6 ملفات
- **التصنيف:** `inventory/certificates`

## ملاحظات مهمة

1. **التوافق مع الإصدارات السابقة:** تم الحفاظ على جميع الوظائف مع تحديث أسماء الصلاحيات فقط
2. **الأمان:** لم يتم تغيير منطق التحقق من الصلاحيات، فقط أسماء الصلاحيات
3. **التنظيم:** جميع الصلاحيات الآن منظمة تحت نظام موحد
4. **سهولة الإدارة:** أصبح من السهل إدارة صلاحيات شهادات الإنجاز من صفحة واحدة

---

**تاريخ التحديث:** 2025-09-27  
**الإصدار:** 1.0  
**المطور:** Augment Agent
