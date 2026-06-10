<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;

/**
 * حدث تحديث أمر عمل
 * Work Order Updated Event
 */
final class WorkOrderUpdated implements DomainEvent
{
    private DateTime $occurredOn;

    public function __construct(
        private readonly Id $workOrderId,
        private readonly WorkOrderNumber $workOrderNumber
    ) {
        $this->occurredOn = DateTime::now();
    }

    public function workOrderId(): Id
    {
        return $this->workOrderId;
    }

    public function workOrderNumber(): WorkOrderNumber
    {
        return $this->workOrderNumber;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'work_order.updated';
    }

    public function eventData(): array
    {
        return [
            'work_order_id' => $this->workOrderId->value(),
            'work_order_number' => $this->workOrderNumber->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
