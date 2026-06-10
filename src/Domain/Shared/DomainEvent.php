<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared;

use EtganERP\Domain\Shared\ValueObjects\DateTime;

/**
 * حدث المجال
 * Domain Event Interface
 */
interface DomainEvent
{
    public function occurredOn(): DateTime;
    public function eventName(): string;
    public function eventData(): array;
}
