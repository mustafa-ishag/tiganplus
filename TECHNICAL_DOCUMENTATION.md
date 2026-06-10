# التوثيق التقني - Technical Documentation
## نظام تِقان - Dashboard Implementation

---

## 🏗️ البنية المعمارية

### الطبقات:
```
Presentation Layer (HTML/CSS/JavaScript)
        ↓
Business Logic Layer (PHP)
        ↓
Data Access Layer (Database Queries)
        ↓
Database Layer (MySQL)
```

---

## 📊 استعلامات قاعدة البيانات

### 1. إحصائيات أوامر العمل
```sql
-- إجمالي أوامر العمل
SELECT COUNT(*) as total FROM work_orders

-- أوامر العمل النشطة
SELECT COUNT(*) as active FROM work_orders WHERE status = 'active'

-- أوامر العمل المكتملة
SELECT COUNT(*) as completed FROM work_orders WHERE status = 'completed'

-- القيمة المقدرة
SELECT SUM(estimated_value) as total FROM work_orders WHERE status = 'active'

-- القيمة الفعلية
SELECT SUM(actual_value) as total FROM work_orders WHERE status = 'completed'
```

### 2. إحصائيات المستخلصات
```sql
-- المستخلصات الجزئية
SELECT COUNT(*) as total FROM partial_extracts

-- المستخلصات النهائية العادية
SELECT COUNT(*) as total FROM final_regular_extracts

-- المستخلصات النهائية للجزئية
SELECT COUNT(*) as total FROM final_for_partial_extracts
```

### 3. بيانات الرسوم البيانية
```sql
-- توزيع أوامر العمل حسب الحالة
SELECT status, COUNT(*) as count FROM work_orders GROUP BY status

-- توزيع أوامر العمل حسب الفرع
SELECT b.name, COUNT(wo.id) as count
FROM work_orders wo
LEFT JOIN branches b ON wo.branch_id = b.id
GROUP BY wo.branch_id, b.name
```

---

## 🔄 تدفق البيانات

### 1. جلب البيانات (PHP)
```php
$db = getDB();
$stmt = $db->query("SELECT ...");
$data = $stmt->fetch(PDO::FETCH_ASSOC);
```

### 2. تحويل إلى JSON
```php
$jsonData = json_encode($data);
```

### 3. تمرير إلى JavaScript
```javascript
const data = <?= $jsonData ?>;
```

### 4. رسم الرسم البياني
```javascript
new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets },
    options: { ... }
});
```

---

## 🎯 المتغيرات الرئيسية

### في PHP:
- `$totalWorkOrders` - إجمالي أوامر العمل
- `$activeWorkOrders` - أوامر العمل النشطة
- `$completedWorkOrders` - أوامر العمل المكتملة
- `$totalPartialExtracts` - المستخلصات الجزئية
- `$totalFinalExtracts` - المستخلصات النهائية العادية
- `$totalFinalForPartialExtracts` - المستخلصات النهائية للجزئية
- `$totalBranches` - عدد الفروع
- `$totalUsers` - عدد المستخدمين
- `$totalMaterials` - عدد المواد
- `$totalEstimatedValue` - القيمة المقدرة
- `$totalActualValue` - القيمة الفعلية
- `$recentWorkOrders` - آخر أوامر العمل
- `$recentExtracts` - آخر المستخلصات

### في JavaScript:
- `workOrdersStatusData` - بيانات حالة أوامر العمل
- `extractsData` - بيانات المستخلصات
- `branchesData` - بيانات الفروع

---

## 🎨 مكونات الواجهة

### 1. بطاقات الإحصائيات (Cards)
```html
<div class="card border-start border-primary border-4">
    <div class="card-body">
        <!-- محتوى البطاقة -->
    </div>
</div>
```

### 2. الرسوم البيانية (Charts)
```html
<canvas id="workOrdersStatusChart"></canvas>
```

### 3. الملخص المالي (Financial Summary)
```html
<div class="progress">
    <div class="progress-bar"></div>
</div>
```

---

## 🔐 معالجة الأخطاء

### Try-Catch Block:
```php
try {
    // استعلامات قاعدة البيانات
} catch (Exception $e) {
    // تعيين قيم افتراضية
    $totalWorkOrders = 0;
}
```

### التحقق من البيانات:
```javascript
if (workOrdersStatusData && workOrdersStatusData.length > 0) {
    // رسم الرسم البياني
}
```

---

## 📈 الأداء والتحسينات

### 1. استخدام الفهارس (Indexes)
- فهرس على حقل `status`
- فهرس على حقل `branch_id`
- فهرس على حقل `created_at`

### 2. تقليل الاستعلامات
- استخدام UNION للاستعلامات المتعددة
- استخدام LEFT JOIN للبيانات المرتبطة

### 3. تحسين الرسوم البيانية
- استخدام `will-change` في CSS
- تحميل الرسوم البيانية بشكل ديناميكي

---

## 🧪 الاختبار

### اختبارات يدوية:
1. التحقق من ظهور الإحصائيات بشكل صحيح
2. التحقق من الرسوم البيانية
3. التحقق من الاستجابة على الأجهزة المختلفة

### اختبارات الأداء:
1. قياس وقت تحميل الصفحة
2. قياس استهلاك الذاكرة
3. قياس استهلاك النطاق الترددي

---

## 🔄 التحديثات المستقبلية

1. **تحديث البيانات الحي**
   - استخدام WebSockets
   - تحديث الرسوم البيانية تلقائياً

2. **تصفية متقدمة**
   - تصفية حسب التاريخ
   - تصفية حسب الفرع

3. **تقارير مخصصة**
   - إنشاء تقارير مخصصة
   - تصدير البيانات

---

## 📚 المراجع

- Chart.js Documentation: https://www.chartjs.org/
- Bootstrap Documentation: https://getbootstrap.com/
- PHP Documentation: https://www.php.net/
- MySQL Documentation: https://dev.mysql.com/

