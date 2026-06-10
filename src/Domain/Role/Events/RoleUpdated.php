<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\Role\ValueObjects\RoleName;

/**
 * حدث تحديث دور
 * Role Updated Event
 */
final class RoleUpdated implements DomainEvent
{
    private Id $roleId;
    private RoleName $name;
    private DateTime $occurredOn;

    public function __construct(Id $roleId, RoleName $name)
    {
        $this->roleId = $roleId;
        $this->name = $name;
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

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'role.updated';
    }

    public function eventData(): array
    {
        return [
            'role_id' => $this->roleId->value(),
            'name' => $this->name->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
