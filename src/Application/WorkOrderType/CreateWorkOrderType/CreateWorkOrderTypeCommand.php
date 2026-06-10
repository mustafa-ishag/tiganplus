<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrderType\CreateWorkOrderType;

/**
 * أمر إنشاء نوع أمر عمل
 * Create Work Order Type Command
 */
final class CreateWorkOrderTypeCommand
{
    public function __construct(
        public readonly string $code,
        public readonly ?string $description = null,
        public readonly string $status = 'active'
    ) {
    }
}
