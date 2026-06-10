<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role;

use EtganERP\Domain\Shared\AggregateRoot;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\Role\ValueObjects\RoleName;
use EtganERP\Domain\Role\ValueObjects\RoleDescription;
use EtganERP\Domain\Role\Events\RoleCreated;
use EtganERP\Domain\Role\Events\RoleUpdated;
use EtganERP\Domain\Role\Events\RoleStatusChanged;

/**
 * كيان الدور
 * Role Entity
 */
final class Role extends AggregateRoot
{
    private RoleName $name;
    private RoleDescription $description;
    private Status $status;
    private array $permissions = [];

    public function __construct(
        Id $id,
        RoleName $name,
        RoleDescription $description,
        ?Status $status = null,
        ?DateTime $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
        
        $this->name = $name;
        $this->description = $description;
        $this->status = $status ?? Status::active();

        $this->recordDomainEvent(new RoleCreated($this->id, $this->name, $this->description));
    }

    public static function create(
        Id $id,
        RoleName $name,
        RoleDescription $description
    ): self {
        return new self($id, $name, $description);
    }

    // Getters
    public function name(): RoleName
    {
        return $this->name;
    }

    public function description(): RoleDescription
    {
        return $this->description;
    }

    public function status(): Status
    {
        return $this->status;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    // Business Methods
    public function updateDetails(RoleName $name, RoleDescription $description): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->markAsUpdated();

        $this->recordDomainEvent(new RoleUpdated($this->id, $this->name));
    }

    public function activate(): void
    {
        if ($this->status->isActive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::active();
        $this->markAsUpdated();

        $this->recordDomainEvent(new RoleStatusChanged($this->id, $this->name, $oldStatus, $this->status));
    }

    public function deactivate(): void
    {
        if ($this->status->isInactive()) {
            return;
        }

        $oldStatus = $this->status;
        $this->status = Status::inactive();
        $this->markAsUpdated();

        $this->recordDomainEvent(new RoleStatusChanged($this->id, $this->name, $oldStatus, $this->status));
    }

    public function assignPermissions(array $permissionIds): void
    {
        $this->permissions = array_unique($permissionIds);
        $this->markAsUpdated();
    }

    public function addPermission(Id $permissionId): void
    {
        if (!in_array($permissionId->value(), $this->permissions, true)) {
            $this->permissions[] = $permissionId->value();
            $this->markAsUpdated();
        }
    }

    public function removePermission(Id $permissionId): void
    {
        $key = array_search($permissionId->value(), $this->permissions, true);
        if ($key !== false) {
            unset($this->permissions[$key]);
            $this->permissions = array_values($this->permissions);
            $this->markAsUpdated();
        }
    }

    public function hasPermission(Id $permissionId): bool
    {
        return in_array($permissionId->value(), $this->permissions, true);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
