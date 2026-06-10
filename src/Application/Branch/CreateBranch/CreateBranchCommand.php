<?php

declare(strict_types=1);

namespace EtganERP\Application\Branch\CreateBranch;

/**
 * أمر إنشاء فرع
 * Create Branch Command
 */
final class CreateBranchCommand
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description = null
    ) {
    }
}
