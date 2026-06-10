<?php

declare(strict_types=1);

namespace EtganERP\Domain\Shared;

/**
 * واجهة حدث المجال
 * Domain Event Interface
 */
interface DomainEventInterface
{
    public function occurredOn(): \DateTimeImmutable;
}
