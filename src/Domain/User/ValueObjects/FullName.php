<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\ValueObjects;

use InvalidArgumentException;

/**
 * الاسم الكامل
 * Full Name Value Object
 */
final class FullName
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = trim($value);
    }

    private function validate(string $name): void
    {
        if (empty($name)) {
            throw new InvalidArgumentException('الاسم الكامل لا يمكن أن يكون فارغاً');
        }

        if (strlen($name) < 2) {
            throw new InvalidArgumentException('الاسم الكامل يجب أن يكون حرفين على الأقل');
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('الاسم الكامل طويل جداً');
        }

        // التحقق من وجود أحرف صحيحة فقط (عربي، إنجليزي، مسافات)
        if (!preg_match('/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}a-zA-Z\s\-\'\.]+$/u', $name)) {
            throw new InvalidArgumentException('الاسم الكامل يحتوي على أحرف غير صحيحة');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function firstName(): string
    {
        $parts = explode(' ', $this->value);
        return $parts[0] ?? '';
    }

    public function lastName(): string
    {
        $parts = explode(' ', $this->value);
        return end($parts) ?: '';
    }

    public function initials(): string
    {
        $parts = explode(' ', $this->value);
        $initials = '';
        
        foreach ($parts as $part) {
            if (!empty($part)) {
                $initials .= mb_substr($part, 0, 1, 'UTF-8');
            }
        }
        
        return mb_strtoupper($initials, 'UTF-8');
    }

    public function equals(FullName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
