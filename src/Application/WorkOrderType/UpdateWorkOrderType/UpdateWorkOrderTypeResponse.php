<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrderType\UpdateWorkOrderType;

/**
 * استجابة تحديث نوع أمر عمل
 * Update Work Order Type Response
 */
final class UpdateWorkOrderTypeResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $message
    ) {
    }
}
