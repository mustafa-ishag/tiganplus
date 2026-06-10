<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Branch\ValueObjects\BranchCode;

/**
 * واجهة مستودع الفروع
 * Branch Repository Interface
 */
interface BranchRepositoryInterface
{
    /**
     * البحث عن فرع بالمعرف
     */
    public function findById(Id $id): ?Branch;

    /**
     * البحث عن فرع بالرمز
     */
    public function findByCode(BranchCode $code): ?Branch;

    /**
     * حفظ فرع
     */
    public function save(Branch $branch): void;

    /**
     * حذف فرع
     */
    public function delete(Branch $branch): void;

    /**
     * التحقق من وجود رمز فرع
     */
    public function existsByCode(BranchCode $code): bool;

    /**
     * الحصول على جميع الفروع
     */
    public function findAll(): array;

    /**
     * الحصول على الفروع النشطة
     */
    public function findActive(): array;

    /**
     * البحث في الفروع
     */
    public function search(string $searchTerm): array;

    /**
     * عدد الفروع
     */
    public function count(): int;

    /**
     * عدد الفروع النشطة
     */
    public function countActive(): int;

    /**
     * الحصول على المعرف التالي
     */
    public function nextId(): Id;
}
