<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrderType\Events;

use EtganERP\Domain\Shared\DomainEventInterface;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeCode;

/**
 * حدث تحديث نوع أمر عمل
 * Work Order Type Updated Event
 */
final class WorkOrderTypeUpdated implements DomainEventInterface
{
    public function __construct(
        private readonly Id $typeId,
        private readonly TypeCode $code
    ) {
    }

    public function typeId(): Id
    {
        return $this->typeId;
    }

    public function code(): TypeCode
    {
        return $this->code;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
