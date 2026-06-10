<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\ValueObjects;

use InvalidArgumentException;

/**
 * اسم المستخدم
 * Username Value Object
 */
final class Username
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = strtolower(trim($value));
    }

    private function validate(string $username): void
    {
        if (empty($username)) {
            throw new InvalidArgumentException('اسم المستخدم لا يمكن أن يكون فارغاً');
        }

        if (strlen($username) < 3) {
            throw new InvalidArgumentException('اسم المستخدم يجب أن يكون 3 أحرف على الأقل');
        }

        if (strlen($username) > 50) {
            throw new InvalidArgumentException('اسم المستخدم طويل جداً');
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new InvalidArgumentException('اسم المستخدم يجب أن يحتوي على أحرف وأرقام فقط');
        }

        // منع الأسماء المحجوزة (باستثناء admin للمدير)
        $reservedNames = ['root', 'system', 'test', 'guest', 'null', 'undefined'];
        if (in_array(strtolower($username), $reservedNames, true)) {
            throw new InvalidArgumentException('اسم المستخدم محجوز');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Username $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
