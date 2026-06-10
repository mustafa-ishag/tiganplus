<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;

/**
 * واجهة مستودع أوامر العمل
 * Work Order Repository Interface
 */
interface WorkOrderRepositoryInterface
{
    /**
     * البحث عن أمر عمل بالمعرف
     */
    public function findById(Id $id): ?WorkOrder;

    /**
     * البحث عن أمر عمل برقم الأمر
     */
    public function findByNumber(WorkOrderNumber $number): ?WorkOrder;

    /**
     * حفظ أمر عمل
     */
    public function save(WorkOrder $workOrder): void;

    /**
     * حذف أمر عمل
     */
    public function delete(WorkOrder $workOrder): void;

    /**
     * التحقق من وجود رقم أمر عمل
     */
    public function existsByNumber(WorkOrderNumber $number): bool;

    /**
     * الحصول على جميع أوامر العمل
     */
    public function findAll(): array;

    /**
     * الحصول على أوامر العمل النشطة
     */
    public function findActive(): array;

    /**
     * الحصول على أوامر العمل حسب الفرع
     */
    public function findByBranch(Id $branchId): array;

    /**
     * الحصول على أوامر العمل حسب النوع
     */
    public function findByType(Id $workOrderTypeId): array;

    /**
     * الحصول على أوامر العمل حسب القسم
     */
    public function findByDepartment(string $department): array;

    /**
     * الحصول على أوامر العمل غير المرتبطة بمستخلص
     */
    public function findUnassignedToExtract(): array;

    /**
     * الحصول على أوامر العمل المرتبطة بمستخلص
     */
    public function findByExtract(Id $extractId): array;

    /**
     * البحث في أوامر العمل
     */
    public function search(string $searchTerm): array;

    /**
     * البحث المتقدم في أوامر العمل
     */
    public function advancedSearch(array $criteria): array;

    /**
     * عدد أوامر العمل
     */
    public function count(): int;

    /**
     * عدد أوامر العمل النشطة
     */
    public function countActive(): int;

    /**
     * عدد أوامر العمل حسب الفرع
     */
    public function countByBranch(Id $branchId): int;

    /**
     * الحصول على المعرف التالي
     */
    public function nextId(): Id;

    /**
     * توليد رقم أمر عمل جديد للفرع
     */
    public function generateWorkOrderNumber(string $branchCode): WorkOrderNumber;
}
