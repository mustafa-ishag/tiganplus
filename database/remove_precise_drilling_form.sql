-- =====================================================
-- التأكد من وجود كلا النموذجين: الحفر الدقيق والكشط
-- Ensure both precise_drilling_form and excavation_form exist
-- =====================================================

-- تحديث ENUM لإضافة كلا النموذجين
ALTER TABLE work_order_attachments
MODIFY COLUMN form_type ENUM(
    'precise_drilling_form',
    'excavation_form',
    'demolition_form',
    'f1_form',
    'completion_certificate',
    'other_document'
) NOT NULL COMMENT 'نوع النموذج';

-- =====================================================
-- ملاحظات:
-- =====================================================
-- النماذج المتاحة الآن:
--    - precise_drilling_form (نموذج الحفر الدقيق)
--    - excavation_form (نموذج الكشط)
--    - demolition_form (نموذج التخريد)
--    - f1_form (نموذج F1)
--    - completion_certificate (شهادة الإنجاز)
--    - other_document (مستندات أخرى)
-- =====================================================

