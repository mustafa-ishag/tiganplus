<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * حالة الصرف
 * Disbursement Status Value Object
 */
final class DisbursementStatus
{
    public const NONE = 'none';
    public const COMPLETED = 'completed';
    public const DISBURSEMENT = 'disbursement';
    public const RETURN = 'return';
    public const DISBURSEMENT_RETURN_COMPLETED = 'disbursement_return_completed';

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $status): void
    {
        $validStatuses = [
            self::NONE,
            self::COMPLETED,
            self::DISBURSEMENT,
            self::RETURN,
            self::DISBURSEMENT_RETURN_COMPLETED
        ];

        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('حالة صرف غير صحيحة: ' . $status);
        }
    }

    public static function none(): self
    {
        return new self(self::NONE);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function disbursement(): self
    {
        return new self(self::DISBURSEMENT);
    }

    public static function return(): self
    {
        return new self(self::RETURN);
    }

    public static function disbursementReturnCompleted(): self
    {
        return new self(self::DISBURSEMENT_RETURN_COMPLETED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isNone(): bool
    {
        return $this->value === self::NONE;
    }

    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function isDisbursement(): bool
    {
        return $this->value === self::DISBURSEMENT;
    }

    public function isReturn(): bool
    {
        return $this->value === self::RETURN;
    }

    public function isDisbursementReturnCompleted(): bool
    {
        return $this->value === self::DISBURSEMENT_RETURN_COMPLETED;
    }

    public function equals(DisbursementStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function toArabic(): string
    {
        return match ($this->value) {
            self::NONE => 'لا يوجد',
            self::COMPLETED => 'مكتمل',
            self::DISBURSEMENT => 'صرف',
            self::RETURN => 'إرجاع',
            self::DISBURSEMENT_RETURN_COMPLETED => 'صرف وإرجاع مكتمل',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
