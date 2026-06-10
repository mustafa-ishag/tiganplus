<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\User\ValueObjects\Username;

/**
 * حدث تحديث مستخدم
 * User Updated Event
 */
final class UserUpdated implements DomainEvent
{
    private Id $userId;
    private Username $username;
    private DateTime $occurredOn;

    public function __construct(Id $userId, Username $username)
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->occurredOn = DateTime::now();
    }

    public function userId(): Id
    {
        return $this->userId;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.updated';
    }

    public function eventData(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'username' => $this->username->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
