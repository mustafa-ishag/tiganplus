<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\Role\ValueObjects\RoleName;

/**
 * حدث تغيير حالة الدور
 * Role Status Changed Event
 */
final class RoleStatusChanged implements DomainEvent
{
    private Id $roleId;
    private RoleName $name;
    private Status $oldStatus;
    private Status $newStatus;
    private DateTime $occurredOn;

    public function __construct(Id $roleId, RoleName $name, Status $oldStatus, Status $newStatus)
    {
        $this->roleId = $roleId;
        $this->name = $name;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->occurredOn = DateTime::now();
    }

    public function roleId(): Id
    {
        return $this->roleId;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    public function oldStatus(): Status
    {
        return $this->oldStatus;
    }

    public function newStatus(): Status
    {
        return $this->newStatus;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'role.status_changed';
    }

    public function eventData(): array
    {
        return [
            'role_id' => $this->roleId->value(),
            'name' => $this->name->value(),
            'old_status' => $this->oldStatus->value(),
            'new_status' => $this->newStatus->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
