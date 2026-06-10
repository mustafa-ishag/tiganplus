<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * المبلغ المالي
 * Money Value Object
 */
final class Money
{
    private float $amount;
    private string $currency;

    public function __construct(float $amount, string $currency = 'SAR')
    {
        $this->validate($amount, $currency);
        $this->amount = round($amount, 2);
        $this->currency = $currency;
    }

    private function validate(float $amount, string $currency): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('المبلغ لا يمكن أن يكون سالباً');
        }

        if ($amount > 999999999.99) {
            throw new InvalidArgumentException('المبلغ كبير جداً');
        }

        if (empty($currency)) {
            throw new InvalidArgumentException('العملة لا يمكن أن تكون فارغة');
        }
    }

    public static function zero(string $currency = 'SAR'): self
    {
        return new self(0.0, $currency);
    }

    public static function fromString(string $amount, string $currency = 'SAR'): self
    {
        $cleanAmount = str_replace([',', ' '], '', $amount);
        $floatAmount = (float) $cleanAmount;

        return new self($floatAmount, $currency);
    }

    public static function fromFloat(float $amount, string $currency = 'SAR'): self
    {
        return new self($amount, $currency);
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function toFloat(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('لا يمكن جمع مبالغ بعملات مختلفة');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('لا يمكن طرح مبالغ بعملات مختلفة');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0.0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0.0;
    }

    public function isGreaterThan(Money $other): bool
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('لا يمكن مقارنة مبالغ بعملات مختلفة');
        }

        return $this->amount > $other->amount;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function format(): string
    {
        return number_format($this->amount, 2, '.', ',') . ' ' . $this->currency;
    }

    public function formatArabic(): string
    {
        return number_format($this->amount, 2, '.', ',') . ' ريال';
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
