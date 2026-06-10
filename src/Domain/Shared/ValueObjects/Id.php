<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * معرف فريد
 * Unique Identifier Value Object
 */
final class Id
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('المعرف يجب أن يكون رقماً موجباً');
        }
        
        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(Id $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
