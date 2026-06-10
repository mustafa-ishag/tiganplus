<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

final class CurrentEntityId
{
    private int $value;

    public function __construct(int $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(int $value): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('معرف الجهة الحالية يجب أن يكون رقماً موجباً');
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(CurrentEntityId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
