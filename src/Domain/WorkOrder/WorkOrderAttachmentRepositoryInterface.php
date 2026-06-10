<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\WorkOrder\ValueObjects\FormType;

/**
 * واجهة مستودع مرفقات أوامر العمل
 * Work Order Attachment Repository Interface
 */
interface WorkOrderAttachmentRepositoryInterface
{
    /**
     * البحث عن مرفق بالمعرف
     */
    public function findById(Id $id): ?WorkOrderAttachment;

    /**
     * البحث عن مرفق بأمر العمل ونوع النموذج
     */
    public function findByWorkOrderAndFormType(Id $workOrderId, FormType $formType): ?WorkOrderAttachment;

    /**
     * الحصول على جميع مرفقات أمر عمل
     */
    public function findByWorkOrder(Id $workOrderId): array;

    /**
     * حفظ مرفق
     */
    public function save(WorkOrderAttachment $attachment): void;

    /**
     * حذف مرفق
     */
    public function delete(WorkOrderAttachment $attachment): void;

    /**
     * التحقق من وجود مرفق
     */
    public function exists(Id $workOrderId, FormType $formType): bool;

    /**
     * الحصول على جميع المرفقات
     */
    public function findAll(): array;

    /**
     * البحث في المرفقات
     */
    public function search(string $searchTerm): array;

    /**
     * الحصول على المرفقات حسب الحالة
     */
    public function findByStatus(string $status): array;

    /**
     * الحصول على شهادات الإنجاز المؤكدة
     */
    public function findConfirmedCompletionCertificates(): array;

    /**
     * الحصول على شهادات الإنجاز غير المؤكدة
     */
    public function findUnconfirmedCompletionCertificates(): array;

    /**
     * الحصول على المرفقات المرفوعة بواسطة مستخدم
     */
    public function findByUploadedBy(Id $userId): array;

    /**
     * عدد المرفقات
     */
    public function count(): int;

    /**
     * عدد المرفقات لأمر عمل
     */
    public function countByWorkOrder(Id $workOrderId): int;

    /**
     * الحصول على المعرف التالي
     */
    public function nextId(): Id;

    /**
     * إنشاء النماذج الافتراضية لأمر عمل جديد
     */
    public function createDefaultFormsForWorkOrder(Id $workOrderId): void;
}
