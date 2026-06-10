<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrder\UpdateWorkOrder;

/**
 * أمر تحديث أمر عمل
 * Update Work Order Command
 */
final class UpdateWorkOrderCommand
{
    public function __construct(
        public readonly int $id,
        public readonly int $workOrderTypeId,
        public readonly string $department,
        public readonly ?int $currentEntityId = null,
        public readonly ?string $assignmentDate = null,
        public readonly ?string $receiptDate = null,
        public readonly ?float $estimatedValue = null,
        public readonly ?float $actualValue = null,
        public readonly ?string $disbursementStatus = null,
        public readonly ?string $notes = null
    ) {
    }
}
