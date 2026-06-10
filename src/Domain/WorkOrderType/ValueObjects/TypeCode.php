<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrderType\ValueObjects;

use InvalidArgumentException;

/**
 * كود نوع أمر العمل
 * Work Order Type Code Value Object
 */
final class TypeCode
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
            throw new InvalidArgumentException('كود نوع أمر العمل لا يمكن أن يكون فارغاً');
        }

        if (strlen($code) < 2) {
            throw new InvalidArgumentException('كود نوع أمر العمل يجب أن يكون حرفين على الأقل');
        }

        if (strlen($code) > 10) {
            throw new InvalidArgumentException('كود نوع أمر العمل طويل جداً (الحد الأقصى 10 أحرف)');
        }

        // السماح بالأحرف الإنجليزية والأرقام فقط
        if (!preg_match('/^[A-Z0-9]+$/i', $code)) {
            throw new InvalidArgumentException('كود نوع أمر العمل يجب أن يحتوي على أحرف إنجليزية وأرقام فقط');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(TypeCode $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
