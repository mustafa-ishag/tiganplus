<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * رقم أمر العمل
 * Work Order Number Value Object
 */
final class WorkOrderNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = trim($value);
    }

    private function validate(string $number): void
    {
        if (empty($number)) {
            throw new InvalidArgumentException('رقم أمر العمل لا يمكن أن يكون فارغاً');
        }

        if (strlen($number) !== 9) {
            throw new InvalidArgumentException('رقم أمر العمل يجب أن يكون 9 أرقام بالضبط');
        }

        // التحقق من الصيغة: 9 أرقام فقط (مثل: 123456789)
        if (!preg_match('/^[0-9]{9}$/', $number)) {
            throw new InvalidArgumentException('رقم أمر العمل يجب أن يكون مكون من 9 أرقام فقط (مثل: 123456789)');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(WorkOrderNumber $other): bool
    {
        return $this->value === $other->value;
    }

    public function getFirstThreeDigits(): string
    {
        return substr($this->value, 0, 3);
    }

    public function getLastSixDigits(): string
    {
        return substr($this->value, 3);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
