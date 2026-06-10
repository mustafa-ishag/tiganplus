<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * البريد الإلكتروني
 * Email Value Object
 */
final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = strtolower(trim($value));
    }

    private function validate(string $email): void
    {
        if (empty($email)) {
            throw new InvalidArgumentException('البريد الإلكتروني لا يمكن أن يكون فارغاً');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('البريد الإلكتروني غير صحيح');
        }

        if (strlen($email) > 255) {
            throw new InvalidArgumentException('البريد الإلكتروني طويل جداً');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
