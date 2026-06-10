<?php

declare(strict_types=1);

namespace EtganERP\Domain\User\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\User\ValueObjects\Username;

/**
 * حدث تغيير حالة المستخدم
 * User Status Changed Event
 */
final class UserStatusChanged implements DomainEvent
{
    private Id $userId;
    private Username $username;
    private Status $oldStatus;
    private Status $newStatus;
    private DateTime $occurredOn;

    public function __construct(Id $userId, Username $username, Status $oldStatus, Status $newStatus)
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
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
        return 'user.status_changed';
    }

    public function eventData(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'username' => $this->username->value(),
            'old_status' => $this->oldStatus->value(),
            'new_status' => $this->newStatus->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
