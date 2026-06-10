<?php

declare(strict_types=1);

namespace EtganERP\Domain\User;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Email;
use EtganERP\Domain\User\ValueObjects\Username;

/**
 * واجهة مستودع المستخدمين
 * User Repository Interface
 */
interface UserRepositoryInterface
{
    /**
     * البحث عن مستخدم بواسطة المعرف
     */
    public function findById(Id $id): ?User;

    /**
     * البحث عن مستخدم بواسطة اسم المستخدم
     */
    public function findByUsername(Username $username): ?User;

    /**
     * البحث عن مستخدم بواسطة البريد الإلكتروني
     */
    public function findByEmail(Email $email): ?User;

    /**
     * البحث عن مستخدم بواسطة remember token
     */
    public function findByRememberToken(string $token): ?User;

    /**
     * حفظ مستخدم
     */
    public function save(User $user): void;

    /**
     * حذف مستخدم
     */
    public function delete(User $user): void;

    /**
     * التحقق من وجود اسم المستخدم
     */
    public function existsByUsername(Username $username): bool;

    /**
     * التحقق من وجود البريد الإلكتروني
     */
    public function existsByEmail(Email $email): bool;

    /**
     * الحصول على جميع المستخدمين
     */
    public function findAll(): array;

    /**
     * الحصول على المستخدمين حسب الفرع
     */
    public function findByBranchId(Id $branchId): array;

    /**
     * الحصول على المستخدمين حسب الدور
     */
    public function findByRoleId(Id $roleId): array;

    /**
     * البحث في المستخدمين
     */
    public function search(string $searchTerm, ?Id $branchId = null, ?Id $roleId = null): array;

    /**
     * عد المستخدمين
     */
    public function count(): int;

    /**
     * عد المستخدمين النشطين
     */
    public function countActive(): int;

    /**
     * الحصول على معرف جديد
     */
    public function nextId(): Id;
}
