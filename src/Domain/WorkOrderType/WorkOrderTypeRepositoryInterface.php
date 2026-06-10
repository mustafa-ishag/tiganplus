<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrderType;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeCode;

/**
 * واجهة مستودع أنواع أوامر العمل
 * Work Order Type Repository Interface
 */
interface WorkOrderTypeRepositoryInterface
{
    /**
     * البحث عن نوع أمر عمل بالمعرف
     */
    public function findById(Id $id): ?WorkOrderType;

    /**
     * البحث عن نوع أمر عمل بالكود
     */
    public function findByCode(TypeCode $code): ?WorkOrderType;

    /**
     * حفظ نوع أمر عمل
     */
    public function save(WorkOrderType $workOrderType): void;

    /**
     * حذف نوع أمر عمل
     */
    public function delete(WorkOrderType $workOrderType): void;

    /**
     * التحقق من وجود كود نوع أمر عمل
     */
    public function existsByCode(TypeCode $code): bool;

    /**
     * الحصول على جميع أنواع أوامر العمل
     */
    public function findAll(): array;

    /**
     * الحصول على أنواع أوامر العمل النشطة
     */
    public function findActive(): array;

    /**
     * البحث في أنواع أوامر العمل
     */
    public function search(string $searchTerm): array;

    /**
     * عدد أنواع أوامر العمل
     */
    public function count(): int;

    /**
     * عدد أنواع أوامر العمل النشطة
     */
    public function countActive(): int;

    /**
     * الحصول على المعرف التالي
     */
    public function nextId(): Id;
}
