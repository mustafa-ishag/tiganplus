-- =====================================================
-- إضافة نوع "مستندات أخرى" إلى جدول work_order_attachments
-- Add "other_document" type to work_order_attachments table
-- =====================================================

-- الخطوة 1: إزالة القيد الفريد القديم أولاً
-- هذا يسمح بإضافة عدة مستندات أخرى لنفس أمر العمل
ALTER TABLE work_order_attachments DROP INDEX unique_work_order_form;

-- الخطوة 2: تحديث ENUM لإضافة other_document
ALTER TABLE work_order_attachments
MODIFY COLUMN form_type ENUM(
    'excavation_form',
    'precise_drilling_form',
    'demolition_form',
    'f1_form',
    'completion_certificate',
    'other_document'
) NOT NULL COMMENT 'نوع النموذج';

-- =====================================================
-- ملاحظات:
-- =====================================================
-- 1. بعد إزالة القيد الفريد، يمكن إضافة عدة مستندات من نفس النوع
-- 2. هذا مفيد خصوصاً لـ "المستندات الأخرى" حيث يمكن رفع عدة ملفات
-- 3. النماذج الأساسية (حفر دقيق، تخريد، إلخ) يتم التحكم بها من الكود البرمجي
-- =====================================================

