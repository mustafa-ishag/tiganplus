<?php

declare(strict_types=1);

namespace EtganERP\Application\Branch\UpdateBranch;

/**
 * أمر تحديث فرع
 * Update Branch Command
 */
final class UpdateBranchCommand
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description = null
    ) {
    }
}
