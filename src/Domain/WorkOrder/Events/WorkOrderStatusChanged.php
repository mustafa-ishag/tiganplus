<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\Status;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;

/**
 * حدث تغيير حالة أمر عمل
 * Work Order Status Changed Event
 */
final class WorkOrderStatusChanged implements DomainEvent
{
    private DateTime $occurredOn;

    public function __construct(
        private readonly Id $workOrderId,
        private readonly WorkOrderNumber $workOrderNumber,
        private readonly Status $oldStatus,
        private readonly Status $newStatus
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
        return 'work_order.status_changed';
    }

    public function eventData(): array
    {
        return [
            'work_order_id' => $this->workOrderId->value(),
            'work_order_number' => $this->workOrderNumber->value(),
            'old_status' => $this->oldStatus->value(),
            'new_status' => $this->newStatus->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
