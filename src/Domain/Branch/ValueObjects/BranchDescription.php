<?php

declare(strict_types=1);

namespace EtganERP\Domain\Branch\ValueObjects;

use InvalidArgumentException;

/**
 * وصف الفرع
 * Branch Description Value Object
 */
final class BranchDescription
{
    private ?string $value;

    public function __construct(?string $value)
    {
        if ($value !== null) {
            $this->validate($value);
            $this->value = trim($value);
        } else {
            $this->value = null;
        }
    }

    private function validate(string $description): void
    {
        if (strlen($description) > 500) {
            throw new InvalidArgumentException('وصف الفرع طويل جداً (الحد الأقصى 500 حرف)');
        }

        // السماح بالأحرف العربية والإنجليزية والأرقام والرموز الأساسية
        if (!preg_match('/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}a-zA-Z0-9\s\-\.\,\:\;\!\?\(\)\،]+$/u', $description)) {
            throw new InvalidArgumentException('وصف الفرع يحتوي على أحرف غير صحيحة');
        }
    }

    public function value(): ?string
    {
        return $this->value;
    }

    public function equals(?BranchDescription $other): bool
    {
        if ($other === null) {
            return $this->value === null;
        }
        
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value ?? '';
    }
}
