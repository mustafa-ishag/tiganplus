<?php

declare(strict_types=1);

namespace EtganERP\Application\Auth\Login;

/**
 * أمر تسجيل الدخول
 * Login Command
 */
final class LoginCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly bool $rememberMe = false
    ) {
    }
}
