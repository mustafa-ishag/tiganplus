<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * التاريخ والوقت
 * DateTime Value Object
 */
final class DateTime
{
    private DateTimeImmutable $value;

    public function __construct(?string $value = null)
    {
        if ($value === null) {
            $this->value = new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh'));
        } else {
            try {
                $this->value = new DateTimeImmutable($value, new DateTimeZone('Asia/Riyadh'));
            } catch (\Exception $e) {
                throw new InvalidArgumentException('تاريخ غير صحيح: ' . $value);
            }
        }
    }

    public static function now(): self
    {
        return new self();
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function format(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->value->format($format);
    }

    public function toDateString(): string
    {
        return $this->value->format('Y-m-d');
    }

    public function toTimeString(): string
    {
        return $this->value->format('H:i:s');
    }

    public function toArabicDate(): string
    {
        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];

        $day = $this->value->format('j');
        $month = $months[(int)$this->value->format('n')];
        $year = $this->value->format('Y');

        return "$day $month $year";
    }

    public function isAfter(DateTime $other): bool
    {
        return $this->value > $other->value;
    }

    public function isBefore(DateTime $other): bool
    {
        return $this->value < $other->value;
    }

    public function equals(DateTime $other): bool
    {
        return $this->value == $other->value;
    }

    public function addDays(int $days): self
    {
        return new self($this->value->modify("+$days days")->format('Y-m-d H:i:s'));
    }

    public function subDays(int $days): self
    {
        return new self($this->value->modify("-$days days")->format('Y-m-d H:i:s'));
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
