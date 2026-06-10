<?php

declare(strict_types=1);

// تحميل التطبيق
require_once __DIR__ . '/../../bootstrap/app.php';

use EtganERP\Infrastructure\Persistence\WorkOrderTypeRepository;
use EtganERP\Domain\Shared\ValueObjects\Id;

// التحقق من وجود معرف نوع أمر العمل
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>معرف غير صحيح</div>';
    exit;
}

$typeId = (int) $_GET['id'];

try {
    // جلب نوع أمر العمل
    $workOrderTypeRepository = new WorkOrderTypeRepository();
    $workOrderType = $workOrderTypeRepository->findById(new Id($typeId));
    
    if (!$workOrderType) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>نوع أمر العمل غير موجود</div>';
        exit;
    }
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>حدث خطأ أثناء جلب البيانات</div>';
    exit;
}
?>

<form id="editWorkOrderTypeForm" onsubmit="submitEditForm(event)">
    <input type="hidden" name="id" value="<?php echo $workOrderType->id()->value(); ?>">
    
    <!-- Type Code -->
    <div class="mb-3">
        <label for="edit_code" class="form-label">
            <i class="fas fa-code me-2"></i>
            كود النوع *
        </label>
        <input type="text" class="form-control" id="edit_code" name="code" 
               value="<?php echo htmlspecialchars($workOrderType->code()->value()); ?>"
               required maxlength="10" readonly>
        <div class="form-text text-muted">
            <i class="fas fa-info-circle me-1"></i>
            لا يمكن تعديل كود النوع بعد الإنشاء
        </div>
    </div>

    <!-- Description -->
    <div class="mb-3">
        <label for="edit_description" class="form-label">
            <i class="fas fa-align-left me-2"></i>
            الوصف
        </label>
        <textarea class="form-control" id="edit_description" name="description" 
                  rows="4" maxlength="500" placeholder="وصف نوع أمر العمل (اختياري)"><?php echo htmlspecialchars($workOrderType->description()?->value() ?? ''); ?></textarea>
        <div class="form-text">
            وصف تفصيلي لنوع أمر العمل (الحد الأقصى 500 حرف)
        </div>
    </div>

    <!-- Status -->
    <div class="mb-4">
        <label class="form-label">
            <i class="fas fa-toggle-on me-2"></i>
            الحالة
        </label>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="edit_status" name="status" 
                   <?php echo $workOrderType->isActive() ? 'checked' : ''; ?>>
            <label class="form-check-label" for="edit_status">
                نشط
            </label>
        </div>
        <div class="form-text">
            تحديد ما إذا كان نوع أمر العمل نشطاً أم لا
        </div>
    </div>

    <!-- Submit Buttons -->
    <div class="text-end">
        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>
            إلغاء
        </button>
        <button type="submit" class="btn btn-warning">
            <i class="fas fa-save me-2"></i>
            حفظ التغييرات
        </button>
    </div>
</form>

<script>
function submitEditForm(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // إظهار loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>جاري الحفظ...';
    submitBtn.disabled = true;
    
    fetch('update-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إغلاق Modal وإعادة تحميل الصفحة
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            location.reload();
        } else {
            alert('خطأ في التحديث: ' + data.message);
        }
    })
    .catch(error => {
        alert('خطأ في الاتصال: ' + error.message);
    })
    .finally(() => {
        // إعادة تعيين الزر
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}
</script>
