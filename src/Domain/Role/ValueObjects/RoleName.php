<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role\ValueObjects;

use InvalidArgumentException;

/**
 * اسم الدور
 * Role Name Value Object
 */
final class RoleName
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
            throw new InvalidArgumentException('اسم الدور لا يمكن أن يكون فارغاً');
        }

        if (strlen($name) < 2) {
            throw new InvalidArgumentException('اسم الدور يجب أن يكون حرفين على الأقل');
        }

        if (strlen($name) > 50) {
            throw new InvalidArgumentException('اسم الدور طويل جداً');
        }

        // التحقق من الأحرف المسموحة (عربي، إنجليزي، أرقام، مسافات، شرطة، شرطة سفلية)
        if (!preg_match('/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}a-zA-Z0-9_\-\s]+$/u', $name)) {
            throw new InvalidArgumentException('اسم الدور يحتوي على أحرف غير مسموحة');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(RoleName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
