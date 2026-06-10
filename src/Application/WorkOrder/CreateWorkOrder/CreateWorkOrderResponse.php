<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrder\CreateWorkOrder;

/**
 * استجابة إنشاء أمر عمل
 * Create Work Order Response
 */
final class CreateWorkOrderResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $workOrderNumber,
        public readonly string $message
    ) {
    }
}
