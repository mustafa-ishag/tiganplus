<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrderType\UpdateWorkOrderType;

/**
 * أمر تحديث نوع أمر عمل
 * Update Work Order Type Command
 */
final class UpdateWorkOrderTypeCommand
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $description = null,
        public readonly string $status = 'active'
    ) {
    }
}
