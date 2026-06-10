<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
$db = getDB();

// إضافة عمود تاريخ ارفاق شهادة الإنجاز
try {
    $db->exec("ALTER TABLE work_order_attachments ADD COLUMN certificate_attached_date DATE NULL DEFAULT NULL AFTER completion_certificate_confirmation");
    echo "تم إضافة عمود certificate_attached_date بنجاح\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "عمود certificate_attached_date موجود مسبقاً\n";
    } else {
        echo "خطأ: " . $e->getMessage() . "\n";
    }
}

// إضافة عمود تاريخ تأكيد شهادة الإنجاز
try {
    $db->exec("ALTER TABLE work_order_attachments ADD COLUMN certificate_confirmed_date DATE NULL DEFAULT NULL AFTER certificate_attached_date");
    echo "تم إضافة عمود certificate_confirmed_date بنجاح\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "عمود certificate_confirmed_date موجود مسبقاً\n";
    } else {
        echo "خطأ: " . $e->getMessage() . "\n";
    }
}

// تحديث السجلات الحالية - تعيين تاريخ الإرفاق للشهادات المرفقة بالفعل
try {
    $db->exec("UPDATE work_order_attachments SET certificate_attached_date = DATE(COALESCE(updated_at, created_at)) WHERE form_type = 'completion_certificate' AND status = 'attached' AND certificate_attached_date IS NULL");
    echo "تم تحديث تواريخ الإرفاق الحالية\n";
} catch (PDOException $e) {
    echo "خطأ في التحديث: " . $e->getMessage() . "\n";
}

// تحديث السجلات الحالية - تعيين تاريخ التأكيد للشهادات المؤكدة بالفعل
try {
    $db->exec("UPDATE work_order_attachments SET certificate_confirmed_date = DATE(COALESCE(updated_at, created_at)) WHERE form_type = 'completion_certificate' AND completion_certificate_confirmation = 'confirmed' AND certificate_confirmed_date IS NULL");
    echo "تم تحديث تواريخ التأكيد الحالية\n";
} catch (PDOException $e) {
    echo "خطأ في التحديث: " . $e->getMessage() . "\n";
}

echo "\nتم الانتهاء من إضافة الأعمدة الجديدة\n";
