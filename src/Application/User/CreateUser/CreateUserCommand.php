<?php

declare(strict_types=1);

namespace EtganERP\Application\User\CreateUser;

/**
 * أمر إنشاء مستخدم
 * Create User Command
 */
final class CreateUserCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $fullName,
        public readonly string $password,
        public readonly int $roleId,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?int $branchId = null
    ) {
    }
}
