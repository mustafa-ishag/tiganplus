<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\User\ValueObjects\Username;

/**
 * حدث تغيير كلمة المرور
 * User Password Changed Event
 */
final class UserPasswordChanged implements DomainEvent
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
        return 'user.password_changed';
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
