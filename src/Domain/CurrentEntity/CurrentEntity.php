<?php

declare(strict_types=1);

namespace EtganERP\Domain\CurrentEntity;

use EtganERP\Domain\WorkOrder\ValueObjects\CurrentEntityId;

final class CurrentEntity
{
    private CurrentEntityId $id;
    private string $name;
    private ?string $code;
    private ?string $description;
    private bool $isActive;

    public function __construct(
        CurrentEntityId $id,
        string $name,
        ?string $code = null,
        ?string $description = null,
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->setName($name);
        $this->code = $code;
        $this->description = $description;
        $this->isActive = $isActive;
    }

    private function setName(string $name): void
    {
        $name = trim($name);
        if (empty($name)) {
            throw new \InvalidArgumentException('اسم الجهة الحالية لا يمكن أن يكون فارغاً');
        }
        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('اسم الجهة الحالية لا يمكن أن يتجاوز 255 حرف');
        }
        $this->name = $name;
    }

    public function id(): CurrentEntityId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function updateName(string $name): void
    {
        $this->setName($name);
    }

    public function updateCode(?string $code): void
    {
        $this->code = $code;
    }

    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }
}
