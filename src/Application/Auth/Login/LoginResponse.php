<?php

declare(strict_types=1);

namespace EtganERP\Application\Auth\Login;

/**
 * استجابة تسجيل الدخول
 * Login Response
 */
final class LoginResponse
{
    public function __construct(
        public readonly int $userId,
        public readonly string $username,
        public readonly string $fullName,
        public readonly int $roleId,
        public readonly ?int $branchId,
        public readonly ?string $rememberToken,
        public readonly string $message
    ) {
    }
}
