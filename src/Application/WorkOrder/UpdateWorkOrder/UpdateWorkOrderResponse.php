<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrder\UpdateWorkOrder;

/**
 * استجابة تحديث أمر عمل
 * Update Work Order Response
 */
final class UpdateWorkOrderResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $workOrderNumber,
        public readonly string $message
    ) {
    }
}
