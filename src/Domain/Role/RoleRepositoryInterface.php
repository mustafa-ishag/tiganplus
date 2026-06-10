<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Role\ValueObjects\RoleName;

/**
 * واجهة مستودع الأدوار
 * Role Repository Interface
 */
interface RoleRepositoryInterface
{
    /**
     * البحث عن دور بواسطة المعرف
     */
    public function findById(Id $id): ?Role;

    /**
     * البحث عن دور بواسطة الاسم
     */
    public function findByName(RoleName $name): ?Role;

    /**
     * حفظ دور
     */
    public function save(Role $role): void;

    /**
     * حذف دور
     */
    public function delete(Role $role): void;

    /**
     * التحقق من وجود اسم الدور
     */
    public function existsByName(RoleName $name): bool;

    /**
     * الحصول على جميع الأدوار
     */
    public function findAll(): array;

    /**
     * الحصول على الأدوار النشطة
     */
    public function findActive(): array;

    /**
     * البحث في الأدوار
     */
    public function search(string $searchTerm): array;

    /**
     * عد الأدوار
     */
    public function count(): int;

    /**
     * عد الأدوار النشطة
     */
    public function countActive(): int;

    /**
     * الحصول على معرف جديد
     */
    public function nextId(): Id;
}
