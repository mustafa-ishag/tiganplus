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

<div class="row">
    <div class="col-12">
        <!-- Type Code -->
        <div class="mb-4">
            <h6 class="text-muted mb-2">
                <i class="fas fa-code me-2 text-primary"></i>
                كود النوع
            </h6>
            <div>
                <span class="badge bg-primary fs-5 px-3 py-2">
                    <?php echo htmlspecialchars($workOrderType->code()->value()); ?>
                </span>
            </div>
        </div>

        <!-- Description -->
        <div class="mb-4">
            <h6 class="text-muted mb-2">
                <i class="fas fa-align-left me-2 text-info"></i>
                الوصف
            </h6>
            <div class="p-3 bg-light rounded">
                <?php echo htmlspecialchars($workOrderType->description()?->value() ?? 'لا يوجد وصف'); ?>
            </div>
        </div>

        <!-- Status -->
        <div class="mb-4">
            <h6 class="text-muted mb-2">
                <i class="fas fa-toggle-on me-2 text-success"></i>
                الحالة
            </h6>
            <div>
                <?php if ($workOrderType->isActive()): ?>
                    <span class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-check-circle me-1"></i>
                        نشط
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger fs-6 px-3 py-2">
                        <i class="fas fa-times-circle me-1"></i>
                        غير نشط
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Created At -->
        <div class="mb-4">
            <h6 class="text-muted mb-2">
                <i class="fas fa-calendar-plus me-2 text-secondary"></i>
                تاريخ الإنشاء
            </h6>
            <div class="p-2 bg-light rounded">
                <i class="fas fa-clock me-2"></i>
                <?php echo $workOrderType->createdAt()->format('Y-m-d H:i:s'); ?>
            </div>
        </div>

        <!-- Updated At -->
        <?php if ($workOrderType->updatedAt()): ?>
        <div class="mb-4">
            <h6 class="text-muted mb-2">
                <i class="fas fa-calendar-edit me-2 text-warning"></i>
                تاريخ آخر تحديث
            </h6>
            <div class="p-2 bg-light rounded">
                <i class="fas fa-clock me-2"></i>
                <?php echo $workOrderType->updatedAt()->format('Y-m-d H:i:s'); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="text-center mt-4 pt-3 border-top">
            <button type="button" class="btn btn-warning me-2" 
                    onclick="editWorkOrderType(<?php echo $workOrderType->id()->value(); ?>); bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();">
                <i class="fas fa-edit me-2"></i>
                تعديل
            </button>
            
            <?php if ($workOrderType->canBeDeleted()): ?>
            <button type="button" class="btn btn-danger" 
                    onclick="if(confirm('هل أنت متأكد من حذف نوع أمر العمل هذا؟')) { window.location.href = 'delete.php?id=<?php echo $workOrderType->id()->value(); ?>'; }">
                <i class="fas fa-trash me-2"></i>
                حذف
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
