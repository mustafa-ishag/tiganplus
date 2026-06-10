<?php

declare(strict_types=1);

namespace EtganERP\Application\Branch\UpdateBranch;

/**
 * استجابة تحديث فرع
 * Update Branch Response
 */
final class UpdateBranchResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $message
    ) {
    }
}
