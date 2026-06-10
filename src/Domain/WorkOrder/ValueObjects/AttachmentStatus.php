<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * حالة المرفق
 * Attachment Status Value Object
 */
final class AttachmentStatus
{
    public const ATTACHED = 'attached';
    public const NOT_ATTACHED = 'not_attached';
    public const NOT_APPLICABLE = 'not_applicable';

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $status): void
    {
        $validStatuses = [
            self::ATTACHED,
            self::NOT_ATTACHED,
            self::NOT_APPLICABLE
        ];

        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('حالة مرفق غير صحيحة: ' . $status);
        }
    }

    public static function attached(): self
    {
        return new self(self::ATTACHED);
    }

    public static function notAttached(): self
    {
        return new self(self::NOT_ATTACHED);
    }

    public static function notApplicable(): self
    {
        return new self(self::NOT_APPLICABLE);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isAttached(): bool
    {
        return $this->value === self::ATTACHED;
    }

    public function isNotAttached(): bool
    {
        return $this->value === self::NOT_ATTACHED;
    }

    public function isNotApplicable(): bool
    {
        return $this->value === self::NOT_APPLICABLE;
    }

    public function equals(AttachmentStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function toArabic(): string
    {
        return match ($this->value) {
            self::ATTACHED => 'مرفق',
            self::NOT_ATTACHED => 'غير مرفق',
            self::NOT_APPLICABLE => 'لا ينطبق',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
