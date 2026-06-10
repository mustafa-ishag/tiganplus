<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * الحالة
 * Status Value Object
 */
final class Status
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const SUSPENDED = 'suspended';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $status): void
    {
        $validStatuses = [
            self::ACTIVE,
            self::INACTIVE,
            self::SUSPENDED,
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
            self::COMPLETED,
            self::CANCELLED
        ];

        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('حالة غير صحيحة: ' . $status);
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function approved(): self
    {
        return new self(self::APPROVED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->value === self::SUSPENDED;
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->value === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->value === self::REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function equals(Status $other): bool
    {
        return $this->value === $other->value;
    }

    public function toArabic(): string
    {
        return match ($this->value) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'معلق',
            self::PENDING => 'في الانتظار',
            self::APPROVED => 'معتمد',
            self::REJECTED => 'مرفوض',
            self::COMPLETED => 'مكتمل',
            self::CANCELLED => 'ملغي',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
