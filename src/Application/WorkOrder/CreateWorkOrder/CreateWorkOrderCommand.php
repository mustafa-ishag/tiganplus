<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrder\CreateWorkOrder;

/**
 * أمر إنشاء أمر عمل
 * Create Work Order Command
 */
final class CreateWorkOrderCommand
{
    public function __construct(
        public readonly string $workOrderNumber,
        public readonly int $workOrderTypeId,
        public readonly string $department,
        public readonly int $branchId,
        public readonly ?int $currentEntityId = null,
        public readonly ?string $location = null,
        public readonly ?string $assignmentDate = null,
        public readonly ?string $receiptDate = null,
        public readonly ?float $estimatedValue = null,
        public readonly ?string $notes = null
    ) {
    }
}
