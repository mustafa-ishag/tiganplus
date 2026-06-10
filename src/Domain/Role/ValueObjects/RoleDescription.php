<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role\ValueObjects;

use InvalidArgumentException;

/**
 * وصف الدور
 * Role Description Value Object
 */
final class RoleDescription
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = trim($value);
    }

    private function validate(string $description): void
    {
        if (empty($description)) {
            throw new InvalidArgumentException('وصف الدور لا يمكن أن يكون فارغاً');
        }

        if (strlen($description) < 5) {
            throw new InvalidArgumentException('وصف الدور يجب أن يكون 5 أحرف على الأقل');
        }

        if (strlen($description) > 255) {
            throw new InvalidArgumentException('وصف الدور طويل جداً');
        }

        // التحقق من الأحرف المسموحة (عربي، إنجليزي، أرقام، رموز أساسية)
        if (!preg_match('/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}a-zA-Z0-9\s\-\.\,\:\;\!\?\(\)\،]+$/u', $description)) {
            throw new InvalidArgumentException('وصف الدور يحتوي على أحرف غير مسموحة');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(RoleDescription $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
