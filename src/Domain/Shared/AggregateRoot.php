<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared;

use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;

/**
 * الجذر المجمع الأساسي
 * Base Aggregate Root
 */
abstract class AggregateRoot
{
    protected Id $id;
    protected DateTime $createdAt;
    protected ?DateTime $updatedAt = null;
    private array $domainEvents = [];

    public function __construct(Id $id, ?DateTime $createdAt = null)
    {
        $this->id = $id;
        $this->createdAt = $createdAt ?? DateTime::now();
    }

    public function id(): Id
    {
        return $this->id;
    }

    public function createdAt(): DateTime
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    protected function markAsUpdated(): void
    {
        $this->updatedAt = DateTime::now();
    }

    protected function recordDomainEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    public function equals(AggregateRoot $other): bool
    {
        return $this->id->equals($other->id) && 
               get_class($this) === get_class($other);
    }
}
