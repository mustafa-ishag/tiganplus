<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch\ValueObjects;

use InvalidArgumentException;

/**
 * رمز الفرع
 * Branch Code Value Object
 */
final class BranchCode
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = strtoupper(trim($value));
    }

    private function validate(string $code): void
    {
        if (empty($code)) {
            throw new InvalidArgumentException('رمز الفرع لا يمكن أن يكون فارغاً');
        }

        if (strlen($code) < 2) {
            throw new InvalidArgumentException('رمز الفرع يجب أن يكون حرفين على الأقل');
        }

        if (strlen($code) > 10) {
            throw new InvalidArgumentException('رمز الفرع طويل جداً (الحد الأقصى 10 أحرف)');
        }

        if (!preg_match('/^[A-Z0-9]+$/', strtoupper($code))) {
            throw new InvalidArgumentException('رمز الفرع يجب أن يحتوي على أحرف إنجليزية وأرقام فقط');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(BranchCode $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
