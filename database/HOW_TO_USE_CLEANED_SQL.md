# 📖 دليل استخدام ملف SQL المنظف
## How to Use the Cleaned SQL File

---

## 📋 معلومات الملف

| المعلومة | القيمة |
|---------|--------|
| **اسم الملف** | `etgan_erp (33)_cleaned_fixed.sql` |
| **الموقع** | `C:\Users\musta\Downloads\` |
| **نسخة احتياطية** | `c:\xampp\htdocs\etganplus\database\etgan_erp_cleaned.sql` |
| **الحجم** | ~1.78 MB |
| **عدد الأسطر** | 13,733 سطر |
| **الترميز** | UTF-8 |

---

## 🚀 طرق الاستيراد

### الطريقة 1️⃣: من سطر الأوامر (Command Line)

#### على Windows:
```bash
# افتح Command Prompt أو PowerShell
cd C:\xampp\mysql\bin

# استيراد الملف
mysql -u root -p etgan_erp < "C:\Users\musta\Downloads\etgan_erp (33)_cleaned_fixed.sql"

# سيطلب منك كلمة المرور (إذا كانت موجودة)
```

#### على Linux/Mac:
```bash
mysql -u root -p etgan_erp < ~/Downloads/etgan_erp\ \(33\)_cleaned_fixed.sql
```

---

### الطريقة 2️⃣: من phpMyAdmin

#### الخطوات:
1. **افتح phpMyAdmin:**
   ```
   http://localhost/phpmyadmin
   ```

2. **اختر قاعدة البيانات:**
   - انقر على `etgan_erp` من القائمة اليسرى

3. **اذهب إلى Import:**
   - انقر على تبويب `Import` في الأعلى

4. **اختر الملف:**
   - انقر على `Choose File`
   - اختر: `etgan_erp (33)_cleaned_fixed.sql`

5. **تأكد من الإعدادات:**
   - Character set: `utf8mb4`
   - Format: `SQL`

6. **انقر على Import:**
   - انقر على زر `Import` الأزرق

7. **انتظر الانتهاء:**
   - سيظهر رسالة نجاح عند الانتهاء

---

### الطريقة 3️⃣: من MySQL Workbench

#### الخطوات:
1. **افتح MySQL Workbench**

2. **اتصل بقاعدة البيانات:**
   - اختر الاتصال المناسب

3. **اذهب إلى File → Open SQL Script:**
   - اختر الملف: `etgan_erp (33)_cleaned_fixed.sql`

4. **تنفيذ السكريبت:**
   - انقر على `Execute` أو اضغط `Ctrl+Shift+Enter`

5. **انتظر الانتهاء:**
   - سيظهر رسالة نجاح عند الانتهاء

---

## ⚠️ قبل الاستيراد

### ✅ قائمة التحقق:

- [ ] **النسخة الاحتياطية:** قم بعمل نسخة احتياطية من قاعدة البيانات الحالية
  ```bash
  mysqldump -u root -p etgan_erp > backup_before_import.sql
  ```

- [ ] **التحقق من الاتصال:** تأكد من أن MySQL يعمل بشكل صحيح
  ```bash
  mysql -u root -p -e "SELECT VERSION();"
  ```

- [ ] **المساحة الحرة:** تأكد من وجود مساحة كافية على القرص الصلب

- [ ] **الصلاحيات:** تأكد من أن لديك صلاحيات كافية لإنشاء الجداول

- [ ] **قاعدة البيانات:** تأكد من وجود قاعدة البيانات `etgan_erp`
  ```bash
  mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS etgan_erp;"
  ```

---

## 🔄 بعد الاستيراد

### ✅ التحقق من النجاح:

```bash
# 1. التحقق من عدد الجداول
mysql -u root -p etgan_erp -e "SHOW TABLES;" | wc -l

# 2. التحقق من عدد الصفوف في جدول معين
mysql -u root -p etgan_erp -e "SELECT COUNT(*) FROM users;"

# 3. التحقق من عدم وجود أخطاء
mysql -u root -p etgan_erp -e "SHOW ERRORS;"
```

### 📊 الإحصائيات المتوقعة:

```sql
-- عدد الجداول
SELECT COUNT(*) as table_count FROM information_schema.tables 
WHERE table_schema = 'etgan_erp';

-- عدد الصفوف في كل جدول
SELECT table_name, table_rows 
FROM information_schema.tables 
WHERE table_schema = 'etgan_erp' 
ORDER BY table_rows DESC;
```

---

## 🐛 استكشاف الأخطاء

### ❌ خطأ: "Access denied for user"
**الحل:**
```bash
# تأكد من اسم المستخدم وكلمة المرور
mysql -u root -p
```

### ❌ خطأ: "Unknown database 'etgan_erp'"
**الحل:**
```bash
# أنشئ قاعدة البيانات أولاً
mysql -u root -p -e "CREATE DATABASE etgan_erp;"
```

### ❌ خطأ: "Syntax error"
**الحل:**
- تأكد من أن الملف بترميز UTF-8
- تأكد من أن الملف لم يتم تعديله

### ❌ خطأ: "Out of memory"
**الحل:**
```bash
# زيادة حد الذاكرة في MySQL
mysql -u root -p -e "SET GLOBAL max_allowed_packet=256M;"
```

---

## 📝 ملاحظات مهمة

### ⚠️ تحذيرات:

1. **الملف الأصلي محفوظ:**
   - الملف الأصلي `etgan_erp (33).sql` محفوظ في Downloads
   - يمكنك استعادة الـ Functions و Triggers منه إذا لزم الأمر

2. **الـ Functions و Triggers المحذوفة:**
   - تم حذف `check_work_order_extract_conflict` Function
   - تم حذف 3 Triggers تابعة لها
   - إذا كنت تحتاجها، استعدها من الملف الأصلي

3. **الاختبار:**
   - اختبر التطبيق بعد الاستيراد
   - تأكد من أن جميع الميزات تعمل بشكل صحيح

4. **الأداء:**
   - قد يستغرق الاستيراد بعض الوقت (حسب سرعة القرص الصلب)
   - لا تغلق الاتصال أثناء الاستيراد

---

## 📞 الدعم

### إذا واجهت مشاكل:

1. **تحقق من الملف:**
   - تأكد من أن الملف موجود وغير تالف
   - تأكد من الترميز (UTF-8)

2. **تحقق من قاعدة البيانات:**
   - تأكد من أن MySQL يعمل
   - تأكد من الاتصال

3. **اطلب المساعدة:**
   - احفظ رسالة الخطأ
   - اطلب المساعدة من فريق الدعم

---

## 📚 مراجع إضافية

- [MySQL Documentation](https://dev.mysql.com/doc/)
- [phpMyAdmin Documentation](https://docs.phpmyadmin.net/)
- [MySQL Workbench Documentation](https://dev.mysql.com/doc/workbench/en/)

---

**آخر تحديث:** 2025-10-23  
**الحالة:** ✅ جاهز للاستخدام

---

