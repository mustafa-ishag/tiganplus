<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch\ValueObjects;

use InvalidArgumentException;

/**
 * اسم الفرع
 * Branch Name Value Object
 */
final class BranchName
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
            throw new InvalidArgumentException('اسم الفرع لا يمكن أن يكون فارغاً');
        }

        if (strlen($name) < 3) {
            throw new InvalidArgumentException('اسم الفرع يجب أن يكون 3 أحرف على الأقل');
        }

        if (strlen($name) > 100) {
            throw new InvalidArgumentException('اسم الفرع طويل جداً (الحد الأقصى 100 حرف)');
        }

        // السماح بالأحرف العربية والإنجليزية والأرقام والمسافات والرموز الأساسية
        if (!preg_match('/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}a-zA-Z0-9\s\-\.\(\)\،\,]+$/u', $name)) {
            throw new InvalidArgumentException('اسم الفرع يحتوي على أحرف غير صحيحة');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(BranchName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
