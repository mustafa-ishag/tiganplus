<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * تأكيد شهادة الإنجاز
 * Completion Certificate Confirmation Value Object
 */
final class CompletionCertificateConfirmation
{
    public const EMPTY = 'empty';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const CONFIRMED = 'confirmed';

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $confirmation): void
    {
        $validConfirmations = [
            self::EMPTY,
            self::ACCEPTED,
            self::REJECTED,
            self::CONFIRMED
        ];

        if (!in_array($confirmation, $validConfirmations, true)) {
            throw new InvalidArgumentException('تأكيد شهادة إنجاز غير صحيح: ' . $confirmation);
        }
    }

    public static function empty(): self
    {
        return new self(self::EMPTY);
    }

    public static function accepted(): self
    {
        return new self(self::ACCEPTED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function confirmed(): self
    {
        return new self(self::CONFIRMED);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === self::EMPTY;
    }

    public function isAccepted(): bool
    {
        return $this->value === self::ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->value === self::REJECTED;
    }

    public function isConfirmed(): bool
    {
        return $this->value === self::CONFIRMED;
    }

    public function equals(CompletionCertificateConfirmation $other): bool
    {
        return $this->value === $other->value;
    }

    public function toArabic(): string
    {
        return match ($this->value) {
            self::EMPTY => 'فارغ',
            self::ACCEPTED => 'قبول',
            self::REJECTED => 'رفض',
            self::CONFIRMED => 'تأكيد',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
