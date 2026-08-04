<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrderType\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrderType\ValueObjects\TypeCode;

/**
 * حدث تحديث نوع أمر عمل
 * Work Order Type Updated Event
 */
final class WorkOrderTypeUpdated implements DomainEvent
{
    private DateTime $occurredOn;

    public function __construct(
        private readonly Id $typeId,
        private readonly TypeCode $code
    ) {
        $this->occurredOn = DateTime::now();
    }

    public function typeId(): Id
    {
        return $this->typeId;
    }

    public function code(): TypeCode
    {
        return $this->code;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'work_order_type.updated';
    }

    public function eventData(): array
    {
        return [
            'type_id' => $this->typeId->value(),
            'code' => $this->code->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
