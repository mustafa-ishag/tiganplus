<?php

declare(strict_types=1);

namespace EtganERP\Application\WorkOrderType\CreateWorkOrderType;

/**
 * استجابة إنشاء نوع أمر عمل
 * Create Work Order Type Response
 */
final class CreateWorkOrderTypeResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $message
    ) {
    }
}
