<?php

declare(strict_types=1);

namespace EtganERP\Domain\Role\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\Role\ValueObjects\RoleName;
use EtganERP\Domain\Role\ValueObjects\RoleDescription;

/**
 * حدث إنشاء دور
 * Role Created Event
 */
final class RoleCreated implements DomainEvent
{
    private Id $roleId;
    private RoleName $name;
    private RoleDescription $description;
    private DateTime $occurredOn;

    public function __construct(Id $roleId, RoleName $name, RoleDescription $description)
    {
        $this->roleId = $roleId;
        $this->name = $name;
        $this->description = $description;
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

    public function description(): RoleDescription
    {
        return $this->description;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'role.created';
    }

    public function eventData(): array
    {
        return [
            'role_id' => $this->roleId->value(),
            'name' => $this->name->value(),
            'description' => $this->description->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
