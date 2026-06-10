<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\ValueObjects;

use InvalidArgumentException;

/**
 * كلمة المرور المشفرة
 * Hashed Password Value Object
 */
final class HashedPassword
{
    private string $value;

    private function __construct(string $hashedPassword)
    {
        $this->value = $hashedPassword;
    }

    public static function fromPlainPassword(string $plainPassword): self
    {
        self::validatePlainPassword($plainPassword);
        
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        
        if ($hashedPassword === false) {
            throw new InvalidArgumentException('فشل في تشفير كلمة المرور');
        }
        
        return new self($hashedPassword);
    }

    public static function fromHashedPassword(string $hashedPassword): self
    {
        if (empty($hashedPassword)) {
            throw new InvalidArgumentException('كلمة المرور المشفرة لا يمكن أن تكون فارغة');
        }
        
        return new self($hashedPassword);
    }

    private static function validatePlainPassword(string $password): void
    {
        if (empty($password)) {
            throw new InvalidArgumentException('كلمة المرور لا يمكن أن تكون فارغة');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        }

        if (strlen($password) > 255) {
            throw new InvalidArgumentException('كلمة المرور طويلة جداً');
        }

        // التحقق من قوة كلمة المرور
        $hasLower = preg_match('/[a-z]/', $password);
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasNumber = preg_match('/\d/', $password);
        $hasSpecial = preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password);

        $strength = $hasLower + $hasUpper + $hasNumber + $hasSpecial;
        
        if ($strength < 2) {
            throw new InvalidArgumentException('كلمة المرور ضعيفة. يجب أن تحتوي على أحرف كبيرة وصغيرة وأرقام أو رموز');
        }
    }

    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->value);
    }

    public function needsRehash(): bool
    {
        return password_needs_rehash($this->value, PASSWORD_DEFAULT);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(HashedPassword $other): bool
    {
        return $this->value === $other->value;
    }
}
