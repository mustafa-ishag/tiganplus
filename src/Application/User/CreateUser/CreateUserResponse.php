<?php

declare(strict_types=1);

namespace EtganERP\Application\User\CreateUser;

/**
 * استجابة إنشاء مستخدم
 * Create User Response
 */
final class CreateUserResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $fullName,
        public readonly string $message
    ) {
    }
}
