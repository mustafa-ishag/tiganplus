<?php

declare(strict_types=1);

namespace EtganERP\Domain\WorkOrder\Events;

use EtganERP\Domain\Shared\DomainEvent;
use EtganERP\Domain\Shared\ValueObjects\Id;
use EtganERP\Domain\Shared\ValueObjects\DateTime;
use EtganERP\Domain\WorkOrder\ValueObjects\WorkOrderNumber;

/**
 * حدث إنشاء أمر عمل
 * Work Order Created Event
 */
final class WorkOrderCreated implements DomainEvent
{
    private DateTime $occurredOn;

    public function __construct(
        private readonly Id $workOrderId,
        private readonly WorkOrderNumber $workOrderNumber,
        private readonly Id $workOrderTypeId,
        private readonly Id $branchId
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

    public function workOrderTypeId(): Id
    {
        return $this->workOrderTypeId;
    }

    public function branchId(): Id
    {
        return $this->branchId;
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'work_order.created';
    }

    public function eventData(): array
    {
        return [
            'work_order_id' => $this->workOrderId->value(),
            'work_order_number' => $this->workOrderNumber->value(),
            'work_order_type_id' => $this->workOrderTypeId->value(),
            'branch_id' => $this->branchId->value(),
            'occurred_on' => $this->occurredOn->format()
        ];
    }
}
