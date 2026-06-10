<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\ValueObjects;

use InvalidArgumentException;

/**
 * القسم
 * Department Value Object
 */
final class Department
{
    public const CONNECTIONS = 'connections';
    public const PROJECTS = 'projects';

    private string $value;

    public function __construct(string $value)
    {
        $this->validate($value);
        $this->value = $value;
    }

    private function validate(string $department): void
    {
        $validDepartments = [
            self::CONNECTIONS,
            self::PROJECTS
        ];

        if (!in_array($department, $validDepartments, true)) {
            throw new InvalidArgumentException('قسم غير صحيح: ' . $department);
        }
    }

    public static function connections(): self
    {
        return new self(self::CONNECTIONS);
    }

    public static function projects(): self
    {
        return new self(self::PROJECTS);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isConnections(): bool
    {
        return $this->value === self::CONNECTIONS;
    }

    public function isProjects(): bool
    {
        return $this->value === self::PROJECTS;
    }

    public function equals(Department $other): bool
    {
        return $this->value === $other->value;
    }

    public function toArabic(): string
    {
        return match ($this->value) {
            self::CONNECTIONS => 'التوصيلات',
            self::PROJECTS => 'المشاريع',
            default => $this->value
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
