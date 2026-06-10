<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\User\ValueObjects\Username;
use EtganERP\Domain\User\ValueObjects\FullName;

/**
 * حدث إنشاء مستخدم
 * User Created Event
 */
final class UserCreated implements DomainEvent
{
    private Id $userId;
    private Username $username;
    private FullName $fullName;
    private DateTime $occurredOn;

    public function __construct(Id $userId, Username $username, FullName $fullName)
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->fullName = $fullName;
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

    public function fullName(): FullName
    {
        return $this->fullName;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.created';
    }

    public function eventData(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'username' => $this->username->value(),
            'full_name' => $this->fullName->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
